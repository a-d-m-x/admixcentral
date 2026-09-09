#!/usr/bin/env python3
"""Host preparation for existing AdmixCentral deployments; Python standard library only.

Never installs application code, builds assets, migrates data or switches queue/session drivers.
"""
import argparse
import configparser
import datetime
import fcntl
import glob
import json
import os
from pathlib import Path
import pwd
import re
import shlex
import shutil
import stat
import subprocess
import sys
import tempfile
import time

SAFE_PATH = '/usr/sbin:/usr/bin:/sbin:/bin'
REQUIRED = {'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl',
            'pcre', 'pdo', 'session', 'tokenizer', 'xml', 'redis', 'pcntl', 'posix', 'zip'}
MARKER = '# Managed by AdmixCentral host-readiness wizard'


class ReadinessError(Exception):
    pass


def say(message):
    print(''.join(c if c in '\n\t' or c.isprintable() else '?' for c in str(message)), flush=True)


def confirm(message):
    if not sys.stdin.isatty():
        raise ReadinessError('Changes require an interactive terminal. Use --check for a read-only report.')
    return input(message + ' [y/N] ').strip().lower() in {'y', 'yes'}


def ask(message, default):
    answer = input(f'{message} [{default}]: ').strip()
    return answer or str(default)


def parse_os(text):
    values = {}
    for line in text.splitlines():
        if re.match(r'^[A-Z_]+=', line):
            key, raw = line.split('=', 1)
            parts = shlex.split(raw, comments=True)
            values[key] = parts[0] if parts else ''
    ids = [values.get('ID', '')] + values.get('ID_LIKE', '').split()
    for family, matches in [('ubuntu', {'ubuntu', 'debian'}), ('fedora', {'fedora'}), ('arch', {'arch'})]:
        if any(value in matches for value in ids):
            return family
    raise ReadinessError('Unsupported OS. Supported families: Ubuntu/Debian, Fedora, Arch (systemd).')


def safe_path(value):
    # These paths are also inserted into Supervisor and cron syntax; reject ambiguity.
    path = Path(value).absolute()
    if not re.fullmatch(r'/[A-Za-z0-9_./+-]+', str(path)) or '..' in path.parts:
        raise ReadinessError('Use an absolute path containing only letters, digits, / . _ + - (no spaces).')
    return path


def read_ini(text):
    parser = configparser.ConfigParser(interpolation=None, strict=True)
    parser.read_string(text)
    return parser


def pool_user(text):
    parser = read_ini(text)
    users = {parser.get(section, 'user') for section in parser.sections() if parser.has_option(section, 'user')}
    if len(users) != 1:
        raise ReadinessError('Cannot identify one FPM pool user. Select the app pool with --fpm-pool.')
    return users.pop().strip()


def update_ini(text, section, updates):
    parser = read_ini(text)
    if section not in parser:
        raise ReadinessError('Expected configuration section is missing: ' + section)
    current, seen, output = None, set(), []
    for line in text.splitlines(keepends=True):
        match = re.match(r'^\s*\[([^]]+)\]', line)
        if match:
            if current == section:
                output.extend(f'{key}={value}\n' for key, value in updates.items() if key not in seen)
            current = match[1]
        entry = re.match(r'^\s*([\w.]+)\s*=', line)
        if current == section and entry and entry[1] in updates:
            key = entry[1]
            output.append(f'{key}={updates[key]}\n')
            seen.add(key)
        else:
            output.append(line)
    if current == section:
        if output and not output[-1].endswith('\n'):
            output[-1] += '\n'
        output.extend(f'{key}={value}\n' for key, value in updates.items() if key not in seen)
    return ''.join(output)


def env_records(text):
    lines = text.splitlines(keepends=True)
    records, start, key, quote = [], None, None, None
    for i, line in enumerate(lines):
        if start is None:
            match = re.match(r'^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*', line)
            if not match:
                continue
            start, key, body = i, match[1], line[match.end():]
        else:
            body = line
        escaped = False
        for char in body:
            if escaped:
                escaped = False
            elif char == '\\' and quote == '"':
                escaped = True
            elif quote:
                if char == quote:
                    quote = None
            elif char in {'"', "'"}:
                quote = char
            elif char == '#':
                break
        if quote is None:
            records.append((key, start, i + 1))
            start = None
    if start is not None:
        raise ReadinessError('Unterminated quoted .env value; repair it before changing environment settings.')
    return lines, records


def update_env(text, values):
    # Only these hardening switches belong to this wizard. Credentials/drivers are immutable here.
    if set(values) - {'APP_DEBUG', 'SESSION_SECURE_COOKIE', 'SESSION_ENCRYPT'}:
        raise ReadinessError('This wizard does not change credentials, URLs or application drivers.')
    lines, records = env_records(text)
    removed = {i for key, first, end in records if key in values for i in range(first, end)}
    result = ''.join(line for i, line in enumerate(lines) if i not in removed).rstrip('\n') + '\n'
    for key, value in values.items():
        if value not in {'true', 'false'}:
            raise ReadinessError('Only boolean hardening settings are allowed.')
        result += f'{key}={value}\n'
    return result


def packages(family, version, local_server=False):
    if family == 'ubuntu':
        prefix = f'php{version}' if version else 'php'
        result = [prefix + suffix for suffix in ['-cli', '-fpm', '-curl', '-mbstring', '-xml', '-zip',
                                                 '-mysql', '-sqlite3', '-bcmath', '-intl', '-redis']]
        result += ['supervisor', 'cron', 'ca-certificates']
        return result + (['redis-server'] if local_server else [])
    if family == 'fedora':
        return ['php-cli', 'php-fpm', 'php-common', 'php-mbstring', 'php-xml', 'php-pecl-zip',
                'php-mysqlnd', 'php-pdo', 'php-process', 'php-bcmath', 'php-intl',
                'php-pecl-redis6', 'supervisor', 'cronie', 'ca-certificates'] + (['valkey'] if local_server else [])
    return ['php', 'php-fpm', 'php-redis', 'php-sqlite', 'php-intl', 'supervisor', 'cronie', 'ca-certificates'] + (
        ['valkey'] if local_server else [])


