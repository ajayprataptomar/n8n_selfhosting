<?php

namespace App\Services\System\Admin;

use App\Enums\Common\Status;
use App\Enums\DefaultTemplateSlug;
use App\Enums\SettingKey;
use App\Enums\StatusEnum;
use App\Enums\System\ChannelTypeEnum;
use App\Exceptions\ApplicationException;
use App\Http\Requests\AdminNewPasswordRequest;
use App\Http\Requests\AdminResetPasswordRequest;
use App\Http\Utility\SendMail;
use App\Managers\AdminManager;
use App\Models\Admin;
use App\Models\AdminPasswordReset;
use App\Models\Gateway;
use App\Models\Template;
use App\Services\System\TemplateService;
use App\Traits\Manageable;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthService
{ 
     use Manageable;

     protected $sendMail;
     protected $adminManager;
     protected $templateService;

     /**
      * __construct
      *
      */
     public function __construct()
     {
          $this->sendMail          = new SendMail();
          $this->adminManager      = new AdminManager();
          $this->templateService   = new TemplateService();
     }

     /**
      * resetPassword
      *
      * @param AdminNewPasswordRequest $request
      * 
      * @return RedirectResponse
      */
     public function sendResetPasswordNotification(AdminNewPasswordRequest $request): RedirectResponse {

          $admin = $this->getSpecificLogByColumn(model: new Admin(), column: "email", value: $request->input("email")); 

          if(!$admin) throw new ApplicationException("No admin account found with that email", Response::HTTP_NOT_FOUND);

          $passwordResetLog = $this->getSpecificLogByColumn(model: new AdminPasswordReset(), column: "email", value: $request->input("email"));
          
          if($passwordResetLog) $passwordResetLog->delete();

          $now      = Carbon::now();
          $token    = randomNumber();

          AdminPasswordReset::create([
               'email'        => $request->input("email"),
               'token'        => $token,
               'created_at'   => $now,
          ]);
          
          $gateway = $this->getSpecificLogByColumn(
               model: new Gateway(), 
               column: "is_default",
               value: StatusEnum::TRUE->status(),
               attributes: [
                    "user_id" => null,
                    "channel" => ChannelTypeEnum::EMAIL->value,
               ]
          );
          if(!$gateway) throw new ApplicationException("Default gateway was not set, please contact support", Response::HTTP_NOT_FOUND);

          $template = $this->getSpecificLogByColumn(
               model: new Template(), 
               column: "slug",
               value: DefaultTemplateSlug::ADMIN_PASSWORD_RESET->value,
               attributes: [
                    "user_id" => null,
                    "channel" => ChannelTypeEnum::EMAIL->value,
                    "default" => true,
                    "status"  => Status::ACTIVE->value
               ]
          );
          if(!$template) throw new ApplicationException("Template Unavailable", Response::HTTP_NOT_FOUND);

          $mailCode = [
               'code' => $token, 
               'time' => $now,
          ];

          $status = $this->sendMail->MailNotification($gateway, $template, $admin, $mailCode);

          if(!$status) throw new ApplicationException("Could not send email please try again or contact support", Response::HTTP_BAD_REQUEST);

          session()->put('admin_password_reset_user_email', $request->email);
          $notify[] = ['success', translate('Check your email password reset code sent successfully')];
          return redirect(route('admin.password.verify.code'))->withNotify($notify);
     }

     /**
      * resetPassword
      *
      * @param AdminResetPasswordRequest $request
      * 
      * @return RedirectResponse
      */
     public function resetPassword(AdminResetPasswordRequest $request): RedirectResponse {

          $email = session()->get('admin_password_reset_user_email');

          // Defensive: the password-set form does not re-submit the email field
          // (user already verified the code). The email lives in the session under
          // 'admin_password_reset_user_email'. Without this fallback the admin
          // lookup fails with "Contact not found".
          if (empty($email)) {
               $email = $request->input("email");
          }

          if (empty($email)) {
               throw new ApplicationException("Password reset session expired. Please request a new code.", Response::HTTP_NOT_FOUND);
          }

          $passwordResetLog = $this->getSpecificLogByColumn(
               model: new AdminPasswordReset(),
               column: "email",
               value: $email);
          if(!$passwordResetLog) throw new ApplicationException("Invalid Token", Response::HTTP_NOT_FOUND);

          $admin = $this->getSpecificLogByColumn(model: new Admin(), column: "email", value: $email);
          if(!$admin) throw new ApplicationException("Admin account not found for the supplied email", Response::HTTP_NOT_FOUND);
          $admin->password = Hash::make($request->password);
          $admin->save();

          // Invalidate the reset code so it cannot be reused (one-time-use).
          $passwordResetLog->delete();

          if(session()->get('admin_password_reset_user_email')){
               session()->forget('admin_password_reset_user_email');
          }

          $gateway = $this->getSpecificLogByColumn(
               model: new Gateway(), 
               column: "is_default",
               value: StatusEnum::TRUE->status(),
               attributes: [
                    "user_id" => null,
                    "channel" => ChannelTypeEnum::EMAIL->value,
               ]
          );
          if(!$gateway) throw new ApplicationException("Default gateway was not set, please contact support", Response::HTTP_NOT_FOUND);

          $template = $this->getSpecificLogByColumn(
               model: new Template(), 
               column: "slug",
               value: DefaultTemplateSlug::PASSWORD_RESET_CONFIRM->value,
               attributes: [
                    "user_id" => null,
                    "channel" => ChannelTypeEnum::EMAIL->value,
                    "default" => true,
                    "status"  => Status::ACTIVE->value
               ]
          );
          if(!$template) throw new ApplicationException("Template Unavailable", Response::HTTP_NOT_FOUND);

          $mailCode = [
               'time' => Carbon::now(),
               'name' => site_settings(SettingKey::SITE_NAME->value, "Xsender")
          ];

          $status = $this->sendMail->MailNotification($gateway, $template, $admin, $mailCode);
          if(!$status) throw new ApplicationException("Could not send email please try again or contact support", Response::HTTP_BAD_REQUEST);

          $passwordResetLog->delete();
          $notify[] = ['success', 'Password changed successfully'];
          return redirect(route('admin.login'))->withNotify($notify);
     }
}