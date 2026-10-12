<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanupLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:cleanup
                            {--days=7 : Delete log files older than this many days}
                            {--max-size=50 : Maximum total size in MB before aggressive cleanup}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old log files to free up storage space';

    /**
     * Log directories to clean
     *
     * @var array
     */
    protected $logDirectories = [
        'logs',
        'framework/cache/data',
        'framework/sessions',
        'framework/views',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $maxSizeMB = (int) $this->option('max-size');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subDays($days);

        $totalDeleted = 0;
        $totalSizeFreed = 0;

        $this->info("Log Cleanup Started");
        $this->info("==================");
        $this->info("Deleting files older than {$days} days (before {$cutoffDate->toDateString()})");

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No files will be deleted");
        }

        $storagePath = storage_path();

        // 1. Clean log files
        $this->info("\n📁 Cleaning log files...");
        $logsPath = storage_path('logs');

        if (File::isDirectory($logsPath)) {
            $files = File::files($logsPath);

            foreach ($files as $file) {
                $fileName = $file->getFilename();

                // Skip .gitkeep and .gitignore
                if (in_array($fileName, ['.gitkeep', '.gitignore'])) {
                    continue;
                }

                $fileTime = Carbon::createFromTimestamp($file->getMTime());
                $fileSize = $file->getSize();

                // Delete if older than cutoff OR if it's a daily log older than retention
                $shouldDelete = $fileTime->lt($cutoffDate);

                // Also check for laravel-YYYY-MM-DD.log pattern
                if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log/', $fileName, $matches)) {
                    $logDate = Carbon::createFromFormat('Y-m-d', $matches[1]);
                    $shouldDelete = $logDate->lt($cutoffDate);
                }

                if ($shouldDelete) {
                    $sizeMB = round($fileSize / 1024 / 1024, 2);

                    if ($dryRun) {
                        $this->line("  Would delete: {$fileName} ({$sizeMB} MB)");
                    } else {
                        File::delete($file->getPathname());
                        $this->line("  Deleted: {$fileName} ({$sizeMB} MB)");
                    }

                    $totalDeleted++;
                    $totalSizeFreed += $fileSize;
                }
            }
        }

        // 2. Clean old session files (older than session lifetime)
        $this->info("\n📁 Cleaning old session files...");
        $sessionsPath = storage_path('framework/sessions');

        if (File::isDirectory($sessionsPath)) {
            $sessionFiles = File::files($sessionsPath);
            $sessionCutoff = Carbon::now()->subMinutes(config('session.lifetime', 180));

            foreach ($sessionFiles as $file) {
                if ($file->getFilename() === '.gitkeep') {
                    continue;
                }

                $fileTime = Carbon::createFromTimestamp($file->getMTime());

                if ($fileTime->lt($sessionCutoff)) {
                    $fileSize = $file->getSize();

                    if (!$dryRun) {
                        File::delete($file->getPathname());
                    }

                    $totalDeleted++;
                    $totalSizeFreed += $fileSize;
                }
            }

            $this->line("  Cleaned sessions older than {$sessionCutoff->diffForHumans()}");
        }

        // 3. Clean compiled views
        $this->info("\n📁 Cleaning compiled views...");
        $viewsPath = storage_path('framework/views');

        if (File::isDirectory($viewsPath)) {
            $viewFiles = File::files($viewsPath);
            $viewCutoff = Carbon::now()->subDays(3); // Keep views for 3 days

            foreach ($viewFiles as $file) {
                if ($file->getFilename() === '.gitkeep') {
                    continue;
                }

                $fileTime = Carbon::createFromTimestamp($file->getMTime());

                if ($fileTime->lt($viewCutoff)) {
                    $fileSize = $file->getSize();

                    if (!$dryRun) {
                        File::delete($file->getPathname());
                    }

                    $totalDeleted++;
                    $totalSizeFreed += $fileSize;
                }
            }

            $this->line("  Cleaned old compiled views");
        }

        // 4. Check total log size and do aggressive cleanup if needed
        $this->info("\n📊 Checking total storage usage...");
        $totalLogSize = $this->getDirectorySize(storage_path('logs'));
        $totalLogSizeMB = round($totalLogSize / 1024 / 1024, 2);

        $this->line("  Current log folder size: {$totalLogSizeMB} MB");

        if ($totalLogSizeMB > $maxSizeMB) {
            $this->warn("  ⚠️  Log folder exceeds {$maxSizeMB} MB limit!");

            if (!$dryRun) {
                // Aggressive cleanup - keep only last 3 days
                $this->line("  Performing aggressive cleanup (keeping only last 3 days)...");
                $aggressiveCutoff = Carbon::now()->subDays(3);

                $files = File::files(storage_path('logs'));
                foreach ($files as $file) {
                    if (in_array($file->getFilename(), ['.gitkeep', '.gitignore'])) {
                        continue;
                    }

                    $fileTime = Carbon::createFromTimestamp($file->getMTime());
                    if ($fileTime->lt($aggressiveCutoff)) {
                        $fileSize = $file->getSize();
                        File::delete($file->getPathname());
                        $totalDeleted++;
                        $totalSizeFreed += $fileSize;
                    }
                }
            }
        }

        // Summary
        $this->info("\n✅ Cleanup Complete");
        $this->info("==================");
        $this->info("Files " . ($dryRun ? "to be " : "") . "deleted: {$totalDeleted}");
        $this->info("Space " . ($dryRun ? "to be " : "") . "freed: " . round($totalSizeFreed / 1024 / 1024, 2) . " MB");

        return Command::SUCCESS;
    }

    /**
     * Get total size of a directory
     *
     * @param string $path
     * @return int
     */
    protected function getDirectorySize(string $path): int
    {
        $size = 0;

        if (!File::isDirectory($path)) {
            return $size;
        }

        foreach (File::allFiles($path) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }
}
