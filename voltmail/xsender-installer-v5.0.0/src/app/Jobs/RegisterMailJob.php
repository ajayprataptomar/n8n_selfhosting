<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Gateway;
use App\Models\Template;
use App\Enums\StatusEnum;
use App\Traits\Manageable;
use App\Enums\Common\Status;
use Illuminate\Bus\Queueable;
use App\Http\Utility\SendMail;
use App\Enums\DefaultTemplateSlug;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RegisterMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Manageable;

    public User $user;
    public string $emailTemplate;
    public array $mailCode;
    protected $sendMail;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, string $emailTemplate, array $mailCode)
    {
        $this->user = $user;
        $this->emailTemplate = $emailTemplate;
        $this->mailCode = $mailCode;
        $this->sendMail = new SendMail();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $gateway = $this->getSpecificLogByColumn(
            model: new Gateway(), 
            column: "is_default",
            value: StatusEnum::TRUE->status(),
            attributes: [
                 "user_id" => null,
                 "channel" => ChannelTypeEnum::EMAIL->value,
            ]
        );

        $template = $this->getSpecificLogByColumn(
            model: new Template(), 
            column: "slug",
            value: $this->emailTemplate,
            attributes: [
                "user_id" => null,
                "channel" => ChannelTypeEnum::EMAIL->value,
                "default" => true,
                "status"  => Status::ACTIVE->value
            ]
        );

        if($gateway && $template) $this->sendMail->MailNotification($gateway, $template, $this->user, $this->mailCode);
    }
}
