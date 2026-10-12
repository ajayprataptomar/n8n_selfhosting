<?php
/**
 * XSender — One-shot repair script.
 *
 * Visit:   https://yourdomain/repair.php?token=xsender-repair
 *
 * Clears Laravel's bootstrap and runtime caches that can carry stale references
 * to dev-only packages after `composer dump-autoload --no-dev`. Use this after
 * a system update when you see:
 *
 *    Class "Barryvdh\Debugbar\ServiceProvider" not found
 *    Class "Facade\Ignition\..." not found
 *    Target class [...] does not exist
 *
 * Self-deletes after successful run for safety. Re-upload to use again.
 */

// ── Auth gate (basic token) ────────────────────────────────────────────────
$expected = 'xsender-repair';
$token    = $_GET['token'] ?? '';
if (!hash_equals($expected, (string) $token)) {
    http_response_code(403);
    echo '<h1>403 Forbidden</h1><p>Append <code>?token=xsender-repair</code> to the URL.</p>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>XSender Repair</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:20px;background:#f8f9fa;color:#1f2937}h1{color:#6366f1}pre{background:#fff;padding:12px;border-radius:6px;border:1px solid #e0e0e0;overflow-x:auto}.ok{color:#10b981}.err{color:#ef4444}.warn{color:#f59e0b}</style>';
echo '</head><body><h1>XSender Repair</h1>';

// ── Locate Laravel root ────────────────────────────────────────────────────
$srcDir = __DIR__ . '/src';
if (!is_dir($srcDir)) {
    // Fallback: maybe repair.php was placed inside src/ directly.
    $srcDir = __DIR__;
}

if (!is_file($srcDir . '/artisan')) {
    echo "<p class='err'>Cannot find Laravel app (looked in <code>{$srcDir}</code>).</p></body></html>";
    exit;
}

echo "<p>Laravel app root: <code>{$srcDir}</code></p>";

// ── Step 1: Delete bootstrap/cache/*.php ───────────────────────────────────
$cacheDir = $srcDir . '/bootstrap/cache';
$cleared  = [];
$kept     = [];

if (is_dir($cacheDir)) {
    $handle = opendir($cacheDir);
    while (($file = readdir($handle)) !== false) {
        if ($file === '.' || $file === '..' || $file === '.gitignore') {
            continue;
        }
        $full = $cacheDir . '/' . $file;
        if (is_file($full) && pathinfo($full, PATHINFO_EXTENSION) === 'php') {
            if (@unlink($full)) {
                $cleared[] = $file;
            } else {
                $kept[] = $file . ' (permission denied)';
            }
        }
    }
    closedir($handle);
}

echo '<h3>bootstrap/cache cleanup</h3><pre>';
echo 'Deleted: ' . (empty($cleared) ? '(none)' : implode(', ', $cleared)) . "\n";
if ($kept) echo 'Failed:  ' . implode(', ', $kept) . "\n";
echo '</pre>';

// ── Step 2: Delete storage/framework cache files ───────────────────────────
$frameworkDirs = [
    $srcDir . '/storage/framework/cache/data',
    $srcDir . '/storage/framework/views',
    $srcDir . '/storage/framework/sessions',
];

$deletedFiles = 0;
foreach ($frameworkDirs as $dir) {
    if (!is_dir($dir)) continue;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $file) {
        if ($file->isFile() && $file->getBasename() !== '.gitignore') {
            if (@unlink($file->getPathname())) {
                $deletedFiles++;
            }
        }
    }
}

echo "<h3>storage/framework cleanup</h3><pre>Cleared {$deletedFiles} cache file(s).</pre>";

// ── Step 2b: Reset OPcache ─────────────────────────────────────────────────
// OPcache holds compiled PHP bytecode in memory. Overwriting *.php files on disk
// does NOT update bytecode if OPcache is enabled with revalidate_freq > 0 (common on cPanel).
// Without this reset, "I uploaded the patch but it still serves the old code" is the result.
echo '<h3>OPcache reset</h3><pre>';
if (function_exists('opcache_reset')) {
    $ok = @opcache_reset();
    echo $ok
        ? "<span class='ok'>OK — OPcache cleared. All PHP files will recompile on next request.</span>"
        : "<span class='warn'>opcache_reset() returned false (may be restricted). Touching all .php in src/ as fallback…</span>";
    // Fallback: change mtime so OPcache invalidates per-file.
    if (!$ok) {
        $cnt = 0;
        $now = time();
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
                @touch($file->getPathname(), $now);
                $cnt++;
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($file->getPathname(), true);
                }
            }
        }
        echo " Touched {$cnt} PHP files.";
    }
} else {
    echo "<span class='warn'>opcache_reset() not available — OPcache likely off, nothing to do.</span>";
}
echo '</pre>';

// ── Step 3: Try artisan optimize:clear via PHP (best-effort) ───────────────
echo '<h3>artisan optimize:clear</h3><pre>';
try {
    require_once $srcDir . '/vendor/autoload.php';
    $app = require $srcDir . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->call('optimize:clear');
    echo "<span class='ok'>OK — caches cleared via Artisan.</span>";
} catch (\Throwable $e) {
    echo "<span class='warn'>Artisan run skipped: " . htmlspecialchars($e->getMessage()) . "</span>\n";
    echo "(That's fine — bootstrap/cache was already wiped above; Laravel will rebuild on next request.)";
}
echo '</pre>';

// ── Step 4: Self-delete ────────────────────────────────────────────────────
$selfDeleted = @unlink(__FILE__);
echo '<h3>Self-cleanup</h3><pre>';
echo $selfDeleted
    ? "<span class='ok'>repair.php deleted itself.</span>"
    : "<span class='warn'>Could not auto-delete repair.php — please delete it manually from the server.</span>";
echo '</pre>';

echo '<h3 class="ok">Done</h3><p>Open the app and check it loads. If the error persists, paste it here.</p>';
echo '</body></html>';
