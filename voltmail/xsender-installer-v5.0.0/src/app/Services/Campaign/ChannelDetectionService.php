<?php

namespace App\Services\Campaign;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\ChannelDetectionMode;
use App\Models\Contact;
use App\Models\UnifiedCampaign;
use Illuminate\Support\Collection;

class ChannelDetectionService
{
    /**
     * Detect available channels for a contact
     */
    public function detectChannelsForContact(Contact $contact): array
    {
        $available = [];

        if (!empty($contact->sms_contact)) {
            $available[] = CampaignChannel::SMS->value;
        }

        if (!empty($contact->email_contact)) {
            $available[] = CampaignChannel::EMAIL->value;
        }

        if (!empty($contact->whatsapp_contact)) {
            $available[] = CampaignChannel::WHATSAPP->value;
        }

        return $available;
    }

    /**
     * Get contacts with channel availability for a contact group
     */
    public function getContactsWithChannels(int $groupId, ?int $userId = null): Collection
    {
        $query = Contact::where('group_id', $groupId)
            ->active();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->admin();
        }

        return $query->get()->map(function ($contact) {
            return [
                'contact' => $contact,
                'channels' => $this->detectChannelsForContact($contact),
            ];
        });
    }

    /**
     * Get channel distribution for a contact group
     */
    public function getChannelDistribution(int $groupId, ?int $userId = null): array
    {
        $contacts = $this->getContactsWithChannels($groupId, $userId);

        $distribution = [
            CampaignChannel::SMS->value => 0,
            CampaignChannel::EMAIL->value => 0,
            CampaignChannel::WHATSAPP->value => 0,
        ];

        foreach ($contacts as $data) {
            foreach ($data['channels'] as $channel) {
                $distribution[$channel]++;
            }
        }

        return [
            'total' => $contacts->count(),
            'channels' => $distribution,
            'multi_channel' => $contacts->filter(fn($d) => count($d['channels']) > 1)->count(),
        ];
    }

    /**
     * Determine which channel to use for a contact based on campaign settings
     */
    public function determineChannelForContact(
        Contact $contact,
        UnifiedCampaign $campaign
    ): ?string {
        $availableChannels = $this->detectChannelsForContact($contact);
        $campaignChannels = $campaign->channels ?? [];

        // Filter to only channels that are both available and configured in campaign
        $eligibleChannels = array_intersect($availableChannels, $campaignChannels);

        if (empty($eligibleChannels)) {
            return null;
        }

        switch ($campaign->channel_detection_mode) {
            case ChannelDetectionMode::AUTO:
                return $this->selectBestChannel($eligibleChannels, $contact);

            case ChannelDetectionMode::PRIORITY_FALLBACK:
                return $this->selectByPriority($eligibleChannels, $campaign->channel_priority ?? []);

            case ChannelDetectionMode::MANUAL:
            default:
                // Return first eligible channel
                return $eligibleChannels[0] ?? null;
        }
    }

    /**
     * Determine all channels to use for a contact (for multi-channel sends)
     */
    public function determineAllChannelsForContact(
        Contact $contact,
        UnifiedCampaign $campaign
    ): array {
        $availableChannels = $this->detectChannelsForContact($contact);
        $campaignChannels = $campaign->channels ?? [];

        return array_values(array_intersect($availableChannels, $campaignChannels));
    }

    /**
     * Select the best channel based on contact preferences and engagement
     */
    protected function selectBestChannel(array $channels, Contact $contact): string
    {
        // Priority order: WhatsApp > SMS > Email (for immediate engagement)
        $priority = [
            CampaignChannel::WHATSAPP->value,
            CampaignChannel::SMS->value,
            CampaignChannel::EMAIL->value,
        ];

        foreach ($priority as $channel) {
            if (in_array($channel, $channels)) {
                return $channel;
            }
        }

        return $channels[0];
    }

    /**
     * Select channel by priority order
     */
    protected function selectByPriority(array $eligibleChannels, array $priority): ?string
    {
        if (empty($priority)) {
            return $eligibleChannels[0] ?? null;
        }

        foreach ($priority as $channel) {
            if (in_array($channel, $eligibleChannels)) {
                return $channel;
            }
        }

        return $eligibleChannels[0] ?? null;
    }

    /**
     * Validate campaign channels configuration
     */
    public function validateCampaignChannels(
        array $channels,
        int $groupId,
        ?int $userId = null
    ): array {
        $distribution = $this->getChannelDistribution($groupId, $userId);
        $errors = [];
        $warnings = [];

        foreach ($channels as $channel) {
            $count = $distribution['channels'][$channel] ?? 0;

            if ($count === 0) {
                $errors[] = translate('No contacts available for') . ' ' . CampaignChannel::from($channel)->label() . ' ' . translate('channel');
            } elseif ($count < 10) {
                $warnings[] = translate('Only') . ' ' . $count . ' ' . translate('contacts available for') . ' ' . CampaignChannel::from($channel)->label();
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'distribution' => $distribution,
        ];
    }

    /**
     * Get contact address for a specific channel
     */
    public function getContactAddress(Contact $contact, string $channel): ?string
    {
        return match ($channel) {
            CampaignChannel::SMS->value => $contact->sms_contact,
            CampaignChannel::EMAIL->value => $contact->email_contact,
            CampaignChannel::WHATSAPP->value => $contact->whatsapp_contact,
            default => null,
        };
    }

    /**
     * Check if contact has specific channel
     */
    public function contactHasChannel(Contact $contact, string $channel): bool
    {
        return !empty($this->getContactAddress($contact, $channel));
    }

    /**
     * Get summary of channel reachability for contacts
     */
    public function getReachabilitySummary(Collection $contacts, array $campaignChannels): array
    {
        $summary = [
            'total' => $contacts->count(),
            'reachable' => 0,
            'unreachable' => 0,
            'by_channel' => [],
        ];

        foreach ($campaignChannels as $channel) {
            $summary['by_channel'][$channel] = [
                'count' => 0,
                'percentage' => 0,
            ];
        }

        foreach ($contacts as $contact) {
            $contactChannels = $this->detectChannelsForContact($contact);
            $reachableChannels = array_intersect($contactChannels, $campaignChannels);

            if (!empty($reachableChannels)) {
                $summary['reachable']++;

                foreach ($reachableChannels as $channel) {
                    $summary['by_channel'][$channel]['count']++;
                }
            } else {
                $summary['unreachable']++;
            }
        }

        // Calculate percentages
        if ($summary['total'] > 0) {
            foreach ($summary['by_channel'] as $channel => &$data) {
                $data['percentage'] = round(($data['count'] / $summary['total']) * 100, 1);
            }
        }

        return $summary;
    }
}
