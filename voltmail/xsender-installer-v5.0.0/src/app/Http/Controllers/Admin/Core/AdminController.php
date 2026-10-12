<?php
namespace App\Http\Controllers\Admin\Core;

use App\Enums\ServiceType;
use App\Enums\SubscriptionStatus;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\CommunicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Contact;
use App\Models\User;
use App\Models\Subscription;
use App\Models\DispatchLog;
use App\Models\AndroidApiSimInfo;
use App\Models\PaymentLog;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Models\Gateway;
use App\Models\SupportTicket;
use App\Service\Admin\Core\FileService;
use App\Services\System\AutomationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AdminController extends Controller
{
    public function dashboard() {

        Session::put("menu_active", false);
        
        $title       = translate("Welcome Back").", ". auth()->guard('admin')->user()->username;
        $customers   = User::where('status','!=','3')
                        ->orderBy('id', 'DESC')
                        ->take(site_settings("paginate_number"))
                        ->get();

        $paymentLogs = PaymentLog::orderBy('id', 'DESC')
                        ->where('status', '!=', 0)
                        ->with('user', 'paymentGateway','paymentGateway')
                        ->take(site_settings("paginate_number"))
                        ->get();
        $logs = [
            "sms" => [

                'all'     => DispatchLog::where('type', ChannelTypeEnum::SMS->value)->count(),
                'success' => DispatchLog::where('type', ChannelTypeEnum::SMS->value)->where('status', CommunicationStatusEnum::DELIVERED->value)->count(),
                'pending' => DispatchLog::where('type', ChannelTypeEnum::SMS->value)->where('status', CommunicationStatusEnum::PENDING->value)->count(),
                'failed'  => DispatchLog::where('type', ChannelTypeEnum::SMS->value)->where('status', CommunicationStatusEnum::FAIL->value)->count(),
            ],
            "email" => [

                'all'     => DispatchLog::where('type', ChannelTypeEnum::EMAIL->value)->count(),
                'success' => DispatchLog::where('type', ChannelTypeEnum::EMAIL->value)->where('status', CommunicationStatusEnum::DELIVERED->value)->count(),
                'pending' => DispatchLog::where('type', ChannelTypeEnum::EMAIL->value)->where('status', CommunicationStatusEnum::PENDING->value)->count(),
                'failed'  => DispatchLog::where('type', ChannelTypeEnum::EMAIL->value)->where('status', CommunicationStatusEnum::FAIL->value)->count(),
            ],
            "whats_app" => [
                
                'all'     => DispatchLog::where('type', ChannelTypeEnum::WHATSAPP->value)->count(),
                'success' => DispatchLog::where('type', ChannelTypeEnum::WHATSAPP->value)->where('status',  CommunicationStatusEnum::DELIVERED->value)->count(),
                'pending' => DispatchLog::where('type', ChannelTypeEnum::WHATSAPP->value)->where('status', CommunicationStatusEnum::PENDING->value)->count(),
                'failed'  => DispatchLog::where('type', ChannelTypeEnum::WHATSAPP->value)->where('status', CommunicationStatusEnum::FAIL->value)->count(),
            ]
            // "sms" => [

            //     'all'     => 10,
            //     'success' => 10,
            //     'pending' => 10,
            //     'failed'  => 10,
            // ],
            // "email" => [

            //    'all'     => 10,
            //     'success' => 10,
            //     'pending' => 10,
            //     'failed'  => 10,
            // ],
            // "whats_app" => [
                
            //     'all'     => 10,
            //     'success' => 10,
            //     'pending' => 10,
            //     'failed'  => 10,
            // ]
        ];
        
        [$paymentReport, $paymentReportMonths] = $this->paymentReport();
        $smsWhatsAppReport                     = $this->smsWhatsAppReports();

        // Setup Checklist - detect what's configured
        $setupChecklist = $this->getSetupChecklist();

        // Quick stats for overview cards
        $quickStats = $this->getQuickStats();
        
        $sixMonthsAgo = Carbon::now()->subMonths(6);
        $totalUsers = User::where('created_at', '>=', $sixMonthsAgo)->get();
        $subscribers = Subscription::where('created_at', '>=', $sixMonthsAgo)
                                        ->where(function($query) {
                                            $query->where('status', SubscriptionStatus::RUNNING->value)
                                                    ->orWhere('status', SubscriptionStatus::RENEWED->value);
                                        })->get();
                                        
        $userData = [];
        foreach ($totalUsers as $user) {
            $createdAt = Carbon::parse($user->created_at)->toDateString();
            if (!isset($userData[$createdAt])) {
                $userData[$createdAt] = 0;
            }
            $userData[$createdAt]++;
        }

        $subscriptionData = [];
        foreach ($subscribers as $subscriber) {
            $createdAt = Carbon::parse($subscriber->created_at)->toDateString();
            if (!isset($subscriptionData[$createdAt])) {
                $subscriptionData[$createdAt] = 0;
            }
            $subscriptionData[$createdAt]++;
        }
        
        $totalUser = [
            'dates' => array_keys($userData),
            'newUsers' => array_values($userData),
            'subscriptions' => array_values($subscriptionData),
        ];
        
        return view('admin.dashboard', compact(
            'title',
            'customers',
            'paymentLogs',
            'logs',
            'totalUser',
            'paymentReport',
            'paymentReportMonths',
            'smsWhatsAppReport',
            'setupChecklist',
            'quickStats',
        ));
    }

    /**
     * @return View
     */
    public function profile(Request $request): View {

        Session::put("menu_active", false);
        $activeTab = $request->session()->get('active_tab', 'details');
        $title     = translate("Admin Information");
        $admin     = auth()->guard('admin')->user();
        return view('admin.profile', compact('title', 'admin', 'activeTab'));
    }

    /**
     * @throws ValidationException
     */
    public function profileUpdate(AdminProfileRequest $request) {

        $status = 'error';
        $message = 'Something went wrong';
        try {
            Session::put("menu_active", false);
            $fileService = new FileService();
            
            $admin = Auth::guard('admin')->user();
            $admin->name = $request->input('name');
            $admin->username = $request->input('username');
            $admin->email = $request->input('email');

            if ($request->hasFile('image')) {
                
                $admin->image = $fileService->uploadFile($request->file('image'), "admin_profile");
            }
            $admin->save();
            $status  = 'success';
            $message = translate('Your profile has been updated.');
            
        } catch (\Exception $e) {
            
            $message = translate("Server Error");
        }
        $notify[] = [$status, $message];
        return tabId('details', redirect()->back()->withNotify($notify));
    }

    public function passwordUpdate(Request $request) {

        try {
            Session::put("menu_active", false);
            $request->validate([
                'current_password' => 'required',
                'password' => 'required|min:5|confirmed',
            ]);

            $admin = Auth::guard('admin')->user();

            if (!Hash::check($request->current_password, $admin->password)) {

                $notify[] = ['error', 'Password do not match !!'];
                return tabId('passwordUpdate', redirect()->back()->withNotify($notify));
            }

            $admin->password = Hash::make($request->password);
            $admin->save();
            $notify[] = ['success', 'Password changed successfully.'];
            return tabId('passwordUpdate', redirect()->back()->withNotify($notify));

        } catch (\Exception $e) {

            $notify[] = ['error', 'Something went wrong.'];
            return tabId('passwordUpdate', redirect()->back()->withNotify($notify));
        }
    }



    
    public function smsWhatsAppReports(): array {

        $smsWhatsAppReports = [
            'sms' => [],
            'whatsapp' => [],
            'email' => [],
        ];

        for ($i = 0; $i < 12; $i++) {
            $date = now()->subMonths($i);
            $month = $date->format('M');

            // $smsCount = DispatchLog::where('type', ChannelTypeEnum::SMS->value)->whereMonth('created_at', $date->month)
            //     ->whereYear('created_at', $date->year)
            //     ->count();

            // $emailCount = DispatchLog::where('type', ChannelTypeEnum::EMAIL->value)->whereMonth('created_at', $date->month)
            //     ->whereYear('created_at', $date->year)
            //     ->count();

            // $whatsappCount = DispatchLog::where('type', ChannelTypeEnum::WHATSAPP->value)->whereMonth('created_at', $date->month)
            //     ->whereYear('created_at', $date->year)
            //     ->count();

            $smsCount = 10;

            $emailCount = 10;

            $whatsappCount =10;

            array_unshift($smsWhatsAppReports['sms'], $smsCount);
            array_unshift($smsWhatsAppReports['whatsapp'], $whatsappCount);
            array_unshift($smsWhatsAppReports['email'], $emailCount);
        }

        return $smsWhatsAppReports;
    }


    private function paymentReport(): array
    {
        $paymentReport = [
            'amount' => [],
            'charge' => [],
            'month' => [],
        ];

        for ($i = 0; $i < 12; $i++) {
            $date = now()->subMonths($i);
            $month = $date->format('M');

            $paymentData = PaymentLog::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->get();

            $totalAmount = $paymentData->sum('amount');
            $totalCharge = $paymentData->sum('charge');

            array_unshift($paymentReport['amount'], $totalAmount);
            array_unshift($paymentReport['charge'], $totalCharge);
            array_unshift($paymentReport['month'], $month);
        }

        $quotedMonthsArray = array_map(function ($month) {
            return '"' . $month . '"';
        }, $paymentReport['month']);

        $paymentReportMonths = implode(',', $quotedMonthsArray);
        
        return [$paymentReport,$paymentReportMonths];
    }

    

    

   

    /**
     * Quick overview stats for the dashboard top row
     */
    private function getQuickStats(): array
    {
        $totalUsers = User::where('status', '!=', '3')->count();

        $activeSubscriptions = Subscription::where(function ($q) {
            $q->where('status', SubscriptionStatus::RUNNING->value)
              ->orWhere('status', SubscriptionStatus::RENEWED->value);
        })->count();

        $monthlyRevenue = PaymentLog::where('status', PaymentLog::SUCCESS)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $messagesToday = DispatchLog::whereDate('created_at', today())->count();

        $pendingTickets = SupportTicket::where('status', '!=', \App\Enums\TicketStatusEnum::CLOSED->value)->count();

        $pendingQueue = DB::table('jobs')->count();

        return [
            'total_users'          => $totalUsers,
            'active_subscriptions' => $activeSubscriptions,
            'monthly_revenue'      => $monthlyRevenue,
            'messages_today'       => $messagesToday,
            'pending_tickets'      => $pendingTickets,
            'pending_queue'        => $pendingQueue,
        ];
    }

    /**
     * Build the setup checklist for the dashboard
     */
    private function getSetupChecklist(): array
    {
        $items = [];

        // 1. Automation / Cron Job
        $automationHealth = AutomationService::getHealthStatus();
        $items[] = [
            'key'         => 'automation',
            'label'       => translate('Background Jobs'),
            'description' => translate('Cron job or scheduler must be running to process messages'),
            'done'        => $automationHealth['is_healthy'],
            'icon'        => 'ri-timer-flash-line',
            'route'       => route('admin.system.setting', ['type' => 'automation']),
            'hint'        => $automationHealth['is_healthy']
                ? translate('Last run') . ': ' . $automationHealth['last_run_ago']
                : translate('Not running — configure now'),
        ];

        // 2. Email Gateway
        $hasEmailGateway = Gateway::whereNull('user_id')
            ->where('channel', ChannelTypeEnum::EMAIL->value)
            ->where('status', \App\Enums\Common\Status::ACTIVE->value)
            ->exists();
        $items[] = [
            'key'         => 'email_gateway',
            'label'       => translate('Email Gateway'),
            'description' => translate('At least one active email gateway is required to send emails'),
            'done'        => $hasEmailGateway,
            'icon'        => 'ri-mail-settings-line',
            'route'       => route('admin.gateway.email.index'),
            'hint'        => $hasEmailGateway
                ? translate('Gateway configured')
                : translate('No active gateway'),
        ];

        // 3. SMS Gateway
        $hasSmsGateway = Gateway::whereNull('user_id')
            ->where('channel', ChannelTypeEnum::SMS->value)
            ->where('status', \App\Enums\Common\Status::ACTIVE->value)
            ->exists();
        $items[] = [
            'key'         => 'sms_gateway',
            'label'       => translate('SMS Gateway'),
            'description' => translate('At least one active SMS gateway is required to send SMS'),
            'done'        => $hasSmsGateway,
            'icon'        => 'ri-message-2-line',
            'route'       => route('admin.gateway.sms.api.index'),
            'hint'        => $hasSmsGateway
                ? translate('Gateway configured')
                : translate('No active gateway'),
        ];

        // 4. WhatsApp Gateway
        $hasWhatsAppGateway = Gateway::whereNull('user_id')
            ->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->where('status', \App\Enums\Common\Status::ACTIVE->value)
            ->exists();
        $items[] = [
            'key'         => 'whatsapp_gateway',
            'label'       => translate('WhatsApp Gateway'),
            'description' => translate('At least one active WhatsApp gateway is needed'),
            'done'        => $hasWhatsAppGateway,
            'icon'        => 'ri-whatsapp-line',
            'route'       => route('admin.gateway.whatsapp.cloud.api.index'),
            'hint'        => $hasWhatsAppGateway
                ? translate('Gateway configured')
                : translate('No active gateway'),
        ];

        $completed = count(array_filter($items, fn($i) => $i['done']));

        return [
            'items'     => $items,
            'total'     => count($items),
            'completed' => $completed,
            'percent'   => count($items) > 0 ? round(($completed / count($items)) * 100) : 0,
        ];
    }

    public function generateApiKey()
    {
        $title = "Generate Api Key";
        $admin = Auth::guard('admin')->user();
        return view('admin.generate_api_key', compact('title', 'admin'));
    }

    public function saveGenerateApiKey(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $admin->api_key  = $request->has('api_key') ? $request->input('api_key') : $admin->api_key ;
        $admin->save();

        return response()->json([
            'message' => 'New Api Key Has been Generate'
        ]);
    }


    public function selectSearch(Request $request){
        
        $searchData = trim($request->term);
        $contacts   = Contact::select('id','email_contact as text')->whereNull('user_id')->with('group')->where('email_contact','LIKE',  '%' . $searchData. '%')->latest()->simplePaginate(10);
        $pages      = true;

        if (empty($contacts->nextPageUrl())) {

            $pages = false;
        }
        $results = array(
          "results" => $contacts->items(),
          "pagination" => array(
            "more" => $pages
          )
        );

        return response()->json($results);
    }
    

    public function selectGateway(Request $request, $type = null) {

        $rows = [];

        if($type == "sms" || $type == "email") {
            
            $rows = Gateway::whereNull('user_id')->where('type', $request->type)->latest()->get();

        } elseif($type == "android") {

            $rows = AndroidApiSimInfo::where('android_gateway_id', $request->type)->latest()->get();
        } 
        return response()->json($rows);
    }
}
