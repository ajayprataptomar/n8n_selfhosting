<?php

namespace App\Services\System;

use App\Models\Gateway;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Professional Phone Number Service
 * Handles phone number formatting and WhatsApp validation globally
 */
class PhoneNumberService
{
    /**
     * All country codes sorted by length (longest first for accurate matching)
     * This ensures 880 is matched before 88, etc.
     */
    private static array $countryCodes = [
        // 4-digit codes
        '1684', '1264', '1268', '1242', '1246', '1441', '1284', '1345', '1767', '1809', '1829', '1849',
        '1473', '1876', '1664', '1787', '1939', '1869', '1758', '1784', '1868', '1649', '1340',
        // 3-digit codes
        '880', '886', '852', '853', '855', '856', '670', '673', '674', '675', '676', '677', '678', '679',
        '680', '681', '682', '683', '685', '686', '687', '688', '689', '690', '691', '692',
        '850', '960', '961', '962', '963', '964', '965', '966', '967', '968', '970', '971', '972', '973', '974', '975', '976', '977',
        '992', '993', '994', '995', '996', '997', '998',
        '211', '212', '213', '216', '218', '220', '221', '222', '223', '224', '225', '226', '227', '228', '229',
        '230', '231', '232', '233', '234', '235', '236', '237', '238', '239', '240', '241', '242', '243', '244', '245', '246', '247', '248', '249',
        '250', '251', '252', '253', '254', '255', '256', '257', '258', '260', '261', '262', '263', '264', '265', '266', '267', '268', '269',
        '290', '291', '297', '298', '299',
        '350', '351', '352', '353', '354', '355', '356', '357', '358', '359',
        '370', '371', '372', '373', '374', '375', '376', '377', '378', '379', '380', '381', '382', '383', '385', '386', '387', '389',
        '420', '421', '423',
        '500', '501', '502', '503', '504', '505', '506', '507', '508', '509',
        '590', '591', '592', '593', '594', '595', '596', '597', '598', '599',
        '670', '672', '673', '674', '675', '676', '677', '678', '679', '680', '681', '682', '683', '685', '686', '687', '688', '689',
        // 2-digit codes
        '20', '27', '28', '30', '31', '32', '33', '34', '36', '39', '40', '41', '43', '44', '45', '46', '47', '48', '49',
        '51', '52', '53', '54', '55', '56', '57', '58', '60', '61', '62', '63', '64', '65', '66',
        '81', '82', '84', '86', '90', '91', '92', '93', '94', '95', '98',
        // 1-digit codes
        '1', '7',
    ];

    /**
     * Format phone number for WhatsApp
     * Handles all input formats and adds country code if needed
     *
     * @param string $phoneNumber Raw phone number input
     * @param Gateway|null $gateway Gateway to extract default country code from
     * @param string|null $defaultCountryCode Fallback country code
     * @return array ['success' => bool, 'formatted' => string, 'original' => string, 'country_code' => string]
     */
    public static function format(string $phoneNumber, ?Gateway $gateway = null, ?string $defaultCountryCode = null): array
    {
        $original = $phoneNumber;

        // Step 1: Clean the number - remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', trim($phoneNumber));

        // Step 2: Handle + prefix
        $hasPlus = strpos($cleaned, '+') === 0;
        $cleaned = ltrim($cleaned, '+');

        // Step 3: Remove any remaining non-digits
        $cleaned = preg_replace('/\D/', '', $cleaned);

        if (empty($cleaned)) {
            return [
                'success' => false,
                'formatted' => '',
                'original' => $original,
                'country_code' => '',
                'error' => 'Invalid phone number: empty after cleaning'
            ];
        }

        // Step 4: Detect if number already has country code
        $detectedCode = self::detectCountryCode($cleaned);

        if ($detectedCode) {
            // Number already has a valid country code
            return [
                'success' => true,
                'formatted' => $cleaned,
                'original' => $original,
                'country_code' => $detectedCode,
            ];
        }

        // Step 5: Number doesn't have country code, we need to add one
        // Try to get country code from gateway first
        $countryCode = self::getCountryCodeFromGateway($gateway);

        // Fallback to provided default or site settings
        if (!$countryCode) {
            $countryCode = $defaultCountryCode ?: site_settings('country_code');
        }

        if (!$countryCode) {
            return [
                'success' => false,
                'formatted' => $cleaned,
                'original' => $original,
                'country_code' => '',
                'error' => 'Cannot determine country code. Please include country code with the number (e.g., +880...)'
            ];
        }

        // Step 6: Remove leading zero if present (local format)
        if (strpos($cleaned, '0') === 0) {
            $cleaned = substr($cleaned, 1);
        }

        // Step 7: Add country code
        $formatted = $countryCode . $cleaned;

        return [
            'success' => true,
            'formatted' => $formatted,
            'original' => $original,
            'country_code' => $countryCode,
        ];
    }

