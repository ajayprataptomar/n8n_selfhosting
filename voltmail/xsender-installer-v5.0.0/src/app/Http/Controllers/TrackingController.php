<?php

namespace App\Http\Controllers;

use App\Models\DispatchLog;
use App\Models\EmailEvent;
use App\Models\EmailTrackingEvent;
use App\Models\ContactEngagement;
use App\Models\TrackingDomain;
use App\Services\EmailBotDetector;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    /**
     * 1x1 transparent GIF pixel for open tracking.
     */
    private const TRANSPARENT_GIF = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    /**
     * Track email open via pixel.
     *
     * GET /t/o/{token}
     */
    public function trackOpen(Request $request, string $token)
    {
        try {
            $data = $this->decodeToken($token);
            if (!$data) {
                return $this->pixelResponse();
            }

            // Unified campaign dispatch (CampaignDispatch) — read-side stats derive from status.
            if (!empty($data['cd'])) {
                $dispatch = \App\Models\CampaignDispatch::find($data['cd']);
                if ($dispatch) {
                    // v4.8 — bot-filtered insights. EVERY pixel hit gets logged
                    // to email_events (raw open count + audit). Only NON-bot
                    // hits advance the dispatch status and engagement counters,
                    // so Gmail's image prefetcher no longer inflates opens.
                    $classification = $this->recordEmailEvent(
                        request:   $request,
                        eventType: 'open',
                        dispatch:  $dispatch,
                        url:       null
                    );
                    if (!$classification['is_bot']) {
                        $dispatch->markAsOpened();
                        $this->updateEngagementFromDispatch($dispatch, 'opened');
                    }
                }
                return $this->pixelResponse();
            }

            $dispatchLog = DispatchLog::find($data['dispatch_log_id'] ?? null);
            if (!$dispatchLog) {
                return $this->pixelResponse();
            }

            EmailTrackingEvent::create([
                'dispatch_log_id' => $dispatchLog->id,
                'contact_id' => $dispatchLog->contact_id,
                'campaign_id' => $dispatchLog->campaign_id,
                'user_id' => $dispatchLog->user_id,
                'event_type' => 'open',
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'created_at' => now(),
            ]);

            $this->updateEngagement($dispatchLog, 'opened');

        } catch (\Exception $e) {
            Log::debug("Tracking open error: " . $e->getMessage());
        }

        return $this->pixelResponse();
    }

    /**
     * Track email click and redirect to original URL.
     *
     * GET /t/c/{token}
     */
    public function trackClick(Request $request, string $token)
    {
        try {
            $data = $this->decodeToken($token);
            if (!$data || empty($data['url'])) {
                return redirect('/');
            }

            $url = $data['url'];

            // Unified campaign dispatch (CampaignDispatch) — read-side stats derive from status.
            if (!empty($data['cd'])) {
                $dispatch = \App\Models\CampaignDispatch::find($data['cd']);
                if ($dispatch) {
                    // v4.8 — bot-filtered insights (see trackOpen comment). Corporate
                    // link scanners (Mimecast/Proofpoint/SafeLinks) click every URL
                    // on arrival — logging without advancing status keeps click-rate
                    // truthful.
                    $classification = $this->recordEmailEvent(
                        request:   $request,
                        eventType: 'click',
                        dispatch:  $dispatch,
                        url:       $url
                    );
                    if (!$classification['is_bot']) {
                        $dispatch->markAsClicked();
                        $this->updateEngagementFromDispatch($dispatch, 'clicked');
                    }
                }
                return redirect()->away($url);
            }

            $dispatchLog = DispatchLog::find($data['dispatch_log_id'] ?? null);
            if ($dispatchLog) {
                EmailTrackingEvent::create([
                    'dispatch_log_id' => $dispatchLog->id,
                    'contact_id' => $dispatchLog->contact_id,
                    'campaign_id' => $dispatchLog->campaign_id,
                    'user_id' => $dispatchLog->user_id,
                    'event_type' => 'click',
                    'url' => substr($url, 0, 2048),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                    'created_at' => now(),
                ]);

                $this->updateEngagement($dispatchLog, 'clicked');
            }

            return redirect()->away($url);

        } catch (\Exception $e) {
            Log::debug("Tracking click error: " . $e->getMessage());
            return redirect('/');
        }
    }

    /**
     * Decode a tracking token.
     * Token format: base64(json({dispatch_log_id, url?}))
     */
    private function decodeToken(string $token): ?array
    {
        try {
            $decoded = base64_decode(strtr($token, '-_', '+/'), true);
            if (!$decoded) return null;

            $data = json_decode($decoded, true);
            if (!is_array($data)) return null;
            // Must identify either a legacy DispatchLog or a unified CampaignDispatch.
            if (empty($data['dispatch_log_id']) && empty($data['cd'])) return null;

            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Encode a tracking token.
     */
    public static function encodeToken(array $data): string
    {
        return strtr(base64_encode(json_encode($data)), '+/', '-_');
    }

    /**
     * Generate open tracking pixel URL.
     */
    public static function openPixelUrl(int $targetId, ?int $userId = null, bool $unified = false): string
    {
        $token = self::encodeToken($unified ? ['cd' => $targetId] : ['dispatch_log_id' => $targetId]);
        $baseUrl = TrackingDomain::getTrackingBaseUrl($userId);
        return "{$baseUrl}/t/o/{$token}";
    }

    /**
     * Generate click tracking URL.
     */
    public static function clickTrackingUrl(int $targetId, string $originalUrl, ?int $userId = null, bool $unified = false): string
    {
        $payload = $unified ? ['cd' => $targetId] : ['dispatch_log_id' => $targetId];
        $payload['url'] = $originalUrl;
        $token = self::encodeToken($payload);
        $baseUrl = TrackingDomain::getTrackingBaseUrl($userId);
        return "{$baseUrl}/t/c/{$token}";
    }

    /**
     * Inject tracking pixel and rewrite links in email HTML.
     * Skips injection when the tracking base URL is not publicly reachable.
     *
     * @param bool $unified  When true, $targetId is a CampaignDispatch id (new unified
     *                       campaign system); otherwise it is a legacy DispatchLog id.
     */
    public static function injectTracking(string $html, int $targetId, ?int $userId = null, bool $unified = false): string
    {
        $baseUrl = TrackingDomain::getTrackingBaseUrl($userId);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        // Skip tracking for local/non-public domains that won't resolve from the internet
        if ($host && (
            preg_match('/\.(test|local|localhost|example|invalid|internal)$/i', $host) ||
            in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0']) ||
            str_ends_with($host, '.local')
        )) {
            return $html;
        }

        // Inject open tracking pixel before </body> or at end
        $pixelUrl = self::openPixelUrl($targetId, $userId, $unified);
        $pixel = '<img src="' . htmlspecialchars($pixelUrl) . '" width="1" height="1" alt="" style="display:none;border:0;" />';

        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', $pixel . '</body>', $html, 1);
        } else {
            $html .= $pixel;
        }

        // Rewrite <a href="..."> links for click tracking.
        // - `\s+` (was `\s`) so newline/tab BEFORE href doesn't skip the anchor.
        // - `/i` flag already handles uppercase <A>.
        // - `html_entity_decode()` on the raw href — the source HTML has entity-
        //   encoded ampersands (`href="page?a=1&amp;b=2"`); without decoding
        //   the redirect URL kept `&amp;` and every multi-param query broke.
        $html = preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']/i',
            function ($matches) use ($targetId, $userId, $unified) {
                $attrs = $matches[1];
                $url   = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // Skip special URLs
                if (preg_match('/^(mailto:|tel:|#|javascript:)/i', $url)) {
                    return $matches[0];
                }
                // Skip unsubscribe links
                if (str_contains($url, 'unsubscribe')) {
                    return $matches[0];
                }
                // Skip tracking URLs (avoid double-wrapping)
                if (str_contains($url, '/t/c/') || str_contains($url, '/t/o/')) {
                    return $matches[0];
                }

                $trackingUrl = self::clickTrackingUrl($targetId, $url, $userId, $unified);
                return '<a ' . $attrs . 'href="' . htmlspecialchars($trackingUrl, ENT_QUOTES) . '"';
            },
            $html
        );

        return $html;
    }

    /**
     * Return a 1x1 transparent GIF response.
     */
    private function pixelResponse(): Response
    {
        return response(self::TRANSPARENT_GIF, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen(self::TRANSPARENT_GIF),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    /**
     * v4.8 — Log a raw email event (open or click) and classify the requester.
     * ALWAYS persists to email_events (so raw counts + audit are truthful).
     * Returns the bot classification so the caller can decide whether to
     * advance CampaignDispatch.status. Fully non-throwing — if the table
     * doesn't exist yet (fresh v4.7 → v4.8 without migrate), we degrade to
     * "not a bot" so opens keep working like they did in v4.7.
     *
     * @return array{is_bot: bool, bot_name: string|null}
     */
    private function recordEmailEvent(Request $request, string $eventType, \App\Models\CampaignDispatch $dispatch, ?string $url): array
    {
        $userAgent = substr($request->userAgent() ?? '', 0, 500);
        try {
            $detector = app(EmailBotDetector::class);
        } catch (\Throwable $e) {
            $detector = new EmailBotDetector();
        }
        $classification = $detector->classify($userAgent);

        try {
            EmailEvent::create([
                'campaign_dispatch_id' => $dispatch->id,
                'contact_id'           => $dispatch->contact_id,
                'user_id'              => optional($dispatch->campaign)->user_id,
                'event_type'           => $eventType,
                'user_agent'           => $userAgent,
                'ip_address'           => $request->ip(),
                'is_bot'               => $classification['is_bot'],
                'bot_name'             => $classification['bot_name'],
                'url'                  => $url ? substr($url, 0, 2048) : null,
            ]);
        } catch (\Throwable $e) {
            // Table missing (migration not run yet) or DB write blocked — never
            // break tracking because of an audit-side failure. Log once, move on.
            Log::debug('EmailEvent log skipped: ' . $e->getMessage());
        }

        return $classification;
    }

    /**
     * Update contact engagement counters.
     */
    private function updateEngagement(DispatchLog $dispatchLog, string $type): void
    {
        if (!$dispatchLog->contact_id || !$dispatchLog->user_id) {
            return;
        }

        $engagement = ContactEngagement::firstOrCreate(
            [
                'contact_id' => $dispatchLog->contact_id,
                'channel' => 'email',
            ],
            [
                'user_id' => $dispatchLog->user_id,
            ]
        );

        $engagement->recordEngagement($type);

        // Update optimal time patterns
        $now = now();
        $engagement->updateOptimalPatterns($now->hour, $now->dayOfWeek);
    }

    /**
     * Update ContactEngagement from a unified CampaignDispatch. Mirrors
     * updateEngagement() but sourced from the new dispatch model so opens/
     * clicks on unified campaigns feed the Campaign Intelligence dashboard
     * cards (Avg Engagement, top performers). Without this, only legacy
     * DispatchLog opens updated engagement — unified campaigns left it at 0.
     */
    private function updateEngagementFromDispatch(\App\Models\CampaignDispatch $dispatch, string $type): void
    {
        if (!$dispatch->contact_id) {
            return;
        }

        $userId = null;
        if ($dispatch->campaign) {
            $userId = $dispatch->campaign->user_id;
        }
        if (!$userId) {
            return;
        }

        $channel = is_object($dispatch->channel) ? $dispatch->channel->value : (string) $dispatch->channel;

        $engagement = ContactEngagement::firstOrCreate(
            [
                'contact_id' => $dispatch->contact_id,
                'channel'    => $channel ?: 'email',
            ],
            [
                'user_id' => $userId,
            ]
        );

        $engagement->recordEngagement($type);

        $now = now();
        $engagement->updateOptimalPatterns($now->hour, $now->dayOfWeek);
    }
}
