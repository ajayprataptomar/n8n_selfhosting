<?php

namespace App\Services\Core;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoService
{
    /**
     * Checks if the HTTP method is restricted for the current route.
     *
     * @param Request $request
     * @return bool
     */
    public function isMethodRestricted(Request $request): bool
    {
        $method = strtoupper($request->method());
        $config = config("demo.method_usage.$method");
        if ($config === null) {
            return false;
        }

        $enabled = Arr::get($config, 'enabled', false);
        $currentRoute = $request->route()->getName();
        $whitelist = Arr::get($config, 'whitelisted_routes', []);
        $blacklist = Arr::get($config, 'blacklisted_routes', []);
        $hasWhite = Arr::exists($config, 'whitelisted_routes');
        $hasBlack = Arr::exists($config, 'blacklisted_routes');
        $inWhite = in_array($currentRoute, $whitelist, true);
        $inBlack = in_array($currentRoute, $blacklist, true);
        $priority = Arr::get($config, 'priority', 'whitelisted_routes');

        // Base behavior: allow if enabled=true, block if enabled=false
        $isBlocked = $enabled ? false : true;

        // If enabled=true: use blacklist logic
        if ($enabled) {
            if ($hasBlack) {
                $isBlocked = empty($blacklist) ? true : $inBlack;
            }
            // Apply whitelist if present
            if ($hasWhite && !empty($whitelist)) {
                $isBlocked = !$inWhite;
            }
        }
        // If enabled=false: prioritize blacklist if non-empty, otherwise use whitelist or block all
        else {
            if ($hasBlack && !empty($blacklist)) {
                $isBlocked = $inBlack; // Only block blacklisted routes
            } elseif ($hasWhite) {
                $isBlocked = empty($whitelist) ? true : !$inWhite;
            }
        }

        // Handle conflicts when route is in both lists
        if ($hasWhite && $hasBlack && $inWhite && $inBlack) {
            $isBlocked = $priority === 'whitelisted_routes' ? false : true;
        }

        return $isBlocked;
    }

    /**
     * Retrieves the restriction message for the HTTP method.
     *
     * @param string $method
     * @return string
     */
    public function getMethodMessage(string $method): string
    {
        return config("demo.method_usage.$method.message", $this->getGlobalMessage());
    }

    /**
     * Returns a response with the restriction message for JSON or redirect.
     *
     * @param Request $request
     * @param string $message
     * @param string $status
     * @return mixed
     */
    public function getRestrictedResponse(Request $request, string $message, string $status = 'error'): mixed
    {
        try {
            if ($request->expectsJson() || $request->routeIs('api.*')) {
                return new JsonResponse(['message' => $message]);
            }
            return back()->withNotify([[$status, $message]]);
        } catch (\Exception $e) {
            return back()->withNotify([['error', 'An error occurred.']]);
        }
    }

    /**
     * Retrieves the global demo mode message.
     *
     * @return string
     */
    public function getGlobalMessage(): string
    {
        return config('demo.messages.global', 'This is a demo environment. Some actions are restricted.');
    }

    /**
     * Resets the database and files if conditions are met.
     *
     * @return void
     */
    public function resetDatabase(): void
    {
        if (!config('demo.enabled')) return;
        if (!$this->validateResetConfig()) return;

        $lockFile = storage_path('demo_reset.lock');
        $handle = $this->acquireFileLock($lockFile);

        if (!$handle) return;

        try {
            $now = Carbon::now();
            $lastReset = $this->getLastResetTime();
            $nextReset = $lastReset 
                ? $this->calculateNextResetTime($lastReset) 
                : $now;

            if ($now->greaterThanOrEqualTo($nextReset)) {
                $this->executeFileReset();
                $this->executeDatabaseReset($now);
            }
        } catch (\Throwable $throwable) {
            // Silent error handling to avoid disrupting reset
        } finally {
            Setting::updateOrInsert(
                ['key' => 'app_version'],
                ['key' => 'app_version', 'value' => Arr::get(config('installer.core'), 'appVersion', null)]
            );
            $this->releaseFileLock($handle);
        }
    }

