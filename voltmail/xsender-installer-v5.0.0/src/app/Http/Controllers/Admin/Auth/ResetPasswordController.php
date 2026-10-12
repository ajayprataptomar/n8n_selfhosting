<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Exceptions\ApplicationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminResetPasswordRequest;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\AdminPasswordReset;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Http\Utility\SendMail;
use App\Models\FrontendSection;
use App\Services\System\Admin\AuthService;
use Exception;

class ResetPasswordController extends Controller
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

    public function create(Request $request, $token)
    {
        $title = translate("Password change");
        $passwordToken = $token;
        $email = session()->get('admin_password_reset_user_email');
        $userResetToken = AdminPasswordReset::where('email', $email)->where('token', $token)->first();
        if(!$userResetToken){
        	if(session()->get('admin_password_reset_user_email')){
	            session()->forget('admin_password_reset_user_email');
	        }
            $notify[] = ['error', 'Invalid token'];
            return redirect(route('admin.password.request'))->withNotify($notify);
        }
        $feedback_element = FrontendSection::where('section_key', FrontendSection::FEEDBACK_ELEMENT)->get();
        return view('admin.auth.reset',compact('title', 'passwordToken', 'feedback_element'));
    }

    public function store(AdminResetPasswordRequest $request)
    {
        try {

            return $this->authService->resetPassword($request);
        } catch (ApplicationException $e) {
                
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);
        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }  
    }
}
