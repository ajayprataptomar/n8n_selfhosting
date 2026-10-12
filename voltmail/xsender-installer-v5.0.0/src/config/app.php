<?php

use Illuminate\Support\Facades\Facade;

$timelog = "Asia/Dhaka";

// Load timezone from timesetup.php (written by admin settings)
// Falls back to APP_TIMEZONE env, then database setting, then default above
$timesetupFile = __DIR__ . '/timesetup.php';
if (file_exists($timesetupFile)) {
    try {
        require_once($timesetupFile);
    } catch (\Throwable $e) {
        // Corrupted file — ignore, use fallback
    }
}

// Allow .env override (useful when config file is not writable)
if (env('APP_TIMEZONE')) {
    $timelog = env('APP_TIMEZONE');
}

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),
    

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),
    'app_version' => env('APP_VERSION', '2.0.2'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => $timelog,

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'us',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        App\Providers\QueueConfigServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        // App\Providers\MailConfigServiceProvider::class,
        App\Providers\SocialLoginServiceProvider::class,
        App\Providers\PaymentServiceProvider::class,
        Laravel\Socialite\SocialiteServiceProvider::class,
        Maatwebsite\Excel\ExcelServiceProvider::class,
        Laravel\Passport\PassportServiceProvider::class,
        App\Providers\ThemeServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */
    'aliases' => [

        'Js'            => Illuminate\Support\Js::class,
        'Str'           => Illuminate\Support\Str::class,
        'Arr'           => Illuminate\Support\Arr::class,
        'DB'            => Illuminate\Support\Facades\DB::class,
        'URL'           => Illuminate\Support\Facades\URL::class,
        'Log'           => Illuminate\Support\Facades\Log::class,
        'App'           => Illuminate\Support\Facades\App::class,
        'Bus'           => Illuminate\Support\Facades\Bus::class,
        'Auth'          => Illuminate\Support\Facades\Auth::class,
        'Date'          => Illuminate\Support\Facades\Date::class,
        'File'          => Illuminate\Support\Facades\File::class,
        'Gate'          => Illuminate\Support\Facades\Gate::class,
        'Http'          => Illuminate\Support\Facades\Http::class,
        'Hash'          => Illuminate\Support\Facades\Hash::class,
        'View'          => Illuminate\Support\Facades\View::class,
        'Mail'          => Illuminate\Support\Facades\Mail::class,
        'Lang'          => Illuminate\Support\Facades\Lang::class,
        'Excel'         => Maatwebsite\Excel\Facades\Excel::class,
        'Redis'         => Illuminate\Support\Facades\Redis::class,
        'Cache'         => Illuminate\Support\Facades\Cache::class,
        'Blade'         => Illuminate\Support\Facades\Blade::class,
        'Event'         => Illuminate\Support\Facades\Event::class,
        'Queue'         => Illuminate\Support\Facades\Queue::class,
        'Crypt'         => Illuminate\Support\Facades\Crypt::class,
        'Route'         => Illuminate\Support\Facades\Route::class,
        'Config'        => Illuminate\Support\Facades\Config::class,
        'Cookie'        => Illuminate\Support\Facades\Cookie::class,
        'Schema'        => Illuminate\Support\Facades\Schema::class,
        'Request'       => Illuminate\Support\Facades\Request::class,
        'Eloquent'      => Illuminate\Database\Eloquent\Model::class,
        'Session'       => Illuminate\Support\Facades\Session::class,
        'Artisan'       => Illuminate\Support\Facades\Artisan::class,
        // Debugbar facade is registered conditionally in AppServiceProvider::boot() so
        // production installs (where the dev package is stripped from autoload via
        // `composer dump-autoload --no-dev`) don't crash with "Class not found".
        'Storage'       => Illuminate\Support\Facades\Storage::class,
        'Response'      => Illuminate\Support\Facades\Response::class,
        'Password'      => Illuminate\Support\Facades\Password::class,
        'Redirect'      => Illuminate\Support\Facades\Redirect::class,
        'Broadcast'     => Illuminate\Support\Facades\Broadcast::class,
        'Socialite'     => Laravel\Socialite\Facades\Socialite::class,
        'Validator'     => Illuminate\Support\Facades\Validator::class,
        'RateLimiter'   => Illuminate\Support\Facades\RateLimiter::class,
        'Notification'  => Illuminate\Support\Facades\Notification::class,
    ],
];
