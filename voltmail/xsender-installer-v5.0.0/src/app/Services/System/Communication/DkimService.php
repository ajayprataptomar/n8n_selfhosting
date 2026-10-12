<?php

namespace App\Services\System\Communication;

use App\Models\SendingDomain;
use Exception;
use Illuminate\Support\Facades\Log;

class DkimService
{
    /**
     * Generate DKIM key pair for a sending domain.
     *
     * @param SendingDomain $domain
     * @param string $selector
     * @return SendingDomain
     * @throws Exception
     */
    public function generateKeyPair(SendingDomain $domain, string $selector = 'xsender'): SendingDomain
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // On Windows, openssl_pkey_new() needs an explicit config path
        $opensslCnf = $this->findOpensslConfig();
        if ($opensslCnf) {
            $config['config'] = $opensslCnf;
        }

        $keyPair = openssl_pkey_new($config);
        if (!$keyPair) {
            $error = '';
            while ($msg = openssl_error_string()) {
                $error .= $msg . ' ';
            }
            Log::error("DKIM key generation failed. OpenSSL config: " . ($config['config'] ?? 'not set') . ". PHP_BINARY: " . PHP_BINARY . ". Error: " . $error);
            throw new Exception(translate('Failed to generate DKIM key pair') . ($error ? ": {$error}" : ''));
        }

        // Extract private key
        openssl_pkey_export($keyPair, $privateKey, null, $config);

        // Extract public key
        $publicKeyDetails = openssl_pkey_get_details($keyPair);
        $publicKey = $publicKeyDetails['key'];

        // Clean public key for DNS record (remove header/footer/newlines)
        $publicKeyForDns = $this->cleanPublicKeyForDns($publicKey);

        // Build DNS TXT record value
        $dkimDnsRecord = "v=DKIM1; k=rsa; p={$publicKeyForDns}";

        // Build suggested SPF record
        $spfRecord = "v=spf1 include:_spf.{$domain->domain} ~all";

        // Build suggested DMARC record
        $dmarcRecord = "v=DMARC1; p=none; rua=mailto:dmarc@{$domain->domain}";

        $domain->update([
            'dkim_selector' => $selector,
            'dkim_private_key' => $privateKey,
            'dkim_public_key' => $publicKey,
            'dkim_dns_record' => $dkimDnsRecord,
            'spf_record' => $spfRecord,
            'dmarc_record' => $dmarcRecord,
        ]);

