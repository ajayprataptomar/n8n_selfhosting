<?php

use App\Enums\AndroidApiSimEnum;
use App\Enums\CampaignRepeatEnum;
use App\Enums\CampaignStatusEnum;
use App\Enums\ContactAttributeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PriorityStatusEnum;
use App\Enums\ServiceType;
use App\Enums\SettingKey;
use App\Enums\StatusEnum;
use App\Enums\SubscriptionStatus;
use App\Enums\System\CommunicationStatusEnum;
use App\Enums\System\SessionStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\WithdrawLogEnum;
use App\Models\Contact;
use App\Models\Language;
use App\Models\PricingPlan;
use App\Models\Setting;
use App\Models\Translation;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

	if(!function_exists('versioned_asset')) {
		/**
		 * Generate a versioned asset URL for cache busting.
		 * Appends ?v={app_version} so browsers fetch fresh files after each release.
		 */
		function versioned_asset(string $path): string {
			$version = config('app.app_version', '1.0.0');
			return asset($path) . '?v=' . $version;
		}
	}

	if(!function_exists('optimize_clear')) {
		/**
		 * Clear all caches after system update
		 * Works on servers with and without terminal access
		 * Uses multiple fallback methods to ensure cache is cleared
		 *
		 * IMPORTANT: After clearing, critical caches (installation status, domain
		 * verification) are automatically restored to prevent false redirects to
		 * the installer or license verification pages.
		 */
		function optimize_clear(): bool {
			$success = true;

			try {
				// Method 1: Try Artisan commands (works on most servers)
				try {
					Artisan::call('config:clear');
					Artisan::call('cache:clear');
					Artisan::call('view:clear');
					Artisan::call('route:clear');
					Artisan::call('event:clear');
				} catch (\Exception $e) {
					// Artisan failed, continue with manual methods
					\Log::warning('Artisan cache clear failed: ' . $e->getMessage());
				}

				// Method 2: Manually delete cache files (fallback for shared hosting)
				$cachePaths = [
					storage_path('framework/cache/data'),
					storage_path('framework/views'),
					base_path('bootstrap/cache'),
				];

				foreach ($cachePaths as $cachePath) {
					if (is_dir($cachePath)) {
						$files = glob($cachePath . '/*');
						foreach ($files as $file) {
							if (is_file($file) && basename($file) !== '.gitignore') {
								@unlink($file);
							} elseif (is_dir($file)) {
								// Recursively delete subdirectories
								$subFiles = glob($file . '/*');
								foreach ($subFiles as $subFile) {
									if (is_file($subFile)) {
										@unlink($subFile);
									}
								}
								@rmdir($file);
							}
						}
					}
				}

				// Method 3: Clear specific bootstrap cache files
				$bootstrapCacheFiles = [
					base_path('bootstrap/cache/config.php'),
					base_path('bootstrap/cache/routes-v7.php'),
					base_path('bootstrap/cache/services.php'),
					base_path('bootstrap/cache/packages.php'),
					base_path('bootstrap/cache/events.php'),
				];

				foreach ($bootstrapCacheFiles as $file) {
					if (file_exists($file)) {
						@unlink($file);
					}
				}

				// Method 4: Clear OPcache if available (important for PHP-FPM servers)
				if (function_exists('opcache_reset')) {
					@opcache_reset();
				}

				// Method 4b: Invalidate individual PHP files in key directories
				// This catches cases where opcache_reset() alone isn't sufficient
				if (function_exists('opcache_invalidate')) {
					$invalidateDirs = [
						base_path('app'),
						base_path('config'),
						base_path('routes'),
					];
					foreach ($invalidateDirs as $dir) {
						if (is_dir($dir)) {
							$phpFiles = glob($dir . '/*.php');
							foreach ($phpFiles as $phpFile) {
								@opcache_invalidate($phpFile, true);
							}
						}
					}
				}

				// Method 5: Clear APCu cache if available
				if (function_exists('apcu_clear_cache')) {
					@apcu_clear_cache();
				}

				// Method 6: Clear Laravel's internal cache
				if (Cache::getStore() instanceof \Illuminate\Cache\FileStore) {
					Cache::flush();
				} else {
					// For other cache drivers, try flush
					try {
						Cache::flush();
					} catch (\Exception $e) {
						// Some drivers may not support flush
					}
				}

				// Method 7: Clear compiled class file if exists (Laravel < 5.6)
				$compiledPath = base_path('bootstrap/cache/compiled.php');
				if (file_exists($compiledPath)) {
					@unlink($compiledPath);
				}

				// Method 8: Clear file status cache
				clearstatcache(true);

				// CRITICAL: Restore installation & domain verification caches
				// These must be re-cached AFTER flush to prevent false redirects
				// to the installer or license verification pages
				restore_critical_caches();

				// Fix file permissions for storage and cache directories
				// Prevents 500 errors when cron/supervisor creates files as different OS user
				fix_storage_permissions();

				\Log::info('System cache cleared successfully after update');

			} catch (\Exception $e) {
				$success = false;
				\Log::error('Failed to clear cache: ' . $e->getMessage());
			}

			return $success;
		}
	}

	if (!function_exists('fix_storage_permissions')) {
		/**
		 * Fix file permissions for storage and bootstrap/cache directories.
		 *
		 * When cron/supervisor runs as root or a different OS user, cache files
		 * (compiled views, file cache, bootstrap cache) get created with that user's
		 * ownership. The web server (www-data/php-fpm) then can't read/write them → 500 error.
		 *
		 * This function sets all files in these directories to 0664 and directories to 0775,
		 * making them group-writable so both cron and web server users can access them.
		 */
		function fix_storage_permissions(): void
		{
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				return; // Skip on Windows
			}

			try {
				$paths = [
					storage_path('framework/views'),
					storage_path('framework/cache'),
					storage_path('framework/sessions'),
					base_path('bootstrap/cache'),
				];

				foreach ($paths as $path) {
					if (!is_dir($path)) continue;

					// Set directory permissions
					@chmod($path, 0775);

					// Recursively fix permissions
					$iterator = new \RecursiveIteratorIterator(
						new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
						\RecursiveIteratorIterator::SELF_FIRST
					);

					foreach ($iterator as $item) {
						if ($item->isDir()) {
							@chmod($item->getPathname(), 0775);
						} else {
							@chmod($item->getPathname(), 0664);
						}
					}
				}
			} catch (\Throwable $e) {
				// Non-fatal — log but don't break
				\Log::warning('fix_storage_permissions failed: ' . $e->getMessage());
			}
		}
	}

	if (!function_exists('optimize_clear_detailed')) {
		/**
		 * Clear all caches and return detailed results for UI feedback.
		 * Same logic as optimize_clear() but tracks each step.
		 */
		function optimize_clear_detailed(): array {
			$results = ['cleared' => [], 'failed' => []];

			// Artisan commands
			$artisanCommands = ['config:clear', 'cache:clear', 'view:clear', 'route:clear', 'event:clear'];
			foreach ($artisanCommands as $cmd) {
				try {
					Artisan::call($cmd);
					$results['cleared'][] = $cmd;
				} catch (\Exception $e) {
					$results['failed'][] = $cmd . ': ' . $e->getMessage();
				}
			}

			// Manual cache file deletion
			$cachePaths = [
				'views' => storage_path('framework/views'),
				'cache' => storage_path('framework/cache/data'),
				'bootstrap' => base_path('bootstrap/cache'),
			];

			foreach ($cachePaths as $label => $cachePath) {
				if (is_dir($cachePath)) {
					$files = glob($cachePath . '/*');
					$deleted = 0;
					foreach ($files as $file) {
						if (is_file($file) && basename($file) !== '.gitignore') {
							if (@unlink($file)) $deleted++;
						} elseif (is_dir($file)) {
							$subFiles = glob($file . '/*');
							foreach ($subFiles as $subFile) {
								if (is_file($subFile)) @unlink($subFile);
							}
							@rmdir($file);
							$deleted++;
						}
					}
					if ($deleted > 0) $results['cleared'][] = "{$label} ({$deleted} files)";
				}
			}

			// OPcache
			if (function_exists('opcache_reset')) {
				@opcache_reset();
				$results['cleared'][] = 'opcache';
			}

			// APCu
			if (function_exists('apcu_clear_cache')) {
				@apcu_clear_cache();
				$results['cleared'][] = 'apcu';
			}

			// Laravel cache flush
			try {
				Cache::flush();
				$results['cleared'][] = 'laravel_cache';
			} catch (\Exception $e) {
				$results['failed'][] = 'laravel_cache: ' . $e->getMessage();
			}

			// File stat cache
			clearstatcache(true);
			$results['cleared'][] = 'file_stat_cache';

			// Restore critical caches
			try {
				restore_critical_caches();
				$results['cleared'][] = 'critical_caches_restored';
			} catch (\Exception $e) {
				$results['failed'][] = 'restore_caches: ' . $e->getMessage();
			}

			return $results;
		}
	}

	if (!function_exists('clear_sidebar_cache')) {
		/**
		 * Clear sidebar notification counts cache
		 * Call this when tickets, payments, or templates are updated
		 */
		function clear_sidebar_cache(): void
		{
			Cache::forget('admin_sidebar_counts');
			Cache::forget('user_sidebar_counts');
		}
	}

	if (!function_exists('clear_app_caches')) {
		/**
		 * Clear all application caches for troubleshooting
		 * Use this when clients report issues
		 *
		 * NOTE: After calling this, you should call restore_critical_caches()
		 * to prevent false redirects to installer/license pages
		 */
		function clear_app_caches(): void
		{
			Cache::forget('site_settings');
			Cache::forget('app_installation_status');
			Cache::forget('domain_verification_status');
			Cache::forget('top_bar_languages');
			Cache::forget('default_language');
			Cache::forget('available_languages');
			Cache::forget('admin_sidebar_counts');
			Cache::forget('user_sidebar_counts');
			Cache::forget('wp_node_configured');
			Cache::forget('software_license');
		}
	}

	if (!function_exists('restore_critical_caches')) {
		/**
		 * Restore critical caches after a cache clear operation
		 *
		 * Prevents the app from incorrectly redirecting to the installer
		 * or license verification page after cache is cleared.
		 * Checks the marker file and database to re-populate caches.
		 */
		function restore_critical_caches(): void
		{

			try {
				// 1. Restore installation status from marker file
				$cacheFileConfig = config('installer.cacheFile') ?: 'X2ZpbGVjYWNoZWluZw==';
				$logFile = storage_path(base64_decode($cacheFileConfig));

				if (file_exists($logFile)) {
					Cache::put('app_installation_status', true, now()->addDays(7));
				}

				// 2. Restore domain verification & license from database
				try {
					\Illuminate\Support\Facades\DB::connection()->getPdo();

					$settings = Setting::whereIn('key', [
						'is_domain_verified',
						'purchase_key',
						'envato_username',
					])->pluck('value', 'key')->toArray();

					// Restore domain verification cache
					if (Arr::get($settings, 'is_domain_verified') == \App\Enums\StatusEnum::TRUE->status()) {
						Cache::put('domain_verification_status', 'verified', now()->addDays(7));
					}

					// Restore license cache if purchase key and username exist in DB
					$purchaseKey = Arr::get($settings, 'purchase_key');
					$envatoUsername = Arr::get($settings, 'envato_username');
					if (!empty($purchaseKey) && !empty($envatoUsername)) {
						Cache::put('software_license', true, now()->addDays(1));
					}

				} catch (\Exception $e) {
					// DB unavailable - caches will be rebuilt on next successful request
					\Log::warning('Could not restore caches from DB: ' . $e->getMessage());
				}

			} catch (\Exception $e) {
				\Log::warning('restore_critical_caches failed: ' . $e->getMessage());
			}
		}
	}

	if(!function_exists('transformToCamelCase')) {

		function transformToCamelCase($string) {

			$string = preg_replace('/[\d_]/', '', $string);
			$camelCaseString = strtolower($string);
			$camelCaseString = str_replace('_', ' ', $camelCaseString);
			$camelCaseString = ucwords($camelCaseString);
			$camelCaseString = str_replace(' ', '', $camelCaseString);
			$camelCaseString = lcfirst($camelCaseString);
			return $camelCaseString;
		}
	}
	
	if (!function_exists('site_settings')) {
		/**
		 * Get site settings with aggressive caching
		 * Uses both Laravel cache (persistent) and static variable (request-level)
		 */
		function site_settings(string|null $key = null, mixed $default = null): string|array|null
		{
			// Static variable for request-level caching (avoids repeated cache reads)
			static $cachedSettings = null;

			try {
				// If we already loaded settings in this request, use them
				if ($cachedSettings !== null) {
					if ($key === null) {
						return $cachedSettings;
					}
					return $cachedSettings[$key] ?? config("site_settings.{$key}") ?? $default;
				}

				// Try to get from Laravel cache first (NO DB connection check here!)
				$settings = Cache::get("site_settings");

				if ($settings === null) {
					// Only check DB connection if cache miss
					try {
						\Illuminate\Support\Facades\DB::connection()->getPdo();
					} catch (\Exception $e) {
						return $default;
					}

					// Cache miss - fetch from database and cache for 24 hours
					$settings = Setting::pluck("value", 'key')->toArray();
					Cache::put("site_settings", $settings, now()->addHours(24));
				}

				// Store in static variable for this request
				$cachedSettings = $settings;

				if ($key === null) {
					return $settings;
				}

				return $settings[$key] ?? config("site_settings.{$key}") ?? $default;

			} catch (\Throwable $th) {
				return $default;
			}
		}
	}

	if (!function_exists('paginateNumber')) {

		function paginateNumber($number = 7) { 

			return $number;
		}
	}

	if (!function_exists('filterContactNumber')) {

		function filterContactNumber($contact): string {

			return preg_replace('/[^0-9]/', '', trim(str_replace('+', '', $contact)));
		}
	}
    
	// v4.9 — removed misplaced `build_post_fields` definition that was
	// wrapped in the WRONG `function_exists('filterContactNumber')` guard.
	// The canonical definition is at line ~1917 below (unchanged, properly
	// named). If `filterContactNumber` happened to be undefined on some
	// exotic install, this block would define `build_post_fields`, and then
	// the block at line 1917 would fatally redeclare it because 1917 has no
	// guard. Now defined exactly once.

	function filePath(): array {
	    $path['profile'] = [
	        'admin'=> [
	            'path'=>'assets/file/dashboard/image/profile',
	            'size'=>'400x400'
	        ],
	        'user'=> [
	            'path'=>'assets/file/images/user/profile',
	            'size'=>'400x400'
        	],
	    ];
		$path["contact"] = [
			'path'=>'assets/file/contact/temporary',
		];
        $path['import'] = [
            'path'=>'assets/file/import',
        ];
	    $path['payment_file'] = [
	        'path' => 'assets/file/payment/data',
	    ];
	    $path['email_uploaded_file'] = [
	        'path' => 'assets/file/email_uploaded_file',
	    ];
	    $path['payment_method'] = [
            'path'=>'assets/file/images/payment_method',
            'size'=>'600x600'
	    ];
	    $path['withdraw_method'] = [
            'path'=>'assets/file/images/payment_method',
            'size'=>'600x600'
	    ];
	    $path['panel_logo'] = [
	        'path' => 'assets/file/images/logoIcon',
			'size'=>'1200x400'
	    ];
	    $path['site_logo'] = [
	        'path' => 'assets/file/images/logoIcon',
			
	    ];
	    $path['admin_bg'] = [
	        'path' => 'assets/file/images/adminBg',
	    ];
	    $path['admin_card'] = [
	        'path' => 'assets/file/images/adminCard',
	    ];
        $path['frontend'] = [
            'path' => 'assets/file/images/frontend',
        ];
	    $path['ticket'] = [
	        'path' => 'assets/file/ticket',
	    ];
	    $path['favicon'] = [
	        'size' => '128x128',
	    ];
	    $path['site_icon'] = [
	        'size' => '100x100',
	    ];
	    $path['demo'] = [
            'path'=>'assets/file/sms',
            'path_email'=>'assets/file/email',
            'path_whatsapp'=>'assets/file/whatsapp',
	    ];
		$path['whatsapp'] = [
            'path_document'=>'assets/file/whatsapp/document',
            'path_audio'=>'assets/file/whatsapp/audio',
            'path_image'=>'assets/file/whatsapp/image',
            'path_video'=>'assets/file/whatsapp/video',
            'path_others'=>'assets/file/whatsapp/others',
	    ];
	    return $path;
	}

	function menuActive($routeName, $type = null): string {

		if (is_array($routeName) &&  in_array(Route::currentRouteName(), $routeName)) {
			
			return 'active';
		} else {
			if (request()->routeIs($routeName)) {
				
				return 'active';
			} else {
			}
		}
		
		return '';
	}

	function menuShow($routeName, $type = null): string {

		if (is_array($routeName) && in_array(Route::currentRouteName(), $routeName)) {
			
			return 'show';
		} else {
			
			if (request()->routeIs($routeName)) {
				
				return 'show';
			} else {
			}
		}
		return '';
	}

	function shortAmount($amount, $length = 2) {

        return round($amount, $length);
	}

	function diffForHumans($date): string {

	    return Carbon::parse($date)->diffForHumans();
	}


	/**
	 * @deprecated v4.9 — use `get_date_time($date, $format = null)` which reads
	 *  the format from site_settings('date_format') + site_settings('time_format')
	 *  so admin-side formatting changes flow through consistently. `getDateTime`
	 *  keeps working (widely used across resources + blades) and will be removed
	 *  in v5.1 after every caller is migrated.
	 */
	function getDateTime($date, $format = 'Y-m-d h:i A')
	{
	    return Carbon::parse($date)->translatedFormat($format);
	}

	function slug($name): string
    {
	   	return Str::slug($name);
	}

	function trxNumber(): string
    {
		$random = strtoupper(Str::random(10));
		return $random;
	}

	function randomNumber(): int
    {
		return mt_rand(1,10000000);
	}

	function uploadNewFile($file, $location, $old = null): string
    {
	   	if(!file_exists($location)){
			mkdir($location, 0777, true);
		}
	    if(!$location) throw new Exception('File could not been created.');
	    if ($old) {
	    	if(file_exists($location.'/'.$old) && is_file($location.'/'.$old)){
				@unlink($old.'/'.$old);
			}
	    }
	    $filename = uniqid() . time() . '.' . $file->getClientOriginalExtension();
	    $file->move($location,$filename);
	    return $filename;
	}

	/**
	 * Show image with fallback to default
	 * PHP 8.4 compatible - handles null values gracefully
	 *
	 * @param string|null $image
	 * @param string|null $size
	 * @return string
	 */
	function showImage(?string $image = null, ?string $size = null): string
    {
		if (!empty($image)) {
			// Try relative path first (from project root)
			if (file_exists($image) && is_file($image)) {
				return asset($image);
			}

			// Try with base_path (handles cases where CWD != project root)
			// Our project: base_path('..') = project root (since Laravel is in src/)
			$absolutePath = base_path('../' . $image);
			if (file_exists($absolutePath) && is_file($absolutePath)) {
				return asset($image);
			}

			// Try from project root directly
			$projectRoot = dirname(base_path());
			$fromRoot = $projectRoot . '/' . ltrim($image, '/');
			if (file_exists($fromRoot) && is_file($fromRoot)) {
				return asset($image);
			}
		}

		// Return default placeholder based on size
		if ($size) {
			return route('default.image', $size);
		}

		return asset('assets/file/default.jpg');
	}

	function number($amount, $length = 2)
    {
	    $amount = round($amount, $length);
	    return $amount;
	}

	function textSorted($text): string
    {
	    return ucfirst(preg_replace("/[^A-Za-z0-9 ]/", ' ', $text));
	}

	function limit($text, $length): string
    {
		$value = Str::limit($text, $length);
		return $value;
	}

	function serverExtensionCheck($name): bool
    {
        if (!extension_loaded($name)) {
            return $response = false;
        }else {
            return $response = true;
        }
    }

	function checkFolderPermission($name): bool
    {
		$perm = substr(sprintf('%o', fileperms($name)), -4);
		if ($perm >= '0775') {
			$response = true;
		} else {
			$response = false;
		}
		return $response;
	}

	function  charactersLeft()
	{
		$user = auth()->user();
		return $user->credit * 160;
	}

	function  charactersLeftWa()
	{
		$user = auth()->user();
		return $user->whatsapp_credit * 320;
	}


	function curlContent($url): bool|string
    {
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    $result = curl_exec($ch);
	    curl_close($ch);
	    return $result;
	}


	function labelName($text): string
    {
	    return ucfirst(preg_replace("/[^A-Za-z0-9 ]/", ' ', $text));
	}


	function uploadImage($file, $location, $size = null, $old = null, $thumb = null): string
    {
	    if(!file_exists($location)){
			mkdir($location, 0755, true);
		}
		if($old){
			if(file_exists($location.'/'.$old) && is_file($location.'/'.$old)){
				@unlink($location.'/'.$old);
			}
		}
	    $filename = uniqid() . time() . '.' . $file->getClientOriginalExtension();

	    // Intervention Image v3
	    $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
	    $manager = new ImageManager($driver);
	    $image = $manager->read($file->getRealPath());

	    if ($size) {
	        $sizeArr = explode('x', strtolower($size));
	        $image->resize((int)$sizeArr[0], (int)$sizeArr[1]);
	    }
	    $image->save($location . '/' . $filename);
	    if ($thumb) {
	        $thumbArr = explode('x', $thumb);
	        $thumbImage = $manager->read($file->getRealPath());
	        $thumbImage->resize((int)$thumbArr[0], (int)$thumbArr[1])->save($location . '/thumb_' . $filename);
	    }
	    return $filename;
	}


	if (!function_exists('tabId')) {

		function tabId($id, $instance = null) {
	
			session(['active_tab' => $id]);
	
			if ($instance instanceof Redirector) {

				return $instance->to(app('url')->previous());
			} elseif ($instance instanceof RedirectResponse) {

				$instance->setTargetUrl(app('url')->previous());
				return $instance;
			}
	
			return redirect()->back();
		}
	}

	if (!function_exists('get_status_bg')) {
        function get_status_bg($status)
        {
            $status = strtolower($status);
            switch ($status) {
                case 'all':
                    $value = "<span class=\"badge badge-soft-all align-middle\">
                                <i class=\"bi bi-check-circle me-1\"></i> All
                              </span>";
                    return $value;
                    break;
                case ($status == 'success' || $status == 'completed' ):
                    $status = ucFirst($status);
                    $value = "<span class=\"badge badge-soft-success align-middle\">
                                <i class=\"bi bi-check-circle me-1\"></i> $status
                              </span>";
                    return $value;
                    break;
                case ($status == 'active' || $status == 'yes' ) :
                    $status = ucFirst($status);
                    $value = "<span class=\"badge badge-soft-success align-middle\">
                                <i class=\"bi bi-check-circle me-1\"></i>    $status
                              </span>";
                    return $value;
                    break;
                case 'pending':
                    $value = "<span class=\"badge badge-soft-warning align-middle\">
                                <i class=\"bi bi-check2-all me-1\"></i> Pending
                              </span>";
                    return $value;
                    break;
                case ($status == 'processing' || $status == 'ongoing' ):
                    $status = ucFirst($status);
                    $value = "<span class=\"badge badge-soft-info align-middle\">
                                <i class=\"bi bi-capslock me-1\"></i> $status
                              </span>";
                    return $value;
                    break;
                case ($status == 'failed' || $status == 'fail' || $status == 'no' ):
                    $status = ucFirst($status);
                    $value = "<span class=\"badge badge-soft-danger align-middle\">
                                <i class=\"bi bi-exclamation-octagon me-1\"></i>  $status
                              </span>";
                    return $value;
                    break;

                case  'schedule':
                    $status = ucFirst($status);


                    $value = "<span class=\"badge badge-soft-danger align-middle\">
                                <i class=\"bi bi-exclamation-octagon me-1\"></i> $status
                                </span>";
                    return $value;
                    break;

                case  'deactive':
                    $status = ucFirst($status);


                    $value = "<span class=\"badge badge-soft-danger align-middle\">
                                <i class=\"bi bi-exclamation-octagon me-1\"></i> Inactive
                              </span>";
                    return $value;
                    break;

                default:
                    $value = "<span class=\"badge badge-soft-secondary align-middle\">
                                <i class=\"bi bi-exclamation-triangle me-1\"></i> Undefined
                              </span>";
                    return $value;
                    break;
            }
        }
    }


	/**
     * current months total day
     */
    function days_in_month($month,$year){
		return cal_days_in_month(CAL_GREGORIAN, $month,$year);
	}

	 /**
	  * current months total day
	  */
	  function days_in_year(){
		 $year = date("Y");
		 $days=0;
		 for($month=1;$month<=12;$month++){
			 $days = $days + days_in_month($month,$year );
		 }
		 return $days;
	  }


	function buildDomDocument($text)
	{
	    $dom = new \DOMDocument();
	    libxml_use_internal_errors(true);
	    $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $text);
	    libxml_use_internal_errors(false);
	    $imageFile = $dom->getElementsByTagName('img');
        if ($imageFile) {
            // Intervention Image v3
            $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
            $manager = new ImageManager($driver);

            foreach($imageFile as $item => $image){
                $data = $image->getAttribute('src');
                $check_b64_data = preg_match("/data:([a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+).base64,.*/", $data);
                if ($check_b64_data) {
                    list($type, $data) = explode(';', $data);
                    list(, $data)      = explode(',', $data);
                    $imgeData = base64_decode($data);
                    $image_name= time().$item.'.png';
                    $save_path       = filePath()['email_uploaded_file']['path'];
                    try {
						if (!file_exists($save_path)) {
							mkdir($save_path, 0777, true);
						}
                        $manager->read($imgeData)->save($save_path.'/'.$image_name);
                        $getpath = asset('assets/file/email_uploaded_file/'.$image_name);
                        $image->removeAttribute('src');
                        $image->setAttribute('src', $getpath);
                    } catch (Exception $e) {

                    }
                }
            }
        }
	    $html = $dom->saveHTML();

		$html = html_entity_decode($html, ENT_COMPAT, 'UTF-8');
	    return $html;
	}


	if (!function_exists('carbon')) {
		/**
		 * @param string|null $date
		 * @return Carbon
		 */
		function carbon(?string $date = null): Carbon
		{
			if (!$date) {
				return Carbon::now();
			}

			return (new Carbon($date));
		}
	}

	if (!function_exists('translate')) {
		/**
		 * Translate a keyword to the current language
		 * PHP 8.4 compatible - explicit nullable type
		 *
		 * @param string|null $keyWord
		 * @param string|null $lang_code
		 * @return string
		 */
		function translate(?string $keyWord = null, ?string $lang_code = null): string
		{
			if ($keyWord === null || $keyWord === '') {
				return '';
			}
			try {
				// Prioritize language from request header (set by client from localStorage)
				$lang_code = $lang_code ?? request()->header('X-App-Language') ?? session('locale', App::getLocale());
				
				// Fall back to default language if lang_code is invalid or unavailable
				$language = \App\Models\Language::where('code', $lang_code)->first();
				if (!$language) {
					$lang_code = \App\Models\Language::where('is_default', StatusEnum::TRUE->status())->value('code') ?? 'us';
				}

				$lang_key = preg_replace('/[^A-Za-z0-9\_]/', '', str_replace(' ', '_', strtolower($keyWord)));
				$translate_data = Cache::remember('translations-' . $lang_code, now()->addHour(), function () use ($lang_code) {
					return Translation::where('code', $lang_code)->pluck('value', 'key')->toArray();
				});

				if (!array_key_exists($lang_key, $translate_data)) {
					// Try fallback language if key is missing
					$default_lang = \App\Models\Language::where('is_default', StatusEnum::TRUE->status())->value('code') ?? 'us';
					if ($lang_code !== $default_lang) {
						$fallback_data = Cache::remember('translations-' . $default_lang, now()->addHour(), function () use ($default_lang) {
						return Translation::where('code', $default_lang)->pluck('value', 'key')->toArray();
						});
						if (array_key_exists($lang_key, $fallback_data)) {
						return ucwords(strip_tags($fallback_data[$lang_key]));
						}
					}

					// Create new translation if missing in both preferred and fallback languages
					$translate_val = str_replace(array("\r", "\n", "\r\n"), "", $keyWord);
					Translation::create([
						'code' => $lang_code,
						'key' => $lang_key,
						'value' => $translate_val
					]);
					Cache::forget('translations-' . $lang_code);
					$keyWord = $translate_val;
				} else {
					$keyWord = $translate_data[$lang_key];
				}
			} catch (\Throwable $th) {
				// Handle errors silently, return original keyword
			}

			return ucwords(strip_tags($keyWord));
		}
	}


