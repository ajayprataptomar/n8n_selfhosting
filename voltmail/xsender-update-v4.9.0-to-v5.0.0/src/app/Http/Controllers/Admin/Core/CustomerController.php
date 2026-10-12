<?php

namespace App\Http\Controllers\Admin\Core;

use App\Enums\Common\Status;
use App\Models\User;
use App\Enums\StatusEnum;
use Illuminate\View\View;
use App\Models\AndroidApi;
use App\Enums\ServiceType;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\SessionStatusEnum;
use App\Exceptions\ApplicationException;
use App\Models\PricingPlan;
use Illuminate\Support\Arr;
use App\Models\WhatsappDevice;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\UserStoreRequest;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\UserCreditRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\AndroidSession;
use App\Models\Gateway;
use App\Service\Admin\Core\CustomerService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public CustomerService $customerService;

    public function __construct(CustomerService $customerService) {

        $this->customerService = $customerService;
    }

    /**
     * @return \Illuminate\View\View
     * 
     */
    public function index(): View {

        Session::put("menu_active", true);
        $title     = translate("All Members");
        $customers = $this->customerService->getPaginateUsers();
        return view('admin.customer.index', compact('title', 'customers'));
    }

    /**
     * trashed
     *
     * @return View
     */
    public function trashed(): View
    {
        $title = translate("Trashed Users");
        $customers = $this->customerService->getPaginateUsers(true);
        return view('admin.customer.index', compact('title', 'customers'));
    }

    /**
     * @param int|string $uid
     * 
     * @return \Illuminate\View\View
     * 
     */
    public function details(int|string $uid): View {
        
        Session::put("menu_active", true);

        $title         = translate("User Details");
        $user          = $this->customerService->findByUid($uid);
        
        $logs          = $this->customerService->logs($user->id);
        $sms_api_gateways = Gateway::whereNull('user_id')
                                        ->where("channel", ChannelTypeEnum::SMS)
                                        ->where('status', Status::ACTIVE)
                                        ->orderBy('is_default', 'DESC'
                                        )->get();
        $sms_android_gateways = AndroidSession::whereNull('user_id')
                                                    ->where('status', SessionStatusEnum::CONNECTED)
                                                    ->orderBy('id', 'DESC')
                                                    ->get();
        $mail_gateways = Gateway::whereNull('user_id')
                                        ->where("channel", ChannelTypeEnum::EMAIL)
                                        ->where('status', Status::ACTIVE)
                                        ->orderBy('is_default', 'DESC')
                                        ->get();
        $pricing_plans = PricingPlan::where("status", StatusEnum::TRUE->status())->pluck("name", "id")->toArray();
        
        return view('admin.customer.details', compact('title', 'user', 'logs', "pricing_plans", "sms_api_gateways", "sms_android_gateways", "mail_gateways"));
    }

    /**
     * @param UserStoreRequest $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function store(UserStoreRequest $request) {

        $notify[] = ['error', 'Something went wrong'];
        
        try {

            $user = User::create([
                'name'                  => $request->input('name'),
                'email'                 => $request->input('email'),
                'status'                => StatusEnum::TRUE->status(),
                'password'              => Hash::make($request->input('password')),
                'email_verified_code'   => null,
                'email_verified_at'     => carbon(),
                'email_verified_status' => StatusEnum::TRUE->status(),
            ]);
            $notify = $this->customerService->applyOnboardingBonus($user);

        } catch(\Exception $e) {

            $notify = ['error', $e->getMessage()];
        }
        return back()->withNotify($notify);
    }

    /**
     * @param UserUpdateRequest $request
     * 
     * @param int $id
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function update(UserUpdateRequest $request, int $id): mixed {

        $status  = 'error';
        $message = 'Something went wrong';
        try {

            $user = $this->customerService->findById($id);
            
            $currentCredentials = (array) $user->gateway_credentials;
            $updatedCredentials = array_merge($currentCredentials, [
                'in_application_sms_method'       => $request->input('in_application_sms_method'),
                'accessible_sms_api_gateways'     => $request->input('accessible_sms_api_gateways'),
                'accessible_sms_android_gateways' => $request->input('accessible_sms_android_gateways'),
                'accessible_email_gateways'       => $request->input('accessible_email_gateways'),
                'specific_gateway_access'         => $request->input('specific_gateway_access'),
                'specific_pricing_plan'           => $request->input('specific_pricing_plan'),
            ]);
            
            if($request->input("specific_pricing_plan") == StatusEnum::TRUE->status()
                    && $request->filled('pricing_plan')) {

                $this->customerService->updatePlan($user, $request);
            }
            AndroidSession::where([

                "user_id" => $user->id, 
                "status"  => Status::ACTIVE
            ])->update(["status" => Status::INACTIVE]);

            Gateway::where([

                "user_id" => $user->id, 
                "channel" => ChannelTypeEnum::WHATSAPP,
                "status"  => Status::ACTIVE
            ])->update(["status" => Status::INACTIVE]);

            $user->fill($this->userData($request->validated()));
            $user->address = [
                
                'address' => $request->input('address'),
                'city'    => $request->input('city'),
                'state'   => $request->input('state'),
                'zip'     => $request->input('zip')
            ];
            $user->gateway_credentials = $updatedCredentials;
            
            $user->save();
            $status  = 'success';
            $message = translate("User updated successfully");
        } catch (\Exception $e) {

            $status  = 'error';
            $message = $e->getMessage();
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }


    private function userData($data) {

        if(array_key_exists('city', $data)) {

            unset($data['city']);
        }
        if(array_key_exists('state', $data)) {

            unset($data['state']);
        }
        if(array_key_exists('zip', $data)) {

            unset($data['zip']);
        }
        if(array_key_exists('in_application_sms_method', $data)) {

            unset($data['in_application_sms_method']);
        }
        if(array_key_exists('accessible_sms_api_gateways', $data)) {

            unset($data['accessible_sms_api_gateways']);
        }
        if(array_key_exists('accessible_sms_android_gateways', $data)) {

            unset($data['accessible_sms_android_gateways']);
        }
        if(array_key_exists('accessible_email_gateways', $data)) {

            unset($data['accessible_email_gateways']);
        }
        if(array_key_exists('specific_gateway_access', $data)) {

            unset($data['specific_gateway_access']);
        }
        if(array_key_exists('specific_pricing_plan', $data)) {

            unset($data['specific_pricing_plan']);
        }
        return $data;
    }
    /**
     * @param UserCreditRequest $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function modifyCredit(UserCreditRequest $request): mixed {

        $status  = 'error';
        $message = translate("Something went wrong");
        try {

            $user = $this->customerService->findByUid($request->input('uid'));
            if(!$user) {

                $notify[] = ['error', translate('User not found')];
                return back()->withNotify($notify);
            }
            $credits = $this->customerService->buildCreditArray($request);
            foreach ($credits as $type => $credit) {
               
                $column = $type . '_credit';
                
                if ($credit > 0) {
                    
                    if ($request->input('type') == StatusEnum::FALSE->status() && $user->$column < $credit) {

                        $notify[] = ['error', translate('Invalid ' . ucfirst($type) . ' Credit Amount')];
                        return back()->withNotify($notify);
                    }
                }
                $status = 'success';
                if ($request->input('type') == StatusEnum::TRUE->status()) { 
                    
                    $message = translate("Credit has been added by Admin");
                    $this->customerService->addedCreditLog($user, $request->input($type.'_credit'), constant(ServiceType::class . '::' . strtoupper($type))->value, true, $message);
                    
                } else {
                   
                    $message = translate("Credit has been deducted by Admin");
                    $this->customerService->deductCreditLog($user, $request->input($type.'_credit'), constant(ServiceType::class . '::' . strtoupper($type))->value, true, $message);
                }
            }
            $user->save();

        } catch (\Exception $e) {
            
            $message = $e->getMessage();
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

    /**
     * @param int $uid
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function login(string $uid) {

        $user = User::where('uid',$uid)->first();
        
        Auth::guard('web')->loginUsingId($user->id);
        
        $notify[] = ['success', "Logged in as $user->name"];
        return redirect()->route('user.dashboard')->withNotify($notify);
    }

    /**
     * Soft delete (move user to trash)
     *
     * @param string|null $uid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function softDelete(string|null $uid = null)
    {
        try {
            $this->customerService->softDeleteUser($uid);
            $notify[] = ['success', translate('User moved to trash successfully')];
        } catch (Exception $e) {
            $notify[] = ['error', $e->getMessage()];
        }
        return back()->withNotify($notify);
    }

    /**
     * Restore a trashed user
     *
     * @param string|null $uid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore(string|null $uid = null)
    {
        try {
            $this->customerService->restoreUser($uid);
            $notify[] = ['success', translate('User restored successfully')];
        } catch (Exception $e) {
            $notify[] = ['error', $e->getMessage()];
        }
        return back()->withNotify($notify);
    }

    public function deleteProgress(Request $request): JsonResponse
    {
        $userIds = $request->query('user_ids', []);

        if (empty($userIds)) {
            return response()->json([]);
        }

        // Fetch users with trashed records
        $users = User::withTrashed()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $response = [];
        foreach ($userIds as $userId) {
            $user = $users[$userId] ?? null;

            // Initialize default response for the user
            $response[$userId] = [
                'status' => 'none',
                'progress' => 0,
                'deleted' => 0,
                'total' => 0,
            ];

            if (!$user) {
                $response[$userId]['status'] = 'error';
                $response[$userId]['message'] = 'User not found';
                continue;
            }

            // Calculate progress if deletion is in progress
            if ($user->is_erasing) {
                $progress = $user->total_entries > 0
                    ? ($user->total_deleted_entries / $user->total_entries) * 100
                    : 0;
                $status = ($user->total_deleted_entries >= $user->total_entries)
                    ? 'completed'
                    : 'processing';

                $response[$userId] = [
                    'status' => $status,
                    'progress' => round($progress, 2),
                    'deleted' => $user->total_deleted_entries,
                    'total' => $user->total_entries,
                ];
            }
        }

        return response()->json($response);
    }

    /**
     * destroy
     *
     * @param string|null|null $uid
     * 
     * @return RedirectResponse
     */
    public function destroy(string|null $uid = null): RedirectResponse {

        try {

            return $this->customerService->permanentlyDeleteUser($uid);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}