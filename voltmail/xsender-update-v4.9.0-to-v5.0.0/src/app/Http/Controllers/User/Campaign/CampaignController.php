<?php

namespace App\Http\Controllers\User\Campaign;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\CampaignType;
use App\Enums\Campaign\ChannelDetectionMode;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use App\Models\Template;
use App\Models\UnifiedCampaign;
use App\Services\Campaign\ChannelDetectionService;
use App\Services\Campaign\UnifiedCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    protected UnifiedCampaignService $campaignService;
    protected ChannelDetectionService $channelDetection;

    public function __construct(
        UnifiedCampaignService $campaignService,
        ChannelDetectionService $channelDetection
    ) {
        $this->campaignService = $campaignService;
        $this->channelDetection = $channelDetection;
    }

    /**
     * Display campaign list
     */
    public function index(Request $request): View
    {
        $title = translate('My Campaigns');
        $user = auth()->user();

        $query = UnifiedCampaign::where('user_id', $user->id)
            ->with(['contactGroup', 'messages'])
            ->withCount('dispatches');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('channel')) {
            $query->withChannel($request->channel);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $campaigns = $query->orderBy('created_at', 'desc')
            ->paginate(paginateNumber());

        // Statistics for the page
        $statistics = [
            'total' => UnifiedCampaign::where('user_id', $user->id)->count(),
            'draft' => UnifiedCampaign::where('user_id', $user->id)->draft()->count(),
            'running' => UnifiedCampaign::where('user_id', $user->id)->running()->count(),
            'completed' => UnifiedCampaign::where('user_id', $user->id)->completed()->count(),
        ];

        return view('user.campaign.index', compact(
            'title',
            'campaigns',
            'statistics'
        ));
    }

    /**
     * Show create campaign form
     */
    public function create(): View
    {
        $title = translate('Create Campaign');
        $user = auth()->user();

        $contactGroups = ContactGroup::where('user_id', $user->id)
            ->withCount('contacts')
            ->orderBy('name')
            ->get();

        $channels = CampaignChannel::options();
        $campaignTypes = CampaignType::cases();
        $detectionModes = ChannelDetectionMode::cases();

        return view('user.campaign.create', compact(
            'title',
            'contactGroups',
            'channels',
            'campaignTypes',
            'detectionModes'
        ));
    }

    /**
     * Store new campaign
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:instant,scheduled,recurring',
            'contact_group_id' => 'required|exists:contact_groups,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:sms,email,whatsapp',
            'channel_detection_mode' => 'required|in:auto,manual,priority_fallback',
            'schedule_at' => 'required_if:type,scheduled,recurring|nullable|date',
            'timezone' => 'nullable|string|max:50',
        ]);

        $user = auth()->user();

        // Verify contact group belongs to user
        $group = ContactGroup::where('id', $request->contact_group_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$group) {
            $notify[] = ['error', translate('Invalid contact group')];
            return back()->withNotify($notify)->withInput();
        }

        // Convert schedule_at from user's selected timezone to the app timezone
        // The app uses Asia/Dhaka (or whatever config('app.timezone') is set to),
        // so we must store in app timezone since all now() comparisons use it
        $scheduleAt = $request->input('schedule_at');
        if ($scheduleAt && $request->input('type') !== 'instant') {
            $userTimezone = $request->input('timezone', config('app.timezone'));
            try {
                $scheduleAt = \Carbon\Carbon::parse($scheduleAt, $userTimezone)
                    ->setTimezone(config('app.timezone'))
                    ->toDateTimeString();
            } catch (\Exception $e) {
                // If timezone parsing fails, use the raw value
            }
        }

        try {
            $campaign = $this->campaignService->create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'type' => $request->input('type'),
                'contact_group_id' => $request->input('contact_group_id'),
                'channels' => $request->input('channels'),
                'channel_detection_mode' => $request->input('channel_detection_mode'),
                'channel_priority' => $request->input('channel_priority'),
                'schedule_at' => $scheduleAt,
                'timezone' => $request->input('timezone', 'UTC'),
                'recurring_config' => $request->input('recurring_config'),
            ], $user->id);

            $notify[] = ['success', translate('Campaign created successfully')];
            return redirect()
                ->route('user.campaign.messages', $campaign->id)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * Show campaign details
     */
    public function show(int $id): View
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['contactGroup', 'messages.gateway', 'dispatches'])
            ->firstOrFail();

        $title = $campaign->name;

        // Get statistics
        $statistics = $this->campaignService->getStatistics($campaign);

        return view('user.campaign.show', compact(
            'title',
            'campaign',
            'statistics'
        ));
    }

    /**
     * Edit campaign
     */
    public function edit(int $id)
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Redirect back to detail with a friendly notice — see admin controller
        // for rationale. Completed / running / cancelled campaigns are locked
        // by design; the framework's default 403 leaves the user stranded on
        // a blank screen with no navigation.
        if (!$campaign->canEdit()) {
            $notify[] = ['error', translate('This campaign cannot be edited — its current status is locked. Duplicate it to make changes.')];
            return redirect()->route('user.campaign.show', $campaign->id)->withNotify($notify);
        }

        $title = translate('Edit Campaign') . ' - ' . $campaign->name;

        $contactGroups = ContactGroup::where('user_id', $user->id)
            ->withCount('contacts')
            ->orderBy('name')
            ->get();

        $channels = CampaignChannel::options();
        $campaignTypes = CampaignType::cases();
        $detectionModes = ChannelDetectionMode::cases();

        return view('user.campaign.edit', compact(
            'title',
            'campaign',
            'contactGroups',
            'channels',
            'campaignTypes',
            'detectionModes'
        ));
    }

    /**
     * Update campaign
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:instant,scheduled,recurring',
            'contact_group_id' => 'required|exists:contact_groups,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:sms,email,whatsapp',
            'channel_detection_mode' => 'required|in:auto,manual,priority_fallback',
            'schedule_at' => 'required_if:type,scheduled,recurring|nullable|date',
            'timezone' => 'nullable|string|max:50',
        ]);

        // Convert schedule_at from user's selected timezone to the app timezone
        $data = $request->all();
        if (!empty($data['schedule_at']) && ($data['type'] ?? '') !== 'instant') {
            $userTimezone = $data['timezone'] ?? config('app.timezone');
            try {
                $data['schedule_at'] = \Carbon\Carbon::parse($data['schedule_at'], $userTimezone)
                    ->setTimezone(config('app.timezone'))
                    ->toDateTimeString();
            } catch (\Exception $e) {
                // If timezone parsing fails, use the raw value
            }
        }

        try {
            $this->campaignService->update($campaign, $data);

            $notify[] = ['success', translate('Campaign updated successfully')];
            return redirect()
                ->route('user.campaign.messages', $campaign->id)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * Show message configuration (wizard step 2)
     */
    public function messages(int $id)
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['contactGroup', 'messages'])
            ->firstOrFail();

        if (!$campaign->canEdit()) {
            $notify[] = ['error', translate('This campaign cannot be edited — its current status is locked. Duplicate it to make changes.')];
            return redirect()->route('user.campaign.show', $campaign->id)->withNotify($notify);
        }

        $title = translate('Campaign Messages') . ' - ' . $campaign->name;

        // Get plan access to determine gateway visibility
        $planAccess = planAccess($user);
        $planType = $planAccess['type'] ?? null;

        // Get available gateways per channel respecting plan access
        $gateways = [];
        $gatewaysGrouped = [];
        foreach ($campaign->channels as $channel) {
            $gateways[$channel] = $this->campaignService->getAvailableGateways($channel, $user->id, $planType);
            $gatewaysGrouped[$channel] = $this->campaignService->getAvailableGatewaysGrouped($channel, $user->id, $planType);
        }

        // Get channel distribution (with fallback)
        try {
            $channelDistribution = $this->channelDetection->getChannelDistribution(
                $campaign->contact_group_id,
                $user->id
            );
        } catch (\Exception $e) {
            $channelDistribution = [
                'total' => 0,
                'channels' => ['sms' => 0, 'email' => 0, 'whatsapp' => 0],
                'multi_channel' => 0,
            ];
        }

        // Get templates per channel (user-created only)
        $templates = [];
        foreach (['sms', 'email', 'whatsapp'] as $ch) {
            $channelType = match ($ch) {
                'sms' => \App\Enums\System\ChannelTypeEnum::SMS->value,
                'email' => \App\Enums\System\ChannelTypeEnum::EMAIL->value,
                'whatsapp' => \App\Enums\System\ChannelTypeEnum::WHATSAPP->value,
            };
            $templates[$ch] = Template::active()
                ->where('channel', $channelType)
                ->where('user_id', $user->id)
                ->get();
        }

        return view('user.campaign.messages', compact(
            'title',
            'campaign',
            'gateways',
            'gatewaysGrouped',
            'channelDistribution',
            'templates',
            'planType'
        ));
    }

    /**
     * Store/update campaign messages
     */
    public function storeMessages(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!$campaign->canEdit()) {
            $notify[] = ['error', translate('Campaign cannot be edited')];
            return back()->withNotify($notify);
        }

        // Determine plan access type
        $planAccess = planAccess($user);
        $planType = $planAccess['type'] ?? null;
        $isAdminPlan = ($planType == \App\Enums\StatusEnum::TRUE->status());

        $messages = $request->input('messages', []);

        try {
            foreach ($campaign->channels as $channel) {
                if (!isset($messages[$channel])) {
                    continue;
                }

                $messageData = $messages[$channel];

                // Gateway field accepts either an integer (real gateway id) or
                // "android-{sessionId}" pseudo-gateway for SMS channel.
                $rawGatewayId = $messageData['gateway_id'] ?? null;
                [$gatewayId, $androidSessionId] = $this->splitGatewaySelection($rawGatewayId, $channel);
                $isAdminPlanNonWhatsapp = $isAdminPlan && $channel !== \App\Enums\Campaign\CampaignChannel::WHATSAPP->value;
                $allowEmpty = $isAdminPlan; // admin-plan auto-assigns at launch

                $request->validate([
                    "messages.{$channel}.gateway_id" => [
                        $allowEmpty ? 'nullable' : 'required',
                        function ($attribute, $value, $fail) use ($gatewayId, $androidSessionId, $channel, $user) {
                            if ($value === null || $value === '') return;
                            if ($gatewayId !== null) {
                                if (!\App\Models\Gateway::where('id', $gatewayId)->exists()) {
                                    $fail(translate('Selected gateway does not exist.'));
                                }
                            } elseif ($androidSessionId !== null) {
                                if ($channel !== 'sms') {
                                    $fail(translate('Android sessions are only valid for SMS messages.'));
                                    return;
                                }
                                $session = \App\Models\AndroidSession::where('id', $androidSessionId)
                                    ->where('user_id', $user->id)
                                    ->first();
                                if (!$session) {
                                    $fail(translate('Selected Android session does not exist or does not belong to you.'));
                                }
                            } else {
                                $fail(translate('Invalid gateway selection.'));
                            }
                        },
                    ],
                    "messages.{$channel}.content" => 'required|string',
                    "messages.{$channel}.subject" => $channel === 'email' ? 'required|string|max:500' : 'nullable',
                ]);

                $this->campaignService->updateOrCreateMessage($campaign, $channel, [
                    'gateway_id' => $gatewayId,
                    'android_session_id' => $androidSessionId,
                    'content' => $messageData['content'],
                    'subject' => $messageData['subject'] ?? null,
                    'from_name' => $messageData['from_name'] ?? null,
                    'reply_to' => $messageData['reply_to'] ?? null,
                    'template_id' => $messageData['template_id'] ?? null,
                    'attachments' => $messageData['attachments'] ?? null,
                ]);
            }

            $notify[] = ['success', translate('Messages saved successfully')];
            return redirect()
                ->route('user.campaign.review', $campaign->id)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * Show review page (wizard step 3)
     */
    public function review(int $id): View
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['contactGroup', 'messages.gateway'])
            ->firstOrFail();

        $title = translate('Review Campaign') . ' - ' . $campaign->name;

        // Get plan access type
        $planAccess = planAccess($user);
        $planType = $planAccess['type'] ?? null;

        // Validate campaign
        $validation = $this->campaignService->validate($campaign);

        // Get channel distribution (with fallback)
        try {
            $channelDistribution = $this->channelDetection->getChannelDistribution(
                $campaign->contact_group_id,
                $user->id
            );
        } catch (\Exception $e) {
            $channelDistribution = [
                'total' => 0,
                'channels' => [
                    'sms' => 0,
                    'email' => 0,
                    'whatsapp' => 0,
                ],
                'multi_channel' => 0,
            ];
        }

        return view('user.campaign.review', compact(
            'title',
            'campaign',
            'validation',
            'channelDistribution',
            'planType'
        ));
    }

    /**
     * Launch the campaign
     */
    public function launch(int $id): RedirectResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $this->campaignService->start($campaign);

            // For instant campaigns, trigger immediate processing
            // so dispatches start sending without waiting for the next cron cycle
            $campaign->refresh();
            if ($campaign->status === \App\Enums\Campaign\UnifiedCampaignStatus::RUNNING) {
                \Illuminate\Support\Facades\Artisan::call('campaigns:process');
            }

            $notify[] = ['success', translate('Campaign launched successfully')];
            return redirect()
                ->route('user.campaign.show', $campaign->id)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
            return back()->withNotify($notify);
        }
    }

    /**
     * Start campaign
     */
    public function start(int $id): RedirectResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $this->campaignService->start($campaign);
            $notify[] = ['success', translate('Campaign started')];
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
        }

        return back()->withNotify($notify);
    }

    /**
     * Pause campaign
     */
    public function pause(int $id): RedirectResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $this->campaignService->pause($campaign);
            $notify[] = ['success', translate('Campaign paused')];
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
        }

        return back()->withNotify($notify);
    }

    /**
     * Resume campaign
     */
    public function resume(int $id): RedirectResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $this->campaignService->resume($campaign);
            $notify[] = ['success', translate('Campaign resumed')];
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
        }

        return back()->withNotify($notify);
    }

    /**
     * Duplicate campaign
     */
    public function duplicate(int $id): RedirectResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $newCampaign = $this->campaignService->duplicate($campaign);
            $notify[] = ['success', translate('Campaign duplicated')];
            return redirect()
                ->route('user.campaign.edit', $newCampaign->id)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
            return back()->withNotify($notify);
        }
    }

    /**
     * Cancel campaign
     */
    public function cancel(int $id): RedirectResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $this->campaignService->cancel($campaign);
            $notify[] = ['success', translate('Campaign cancelled')];
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
        }

        return back()->withNotify($notify);
    }

    /**
     * Delete campaign
     */
    public function destroy(int $id): RedirectResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $this->campaignService->delete($campaign);
            $notify[] = ['success', translate('Campaign deleted')];
            return redirect()
                ->route('user.campaign.index')
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e)];
            return back()->withNotify($notify);
        }
    }

    /**
     * v4.8 — Per-recipient dispatch list (AJAX). Same shape as admin
     * `UnifiedCampaignController::getDispatches` but scoped to the current user
     * so people can only see their own campaigns' rows.
     */
    public function getDispatches(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $query = $campaign->dispatches()->with(['contact', 'gateway']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        $dispatches = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->input('per_page', 50), 200));

        return response()->json($dispatches);
    }

    /**
     * v4.8 — Streamed CSV of every dispatch for a campaign. No pagination —
     * this is the "give me the full delivery report" download. Uses a streamed
     * response so 100k-row campaigns don't OOM the PHP worker.
     */
    public function exportDispatches(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = auth()->user();
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $filename = 'campaign-' . $campaign->id . '-dispatches-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(function () use ($campaign) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['contact_name', 'contact_address', 'channel', 'status', 'sent_at', 'delivered_at', 'error_message']);

            $campaign->dispatches()
                ->with('contact')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($out) {
                    foreach ($rows as $d) {
                        $address = method_exists($d, 'getContactAddress') ? $d->getContactAddress() : ($d->contact?->email_contact ?? $d->contact?->whatsapp_contact ?? $d->contact?->sms_contact ?? '');
                        $channel = is_object($d->channel) ? $d->channel->value : (string) $d->channel;
                        $status  = is_object($d->status) ? $d->status->value : (string) $d->status;
                        fputcsv($out, [
                            trim(($d->contact?->first_name ?? '') . ' ' . ($d->contact?->last_name ?? '')),
                            $address,
                            $channel,
                            $status,
                            $d->sent_at?->toDateTimeString(),
                            $d->delivered_at?->toDateTimeString(),
                            $d->error_message,
                        ]);
                    }
                });
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /**
     * Get channel distribution for a contact group (AJAX)
     */
    public function getChannelDistribution(Request $request): JsonResponse
    {
        $user = auth()->user();
        $groupId = $request->input('group_id');

        if (!$groupId) {
            return response()->json(['error' => 'Group ID required'], 400);
        }

        // Verify group belongs to user
        $group = ContactGroup::where('id', $groupId)
            ->where('user_id', $user->id)
            ->first();

        if (!$group) {
            return response()->json(['error' => 'Invalid group'], 403);
        }

        try {
            $distribution = $this->channelDetection->getChannelDistribution($groupId, $user->id);
            return response()->json($distribution);
        } catch (\Exception $e) {
            return response()->json([
                'total' => 0,
                'channels' => ['sms' => 0, 'email' => 0, 'whatsapp' => 0],
                'multi_channel' => 0,
            ]);
        }
    }

    /**
     * Split a gateway selection string into [gatewayId, androidSessionId].
     *
     *  "42"            → [42, null]
     *  "android-5"     → [null, 5]
     *  ""/null/invalid → [null, null]   (caller validates)
     */
    protected function splitGatewaySelection($value, string $channel): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        if (is_string($value) && str_starts_with($value, 'android-')) {
            $sessionId = (int) substr($value, 8);
            return $sessionId > 0 ? [null, $sessionId] : [null, null];
        }

        if (is_numeric($value)) {
            return [(int) $value, null];
        }

        return [null, null];
    }
}
