<?php

namespace App\Services\Campaign;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\CampaignType;
use App\Enums\Campaign\ChannelDetectionMode;
use App\Enums\Campaign\DispatchStatus;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Models\CampaignDispatch;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Gateway;
use App\Models\UnifiedCampaign;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnifiedCampaignService
{
    protected ChannelDetectionService $channelDetection;

    public function __construct(ChannelDetectionService $channelDetection)
    {
        $this->channelDetection = $channelDetection;
    }

    /**
     * Create a new unified campaign
     */
    public function create(array $data, ?int $userId = null): UnifiedCampaign
    {
        return DB::transaction(function () use ($data, $userId) {
            // Create the campaign
            $campaign = UnifiedCampaign::create([
                'user_id' => $userId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => UnifiedCampaignStatus::DRAFT,
                'type' => $data['type'] ?? CampaignType::INSTANT,
                'schedule_at' => $data['schedule_at'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
                'recurring_config' => $data['recurring_config'] ?? null,
                'contact_group_id' => $data['contact_group_id'],
                'contact_filter' => $data['contact_filter'] ?? null,
                'channels' => $data['channels'],
                'channel_priority' => $data['channel_priority'] ?? null,
                'channel_detection_mode' => $data['channel_detection_mode'] ?? ChannelDetectionMode::AUTO,
                'meta_data' => $data['meta_data'] ?? null,
            ]);

            // Create messages for each channel
            if (!empty($data['messages'])) {
                foreach ($data['messages'] as $channel => $messageData) {
                    if (in_array($channel, $data['channels'])) {
                        $this->createMessage($campaign, $channel, $messageData);
                    }
                }
            }

            // Calculate total contacts
            $this->updateTotalContacts($campaign);

            return $campaign;
        });
    }

    /**
     * Update a campaign
     */
    public function update(UnifiedCampaign $campaign, array $data): UnifiedCampaign
    {
        if (!$campaign->canEdit()) {
            throw new \Exception(translate('Campaign cannot be edited in current status'));
        }

        return DB::transaction(function () use ($campaign, $data) {
            // If campaign was SCHEDULED, revert to DRAFT and clean up dispatches
            // They will be recreated when the campaign is launched again
            $wasScheduled = $campaign->status === UnifiedCampaignStatus::SCHEDULED;

            $updateData = [
                'name' => $data['name'] ?? $campaign->name,
                'description' => $data['description'] ?? $campaign->description,
                'type' => $data['type'] ?? $campaign->type,
                'schedule_at' => $data['schedule_at'] ?? $campaign->schedule_at,
                'timezone' => $data['timezone'] ?? $campaign->timezone,
                'recurring_config' => $data['recurring_config'] ?? $campaign->recurring_config,
                'contact_group_id' => $data['contact_group_id'] ?? $campaign->contact_group_id,
                'contact_filter' => $data['contact_filter'] ?? $campaign->contact_filter,
                'channels' => $data['channels'] ?? $campaign->channels,
                'channel_priority' => $data['channel_priority'] ?? $campaign->channel_priority,
                'channel_detection_mode' => $data['channel_detection_mode'] ?? $campaign->channel_detection_mode,
                'meta_data' => $data['meta_data'] ?? $campaign->meta_data,
            ];

            if ($wasScheduled) {
                $updateData['status'] = UnifiedCampaignStatus::DRAFT;
                $updateData['started_at'] = null;

                // Remove existing dispatches — they'll be recreated on next launch
                $campaign->dispatches()->delete();
            }

            $campaign->update($updateData);

            // Update messages if provided
            if (!empty($data['messages'])) {
                foreach ($data['messages'] as $channel => $messageData) {
                    $this->updateOrCreateMessage($campaign, $channel, $messageData);
                }
            }

            // Recalculate total contacts if group changed
            if (isset($data['contact_group_id'])) {
                $this->updateTotalContacts($campaign);
            }

            return $campaign->fresh();
        });
    }

    /**
     * Create a campaign message
     */
    public function createMessage(UnifiedCampaign $campaign, string $channel, array $data): CampaignMessage
    {
        return CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'channel' => $channel,
            'gateway_id' => $data['gateway_id'] ?? null,
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'],
            'template_id' => $data['template_id'] ?? null,
            'attachments' => $data['attachments'] ?? null,
            'personalization_vars' => $data['personalization_vars'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'meta_data' => $data['meta_data'] ?? null,
        ]);
    }

    /**
     * Update or create a campaign message
     */
    public function updateOrCreateMessage(UnifiedCampaign $campaign, string $channel, array $data): CampaignMessage
    {
        return CampaignMessage::updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'channel' => $channel,
            ],
            [
                'gateway_id' => $data['gateway_id'] ?? null,
                'android_session_id' => $data['android_session_id'] ?? null,
                'subject' => $data['subject'] ?? null,
                'from_name' => $data['from_name'] ?? null,
                'reply_to' => $data['reply_to'] ?? null,
                'content' => $data['content'],
                'template_id' => $data['template_id'] ?? null,
                'attachments' => $data['attachments'] ?? null,
                'personalization_vars' => $data['personalization_vars'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'meta_data' => $data['meta_data'] ?? null,
            ]
        );
    }

    /**
     * Update total contacts count
     */
    public function updateTotalContacts(UnifiedCampaign $campaign): void
    {
        if (!$campaign->contact_group_id) {
            return;
        }

        $query = Contact::where('group_id', $campaign->contact_group_id)
            ->active();

        if ($campaign->user_id) {
            $query->where('user_id', $campaign->user_id);
        } else {
            $query->admin();
        }

        // Apply contact filters if any
        if (!empty($campaign->contact_filter)) {
            $query = $this->applyContactFilters($query, $campaign->contact_filter);
        }

        $campaign->update(['total_contacts' => $query->count()]);
    }

    /**
     * Apply contact filters to query
     */
    protected function applyContactFilters($query, array $filters)
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;

            if ($field && $value !== null) {
                $query->where($field, $operator, $value);
            }
        }

        return $query;
    }

    /**
     * Start a campaign
     */
    public function start(UnifiedCampaign $campaign): bool
    {
        if (!$campaign->canStart()) {
            throw new \Exception(translate('Campaign cannot be started'));
        }

        // Validate messages exist
        if ($campaign->messages()->count() === 0) {
            throw new \Exception(translate('Campaign has no messages configured'));
        }

        // Determine plan access type for gateway handling
        $planType = null;
        if ($campaign->user_id) {
            $user = User::find($campaign->user_id);
            if ($user) {
                $planAccess = planAccess($user);
                $planType = $planAccess['type'] ?? null;
            }
        }

        // Auto-assign admin gateways for channels that don't have one
        // (when plan type is admin, user doesn't select gateways for SMS/Email/WhatsApp Cloud)
        foreach ($campaign->channels as $channel) {
            $message = $campaign->getMessageForChannel($channel);
            // Android sessions are an alternative sender for SMS — skip the gateway auto-assign branch
            if ($message && !$message->gateway_id && !$message->android_session_id) {
                if ($planType == \App\Enums\StatusEnum::TRUE->status()) {
                    // Admin plan: auto-assign a random admin gateway
                    $adminGateway = $this->getRandomAdminGateway($channel);
                    if ($adminGateway) {
                        $message->update(['gateway_id' => $adminGateway->id]);
                    } else {
                        throw new \Exception(translate('No admin gateway available for') . ' ' . $channel);
                    }
                } else {
                    throw new \Exception(translate('No gateway configured for') . ' ' . $channel);
                }
            }
        }

        DB::transaction(function () use ($campaign) {
            // Create dispatches for all contacts
            $this->createDispatches($campaign);

            // Update campaign status
            if ($campaign->type === CampaignType::INSTANT) {
                $campaign->markAsStarted();
            } else {
                $campaign->update(['status' => UnifiedCampaignStatus::SCHEDULED]);
            }
        });

        return true;
    }

    /**
     * Create dispatch records for all contacts
     */
    public function createDispatches(UnifiedCampaign $campaign): int
    {
        $contacts = $this->getEligibleContacts($campaign);
        $count = 0;

        foreach ($contacts as $contact) {
            // Determine which channels to use for this contact
            $channels = $campaign->channel_detection_mode === ChannelDetectionMode::AUTO
                ? [$this->channelDetection->determineChannelForContact($contact, $campaign)]
                : $this->channelDetection->determineAllChannelsForContact($contact, $campaign);

            foreach ($channels as $channel) {
                if (!$channel) {
                    continue;
                }

                $message = $campaign->getMessageForChannel($channel);
                if (!$message || !$message->is_active) {
                    continue;
                }

                $androidSimId = null;
                if ($message->android_session_id) {
                    // Pick a connected, send-enabled SIM from this session.
                    // Round-robin by selecting the SIM with the oldest last-used timestamp
                    // (using id ASC + updated_at as a tiebreaker keeps load even).
                    $sim = \App\Models\AndroidSim::where('android_session_id', $message->android_session_id)
                        ->where('status', \App\Enums\Common\Status::ACTIVE->value)
                        ->where('send_sms', true)
                        ->orderBy('updated_at', 'asc')
                        ->first();

                    if ($sim) {
                        $androidSimId = $sim->id;
                        // touch so the next dispatch gets a different SIM (round-robin)
                        $sim->touch();
                    }
                }

                // firstOrCreate guards against duplicate dispatches per contact.
                // Without this, a double-click on Launch, a retried queue job, or
                // any code path that re-invokes start() would create N × contacts
                // rows and the campaign's Sent count would jump 200%+. The DB
                // migration also adds a matching UNIQUE index as a hard backstop
                // (create_campaign_dispatches_unique_index.php).
                $dispatch = CampaignDispatch::firstOrCreate(
                    [
                        'campaign_id'         => $campaign->id,
                        'contact_id'          => $contact->id,
                        'channel'             => $channel,
                        'campaign_message_id' => $message->id,
                    ],
                    [
                        'gateway_id'     => $message->gateway_id,
                        'android_sim_id' => $androidSimId,
                        'status'         => DispatchStatus::PENDING,
                        'scheduled_at'   => $campaign->schedule_at,
                    ]
                );

                if ($dispatch->wasRecentlyCreated) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Get eligible contacts for a campaign
     */
    public function getEligibleContacts(UnifiedCampaign $campaign): Collection
    {
        $query = Contact::where('group_id', $campaign->contact_group_id)
            ->active();

        if ($campaign->user_id) {
            $query->where('user_id', $campaign->user_id);
        } else {
            $query->admin();
        }

        // Apply contact filters
        if (!empty($campaign->contact_filter)) {
            $query = $this->applyContactFilters($query, $campaign->contact_filter);
        }

        return $query->get();
    }

    /**
     * Pause a campaign
     */
    public function pause(UnifiedCampaign $campaign): bool
    {
        if (!$campaign->canPause()) {
            throw new \Exception(translate('Campaign cannot be paused'));
        }

        $campaign->markAsPaused();

        // Mark pending dispatches as paused
        $campaign->dispatches()
            ->whereIn('status', [DispatchStatus::PENDING, DispatchStatus::QUEUED])
            ->update(['meta_data->paused_at' => now()]);

        return true;
    }

    /**
     * Resume a paused campaign
     */
    public function resume(UnifiedCampaign $campaign): bool
    {
        if ($campaign->status !== UnifiedCampaignStatus::PAUSED) {
            throw new \Exception(translate('Campaign is not paused'));
        }

        $campaign->markAsStarted();

        return true;
    }

    /**
     * Cancel a campaign
     */
    public function cancel(UnifiedCampaign $campaign): bool
    {
        if (!$campaign->canCancel()) {
            throw new \Exception(translate('Campaign cannot be cancelled'));
        }

        DB::transaction(function () use ($campaign) {
            $campaign->markAsCancelled();

            // Cancel pending dispatches
            $campaign->dispatches()
                ->whereIn('status', [DispatchStatus::PENDING, DispatchStatus::QUEUED])
                ->update(['status' => DispatchStatus::FAILED, 'error_message' => 'Campaign cancelled']);
        });

        return true;
    }

    /**
     * Duplicate a campaign
     */
    public function duplicate(UnifiedCampaign $campaign, ?string $newName = null): UnifiedCampaign
    {
        return DB::transaction(function () use ($campaign, $newName) {
            $newCampaign = $campaign->replicate();
            $newCampaign->uid = str_unique();
            $newCampaign->name = $newName ?? $campaign->name . ' (' . translate('Copy') . ')';
            $newCampaign->status = UnifiedCampaignStatus::DRAFT;
            $newCampaign->schedule_at = null;
            $newCampaign->started_at = null;
            $newCampaign->completed_at = null;
            $newCampaign->processed_contacts = 0;
            $newCampaign->stats = null;
            $newCampaign->save();

            // Duplicate messages
            foreach ($campaign->messages as $message) {
                $newMessage = $message->replicate();
                $newMessage->uid = str_unique();
                $newMessage->campaign_id = $newCampaign->id;
                $newMessage->save();
            }

            return $newCampaign;
        });
    }

    /**
     * Delete a campaign
     */
    public function delete(UnifiedCampaign $campaign): bool
    {
        if ($campaign->status === UnifiedCampaignStatus::RUNNING) {
            throw new \Exception(translate('Cannot delete a running campaign'));
        }

        return $campaign->delete();
    }

    /**
     * Get campaign statistics
     *
     * Counts are CUMULATIVE, not literal-status buckets. A dispatch that
     * upgrades from SENT → OPENED → CLICKED still counts as sent AND delivered
     * — we track the highest status a dispatch has reached, not its current
     * label. Previously used `->sent()` / `->delivered()` scopes which match
     * `status = SENT` exactly, so as opens/clicks came in the Sent count
     * DECREASED. This aggregates once by status and derives buckets from that.
     */
    public function getStatistics(UnifiedCampaign $campaign): array
    {
        $counts = $campaign->dispatches()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $get = fn (string $status): int => (int) ($counts[$status] ?? 0);

        $pending    = $get('pending');
        $queued     = $get('queued');
        $processing = $get('processing');
        $sent       = $get('sent');
        $delivered  = $get('delivered');
        $opened     = $get('opened');
        $clicked    = $get('clicked');
        $replied    = $get('replied');
        $bounced    = $get('bounced');
        $failed     = $get('failed');

        // A higher status implies every lower one has been passed.
        $postSend = $sent + $delivered + $opened + $clicked + $replied;

        // Count unsubscribes for this campaign
        $unsubscribedCount = 0;
        try {
            $unsubscribedCount = \App\Models\CampaignUnsubscribe::where('campaign_id', $campaign->id)->count();
        } catch (\Exception $e) {
            // Table may not exist on older installations
        }

        return [
            'total'        => (int) $counts->sum(),
            'pending'      => $pending,
            'queued'       => $queued,
            'processing'   => $processing,
            // Anything that left the system — bounced was sent then rejected.
            'sent'         => $postSend + $bounced,
            // Email has no SMTP delivery receipt, so a successful send counts as delivered.
            'delivered'    => $postSend,
            'failed'       => $failed,
            'opened'       => $opened + $clicked + $replied,
            'clicked'      => $clicked + $replied,
            'replied'      => $replied,
            'bounced'      => $bounced,
            'unsubscribed' => $unsubscribedCount,
            'by_channel'   => $this->getStatisticsByChannel($campaign),
        ];
    }

    /**
     * Get statistics by channel (cumulative — same rules as getStatistics)
     */
    protected function getStatisticsByChannel(UnifiedCampaign $campaign): array
    {
        $rows = $campaign->dispatches()
            ->selectRaw('channel, status, COUNT(*) as aggregate')
            ->groupBy('channel', 'status')
            ->get();

        $stats = [];
        foreach ($campaign->channels as $channel) {
            $stats[$channel] = [
                'total'     => 0,
                'sent'      => 0,
                'delivered' => 0,
                'failed'    => 0,
                'opened'    => 0,
                'clicked'   => 0,
            ];
        }

        foreach ($rows as $row) {
            $channel = is_object($row->channel) ? $row->channel->value : (string) $row->channel;
            $status  = is_object($row->status) ? $row->status->value : (string) $row->status;
            $count   = (int) $row->aggregate;

            if (!isset($stats[$channel])) {
                $stats[$channel] = ['total' => 0, 'sent' => 0, 'delivered' => 0, 'failed' => 0, 'opened' => 0, 'clicked' => 0];
            }

            $stats[$channel]['total'] += $count;

            if (in_array($status, ['sent', 'delivered', 'opened', 'clicked', 'replied'], true)) {
                $stats[$channel]['sent']      += $count;
                $stats[$channel]['delivered'] += $count;
            }
            if ($status === 'bounced') {
                $stats[$channel]['sent']   += $count;
                $stats[$channel]['failed'] += $count;
            }
            if (in_array($status, ['opened', 'clicked', 'replied'], true)) {
                $stats[$channel]['opened'] += $count;
            }
            if (in_array($status, ['clicked', 'replied'], true)) {
                $stats[$channel]['clicked'] += $count;
            }
            if ($status === 'failed') {
                $stats[$channel]['failed'] += $count;
            }
        }

        return $stats;
    }

    /**
     * Get available gateways for a channel respecting plan access type
     *
     * @param string $channel Campaign channel (sms, email, whatsapp)
     * @param int|null $userId User ID
     * @param string|null $planType Plan access type: '1' = admin gateways, '0' = user gateways
     */
    public function getAvailableGateways(string $channel, ?int $userId = null, ?string $planType = null): Collection
    {
        $channelType = match ($channel) {
            CampaignChannel::SMS->value => 'sms',
            CampaignChannel::EMAIL->value => 'email',
            CampaignChannel::WHATSAPP->value => 'whatsapp',
            default => null,
        };

        if (!$channelType) {
            return collect();
        }

        $query = Gateway::active()
            ->where('channel', $channelType);

        if ($userId !== null && $planType !== null) {
            if ($planType == \App\Enums\StatusEnum::TRUE->status()) {
                // Admin plan: For WhatsApp, only show user's Node/QR devices
                // For SMS/Email, no selection needed (auto-assign admin gateway)
                if ($channel === CampaignChannel::WHATSAPP->value) {
                    $query->where('user_id', $userId)->where('type', 'node');
                } else {
                    // Return empty - admin gateways will be auto-assigned at launch
                    return collect();
                }
            } else {
                // User plan: only show user's own gateways
                $query->where('user_id', $userId);
            }
        } elseif ($userId !== null) {
            // No plan type specified, show user's gateways only (safe default)
            $query->where('user_id', $userId);
        } else {
            // Admin panel: show admin gateways
            $query->whereNull('user_id');
        }

        $gateways = $query->get();

        // For SMS channel: also surface Android Sessions as pseudo-Gateway entries
        // so users can pick an Android device in the unified campaign builder.
        // The id is prefixed `android-{sessionId}` so the controller can split it
        // back into `android_session_id` at save time without colliding with real gateway IDs.
        if ($channel === CampaignChannel::SMS->value) {
            $gateways = $gateways->concat($this->getAvailableAndroidSessions($userId, $planType));
        }

        return $gateways;
    }

    /**
     * Return connected Android Sessions as pseudo-Gateway models with id "android-{N}".
     * Skips admin-plan users for SMS (admin auto-assigns gateways).
     */
    protected function getAvailableAndroidSessions(?int $userId, ?string $planType): Collection
    {
        // Admin-plan SMS already short-circuited above (returns empty for the API query) — match here.
        if ($userId !== null && $planType !== null
            && $planType == \App\Enums\StatusEnum::TRUE->status()) {
            return collect();
        }

        $sessionQuery = \App\Models\AndroidSession::query()
            ->where('status', \App\Enums\System\SessionStatusEnum::CONNECTED);

        if ($userId !== null) {
            $sessionQuery->where('user_id', $userId);
        } else {
            $sessionQuery->whereNull('user_id');
        }

        return $sessionQuery->get()->map(function ($session) {
            $g = new Gateway();
            $g->exists = false; // mark as non-persistent so save() never targets it
            $g->id = 'android-' . $session->id;
            $g->name = $session->name;
            $g->channel = 'sms';
            $g->type = 'android';
            $g->user_id = $session->user_id;
            $g->status = \App\Enums\Common\Status::ACTIVE;
            return $g;
        });
    }

    /**
     * Get a random admin gateway for a channel (for auto-assignment)
     */
    public function getRandomAdminGateway(string $channel): ?Gateway
    {
        $channelType = match ($channel) {
            CampaignChannel::SMS->value => 'sms',
            CampaignChannel::EMAIL->value => 'email',
            CampaignChannel::WHATSAPP->value => 'whatsapp',
            default => null,
        };

        if (!$channelType) {
            return null;
        }

        return Gateway::active()
            ->where('channel', $channelType)
            ->whereNull('user_id')
            ->inRandomOrder()
            ->first();
    }

    /**
     * Get available gateways for a channel grouped by type
     * Returns array with gateway types as keys
     */
    public function getAvailableGatewaysGrouped(string $channel, ?int $userId = null, ?string $planType = null): array
    {
        $gateways = $this->getAvailableGateways($channel, $userId, $planType);

        if ($gateways->isEmpty()) {
            return [];
        }

        $grouped = [];

        foreach ($gateways as $gateway) {
            $type = $this->getGatewayTypeLabel($channel, $gateway->type);
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $gateway;
        }

        return $grouped;
    }

    /**
     * Get human-readable gateway type label
     */
    protected function getGatewayTypeLabel(string $channel, ?string $type): string
    {
        if ($channel === CampaignChannel::SMS->value) {
            return match ($type) {
                'android' => translate('Android Devices'),
                'api' => translate('SMS API Providers'),
                default => translate('Other'),
            };
        }

        if ($channel === CampaignChannel::WHATSAPP->value) {
            return match ($type) {
                'cloud' => translate('Meta Cloud API'),
                'node' => translate('WhatsApp Devices (Node)'),
                default => translate('Other'),
            };
        }

        if ($channel === CampaignChannel::EMAIL->value) {
            return match ($type) {
                'smtp' => translate('SMTP'),
                'api' => translate('Email API'),
                default => ucfirst($type ?? translate('Default')),
            };
        }

        return ucfirst($type ?? translate('Default'));
    }

    /**
     * Validate campaign before starting
     */
    public function validate(UnifiedCampaign $campaign): array
    {
        $errors = [];
        $warnings = [];

        // Determine plan access type
        $planType = null;
        if ($campaign->user_id) {
            $user = User::find($campaign->user_id);
            if ($user) {
                $planAccess = planAccess($user);
                $planType = $planAccess['type'] ?? null;
            }
        }

        $isAdminPlan = ($planType == \App\Enums\StatusEnum::TRUE->status());

        // Check contact group
        if (!$campaign->contact_group_id) {
            $errors[] = translate('No contact group selected');
        } else {
            $group = ContactGroup::find($campaign->contact_group_id);
            if (!$group) {
                $errors[] = translate('Contact group not found');
            }
        }

        // Check channels
        if (empty($campaign->channels)) {
            $errors[] = translate('No channels selected');
        }

        // Check messages
        foreach ($campaign->channels as $channel) {
            $message = $campaign->getMessageForChannel($channel);

            if (!$message) {
                $errors[] = translate('No message configured for') . ' ' . $channel;
            } elseif (empty($message->content)) {
                $errors[] = translate('Empty content for') . ' ' . $channel . ' ' . translate('message');
            } elseif (!$message->gateway_id && !$message->android_session_id) {
                // Skip gateway check for admin plan on SMS/Email/WhatsApp Cloud
                // (admin gateways will be auto-assigned at launch)
                if ($isAdminPlan) {
                    // For WhatsApp node type, gateway is still required
                    if ($channel === CampaignChannel::WHATSAPP->value) {
                        // Check if message has a node gateway or if it's cloud (auto-assign)
                        $warnings[] = translate('Admin gateway will be auto-assigned for') . ' ' . $channel;
                    }
                } else {
                    $errors[] = translate('No gateway selected for') . ' ' . $channel;
                }
            }

            // Email-specific checks
            if ($channel === CampaignChannel::EMAIL->value && $message) {
                if (empty($message->subject)) {
                    $warnings[] = translate('No subject line for email message');
                }
            }
        }

        // Check schedule
        if ($campaign->type !== CampaignType::INSTANT && !$campaign->schedule_at) {
            $errors[] = translate('Scheduled campaign requires a schedule date');
        }

        // Check contacts
        if ($campaign->total_contacts === 0) {
            $errors[] = translate('No contacts available in the selected group');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
