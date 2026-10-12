<?php

namespace App\Services\System;

/**
 * v4.9 — MySQL / MariaDB compatibility pre-flight.
 *
 * Runs BEFORE the installer writes `.env` and BEFORE migrations run, so we
 * fail fast with an actionable message instead of stopping mid-migration
 * with SQLSTATE[42000] "Specified key was too long; max key length is 1000
 * bytes" (Sanctum's personal_access_tokens migration) or SQLSTATE[42S01]
 * "Table already exists" (when re-running against a non-empty DB).
 *
 * Returned JSON shape matches the existing CampaignDiagnosticService pattern
 * so the same Blade partial (partials/campaign/health_banner.blade.php's JS
 * `renderDiagnosticReport`) can render it without any new client-side code.
 *
 *   [
 *     'overall'  => ['healthy' => bool, 'message' => string],
 *     'server'   => ['healthy' => bool, 'message' => string, 'value' => string],
 *     'engine'   => ['healthy' => bool, 'message' => string, 'value' => string],
 *     'row_format' => [...],
 *     'charset'  => [...],
 *     'primary_key_rule' => [...],
 *     'errors'   => ['count' => int, 'errors' => [...]],
 *   ]
 *
 * Reuses `translate()` for every user-facing string per project convention.
 */
class DatabaseCompatibilityChecker
{
    /**
     * Minimum MySQL/MariaDB versions we support.
     */
    private const MIN_MYSQL_VERSION   = '5.7.0';
    private const MIN_MARIADB_VERSION = '10.3.0';

    /**
     * Run every compatibility probe against a live connection.
     * Never throws — every failure becomes a check-row so the caller can
     * render it to the customer instead of crashing the installer.
     *
     * @param  string|null $host      Optional override for pre-install probing.
     * @param  int|null    $port
     * @param  string|null $username
     * @param  string|null $password
     * @param  string|null $database
     * @return array
     */
    public function check(
        ?string $host = null,
        ?int $port = null,
        ?string $username = null,
        ?string $password = null,
        ?string $database = null
    ): array {
        $pdo = $this->connect($host, $port, $username, $password, $database);

        if ($pdo === null) {
            return $this->failFast(translate('Could not open a probe connection to MySQL. The compatibility scan was skipped — resolve the connection error first.'));
        }

        $errors    = [];
        $server    = $this->probeServerVersion($pdo);
        $engine    = $this->probeStorageEngine($pdo);
        $rowFormat = $this->probeRowFormat($pdo);
        $charset   = $this->probeCharset($pdo);
        $pkRule    = $this->probePrimaryKeyRule($pdo);

        foreach ([$server, $engine, $rowFormat, $charset, $pkRule] as $section) {
            if (!$section['healthy']) {
                $errors[] = ['section' => $section['label'] ?? '', 'message' => $section['message'] ?? ''];
            }
        }

        $healthy = empty($errors);

        return [
            'overall' => [
                'healthy' => $healthy,
                'message' => $healthy
                    ? translate('MySQL server is compatible with XSender. You can proceed with the installation.')
                    : translate('The MySQL server has one or more settings that would cause the installer to fail. See the details below and update your MySQL configuration before continuing.'),
            ],
            'server'           => $server,
            'engine'           => $engine,
            'row_format'       => $rowFormat,
            'charset'          => $charset,
            'primary_key_rule' => $pkRule,
            'errors' => [
                'count'  => count($errors),
                'errors' => $errors,
            ],
        ];
    }

    /**
     * Probe MySQL/MariaDB version.
     */
    private function probeServerVersion(\PDO $pdo): array
    {
        try {
            $raw = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

            $isMariaDb = stripos($raw, 'mariadb') !== false;
            $numeric   = preg_replace('/[^0-9\.]/', '', explode('-', $raw)[0]);
            $min       = $isMariaDb ? self::MIN_MARIADB_VERSION : self::MIN_MYSQL_VERSION;

            $ok = version_compare($numeric, $min, '>=');

            return [
                'label'   => translate('Database Server Version'),
                'healthy' => $ok,
                'value'   => $raw,
                'message' => $ok
                    ? translate('Version') . ' ' . $raw
                    : translate('Detected ') . $raw . '. ' . translate('Minimum required is ') . $min . '.',
            ];
        } catch (\Throwable $e) {
            return $this->probeError(translate('Database Server Version'), $e);
        }
    }

    /**
     * Storage engine — MyISAM enforces the 1000-byte key limit that breaks
     * every long index (Sanctum's tokenable_type_tokenable_id, spatie
     * media libraries, etc.). InnoDB with DYNAMIC row format is required.
     */
    private function probeStorageEngine(\PDO $pdo): array
    {
        try {
            $value = (string) $pdo->query("SELECT @@default_storage_engine")->fetchColumn();
            $ok    = strcasecmp($value, 'InnoDB') === 0;

            return [
                'label'   => translate('Default Storage Engine'),
                'healthy' => $ok,
                'value'   => $value,
                'message' => $ok
                    ? translate('InnoDB (correct)')
                    : translate('Detected ') . $value . '. ' . translate('MyISAM enforces a 1000-byte key limit that will break the installer. Set default_storage_engine = InnoDB in my.cnf and restart MySQL.'),
            ];
        } catch (\Throwable $e) {
            return $this->probeError(translate('Default Storage Engine'), $e);
        }
    }

