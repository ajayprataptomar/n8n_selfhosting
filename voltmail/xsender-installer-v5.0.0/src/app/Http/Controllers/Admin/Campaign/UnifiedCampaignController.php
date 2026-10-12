<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\CampaignType;
use App\Enums\Campaign\ChannelDetectionMode;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use App\Models\Gateway;
use App\Models\UnifiedCampaign;
use App\Services\Campaign\ChannelDetectionService;
use App\Services\Campaign\UnifiedCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnifiedCampaignController extends Controller
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
        $title = translate('Campaigns');

        $query = UnifiedCampaign::admin()
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

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $campaigns = $query->orderBy('created_at', 'desc')
            ->paginate(paginateNumber());

        // Statistics for the page
        $statistics = [
            'total' => UnifiedCampaign::admin()->count(),
            'draft' => UnifiedCampaign::admin()->draft()->count(),
            'running' => UnifiedCampaign::admin()->running()->count(),
            'completed' => UnifiedCampaign::admin()->completed()->count(),
        ];

        return view('admin.campaign.index', compact(
            'title',
            'campaigns',
            'statistics'
        ));
    }

    /**
     * Show create campaign form (wizard step 1)
     */
    public function create(): View
    {
        $title = translate('Create Campaign');

        $contactGroups = ContactGroup::admin()
            ->withCount('contacts')
            ->orderBy('name')
            ->get();

        $channels = CampaignChannel::options();
        $campaignTypes = CampaignType::cases();
        $detectionModes = ChannelDetectionMode::cases();

        return view('admin.campaign.create', compact(
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

        // Convert schedule_at from selected timezone to the app timezone
        // App uses config('app.timezone') for all now() comparisons
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
            ]);

            $notify[] = ['success', translate('Campaign created successfully')];
            return redirect()
                ->route('admin.campaign.messages', $campaign->uid)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * Show message configuration (wizard step 2)
     */
    public function messages(string $uid): View
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();
        $title = translate('Campaign Messages') . ' - ' . $campaign->name;

        // Get available gateways per channel (grouped by type)
        $gateways = [];
        $gatewaysGrouped = [];
        foreach ($campaign->channels as $channel) {
            $gateways[$channel] = $this->campaignService->getAvailableGateways($channel);
            $gatewaysGrouped[$channel] = $this->campaignService->getAvailableGatewaysGrouped($channel);
        }

        // Get channel distribution
        $channelDistribution = $this->channelDetection->getChannelDistribution(
            $campaign->contact_group_id
        );

        // Get templates per channel (exclude system/internal/global/default templates)
        // Show admin-created campaign templates for each channel
        $templates = [
            'sms' => \App\Models\Template::active()
                ->where('channel', \App\Enums\System\ChannelTypeEnum::SMS->value)
                ->whereNull('user_id')
                ->where(function($q) {
                    $q->where('global', \App\Enums\StatusEnum::FALSE->status())->orWhereNull('global');
                })
                ->where(function($q) {
                    $q->where('default', \App\Enums\StatusEnum::FALSE->status())->orWhereNull('default');
                })
                ->get(),
            'email' => \App\Models\Template::active()
                ->where('channel', \App\Enums\System\ChannelTypeEnum::EMAIL->value)
                ->whereNull('user_id')
                ->where(function($q) {
                    $q->where('global', \App\Enums\StatusEnum::FALSE->status())->orWhereNull('global');
                })
                ->where(function($q) {
                    $q->where('default', \App\Enums\StatusEnum::FALSE->status())->orWhereNull('default');
                })
                ->get(),
            'whatsapp' => \App\Models\Template::active()
                ->where('channel', \App\Enums\System\ChannelTypeEnum::WHATSAPP->value)
                ->whereNull('user_id')
                ->where(function($q) {
                    $q->where('global', \App\Enums\StatusEnum::FALSE->status())->orWhereNull('global');
                })
                ->where(function($q) {
                    $q->where('default', \App\Enums\StatusEnum::FALSE->status())->orWhereNull('default');
                })
                ->get(),
        ];

        return view('admin.campaign.messages', compact(
            'title',
            'campaign',
            'gateways',
            'gatewaysGrouped',
            'channelDistribution',
            'templates'
        ));
    }

    /**
     * Store/update campaign messages
     */
    public function storeMessages(Request $request, string $uid): RedirectResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

        if (!$campaign->canEdit()) {
            $notify[] = ['error', translate('Campaign cannot be edited')];
            return back()->withNotify($notify);
        }

        $messages = $request->input('messages', []);

        try {
            foreach ($campaign->channels as $channel) {
                if (!isset($messages[$channel])) {
                    continue;
                }

                $messageData = $messages[$channel];

                // Gateway field accepts either an integer (real gateway id) or
                // "android-{sessionId}" for Android Session pseudo-gateways (SMS only).
                $rawGatewayId = $messageData['gateway_id'] ?? null;
                [$gatewayId, $androidSessionId] = $this->splitGatewaySelection($rawGatewayId, $channel);

                $request->validate([
                    "messages.{$channel}.gateway_id" => [
                        'required',
                        function ($attribute, $value, $fail) use ($gatewayId, $androidSessionId, $channel) {
                            if ($gatewayId !== null) {
                                if (!\App\Models\Gateway::where('id', $gatewayId)->exists()) {
                                    $fail(translate('Selected gateway does not exist.'));
                                }
                            } elseif ($androidSessionId !== null) {
                                if ($channel !== 'sms') {
                                    $fail(translate('Android sessions are only valid for SMS messages.'));
                                    return;
                                }
                                if (!\App\Models\AndroidSession::where('id', $androidSessionId)->exists()) {
                                    $fail(translate('Selected Android session does not exist.'));
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
                ->route('admin.campaign.review', $campaign->uid)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * Show review page (wizard step 3)
     */
    public function review(string $uid): View
    {
        $campaign = UnifiedCampaign::where('uid', $uid)
            ->with(['contactGroup', 'messages.gateway'])
            ->firstOrFail();

        $title = translate('Review Campaign') . ' - ' . $campaign->name;

        // Validate campaign
        $validation = $this->campaignService->validate($campaign);

        // Get channel distribution
        $channelDistribution = $this->channelDetection->getChannelDistribution(
            $campaign->contact_group_id
        );

        return view('admin.campaign.review', compact(
            'title',
            'campaign',
            'validation',
            'channelDistribution'
        ));
    }

    /**
     * Launch the campaign
     */
    public function launch(string $uid): RedirectResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

        try {
            $this->campaignService->start($campaign);

            // For instant campaigns, trigger immediate processing
            $campaign->refresh();
            if ($campaign->status === \App\Enums\Campaign\UnifiedCampaignStatus::RUNNING) {
                \Illuminate\Support\Facades\Artisan::call('campaigns:process');
            }

            $notify[] = ['success', translate('Campaign launched successfully')];
            return redirect()
                ->route('admin.campaign.show', $campaign->uid)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Show campaign details
     */
    public function show(string $uid): View
    {
        $campaign = UnifiedCampaign::where('uid', $uid)
            ->with(['contactGroup', 'messages.gateway', 'dispatches'])
            ->firstOrFail();

        $title = $campaign->name;

        // Get statistics
        $statistics = $this->campaignService->getStatistics($campaign);

        return view('admin.campaign.show', compact(
            'title',
            'campaign',
            'statistics'
        ));
    }

    /**
     * Edit campaign
     */
    public function edit(string $uid)
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

        // Redirect back to the campaign detail with a friendly notice instead
        // of dropping the user on a blank framework 403 page. Completed /
        // running / cancelled campaigns are immutable by design — this is not
        // an error state, just a locked one, and the user needs a way back.
        if (!$campaign->canEdit()) {
            $notify[] = ['error', translate('This campaign cannot be edited — its current status is locked. Duplicate it to make changes.')];
            return redirect()->route('admin.campaign.show', $campaign->uid)->withNotify($notify);
        }

        $title = translate('Edit Campaign') . ' - ' . $campaign->name;

        $contactGroups = ContactGroup::admin()
            ->withCount('contacts')
            ->orderBy('name')
            ->get();

        $channels = CampaignChannel::options();
        $campaignTypes = CampaignType::cases();
        $detectionModes = ChannelDetectionMode::cases();

        return view('admin.campaign.edit', compact(
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
    public function update(Request $request, string $uid): RedirectResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

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

        // Convert schedule_at from selected timezone to the app timezone
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
                ->route('admin.campaign.show', $campaign->uid)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * Pause campaign
     */
    public function pause(string $uid): RedirectResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

        try {
            $this->campaignService->pause($campaign);

            $notify[] = ['success', translate('Campaign paused')];
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    /**
     * Resume campaign
     */
    public function resume(string $uid): RedirectResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

        try {
            $this->campaignService->resume($campaign);

            $notify[] = ['success', translate('Campaign resumed')];
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    /**
     * Cancel campaign
     */
    public function cancel(string $uid): RedirectResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

        try {
            $this->campaignService->cancel($campaign);

            $notify[] = ['success', translate('Campaign cancelled')];
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    /**
     * Duplicate campaign
     */
    public function duplicate(string $uid): RedirectResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

        try {
            $newCampaign = $this->campaignService->duplicate($campaign);

            $notify[] = ['success', translate('Campaign duplicated')];
            return redirect()
                ->route('admin.campaign.edit', $newCampaign->uid)
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete campaign
     */
    public function destroy(string $uid): RedirectResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

        try {
            $this->campaignService->delete($campaign);

            $notify[] = ['success', translate('Campaign deleted')];
            return redirect()
                ->route('admin.campaign.index')
                ->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Get channel distribution (AJAX)
     */
    public function getChannelDistribution(Request $request): JsonResponse
    {
        $groupId = $request->input('group_id');

        if (!$groupId) {
            return response()->json(['error' => 'Group ID required'], 400);
        }

        $distribution = $this->channelDetection->getChannelDistribution($groupId);

        return response()->json($distribution);
    }

    /**
     * Get campaign statistics (AJAX)
     */
    public function getStatistics(string $uid): JsonResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();
        $statistics = $this->campaignService->getStatistics($campaign);

        return response()->json($statistics);
    }

    /**
     * v4.8 — Streamed CSV of every dispatch row for a campaign. Uses
     * chunkById so 100k-row campaigns don't OOM the PHP worker.
     */
    public function exportDispatches(string $uid): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();
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
     * Get dispatches list (AJAX)
     */
    public function getDispatches(Request $request, string $uid): JsonResponse
    {
        $campaign = UnifiedCampaign::where('uid', $uid)->firstOrFail();

        $query = $campaign->dispatches()
            ->with(['contact', 'gateway']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        $dispatches = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json($dispatches);
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
