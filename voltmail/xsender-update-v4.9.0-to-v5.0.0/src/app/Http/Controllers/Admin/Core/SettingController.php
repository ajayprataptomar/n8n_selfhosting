<?php

namespace App\Http\Controllers\Admin\Core;

use App\Enums\SettingKey;
use App\Enums\StatusEnum;
use App\Managers\ThemeManager;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Support\Facades\Session;
use App\Service\Admin\Core\SettingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

class SettingController extends Controller
{
    public SettingService $settingService;
    public function __construct(SettingService $settingService) { 

        $this->settingService = $settingService;
    }

    /**
     * @param $type
     * 
     * @return \Illuminate\View\View
     * 
     * @throws Exception $error
     * 
     */
    public function index($type = "general") {
    
        try {
            
            Session::put("menu_active", true);
            $data = $this->settingService->getIndex($type);
            
            return view("admin.setting.$type", $data);
            
        } catch (\Exception $error) {
            
            $notify[] = ["error", translate("System Error: "). $error->getMessage()];
            return redirect()->route("admin.dashboard")->withNotify($notify);
        }
    }

    public function store(Request $request) {
        
        $reload  = false;
        $status  = true;
        $message = translate('Updated settings successfully');
        $extra   = null;
        if($request->has('site_settings')) {

            try {
                $path = base_path('.env');
                $env_content = file_get_contents($path);
                $validations = $this->settingService->validationRules($request->site_settings);
                $request->validate($validations['rules'],$validations['message']);
                
                if(isset($request->site_settings['time_zone'])) {
                    $timezone = $request->site_settings['time_zone'];
                    $saved = false;

                    // Method 1: Try writing to config/timesetup.php (original approach)
                    try {
                        $timeLocationFile = config_path('timesetup.php');
                        $time = "<?php \$timelog = '".$timezone."' ?>";
                        @file_put_contents($timeLocationFile, $time);
                        if (file_exists($timeLocationFile) && str_contains(file_get_contents($timeLocationFile), $timezone)) {
                            $saved = true;
                        }
                    } catch (\Exception $e) {
                        // Config directory not writable — use fallback
                    }

                    // Method 2: Fallback — write APP_TIMEZONE to .env
                    if (!$saved) {
                        try {
                            $envPath = base_path('.env');
                            $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
                            if (str_contains($envContent, 'APP_TIMEZONE=')) {
                                $envContent = preg_replace('/APP_TIMEZONE=.*/', 'APP_TIMEZONE=' . $timezone, $envContent);
                            } else {
                                $envContent .= PHP_EOL . 'APP_TIMEZONE=' . $timezone;
                            }
                            file_put_contents($envPath, $envContent);
                        } catch (\Exception $e) {
                            // .env also not writable — timezone will still be saved in DB settings below
                        }
                    }

                    // Always apply timezone immediately for current request
                    config(['app.timezone' => $timezone]);
                    date_default_timezone_set($timezone);
                    $reload = true;
                }
                if (isset($request->site_settings['debug_mode'])) {

                    $debugMode = $request->site_settings['debug_mode'] == StatusEnum::TRUE->status();
                    $envFilePath = $path; 
                    $envContent = file_exists($envFilePath) ? file_get_contents($envFilePath) : '';
                    $newEnv = $debugMode ? 'local' : 'production';
                    $newDebug = $debugMode ? 'true' : 'false';
                    $updatedEnvContent = str_replace(
                        ['APP_DEBUG=true', 'APP_DEBUG=false'],
                        ["APP_DEBUG={$newDebug}", "APP_DEBUG={$newDebug}"],
                        $envContent
                    );
                    file_put_contents($envFilePath, $updatedEnvContent);
                    $reload = true;
                }

                if (isset($request->site_settings['theme_dir']) && $request->site_settings['theme_dir'] != site_settings('theme_dir')) { 

                    $current_language = Language::where('code', App::getLocale())->first();
                    if($current_language && $current_language->ltr == StatusEnum::FALSE->status()) {

                        $siteSettings = $request->input('site_settings');
                        $siteSettings['theme_dir'] = StatusEnum::FALSE->status();
                        $request->merge(['site_settings' => $siteSettings]);
                        $extra = translate("Current language doesnt have ltr/rtl compatibility");   
                    } 
                    $reload = true;
                }
                if (isset($request->site_settings['theme_mode']) && $request->site_settings['theme_mode'] != site_settings('theme_mode')) { 
                    
                    $reload = true;
                }
                if (isset($request->site_settings[SettingKey::QUEUE_CONNECTION_CONFIG->value])) { 
                    
                    $reload = true;
                }
                if (isset($request->site_settings[SettingKey::FRONTEND_ACTIVE_THEME->value])) { 
                    
                    $themeManager = new ThemeManager();
                    $themeManager->clearCache();
                    Artisan::call('optimize:clear');
                }
                if(isset($request->site_settings[SettingKey::ANDROID_OFF_CANVAS_GUIDE->value]) 
                    || isset($request->site_settings[SettingKey::WHATSAPP_OFF_CANVAS_GUIDE->value])) {
                        $reload = true;
                }
                $this->settingService->updateSettings($request->site_settings, $request->input("channel", "site_settings"));
                $message = translate('Settings Updated Successfully');

            } catch (\Exception $exception) {
                $status = false;
                $message = $exception->getMessage();
            }
            
        } else {

            $status  = false;
            $message = translate("Nothing to update");
        }

        if ($request->expectsJson()) {
            return response()->json([
                'reload'  => $reload,
                'status'  => $status,
                'message' => $message . ($extra ? ', ' . $extra : '')
            ]);
        }
        
        $status = $status ? 'success' : 'error';
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }
}