/**
 * @param $langCode
 * @return bool|string
 */
    function getLangFile($langCode): bool|string
    {
        return file_get_contents(resource_path(config('constants.options.langFilePath')). $langCode.'.json');
    }


    /**
     *
     */
    function offensiveMsgBlock($requestMessage)
	{
		$path = base_path('lang/globalworld/offensive.json');
        $offensiveData = json_decode(file_get_contents($path), true);
		$message = explode(' ', $requestMessage);
		foreach ($offensiveData as $key => $value) {
			foreach($message as $msgKey => $item){
				if(strtolower($item) == strtolower($key)){
					$message[$msgKey] = $value;
					Session::put('offsensiveNotify', "& We found some offsensive word");
				}
			}
		}
		$message = implode(' ',$message);

		return $message;
	}

    function download_from_url(string $url, string $prefix = ''): ?string
    {
        if (! $stream = @fopen($url, 'r')) {
            throw new \Exception('Can not open file from ' . $url);
        }

        $tempFile = tempnam(sys_get_temp_dir(), $prefix);

        if (file_put_contents($tempFile, $stream)) {
            return $tempFile;
        }

        return null;
    }

	function logStatus($status)
	{
        switch ($status) {
            case 1:
                $status = 'Pending';
                break;
            case 3:
                $status = 'Fail';
                break;
			case 4:
				$status = 'Success';
				break;
            default:
                $status = 'Schedule';
                break;
        }
	    return $status;
	}



    function convertTime($seconds): string
    {
		$hours = floor($seconds / 3600);
		$minutes = floor(($seconds % 3600) / 60);
		$seconds = $seconds % 60;

		$result = '';

		if ($hours > 0) {
		    $result .= $hours . ' hour';
		    if ($hours > 1) {
		      $result .= 's';
		    }
		    $result .= ' ';
		}

		if ($minutes > 0) {
		    $result .= $minutes . ' minute';
		    if ($minutes > 1) {
		      $result .= 's';
		    }
		    $result .= ' ';
		}

		if ($seconds > 0) {
		    $result .= $seconds . ' second';
		    if ($seconds > 1) {
		      $result .= 's';
		    }
		}

		return $result;
	}


	if (!function_exists('subscription_status')) { 

		function getFrontendSection($default = false)
		{
			$jsonUrl 	= resource_path('data/frontend_section.json');
			$sections = json_decode(file_get_contents($jsonUrl), true);
			$formattedSections = [];
			foreach ($sections as $key => $value) {
				$newKey = textFormat(['_'], $key, '-'); 
				$formattedSections[strtolower($newKey)] = $value;  
			}
			if ($default) ksort($formattedSections);
			return $formattedSections;
		}
	}


    function setInputLabel(string $text): string
    {
        $text = preg_replace('/[^A-Za-z0-9 ]/', ' ', $text);
        return ucfirst($text);
    }

    function getArrayValue($arr,  $key ="", $default = [])
    {
        return \Illuminate\Support\Arr::get((array)$arr, $key, '');
    }

    function getTranslatedArrayValue($arr,  $key ="", $default = [])
    {
        return translate(\Illuminate\Support\Arr::get((array)$arr, $key, ''));
    }

	if (!function_exists('str_unique')) {
        /**
         * @param int $length
         * @return string
         */
        function str_unique(int $length = 30): string
        {
            $side = rand(0,1);
            $salt = rand(0,9);
            $len = $length - 1;
            $string = \Illuminate\Support\Str::random($len <= 0 ? 7 : $len);
            $separatorPos = (int) ceil($length/4);
            $string = $side === 0 ? ($salt . $string) : ($string . $salt);
            $string = substr_replace($string, '-', $separatorPos, 0);
            return substr_replace($string, '-', negative_value($separatorPos), 0);
        }
    }
	if (!function_exists('negative_value')) {
        /**
         * @param int|float $value
         * @param $float
         * @return int|float
         */
        function negative_value(int|float $value, $float = false): int|float
        {
            if ($float) {
                $value = (float) $value;
            }
            return 0 - abs($value);
        }
    }

	if(!function_exists('convert_unit')) {
		
		/**
         * @param int|float $value
         * @param $float
         * @return string
         */

		 function convert_unit(int|float $bytes, $decimals = 2) {
			$size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		
			$factor = floor((strlen($bytes) - 1) / 3);
		
			return sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)) . @$size[$factor];
		}
	}

	if(!function_exists('planAccess')) {

		/**
         * @param mixed $user
         * @return mixed
         */

		function planAccess(mixed $user) {

			$plan_type = [
				"user"  => StatusEnum::FALSE->status(),
				"admin" => StatusEnum::TRUE->status()
			];

			$gateway_type = array_keys(config("planaccess.pricing_plan"));

			$user_plan = $user->runningSubscription()?->currentPlan();

			if($user_plan) {
				$allowed_access = [];
				$allowed_access["type"] = $user_plan->type;

				// Process messaging channels (sms, email, whatsapp)
				foreach((array)$gateway_type as $gateway) {

					$gateway_info = (array)$user_plan->$gateway;


					if(array_key_exists("android", $gateway_info)){

						$android_data = (array)$gateway_info["android"];
						if($allowed_access["type"] == StatusEnum::TRUE->status()) {
							unset($android_data["gateway_limit"]);
						}
						$allowed_access["android"] = $android_data;
						unset($gateway_info["android"]);

					}

					unset($gateway_info["credits"]);
					$allowed_access[$gateway] = $gateway_info;
				}

				// Add advanced features (lead_generation, automation, ai_intelligence)
				if ($user_plan->lead_generation) {
					$allowed_access['lead_generation'] = (array) $user_plan->lead_generation;
				}

				if ($user_plan->automation) {
					$allowed_access['automation'] = (array) $user_plan->automation;
				}

				if ($user_plan->ai_intelligence) {
					$allowed_access['ai_intelligence'] = (array) $user_plan->ai_intelligence;
				}

				return $allowed_access;
			} else {
            	return [];
            }
		}
	}

	if(!function_exists('checkCredit')) {

		/**
         * @param mixed $user
		 * 
		 * @param string $service_type
		 * 
         * @return boolean
         */

		function checkCredit(mixed $user, string $service_type, $credit_count = 1) {
            if (!$user) {
                return false; // or handle the null case as appropriate
            }

            $column = $service_type."_credit";
            $pass = true;

            if ($user->{$column} && (int)$user->{$column} < $credit_count && (int)$user->{$column} != -1) {
                $pass = false;
            }

            return $pass;
        }

	}

	if (!function_exists('code_correction')){
		function code_correction(string $code):string
		{
			if(!stripos($code, "#")) {
				$code = "#".$code;
			}
			
			return  "$code";
		}
	}

	if(!function_exists("nameSplit")) {
		/**
		 * Split full name into parts
		 * PHP 8.4 compatible - explicit nullable type
		 *
		 * @param string|null $fullName
		 * @return array
		 */
		function nameSplit(?string $fullName = null): array {
			$data = [];
			$matches = [];
			$fullName = $fullName ?? '';

			if (!empty($fullName) && preg_match('/^([a-z]+\.)?\s*?(\S+)(?:\s*?(\S*))?$/i', $fullName, $matches)) {
				$prefix = $matches[1] ?? '';
				$firstName = $matches[2] ?? '';
				$lastName = $matches[3] ?? '';
			} else {
				$prefix = '';
				$firstName = $fullName;
				$lastName = '';
			}

			$data["prefix"]    = $prefix;
			$data["first_name"] = $firstName;
			$data["last_name"]  = $lastName;
		
			return $data;
		}
	}

	if(!function_exists("textFormat")) {

		/**
		 * Format text by replacing symbols
		 * PHP 8.4 compatible - explicit nullable types
		 *
		 * @param array|null $symbols
		 * @param mixed $data
		 * @param string|null $replace_with
		 * @return string
		 */
		function textFormat(?array $symbols = null, mixed $data = '', ?string $replace_with = null): string {
			if ($data === null) {
				return '';
			}
			$convertedString = ucwords(str_replace($symbols ?? [], $replace_with ?? ' ', (string) $data));
			return $convertedString;
		}
	}

	if(!function_exists("slice_array_pagination")) {
		/**
		 * Slice array with pagination
		 * PHP 8.4 compatible - explicit nullable types
		 *
		 * @param array|null $data
		 * @return \Illuminate\Pagination\LengthAwarePaginator
		 */
		function slice_array_pagination(?array $data = null) {
			$data = $data ?? [];
			$result = new Illuminate\Pagination\LengthAwarePaginator(
                array_slice($data, (request()->get('page', 1) - 1) * paginateNumber(site_settings("paginate_number")), paginateNumber(site_settings("paginate_number"))),
                count($data),
                paginateNumber(site_settings("paginate_number")),
                request()->get('page', 1),
                ['path' => url()->current()]
            );
			return $result;
		}
	}

	if(!function_exists("generateText")) {
		function generateText($type) {
			
            $words = [
                "first_name" => ['David', 'charlie', 'tony', 'steve', 'natasha', 'walter', 'jesse'],
                "last_name" => ['warner', 'nelson', 'murdock', 'adams'],
                "object" => ['bus', 'flower', 'house', 'ball', 'keys'],
                "email" => ['random61@mail.com', 'random45@mail.com', 'random43@mail.com', 'random42@mail.com', 'random41@mail.com'],
            ];

			$extract = $words[$type];
            shuffle($extract);
            return ucfirst($extract[0]);
        }
	}

	if(!function_exists("generateDemoFile")) {

		function generateDemoFile($type, $conditionExlude = [], $allow_attribute = false) {

            
        }
	}




	if (!function_exists("textSpinner")) {

		/**
		 * textSpinner
		 *
		 * @param string|null $text
		 * 
		 * @return array|string|null
		 */
		function textSpinner(string|null $text): array|string|null {
			if (!$text) return $text;
			$pattern = '/{([^{}]*\|[^{}]*)}/';
			return preg_replace_callback($pattern, function ($matches) {
				$options = explode('|', $matches[1]);
				
				return $options[array_rand($options)];
			}, $text);
		}
		 
	}

	if (!function_exists("get_property_key")) {

		function get_property_key($needle, $haystack){
			return array_search(strtolower($needle), array_map('strtolower', $haystack));
		}
	}

	if (!function_exists("storeCloudMediaAndGetLink")) {

		function storeCloudMediaAndGetLink($requestKey, $file) {

			$fileType = explode('_', $requestKey)[0];
			$fileFieldType = explode('_', $requestKey)[1] . '_' . explode('_', $requestKey)[2];

			$directory = '';
			switch ($fileType) {
				case 'image':
					$directory = storage_path('../../assets/file/cloud_api/header/image');
					break;
				case 'video':
					$directory = storage_path('../../assets/file/cloud_api/header/video');
					break;
				case 'document':
					$directory = storage_path('../../assets/file/cloud_api/header/document');
					break;
				default:
					return null;
			}
			
			if (!File::isDirectory($directory)) {
				File::makeDirectory($directory, 0755, true, true);
			}

			$fileName = $file->getClientOriginalName();
			$filePath = $file->move($directory, $fileName);
			
			return asset('storage/assets/file/cloud_api/header/' . $fileType . '/' . $fileName);
		}
	}

	/**
	 *
	 * @param float $number
	 * @param int $precision
	 * @return string
	 */
	if (!function_exists("formatNumber")) {

		function formatNumber($number, $precision = 2) {

			if (!is_numeric($number)) {
				return "Invalid number format";
			}

			$number = (float) $number;
			$negative = $number < 0;
			$number = abs($number);

			if ($number < 1000) {
				return ($negative ? "-" : "") . number_format($number, $precision);
			}

			$formatters = [
				['Y', 1e24], // Yotta
				['Z', 1e21], // Zetta
				['E', 1e18], // Exa
				['P', 1e15], // Peta
				['T', 1e12], // Tera
				['B', 1e9],  // Billion
				['M', 1e6],  // Million
				['K', 1e3],  // Thousand
			];

			foreach ($formatters as $formatter) {
				[$suffix, $div] = $formatter;
				if ($number >= $div) {
					$formattedNumber = floor($number / $div * pow(10, $precision)) / pow(10, $precision);
					return ($negative ? "-" : "") . number_format($formattedNumber, $precision) . $suffix;
				}
			}

			return ($negative ? "-" : "") . number_format($number, $precision);
		}
	}
	 

	/**
	 * Truncate a string to a specified length and append an ellipsis if necessary.
	 *
	 * @param string 
	 * @param int
	 * @return string 
	 */
	if (!function_exists('truncate_string')) {
		
		function truncate_string($string, $length = 20) {
			
			if (strlen($string) > $length) {
				return substr($string, 0, $length) . '...';
			}
			return $string;
		}
	}

	if (!function_exists('convertCurrency')) {
		function convertCurrency($amount, $fromCurrency, $toCurrency) {
			$currencies = json_decode(site_settings("currencies"), true);
			
			if (!isset($currencies[$fromCurrency]) || !isset($currencies[$toCurrency])) {
				return null;
			}

			$fromRate = floatval($currencies[$fromCurrency]['rate']);
			$toRate 	= floatval($currencies[$toCurrency]['rate']);
			
			$usdAmount = $amount / $fromRate;
			$convertedAmount = $usdAmount * $toRate;
			
			return number_format($convertedAmount, 2, '.', '');
		}
	}

	if (!function_exists('convert_to_default_currency')) {

		/**
		* Convert an amount from a given currency to the default currency.
		*
		* @param string $currencyCode The currency code (e.g., 'USD', 'BDT').
		* @param float|string $amount The amount in the given currency.
		* @return float|string The converted amount in the default currency.
		* @throws \App\Exceptions\ApplicationException If currency code is invalid or no default currency is found.
		*/
		function convert_to_default_currency($currencyCode, $amount, $precision = 2)
		{
			$currencies = json_decode(site_settings('currencies'), true);
			
			if (!isset($currencies[$currencyCode])) return "0.00"; 

			$defaultCurrency = null;
			foreach ($currencies as $code => $currency) {
				if ($currency['is_default'] == \App\Enums\StatusEnum::TRUE->status()) {
					$defaultCurrency = $code;
					break;
				}
			}

			if (!$defaultCurrency) return "0.00"; 
			$inputRate 		= $currencies[$currencyCode]['rate'] ?? 1;
			$defaultRate 		= $currencies[$defaultCurrency]['rate'] ?? 1;
			if (function_exists('bcdiv') && function_exists('bcmul')) {
				$convertedAmount = ($inputRate > 0) ? bcdiv(bcmul($amount, $defaultRate, 8), $inputRate, 8) : 0;
			} else {
				$convertedAmount = ($inputRate > 0) ? ($amount * $defaultRate) / $inputRate : 0;
			}
			return formatNumber($convertedAmount, $precision);
		}

	}

	/**
	 * 
	 * @param array 
	 * @return string|null 
	 */
	if (!function_exists('getDefaultCurrencyCode')) {
		
		function getDefaultCurrencyCode(array|null $data = null)
		{
			if(!$data) {
				$data = getActiveCurrencies();
			}
			foreach ($data as $key => $value) {
				if (isset($value['is_default']) && $value['is_default'] == 1) {
					return $key;
				}
			}
			return null;
		}
	}

	/**
	 * 
	 * @param array 
	 * @return string|null 
	 */
	if (!function_exists('getDefaultCurrencySymbol')) {
		
		function getDefaultCurrencySymbol(array|null $data = null)
		{
			if(!$data) {
				$data = getActiveCurrencies();
			}
			foreach ($data as $key => $value) {
				if (isset($value['is_default']) && $value['is_default'] == 1) {
					return $value['symbol'];
				}
			}
			return null;
		}
	}

	if (!function_exists('getActiveCurrencies')) {
		
		function getActiveCurrencies() {
			$currencies = json_decode(site_settings("currencies"), true);
			return array_filter($currencies, 
				fn($currency) => 
					Arr::get($currency,'status') == StatusEnum::TRUE->status());
		}
	}
	
	if (!function_exists('getCurrencySymbol')) {
		
		function getCurrencySymbol($code) {
			$currencies = json_decode(site_settings("currencies"), true);
			return $currencies[$code]['symbol'] ?? '--';
		}
	}

	if (!function_exists('hexToRgba')) {

		/**
		 * Convert hex color to RGBA.
		 *
		 * @param string $hex The hex color code.
		 * @param float  $alpha The alpha value (0 to 1).
		 * @return string The RGBA color string.
		 * @throws Exception If the hex color code is invalid.
		 */
		function hexToRgba($hex, $alpha = 0.2) {

			$hex = ltrim($hex, '#');
			if (strlen($hex) === 6) {

				list($r, $g, $b) = array(

					hexdec(substr($hex, 0, 2)),
					hexdec(substr($hex, 2, 2)),
					hexdec(substr($hex, 4, 2))
				);
			} elseif (strlen($hex) === 3) {
				
				list($r, $g, $b) = array(
					hexdec(str_repeat(substr($hex, 0, 1), 2)),
					hexdec(str_repeat(substr($hex, 1, 1), 2)),
					hexdec(str_repeat(substr($hex, 2, 1), 2))
				);
			} else {
				throw new Exception("Invalid hex color code.");
			}
			if ($alpha < 0) {

				$alpha = 0;
			} elseif ($alpha > 1) {

				$alpha = 1;
			}
			return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $alpha);
		}
	}

	if (!function_exists('rgbaToHex')) {

		/**
		 * Convert RGBA color to hex color.
		 *
		 * @param string $rgba The RGBA color string.
		 * @return string The hex color code.
		 * @throws Exception If the RGBA color string is invalid.
		 */
		function rgbaToHex($rgba) {
			// Extract the RGBA values
			if (preg_match('/rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]*\.?[0-9]+)\s*\)/', $rgba, $matches)) {
				$r = intval($matches[1]);
				$g = intval($matches[2]);
				$b = intval($matches[3]);
				$alpha = floatval($matches[4]);
	
				// Ensure the RGB values are within the valid range
				if ($r < 0 || $r > 255 || $g < 0 || $g > 255 || $b < 0 || $b > 255 || $alpha < 0 || $alpha > 1) {
					throw new Exception("Invalid RGBA color string.");
				}
	
				// Convert the RGB values to hex
				$hex = sprintf('#%02X%02X%02X', $r, $g, $b);
	
				return $hex;
			} else {
				throw new Exception("Invalid RGBA color string.");
			}
		}
	}


	if (!function_exists('t2k')) {

		function t2k(string $text ,?string $replace = "_") :string {
		   return strtolower(strip_tags(str_replace(' ', $replace, $text)));
		}
	 }

	if (!function_exists('campaign_repeat_status')){
		function campaign_repeat_status(mixed  $status) :string
		{
         $badges  = [
            CampaignRepeatEnum::DAY->value   => "warning-soft",
            CampaignRepeatEnum::WEEK->value  => "success-soft",
            CampaignRepeatEnum::MONTH->value => "danger-solid",
            CampaignRepeatEnum::YEAR->value  => "info-soft"
         ];

         $class  = Arr::get($badges , $status , 'primary-soft');
         $status = ucfirst(t2k(Arr::get(array_flip(CampaignRepeatEnum::toArray()), $status, 'Hour')));
         return "<span class=\"i-badge $class\">$status</span>";

		}
   	}
	if (!function_exists('campaign_status')) {

		function campaign_status(mixed  $status) :string {

			$badges  = [
				CampaignStatusEnum::CANCEL->value  	 => "dark-soft",
				CampaignStatusEnum::ACTIVE->value    => "primary-soft",
				CampaignStatusEnum::DEACTIVE->value  => "danger-solid",
				CampaignStatusEnum::COMPLETED->value => "success-soft",
				CampaignStatusEnum::ONGOING->value   => "info-soft"
			];
			$class  = Arr::get($badges , $status , 'primary-soft');
			$status = ucfirst(t2k(Arr::get(array_flip(CampaignStatusEnum::toArray()), $status, 'Cancel')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}
	if (!function_exists('communication_status')) {

		function communication_status(mixed  $status) :string {
			
			$badges  = [
				// CommunicationStatusEnum::CANCEL->value     => "dark-soft",
				CommunicationStatusEnum::PENDING->value    => "primary-soft",
				CommunicationStatusEnum::SCHEDULE->value   => "info-soft",
				CommunicationStatusEnum::FAIL->value 	   => "danger-solid",
				CommunicationStatusEnum::DELIVERED->value  => "success-soft",
				CommunicationStatusEnum::PROCESSING->value => "warning-soft"
			];
			$class  = Arr::get($badges , $status , 'primary-soft');
			$status = ucfirst(t2k(Arr::get(array_flip(CommunicationStatusEnum::toArray()), $status, 'PENDING')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}
	if (!function_exists('contact_meta')) {

		function contact_meta(mixed $status) :string {
			
			$badges  = [
				ContactAttributeEnum::DATE->value     => "warning-soft",
				ContactAttributeEnum::BOOLEAN->value  => "danger-soft",
				ContactAttributeEnum::NUMBER->value   => "info-soft",
				ContactAttributeEnum::TEXT->value 	   => "success-soft"
			];
			$class  = Arr::get($badges , $status , 'primary-soft');
			$status = ucfirst(t2k(Arr::get(array_flip(ContactAttributeEnum::toArray()), $status, 'Date')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}

	if (!function_exists('payment_status')) {

		function payment_status(mixed  $status) :string {

			$badges  = [
				PaymentStatusEnum::PENDING->value     => "warning-soft",
				PaymentStatusEnum::SUCCESS->value    => "success-soft",
				PaymentStatusEnum::FAILED->value   => "danger-solid",
				PaymentStatusEnum::PROCESSING->value 	   => "info-soft",
				PaymentStatusEnum::CANCEL->value 	   => "dark-soft"
			];
			$class  = Arr::get($badges , $status , 'primary-soft');
			$status = ucfirst(t2k(Arr::get(array_flip(PaymentStatusEnum::toArray()), $status, 'Cancel')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}
	if (!function_exists('priority_status')) {

		function priority_status(mixed  $status) :string {

			$badges  = [
				PriorityStatusEnum::LOW->value     => "warning-soft",
				PriorityStatusEnum::MEDIUM->value    => "success-soft",
				PriorityStatusEnum::HIGH->value   => "danger-solid"
			];
			$class  = Arr::get($badges , $status , 'warning-soft');
			$status = ucfirst(t2k(Arr::get(array_flip(PriorityStatusEnum::toArray()), $status, 'Low')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}

	if (!function_exists('service_type')) {

		function service_type(mixed $status) :string {

			$badges  = [
				ServiceType::SMS->value     => "info-soft",
				ServiceType::WHATSAPP->value    => "success-soft",
				ServiceType::EMAIL->value   => "danger-solid"
			];
			$class  = Arr::get($badges , $status , 'warning-soft');
			$status = ucfirst(t2k(Arr::get(array_flip(ServiceType::toArray()), $status, 'Sms')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}

	if (!function_exists('android_sim_status')) {

		function android_sim_status(mixed $status) :string {

			$badges  = [
				AndroidApiSimEnum::ACTIVE->value     => "success-soft",
				AndroidApiSimEnum::INACTIVE->value   => "danger-solid",
			];
			$class  = Arr::get($badges , $status , 'danger-solid');
			$status = ucfirst(t2k(Arr::get(array_flip(AndroidApiSimEnum::toArray()), $status, 'INACTIVE')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}
	if (!function_exists('subscription_status')) {

		function subscription_status(mixed $status) :string {
			
			$badges  = [
				SubscriptionStatus::RUNNING->value => "primary-soft",
				SubscriptionStatus::EXPIRED->value => "warning-soft",
				SubscriptionStatus::REQUESTED->value => "info-soft",
				SubscriptionStatus::INACTIVE->value => "danger-solid",
				SubscriptionStatus::RENEWED->value => "success-soft",
			];
			$class  = Arr::get($badges , $status , 'danger-solid');
			$status = ucfirst(t2k(Arr::get(array_flip(SubscriptionStatus::toArray()), $status, 'INACTIVE')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}
	if (!function_exists('support_ticket_status')) {

		function support_ticket_status(mixed $status) :string {

			$badges  = [
				TicketStatusEnum::RUNNING->value => "primary-soft",
				TicketStatusEnum::ANSWERED->value => "success-soft",
				TicketStatusEnum::REPLIED->value => "info-soft",
				TicketStatusEnum::CLOSED->value => "dark-soft",
			];
			$class  = Arr::get($badges , $status , 'danger-solid');
			$status = ucfirst(t2k(Arr::get(array_flip(TicketStatusEnum::toArray()), $status, 'INACTIVE')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}

	if (!function_exists('withdraw_log_status')) {

		function withdraw_log_status(mixed $status) :string {

			$badges  = [
				WithdrawLogEnum::PENDING->value => "primary-soft",
				WithdrawLogEnum::APPROVED->value => "success-soft",
				WithdrawLogEnum::REJECTED->value => "danger-soft",
			];
			$class  = Arr::get($badges , $status , 'primary-solid');
			$status = ucfirst(t2k(Arr::get(array_flip(WithdrawLogEnum::toArray()), $status, 'PENDING')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}

	if (!function_exists('android_session_status')) {

		function android_session_status(mixed $status) :string {

			$badges  = [
				SessionStatusEnum::INITIATED->value => "primary-soft",
				SessionStatusEnum::CONNECTED->value => "success-soft",
				SessionStatusEnum::DISCONNECTED->value => "danger-soft",
				SessionStatusEnum::EXPIRED->value => "warning-soft",
			];
			$class  = Arr::get($badges , $status , 'dark-solid');
			$status = ucfirst(t2k(Arr::get(array_flip(SessionStatusEnum::toArray()), $status, 'initiated')));
			return "<span class=\"i-badge dot pill $class\">$status</span>";
		}
   	}
	   function build_post_fields( $data,$existingKeys='',&$returnArray=[]){
	    if(($data instanceof CURLFile) or !(is_array($data) or is_object($data))){
	        $returnArray[$existingKeys]=$data;
	        return $returnArray;
	    }
	    else{
	        foreach ($data as $key => $item) {
	            build_post_fields($item,$existingKeys?$existingKeys."[$key]":$key,$returnArray);
	        }
	        return $returnArray;
	    }
	}

	// v4.9 — user-space `str_ends_with` polyfill removed. PHP 8.0+ ships
	// this natively (composer.json requires PHP ^8.1). The guarded polyfill
	// was harmless but reads as if the codebase supported < PHP 8.


	if (! function_exists('filterDuplicateContacts')) {
		function filterDuplicateContacts(array $contacts): array {
			
		    return array_unique($contacts, SORT_REGULAR);
		}
	}

	if (!function_exists('getEnvironmentMessage')) {

		/**
		 * Get an environment-specific message.
		 *
		 * @param string $devMessage The message to show in development (e.g., exception message)
		 * @param string $prodMessage The message to show in production (defaults to "Something went wrong")
		 * @return string The appropriate message based on the environment
		 */
		function getEnvironmentMessage(string $devMessage, ?string $prodMessage = null): string
		{
			// Priority: Admin setting > .env > Default
			$supportURL   	= site_settings('support_url')
							?? env('SUPPORT_URL')
							?? config('setting.support_url')
							?? SettingKey::SUPPORT_URL->value;

			$prodMessage  	= $prodMessage
							? $prodMessage
							: translate("Please contact support at: "). $supportURL;

		    	return App::environment('development')
					? $devMessage
					: $prodMessage;
		}
	}

	if (!function_exists('demo_mode_enabled')) {
		/**
		 * Check if demo mode is globally enabled.
		 */
		function demo_mode_enabled(): bool
		{
		    return config('demo.enabled', false);
		}
	 }
	 
	if (!function_exists('demo_feature_enabled')) {
		/**
		 * Check if a specific feature is in demo mode.
		 */
		function demo_feature_enabled(string $feature): bool
		{
			return demo_mode_enabled() && config("demo.features.{$feature}.enabled", false);
		}
	}
	 
	if (!function_exists('demo_restriction_active')) {
		/**
		 * Check if a specific restriction is active for a feature.
		 */
		function demo_restriction_active(string $feature, string $restriction): bool
		{
			return demo_feature_enabled($feature) && 
					(config("demo.features.{$feature}.restrictions.{$restriction}", false) === true);
		}
	}
	 
	if (!function_exists('demo_get_message')) {
		/**
		 * Retrieve the appropriate demo mode message based on feature and restriction.
		 */
		function demo_get_message(string $feature, ?string $restriction = null): string
		{
			// Check restriction-specific message first
			if ($restriction && 
				($message = config("demo.features.{$feature}.messages.restrictions.{$restriction}"))) 
					return $message;
			
		
			// Fallback to feature-specific default message
			if ($message = config("demo.features.{$feature}.messages.default")) 
				return $message;
			
		
			// Fallback to global message
			return config('demo.messages.global', 'Demo mode is active.');
		}
	}

	if(!function_exists('returnBackWithResponse')) {
		
		/**
		 * returnBackWithResponse
		 *
		 * @param string $status
		 * @param mixed string|array
		 * 
		 * @return RedirectResponse
		 */
		function returnBackWithResponse(string $status = "error", string|array $message = "Invalid Data") : RedirectResponse {

			$notify[] = [$status, translate($message)];
			
			return back()->withNotify($notify);
		}
	}

	if(!function_exists('returnRedirectWithResponse')) {

		/**
		 * returnRedirectWithResponse
		 *
		 * @param string $route
		 * @param string $status
		 * @param mixed string
		 * 
		 * @return RedirectResponse
		 */
		function returnRedirectWithResponse(string $route, string $status = "error", string $message = "Invalid Data") : RedirectResponse {

			$notify[] = [$status, translate($message)];
			return redirect($route)->withNotify($notify);
		}
	}

	if (!function_exists('getAuthUser')) {

		/**
		 * Summary of getAuthUser
		 * @param string $guard
		 * @param array $load
		 * @return mixed
		 */
		function getAuthUser(string $guard = 'admin', array $load = []): mixed
		{
			return auth()?->guard($guard)
						?->user()
						?->load($load);
		}
	  
	}

	if (!function_exists('generate_unique_token')) {

		/**
		 * generate_unique_token
		 *
		 * @return string
		 */
		function generate_unique_token(): string
		{
		    $timestamp = round(microtime(true) * 1000); 
		    $randomString = bin2hex(random_bytes(8)); 
		    return $timestamp . '-' . $randomString;
		}
	}

	if(!function_exists("replaceContactVariables")) {

		/**
		 * replaceContactVariables
		 *
		 * @param Contact|null $contact
		 * @param string|null|null $messageBody
		 * 
		 * @return string|null
		 */
		function replaceContactVariables(?Contact $contact = null, string|null $messageBody = null): string|null {
			
			if(!$messageBody || !$contact) return $messageBody;
			if (!Str::contains($messageBody, ['{{', '}}'])) return $messageBody;
			$columns 	= array_keys($contact->getAttributes());
			$metaData = $contact->meta_data;
			preg_match_all('/{{(.*?)}}/', $messageBody, $matches);
			$variables = Arr::get($matches, 1);
			if(!$variables) return $messageBody;

			$replacements = collect($variables)
							->mapWithKeys(function ($variable) use ($contact, $columns, $metaData) {
								$trimmedVariable = trim($variable); 
								$placeholder = "{{{$trimmedVariable}}}";
								if (in_array($trimmedVariable, $columns)) {
									return [$placeholder => $contact->$trimmedVariable ?? ''];
								}
								if ($metaData && property_exists($metaData, $trimmedVariable)) {
									$metaValue = $metaData->$trimmedVariable;
									$value = is_object($metaValue) && property_exists($metaValue, 'value')
												? $metaValue->value
												: $metaValue;
									return [$placeholder => $value ?? ''];
								}
								return [$placeholder => $placeholder];
							})->toArray();
							
			return str_replace(
				array_keys($replacements),
				array_values($replacements),
				$messageBody
				);
		}
	}

	if (!function_exists('update_env')) {
		function update_env(string $key, string $newValue): void
		{
		    $path = base_path('.env');
		    $envContent = file_get_contents($path);
	 
		    if (preg_match('/^' . preg_quote($key, '/') . '=/m', $envContent)) {
			   $envContent = preg_replace('/^' . preg_quote($key, '/') . '.*/m', $key . '=' . $newValue, $envContent);
		    } else {
			   $envContent .= PHP_EOL . $key . '=' . $newValue . PHP_EOL;
		    }
		    file_put_contents($path, $envContent);
	 
		}
	 }

	 if (!function_exists('is_domain_verified')) {
		/**
		 * Check if domain is verified - uses cached site_settings()
		 *
		 * Returns true if:
		 *  - Domain is explicitly verified (is_domain_verified == TRUE)
		 *  - site_settings() returned null (DB unavailable after cache clear)
		 *    → Assume verified to avoid blocking legitimate users
		 *
		 * Returns false only if explicitly set to FALSE in the database
		 *
		 * @return bool
		 */
		function is_domain_verified(): bool
		{
		    $domainStatus = site_settings('is_domain_verified');

		    // If null (DB unavailable or cache miss with no DB),
		    // default to true — don't block legitimate users due to temporary issues
		    if ($domainStatus === null) {
		        return true;
		    }

		    return $domainStatus == StatusEnum::TRUE->status();
		}
	 }

	 if (!function_exists('get_date_time')) {
		/**
		 * Summary of get_date_time
		 * @param string $date
		 * @param mixed $format
		 * @return string
		 */
		function get_date_time(string $date, ?string $format = null): string
		{
		    $format = $format ?? site_settings("date_format", 'd M, Y') . " " . site_settings("time_format", 'h:i A');
		    return Carbon::parse($date)->translatedFormat($format);
		}
	 }

	 if (!function_exists('response_status')) {
		function response_status(string $message = 'Sucessfully Completed', string $key = 'success'): array
		{
		    	return [ $key, translate($message) ];
		}
	 }

	 if (!function_exists('check_open_ai_text_availability')) {
		function check_open_ai_text_availability(string $message = 'Sucessfully Completed', string $key = 'success'): bool
		{
		    	return site_settings(SettingKey::AI_FUNCTIONS->value, StatusEnum::FALSE->status()) == StatusEnum::TRUE->status()
            	&& Arr::get(json_decode(site_settings(SettingKey::AI_MODELS->value), true), "open_ai_text.status", StatusEnum::FALSE->status()) == StatusEnum::TRUE->status();
		}
	 }

	if (!function_exists('check_open_ai_image_availability')) {
		function check_open_ai_image_availability(string $message = 'Sucessfully Completed', string $key = 'success'): bool
		{
		    	return site_settings(SettingKey::AI_FUNCTIONS->value, StatusEnum::FALSE->status()) == StatusEnum::TRUE->status()
            	&& Arr::get(json_decode(site_settings(SettingKey::AI_MODELS->value), true), "open_ai_image.status", StatusEnum::FALSE->status()) == StatusEnum::TRUE->status();
		}
	}

	if (!function_exists('openai_Image_key')) {
		function openai_Image_key(): string
		{
 			$aiModels  = json_decode(site_settings('ai_models'), true);
			$key = Arr::get($aiModels, "open_ai_image.key", "###");

			return $key;
		}
	}
	 if (!function_exists('get_ai_option')){
		function get_ai_option() :array{


			return [

				'improve_it' => [
					'prompt' => "Improve the above message writing"
				],
				'Grammer Correction' => [
					'prompt' => "Correct any grammatical mistake in the message"
				],
				'make_it_more_detailed' => [

					'prompt' => "Make this message More Detailed"
				],
				'simplyfy_it' => [
					'prompt' => "Simplyfy this message"
				],
				'make_it_informative' => [
					'prompt' => "Make the message more informative"
				],
				'fix_any_mistake' => [
					'prompt' => "Fix if there is any mistake in the message"
				],
				'sound_fluent' => [
					'prompt' => "Make this message as it sound more fluent"
				],
				'make_it_objective' => [
					'prompt' => "Make  this message more objective"
				],
			];

		}

    }

	if (!function_exists('get_ai_tone')){
		function get_ai_tone() :array{


			return [

				'engaging' => [
					'display_name' => "Make It Engaging",
					'prompt'       => "Make the message content tone more engaging",

				],
				'sound_formal' => [
					'display_name' => "Sound Formal",
					'prompt'       => "Make the message content tone more formal",
				],
				'sound_casual' => [
					'display_name' => "Sound Casual",
					'prompt'       => "Make the message  content tone  sound more casual",
				],
				'friendly' => [
					'display_name' => "Make It Friendly",
					'prompt'       => "Make the message content tone more user friendly",
				],

				'exciting' => [
					'display_name' => "Make It Exciting",
					'prompt'       => "Make the message content tone more exciting",
				],

				'confident' => [
					'display_name' => "Make It Confident",
					'prompt'       => "Make the message content tone more Confident",
				],

				'assertive' => [
					'display_name' => "Make It Assertive",
					'prompt'       => "Make the message content tone more assertive",
				]

			];

		}

    }
    if (!function_exists('k2t')){
       function k2t(string $text) :string{
           return ucfirst(preg_replace("/[^A-Za-z0-9 ]/", ' ', $text));
        }
    }

    // v4.9 — duplicate t2k definition removed. Canonical version is at
    // line ~1724 above (same signature, same body). Both were
    // `function_exists`-guarded so no crash, but keeping two copies is a
    // maintenance hazard — any future signature change would need to be
    // repeated in both places or the guarded second copy silently drifts.
    if (!function_exists('limit_words')) {
        function limit_words($text, $limit = 25) {


            return \Illuminate\Support\Str::limit($text, $limit, $end='...');
        }
    }

   	## Theme Management

    	if (!function_exists('getAvailableFrontendThemes')) { 

		/**
		 * Summary of getAvailableFrontendThemes
		 * @return array
		 */
		function getAvailableFrontendThemes(): array
		{
			return collect(config('frontend_themes.available_themes', []))
				->filter(fn($theme) => Arr::get($theme, 'status') === 'active')
				->toArray();
		}
	}

	if (!function_exists('getActiveFrontendTheme')) { 

		/**
		 * Summary of getActiveFrontendTheme
		 * @return string
		 */
		function getActiveFrontendTheme(): string
		{
			return site_settings(SettingKey::FRONTEND_ACTIVE_THEME->value, config('frontend_themes.default_theme', 'default'));
		}
	}
	
	if(!function_exists("getFrontendThemeData")) {

		/**
		 * Summary of getFrontendThemeData
		 * @param string $slug
		 */
		function getFrontendThemeData(string $slug): ?array
		{
			return Arr::get(config('frontend_themes.available_themes'), $slug);
		}
	}

	if(!function_exists("isActiveFrontendTheme")) { 

		/**
		 * Summary of isActiveFrontendTheme
		 * @param string $slug
		 * @return bool
		 */
		function isActiveFrontendTheme(string $slug): bool
		{
			return getActiveFrontendTheme() === $slug;
		}
	}

	if(!function_exists("getFrontendThemeScreenshot")) { 

		/**
		 * Summary of getFrontendThemeScreenshot
		 * @param string $slug
		 * @param string|null $screenshot
		 * @return string
		 */
		function getFrontendThemeScreenshot(string $slug, string|null $screenshot = null): string
		{
			$themeData = getFrontendThemeData($slug);
			
			if (!$themeData) return asset('assets/file/default.jpg');
			
			$screenshot = $screenshot ?: Arr::first(Arr::get($themeData, 'screenshots', ['homepage.png']));
			$imagePath = Arr::get(config('setting.file_path.frontend_theme'), 'path') . $slug . '/' . $screenshot;
			
			return showImage($imagePath, '800x400');
		}
	}

	if(!function_exists("getFrontendThemeThumbnail")) { 

		/**
		 * Summary of getFrontendThemeThumbnail
		 * @param string $slug
		 * @param string|null $screenshot
		 * @return string
		 */
		function getFrontendThemeThumbnail(string $slug, string|null $screenshot = null): string
		{
			$themeData = getFrontendThemeData($slug);
			if (!$themeData) return asset('assets/file/default.jpg');
			
			$screenshot = $screenshot ?: Arr::first(Arr::get($themeData, 'screenshots', ['homepage.png']));
			$imagePath = Arr::get(config('setting.file_path.frontend_theme'), 'path') . $slug . '/' . $screenshot;
			
			return showImage($imagePath, '400x200');
		}
	}

	if(!function_exists("getFrontendThemeScreenshots")) { 

		/**
		 * Summary of getFrontendThemeScreenshots
		 * @param string $slug
		 * @return array
		 */
		function getFrontendThemeScreenshots(string $slug): array
		{
			$themeData = getFrontendThemeData($slug);
			if (!$themeData) return [];
			
			$basePath = Arr::get(config('setting.file_path.frontend_theme'), 'path') . $slug . '/';
			
			return collect(Arr::get($themeData, 'screenshots', []))
				->map(fn($screenshot) => [
					'filename' => $screenshot,
					'preview' => showImage($basePath . $screenshot, '800x400'),
					'thumbnail' => showImage($basePath . $screenshot, '400x200')
				])
				->toArray();
		}
	}

	if(!function_exists("prepareFrontendThemesData")) { 

		/**
		 * Summary of prepareFrontendThemesData
		 * @return array
		 */
		function prepareFrontendThemesData(): array
		{
			return collect(getAvailableFrontendThemes())
				->mapWithKeys(fn($theme, $slug) => [
					$slug => array_merge($theme, [
						'screenshot_urls' => getFrontendThemeScreenshots($slug)
					])
				])
				->toArray();
		}
	}


	if (! function_exists('isUserAuthRoute')) {
		function isUserAuthRoute(): bool
		{
			$authGetRoutes = [
				'register',
				'login',
				'password.request',
				'password.reset',
				'password.verify.code',
				'user.authorization.process',
				'user.email.verification',
			];

			$currentRoute = Route::currentRouteName();

			return in_array($currentRoute, $authGetRoutes, true);
		}
	}

	if (!function_exists('applyStyleToTitle')) {

		/**
		 * Summary of applyStyleToTitle
		 * @param mixed $input
		 * @param bool $random
		 */
		function applyStyleToTitle(mixed $input, bool $random = false) {
			if (!is_string($input) || is_null($input)) {
				return $input;
			}

			$words = explode(' ', $input);
			if (count($words) < 4) {
				return $input;
			}

			$index = $random ? array_rand($words) : 3;
			$words[$index] = '<span class="text-primary">' . $words[$index] . '</span>';
			return implode(' ', $words);
		}
	}

	if (! function_exists('limit_html_descriptions')) {
		/**
		 * Limit words in a string.
		 *
		 * @param  string  $text
		 * @param  int     $limit
		 * @param  bool    $ellipsis
		 * @return string
		 */
		function limit_html_descriptions(string $text, int $limit = 18, bool $ellipsis = false): string
		{
			$clean = trim(strip_tags($text));
			
			$words = preg_split('/\s+/', $clean);

			if (count($words) <= $limit) {
				return implode(' ', $words);
			}

			$truncated = array_slice($words, 0, $limit);
			$result = implode(' ', $truncated);

			return $ellipsis ? $result . '...' : $result;
		}
	}
	if (!function_exists('getLanguageRtlStates')) {
		
		function getLanguageRtlStates() {
			return Language::active()->pluck('ltr', 'code')->map(function ($is_ltr) {
				return $is_ltr == StatusEnum::FALSE->status();
			})->toJson();
		}
	}

	if (!function_exists('plain_text_preview')) {
		/**
		 * v5.0 — canonical "plain-text summary" for any HTML/subject/preview cell.
		 *
		 * Fixes the very common bug where `Str::limit($html, N)` leaked raw
		 * <style> block contents like `*{box-sizing:border-box}body{margin:0}`
		 * into customer-visible previews because strip_tags() removes tags but
		 * KEEPS the CSS/JS body inside them.
		 *
		 * Order matters:
		 *   1. Strip <style>…</style> and <script>…</script> entirely (content included)
		 *   2. strip_tags on what remains
		 *   3. html_entity_decode so &nbsp;/&amp; render as human characters
		 *   4. Unicode-aware whitespace collapse (matches non-breaking space U+00A0)
		 *   5. Str::limit
		 */
		function plain_text_preview($html, int $limit = 100, string $end = '...'): string {
			$s = (string) ($html ?? '');
			if ($s === '') return '';

			// Kill entire <style>/<script> blocks — content included.
			$s = preg_replace('#<(style|script)\b[^>]*>.*?</\1\s*>#is', ' ', $s) ?? $s;
			// Kill HTML comments (Outlook conditional comments carry CSS).
			$s = preg_replace('/<!--.*?-->/s', ' ', $s) ?? $s;

			$s = strip_tags($s);
			$s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$s = preg_replace('/[\s\x{A0}]+/u', ' ', $s) ?? $s;
			$s = trim($s);

			return \Illuminate\Support\Str::limit($s, $limit, $end);
		}
	}

	if (!function_exists('sms_detect_encoding')) {
		/**
		 * Detect whether an SMS body fits into GSM-7 or requires UCS-2 (Unicode).
		 *
		 * Returns 'GSM' if every character in the body is representable in the
		 * GSM 03.38 basic + extension alphabet. Returns 'UCS2' otherwise
		 * (any Unicode char, emoji, most non-Latin scripts).
		 *
		 * All major SMS carriers (Twilio, Vonage, MessageBird, Infobip, etc.)
		 * bill by segment count, and the segment size depends entirely on
		 * this encoding decision, so this must match the carrier's own
		 * detection to avoid over/under-billing.
		 */
		function sms_detect_encoding(string $body): string
		{
			// GSM 03.38 basic + extension alphabet (all characters that fit in GSM-7).
			// Extension-set chars ({, }, [, ], \, ~, ^, |, €, form-feed) cost 2 GSM-7 units
			// but are still representable — accounted for in the segment counter, not here.
			static $gsm = [
				'@','£','$','¥','è','é','ù','ì','ò','Ç',"\n",'Ø','ø',"\r",'Å','å',
				'Δ','_','Φ','Γ','Λ','Ω','Π','Ψ','Σ','Θ','Ξ',"\x1B",'Æ','æ','ß','É',
				' ','!','"','#','¤','%','&',"'",'(',')','*','+',',','-','.','/',
				'0','1','2','3','4','5','6','7','8','9',':',';','<','=','>','?',
				'¡','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O',
				'P','Q','R','S','T','U','V','W','X','Y','Z','Ä','Ö','Ñ','Ü','§',
				'¿','a','b','c','d','e','f','g','h','i','j','k','l','m','n','o',
				'p','q','r','s','t','u','v','w','x','y','z','ä','ö','ñ','ü','à',
				// Extension set (cost 2 chars in GSM-7 counter but still GSM-encodable)
				"\f",'^','{','}','\\','[','~',']','|','€',
			];
			static $gsmSet = null;
			if ($gsmSet === null) {
				$gsmSet = array_flip($gsm);
			}

			$chars = mb_str_split($body, 1, 'UTF-8');
			foreach ($chars as $ch) {
				if (!isset($gsmSet[$ch])) {
					return 'UCS2';
				}
			}
			return 'GSM';
		}
	}

	if (!function_exists('sms_segment_count')) {
		/**
		 * Number of SMS segments this body will be split into by the carrier.
		 *
		 * GSM-7 encoding:
		 *   - Single segment: up to 160 GSM-7 units
		 *   - Multi-part:     153 GSM-7 units per segment (7 units reserved for UDH concatenation header)
		 *   - Extension-set chars ({}[]\~^|€, form-feed) cost 2 GSM-7 units each
		 *
		 * UCS-2 encoding (any Unicode char / emoji):
		 *   - Single segment: up to 70 UCS-2 chars
		 *   - Multi-part:     67 UCS-2 chars per segment (3 chars reserved for UDH)
		 *
		 * Empty body still counts as 1 segment (an empty SMS is still a billable send).
		 *
		 * IGNORES site_settings('sms_word_count') — that setting historically
		 * mis-defaults to 320, which does not match any real carrier's billing.
		 * All modern SMS billing follows the GSM 03.38 standard, so we enforce it
		 * regardless of that setting to protect customers from being under-billed.
		 */
		function sms_segment_count(string $body): int
		{
			if ($body === '') return 1;

			$encoding = sms_detect_encoding($body);

			if ($encoding === 'GSM') {
				// Count GSM-7 units — extension chars count as 2.
				static $extension = ['^' => 1, '{' => 1, '}' => 1, '\\' => 1, '[' => 1, '~' => 1, ']' => 1, '|' => 1, '€' => 1, "\f" => 1];
				$chars = mb_str_split($body, 1, 'UTF-8');
				$units = 0;
				foreach ($chars as $ch) {
					$units += isset($extension[$ch]) ? 2 : 1;
				}
				if ($units <= 160) return 1;
				return (int) ceil($units / 153);
			}

			// UCS-2: each code-point is one 16-bit UCS-2 unit; surrogate-pair emoji
			// (e.g. 😀) actually consumes 2 UCS-2 units. mb_strlen with UTF-8 counts
			// codepoints — most billing engines count *UCS-2 units*, so we upgrade
			// astral-plane chars to their surrogate-pair length.
			$len = 0;
			$chars = mb_str_split($body, 1, 'UTF-8');
			foreach ($chars as $ch) {
				$cp = mb_ord($ch, 'UTF-8');
				$len += ($cp !== false && $cp > 0xFFFF) ? 2 : 1;
			}
			if ($len <= 70) return 1;
			return (int) ceil($len / 67);
		}
	}

	if (!function_exists('sms_credit_units')) {
		/**
		 * Credits consumed by sending $body to $contactCount recipients on
		 * an SMS-billed channel (SMS + WhatsApp text). Every recipient pays
		 * for every segment. Non-SMS channels always cost 1 per contact.
		 *
		 * Usage:
		 *   $units = sms_credit_units($messageBody, $contactCount, ServiceType::SMS->value);
		 */
		function sms_credit_units(string $body, int $contactCount, string $serviceType): int
		{
			$contactCount = max(1, $contactCount);
			$type = strtolower($serviceType);
			if ($type === 'sms' || $type === 'whatsapp') {
				return sms_segment_count($body) * $contactCount;
			}
			return $contactCount;
		}
	}

