<?php

namespace App\Http\Controllers\User;

use App\Enums\ServiceType;
use App\Enums\SettingKey;
use App\Enums\Common\Status;
use App\Enums\StatusEnum;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\CommunicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\AffiliateLog;
use App\Models\AndroidApi;
use App\Models\AndroidApiSimInfo;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\CampaignUnsubscribe;
use App\Models\DispatchLog;
use App\Models\GeneralSetting;
use App\Models\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Transaction;
use App\Models\Group;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Template;
use App\Models\CreditLog;
use App\Models\EmailLog;
use App\Models\EmailCreditLog;
use App\Models\PaymentMethod;
use App\Models\WhatsappLog;
use App\Models\WhatsappCreditLog;
use App\Models\PaymentLog;
use App\Models\Gateway;
use App\Models\PostWebhookLog;
use App\Models\PricingPlan;
use Gregwar\Captcha\PhraseBuilder;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Support\Facades\Session;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Rules\FileExtentionCheckRule;
use App\Service\Admin\Core\FileService;
use App\Service\Admin\Dispatch\WhatsAppService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public $fileService;
    public function __construct() {

        $this->fileService = new FileService();
    }

    public function dashboard()
    {
        Session::put("menu_active", false);
        $now = Carbon::now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonth()->endOfMonth();
        $title          = translate("Welcome Back").", ".auth()->user()->name;
        $user = Auth::user();
        
        $planAccess = planAccess($user);
        $creditUsage = [];
        if(!empty($planAccess)){

            $planAccess = (object) $planAccess;
            $types = ['sms', 'email', 'whatsapp'];
            $creditUsage = [];
    
            foreach ($types as $type) {
                $creditColumn = $type . '_credit';
                $creditsPerDay = Arr::get($planAccess->{$type}, 'credits_per_day', 0);
    
                // Unlimited if credits_per_day is 0
                $isUnlimited = ($creditsPerDay == 0);
    
                $todayStart = Carbon::today()->startOfDay();
                $todayEnd = Carbon::today()->endOfDay();
    
                $usedCredits = 0;
                if (!$isUnlimited) {
                    $usedCredits = DB::table('dispatch_logs')
                        ->where('user_id', $user->id)
                        ->where('type', $type)
                        ->whereBetween('created_at', [$todayStart, $todayEnd])
                        ->whereIn('status', [
                            CommunicationStatusEnum::PENDING->value,
                            CommunicationStatusEnum::SCHEDULE->value,
                            CommunicationStatusEnum::PROCESSING->value,
                            CommunicationStatusEnum::DELIVERED->value
                        ])
                        ->count();
                }
    
                $creditUsage[$type] = [
                    'used' => $usedCredits,
                    'total' => $isUnlimited ? -1 : $creditsPerDay,
                    'available' => $isUnlimited ? -1 : max(0, $creditsPerDay - $usedCredits),
                    'is_unlimited' => $isUnlimited,
                ];
            }
        }


        $currentMonthCommission = DB::table('affiliate_logs')
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('commission_amount');

        $previousMonthCommission = DB::table('affiliate_logs')
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('commission_amount');

        $currentMonthWithdraw = DB::table('withdraw_logs')
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('final_amount');

        $previousMonthWithdraw = DB::table('withdraw_logs')
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('final_amount');

        // Net change for each month
        $currentMonthNet = $currentMonthCommission - $currentMonthWithdraw;
        $previousMonthNet = $previousMonthCommission - $previousMonthWithdraw;

        // Calculate growth rate
        if ($previousMonthNet != 0) {
            $growthRate = (($currentMonthNet - $previousMonthNet) / abs($previousMonthNet)) * 100;
        } else {
            $growthRate = $currentMonthNet > 0 ? 100 : 0;
        }

        $growthRateFormatted = ($growthRate >= 0 ? '+' : '') . number_format($growthRate, 1) . '%';
        
        $transactions   = Transaction::where('user_id', $user->id)
                                        ->orderBy('id', 'DESC')
                                        ->take(5)
                                        ->get();
        $credits        = CreditLog::where('user_id', $user->id)
                                        ->where('post_credit', '>', '0')
                                        ->orderBy('id', 'DESC')
                                        // ->with('user')
                                        ->take(5)
                                        ->get();

        
        $logs = [
            "sms" => [
                'all' => DispatchLog::where('type', ChannelTypeEnum::SMS->value)->where('user_id', $user->id)->count(),
                'success' => DispatchLog::where('type', ChannelTypeEnum::SMS->value)->where('user_id', $user->id)->where('status', CommunicationStatusEnum::DELIVERED->value)->count(),
                'pending' => DispatchLog::where('type', ChannelTypeEnum::SMS->value)->where('user_id', $user->id)->where('status', CommunicationStatusEnum::PENDING->value)->count(),
                'failed' => DispatchLog::where('type', ChannelTypeEnum::SMS->value)->where('user_id', $user->id)->where('status', CommunicationStatusEnum::FAIL->value)->count(),
                // 'all' => 10,
                // 'success' => 10,
                // 'pending' => 10,
                // 'failed' => 10,
            ],
            "email" => [
                // 'all' => 10,
                // 'success' => 10,
                // 'pending' => 10,
                // 'failed' => 10,
                'all' => DispatchLog::where('type', ChannelTypeEnum::EMAIL->value)->where('user_id', $user->id)->count(),
                'success' => DispatchLog::where('type', ChannelTypeEnum::EMAIL->value)->where('user_id', $user->id)->where('status', CommunicationStatusEnum::DELIVERED->value)->count(),
                'pending' => DispatchLog::where('type', ChannelTypeEnum::EMAIL->value)->where('user_id', $user->id)->where('status', CommunicationStatusEnum::PENDING->value)->count(),
                'failed' => DispatchLog::where('type', ChannelTypeEnum::EMAIL->value)->where('user_id', $user->id)->where('status', CommunicationStatusEnum::FAIL->value)->count(),
            ],
            'whats_app' => [
                // 'all' => 10,
                // 'success' => 10,
                // 'pending' => 10,
                // 'failed' => 10,
                'all' => DispatchLog::where('type', ChannelTypeEnum::WHATSAPP->value)->where('user_id', $user->id)->count(),
                'success' => DispatchLog::where('type', ChannelTypeEnum::WHATSAPP->value)->where('user_id', $user->id)->where('status', CommunicationStatusEnum::DELIVERED->value)->count(),
                'pending' => DispatchLog::where('type', ChannelTypeEnum::WHATSAPP->value)->where('user_id', $user->id)->where('status', CommunicationStatusEnum::PENDING->value)->count(),
                'failed' => DispatchLog::where('type', ChannelTypeEnum::WHATSAPP->value)->where('user_id', $user->id)->where('status', CommunicationStatusEnum::FAIL->value)->count(),
            ],
        ];
        
        // Setup guide: plan-aware gateway guidance
        $setupGuide = $this->getUserSetupGuide($user, $planAccess);

        // Quick stats for overview
        $quickStats = $this->getUserQuickStats($user);

        return view('user.dashboard', compact(
            'title', 'user', 'creditUsage', 'transactions', 'credits',
            'logs', 'growthRateFormatted', 'setupGuide', 'quickStats'
        ));
    }

    /**
     * Build plan-aware setup guide for user dashboard
     */
    private function getUserSetupGuide($user, $planAccess): array
    {
        if (empty($planAccess)) {
            return [
                'has_plan'  => false,
                'items'     => [],
                'completed' => 0,
                'total'     => 0,
                'percent'   => 0,
            ];
        }

        $pa = is_object($planAccess) ? $planAccess : (object) $planAccess;
        $isAdminPlan = ($pa->type ?? '') == StatusEnum::TRUE->status();
        $items = [];

        $channels = [
            'sms' => [
                'label'       => translate('SMS Gateway'),
                'icon'        => 'ri-message-2-line',
                'admin_hint'  => translate('Using system gateways'),
                'user_route'  => route('user.gateway.sms.api.index'),
                'send_route'  => route('user.communication.sms.create'),
            ],
            'email' => [
                'label'       => translate('Email Gateway'),
                'icon'        => 'ri-mail-settings-line',
                'admin_hint'  => translate('Using system gateways'),
                'user_route'  => route('user.gateway.email.index'),
                'send_route'  => route('user.communication.email.create'),
            ],
            'whatsapp' => [
                'label'       => translate('WhatsApp Gateway'),
                'icon'        => 'ri-whatsapp-line',
                'admin_hint'  => translate('Using system gateways'),
                'user_route'  => route('user.gateway.whatsapp.cloud.api.index'),
                'send_route'  => route('user.communication.whatsapp.create'),
            ],
        ];

        foreach ($channels as $key => $channel) {
            $channelAccess = (array) ($pa->{$key} ?? []);
            $isAllowed = Arr::get($channelAccess, 'is_allowed', false);

            if (!$isAllowed) {
                continue;
            }

            if ($isAdminPlan) {
                // Admin plan: user uses system gateways, always "done"
                $items[] = [
                    'key'        => $key,
                    'label'      => $channel['label'],
                    'icon'       => $channel['icon'],
                    'done'       => true,
                    'hint'       => $channel['admin_hint'],
                    'route'      => $channel['send_route'],
                    'action'     => translate('Send Now'),
                ];
            } else {
                // User plan: check if user has created active gateways
                $hasGateway = Gateway::where('user_id', $user->id)
                    ->where('channel', ChannelTypeEnum::from($key)->value)
                    ->where('status', Status::ACTIVE->value)
                    ->exists();

                $items[] = [
                    'key'        => $key,
                    'label'      => $channel['label'],
                    'icon'       => $channel['icon'],
                    'done'       => $hasGateway,
                    'hint'       => $hasGateway
                        ? translate('Gateway configured')
                        : translate('Add your gateway to start sending'),
                    'route'      => $hasGateway ? $channel['send_route'] : $channel['user_route'],
                    'action'     => $hasGateway ? translate('Send Now') : translate('Setup'),
                ];
            }
        }

        $completed = count(array_filter($items, fn($i) => $i['done']));
        $total = count($items);

        return [
            'has_plan'      => true,
            'is_admin_plan' => $isAdminPlan,
            'items'         => $items,
            'completed'     => $completed,
            'total'         => $total,
            'percent'       => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    /**
     * Quick overview stats for user dashboard
     */
    private function getUserQuickStats($user): array
    {
        $totalContacts = Contact::where('user_id', $user->id)->count();
        $totalGroups = ContactGroup::where('user_id', $user->id)->count();
        $messagesToday = DispatchLog::where('user_id', $user->id)
            ->whereDate('created_at', today())->count();
        $totalGateways = Gateway::where('user_id', $user->id)
            ->where('status', Status::ACTIVE->value)->count();

        return [
            'total_contacts'  => $totalContacts,
            'total_groups'    => $totalGroups,
            'messages_today'  => $messagesToday,
            'total_gateways'  => $totalGateways,
        ];
    }

    public function profile()
    {
        Session::put("menu_active", false);
        $title = translate("User Profile");
        $user = auth()->user();
        return view('user.profile', compact('title', 'user'));
    }

    public function profileUpdate(Request $request)
    {
        $user = Auth::user();
        $this->validate($request, [
            'name' => 'nullable',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'image' => ['nullable', 'image', new FileExtentionCheckRule(json_decode(site_settings('mime_types'),true))],
            'address' => 'nullable|max:250',
            'city' => 'nullable|max:250',
            'state' => 'nullable|max:250',
            'zip' => 'nullable|max:250',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $address = [
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'zip' => $request->zip
        ];
        $user->address = $address;
        if($request->hasFile('image')) {
            try {
                $removefile = $user->image ?: null;
                $user->image = $this->fileService->uploadFile($request->image, null, filePath()['profile']['user']['path'], filePath()['profile']['user']['size'], false);
            }catch (\Exception $exp){
                $notify[] = ['error', 'Image could not be uploaded.'];
                return back()->withNotify($notify);
            }
        }
        $user->save();
        $notify[] = ['success', 'Your profile has been updated.'];
        return redirect()->route('user.profile')->withNotify($notify);
    }

    public function password()
    {
        $title = translate("Password Update");
        return view('user.password', compact('title'));
    }

    public function passwordUpdate(Request $request)
    {
        $this->validate($request, [
            'current_password' => 'nullable',
            'password' => 'required|confirmed',
        ]);

        $user = auth()->user();

        if ($user->password && !Hash::check($request->input('current_password'), $user->password)) {
            $notify[] = ['error', 'The password doesn\'t match!'];
            return back()->withNotify($notify);
        }

        $user->password = Hash::make($request->input('password'));
        $user->save();

        $notify[] = ['success', 'Password has been updated'];
        return back()->withNotify($notify);
    }
    
    public function transaction() {

        Session::put("menu_active", true);
        $title          = translate("Transaction log");
        $paymentMethods = PaymentMethod::where('status', 1)->get();
        $transactions   = Transaction::where('user_id', auth()->user()->id)
                            ->search(['transaction_number', 'amount'])
                            ->latest()
                            ->date()
                            ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                            ->appends(request()->all());
        return view('user.report.record.transaction', compact('title', 'transactions', 'paymentMethods'));
    }

    public function paymentLog()
    {
        Session::put("menu_active", true);
        $title = translate("Payment Logs");
        $paymentLogs = PaymentLog::where('status', '!=', 0)
                                    ->where("user_id", auth()->user()->id)
                                    ->with('paymentGateway')
                                    ->latest()
                                    ->search(['trx_number', 'amount', 'paymentGateway:name'])
                                    ->with('user', 'plan')
                                    ->date()
                                    ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                                    ->appends(request()->all());
        $paymentMethods = PaymentMethod::where('status', 1)->get();
        return view('user.report.record.payment', compact('title', 'paymentLogs', 'paymentMethods'));
    }

    public function affiliateLog()
    {
        Session::put("menu_active", true);
        $title = translate("Affiliate Logs");
        $logs = AffiliateLog::where("user_id", auth()->user()->id)
                                    ->with(['user', 'affiliate', 'subscription', 'subscription.plan'])
                                    ->latest()
                                    ->search(['trx_code', 'user:name', 'user:email', 'affiliate:name', 'affiliate:email'])
                                    ->date()
                                    ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                                    ->appends(request()->all());
        return view('user.report.record.affiliate', compact('title', 'logs'));
    }

    public function credit() {
        
        Session::put("menu_active", true);
        $title      = translate("Credit logs");
        $creditLogs = CreditLog::where("user_id", auth()->user()->id)
                        ->routefilter()
                        ->latest()
                        ->date()
                        ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                        ->appends(request()->all());

        return view('user.report.credit', compact('title', 'creditLogs'));
    }


    public function generateApiKey()
    {
        $title = translate("Generate Api Key");
        $user = Auth::user();
        return view('user.generate_api_key', compact('title', 'user'));
    }

    public function saveGenerateApiKey(Request $request)
    {
        $user = Auth::user();
        $user->api_key  = $request->has('api_key') ? $request->input('api_key') : $user->api_key ;
        $user->save();

        return response()->json([
            'message' => 'New Api Key Has been Generate'
        ]);
    }


    public function defaultSmsMethod() {
        
        $title          = "SMS Send Method";
        $user           = Auth::user();
        $setting        = GeneralSetting::first();
        $allowed_access = planAccess($user);
        $general 		= GeneralSetting::first();
        
        if($allowed_access) {

            $allowed_access = (object)planAccess($user);
           
        } else {

            $notify[] = ['error','Please Purchase A Plan'];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }
        
        if($allowed_access->type == StatusEnum::FALSE->status()) {

            $smsGateways      = Gateway::where('user_id', $user->id)->sms()->orderBy('is_default', 'DESC')->paginate(paginateNumber(site_settings("paginate_number")));
            $gatewaysForCount = Gateway::where('user_id', $user->id)->sms()->where('status',1)->get();
            $androids         = AndroidApi::where('user_id', auth()->user()->id)->orderBy('id', 'DESC')->paginate(paginateNumber(site_settings("paginate_number")));
        } else {

            $smsGateways      = Gateway::whereNull('user_id')->sms()->orderBy('is_default', 'DESC')->paginate(paginateNumber(site_settings("paginate_number")));
            $gatewaysForCount = Gateway::whereNull('user_id')->sms()->where('status',1)->get();
            $androids         = AndroidApi::whereNull('user_id')->orderBy('id', 'DESC')->paginate(paginateNumber(site_settings("paginate_number")));
        }
        
        $defaultGateway = Arr::get($user->gateway_credentials, 'sms.default_gateway_id',  $setting->sms_gateway_id);
        $credentials    = SmsGateway::orderBy('id','asc')->get();
        
        if(request()->routeIs('user.sms.gateway.sendmethod.gateway')) {

            return view("user.gateway.settings.gateway", compact('title', 'smsGateways', 'defaultGateway', 'androids', 'general'));
        }
        elseif(request()->routeIs('user.sms.gateway.sendmethod.api')) {

            if($allowed_access->sms["is_allowed"]) {

                $gatewayCount = $gatewaysForCount->groupBy('type')->map->count(); 
                return view("user.gateway.index", compact('title', 'smsGateways', 'defaultGateway', 'androids', 'credentials', 'user', 'gatewayCount', 'allowed_access', 'general'));
            } else {
                $notify[] = ['error', "You Do Not Have The Permission To Create SMS Gateway!"];
                return back()->withNotify($notify);
            }
           
        }
        elseif(request()->routeIs('user.gateway.sendmethod.android')) {

            return view("user.android.gateways", compact('title', 'smsGateways', 'defaultGateway', 'androids', 'allowed_access', 'general'));
        }
    }


    public function defaultSmsGateway(Request $request) {

        $request->validate([
            'sms_gateway'=>"required"
        ]);

        $user              = Auth::user();
        $user->sms_gateway = $request->input('sms_gateway');
        $user->save();

        $notify[] = ['success', 'Default Gateway Updated!!!'];
        return back()->withNotify($notify);
    }

    public function defaultCaptcha(int | string $randCode) :void {
        
        $phrase  = new PhraseBuilder;
        $code    = $phrase->build(4);
        $builder = new CaptchaBuilder($code, $phrase);
        $builder->setBackgroundColor(220, 210, 230);
        $builder->setMaxAngle(25);
        $builder->setMaxBehindLines(0);
        $builder->setMaxFrontLines(0);
        $builder->build($width = 100, $height = 40, $font = null);
        $phrase = $builder->getPhrase();
 
        if(Session::has('gcaptcha_code')) {
            Session::forget('gcaptcha_code');
        }
        Session::put('gcaptcha_code', $phrase);
        header("Cache-Control: no-cache, must-revalidate");
        header("Content-Type:image/jpeg");
        $builder->output();
    }

    /**
     * Handle the unsubscribe request.
     *
     * Wrapped in try/catch — decrypt() throws when the link is mangled by an
     * email client (trailing punctuation, line-wrapping, copy-paste), and we
     * never want a generic 500 page for recipients who clicked an email.
     * Instead show the friendly success page so they perceive the action as
     * complete.
     */
    public function unsubscribe(Request $request) {

        try {
            // Campaign-linked unsubscribes carry campaign_id. Ad-hoc (Messages → Send) carry user_id instead.
            $campaign_id = $request->filled('campaign_id') ? decrypt($request->get('campaign_id')) : null;
            $contact_uid = decrypt($request->get('contact_id'));
            $channel     = $request->get('channel', ChannelTypeEnum::EMAIL->value);

            if ($campaign_id !== null) {
                $user_id = optional(Campaign::find($campaign_id))->user_id;
            } else {
                $user_id = $request->filled('user_id') ? decrypt($request->get('user_id')) : null;
            }

            $existsQuery = CampaignUnsubscribe::where('contact_uid', $contact_uid)
                ->where('channel', $channel);

            if ($campaign_id !== null) {
                $existsQuery->where('campaign_id', $campaign_id);
            } else {
                $existsQuery->whereNull('campaign_id')->where('user_id', $user_id);
            }

            if (!$existsQuery->exists()) {
                CampaignUnsubscribe::create([
                    'user_id'     => $user_id,
                    'contact_uid' => $contact_uid,
                    'campaign_id' => $campaign_id,
                    'channel'     => $channel,
                    'meta_data'   => json_encode([
                        'unsubscribed_at' => now(),
                        'source'          => $campaign_id !== null ? 'campaign' : 'ad_hoc',
                    ]),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Unsubscribe link decrypt/persist failed', [
                'error' => $e->getMessage(),
                'query' => $request->query(),
            ]);
        }

        return redirect()->route('unsubscribe.success');
    }

    /**
     * RFC 8058 One-Click POST endpoint.
     * Called by receiving mail servers (Gmail, Yahoo, Apple Mail) with a
     * form body of `List-Unsubscribe=One-Click`. Reuses the same persist
     * logic as unsubscribe() but returns a plain 200 instead of a redirect,
     * as the caller is a mail-provider bot, not a browser.
     */
    public function unsubscribePost(Request $request) {
        try {
            $this->unsubscribe($request);
        } catch (\Throwable $e) {
            \Log::warning('One-Click unsubscribe failed', ['error' => $e->getMessage()]);
        }

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    public function unsubscribeSuccess() {

        $siteName = e((string) site_settings('site_name', 'XSender'));
        $title    = translate('Unsubscribed');
        $headline = translate('You are unsubscribed.');
        $sub      = translate('You will no longer receive marketing emails from') . ' ' . $siteName . '.';
        $note     = translate('Transactional messages (order confirmations, receipts, password resets) may still be sent.');
        $home     = e(url('/'));
        $homeCta  = translate('Return to homepage');

        // Self-contained, theme-independent landing page. Renders identically on
        // every install regardless of frontend theme choice. Previous version
        // relied on a per-theme Blade view that some installs did not have; that
        // caused the "View not found" 500. This page always works.
        $html = '<!DOCTYPE html><html lang="en"><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . e($title) . ' · ' . $siteName . '</title>'
            . '<style>'
            . '*{box-sizing:border-box}'
            . 'html,body{margin:0;padding:0;background:#f7f8fa;color:#0f172a;'
            . 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI Variable Text","Segoe UI",Roboto,system-ui,sans-serif;'
            . 'line-height:1.55;-webkit-font-smoothing:antialiased;min-height:100vh}'
            . '.wrap{max-width:520px;margin:0 auto;padding:80px 24px 48px;text-align:center}'
            . '.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;'
            . 'padding:48px 32px;box-shadow:0 1px 3px rgba(15,23,42,.04),0 12px 40px rgba(15,23,42,.04)}'
            . '.check{width:64px;height:64px;margin:0 auto 20px;border-radius:50%;'
            . 'background:#dcfce7;display:flex;align-items:center;justify-content:center}'
            . '.check svg{width:32px;height:32px;color:#16a34a}'
            . 'h1{margin:0 0 8px;font-size:24px;font-weight:700;letter-spacing:-.01em;color:#0f172a}'
            . '.sub{margin:0 0 20px;color:#334155;font-size:15px}'
            . '.note{margin:20px 0 0;padding:14px 16px;background:#f8fafc;border:1px solid #e2e8f0;'
            . 'border-radius:10px;color:#64748b;font-size:13px;text-align:left;line-height:1.5}'
            . '.cta{display:inline-block;margin-top:24px;padding:11px 22px;background:#0f172a;color:#fff;'
            . 'text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;transition:background .15s}'
            . '.cta:hover{background:#1e293b}'
            . '.brand{margin-top:32px;color:#94a3b8;font-size:12px;letter-spacing:.02em}'
            . '@media (prefers-color-scheme:dark){'
            . 'html,body{background:#0b1220;color:#e2e8f0}'
            . '.card{background:#111827;border-color:#1f2937;'
            . 'box-shadow:0 1px 3px rgba(0,0,0,.4),0 12px 40px rgba(0,0,0,.4)}'
            . 'h1{color:#f1f5f9}.sub{color:#cbd5e1}'
            . '.note{background:#0f172a;border-color:#1e293b;color:#94a3b8}'
            . '.cta{background:#e2e8f0;color:#0f172a}.cta:hover{background:#f1f5f9}'
            . '.brand{color:#475569}'
            . '.check{background:rgba(22,163,74,.15)}'
            . '}'
            . '</style>'
            . '</head><body>'
            . '<div class="wrap">'
            . '  <div class="card">'
            . '    <div class="check" aria-hidden="true">'
            . '      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'
            . '        <polyline points="20 6 9 17 4 12"></polyline>'
            . '      </svg>'
            . '    </div>'
            . '    <h1>' . e($headline) . '</h1>'
            . '    <p class="sub">' . $sub . '</p>'
            . '    <div class="note">' . e($note) . '</div>'
            . '    <a href="' . $home . '" class="cta">' . e($homeCta) . '</a>'
            . '  </div>'
            . '  <div class="brand">' . e(translate('Powered by')) . ' ' . $siteName . '</div>'
            . '</div>'
            . '</body></html>';

        return response($html, 200, [
            'Content-Type'  => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
