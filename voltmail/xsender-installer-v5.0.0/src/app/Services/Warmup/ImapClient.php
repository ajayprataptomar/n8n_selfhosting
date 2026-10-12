<?php

namespace App\Services\Warmup;

use Exception;

class ImapClient
{
    protected string $host;
    protected int $port;
    protected string $encryption;
    protected int $timeout;
    protected $stream = null;
    protected int $tagCounter = 1;
    protected ?int $currentUidValidity = null;

    /**
     * Optional custom transport handler for testing and offline mocks
     */
    protected $transport = null;

    public function __construct(
        string $host = '',
        int $port = 993,
        string $encryption = 'ssl',
        int $timeout = 15,
        ?callable $customTransport = null
    ) {
        $this->host       = $host;
        $this->port       = $port;
        $this->encryption = $encryption;
        $this->timeout    = $timeout;
        $this->transport  = $customTransport;
    }

    /**
     * Connect to the IMAP server
     */
    public function connect(): bool
    {
        if ($this->transport) {
            return ($this->transport)('connect', ['host' => $this->host, 'port' => $this->port]);
        }

        $proto = match (strtolower($this->encryption)) {
            'ssl'   => 'ssl://',
            'tls'   => 'tls://',
            default => 'tcp://',
        };

        $errno = 0;
        $errstr = '';
        $target = $proto . $this->host . ':' . $this->port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $this->stream = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->stream) {
            throw new Exception("IMAP Connection failed to {$target}: [{$errno}] {$errstr}");
        }

        stream_set_timeout($this->stream, $this->timeout);

        // Read server greeting
        $greeting = $this->readLine();
        if (!str_starts_with($greeting, '* OK')) {
            throw new Exception("Unexpected IMAP greeting: {$greeting}");
        }

        return true;
    }

    /**
     * Authenticate with username and password
     */
    public function login(string $username, string $password): bool
    {
        if ($this->transport) {
            return ($this->transport)('login', ['username' => $username]);
        }

        // Escape double quotes in password
        $escapedPass = addcslashes($password, '"\\');
        $escapedUser = addcslashes($username, '"\\');

        $response = $this->sendCommand("LOGIN \"{$escapedUser}\" \"{$escapedPass}\"");
        return $this->isSuccess($response);
    }

    /**
     * Select a mailbox folder (e.g., 'INBOX', '[Gmail]/Spam', 'Junk')
     * Parses and records the folder UIDVALIDITY
     */
    public function selectFolder(string $folder = 'INBOX'): array
    {
        if ($this->transport) {
            $res = ($this->transport)('selectFolder', ['folder' => $folder]);
            $this->currentUidValidity = $res['uidvalidity'] ?? 1000;
            return $res;
        }

        $response = $this->sendCommand("SELECT \"{$folder}\"");
        if (!$this->isSuccess($response)) {
            throw new Exception("Failed to select folder: {$folder}");
        }

        // Parse UIDVALIDITY from response: * OK [UIDVALIDITY 12345678]
        $uidValidity = null;
        $existsCount = 0;

        foreach ($response as $line) {
            if (preg_match('/\[UIDVALIDITY\s+(\d+)\]/i', $line, $matches)) {
                $uidValidity = (int) $matches[1];
            }
            if (preg_match('/^\*\s+(\d+)\s+EXISTS/i', $line, $matches)) {
                $existsCount = (int) $matches[1];
            }
        }

        $this->currentUidValidity = $uidValidity;

        return [
            'folder'      => $folder,
            'uidvalidity' => $uidValidity,
            'exists'      => $existsCount,
        ];
    }

    /**
     * Search for messages matching a Message-ID header
     * Returns array of integer UIDs
     */
    public function searchMessageId(string $messageId): array
    {
        if ($this->transport) {
            return ($this->transport)('searchMessageId', ['messageId' => $messageId]);
        }

        $cleanMsgId = trim($messageId, '<>');
        $response = $this->sendCommand("UID SEARCH HEADER Message-ID \"{$cleanMsgId}\"");

        $uids = [];
        foreach ($response as $line) {
            if (str_starts_with($line, '* SEARCH')) {
                $parts = explode(' ', trim($line));
                array_shift($parts); // remove *
                array_shift($parts); // remove SEARCH
                foreach ($parts as $uid) {
                    if (is_numeric($uid) && (int) $uid > 0) {
                        $uids[] = (int) $uid;
                    }
                }
            }
        }

        return $uids;
    }

    /**
     * Search for messages with UID greater than a watermark
     */
    public function searchSinceUid(int $watermarkUid): array
    {
        if ($this->transport) {
            return ($this->transport)('searchSinceUid', ['watermark' => $watermarkUid]);
        }

        $startUid = max(1, $watermarkUid + 1);
        $response = $this->sendCommand("UID SEARCH UID {$startUid}:*");

        $uids = [];
        foreach ($response as $line) {
            if (str_starts_with($line, '* SEARCH')) {
                $parts = explode(' ', trim($line));
                array_shift($parts);
                array_shift($parts);
                foreach ($parts as $uid) {
                    if (is_numeric($uid) && (int) $uid > $watermarkUid) {
                        $uids[] = (int) $uid;
                    }
                }
            }
        }

        return $uids;
    }

    /**
     * Disconnect / Logout
     */
    public function disconnect(): void
    {
        if ($this->stream) {
            try {
                $this->sendCommand("LOGOUT");
            } catch (\Exception $e) {
                // Ignore logout exceptions on close
            }
            @fclose($this->stream);
            $this->stream = null;
        }
    }

    public function getCurrentUidValidity(): ?int
    {
        return $this->currentUidValidity;
    }

    /**
     * Send tagged IMAP command and read full multi-line response until tagged completion
     */
    protected function sendCommand(string $command): array
    {
        $tag = 'TAG' . str_pad((string) $this->tagCounter++, 4, '0', STR_PAD_LEFT);
        $fullLine = "{$tag} {$command}\r\n";

        if (!@fwrite($this->stream, $fullLine)) {
            throw new Exception("Failed to write command to IMAP stream");
        }

        $lines = [];
        while (!feof($this->stream)) {
            $line = $this->readLine();
            $lines[] = $line;

            if (str_starts_with($line, $tag . ' ')) {
                break; // Tagged completion reached
            }
        }

        return $lines;
    }

    protected function readLine(): string
    {
        $line = fgets($this->stream, 4096);
        if ($line === false) {
            throw new Exception("IMAP connection closed or timed out");
        }
        return rtrim($line, "\r\n");
    }

    protected function isSuccess(array $responseLines): bool
    {
        $lastLine = end($responseLines) ?: '';
        return (bool) preg_match('/^TAG\d+\s+OK/i', $lastLine);
    }
}