    /**
     * InnoDB row format — must be DYNAMIC to allow the 3072-byte index key
     * limit needed for utf8mb4 composite indexes.
     */
    private function probeRowFormat(\PDO $pdo): array
    {
        try {
            $value = (string) $pdo->query("SELECT @@innodb_default_row_format")->fetchColumn();
            $ok    = strcasecmp($value, 'dynamic') === 0;

            return [
                'label'   => translate('InnoDB Row Format'),
                'healthy' => $ok,
                'value'   => $value,
                'message' => $ok
                    ? translate('DYNAMIC (correct)')
                    : translate('Detected ') . $value . '. ' . translate('The COMPACT row format enforces a 767-byte prefix limit that clashes with utf8mb4. Set innodb_default_row_format = dynamic in my.cnf and restart MySQL.'),
            ];
        } catch (\Throwable $e) {
            return $this->probeError(translate('InnoDB Row Format'), $e);
        }
    }

    /**
     * Server character set — must be utf8mb4 so the app can store emoji,
     * international phone numbers with unusual chars, and CJK content.
     */
    private function probeCharset(\PDO $pdo): array
    {
        try {
            $server = (string) $pdo->query("SELECT @@character_set_server")->fetchColumn();
            $ok     = stripos($server, 'utf8mb4') === 0 || stripos($server, 'utf8mb4') !== false;

            return [
                'label'   => translate('Server Character Set'),
                'healthy' => $ok,
                'value'   => $server,
                'message' => $ok
                    ? translate('utf8mb4 (correct)')
                    : translate('Detected ') . $server . '. ' . translate('Set character_set_server = utf8mb4 and collation_server = utf8mb4_unicode_ci in my.cnf, then restart MySQL. Also run: ALTER DATABASE your_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'),
            ];
        } catch (\Throwable $e) {
            return $this->probeError(translate('Server Character Set'), $e);
        }
    }

    /**
     * MySQL 8.0+ ships with sql_require_primary_key = ON on some hosts,
     * which blocks any migration that creates a table without an explicit
     * primary key (some pivot tables and legacy vendor migrations).
     */
    private function probePrimaryKeyRule(\PDO $pdo): array
    {
        try {
            $value = (string) $pdo->query("SELECT @@sql_require_primary_key")->fetchColumn();
            // '0' or 'OFF' both mean OK
            $ok = in_array(strtoupper($value), ['0', 'OFF'], true);

            return [
                'label'   => translate('sql_require_primary_key'),
                'healthy' => $ok,
                'value'   => $value ?: 'OFF',
                'message' => $ok
                    ? translate('OFF (correct)')
                    : translate('Detected ON. Some Laravel vendor migrations create tables without primary keys and will fail with this setting. Set sql_require_primary_key = OFF in my.cnf and restart MySQL.'),
            ];
        } catch (\Throwable $e) {
            // MariaDB and older MySQL don't have this variable — that's fine.
            return [
                'label'   => translate('sql_require_primary_key'),
                'healthy' => true,
                'value'   => 'N/A',
                'message' => translate('Not applicable on this server (safe default).'),
            ];
        }
    }

    /**
     * Open a probe connection using the same PDO settings the installer's
     * _checkDbConnection method uses. Returns null on any failure — caller
     * short-circuits into failFast().
     */
    private function connect(
        ?string $host, ?int $port, ?string $username, ?string $password, ?string $database
    ): ?\PDO {
        $host     = $host     ?? env('DB_HOST', '127.0.0.1');
        $port     = $port     ?? (int) env('DB_PORT', 3306);
        $username = $username ?? env('DB_USERNAME', '');
        $password = $password ?? env('DB_PASSWORD', '');
        $database = $database ?? env('DB_DATABASE', '');

        try {
            $dsn = "mysql:host={$host};port={$port}" . ($database ? ";dbname={$database}" : '');
            return new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function probeError(string $label, \Throwable $e): array
    {
        return [
            'label'   => $label,
            'healthy' => false,
            'value'   => 'error',
            'message' => translate('Could not read this setting from MySQL: ') . $e->getMessage(),
        ];
    }

    private function failFast(string $reason): array
    {
        return [
            'overall'          => ['healthy' => false, 'message' => $reason],
            'server'           => ['label' => translate('Database Server Version'), 'healthy' => false, 'value' => '-', 'message' => translate('Skipped — no connection.')],
            'engine'           => ['label' => translate('Default Storage Engine'), 'healthy' => false, 'value' => '-', 'message' => translate('Skipped — no connection.')],
            'row_format'       => ['label' => translate('InnoDB Row Format'), 'healthy' => false, 'value' => '-', 'message' => translate('Skipped — no connection.')],
            'charset'          => ['label' => translate('Server Character Set'), 'healthy' => false, 'value' => '-', 'message' => translate('Skipped — no connection.')],
            'primary_key_rule' => ['label' => translate('sql_require_primary_key'), 'healthy' => false, 'value' => '-', 'message' => translate('Skipped — no connection.')],
            'errors'           => ['count' => 1, 'errors' => [['section' => translate('Connection'), 'message' => $reason]]],
        ];
    }
}
