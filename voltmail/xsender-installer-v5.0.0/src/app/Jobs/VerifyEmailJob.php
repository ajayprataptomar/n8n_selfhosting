<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ContactImport;
use Illuminate\Bus\Queueable;
use App\Services\Core\MailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\System\Contact\ContactService;
use App\Enums\System\EmailVerificationStatusEnum;
use Illuminate\Support\Arr;

class VerifyEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $contactImportId;
    protected $contactService;
    protected $mailService;

    public function __construct(int $contactImportId)
    {
        $this->contactImportId  = $contactImportId;
        $this->onQueue('verify-email');
        $this->contactService   = new ContactService();
        $this->mailService      = new MailService();
    }

    public function handle(): void
    {
        try {

            $contactImport = ContactImport::findOrFail($this->contactImportId);
            $groupId = $contactImport->group_id;
    
            $chunkSize = config('queue.batch_sizes.regular.verify_email.max');
    
            $contacts = LazyCollection::make(function () use ($groupId) {
                $query = Contact::where('group_id', $groupId)
                    ->whereNotNull('email_contact')
                    ->where('email_verification', EmailVerificationStatusEnum::PENDING->value)
                    ->select('id', 'email_contact', 'email_verification');
                yield from $query->cursor();
            });
    
            $processedCount = 0;
    
            $contacts->chunk($chunkSize)->each(function ($chunk) use ($contactImport, &$processedCount) {
                $updates = [];
    
                $chunk->each(function ($contact) use (&$updates) {
                    $email = $contact->email_contact;
                    $verificationResult = $this->mailService->verifyEmail($email);
    
                    $updates[$contact->id] = [
                        'email_verification' => $verificationResult['valid']
                            ? EmailVerificationStatusEnum::VERIFIED->value
                            : EmailVerificationStatusEnum::UNVERIFIED->value,
                    ];
                });
    
                foreach ($updates as $id => $data) {
                    Contact::where('id', $id)->update(['email_verification' => Arr::get($data, "email_verification", EmailVerificationStatusEnum::UNVERIFIED->value)]);
                }
    
                $contactImport->increment('processed_emails', $chunk->count());
            });
    
        } catch (\Throwable $e) {

            throw $e;
        }
    }
}