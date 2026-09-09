"""Host wizard regression tests. All writes are confined to temporary directories."""
import importlib.util
import io
import json
import os
from pathlib import Path
import pwd
import shutil
import socketserver
import subprocess
import tempfile
import threading
import unittest
from contextlib import redirect_stdout
from types import SimpleNamespace
from unittest.mock import Mock, patch

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('host_wizard', ROOT / 'scripts/security_upgrade/wizard.py')
w = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(w)


class ConfigurationTests(unittest.TestCase):
    def test_os_families_and_derivatives(self):
        for source, family in [('ID=ubuntu', 'ubuntu'), ('ID=linuxmint\nID_LIKE="ubuntu debian"', 'ubuntu'),
                               ('ID=fedora', 'fedora'), ('ID=nobara\nID_LIKE=fedora', 'fedora'),
                               ('ID=arch', 'arch'), ('ID=manjaro\nID_LIKE=arch', 'arch')]:
            with self.subTest(source=source):
                self.assertEqual(w.parse_os(source), family)
        for source in ['ID=unknown', 'ID=opensuse', 'ID=rhel', 'ID=archlinuxfake']:
            with self.assertRaises(w.ReadinessError):
                w.parse_os(source)

    def test_distro_package_names_and_no_partial_arch_upgrade(self):
        ubuntu = w.packages('ubuntu', '8.4', True)
        self.assertIn('php8.4-redis', ubuntu)
        self.assertIn('redis-server', ubuntu)
        self.assertNotIn('php8.3-fpm', ubuntu)
        self.assertIn('php-fpm', w.packages('ubuntu', '', False))
        for family in ['fedora', 'arch']:
            self.assertIn('valkey', w.packages(family, '', True))
            self.assertNotIn('valkey', w.packages(family, '', False))
            self.assertNotIn('redis', w.packages(family, '', True))
        self.assertIn('php-pecl-redis6', w.packages('fedora', '', False))
        self.assertIn('php-redis', w.packages('arch', '', False))
        self.assertEqual(w.install_command('arch', ['php']), ['pacman', '-S', '--needed', 'php'])

    def test_env_credentials_multiline_interpolation_and_duplicate_keys(self):
        secrets = 'APP_KEY="base64:secret"\nREDIS_PASSWORD="a&b#c${NOT_A_SHELL}$(id)"\nREDIS_USERNAME=acl\nREDIS_URL="rediss://u:p@host:6380/5"\nDB_PASSWORD="first\nAPP_DEBUG=keep-inside-secret\nlast"\n'
        source = secrets + 'QUEUE_CONNECTION=database\nSESSION_DRIVER=database\nCACHE_STORE=database\nAPP_DEBUG=true\nexport APP_DEBUG=true\n'
        updated = w.update_env(source, {'APP_DEBUG': 'false'})
        self.assertTrue(updated.startswith(secrets))
        self.assertIn('QUEUE_CONNECTION=database\nSESSION_DRIVER=database\nCACHE_STORE=database\n', updated)
        self.assertEqual(updated.count('\nAPP_DEBUG=false\n'), 1)
        self.assertNotIn('APP_DEBUG=true', updated)
        self.assertEqual(updated, w.update_env(updated, {'APP_DEBUG': 'false'}))

    def test_env_rejects_credentials_driver_and_unterminated_quote_changes(self):
        for key in ['REDIS_PASSWORD', 'REDIS_CLIENT', 'CACHE_STORE', 'QUEUE_CONNECTION', 'APP_KEY']:
            with self.assertRaises(w.ReadinessError):
                w.update_env('APP_DEBUG=true\n', {key: 'false'})
        with self.assertRaises(w.ReadinessError):
            w.update_env('DB_PASSWORD="unterminated\nAPP_DEBUG=true\n', {'APP_DEBUG': 'false'})

    def test_ini_preserves_queue_routing_and_other_sections(self):
        source = '[program:admix-worker]\ncommand=/usr/bin/php /var/www/admixcentral/artisan queue:work database --queue=urgent,default\nnumprocs=8\n[other]\nnumprocs=7\n'
        updated = w.update_ini(source, 'program:admix-worker', {'numprocs': '2', 'user': 'apache'})
        self.assertIn('queue:work database --queue=urgent,default', updated)
        self.assertEqual(w.read_ini(updated)['other']['numprocs'], '7')
        self.assertEqual(w.read_ini(updated)['program:admix-worker']['user'], 'apache')
        self.assertEqual(updated, w.update_ini(updated, 'program:admix-worker', {'numprocs': '2', 'user': 'apache'}))

    def test_pool_user_comes_from_config_and_rejects_ambiguous_users(self):
        self.assertEqual(w.pool_user('[www]\nuser=www-data\n'), 'www-data')
        self.assertEqual(w.pool_user('[www]\nuser=apache\n'), 'apache')
        self.assertEqual(w.pool_user('[www]\nuser=http\n'), 'http')
        with self.assertRaises(w.ReadinessError):
            w.pool_user('[one]\nuser=apache\n[two]\nuser=another\n')

    def test_redis_auth_and_persistence_are_preserved(self):
        source = 'bind 0.0.0.0\nprotected-mode no\nrequirepass "secret#&"\nuser admix on >secret ~* +@all\nappendonly no\nsave 60 10\nmaxmemory 256mb\nmaxmemory-policy allkeys-lru\n'
        new = w.redis_harden(source)
        for line in ['requirepass "secret#&"', 'user admix on >secret ~* +@all', 'appendonly no', 'save 60 10', 'maxmemory 256mb']:
            self.assertIn(line, new)
        self.assertIn('bind 127.0.0.1 -::1', new)
        self.assertIn('maxmemory-policy noeviction', new)
        self.assertEqual(new, w.redis_harden(new))
        for extra in ['include /etc/other.conf', 'replicaof 10.0.0.1 6379', 'cluster-enabled yes', 'tls-port 6380']:
            with self.assertRaises(w.ReadinessError):
                w.redis_harden(source + extra + '\n')

    def test_new_services_use_nonroot_user_and_current_queue_connection(self):
        for user in ['www-data', 'apache', 'http']:
            worker = w.read_ini(w.worker_config('/var/www/admixcentral', '/usr/bin/php', user, 3))['program:admix-worker']
            self.assertEqual(worker['user'], user)
            self.assertNotIn('queue:work redis', worker['command'])
            self.assertIn('%(process_num)02d', worker['stdout_logfile'])
            reverb = w.read_ini(w.reverb_config('/var/www/admixcentral', '/usr/bin/php', user))['program:admix-reverb']
            self.assertEqual(reverb['numprocs'], '1')
            self.assertNotIn('--host=', reverb['command'])
            self.assertIn(user + ' cd /var/www/admixcentral && /usr/bin/php artisan schedule:run', w.cron_config('/var/www/admixcentral', '/usr/bin/php', user))

    def test_rejects_paths_that_inject_supervisor_cron_or_shell_syntax(self):
        for path in ['/tmp/a;id', '/tmp/a%id', '/tmp/a b', '/tmp/a\nroot', '/tmp/a/../b', '/tmp/$(id)']:
            with self.assertRaises(w.ReadinessError):
                w.safe_path(path)


class BackupTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.base = Path(self.temp.name)
        (self.base / 'backups').mkdir(mode=0o700)
        self.backups = w.Backups(self.base / 'backups')
        self.path = self.base / 'service.conf'
        self.path.write_text('original')
        self.path.chmod(0o640)
        self.output = redirect_stdout(io.StringIO())
        self.output.__enter__()

    def tearDown(self):
        self.output.__exit__(None, None, None)
        self.temp.cleanup()

    def test_validation_failure_restores_content_and_permissions(self):
        with self.assertRaises(w.ReadinessError):
            self.backups.change(self.path, 'bad', Mock(side_effect=w.ReadinessError('invalid')))
        self.assertEqual(self.path.read_text(), 'original')
        self.assertEqual(self.path.stat().st_mode & 0o777, 0o640)
        self.assertEqual((self.base / 'backups/1.original').stat().st_mode & 0o777, 0o600)
        self.assertEqual(json.loads((self.base / 'backups/1.json').read_text())['path'], str(self.path))

    def test_failed_restart_restores_old_file_and_retries_recovery(self):
        activate = Mock(side_effect=[w.ReadinessError('failed'), None])
        with self.assertRaises(w.ReadinessError):
            self.backups.change(self.path, 'new', lambda: None, activate)
        self.assertEqual(self.path.read_text(), 'original')
        self.assertEqual(activate.call_count, 2)

    def test_interrupt_restores_file(self):
        with self.assertRaises(KeyboardInterrupt):
            self.backups.change(self.path, 'new', Mock(side_effect=KeyboardInterrupt()))
        self.assertEqual(self.path.read_text(), 'original')

    def test_recovery_restores_related_config_snapshot(self):
        cache = self.base / 'config.php'
        cache.write_text('cached originals')
        snapshot = self.backups.save(cache)
        def fail():
            cache.unlink()
            raise w.ReadinessError('failed')
        with self.assertRaises(w.ReadinessError):
            self.backups.change(self.path, 'new', fail, recover=lambda: self.backups.restore(snapshot))
        self.assertEqual(cache.read_text(), 'cached originals')

    def test_idempotent_change_does_not_restart_or_backup(self):
        activate = Mock()
        self.assertFalse(self.backups.change(self.path, 'original', Mock(), activate,
                         mode=0o640, owner=(os.getuid(), os.getgid())))
        activate.assert_not_called()
        self.assertEqual(self.backups.sequence, 0)

    def test_symlink_not_followed(self):
        link = self.base / 'linked.conf'
        link.symlink_to(self.path)
        with self.assertRaises(w.ReadinessError):
            self.backups.change(link, 'changed', lambda: None)
        self.assertEqual(self.path.read_text(), 'original')

    def test_failed_new_file_is_removed(self):
        new = self.base / 'new.conf'
        with self.assertRaises(w.ReadinessError):
            self.backups.change(new, 'new', Mock(side_effect=w.ReadinessError('failed')),
                                owner=(os.getuid(), os.getgid()))
        self.assertFalse(new.exists())