    /**
     * Detect country code from a phone number
     *
     * @param string $number Clean phone number (digits only)
     * @return string|null Country code if found, null otherwise
     */
    public static function detectCountryCode(string $number): ?string
    {
        // Check against all country codes (sorted by length, longest first)
        foreach (self::$countryCodes as $code) {
            if (strpos($number, $code) === 0) {
                // Verify the remaining digits form a valid phone number (at least 6 digits)
                $remaining = substr($number, strlen($code));
                if (strlen($remaining) >= 6 && strlen($remaining) <= 14) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * Extract country code from gateway's WhatsApp number
     *
     * @param Gateway|null $gateway
     * @return string|null
     */
    public static function getCountryCodeFromGateway(?Gateway $gateway): ?string
    {
        if (!$gateway) {
            return null;
        }

        $gatewayNumber = Arr::get($gateway->meta_data, 'number', '');
        if (empty($gatewayNumber)) {
            return null;
        }

        // Clean the gateway number
        $cleaned = preg_replace('/\D/', '', $gatewayNumber);

        // Detect country code from gateway number
        return self::detectCountryCode($cleaned);
    }

    /**
     * Check if a phone number exists on WhatsApp
     *
     * @param string $phoneNumber Formatted phone number (with country code)
     * @param Gateway $gateway Gateway to use for checking
     * @return array ['exists' => bool, 'jid' => string|null, 'error' => string|null]
     */
    public static function checkWhatsAppExists(string $phoneNumber, Gateway $gateway): array
    {
        try {
            $apiURL = env('WP_SERVER_URL') . '/messages/check';

            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-Key' => env('WP_API_KEY', ''),
                ])
                ->post($apiURL, [
                    'sessionId' => $gateway->name,
                    'number' => $phoneNumber,
                ]);

            if ($response->status() === 200) {
                $data = $response->json();
                if (Arr::get($data, 'success')) {
                    return [
                        'exists' => Arr::get($data, 'data.exists', false),
                        'jid' => Arr::get($data, 'data.jid'),
                        'error' => null,
                    ];
                }
            }

            return [
                'exists' => false,
                'jid' => null,
                'error' => Arr::get($response->json(), 'message', 'Failed to check number'),
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp number check failed: ' . $e->getMessage());
            return [
                'exists' => false,
                'jid' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format and validate phone number for WhatsApp sending
     * This is the main method to use before sending messages
     *
     * @param string $phoneNumber Raw phone number
     * @param Gateway $gateway Gateway to use
     * @param bool $validateExists Whether to check if number exists on WhatsApp
     * @return array ['success' => bool, 'formatted' => string, 'error' => string|null]
     */
    public static function prepareForSending(string $phoneNumber, Gateway $gateway, bool $validateExists = true): array
    {
        // Step 1: Format the number
        $formatted = self::format($phoneNumber, $gateway);

        if (!$formatted['success']) {
            return [
                'success' => false,
                'formatted' => '',
                'error' => $formatted['error'] ?? 'Invalid phone number format',
            ];
        }

        $formattedNumber = $formatted['formatted'];

        // Step 2: Validate length (international numbers are typically 10-15 digits)
        if (strlen($formattedNumber) < 10 || strlen($formattedNumber) > 15) {
            return [
                'success' => false,
                'formatted' => $formattedNumber,
                'error' => "Invalid phone number length: {$formattedNumber} (expected 10-15 digits)",
            ];
        }

        // Step 3: Check if number exists on WhatsApp (optional but recommended)
        if ($validateExists) {
            // Use cache to avoid repeated checks for the same number
            $cacheKey = "whatsapp_exists_{$gateway->name}_{$formattedNumber}";
            $exists = Cache::remember($cacheKey, now()->addHours(24), function () use ($formattedNumber, $gateway) {
                $check = self::checkWhatsAppExists($formattedNumber, $gateway);
                return $check['exists'];
            });

            if (!$exists) {
                // Clear cache on failure to allow retry
                Cache::forget($cacheKey);
                return [
                    'success' => false,
                    'formatted' => $formattedNumber,
                    'error' => "Number {$formattedNumber} is not registered on WhatsApp",
                ];
            }
        }

        return [
            'success' => true,
            'formatted' => $formattedNumber,
            'error' => null,
        ];
    }

    /**
     * Batch validate multiple phone numbers
     *
     * @param array $phoneNumbers Array of phone numbers
     * @param Gateway $gateway Gateway to use
     * @param bool $validateExists Whether to check WhatsApp existence
     * @return array ['valid' => [...], 'invalid' => [...]]
     */
    public static function validateBatch(array $phoneNumbers, Gateway $gateway, bool $validateExists = false): array
    {
        $valid = [];
        $invalid = [];

        foreach ($phoneNumbers as $index => $number) {
            $result = self::prepareForSending($number, $gateway, $validateExists);

            if ($result['success']) {
                $valid[$index] = [
                    'original' => $number,
                    'formatted' => $result['formatted'],
                ];
            } else {
                $invalid[$index] = [
                    'original' => $number,
                    'error' => $result['error'],
                ];
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'valid_count' => count($valid),
            'invalid_count' => count($invalid),
        ];
    }
}