def install_command(family, names):
    return {'ubuntu': ['apt-get', 'install', '--no-install-recommends'],
            'fedora': ['dnf', 'install'], 'arch': ['pacman', '-S', '--needed']}[family] + names


def redis_harden(text):
    # Restrict to a dedicated, standalone local instance; do not reinterpret includes or clustering.
    forbidden = r'^\s*(?:include|replicaof|slaveof|cluster-enabled|sentinel|tls-port)\s+'
    if re.search(forbidden, text, re.M):
        raise ReadinessError('Custom Redis/Valkey includes, replication, TLS or clustering: harden manually.')
    values = {'bind': '127.0.0.1 -::1', 'protected-mode': 'yes', 'maxmemory-policy': 'noeviction'}
    output = [line for line in text.splitlines() if not re.match(
        r'^\s*(?:bind|protected-mode|maxmemory-policy)\s+', line)]
    return '\n'.join(output).rstrip() + '\n' + '\n'.join(f'{k} {v}' for k, v in values.items()) + '\n'


def worker_config(install, php, user, count):
    return f'''{MARKER}
[program:admix-worker]
process_name=%(program_name)s_%(process_num)02d
command={php} {install}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory={install}
user={user}
numprocs={count}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile={install}/storage/logs/worker-%(process_num)02d.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
'''


def reverb_config(install, php, user):
    return f'''{MARKER}
[program:admix-reverb]
command={php} {install}/artisan reverb:start
directory={install}
user={user}
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=60
redirect_stderr=true
stdout_logfile={install}/storage/logs/reverb.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
'''


def cron_config(install, php, user):
    return f'''{MARKER}
SHELL=/bin/sh
PATH={SAFE_PATH}
* * * * * {user} cd {install} && {php} artisan schedule:run >> /dev/null 2>&1
'''


