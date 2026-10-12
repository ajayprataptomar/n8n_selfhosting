<?php

namespace App\Http\Utility;

use Exception;
use App\Models\User;
use App\Models\Admin;
use GuzzleHttp\Client;
use App\Models\Gateway;
use App\Models\Template;
use App\Enums\SettingKey;
use App\Enums\StatusEnum;
use App\Traits\Manageable;
use Illuminate\Support\Arr;
use App\Models\DispatchLog;
use App\Enums\Common\Status;
use App\Traits\Dispatchable;
use App\Enums\EmailProviderkey;
use App\Enums\DefaultTemplateSlug;
use App\Enums\System\ChannelTypeEnum;
use App\Services\System\TemplateService;

# SMTP (PHPMailer for direct SMTP, Symfony Mime for SES raw email)
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use App\Models\SendingDomain;

# SendGrid
use SendGrid;
use SendGrid\Mail\Mail;

# AWS
use Aws\Ses\SesClient;
use ErrorException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

# MailGun
use Mailgun\Mailgun;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class SendMail
{
    use Dispatchable, Manageable;

    protected $templateService;
    protected ?string $lastError = null;

    public function __construct()
    {
        $this->templateService = new TemplateService();
    }

    /**
     * Get the last error message from a failed send attempt.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * MailNotification
     *
     * @param Gateway $gateway
     * @param Template $template
     * @param Admin|User|Model $user
     * @param array|null $mailCode
     * 
     * @return bool
     */
    public function MailNotification(Gateway $gateway, Template $template, Admin|User|Model $user, array|null $mailCode = null): bool
    {
        // Guard: during installation / early boot the `templates` table may
        // not yet exist (customer error 42S02 "Base table or view not found
        // Table 'x_xs.templates' doesn't exist"). Silently no-op instead of
        // 500-ing the install flow — mail delivery is not critical during
        // the setup wizard.
        if (!\Illuminate\Support\Facades\Schema::hasTable('templates')) {
            \Log::info('SendMail::MailNotification skipped — templates table not yet created (fresh install).');
            return false;
        }

        $globalTemplate = $this->getSpecificLogByColumn(
            model: new Template(),
            column: 'slug',
            value: DefaultTemplateSlug::GLOBAL_TEMPLATE->value,
            attributes: [
                "user_id" => null,
                "channel" => ChannelTypeEnum::EMAIL->value,
                "global" => true,
                "default" => false,
                "status" => Status::ACTIVE->value
            ]
        );

        $messageBody = $this->templateService->processTemplate(template: $template, variables: $mailCode);
        
        $globalMailCode = [
            "name" => site_settings(SettingKey::SITE_NAME->value, "Xsender"),
            "message" => $messageBody
        ];
        $finalMessage = $this->templateService->processTemplate(template: $globalTemplate, variables: $globalMailCode);
        
        if (site_settings('email_notifications') == StatusEnum::TRUE->status() || site_settings('email_notifications') == Status::ACTIVE->value) {
            return $this->sendWithHandler($gateway, $user->email, Arr::get($template->template_data, "subject"), $finalMessage);
        }
        return false;
    }

    /**
     * send
     *
     * @param Gateway $gateway
     * @param array|string $to
     * @param array|string|null $subject
     * @param array|string|null $mailBody
     * @param array|DispatchLog|Collection|null $dispatchLog
     * 
     * @return bool
     */
    public function send(Gateway $gateway, array|string $to, array|string|null $subject = null, array|string|null $mailBody = null, array|DispatchLog|Collection|null $dispatchLog = null, ?array $attachments = null): bool
    {
        $mailBody = textSpinner($mailBody);
        return $this->sendWithHandler($gateway, $to, $subject, $mailBody, $dispatchLog, $attachments);
    }

    /**
     * sendWithHandler
     *
     * @param Gateway $gateway
     * @param array|string $to
     * @param array|string|null $subject
     * @param array|string|null $mailBody
     * @param array|DispatchLog|Collection|null $dispatchLog
     * 
     * @return bool
     */
    protected function sendWithHandler(Gateway $gateway, array|string $to, array|string|null $subject = null, array|string|null $mailBody = null, array|DispatchLog|Collection|null $dispatchLog = null, ?array $attachments = null): bool
    {
        $this->lastError = null;
        $creds = $this->getCredentials(ChannelTypeEnum::EMAIL, $gateway->type, $gateway);
        if (!$creds) {
            $this->lastError = translate("Gateway credentials are not available. Required meta_data fields may be missing.");
            if ($dispatchLog) $this->fail($dispatchLog, $this->lastError);
            return false;
        }

        try {
            $timeout = 200;

            $success = false;
            $providerMethod = match ($gateway->type) {
                EmailProviderkey::SMTP->value => fn () => $this->sendViaSmtp($gateway, $creds, $to, $subject, $mailBody, $dispatchLog, $attachments),
                EmailProviderkey::SENDGRID->value => fn () => $this->sendViaSendgrid($gateway, $creds, $to, $subject, $mailBody, $dispatchLog, $attachments),
                EmailProviderkey::AWS->value => fn () => $this->sendViaAws($gateway, $creds, $to, $subject, $mailBody, $dispatchLog, $attachments),
                EmailProviderkey::MAILJET->value => fn () => $this->sendViaMailjet($gateway, $creds, $to, $subject, $mailBody, $dispatchLog, $attachments),
                EmailProviderkey::MAILGUN->value => fn () => $this->sendViaMailgun($gateway, $creds, $to, $subject, $mailBody, $dispatchLog, $attachments),
                default => throw new \Exception("Unknown gateway type: {$gateway->type}"),
            };
    
            $result = null;
            set_time_limit($timeout);
            try {
                $result = $providerMethod();
                set_time_limit(0); 
            } catch (ErrorException $e) {
                if (str_contains($e->getMessage(), 'Maximum execution time')) {
                    throw new Exception("Provider timed out after {$timeout} seconds");
                }
                throw $e;
            }
    
            $success = $result;
    
            if ($success && $dispatchLog) {
                $this->markAsDelivered($dispatchLog);
            }
            return $success;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            if($dispatchLog) {
                $this->fail($dispatchLog, $e->getMessage());
            }
            return false;
        }
    }

    /**
     * sendViaSmtp
     *
     * @param Gateway $gateway
     * @param array $creds
     * @param string $to
     * @param array|string $subject
     * @param array|string $mailBody
     * @param array|DispatchLog|Collection|null $dispatchLog
     * 
     * @return bool
     */
    private function sendViaSmtp(Gateway $gateway, array $creds, string $to, array|string $subject, array|string $mailBody, array|DispatchLog|Collection|null $dispatchLog = null, ?array $attachments = null): bool
    {
        try {
            $host       = Arr::get($creds, "host");
            $port       = (int) Arr::get($creds, "port", 465);
            $encryption = Arr::get($creds, "encryption");
            $username   = Arr::get($creds, "username");
            $password   = Arr::get($creds, "password");

            $log = $dispatchLog instanceof Collection ? $dispatchLog->first() : $dispatchLog;
            $fromName = $log
                ? (Arr::get($log->meta_data, "email_from_name", $gateway->name) ?: $gateway->name)
                : $gateway->name;
            $replyTo = $log
                ? (Arr::get($log->meta_data, "reply_to_address", $gateway->address) ?: $gateway->address)
                : $gateway->address;

            $mail = new PHPMailer(true);

            // SMTP configuration
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->Port       = $port;
            $mail->SMTPAuth   = true;
            $mail->Username   = $username;
            $mail->Password   = $password;
            $mail->Timeout    = 30;
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;

            // Encryption
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif (in_array($encryption, ['tls', 'starttls'])) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Disable SSL peer verification for shared hosting compatibility
            // (e.g. mail.example.com resolving to *.hostgator.com certificate)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            // Sender and recipient
            $mail->setFrom($gateway->address, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($replyTo);

            // Content - multipart/alternative (text/plain + text/html)
            $mail->isHTML(true);
            $subjectText = is_array($subject) ? $subject[0] : $subject;
            $bodyHtml    = is_array($mailBody) ? $mailBody[0] : $mailBody;
            // List-Unsubscribe headers — apply to campaign AND ad-hoc emails for CAN-SPAM compliance
            $unsubscribeLink = $log ? Arr::get($log->meta_data, "unsubscribe_link") : null;
            if ($unsubscribeLink) {
                $bodyHtml = $this->injectUnsubscribeFooter($bodyHtml, $unsubscribeLink);
            }

            $mail->Subject = $subjectText;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));

            if ($unsubscribeLink) {
                // RFC 8058: HTTPS URL first so one-click POST bots read it before
                // the mailto fallback. Post header value must be exactly
                // `List-Unsubscribe=One-Click` (Gmail/Yahoo reject the bare "One-Click").
                $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubscribeLink . '>, <mailto:' . $gateway->address . '?subject=unsubscribe>');
                $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }

            // Attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    $filePath = $this->resolveAttachmentPath(Arr::get($attachment, 'url_file', ''));
                    if ($filePath) {
                        $mail->addAttachment(
                            $filePath,
                            Arr::get($attachment, 'name', basename($filePath)),
                            PHPMailer::ENCODING_BASE64,
                            Arr::get($attachment, 'mime_type', 'application/octet-stream')
                        );
                    }
                }
            }

            // DKIM signing via PHPMailer's built-in support
            $this->configureDkim($mail, $gateway);

            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            throw new Exception(translate("SMTP dispatch failed: ") . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception(translate("Unexpected error in SMTP dispatch: ") . $e->getMessage());
        }
    }

    /**
     * sendViaSendgrid
     *
     * @param Gateway $gateway
     * @param array $creds
     * @param array|string $to
     * @param array|string $subject
     * @param array|string $mailBody
     * @param array|DispatchLog|Collection|null $dispatchLog
     * 
     * @return bool
     */
    private function sendViaSendgrid(Gateway $gateway, array $creds, array|string $to, array|string $subject, array|string $mailBody, array|DispatchLog|Collection|null $dispatchLog = null, ?array $attachments = null): bool
    {
        $sendgrid = new SendGrid(Arr::get($creds, "secret_key"));
        $email = new Mail();

        $fromName = $dispatchLog
            ? ($dispatchLog instanceof Collection
                ? (Arr::get($dispatchLog->first()->meta_data, "email_from_name") ?: $gateway->name)
                : (Arr::get($dispatchLog->meta_data, "email_from_name") ?: $gateway->name))
            : $gateway->name;

        $email->setFrom($gateway->address, $fromName);
        $email->addTo($to);

        $email->setSubject($subject);
        $email->addContent("text/html", $mailBody);

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $filePath = $this->resolveAttachmentPath(Arr::get($attachment, 'url_file', ''));
                if ($filePath) {
                    $email->addAttachment(
                        base64_encode(file_get_contents($filePath)),
                        Arr::get($attachment, 'mime_type', 'application/octet-stream'),
                        Arr::get($attachment, 'name', basename($filePath)),
                        'attachment'
                    );
                }
            }
        }

        $result = $sendgrid->send($email);

        if ($result->statusCode() < 200 || $result->statusCode() >= 300) {
            $errorMessage = json_decode($result->body())->errors[0]->message ?? "SendGrid failed with status: " . $result->statusCode();
            throw new Exception($errorMessage);
        }

        return true;
    }

    /**
     * sendViaAws
     *
     * @param Gateway $gateway
     * @param array $creds
     * @param array|string $to
     * @param array|string $subject
     * @param array|string $mailBody
     * @param array|DispatchLog|Collection|null $dispatchLog
     * 
     * @return bool
     */
    private function sendViaAws(Gateway $gateway, array $creds, array|string $to, array|string $subject, array|string $mailBody, array|DispatchLog|Collection|null $dispatchLog = null, ?array $attachments = null): bool
    {
        $sesClient = new SesClient([
            'profile' => Arr::get($creds, "profile"),
            'version' => Arr::get($creds, "version"),
            'region' => Arr::get($creds, "region")
        ]);

        $charSet = 'UTF-8';
        $replyTo = $dispatchLog
            ? ($dispatchLog instanceof Collection
                ? (Arr::get($dispatchLog->first()->meta_data, "reply_to_address") ?: $gateway->address)
                : (Arr::get($dispatchLog->meta_data, "reply_to_address") ?: $gateway->address))
            : $gateway->address;

        $senderEmail = Arr::get($creds, "sender_email");
        $subjectText = is_array($subject) ? $subject[0] : $subject;
        $bodyHtml = is_array($mailBody) ? $mailBody[0] : $mailBody;
        $toAddresses = is_array($to) ? $to : [$to];

        if (!empty($attachments)) {
            $email = (new Email())
                ->from(new Address($senderEmail))
                ->subject($subjectText)
                ->text(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)))
                ->html($bodyHtml)
                ->replyTo($replyTo);

            foreach ($toAddresses as $recipient) {
                $email->addTo($recipient);
            }

            $this->attachFiles($email, $attachments);

            $sesClient->sendRawEmail([
                'Source' => $senderEmail,
                'RawMessage' => [
                    'Data' => $email->toString(),
                ],
            ]);
        } else {
            $sesClient->sendEmail([
                'Destination' => [
                    'ToAddresses' => $toAddresses,
                ],
                'ReplyToAddresses' => [$replyTo],
                'Source' => $senderEmail,
                'Message' => [
                    'Body' => [
                        'Html' => [
                            'Charset' => $charSet,
                            'Data' => $bodyHtml,
                        ],
                    ],
                    'Subject' => [
                        'Charset' => $charSet,
                        'Data' => $subjectText,
                    ],
                ],
                'ConfigurationSetName' => 'ConfigSet',
            ]);
        }

        return true;
    }

    /**
     * sendViaMailjet
     *
     * @param Gateway $gateway
     * @param array $creds
     * @param array|string $to
     * @param array|string $subject
     * @param array|string $mailBody
     * @param array|DispatchLog|Collection|null $dispatchLog
     * 
     * @return bool
     */
    private function sendViaMailjet(Gateway $gateway, array $creds, array|string $to, array|string $subject, array|string $mailBody, array|DispatchLog|Collection|null $dispatchLog = null, ?array $attachments = null): bool
    {
        $emailFrom = $dispatchLog
            ? ($dispatchLog instanceof Collection
                ? (Arr::get($dispatchLog->first()->meta_data, "email_from_name") ?: $gateway->name)
                : (Arr::get($dispatchLog->meta_data, "email_from_name") ?: $gateway->name))
            : $gateway->name;
        $replyTo = $dispatchLog
            ? ($dispatchLog instanceof Collection
                ? (Arr::get($dispatchLog->first()->meta_data, "reply_to_address") ?: $gateway->address)
                : (Arr::get($dispatchLog->meta_data, "reply_to_address") ?: $gateway->address))
            : $gateway->address;

        $mailjetAttachments = [];
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $filePath = $this->resolveAttachmentPath(Arr::get($attachment, 'url_file', ''));
                if ($filePath) {
                    $mailjetAttachments[] = [
                        'ContentType' => Arr::get($attachment, 'mime_type', 'application/octet-stream'),
                        'Filename'    => Arr::get($attachment, 'name', basename($filePath)),
                        'Base64Content' => base64_encode(file_get_contents($filePath)),
                    ];
                }
            }
        }

        $messages = is_array($to)
            ? array_map(function ($recipient) use ($subject, $mailBody, $replyTo, $emailFrom, $mailjetAttachments) {
                $msg = [
                    'From' => ['Email' => $replyTo, 'Name' => $emailFrom],
                    'To' => [['Email' => $recipient, 'Name' => explode('@', $recipient)[0]]],
                    'Subject' => is_array($subject) ? $subject[0] : $subject,
                    'TextPart' => ' ',
                    'HTMLPart' => is_array($mailBody) ? $mailBody[0] : $mailBody
                ];
                if (!empty($mailjetAttachments)) $msg['Attachments'] = $mailjetAttachments;
                return $msg;
            }, $to)
            : [array_merge([
                'From' => ['Email' => $replyTo, 'Name' => $emailFrom],
                'To' => [['Email' => $to, 'Name' => explode('@', $to)[0]]],
                'Subject' => $subject,
                'TextPart' => ' ',
                'HTMLPart' => $mailBody
            ], !empty($mailjetAttachments) ? ['Attachments' => $mailjetAttachments] : [])];

        $body = ['Messages' => $messages];
        $client = new Client(['base_uri' => 'https://api.mailjet.com/v3.1/']);
        $response = $client->request('POST', 'send', [
            'json' => $body,
            'auth' => [Arr::get($creds, "api_key"), Arr::get($creds, "secret_key")]
        ]);

        $responseBody = json_decode($response->getBody());
        if ($response->getStatusCode() != 200 || $responseBody->Messages[0]->Status != 'success') {
            $errorMessage = $responseBody->Messages[0]->Status ?? "Mailjet failed with status: " . $response->getStatusCode();
            throw new Exception($errorMessage);
        }

        return true;
    }

    /**
     * sendViaMailgun
     *
     * @param Gateway $gateway
     * @param array $creds
     * @param array|string $to
     * @param array|string $subject
     * @param array|string $mailBody
     * @param array|DispatchLog|Collection|null $dispatchLog
     * 
     * @return bool
     */
    private function sendViaMailgun(Gateway $gateway, array $creds, array|string $to, array|string $subject, array|string $mailBody, array|DispatchLog|Collection|null $dispatchLog = null, ?array $attachments = null): bool
    {
        $mailGun = Mailgun::create(Arr::get($creds, "secret_key"));
        $domain = Arr::get($creds, "verified_domain");

        $params = [
            'from' => $gateway->address,
            'to' => is_array($to) ? implode(',', $to) : $to,
            'subject' => is_array($subject) ? $subject[0] : $subject,
            'html' => is_array($mailBody) ? $mailBody[0] : $mailBody
        ];

        if (!empty($attachments)) {
            $mailgunAttachments = [];
            foreach ($attachments as $attachment) {
                $filePath = $this->resolveAttachmentPath(Arr::get($attachment, 'url_file', ''));
                if ($filePath) {
                    $mailgunAttachments[] = [
                        'filePath'  => $filePath,
                        'filename'  => Arr::get($attachment, 'name', basename($filePath)),
                    ];
                }
            }
            if (!empty($mailgunAttachments)) {
                $params['attachment'] = $mailgunAttachments;
            }
        }

        $result = $mailGun->messages()->send($domain, $params);

        return true;
    }

    /**
     * Attach files to a Symfony Email instance (used by SMTP and AWS SES raw email)
     *
     * @param Email $email
     * @param array|null $attachments
     * @return void
     */
    /**
     * Inject an unsubscribe footer into the HTML body so every commercial email
     * carries a visible opt-out path (CAN-SPAM / GDPR). If the body already contains
     * a {{unsubscribe_link}} placeholder, replace it instead of appending a footer.
     */
    protected function injectUnsubscribeFooter(string $bodyHtml, string $unsubscribeLink): string
    {
        $hasPlaceholder = str_contains($bodyHtml, '{{unsubscribe_link}}')
            || str_contains($bodyHtml, '%7B%7Bunsubscribe_link%7D%7D');

        if ($hasPlaceholder) {
            return str_replace(
                ['{{unsubscribe_link}}', '%7B%7Bunsubscribe_link%7D%7D'],
                $unsubscribeLink,
                $bodyHtml
            );
        }

        $footer = '<div style="text-align:center;padding:20px 0 10px;margin-top:20px;border-top:1px solid #e0e0e0;font-size:12px;color:#999;">'
            . '<p style="margin:0;">If you no longer wish to receive these emails, '
            . '<a href="' . $unsubscribeLink . '" style="color:#666;text-decoration:underline;">unsubscribe here</a>.</p>'
            . '</div>';

        if (stripos($bodyHtml, '</body>') !== false) {
            return str_ireplace('</body>', $footer . '</body>', $bodyHtml);
        }

        return $bodyHtml . $footer;
    }

    protected function attachFiles(Email $email, ?array $attachments): void
    {
        if (empty($attachments)) return;

        foreach ($attachments as $attachment) {
            $filePath = $this->resolveAttachmentPath(Arr::get($attachment, 'url_file', ''));
            if ($filePath) {
                $email->attachFromPath(
                    $filePath,
                    Arr::get($attachment, 'name', basename($filePath)),
                    Arr::get($attachment, 'mime_type')
                );
            }
        }
    }

    /**
     * Resolve attachment file path, supporting both private storage and legacy public paths
     */
    protected function resolveAttachmentPath(string $urlFile): ?string
    {
        if (empty($urlFile)) return null;

        if (str_starts_with($urlFile, 'storage:email_attachments/')) {
            $filename = str_replace('storage:email_attachments/', '', $urlFile);
            $disk = Storage::disk('email_attachments');
            return $disk->exists($filename) ? $disk->path($filename) : null;
        }

        // Backward compatibility: old public path
        return file_exists($urlFile) ? $urlFile : null;
    }

    /**
     * Configure DKIM signing on a PHPMailer instance using built-in PHPMailer DKIM support.
     */
    protected function configureDkim(PHPMailer $mail, Gateway $gateway): void
    {
        try {
            if (site_settings('dkim_enabled') != StatusEnum::TRUE->status()) {
                return;
            }

            $sendingDomain = SendingDomain::findForEmail($gateway->address, $gateway->user_id);

            if (!$sendingDomain || !$sendingDomain->isDkimConfigured()) {
                return;
            }

            $mail->DKIM_domain     = $sendingDomain->domain;
            $mail->DKIM_selector   = $sendingDomain->dkim_selector;
            $mail->DKIM_private    = $sendingDomain->dkim_private_key;
            $mail->DKIM_identity   = $mail->From;
        } catch (Exception $e) {
            Log::warning("DKIM configuration failed for {$gateway->address}: " . $e->getMessage());
        }
    }

    /**
     * Sign an email with DKIM if a verified sending domain exists for the gateway's address.
     * Adds DKIM-Signature header directly to the email (used by SES raw email path).
     *
     * @param Email $email
     * @param Gateway $gateway
     * @return void
     */
    protected function signWithDkim(Email $email, Gateway $gateway): void
    {
        try {
            if (site_settings('dkim_enabled') != StatusEnum::TRUE->status()) {
                return;
            }

            $sendingDomain = SendingDomain::findForEmail($gateway->address, $gateway->user_id);

            if (!$sendingDomain || !$sendingDomain->isDkimConfigured()) {
                return;
            }

            $signature = $this->generateDkimSignature(
                $email,
                $sendingDomain->dkim_private_key,
                $sendingDomain->domain,
                $sendingDomain->dkim_selector
            );

            if ($signature) {
                $email->getHeaders()->addTextHeader('DKIM-Signature', $signature);
            }
        } catch (Exception $e) {
            Log::warning("DKIM signing failed for {$gateway->address}: " . $e->getMessage());
        }
    }

    /**
     * Generate a DKIM signature for an email using relaxed/relaxed canonicalization.
     *
     * @param Email $email
     * @param string $privateKey PEM-encoded private key
     * @param string $domain Signing domain
     * @param string $selector DKIM selector
     * @return string|null The DKIM-Signature header value (without "DKIM-Signature: " prefix)
     */
    private function generateDkimSignature(Email $email, string $privateKey, string $domain, string $selector): ?string
    {
        // Headers to sign
        $signedHeaders = ['from', 'to', 'subject', 'date', 'mime-version', 'content-type'];

        // Get the email as string to extract headers and body
        $rawMessage = $email->toString();
        $parts = preg_split("/\r?\n\r?\n/", $rawMessage, 2);
        $headerBlock = $parts[0] ?? '';
        $body = $parts[1] ?? '';

        // Relaxed body canonicalization:
        // - Reduce all whitespace sequences to single space
        // - Remove trailing whitespace on lines
        // - Remove all empty lines at the end of body
        // - Ensure body ends with CRLF
        $bodyLines = explode("\n", str_replace("\r\n", "\n", $body));
        $canonBody = '';
        foreach ($bodyLines as $line) {
            $line = rtrim($line);
            $line = preg_replace('/\s+/', ' ', $line);
            $canonBody .= $line . "\r\n";
        }
        $canonBody = rtrim($canonBody, "\r\n") . "\r\n";

        // Body hash (SHA-256)
        $bodyHash = base64_encode(hash('sha256', $canonBody, true));

        // Build DKIM-Signature header value (without the signature, for signing)
        $timestamp = time();
        $dkimHeader = implode('; ', [
            'v=1',
            'a=rsa-sha256',
            'c=relaxed/relaxed',
            "d={$domain}",
            "s={$selector}",
            "t={$timestamp}",
            'h=' . implode(':', $signedHeaders),
            "bh={$bodyHash}",
            'b=',
        ]);

        // Relaxed header canonicalization for the signed headers:
        // - Convert header name to lowercase
        // - Unfold headers (remove CRLF before whitespace)
        // - Reduce all whitespace to single space
        // - Remove trailing whitespace
        $parsedHeaders = [];
        $currentHeader = '';
        foreach (explode("\n", str_replace("\r\n", "\n", $headerBlock)) as $line) {
            if (preg_match('/^\s/', $line) && $currentHeader) {
                $currentHeader .= ' ' . trim($line);
            } else {
                if ($currentHeader) {
                    $colonPos = strpos($currentHeader, ':');
                    if ($colonPos !== false) {
                        $name = strtolower(trim(substr($currentHeader, 0, $colonPos)));
                        $value = trim(substr($currentHeader, $colonPos + 1));
                        $parsedHeaders[$name] = $value;
                    }
                }
                $currentHeader = trim($line);
            }
        }
        if ($currentHeader) {
            $colonPos = strpos($currentHeader, ':');
            if ($colonPos !== false) {
                $name = strtolower(trim(substr($currentHeader, 0, $colonPos)));
                $value = trim(substr($currentHeader, $colonPos + 1));
                $parsedHeaders[$name] = $value;
            }
        }

        // Build canonical header string for signing
        $canonHeaders = '';
        foreach ($signedHeaders as $headerName) {
            if (isset($parsedHeaders[$headerName])) {
                $value = preg_replace('/\s+/', ' ', trim($parsedHeaders[$headerName]));
                $canonHeaders .= "{$headerName}:{$value}\r\n";
            }
        }

        // Append the DKIM-Signature header itself (relaxed canon)
        $dkimValue = preg_replace('/\s+/', ' ', trim($dkimHeader));
        $canonHeaders .= "dkim-signature:{$dkimValue}";

        // Sign with RSA-SHA256
        $privateKeyResource = openssl_pkey_get_private($privateKey);
        if (!$privateKeyResource) {
            Log::warning("DKIM: Invalid private key for domain {$domain}");
            return null;
        }

        $signature = '';
        $signed = openssl_sign($canonHeaders, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

        if (!$signed) {
            Log::warning("DKIM: openssl_sign failed for domain {$domain}");
            return null;
        }

        $signatureBase64 = base64_encode($signature);

        // Build final DKIM-Signature value with the actual signature
        return implode('; ', [
            'v=1',
            'a=rsa-sha256',
            'c=relaxed/relaxed',
            "d={$domain}",
            "s={$selector}",
            "t={$timestamp}",
            'h=' . implode(':', $signedHeaders),
            "bh={$bodyHash}",
            "b={$signatureBase64}",
        ]);
    }
}