<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Fetches a list of installable add-ons from a configured marketplace URL,
 * caches the response, and supports one-click install by downloading the
 * remote ZIP to a temp file before handing off to AddonManager's existing
 * install pipeline (purchase verification still happens locally).
 *
 * Marketplace URL is read from site_settings('addon_marketplace_url').
 * Expected response shape:
 *
 *   {
 *     "version": 1,
 *     "addons": [
 *       {
 *         "slug": "whatsapp-cloud-pro",
 *         "name": "WhatsApp Cloud Pro",
 *         "description": "...",
 *         "version": "1.2.0",
 *         "price": "29",
 *         "icon": "https://...",
 *         "download_url": "https://.../whatsapp-cloud-pro-1.2.0.zip",
 *         "min_xsender_version": "4.5.0",
 *         "tags": ["whatsapp","enterprise"]
 *       },
 *       ...
 *     ]
 *   }
 */
class AddonMarketplaceService
{
    private const CACHE_KEY  = 'addon_marketplace_listing';
    private const CACHE_TTL  = 1800;   // 30 minutes
    private const HTTP_TIMEOUT = 20;

    public function listAvailable(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $url = trim((string) site_settings('addon_marketplace_url'));
            if ($url === '') {
                return ['version' => 0, 'addons' => []];
            }

            try {
                $response = Http::timeout(self::HTTP_TIMEOUT)->acceptJson()->get($url);
                if (!$response->successful()) {
                    Log::warning('AddonMarketplace: HTTP fetch failed', [
                        'url'    => $url,
                        'status' => $response->status(),
                    ]);
                    return ['version' => 0, 'addons' => [], 'error' => 'HTTP ' . $response->status()];
                }
                $data = $response->json();
                if (!is_array($data) || !isset($data['addons']) || !is_array($data['addons'])) {
                    return ['version' => 0, 'addons' => [], 'error' => 'Malformed response'];
                }
                return $data;
            } catch (\Throwable $e) {
                Log::error('AddonMarketplace: fetch exception', ['error' => $e->getMessage()]);
                return ['version' => 0, 'addons' => [], 'error' => $e->getMessage()];
            }
        });
    }

    /**
     * Download a remote ZIP to a temp file and return the absolute path.
     * Caller is responsible for cleanup after handing it to AddonManager.
     */
    public function downloadAddonZip(string $downloadUrl): string
    {
        if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid download URL');
        }

        $response = Http::timeout(120)->get($downloadUrl);
        if (!$response->successful()) {
            throw new \RuntimeException('Download failed: HTTP ' . $response->status());
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        if ($contentType && !str_contains($contentType, 'zip') && !str_contains($contentType, 'octet-stream')) {
            throw new \RuntimeException("Unexpected content-type for ZIP: {$contentType}");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xs_addon_');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temp file for addon download');
        }
        // Ensure .zip extension so downstream tooling treats it correctly.
        $zipPath = $tmp . '.zip';
        rename($tmp, $zipPath);

        file_put_contents($zipPath, $response->body());

        return $zipPath;
    }
}
