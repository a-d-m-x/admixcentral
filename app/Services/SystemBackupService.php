<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DeviceConnection;
use App\Models\Firewall;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class SystemBackupService
{
    protected $encryptionMethod = 'AES-256-CBC';

    /**
     * Create a system backup.
     *
     * @param string $password
     * @return string Filename of the created backup
     * @throws \Exception
     */
    public function createBackup(string $password): string
    {
        $data = $this->gatherSystemData();
        $jsonContent = json_encode($data);

        if ($jsonContent === false) {
            throw new \Exception("Failed to encode system data to JSON.");
        }

        $encryptedContent = $this->encryptData($jsonContent, $password);

        $filename = 'backup-' . now()->format('Y-m-d-H-i-s') . '.json.enc';
        Storage::put('backups/' . $filename, $encryptedContent);

        return $filename;
    }

    /**
     * Restore system from a backup file path.
     *
     * @param string $path Absolute path to the backup file
     * @param string $password
     * @throws \Exception
     */
    public function restoreFromPath(string $path, string $password, array $options = []): void
    {
        if (!file_exists($path)) {
            throw new \Exception("Backup file not found at path: $path");
        }

        $encryptedContent = file_get_contents($path);
        $this->restoreFromContent($encryptedContent, $password, $options);
    }

    /**
     * Restore system from raw content.
     *
     * @param string $encryptedContent
     * @param string $password
     * @throws \Exception
     */
    public function restoreFromContent(string $encryptedContent, string $password, array $options = []): void
    {
        $jsonContent = $this->decryptData($encryptedContent, $password);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to decode JSON data: " . json_last_error_msg());
        }

        // Validate backup data structure before restoring
        if (!is_array($data) || empty($data['version'])) {
            throw new \Exception("Invalid backup format: missing version field.");
        }

        // Whitelist expected top-level keys to prevent injection of unexpected data
        $allowedKeys = ['version', 'timestamp', 'companies', 'users', 'firewalls', 'system_settings', 'device_connections'];
        $unexpectedKeys = array_diff(array_keys($data), $allowedKeys);
        if (!empty($unexpectedKeys)) {
            Log::warning("Backup contains unexpected keys, stripping: " . implode(', ', $unexpectedKeys));
            $data = array_intersect_key($data, array_flip($allowedKeys));
        }

        $this->restoreSystemData($data, $options);
    }

    protected function gatherSystemData(): array
    {
        $usersData = [];
        foreach (User::all()->makeVisible(['password', 'two_factor_secret', 'two_factor_recovery_codes']) as $user) {
            $uArr = $user->toArray();
            unset($uArr['remember_token']);

            if (!empty($user->two_factor_secret)) {
                try {
                    $uArr['two_factor_secret_plain'] = decrypt($user->two_factor_secret);
                } catch (\Throwable) {
                    $uArr['two_factor_secret_plain'] = null;
                }
            }
            if (!empty($user->two_factor_recovery_codes)) {
                try {
                    $uArr['two_factor_recovery_codes_plain'] = decrypt($user->two_factor_recovery_codes);
                } catch (\Throwable) {
                    $uArr['two_factor_recovery_codes_plain'] = null;
                }
            }
            $usersData[] = $uArr;
        }

        return [
            'version' => '2.0', // Schema version
            'timestamp' => now()->toIso8601String(),
            'companies' => Company::all()->makeVisible(['api_token'])->toArray(),
            'users' => $usersData,
            'firewalls' => Firewall::all()->makeVisible([
                'api_key', 'api_secret', 'api_token', 'ssh_username', 'ssh_password'
            ])->toArray(),
            'system_settings' => SystemSetting::whereNotIn('key', ['logo_path', 'favicon_path'])->get()->toArray(),
        ];
    }

    protected function restoreSystemData(array $data, array $options = [])
    {
        DB::transaction(function () use ($data, $options) {
            try {
                // Disable foreign key checks to avoid constraint violations during truncate
                Schema::disableForeignKeyConstraints();

                // 1. Restore Companies
                if (isset($data['companies'])) {
                    Company::query()->delete();
                    foreach ($data['companies'] as $record) {
                        if (isset($record['created_at']))
                            $record['created_at'] = \Carbon\Carbon::parse($record['created_at'])->toDateTimeString();
                        if (isset($record['updated_at']))
                            $record['updated_at'] = \Carbon\Carbon::parse($record['updated_at'])->toDateTimeString();

                        Company::forceCreate($record);
                    }
                }

                // 2. Restore Users (Granular Logic)
                if (isset($data['users'])) {
                    if (!($options['exclude_global_admins'] ?? false)) {
                        User::where('role', 'admin')
                            ->whereNull('company_id')
                            ->delete();
                    }

                    if (!($options['exclude_end_users'] ?? false)) {
                        User::where(function ($q) {
                            $q->where('role', '!=', 'admin')
                                ->orWhereNotNull('company_id');
                        })->delete();
                    }

                    foreach ($data['users'] as $record) {
                        $isGlobalArg = ($record['role'] === 'admin' && is_null($record['company_id']));
                        $isEndOrCompanyArg = !$isGlobalArg;

                        if (($options['exclude_global_admins'] ?? false) && $isGlobalArg) {
                            continue;
                        }

                        if (($options['exclude_end_users'] ?? false) && $isEndOrCompanyArg) {
                            continue;
                        }

                        unset($record['remember_token']);

                        // Re-encrypt 2FA credentials under the current application key
                        if (isset($record['two_factor_secret_plain'])) {
                            if (!empty($record['two_factor_secret_plain'])) {
                                $record['two_factor_secret'] = encrypt($record['two_factor_secret_plain']);
                            }
                            unset($record['two_factor_secret_plain']);
                        }
                        if (isset($record['two_factor_recovery_codes_plain'])) {
                            if (!empty($record['two_factor_recovery_codes_plain'])) {
                                $record['two_factor_recovery_codes'] = encrypt($record['two_factor_recovery_codes_plain']);
                            }
                            unset($record['two_factor_recovery_codes_plain']);
                        }

                        if (isset($record['created_at']))
                            $record['created_at'] = \Carbon\Carbon::parse($record['created_at'])->toDateTimeString();
                        if (isset($record['updated_at']))
                            $record['updated_at'] = \Carbon\Carbon::parse($record['updated_at'])->toDateTimeString();
                        if (isset($record['email_verified_at']))
                            $record['email_verified_at'] = \Carbon\Carbon::parse($record['email_verified_at'])->toDateTimeString();

                        User::forceCreate($record);
                    }
                }

                // 3. Restore Firewalls
                if (isset($data['firewalls'])) {
                    Firewall::query()->delete();
                    foreach ($data['firewalls'] as $record) {
                        if (isset($record['created_at']))
                            $record['created_at'] = \Carbon\Carbon::parse($record['created_at'])->toDateTimeString();
                        if (isset($record['updated_at']))
                            $record['updated_at'] = \Carbon\Carbon::parse($record['updated_at'])->toDateTimeString();

                        Firewall::forceCreate($record);
                    }
                }

                // 4. Restore System Settings
                if (isset($data['system_settings'])) {
                    $keysToPreserve = ['logo_path', 'favicon_path'];

                    if ($options['exclude_hostname'] ?? false) {
                        $keysToPreserve[] = 'site_url';
                        $keysToPreserve[] = 'site_protocol';
                    }

                    SystemSetting::whereNotIn('key', $keysToPreserve)->delete();

                    foreach ($data['system_settings'] as $record) {
                        if (in_array($record['key'], $keysToPreserve)) {
                            continue;
                        }

                        unset($record['id']);

                        if (isset($record['created_at']))
                            $record['created_at'] = \Carbon\Carbon::parse($record['created_at'])->toDateTimeString();
                        if (isset($record['updated_at']))
                            $record['updated_at'] = \Carbon\Carbon::parse($record['updated_at'])->toDateTimeString();

                        SystemSetting::updateOrInsert(
                            ['key' => $record['key']],
                            $record
                        );
                    }
                }

                // Ephemeral live connections are cleared, not restored
                DeviceConnection::query()->delete();

            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });
    }

    protected function encryptData(string $data, string $password): string
    {
        $salt = random_bytes(16);
        $iv = random_bytes(12); // Standard GCM 96-bit nonce
        $key = hash_pbkdf2("sha256", $password, $salt, 100000, 32, true);
        $tag = '';
        $aad = 'admixcentral-backup-v2';

        $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if ($encrypted === false) {
            throw new \Exception("Encryption failed.");
        }

        // Format: 'ADMX2' (5) + Salt (16) + IV (12) + Tag (16) + Ciphertext
        return base64_encode('ADMX2' . $salt . $iv . $tag . $encrypted);
    }

    protected function decryptData(string $data, string $password): string
    {
        $raw = base64_decode($data);
        if ($raw === false) {
            throw new \Exception("Invalid base64 payload.");
        }

        // Check for Authenticated V2 Format (ADMX2)
        if (str_starts_with($raw, 'ADMX2')) {
            if (strlen($raw) < 5 + 16 + 12 + 16) {
                throw new \Exception("Malformed backup archive.");
            }

            $salt = substr($raw, 5, 16);
            $iv = substr($raw, 21, 12);
            $tag = substr($raw, 33, 16);
            $ciphertext = substr($raw, 49);
            $aad = 'admixcentral-backup-v2';

            $key = hash_pbkdf2("sha256", $password, $salt, 100000, 32, true);
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);

            if ($decrypted === false) {
                throw new \Exception("Decryption failed. Authentication tag mismatch, incorrect password, or corrupted file.");
            }

            return $decrypted;
        }

        // Legacy CBC Fallback (only for unversioned legacy backups)
        $salt = substr($raw, 0, 16);
        $iv = substr($raw, 16, 16);
        $encrypted = substr($raw, 32);

        $key = hash_pbkdf2("sha256", $password, $salt, 10000, 32, true);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

        if ($decrypted === false) {
            throw new \Exception("Decryption failed. Incorrect password or corrupted file.");
        }

        return $decrypted;
    }
}
