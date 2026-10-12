<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Enums\StatusEnum;
use App\Models\Admin;
use App\Services\System\DatabaseCompatibilityChecker;
use App\Traits\InstallerManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class InstallerController extends Controller
{

    use InstallerManager;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // Allow reinstallation if:
            // 1. ?reinstall=true AND APP_DEBUG=true (development only)
            // 2. Or if the reinstall session flag is set
            $allowReinstall = ($request->query('reinstall') === 'true' && config('app.debug') === true)
                || session('allow_reinstall') === true;

            if ($allowReinstall && $request->query('reinstall') === 'true') {
                // Set session flag to persist through installation flow
                session(['allow_reinstall' => true]);
                // Clear installation cache
                \Illuminate\Support\Facades\Cache::forget('app_installation_status');
            }

            if ($this->is_installed()
                && !$allowReinstall
                && !$request->routeIs('install.setup.finished')
                && !$request->routeIs('invalid.purchase')
                && !$request->routeIs('verify.puchase')) {
                return redirect()->route('home')->with('success', trans('default.already_installed'));
            }

            return $next($request);
        });
    }


    /**
     * Installer init
     *
     * @return View
     */
    public function init() :View
    {
        $this->_registerDomain();

        return view('install.init',[
            'title' => 'Install'
        ]);
    }


    /**
     * Requirements and permission verifications
     * @return View |RedirectResponse
     */
    public function requirementVerification() : View |RedirectResponse
    {
        if (Hash::check(base64_decode('cmVxdWlyZW1lbnRz'), request()->input('verify_token'))) {
            return view('install.requirements',[
                'title' => 'File Permissions & Requirments',
                'requirements' => $this->checkRequirements(
                    config('installer.requirements')
                ),
                "phpSupportInfo" =>  $this->checkPHPversion(config('installer.core.minPhpVersion')),
                'permissions'    => $this->permissionsCheck(
                                        config('installer.permissions')
                                    )

            ]);
        }

        $notify[] = ["error", "Invalid verification token"];
        return redirect()->route('install.init')->withNotify($notify);
    }


    /**
     * @return View|RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function envatoVerification() :View |RedirectResponse
    {

        if(session()->get('system_requirments')){
            if (Hash::check(base64_decode('ZW52YXRvX3ZlcmlmaWNhdGlvbg=='), request()->input('verify_token'))) 
                return view('install.envato_verification',[
                    'title' => 'Envato Verification'
                ]);
            $notify[] = ["error", "Invalid verification token"];
            return redirect()->route('install.init')->withNotify($notify);
        }
        $notify[] = ["error", "Server requirements not met. Ensure all essential Extension and file permissions are enabled to ensure proper functionality"];
        return redirect()->back()->withNotify($notify);

    }


    /**
     * @param Request $request
     * @return View|RedirectResponse
     */
    public function purchaseVerification(Request $request): View|RedirectResponse
    {
        $request->validate([
            base64_decode('cHVyY2hhc2VfY29kZQ==') => "required",
            base64_decode('dXNlcm5hbWU=')         => "required"
        ],[
            base64_decode('cHVyY2hhc2VfY29kZQ==').".required" => "Code is required",
            base64_decode('dXNlcm5hbWU=').".required"         => "Username is required",
        ]);
        if ($this->_envatoVerification($request)) {
            session()->put(base64_decode('cHVyY2hhc2VfY29kZQ=='), $request->input(base64_decode('cHVyY2hhc2VfY29kZQ==')));
            session()->put(base64_decode('dXNlcm5hbWU='), $request->input(base64_decode('dXNlcm5hbWU=')));
            return redirect()->route('install.db.setup', ['verify_token' => bcrypt(base64_decode('ZGJzZXR1cF8='))]);
        }

        $notify[] = ["error", "License verification failed. Please check your purchase code and Envato username. Ensure they match your CodeCanyon purchase."];
        return redirect()->back()->withNotify($notify);
    }


    /**
     * @return View|RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function dbSetup() :View |RedirectResponse
    {
        if(session()->get('system_requirments')){
            if (Hash::check(base64_decode('ZGJzZXR1cF8='), request()->input('verify_token'))) {
                return view('install.db_setup',[
                    'title' => 'Database Setup'
                ]);
            }
            $notify[] = ["error", "Invalid verification code"];
            return redirect()->route('install.init')->withNotify($notify);
        }

        $notify[] = ["error", "Server requirements not met. Ensure all essential Extension and file permissions are enabled to ensure proper functionality"];
        return redirect()->back()->withNotify($notify);
    }

    /**
     * v4.9 — MySQL compatibility pre-flight (AJAX). Called from dbSetup form
     * BEFORE dbStore. Runs against a probe PDO connection using the supplied
     * credentials and returns the JSON shape rendered by the reusable
     * partials/campaign/health_banner.blade.php JS renderer.
     *
     * Does NOT write .env. Does NOT modify state. Pure read-only probe.
     */
    public function dbPreflight(Request $request): JsonResponse
    {
        $request->validate([
            'db_host'     => 'required|string|max:255',
            'db_port'     => 'required|numeric',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
        ]);

        $checker = new DatabaseCompatibilityChecker();
        $result  = $checker->check(
            host:     trim((string) $request->input('db_host')),
            port:     (int) $request->input('db_port'),
            username: trim((string) $request->input('db_username')),
            password: (string) $request->input('db_password', ''),
            database: trim((string) $request->input('db_database')),
        );

        return response()->json($result);
    }

    /**
     * Database setup
     *
     * @param Request $request
     * @return View |RedirectResponse
     */
    public function dbStore(Request $request): View|RedirectResponse
    {
        $request->validate([
            'db_host'     => "required",
            'db_port'     => "required|numeric",
            'db_database' => "required",
            'db_username' => "required",
        ]);

        // Test database connection first
        if (!$this->_chekcDbConnection($request)) {
            $errorMsg = "Database connection failed. Common issues:\n";
            $errorMsg .= "1) Host: On shared hosting, try 'localhost' or '127.0.0.1'\n";
            $errorMsg .= "2) Username format: Usually 'cpaneluser_dbuser' on shared hosting\n";
            $errorMsg .= "3) Database format: Usually 'cpaneluser_dbname' on shared hosting\n";
            $errorMsg .= "4) Ensure user has ALL PRIVILEGES on the database\n";
            $errorMsg .= "Check Laravel log for detailed error: storage/logs/laravel.log";

            $notify[] = ["error", $errorMsg];
            return back()->withNotify($notify)->withInput($request->except('db_password'));
        }

        // Connection successful, now create .env file
        if (!$this->_envConfig($request)) {
            $notify[] = ["error", "Failed to create configuration file. Please check file permissions on .env file."];
            return back()->withNotify($notify)->withInput($request->except('db_password'));
        }

        // Success - proceed to account setup
        return redirect()->route('install.account.setup', [
            'verify_token' => bcrypt(base64_decode('c3lzdGVtX2NvbmZpZw=='))
        ]);
    }



    /**
     * Setup admin account
     *
     * @return View |RedirectResponse
     */
    public function accountSetup() :View |RedirectResponse
    {

        if (Hash::check(base64_decode('c3lzdGVtX2NvbmZpZw=='), request()->input('verify_token'))) {
            return view('install.account_setup',[
                'title' => 'System Account Setup'
            ]);
        }
        $notify[] = ["error", 'Invalid verification token'];
        return redirect()->route('install.init')->withNotify($notify);

    }

    /**
     * Account Store
     *
     * @param Request $request
     * @return View |RedirectResponse
     */
    public function accountSetupStore(Request $request) :View |RedirectResponse
    {

        try {

            $request->validate([
                'username' => 'required|max:155',
                'email'    => 'required|email|max:155',
                'password' => 'required|min:5',
            ]);

            $is_force = request()->input('force','0');

            if($is_force == StatusEnum::FALSE->status() && !$this->_isDbEmpty()) {

                $notify[] = ["error", 'Please Empty Your database first!!'];
                return redirect()->back()->withNotify($notify);
            }

            $this->_dbMigrate($is_force);
            optimize_clear();

            // Run seeders BEFORE creating the admin. The seeders populate
            // system tables the Admin save path may touch — most importantly
            // the `templates` table used by SendMail::MailNotification. If
            // an observer or notification tries to fire on admin creation
            // before seeders run, we hit
            // "Table 'x_xs.templates' doesn't exist" during install.
            $this->_dbSeed();

            $admin =  Admin::firstOrNew(['username' => "admin"]);
            $admin->username                  = $request->input('username');
            $admin->name                      = 'Admin User';
            $admin->image                      = '67de82df887401742635743.webp';
            $admin->api_key                    = 'b52dcc52-4828-44c5-b0c7-54ef96d37d82';
            $admin->email                     = $request->input('email');
            $admin->password                  = Hash::make($request->input('password'));

            $admin->save();

            session()->put('password',$request->input('password'));

            // Pass purchase credentials from session to save in database
            // Try session first, then fallback to .env (in case session was lost during redirects)
            $purchaseKey = session()->get(base64_decode("cHVyY2hhc2VfY29kZQ=="));
            $envatoUsername = session()->get(base64_decode("dXNlcm5hbWU="));

            // Fallback to .env if session is empty (handles servers with session issues)
            if (empty($purchaseKey)) {
                $purchaseKey = env('PURCHASE_KEY', '');
            }
            if (empty($envatoUsername)) {
                $envatoUsername = env('ENVATO_USERNAME', '');
            }

            $this->_systemInstalled($purchaseKey, $envatoUsername);

            return redirect()->route('install.setup.finished',['verify_token' => bcrypt(base64_decode('c2V0dXBfY29tcGxldGVk'))]);
        } catch (\Exception $ex) {
            $notify[] = ["error", strip_tags($ex->getMessage())];
            return back()->withNotify($notify);

        }


    }


    /**
     * Setup finished
     *
     * @param Request $request
     * @return View |RedirectResponse
     */
    public function setupFinished(Request $request) :View |RedirectResponse
    {
        if (Hash::check(base64_decode('c2V0dXBfY29tcGxldGVk'), request()->input('verify_token'))) {
            $admin =  Admin::where('username', "admin")->first();
            optimize_clear();
            return view('install.setup_finished',[
                'admin' => $admin,
                'title' => 'System Installed',
            ]);
        }
        $notify[] = ["error", 'Invalid verification token'];
        return redirect()->route('install.init')->withNotify($notify);
    }



    /**
     * Invalid user
     *
     * @return View |RedirectResponse
     */
    public function invalidPurchase() :View |RedirectResponse
    {
        if(!$this->_isPurchased()){

            $view = request()->input('verification_view',false) ? 'install.invalid':'invalid_license' ;
            return view($view ,[
                'title' => 'Invalid Software License',
                'note'  => 'Please Verify Yourself',
            ]);
        }
        $notify[] = ["success", 'Your system is already verified'];
        return redirect()->route("home")->withNotify($notify);

    }


    /**
     * Verify purchase
     *
     * @param Request $request
     * @return View |RedirectResponse
     */
    public function verifyPurchase(Request $request) :View |RedirectResponse
    {
        $request->validate([
            base64_decode('cHVyY2hhc2VfY29kZQ==') => "required",
            base64_decode('dXNlcm5hbWU=')         => "required"
        ],[
            base64_decode('cHVyY2hhc2VfY29kZQ==').".required" => "Code is required",
            base64_decode('dXNlcm5hbWU=').".required"         => "Username is required",
        ]);


        if($this->_registerDomain() && $this->_validatePurchaseKey($request->input(base64_decode('cHVyY2hhc2VfY29kZQ==')) , $request->input(base64_decode('dXNlcm5hbWU='))) ){

            $newPurchaseKey        = $request->input(base64_decode('cHVyY2hhc2VfY29kZQ=='));
            $newEnvatoUsername     =  $request->input( base64_decode('dXNlcm5hbWU='));
            update_env('PURCHASE_KEY',$newPurchaseKey);
            update_env('ENVATO_USERNAME',$newEnvatoUsername);
            optimize_clear();
            $this->_systemInstalled($newPurchaseKey,$newEnvatoUsername);
            $notify[] = ["success", 'Verified Successfully'];
            return redirect()->route("admin.dashboard")->withNotify($notify);
        }

        $notify[] = ["error", 'Verification failed. Please check your purchase code and username.'];
        return redirect()->back()->withNotify($notify);
    }
}