def atomic_write(path, data, mode, uid, gid, attrs=None):
    path = Path(path)
    if path.is_symlink():
        raise ReadinessError('Refusing to overwrite a symlink: ' + str(path))
    fd, temporary = tempfile.mkstemp(prefix='.admix-host-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(data)
            stream.flush()
            os.fsync(stream.fileno())
        os.chown(temporary, uid, gid)
        os.chmod(temporary, mode)
        for key, value in (attrs or {}).items():
            os.setxattr(temporary, key, value)
        os.replace(temporary, path)
    finally:
        Path(temporary).unlink(missing_ok=True)


class Backups:
    def __init__(self, base):
        self.base = Path(base)
        self.sequence = 0

    def save(self, path):
        path = Path(path)
        if path.is_symlink() or (path.exists() and not path.is_file()):
            raise ReadinessError('Refusing a symlink or non-regular configuration file: ' + str(path))
        self.sequence += 1
        prefix = self.base / str(self.sequence)
        info = {'path': str(path), 'existed': path.exists()}
        if path.exists():
            st = path.stat()
            info.update(mode=stat.S_IMODE(st.st_mode), uid=st.st_uid, gid=st.st_gid)
            shutil.copy2(path, str(prefix) + '.original')
            os.chmod(str(prefix) + '.original', 0o600)
        (Path(str(prefix) + '.json')).write_text(json.dumps(info) + '\n')
        return info, prefix

    def restore(self, snapshot):
        info, prefix = snapshot
        path = Path(info['path'])
        if info['existed']:
            source = Path(str(prefix) + '.original')
            attrs = {key: os.getxattr(source, key) for key in os.listxattr(source)}
            atomic_write(path, source.read_bytes(), info['mode'], info['uid'], info['gid'], attrs)
        else:
            path.unlink(missing_ok=True)

    def change(self, path, content, validate, activate=None, mode=None, owner=None, recover=None):
        path = Path(path)
        data = content.encode() if isinstance(content, str) else content
        if (path.exists() and not path.is_symlink() and path.read_bytes() == data
                and (mode is None or stat.S_IMODE(path.stat().st_mode) == mode)
                and (owner is None or (path.stat().st_uid, path.stat().st_gid) == owner)):
            say('Already configured: ' + str(path))
            return False
        info, prefix = self.save(path)
        attrs = {key: os.getxattr(path, key) for key in os.listxattr(path)} if path.exists() else {}
        try:
            atomic_write(path, data, mode if mode is not None else info.get('mode', 0o644),
                         owner[0] if owner else info.get('uid', 0),
                         owner[1] if owner else info.get('gid', 0), attrs)
            validate()
            if activate:
                activate()
        except BaseException:
            if info['existed']:
                atomic_write(path, Path(str(prefix) + '.original').read_bytes(),
                             info['mode'], info['uid'], info['gid'], attrs)
            else:
                path.unlink(missing_ok=True)
            say('Restored previous file after failure: ' + str(path))
            if recover or activate:
                try:
                    (recover or activate)()
                except Exception:
                    say('Service recovery failed. Inspect its journal before continuing.')
            raise
        return True


class Runner:
    def __init__(self):
        self.log = None

    def run(self, args, check=True, user=None, input_text=None, interactive=False, timeout=45):
        command = [str(arg) for arg in args]
        if user:
            if pwd.getpwnam(user).pw_uid == 0:
                raise ReadinessError('Application PHP must never run as root.')
            clean = ['env', '-i', 'PATH=' + SAFE_PATH, 'USER=' + user, 'LOGNAME=' + user]
            if os.geteuid() == 0:
                command = ['runuser', '-u', user, '--'] + clean + command
            elif pwd.getpwuid(os.geteuid()).pw_name != user:
                raise ReadinessError('Run with sudo to inspect the application as its runtime user.')
            else:
                command = clean + command
        try:
            result = subprocess.run(command, cwd='/', env={'PATH': SAFE_PATH, 'LANG': 'C.UTF-8'},
                                    input=input_text, text=True, capture_output=not interactive,
                                    timeout=timeout)
        except (OSError, subprocess.TimeoutExpired) as exc:
            raise ReadinessError(f'{args[0]} could not complete ({type(exc).__name__}).') from None
        if self.log:
            # Never log PHP source, environment values, command output or connection exceptions.
            self.log.write(f'{datetime.datetime.now().isoformat()} {Path(str(args[0])).name}: exit {result.returncode}\n')
            self.log.flush()
        if check and result.returncode:
            raise ReadinessError(f'{Path(str(args[0])).name} failed (exit {result.returncode}). Inspect its service journal/configuration.')
        return result


class Wizard:
    def __init__(self, args):
        self.args, self.run = args, Runner()
        self.install = safe_path(str(safe_path(args.install_dir).resolve()))
        self.family = parse_os(Path('/etc/os-release').read_text())
        self.php, self.version, self.fpm, self.pool, self.user = None, '', None, None, None
        self.fpm_service = None
        self.backups = None
        self.issues = []
        self.report = {}
        self.lock = None

    def issue(self, message):
        self.issues.append(message)
        say('[ATTENTION] ' + message)

    def unit_exists(self, name):
        result = self.run.run(['systemctl', 'show', name, '--property=LoadState', '--value'], check=False)
        return result.returncode == 0 and result.stdout.strip() not in {'', 'not-found'}

    def active(self, name):
        return self.run.run(['systemctl', 'is-active', '--quiet', name], check=False).returncode == 0

    def service(self, choices):
        existing = [name for name in choices if self.unit_exists(name)]
        return next((name for name in existing if self.active(name)), existing[0] if existing else None)

    def detect_php(self):
        if self.args.php_version and not re.fullmatch(r'8\.\d+', self.args.php_version):
            raise ReadinessError('PHP version must have the form 8.x.')
        if self.args.fpm_service and not re.fullmatch(r'[A-Za-z0-9_.@-]+\.service', self.args.fpm_service):
            raise ReadinessError('FPM service must be a systemd unit name ending in .service.')
        selected = self.args.php_bin or ('php' + self.args.php_version if self.args.php_version else 'php')
        found = shutil.which(selected, path=SAFE_PATH)
        self.php = str(safe_path(found)) if found else None
        if not self.php:
            return
        result = self.run.run([self.php, '-r', 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;'])
        if not re.fullmatch(r'8\.\d+', result.stdout):
            raise ReadinessError('AdmixCentral requires PHP 8.2+ within PHP 8; select a supported PHP binary.')
        self.version = result.stdout
        if tuple(map(int, self.version.split('.'))) < (8, 2):
            raise ReadinessError('PHP 8.2+ is required. Upgrade the host/PHP through trusted distro repositories first; no PPA is added automatically.')
        fpm_name = self.args.fpm_bin or ('php-fpm' + self.version if self.family == 'ubuntu' else 'php-fpm')
        self.fpm = shutil.which(fpm_name, path=SAFE_PATH)
        if self.fpm:
            self.fpm = str(safe_path(self.fpm))
        self.fpm_service = self.args.fpm_service or ('php' + self.version + '-fpm.service' if self.family == 'ubuntu' else 'php-fpm.service')
        candidates = [Path(self.args.fpm_pool)] if self.args.fpm_pool else [
            Path('/etc/php') / self.version / 'fpm/pool.d/www.conf',
            Path('/etc/php-fpm.d/www.conf'), Path('/etc/php/php-fpm.d/www.conf')]
        self.pool = next((path for path in candidates if path.is_file()), None)
        self.user = self.args.runtime_user or (pool_user(self.pool.read_text()) if self.pool else None)
        if self.user:
            if not re.fullmatch(r'[a-z_][a-z0-9_-]*\$?', self.user) or pwd.getpwnam(self.user).pw_uid == 0:
                raise ReadinessError('Select a valid non-root FPM runtime user.')

    def probe(self, ping=False):
        if not self.php or not self.user:
            return {}
        source = Path(__file__).with_name('probe.php').read_text().removeprefix('<?php')
        result = self.run.run([self.php, '-r', source, '--', str(self.install), 'ping' if ping else 'inspect'],
                              user=self.user, check=False, timeout=30)
        try:
            report = json.loads(result.stdout)
        except (ValueError, TypeError):
            report = {}
        return report if report.get('ok') else {}

    def supervisor(self):
        choices = [Path('/etc/supervisor/supervisord.conf'), Path('/etc/supervisord.conf')]
        main = next((path for path in choices if path.is_file()), None)
        if not main:
            return None, [], None
        config = read_ini(main.read_text())
        patterns = config.get('include', 'files', fallback='').replace('%(here)s', str(main.parent)).split()
        paths, destination = set(), None
        for pattern in patterns:
            if not pattern.startswith('/'):
                pattern = str(main.parent / pattern)
            if '%' in pattern:
                raise ReadinessError('Custom Supervisor include expansion: configure its program files manually.')
            paths.update(Path(path) for path in glob.glob(pattern))
            if Path(pattern).name in {'*.conf', '*.ini'} and Path(pattern).parent.is_dir() and destination is None:
                destination = (Path(pattern).parent, Path(pattern).suffix)
        # Include the main file so embedded Admix programs are detected and never duplicated.
        return main, [main] + sorted(paths), destination

    def programs(self, name):
        main, paths, destination = self.supervisor()
        matches = []
        for path in paths:
            config = read_ini(path.read_text())
            if 'program:' + name in config:
                matches.append(path)
        if len(matches) > 1:
            raise ReadinessError('Duplicate Supervisor definition for ' + name + '; resolve it manually.')
        return main, matches[0] if matches else None, destination

    def alternate_services(self, action):
        matches = []
        for base in ['/etc/systemd/system', '/usr/lib/systemd/system']:
            for path in Path(base).glob('*.service'):
                if path.is_file() and any('artisan' in line and action in line and not line.lstrip().startswith('#')
                                         for line in path.read_text(errors='replace').splitlines()):
                    matches.append(str(path))
        _, paths, _ = self.supervisor()
        for path in paths:
            parser = read_ini(path.read_text())
            for section in parser.sections():
                command = parser.get(section, 'command', fallback='')
                if section.startswith('program:') and 'artisan' in command and action in command:
                    matches.append(str(path) + ' [' + section + ']')
        return matches

    def scheduler_entries(self):
        matches = []
        paths = [Path('/etc/crontab')] + list(Path('/etc/cron.d').glob('*'))
        for base in ['/etc/systemd/system', '/usr/lib/systemd/system']:
            paths.extend(Path(base).glob('*.service'))
        for path in paths:
            if path.is_file():
                text = path.read_text(errors='replace')
                if any('schedule:' in line and ('artisan' in line) and not line.lstrip().startswith('#')
                       for line in text.splitlines()):
                    matches.append(str(path))
        if shutil.which('crontab', path=SAFE_PATH):
            for user in sorted({'root', self.user} - {None}):
                if os.geteuid() and user != pwd.getpwuid(os.geteuid()).pw_name:
                    continue
                args = ['crontab', '-l'] if os.geteuid() else ['crontab', '-u', user, '-l']
                result = self.run.run(args, check=False)
                if any('artisan' in line and 'schedule:' in line and not line.lstrip().startswith('#')
                       for line in result.stdout.splitlines()):
                    matches.append('crontab for ' + user)
        return matches

    def check(self):
        self.issues = []
        say('\nHost readiness: ' + self.family + ' | ' + str(self.install))
        self.detect_php()
        if not self.php:
            self.issue('PHP CLI is missing; install the distribution PHP packages.')
        elif not self.fpm or not self.pool or not self.user:
            self.issue('PHP-FPM binary, pool or runtime user is missing; use the explicit FPM options for custom layouts.')
        else:
            say('PHP ' + self.version + '; runtime user ' + self.user + '; pool ' + str(self.pool))
            fpm_modules = self.run.run([self.fpm, '-m'], check=False)
            fpm_version = self.run.run([self.fpm, '-v'], check=False)
            if not re.search(r'PHP ' + re.escape(self.version) + r'\.', fpm_version.stdout):
                self.issue('PHP CLI and FPM versions differ; install/select matching binaries before preparing services.')
            missing = REQUIRED - {line.strip().lower() for line in fpm_modules.stdout.splitlines()}
            # pcntl is a CLI queue-worker requirement, and may intentionally be absent from FPM.
            missing.discard('pcntl')
            if missing:
                self.issue('FPM extensions missing: ' + ', '.join(sorted(missing)))
            if self.run.run([self.fpm, '-t'], check=False).returncode:
                self.issue('PHP-FPM configuration validation failed.')
            if not self.active(self.fpm_service):
                self.issue('PHP-FPM is not active: ' + self.fpm_service)
        self.report = self.probe(ping=True)
        if not self.report:
            self.issue('Application configuration could not be inspected as its runtime user. Check vendor/, .env permissions and PHP compatibility.')
        else:
            missing = REQUIRED - {name.lower() for name in self.report['modules']}
            db_module = {'mysql': 'pdo_mysql', 'mariadb': 'pdo_mysql', 'sqlite': 'pdo_sqlite', 'pgsql': 'pdo_pgsql'}.get(self.report.get('database'))
            if db_module and db_module not in {name.lower() for name in self.report['modules']}:
                missing.add(db_module)
            if missing:
                self.issue('CLI extensions missing: ' + ', '.join(sorted(missing)))
            if self.fpm and db_module:
                loaded = self.run.run([self.fpm, '-m'], check=False).stdout.lower().splitlines()
                if db_module not in loaded:
                    self.issue('FPM database extension missing: ' + db_module)
            for key in ['cache', 'session', 'queue']:
                # Avoid echoing arbitrary config strings to the terminal.
                value = str(self.report[key])
                say(f'{key} driver: ' + (value if re.fullmatch('[a-z_]+', value) else '(custom)'))
            for name, ok in self.report['redis_ping'].items():
                if not ok:
                    self.issue('Redis connection ' + name + ' failed PING using the existing application settings.')
                else:
                    say('Redis connection ' + name + ': PONG')
            for field, message in [('writable_storage', 'Runtime user cannot write storage/logs or storage/framework.'),
                                   ('writable_cache', 'Runtime user cannot write bootstrap/cache.'),
                                   ('ca_readable', 'Configured firewall CA bundle is unreadable by the runtime user.'),
                                   ('curl_pinning', 'PHP cURL lacks the public-key pinning option required for native firewall certificates.')]:
                if not self.report[field]:
                    self.issue(message)
            if self.report['config_cached']:
                self.issue('Laravel configuration is cached. Existing direct env() reads need review; wizard changes will use config:clear.')
            if self.report['debug']:
                self.issue('APP_DEBUG is enabled on this deployment.')
            if not self.report['https']:
                self.issue('APP_URL is not HTTPS. Configure the public HTTPS origin and reverse proxy before enabling secure-only cookies.')
            if not self.report['session_secure'] or not self.report['session_encrypt']:
                self.issue('Secure-only and/or encrypted session settings are not enabled.')
        env_mode = (self.install / '.env').stat().st_mode
        if env_mode & 0o007:
            self.issue('.env is accessible to other host users; restrict it to the deployment owner/runtime group after checking updater access.')
        server = self.service(['redis-server.service', 'redis.service', 'valkey.service'])
        say('Local Redis/Valkey service: ' + (server or 'none (a remote Redis service is also supported)'))
        for path in [Path('/etc/redis/redis.conf'), Path('/etc/redis.conf'), Path('/etc/valkey/valkey.conf')]:
            if path.is_file():
                text = path.read_text()
                binds = re.findall(r'^\s*bind\s+([^\n]+)', text, re.M)
                if not binds or any(token not in {'127.0.0.1', '::1', '-::1'} for token in binds[-1].split('#')[0].split()):
                    self.issue('Redis/Valkey has a non-loopback or implicit bind: ' + str(path))
                if re.search(r'^\s*protected-mode\s+no\b', text, re.M):
                    self.issue('Redis/Valkey protected mode is off: ' + str(path))
                if not re.search(r'^\s*appendonly\s+yes\b', text, re.M):
                    self.issue('Redis/Valkey AOF persistence is not enabled: review durability before storing queues/sessions there.')
        main, _, _ = self.supervisor()
        for name in ['admix-worker', 'admix-reverb']:
            _, path, _ = self.programs(name)
            if not path:
                alternatives = self.alternate_services('queue:' if name == 'admix-worker' else 'reverb:start')
                self.issue(('Review existing alternative service: ' + ', '.join(alternatives)) if alternatives else 'Supervisor program missing: ' + name)
                continue
            config = read_ini(path.read_text())['program:' + name]
            if config.get('user') != self.user:
                self.issue(name + ' does not use the selected FPM runtime user.')
            if name == 'admix-worker':
                parts = shlex.split(config.get('command', ''))
                if 'queue:work' in parts:
                    tail = parts[parts.index('queue:work') + 1:]
                    if tail and not tail[0].startswith('-') and tail[0] != self.report.get('queue'):
                        self.issue('Worker has an explicit queue connection different from QUEUE_CONNECTION; verify producers and consumers before changing either.')
            if name == 'admix-reverb' and config.get('numprocs', '1') != '1':
                self.issue('Reverb must have one process per listening port.')
            if name == 'admix-reverb' and ('--host=0.0.0.0' in config.get('command', '') or ('--host' not in config.get('command', '') and not self.report.get('reverb_loopback', False))):
                self.issue('Reverb listens on all IPv4 interfaces; use loopback when the reverse proxy is local.')
            if shutil.which('supervisorctl', path=SAFE_PATH):
                status = self.run.run(['supervisorctl', '-c', str(main), 'status', name + ':*'], check=False)
                if status.returncode or not status.stdout.strip() or any(' RUNNING ' not in line for line in status.stdout.splitlines()):
                    self.issue(name + ' is not fully RUNNING under Supervisor.')
        entries = self.scheduler_entries()
        if not entries:
            self.issue('No Laravel scheduler entry was found.')
        elif len(entries) > 1:
            self.issue('Multiple possible Laravel scheduler definitions; check for duplicate execution: ' + ', '.join(entries))
        else:
            say('Scheduler definition: ' + entries[0] + ' (verify it targets this installation)')
        for unit in [self.service(['supervisor.service', 'supervisord.service']), self.service(['cron.service', 'crond.service'])]:
            if unit and not self.active(unit):
                self.issue('Service is not active: ' + unit)
        if self.legacy_sudoers():
            self.issue('Legacy AdmixCentral sudoers grants exist; the wizard can remove that drop-in after explaining the affected web controls.')
        if shutil.which('getenforce', path=SAFE_PATH):
            state = self.run.run(['getenforce']).stdout.strip()
            say('SELinux: ' + state + '. Review the SELinux guidance in docs/HOST_READINESS.md; no policy is disabled automatically.')
        say(f'Readiness report: {len(self.issues)} item(s) need review. This is not a complete security certification.')
        return not self.issues

    def legacy_sudoers(self):
        path = Path('/etc/sudoers.d/admixcentral')
        return path.is_file() and any(line.strip() and not line.lstrip().startswith('#') for line in path.read_text().splitlines())

    def start_backups(self):
        base = Path('/var/backups/admixcentral-host')
        if base.is_symlink():
            raise ReadinessError('Backup directory must not be a symlink.')
        base.mkdir(mode=0o700, parents=True, exist_ok=True)
        if base.stat().st_uid != 0 or stat.S_IMODE(base.stat().st_mode) & 0o077:
            raise ReadinessError('Backup directory must be root-owned with mode 0700: ' + str(base))
        self.lock = os.open(base / 'wizard.lock', os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
        try:
            fcntl.flock(self.lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            raise ReadinessError('Another host wizard is already running.') from None
        directory = Path(tempfile.mkdtemp(prefix=datetime.datetime.now().strftime('%Y%m%d-%H%M%S-'), dir=base))
        self.backups = Backups(directory)
        self.run.log = (directory / 'actions.log').open('x')
        say('Private configuration backups and action results: ' + str(directory))

    def package_step(self):
        existing = self.service(['redis-server.service', 'redis.service', 'valkey.service'])
        local = False
        if not existing:
            say('\nA local Redis-compatible server is optional. Remote Redis/ACL/TLS settings will be preserved.')
            local = confirm('Install a local dedicated Redis/Valkey server on this host?')
        names = packages(self.family, self.version or self.args.php_version, local)
        say('\nPackages provide PHP-FPM, application extensions, Redis client support, Supervisor and cron.')
        say('Package scripts may start/restart services. Schedule a maintenance window. Existing package versions may be upgraded.')
        say('Proposed command: ' + shlex.join(install_command(self.family, names)))
        if self.family == 'arch':
            say('Arch requires a fully updated system. Run pacman -Syu first if needed, then rerun this wizard. No partial database refresh is performed here.')
        if confirm('Install these packages using the configured distribution repositories?'):
            if Path('/run/ostree-booted').exists():
                raise ReadinessError('Fedora Atomic requires rpm-ostree host layering and a reboot; see the host-readiness guide.')
            if self.family == 'ubuntu':
                self.run.run(['apt-get', 'update'], interactive=True, timeout=None)
            self.run.run(install_command(self.family, names), interactive=True, timeout=None)
            self.detect_php()
            if not self.php or not self.fpm or not self.user:
                raise ReadinessError('PHP/FPM/runtime discovery still fails. Specify the custom FPM options and rerun.')
        if self.php and self.fpm:
            version = self.run.run([self.fpm, '-v']).stdout
            if not re.search(r'PHP ' + re.escape(self.version) + r'\.', version):
                raise ReadinessError('Selected PHP CLI/FPM versions differ; select matching binaries before changing services.')
        self.arch_extensions()

    def arch_extensions(self):
        if self.family != 'arch' or not self.php:
            return
        modules = self.run.run([self.php, '-m']).stdout.lower().splitlines()
        directory = self.run.run([self.php, '-r', 'echo ini_get("extension_dir");']).stdout.strip()
        wanted = sorted((REQUIRED | {'pdo_mysql', 'pdo_sqlite', 'bcmath', 'intl'}) - set(modules))
        shared = [name for name in wanted if (Path(directory) / (name + '.so')).is_file()]
        if not shared:
            return
        path = Path('/etc/php/conf.d/99-admixcentral.ini')
        if path.exists() and MARKER not in path.read_text():
            raise ReadinessError('Custom 99-admixcentral.ini exists; enable missing PHP extensions manually.')
        say('\nArch ships some extensions disabled. Available missing modules: ' + ', '.join(shared))
        if confirm('Enable these modules in /etc/php/conf.d/99-admixcentral.ini?'):
            existing = path.read_text() if path.exists() else MARKER + '\n'
            content = existing + ''.join('extension=' + name + '\n' for name in shared)
            def validate():
                loaded = self.run.run([self.php, '-m']).stdout.lower().splitlines()
                if set(shared) - set(loaded):
                    raise ReadinessError('One or more selected PHP extensions did not load.')
                self.run.run([self.fpm, '-t'])
            self.backups.change(path, content, validate, lambda: self.reload_fpm())

    def reload_fpm(self):
        self.run.run([self.fpm, '-t'])
        self.run.run(['systemctl', 'reload-or-restart', self.fpm_service])
        if not self.active(self.fpm_service):
            raise ReadinessError('PHP-FPM did not become active.')

    def redis_step(self):
        server = self.service(['redis-server.service', 'redis.service', 'valkey.service'])
        if not server:
            say('No local Redis/Valkey service. Existing remote Redis settings remain in use.')
            return
        say('\nRedis/Valkey: ' + server + '. Application credentials, database numbers and data remain unchanged.')
        if not self.active(server) and confirm('Enable and start this local server using its existing configuration?'):
            self.run.run(['systemctl', 'enable', '--now', server])
            if not self.active(server):
                raise ReadinessError('Local Redis/Valkey failed to start.')
        command = self.run.run(['systemctl', 'show', server, '--property=ExecStart', '--value']).stdout
        choices = [Path('/etc/valkey/valkey.conf'), Path('/etc/redis/redis.conf'), Path('/etc/redis.conf')]
        path = next((path for path in choices if path.is_file() and re.search(re.escape(str(path)) + r'(?:\s|;|$)', command)), None)
        if not path:
            say('Custom local server layout; review its bind, ACL, persistence and eviction policy manually.')
            return
        say('Dedicated local hardening binds Redis to loopback, enables protected mode, and uses noeviction so queues/sessions are not evicted.')
        say('It requires a brief restart and disconnects clients. Non-persisted Redis data can be lost; verify a current recoverable dataset backup before proceeding.')
        say('Authentication, persistence settings and memory limits are preserved. Skip this step if durability or backup recovery is uncertain.')
        say('For a shared, replicated, clustered or TLS instance, skip and follow the guide.')
        try:
            content = redis_harden(path.read_text())
        except ReadinessError as exc:
            say(str(exc) + ' See docs/HOST_READINESS.md. Other preparation steps can continue.')
            return
        if confirm('Is this a dedicated local instance with a verified current dataset backup, and may it be restarted to apply these settings?'):
            def activate():
                self.run.run(['systemctl', 'restart', server])
                if not self.active(server):
                    raise ReadinessError('Redis/Valkey failed to start with the edited configuration.')
                if self.report.get('redis_local'):
                    result = self.probe(ping=True)
                    if not result or not all(result.get('redis_ping', {}).values()):
                        raise ReadinessError('Application Redis connectivity failed after the service change.')
            self.backups.change(path, content, lambda: None, activate)
        say('AOF durability requires a separate live enablement and backup plan; the wizard never restarts an existing dataset with appendonly newly enabled.')

    def supervisor_command(self, args, timeout=45):
        result = self.run.run(args, timeout=timeout)
        if re.search(r'(^|\n)(?:ERROR|error:)', result.stdout or ''):
            raise ReadinessError('Supervisor rejected the configuration or group update; inspect the root-owned Supervisor configuration.')
        return result

    def supervisor_step(self):
        if not self.php or not self.user:
            raise ReadinessError('A PHP binary and non-root runtime user are required before preparing app services.')
        service = self.service(['supervisor.service', 'supervisord.service'])
        if not service:
            say('Supervisor is missing; install packages or manage workers with your existing service manager.')
            return
        if not self.active(service):
            say('\nStarting Supervisor starts all autostart programs in its current configuration.')
            if confirm('Enable and start Supervisor?'):
                self.run.run(['systemctl', 'enable', '--now', service])
            else:
                return
        for name in ['admix-worker', 'admix-reverb']:
            main, path, destination = self.programs(name)
            if not path:
                alternate = self.alternate_services('queue:' if name == 'admix-worker' else 'reverb:start')
                if alternate:
                    say('Existing alternative service(s) preserved; no duplicate program created: ' + ', '.join(alternate))
                    continue
            if not main or (not path and not destination):
                say('No usable Supervisor include directory; configure ' + name + ' manually.')
                continue
            if path:
                config = read_ini(path.read_text())
                if config.sections() != ['program:' + name]:
                    say('Program is embedded in a shared configuration; leaving it for manual review: ' + str(path))
                    continue
                command = config['program:' + name].get('command', '')
                parts = shlex.split(command)
                if len(parts) < 3 or parts[1] != str(self.install / 'artisan') or parts[2] != (
                        'queue:work' if name == 'admix-worker' else 'reverb:start'):
                    say('Custom command or different installation for ' + name + '; edit manually.')
                    continue
            say('\n' + name + ': use PHP ' + self.version + ' as ' + self.user + '. Changes restart this program; running jobs may need time to finish.')
            if name == 'admix-worker':
                say('Existing connection/queue arguments are preserved. A new worker uses the current QUEUE_CONNECTION. No jobs are moved.')
            else:
                say('Reverb gets one process to avoid port conflicts. The existing host/port arguments are preserved unless you explicitly select loopback.')
            if not confirm('Prepare or adjust ' + name + '?'):
                continue
            count = 1
            if name == 'admix-worker':
                default = config['program:' + name].get('numprocs', '2') if path else '2'
                count = int(ask('Worker count (1–32; choose for available RAM and firewall load)', default))
                if not 1 <= count <= 32:
                    raise ReadinessError('Worker count must be between 1 and 32.')
            if path:
                parts[0] = self.php
                if name == 'admix-reverb' and confirm('Is the reverse proxy on this host, and should Reverb bind only to 127.0.0.1?'):
                    filtered, skip = [], False
                    for part in parts:
                        if skip:
                            skip = False
                        elif part == '--host':
                            skip = True
                        elif not part.startswith('--host='):
                            filtered.append(part)
                    parts = filtered + ['--host=127.0.0.1']
                if any(re.search(r'[\s;#%]', part) for part in parts):
                    raise ReadinessError('Complex Supervisor command arguments require manual editing.')
                updates = {'command': ' '.join(parts), 'user': self.user, 'directory': str(self.install),
                           'numprocs': str(count), 'stopasgroup': 'true', 'killasgroup': 'true',
                           'stopwaitsecs': '3600' if name == 'admix-worker' else '60'}
                updates['process_name'] = '%(program_name)s_%(process_num)02d' if name == 'admix-worker' else '%(program_name)s'
                if name == 'admix-worker':
                    updates['stdout_logfile'] = str(self.install / 'storage/logs/worker-%(process_num)02d.log')
                content = update_ini(path.read_text(), 'program:' + name, updates)
            else:
                path = destination[0] / (name + destination[1])
                if path.exists():
                    raise ReadinessError('Unrelated configuration occupies ' + str(path))
                content = worker_config(self.install, self.php, self.user, count) if name == 'admix-worker' else reverb_config(self.install, self.php, self.user)
                if name == 'admix-reverb' and confirm('Is the reverse proxy local, and should the new Reverb service bind to 127.0.0.1?'):
                    content = content.replace('artisan reverb:start', 'artisan reverb:start --host=127.0.0.1')
            ctl = ['supervisorctl', '-c', str(main)]
            def validate():
                read_ini(path.read_text())
                self.supervisor_command(ctl + ['reread'])
            def activate():
                self.supervisor_command(ctl + ['reread'])
                self.supervisor_command(ctl + ['update', name], timeout=3700)
                # start is idempotent through status check; no global restart/update.
                state = self.run.run(ctl + ['status', name + ':*'], check=False)
                if 'STOPPED' in state.stdout:
                    self.supervisor_command(ctl + ['start', name + ':*'])
                for _ in range(10):
                    state = self.run.run(ctl + ['status', name + ':*'], check=False)
                    if state.returncode == 0 and state.stdout.strip() and all(' RUNNING ' in line for line in state.stdout.splitlines()):
                        return
                    time.sleep(1)
                raise ReadinessError(name + ' did not reach RUNNING; previous config will be restored.')
            self.backups.change(path, content, validate, activate, mode=0o644, owner=(0, 0))

    def scheduler_step(self):
        say('\nThe Laravel scheduler drives firewall polling and other scheduled work once per minute.')
        entries = self.scheduler_entries()
        if entries:
            say('Existing possible scheduler(s) preserved: ' + ', '.join(entries))
            say('Confirm the path/runtime user and avoid duplicate cron/timer entries. No extra entry will be added.')
            return
        service = self.service(['cron.service', 'crond.service'])
        if not service:
            say('cron/cronie is missing. Install it or use your existing systemd timer.')
            return
        if confirm('Install /etc/cron.d/admixcentral and enable the cron service?'):
            path = Path('/etc/cron.d/admixcentral')
            if path.exists():
                raise ReadinessError('An unrelated cron file already uses this name; edit it manually.')
            self.backups.change(path, cron_config(self.install, self.php, self.user), lambda: None,
                                lambda: self.run.run(['systemctl', 'enable', '--now', service]), mode=0o644, owner=(0, 0))

    def fpm_step(self):
        if not self.pool or not self.fpm:
            return
        say('\nPHP-FPM changes affect requests handled by this pool. A reload also loads newly installed extensions.')
        if confirm('Validate and reload the selected PHP-FPM service?'):
            self.reload_fpm()
        say('Worker memory varies with workload. Keep existing pool sizing unless measured RAM usage supports a change.')
        if confirm('Set an explicit pm.max_children limit for this pool?'):
            parser = read_ini(self.pool.read_text())
            sections = [s for s in parser.sections() if parser.has_option(s, 'user')]
            if len(sections) != 1:
                raise ReadinessError('Select a single FPM pool file before changing its size.')
            section = sections[0]
            count = int(ask('Maximum PHP children (leave RAM for database, Redis, OS and queue workers)', parser.get(section, 'pm.max_children', fallback='10')))
            if not 1 <= count <= 512:
                raise ReadinessError('PHP child limit must be between 1 and 512.')
            updates = {'pm.max_children': str(count)}
            if parser.get(section, 'pm', fallback='dynamic') == 'dynamic':
                updates.update({'pm.start_servers': str(max(1, count // 4)),
                                'pm.min_spare_servers': str(max(1, count // 4)),
                                'pm.max_spare_servers': str(max(1, count // 2))})
            self.backups.change(self.pool, update_ini(self.pool.read_text(), section, updates),
                                lambda: self.run.run([self.fpm, '-t']), self.reload_fpm)

    def security_step(self):
        self.report = self.probe()
        if self.report:
            updates = {}
            say('\nEnvironment hardening preserves APP_KEY, database/Redis credentials, drivers and firewall certificate trust settings.')
            if self.report['debug'] and confirm('Turn APP_DEBUG off to hide internal errors from web visitors?'):
                updates['APP_DEBUG'] = 'false'
            if not self.report['session_secure'] and self.report['https']:
                say('Secure-only cookies require working public HTTPS, including the login page and proxy configuration.')
                if confirm('Have you verified HTTPS access, and should secure-only session cookies be enabled?'):
                    updates['SESSION_SECURE_COOKIE'] = 'true'
            if not self.report['session_encrypt']:
                say('Enabling encrypted sessions invalidates existing plaintext sessions; users must sign in again.')
                if confirm('Enable session encryption during this maintenance window?'):
                    updates['SESSION_ENCRYPT'] = 'true'
            if updates:
                say('Applying environment changes clears only Laravel configuration, reloads FPM and requests graceful queue/Reverb restarts.')
                cached_snapshot = self.backups.save(self.install / 'bootstrap/cache/config.php')
                def activate():
                    for command in ['config:clear', 'queue:restart', 'reverb:restart']:
                        self.run.run([self.php, str(self.install / 'artisan'), command, '--no-interaction'], user=self.user)
                    self.reload_fpm()
                def recover():
                    self.backups.restore(cached_snapshot)
                    for command in ['queue:restart', 'reverb:restart']:
                        self.run.run([self.php, str(self.install / 'artisan'), command, '--no-interaction'], user=self.user)
                    self.reload_fpm()
                self.backups.change(self.install / '.env', update_env((self.install / '.env').read_text(), updates),
                                    lambda: None, activate, recover=recover)
        path = Path('/etc/sudoers.d/admixcentral')
        if self.legacy_sudoers():
            say('\nLegacy AdmixCentral sudo rules permit web-triggered root configuration writes and can turn an application compromise into host compromise.')
            say('Removing this drop-in disables web SSL installation and host performance-tuning actions that depend on sudo. Manage those as an OS administrator.')
            say('The built-in GitHub code updater stays in place; application ownership and updater code are unchanged.')
            if confirm('Remove the legacy AdmixCentral sudo grants (replace this drop-in with a comment)?'):
                self.backups.change(path, MARKER + '\n# Legacy web-to-root grants removed; use an OS administrator for host changes.\n',
                                    lambda: self.run.run(['visudo', '-cf', '/etc/sudoers']), mode=0o440, owner=(0, 0))
        say('Private/self-signed pfSense and OPNsense certificates remain supported through per-firewall public-key pins or a trusted CA bundle. TLS verification is never disabled here.')

    def execute(self):
        for name in ['artisan', '.env', 'vendor/autoload.php']:
            if not (self.install / name).is_file():
                raise ReadinessError('Existing deployment is missing ' + name + '; install the app using its normal installation/update process first.')
        if not Path('/run/systemd/system').is_dir():
            raise ReadinessError('This wizard needs a systemd host. Use the guide for containers or non-systemd systems.')
        ready = self.check()
        if self.args.check:
            return 0 if ready else 2
        if os.geteuid() != 0:
            raise ReadinessError('Run the interactive wizard with sudo; --check can run without root where permissions allow.')
        if not confirm('\nBegin guided host preparation? Each change is explained and separately selectable.'):
            return 2 if self.issues else 0
        os.umask(0o077)
        self.start_backups()
        self.package_step()
        self.report = self.probe()
        self.redis_step()
        self.supervisor_step()
        self.scheduler_step()
        self.fpm_step()
        self.security_step()
        say('\nHost preparation finished. Rechecking current readiness; skipped/unresolved items remain visible.')
        return 0 if self.check() else 2


def arguments(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', '--dry-run', action='store_true', help='Read-only readiness report; no configuration changes or service actions (exit 2 means attention needed).')
    parser.add_argument('--install-dir', default=os.environ.get('INSTALL_DIR', '/var/www/admixcentral'))
    parser.add_argument('--runtime-user', help='Non-root PHP-FPM pool user (otherwise detected from the pool).')
    parser.add_argument('--php-version', default=os.environ.get('PHP_VER', ''), help='Select an installed versioned PHP CLI, e.g. 8.3 on Ubuntu; no repository is added.')
    parser.add_argument('--php-bin', help='Explicit PHP CLI binary for a custom installation.')
    parser.add_argument('--fpm-bin', help='Explicit matching PHP-FPM binary.')
    parser.add_argument('--fpm-pool', help='Explicit PHP-FPM pool file for this application.')
    parser.add_argument('--fpm-service', help='Explicit systemd PHP-FPM service name.')
    return parser.parse_args(argv)


def main(argv=None):
    if sys.version_info < (3, 9):
        say('Python 3.9+ is required.')
        return 1
    try:
        return Wizard(arguments(argv)).execute()
    except (ReadinessError, OSError, ValueError, KeyError, configparser.Error) as exc:
        say('Stopped: ' + str(exc))
        return 1
    except (KeyboardInterrupt, EOFError):
        say('\nStopped by operator. Completed steps remain applied; see the private backup directory for recovery.')
        return 130


if __name__ == '__main__':
    sys.exit(main())
