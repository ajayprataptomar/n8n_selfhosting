<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Exceptions\ApplicationException;
use Carbon\Carbon;
use App\Models\Admin;
use Illuminate\Http\Request;
use App\Http\Utility\SendMail;
use App\Models\AdminPasswordReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminNewPasswordRequest;
use App\Models\FrontendSection;
use App\Services\System\Admin\AuthService;
use Exception;
use Illuminate\Http\RedirectResponse;

class NewPasswordController extends Controller
{
    protected $sendMail;
    protected $authService;

    /**
     * __construct
     *
     */
    public function __construct()
    {
        $this->sendMail = new SendMail();
        $this->authService = new AuthService();
    }

    public function create()
    {
        $title = translate("forgot password");
        $feedback_element = FrontendSection::where('section_key', FrontendSection::FEEDBACK_ELEMENT)->get();
        return view('admin.auth.forgot-password', compact('title', 'feedback_element'));
    }

    /**
     * store
     *
     * @param AdminNewPasswordRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(AdminNewPasswordRequest $request): RedirectResponse
    {
        try {

            return $this->authService->sendResetPasswordNotification($request);
        } catch (ApplicationException $e) {
                
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);
        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    public function passwordResetCodeVerify(){
        $title = translate("Admin Password Reset");
        if(!session()->get('admin_password_reset_user_email')) {
            $notify[] = ['error','Your email session expired please try again'];
            return redirect()->route('admin.password.request')->withNotify($notify);
        }
        $feedback_element = FrontendSection::where('section_key', FrontendSection::FEEDBACK_ELEMENT)->get();
        return view('admin.auth.verify',compact('title', 'feedback_element'));
    }

    public function emailVerificationCode(Request $request)
    {
        $this->validate($request, [
            'code' => 'required'
        ]);
        $code = preg_replace('/[ ,]+/', '', trim($request->code));
        $email = session()->get('admin_password_reset_user_email');
        $adminResetToken = AdminPasswordReset::where('email', $email)->where('token', $code)->first();
        if(!$adminResetToken){
        	if(session()->get('admin_password_reset_user_email')){
	            session()->forget('admin_password_reset_user_email');
	        }
            $notify[] = ['error', 'Invalid token'];
            return redirect(route('admin.password.request'))->withNotify($notify);
        }
        $notify[] = ['success', 'Change your password.'];
        return redirect()->route('admin.password.reset', $code)->withNotify($notify);

    }

}