class WorkflowTests(unittest.TestCase):
    def test_read_only_execute_never_reaches_mutating_steps(self):
        wizard = object.__new__(w.Wizard)
        wizard.install = ROOT
        wizard.args = SimpleNamespace(check=True)
        wizard.check = Mock(return_value=False)
        for method in ['start_backups', 'package_step', 'redis_step', 'supervisor_step', 'scheduler_step', 'fpm_step', 'security_step']:
            setattr(wizard, method, Mock(side_effect=AssertionError('read-only violation')))
        with patch.object(Path, 'is_file', return_value=True), patch.object(Path, 'is_dir', return_value=True):
            self.assertEqual(wizard.execute(), 2)
        wizard.check.assert_called_once()

    def test_runner_refuses_root_application_php(self):
        with self.assertRaises(w.ReadinessError), patch('subprocess.run') as run:
            w.Runner().run(['php', 'artisan'], user='root')
        run.assert_not_called()

    def test_runner_strips_inherited_environment_and_does_not_log_output(self):
        user = pwd.getpwuid(os.getuid()).pw_name
        runner = w.Runner()
        runner.log = io.StringIO()
        with patch('subprocess.run', return_value=SimpleNamespace(returncode=0, stdout='secret', stderr='secret')) as run:
            runner.run(['php', '-r', 'code'], user=user)
        kwargs = run.call_args.kwargs
        self.assertNotIn('shell', kwargs)
        self.assertEqual(kwargs['env'], {'PATH': w.SAFE_PATH, 'LANG': 'C.UTF-8'})
        self.assertIn('-i', run.call_args.args[0])
        self.assertNotIn('secret', runner.log.getvalue())

    def test_existing_scheduler_does_not_add_cron(self):
        wizard = object.__new__(w.Wizard)
        wizard.scheduler_entries = Mock(return_value=['/etc/systemd/system/admix-schedule.service'])
        wizard.backups = Mock()
        with redirect_stdout(io.StringIO()), patch.object(w, 'confirm', side_effect=AssertionError('should not prompt')):
            wizard.scheduler_step()
        wizard.backups.change.assert_not_called()

    def test_supervisor_rpc_error_is_not_mistaken_for_success(self):
        wizard = object.__new__(w.Wizard)
        wizard.run = Mock()
        wizard.run.run.return_value = SimpleNamespace(returncode=0, stdout='ERROR: CANT_REREAD: invalid user')
        with self.assertRaises(w.ReadinessError):
            wizard.supervisor_command(['supervisorctl', 'reread'])

    def test_alternative_service_prevents_duplicate_program_creation(self):
        wizard = object.__new__(w.Wizard)
        wizard.php, wizard.version, wizard.user = '/usr/bin/php', '8.5', 'http'
        wizard.service = lambda _: 'supervisord.service'
        wizard.active = lambda _: True
        wizard.programs = lambda _: (Path('/etc/supervisord.conf'), None, (Path('/etc/supervisor.d'), '.ini'))
        wizard.alternate_services = lambda action: ['/etc/systemd/system/admix-' + action + '.service']
        wizard.backups = Mock()
        with redirect_stdout(io.StringIO()), patch.object(w, 'confirm', side_effect=AssertionError('no duplicate prompt')):
            wizard.supervisor_step()
        wizard.backups.change.assert_not_called()

    def test_running_redis_is_preserved_over_new_valkey_default(self):
        wizard = object.__new__(w.Wizard)
        wizard.unit_exists = lambda name: name in {'redis.service', 'valkey.service'}
        wizard.active = lambda name: name == 'redis.service'
        self.assertEqual(wizard.service(['redis-server.service', 'redis.service', 'valkey.service']), 'redis.service')

    def test_supervisor_preserves_queue_selection_and_reverb_port(self):
        with tempfile.TemporaryDirectory() as directory:
            base = Path(directory)
            worker, reverb, main = [base / name for name in ['worker.ini', 'reverb.ini', 'supervisord.conf']]
            worker.write_text(w.worker_config('/var/www/admixcentral', '/usr/bin/php', 'http', 8).replace('queue:work --', 'queue:work redis --queue=urgent,default --'))
            reverb.write_text(w.reverb_config('/var/www/admixcentral', '/usr/bin/php', 'http').replace('reverb:start', 'reverb:start --host=0.0.0.0 --port=9001').replace('numprocs=1', 'numprocs=3'))
            wizard = object.__new__(w.Wizard)
            wizard.php, wizard.version, wizard.user, wizard.install = '/usr/bin/php', '8.5', 'http', Path('/var/www/admixcentral')
            wizard.service = lambda _: 'supervisord.service'
            wizard.active = lambda _: True
            wizard.programs = lambda name: (main, worker if name == 'admix-worker' else reverb, (base, '.ini'))
            wizard.backups = Mock()
            with redirect_stdout(io.StringIO()), patch.object(w, 'confirm', side_effect=[True, True, True]), patch.object(w, 'ask', return_value='2'):
                wizard.supervisor_step()
            worker_new = wizard.backups.change.call_args_list[0].args[1]
            reverb_new = wizard.backups.change.call_args_list[1].args[1]
            self.assertIn('queue:work redis --queue=urgent,default', worker_new)
            self.assertIn('numprocs=2', worker_new)
            self.assertIn('--port=9001 --host=127.0.0.1', reverb_new)
            self.assertIn('numprocs=1', reverb_new)