        return $domain;
    }

    /**
     * Verify DNS records for a sending domain.
     *
     * @param SendingDomain $domain
     * @return array Results of each verification check
     */
    public function verifyDns(SendingDomain $domain): array
    {
        $results = [
            'dkim' => false,
            'spf' => false,
            'dmarc' => false,
            'messages' => [],
        ];

        // Verify DKIM
        $results['dkim'] = $this->verifyDkim($domain, $results['messages']);

        // Verify SPF
        $results['spf'] = $this->verifySpf($domain, $results['messages']);

        // Verify DMARC
        $results['dmarc'] = $this->verifyDmarc($domain, $results['messages']);

        // Update domain status
        $updateData = [
            'dkim_verified' => $results['dkim'] ? 'yes' : 'no',
            'spf_verified' => $results['spf'] ? 'yes' : 'no',
            'dmarc_verified' => $results['dmarc'] ? 'yes' : 'no',
            'dns_checked_at' => now(),
        ];

        // If DKIM is verified, activate the domain
        if ($results['dkim']) {
            $updateData['status'] = 'active';
            $updateData['verified_at'] = now();
        }

        $domain->update($updateData);

        return $results;
    }

    /**
     * Verify DKIM DNS record.
     */
    private function verifyDkim(SendingDomain $domain, array &$messages): bool
    {
        if (!$domain->isDkimConfigured()) {
            $messages[] = translate('DKIM keys not generated yet');
            return false;
        }

        $expectedPublicKey = $this->cleanPublicKeyForDns($domain->dkim_public_key);
        $hostname = "{$domain->dkim_selector}._domainkey.{$domain->domain}";

        try {
            $records = dns_get_record($hostname, DNS_TXT);

            if (empty($records)) {
                $messages[] = translate('No DKIM TXT record found at') . " {$hostname}";
                return false;
            }

            foreach ($records as $record) {
                $txt = $record['txt'] ?? '';
                // DNS records may be split across multiple strings — concatenate
                $txt = str_replace(['" "', "\t", "\n", "\r", ' '], '', $txt);

                if (str_contains($txt, $expectedPublicKey)) {
                    $messages[] = translate('DKIM record verified successfully');
                    return true;
                }
            }

            $messages[] = translate('DKIM record found but public key does not match');
            return false;

        } catch (Exception $e) {
            Log::warning("DKIM verification failed for {$domain->domain}: " . $e->getMessage());
            $messages[] = translate('DNS lookup failed for DKIM record');
            return false;
        }
    }

    /**
     * Verify SPF DNS record.
     */
    private function verifySpf(SendingDomain $domain, array &$messages): bool
    {
        try {
            $records = dns_get_record($domain->domain, DNS_TXT);

            if (empty($records)) {
                $messages[] = translate('No TXT records found for SPF');
                return false;
            }

            foreach ($records as $record) {
                $txt = $record['txt'] ?? '';
                if (str_starts_with($txt, 'v=spf1')) {
                    $messages[] = translate('SPF record found');
                    return true;
                }
            }

            $messages[] = translate('No SPF record found');
            return false;

        } catch (Exception $e) {
            $messages[] = translate('DNS lookup failed for SPF record');
            return false;
        }
    }

    /**
     * Verify DMARC DNS record.
     */
    private function verifyDmarc(SendingDomain $domain, array &$messages): bool
    {
        try {
            $hostname = "_dmarc.{$domain->domain}";
            $records = dns_get_record($hostname, DNS_TXT);

            if (empty($records)) {
                $messages[] = translate('No DMARC record found');
                return false;
            }

            foreach ($records as $record) {
                $txt = $record['txt'] ?? '';
                if (str_starts_with($txt, 'v=DMARC1')) {
                    $messages[] = translate('DMARC record found');
                    return true;
                }
            }

            $messages[] = translate('No DMARC record found');
            return false;

        } catch (Exception $e) {
            $messages[] = translate('DNS lookup failed for DMARC record');
            return false;
        }
    }

    /**
     * Clean a PEM-encoded public key for DNS TXT record.
     * Removes header, footer, and newlines.
     */
    private function cleanPublicKeyForDns(string $publicKey): string
    {
        return str_replace(
            ["\n", "\r", '-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'],
            '',
            $publicKey
        );
    }

    /**
     * Get the DNS record hostname for DKIM verification.
     */
    public function getDkimHostname(SendingDomain $domain): string
    {
        return "{$domain->dkim_selector}._domainkey.{$domain->domain}";
    }

    /**
     * Get all DNS records that need to be configured.
     */
    public function getDnsRecords(SendingDomain $domain): array
    {
        $dkimValue = $domain->dkim_dns_record;
        $needsSplit = strlen($dkimValue) > 255;

        return [
            'dkim' => [
                'type' => 'TXT',
                'hostname' => $this->getDkimHostname($domain),
                'value' => $dkimValue,
                'value_split' => $needsSplit ? $this->splitDkimValueForDns($dkimValue) : null,
                'needs_split' => $needsSplit,
                'value_length' => strlen($dkimValue),
                'selector' => $domain->dkim_selector ?? 'xsender',
                'verified' => $domain->dkim_verified === 'yes',
            ],
            'spf' => [
                'type' => 'TXT',
                'hostname' => $domain->domain,
                'value' => $domain->spf_record,
                'verified' => $domain->spf_verified === 'yes',
            ],
            'dmarc' => [
                'type' => 'TXT',
                'hostname' => "_dmarc.{$domain->domain}",
                'value' => $domain->dmarc_record,
                'verified' => $domain->dmarc_verified === 'yes',
            ],
        ];
    }

    /**
     * Split a long DKIM DNS TXT record value into 255-char quoted strings.
     *
     * Per RFC 4408, DNS TXT records can contain multiple strings of up to 255 bytes each.
     * Some DNS providers (cPanel, older registrars) cannot accept values longer than 255
     * characters in a single field. This method splits the value into properly quoted
     * chunks that DNS providers can accept.
     *
     * @param string $value The full DKIM DNS record value
     * @param int $chunkSize Maximum characters per chunk (default 255 per RFC)
     * @return string The split value with quoted strings
     */
    public function splitDkimValueForDns(string $value, int $chunkSize = 255): string
    {
        if (strlen($value) <= $chunkSize) {
            return $value;
        }

        $chunks = str_split($value, $chunkSize);
        $quoted = array_map(fn($chunk) => '"' . $chunk . '"', $chunks);

        return implode(' ', $quoted);
    }

    /**
     * Check if OpenSSL key generation is working on this server.
     * Returns diagnostic info for troubleshooting.
     */
    public function checkOpenSslReadiness(): array
    {
        $result = [
            'ready' => false,
            'openssl_loaded' => extension_loaded('openssl'),
            'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null,
            'config_path' => null,
            'error' => null,
        ];

        if (!$result['openssl_loaded']) {
            $result['error'] = translate('The OpenSSL PHP extension is not installed or enabled on your server.');
            return $result;
        }

        $configPath = $this->findOpensslConfig();
        $result['config_path'] = $configPath;

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        if ($configPath) {
            $config['config'] = $configPath;
        }

        $key = @openssl_pkey_new($config);
        if ($key) {
            $result['ready'] = true;
        } else {
            $error = '';
            while ($msg = openssl_error_string()) {
                $error .= $msg . ' ';
            }
            $result['error'] = trim($error) ?: translate('OpenSSL failed to generate a test key. The openssl.cnf configuration file may be missing.');
        }

        return $result;
    }

    /**
     * Find the openssl.cnf config file path.
     * Required on Windows where the default path may not exist.
     */
    private function findOpensslConfig(): ?string
    {
        $candidates = [
            // Environment variable (highest priority)
            getenv('OPENSSL_CONF') ?: '',
            // Relative to PHP binary (works for CLI)
            dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
            dirname(PHP_BINARY) . '/../extras/ssl/openssl.cnf',
        ];

        // Relative to php.ini location (works under Apache where PHP_BINARY is httpd)
        $phpIni = php_ini_loaded_file();
        if ($phpIni) {
            $phpDir = dirname($phpIni);
            $candidates[] = $phpDir . '/extras/ssl/openssl.cnf';
            $candidates[] = dirname($phpDir) . '/extras/ssl/openssl.cnf';
        }

        // Laragon-specific paths
        if (is_dir('D:/laragon/bin/php')) {
            $phpDirs = glob('D:/laragon/bin/php/php-*/extras/ssl/openssl.cnf');
            if ($phpDirs) {
                $candidates = array_merge($candidates, $phpDirs);
            }
        }

        // Standard Windows location
        $candidates[] = 'C:/Program Files/Common Files/SSL/openssl.cnf';

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
