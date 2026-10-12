<?php
namespace App\Http\Utility;

use App\Enums\SettingKey;
use App\Enums\System\CommunicationStatusEnum;
use Exception;
use Mailgun\Mailgun;
use App\Models\User;
use GuzzleHttp\Client;
use Aws\Ses\SesClient;
use App\Models\EmailLog;
use App\Service\Admin\Dispatch\EmailService;
use App\Models\CampaignContact;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use SendGrid\Mail\TypeException;
use PHPMailer\PHPMailer\PHPMailer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SendEmail
{
    /**
     * @param $emailFrom
     * @param $sitename
     * @param $emailTo
     * @param $subject
     * @param $messages
     * @param $emailLog
     * @return void
     */
    public static function sendPHPMail($emailFrom, $sitename, $emailTo, $subject, $messages, $emailLog): void {

        $headers  = "From: $sitename <$emailFrom> \r\n";
        $headers .= "Reply-To: $sitename <$emailFrom> \r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";
        try {

            if($emailLog->contact_id) {

               $status = "Success";
            }
            @mail($emailTo, $subject, $messages, $headers);
            $meta_data = $emailLog->meta_data;
            $meta_data['delivered_at'] = Carbon::now()->toDayDateTimeString();
            $emailLog->meta_data = $meta_data;
            $emailLog->status = CommunicationStatusEnum::DELIVERED->value;
            $emailLog->save();
        } catch (\Exception $exception) {
            
            if($emailLog->user_id) {

                EmailService::updateEmailLogAndCredit($emailLog, $exception->getMessage());
            }
        }
    }
   
    /**
     * @param $emailFrom
     * @param $fromName
     * @param $emailTo
     * @param $replyTo
     * @param $subject
     * @param $messages
     * @param $emailLog
     * @return void
     */
    public static function sendSMTPMail($emailTo, $replyTo, $subject, $messages, $emailLog, $emailMethod, $emailFromName): void {

        try {
            $username   = Arr::get($emailMethod->meta_data, "username");
            $password   = Arr::get($emailMethod->meta_data, "password");
            $host       = Arr::get($emailMethod->meta_data, "host");
            $port       = (int) Arr::get($emailMethod->meta_data, "port", 465);
            $encryption = Arr::get($emailMethod->meta_data, "encryption");

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
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            // Sender and recipient
            $mail->setFrom($emailMethod->address, $emailFromName ?? $emailMethod->name);
            $mail->addAddress($emailTo);
            $mail->addReplyTo($replyTo ?? $emailMethod->address);

            // CAN-SPAM / GDPR opt-out — apply to campaign AND ad-hoc emails.
            $unsubscribeLink = $emailLog ? Arr::get($emailLog->meta_data, "unsubscribe_link") : null;
            if ($unsubscribeLink) {
                $messages = $this->injectUnsubscribeFooter($messages, $unsubscribeLink);
            }

            // Content - multipart/alternative (text/plain + text/html)
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $messages;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $messages));

            if ($unsubscribeLink) {
                $mail->addCustomHeader('List-Unsubscribe', '<mailto:' . $emailMethod->address . '?subject=unsubscribe>, <' . $unsubscribeLink . '>');
                $mail->addCustomHeader('List-Unsubscribe-Post', 'One-Click');
            }

            $mail->send();
            $emailLog->processed_at = Carbon::now();
            $emailLog->status = CommunicationStatusEnum::DELIVERED;
            $emailLog->save();

        } catch (Exception $exception) {
            Log::error("Email dispatch fail: " . $exception->getMessage());

            if($emailLog->user_id) {
                EmailService::updateEmailLogAndCredit($emailLog, CommunicationStatusEnum::FAIL->value, $exception->getMessage());
            } else {
                $emailLog->status = CommunicationStatusEnum::FAIL;
                $emailLog->response_message = !is_null($exception->getMessage()) ? $exception->getMessage() : null;
                $emailLog->processed_at = Carbon::now();

                $emailLog->save();
            }
        }
    }

    /**
     * @param $emailFrom
     * @param $fromName
     * @param $emailTo
     * @param $subject
     * @param $messages
     * @param $emailLog
     * @param $credentials
     * @return void
     * @throws TypeException
     */
    public static function sendGrid($emailFrom, $fromName, $emailTo, $subject, $messages, $emailLog, $credentials): void {

        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($emailFrom, $fromName);
        $email->addTo($emailTo);
        $email->setSubject($subject);
        $email->addContent("text/html", $messages);
        $sendgrid = new \SendGrid(@$credentials);

        try {

            $response = $sendgrid->send($email);
            if (!in_array($response->statusCode(), ['201','200','202'])) {

                $emailLog->status = EmailLog::FAILED;
                $emailLog->response_gateway = "Error";
                $emailLog->save();
                $user = User::find($emailLog->user_id);
                if ($user != '') {

                    $user->email_credit += 1;
                    $user->save();
                }
            } else {
                $meta_data = $emailLog->meta_data;
                $meta_data['delivered_at'] = Carbon::now()->toDayDateTimeString();
                $emailLog->meta_data = $meta_data;
                $emailLog->status = CommunicationStatusEnum::DELIVERED->value;
                $emailLog->save();
            }
        } catch (\Exception $exception) {
            \Log::error("Email dispatch fail: " . $exception->getMessage());
            if($emailLog->user_id) {
                EmailService::updateEmailLogAndCredit($emailLog, $exception->getMessage());
            } else {
                $emailLog->status = CommunicationStatusEnum::FAIL->value;
                $emailLog->response_message = !is_null($exception->getMessage()) ? $exception->getMessage() : null;
                $emailLog->save();
            }
            
        }
    }

    /**
     * @param $emailFrom
     * @param $emailTo
     * @param $fromName
     * @param $subject
     * @param $messages
     * @return string|void
     */
    public static function sendMailJetMail($emailTo, $subject, $messages, $emailLog, $gateway, $email_reply_to = null, $email_from_name = null) {

        $mailCredential = $gateway->mail_gateways;
        $result         = null;
        $emailParts     = explode('@', $emailTo);
        $receiver       = $emailParts[0];
       
        try {

            $body = [
                'Messages' => [
                    [
                    'From' => [
                        'Email' => $email_reply_to ? $email_reply_to : $gateway->address,
                        'Name'  => $email_from_name ? $email_from_name :$gateway->name
                    ],
                    'To' => [
                        [
                            'Email' => $emailTo,
                            'Name'  => $receiver
                        ]
                    ],
                    'Subject'  => $subject,
                    "TextPart" => " ",
                    'HTMLPart' => $messages
                    ]
                ]
            ];
            $client = new Client([
                'base_uri' => 'https://api.mailjet.com/v3.1/',
            ]);
 
            $response = $client->request('POST', 'send', [
                'json' => $body,
                'auth' => [$mailCredential->api_key, $mailCredential->secret_key]
            ]);
            if($response->getStatusCode() == 200) {
                $body = $response->getBody();
                $response = json_decode($body);
                
                if ($response->Messages[0]->Status == 'success') {
                    if ($emailLog->contact_id) {
                        $status = "Success";
                    }
                    $meta_data = $emailLog->meta_data;
                    $meta_data['delivered_at'] = Carbon::now()->toDayDateTimeString();
                    $emailLog->meta_data = $meta_data;
                    $emailLog->status = CommunicationStatusEnum::DELIVERED->value;
                    $emailLog->save();
                    
                    Artisan::call('optimize:clear');
                    \Illuminate\Support\Facades\Artisan::call('queue:restart');
                }
                else {
                    if($emailLog->user_id) {
                        EmailService::updateEmailLogAndCredit($emailLog, translate("Email could not be sent"));
                    } else {
                        $emailLog->status = CommunicationStatusEnum::FAIL->value;
                        $emailLog->response_message = translate("Email could not be sent");
                        $emailLog->save();
                    }
                }
            }
        } catch (\Exception $exception) {

            \Log::error("Email dispatch fail: " . $exception->getMessage());
            if($emailLog->user_id) {

                EmailService::updateEmailLogAndCredit($emailLog, $exception->getMessage());
            } else {
                $emailLog->status = CommunicationStatusEnum::FAIL->value;
                $emailLog->response_message = !is_null($exception->getMessage()) ? $exception->getMessage() : null;
                $emailLog->save();
            }
            
        }
    }

    /**
     * send mail using ses
     *
     */
    public static function sendSesMail($recipient_emails, $subject, $messages, $emailLog, $gateway) {

        $result = null;
        $mailCredential = $gateway->mail_gateways;
       
        try {
            $SesClient = new SesClient([
                'profile' => $mailCredential->profile,
                'version' => $mailCredential->version,
                'region'  => $mailCredential->region
            ]);
            $sender_email = $gateway->address;
            $configuration_set = 'ConfigSet';
            $html_body = $messages;
            $char_set = 'UTF-8';
            $result = $SesClient->sendEmail([
                'Destination' => [
                    'ToAddresses' => $recipient_emails,
                ],
                'ReplyToAddresses' => [$sender_email],
                'Source'           => $sender_email,
                'Message'          => [
                    'Body' => [
                        'Html' => [
                            'Charset' => $char_set,
                            'Data'    => $html_body,
                        ],
                    ],
                    'Subject' => [
                        'Charset' => $char_set,
                        'Data'    => $subject,
                    ],
                ],
                'ConfigurationSetName' => $configuration_set,
            ]);
            \Log::info("Amazon Result Log: " . $result);
            $meta_data = $emailLog->meta_data;
            $meta_data['delivered_at'] = Carbon::now()->toDayDateTimeString();
            $emailLog->meta_data = $meta_data;
            $emailLog->status = CommunicationStatusEnum::DELIVERED->value;
            $emailLog->save();
            
        } catch (\Exception $exception) {
            \Log::error("Email dispatch fail: " . $exception->getMessage());
           
            if($emailLog->user_id) {

                EmailService::updateEmailLogAndCredit($emailLog, CommunicationStatusEnum::FAIL->value, $exception->getMessage());
            } else {
                $emailLog->status = CommunicationStatusEnum::FAIL->value;
                $emailLog->response_message = !is_null($exception->getMessage()) ? $exception->getMessage() : null;
                $emailLog->save();
            }
        }
    }

    /**
     * send mail using MailGun
     *
     * @param $details , $email
     */
    public static function sendMailGunMail($recipient_email, $subject, $messages, $emailLog, $gateway) {
        
        $result         = null;
        $mailCredential = $gateway->mail_gateways;
        $mailGun        = Mailgun::create($mailCredential->secret_key);
        $domain         = $mailCredential->verified_domain;
        try {

            $result =  $mailGun->messages()->send( $domain, [
                'from'    => $gateway->address,
                'to'      => $recipient_email,
                'subject' => $subject,
                'html'    => $messages
            ]);

            \Log::info("mailGun Result Log: " . $result);
            $meta_data = $emailLog->meta_data;
            $meta_data['delivered_at'] = Carbon::now()->toDayDateTimeString();
            $emailLog->meta_data = $meta_data;
            $emailLog->status = CommunicationStatusEnum::DELIVERED->value;
            $emailLog->save();

        } catch (\Exception $exception) {

            \Log::error("Email dispatch fail: " . $exception->getMessage());
            if($emailLog->user_id) {

                EmailService::updateEmailLogAndCredit($emailLog, CommunicationStatusEnum::FAIL->value, $exception->getMessage());
            } else {
                $emailLog->status = CommunicationStatusEnum::FAIL->value;
                $emailLog->response_message = !is_null($exception->getMessage()) ? $exception->getMessage() : null;
                $emailLog->save();
            }
        }
    }

    /**
     * Inject an unsubscribe footer into the HTML body. If the body already contains a
     * {{unsubscribe_link}} placeholder, replace it. Otherwise append a compliant footer.
     * Required for CAN-SPAM / GDPR on commercial email — campaigns and ad-hoc both.
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
}