class ProbeTests(unittest.TestCase):
    def test_real_php_probe_uses_acl_url_and_does_not_boot_providers_or_write_data(self):
        if not shutil.which('php') or not (ROOT / 'vendor/autoload.php').exists():
            self.skipTest('PHP and installed Composer dependencies are needed for the probe integration test')
        modules = subprocess.run(['php', '-m'], capture_output=True, text=True).stdout.lower().splitlines()
        if 'redis' not in modules:
            self.skipTest('phpredis extension needed')
        commands = []
        class Handler(socketserver.StreamRequestHandler):
            def handle(self):
                while True:
                    line = self.rfile.readline()
                    if not line:
                        return
                    count = int(line[1:])
                    values = []
                    for _ in range(count):
                        size = int(self.rfile.readline()[1:])
                        values.append(self.rfile.read(size).decode())
                        self.rfile.read(2)
                    commands.append(values)
                    self.wfile.write(b'+PONG\r\n' if values[0].upper() == 'PING' else b'+OK\r\n')
        with socketserver.ThreadingTCPServer(('127.0.0.1', 0), Handler) as server, tempfile.TemporaryDirectory() as directory:
            thread = threading.Thread(target=server.serve_forever, daemon=True)
            thread.start()
            try:
                base = Path(directory)
                (base / 'vendor').symlink_to(ROOT / 'vendor', target_is_directory=True)
                (base / 'bootstrap/cache').mkdir(parents=True)
                (base / 'bootstrap/app.php').write_text('<?php return new Illuminate\\Foundation\\Application(dirname(__DIR__));')
                (base / 'config').mkdir()
                for name in ['app', 'cache', 'session', 'queue', 'database']:
                    shutil.copyfile(ROOT / ('config/' + name + '.php'), base / ('config/' + name + '.php'))
                (base / 'storage/logs').mkdir(parents=True)
                (base / 'storage/framework').mkdir()
                (base / '.env').write_text(f'APP_KEY=base64:secret\nAPP_DEBUG=false\nREDIS_URL="redis://testacl:testsecret@127.0.0.1:{server.server_address[1]}/5"\nREDIS_PASSWORD=unused\n')
                before = {str(p): p.read_bytes() for p in base.rglob('*') if p.is_file()}
                code = (ROOT / 'scripts/security_upgrade/probe.php').read_text().removeprefix('<?php')
                result = subprocess.run(['php', '-r', code, '--', str(base), 'ping'], env={'PATH': w.SAFE_PATH}, capture_output=True, text=True, timeout=15)
                self.assertEqual(result.returncode, 0, result.stderr)
                report = json.loads(result.stdout)
                self.assertEqual(report['redis_ping'], {'default': True, 'cache': True})
                self.assertNotIn('testsecret', result.stdout + result.stderr)
                self.assertNotIn('base64:secret', result.stdout + result.stderr)
                self.assertTrue(any(c[:3] == ['AUTH', 'testacl', 'testsecret'] for c in commands))
                self.assertTrue(any(c[:2] == ['SELECT', '5'] for c in commands))
                self.assertTrue(all(c[0].upper() in {'AUTH', 'SELECT', 'PING', 'CLIENT'} for c in commands))
                self.assertEqual(before, {str(p): p.read_bytes() for p in base.rglob('*') if p.is_file()})
            finally:
                server.shutdown()
                thread.join(timeout=2)


if __name__ == '__main__':
    unittest.main()