    /**
     * Executes file system reset from backup ZIP.
     *
     * @return void
     */
    protected function executeFileReset(): void
    {
        $backupZipPath = resource_path('data/backup-file.zip');
        $baseAssetsPath = base_path('../assets');
        $targetDirectories = [$baseAssetsPath . '/file'];

        if (!file_exists($backupZipPath)) return;

        try {
            collect($targetDirectories)
                ->filter(fn($dir) => is_dir($dir))
                ->each(fn($dir) => $this->removeDirectory($dir));

            if (!is_dir($baseAssetsPath)) {
                mkdir($baseAssetsPath, 0755, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($backupZipPath) === true) {
                $zip->extractTo($baseAssetsPath);
                $zip->close();

                collect($targetDirectories)
                    ->filter(fn($dir) => is_dir($dir))
                    ->each(fn($dir) => chmod($dir, 0755));
            }
        } catch (\Throwable $throwable) {
            // Silent error handling
        }
    }

    /**
     * Removes a directory and its contents recursively.
     *
     * @param string $directory
     * @return bool
     */
    protected function removeDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        collect(array_diff(scandir($directory), ['.', '..']))
            ->each(function ($file) use ($directory) {
                $filePath = $directory . DIRECTORY_SEPARATOR . $file;
                is_dir($filePath) ? $this->removeDirectory($filePath) : unlink($filePath);
            });

        return rmdir($directory);
    }

    /**
     * Validates the database reset configuration.
     *
     * @return bool
     */
    protected function validateResetConfig(): bool
    {
        $unit = config('demo.database_reset_unit', 'hour');
        $duration = config('demo.database_reset_duration', 4);
        $validUnits = ['second', 'minute', 'hour', 'day', 'month', 'year'];

        return in_array($unit, $validUnits, true) && $duration > 0;
    }

    /**
     * Acquires a file lock for reset operations.
     *
     * @param string $lockFile
     * @return mixed
     */
    protected function acquireFileLock(string $lockFile): mixed
    {
        $handle = fopen($lockFile, 'a+');
        if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle) fclose($handle);
            return null;
        }
        return $handle;
    }

    /**
     * Releases a file lock.
     *
     * @param mixed $handle
     * @return void
     */
    protected function releaseFileLock($handle): void
    {
        if ($handle) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Retrieves the last reset time from storage.
     *
     * @return Carbon|null
     */
    protected function getLastResetTime(): ?Carbon
    {
        $resetFile = storage_path('demo_reset.json');
        if (Storage::exists('demo_reset.json')) {
            $data = json_decode(Storage::get('demo_reset.json'), true);
            return Arr::has($data, 'last_reset_at') ? Carbon::parse($data['last_reset_at']) : null;
        }
        return null;
    }

    /**
     * Calculates the next reset time based on configuration.
     *
     * @param Carbon $lastReset
     * @return Carbon
     */
    protected function calculateNextResetTime(Carbon $lastReset): Carbon
    {
        $unit = config('demo.database_reset_unit', 'hour');
        $duration = config('demo.database_reset_duration', 4);
        $nextReset = $lastReset->copy();

        return match ($unit) {
            'second' => $nextReset->addSeconds($duration),
            'minute' => $nextReset->addMinutes($duration),
            'hour' => $nextReset->addHours($duration),
            'day' => $nextReset->addDays($duration),
            'month' => $nextReset->addMonths($duration),
            'year' => $nextReset->addYears($duration),
            default => $nextReset,
        };
    }

    /**
     * Executes database reset using SQL file.
     *
     * @param Carbon $now
     * @return void
     */
    protected function executeDatabaseReset(Carbon $now): void
    {
        $sqlFile = resource_path('database/database_demo.sql');
        if (!file_exists($sqlFile)) return;

        try {
            $tables = DB::select('SHOW TABLES');
            $database = DB::getDatabaseName();
            $tableKey = 'Tables_in_' . $database;

            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            collect($tables)->each(fn($table) => DB::statement("DROP TABLE IF EXISTS `{$table->$tableKey}`"));
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            DB::unprepared(file_get_contents($sqlFile));
            Storage::put('demo_reset.json', json_encode(['last_reset_at' => $now->toDateTimeString()]));
        } catch (\Throwable $throwable) {
            // Silent error handling
        }
    }
}