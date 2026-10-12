<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\IncomingApi\SmsController;
use App\Http\Controllers\Api\IncomingApi\EmailController;
use App\Http\Controllers\Api\IncomingApi\WhatsAppController;
use App\Http\Controllers\Api\IncomingApi\NodeWhatsAppWebhookController;
use App\Http\Controllers\Api\Communication\SmsDispatchController;
use App\Http\Controllers\Api\Communication\Gateway\Android\SimController;
use App\Http\Controllers\Api\Communication\Gateway\Android\SessionController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

## ---------------------------- ##
##   Android App API Endpoints  ##
## ---------------------------- ##

Route::middleware(['android.gateway.token'])->group(function () {
    Route::prefix('gateway')
            ->name('gateway.')
            ->group(function () {
        Route::prefix('sms')
                ->name('sms.')
                ->group(function () {
            Route::prefix('android')
                    ->name('android.')
                    ->group(function () {
                Route::post('register-session', [SessionController::class, 'registerSession'])
                        ->name('register-session')
                        ->withoutMiddleware(['android.gateway.token']);
                Route::post('logout', [SessionController::class, 'logout'])
                        ->name('logout');

                Route::prefix('sim')
                        ->name('sim.')
                        ->group(function () {

                    Route::post('update-status', [SimController::class, 'updateStatus'])->name('update-status');
                });

                Route::apiResource('sim', SimController::class)
                        ->except(['show']) 
                        ->names([
                    'store'     => 'sim.store',
                    'update'    => 'sim.update',
                    'destroy'   => 'sim.delete',
                ]);
            });
        });
    });
    
    Route::prefix('logs')
            ->name('logs.')
            ->group(function () {
        Route::prefix('sms')
                ->name('sms.')
                ->group(function () {
            Route::get('pending', [SmsDispatchController::class, 'fetchPending'])
                ->name('pending');
            Route::post('update-statuses', [SmsDispatchController::class, 'updateStatuses'])
                ->name('update-statuses');
        });
    });
});

## ------------------- ##
##   Old Xsender API   ##
## ------------------- ##

Route::middleware(['incoming.api', 'sanitizer'])->name('incoming.')->group(function () {

    Route::post('email/send', [EmailController::class, 'store'])->name('email.send');
    Route::get('email/send', [EmailController::class, 'sendWithQuery'])->name('email.send.query');
    Route::get('get/email/{id?}', [EmailController::class, 'getEmailLog']);

    Route::post('sms/send', [SmsController::class, 'store'])->name('sms.send');
    Route::get('sms/send', [SmsController::class, 'sendWithQuery'])->name('sms.send.query');
    Route::get('get/sms/{id?}', [SmsController::class, 'getSmsLog']);

    Route::post('whatsapp/send', [WhatsAppController::class, 'store'])->name('whatsapp.send');
    Route::get('whatsapp/send', [WhatsAppController::class, 'sendWithQuery'])->name('whatsapp.send.query');
    Route::get('get/whatsapp/{id?}', [WhatsAppController::class, 'getWhatsAppLog']);
});

## -------------------------------- ##
##   Email Provider Webhooks        ##
## -------------------------------- ##

use App\Http\Controllers\Api\Webhook\EmailWebhookController;

Route::prefix('webhook/email')->name('webhook.email.')->withoutMiddleware(['throttle:api'])->group(function () {
    Route::post('sendgrid', [EmailWebhookController::class, 'sendgrid'])->name('sendgrid');
    Route::post('ses', [EmailWebhookController::class, 'ses'])->name('ses');
    Route::post('mailgun', [EmailWebhookController::class, 'mailgun'])->name('mailgun');
    Route::post('mailjet', [EmailWebhookController::class, 'mailjet'])->name('mailjet');
});

## -------------------------------- ##
##   Meta Lead Ads Webhook          ##
## -------------------------------- ##

use App\Http\Controllers\Api\Webhook\MetaLeadAdsWebhookController;

Route::prefix('webhook/meta-lead-ads')->name('webhook.meta_lead_ads.')->withoutMiddleware(['throttle:api'])->group(function () {
    Route::get('/',  [MetaLeadAdsWebhookController::class, 'verify'])->name('verify');
    Route::post('/', [MetaLeadAdsWebhookController::class, 'receive'])->name('receive');
});

## -------------------------------- ##
##   Google Ads Lead Form Webhook   ##
## -------------------------------- ##

use App\Http\Controllers\Api\Webhook\GoogleAdsLeadWebhookController;

Route::post('/webhook/google-ads-lead', [GoogleAdsLeadWebhookController::class, 'receive'])
    ->name('webhook.google_ads_lead.receive')
    ->withoutMiddleware(['throttle:api']);

## -------------------------------- ##
##   Node WhatsApp Webhook Route    ##
## -------------------------------- ##

Route::post('whatsapp/node/webhook', [NodeWhatsAppWebhookController::class, 'webhook'])
    ->name('whatsapp.node.webhook')
    ->withoutMiddleware(['throttle:api']);

## ---------------------------------------- ##
##   WhatsApp Session Status Webhook        ##
## ---------------------------------------- ##

use App\Http\Controllers\Api\WhatsappSessionWebhookController;

## ---------------------------------------- ##
##   Automation Webhook Trigger Route       ##
## ---------------------------------------- ##

Route::post('automation/webhook/{id}', [\App\Http\Controllers\Api\Automation\AutomationWebhookController::class, 'trigger'])
    ->name('automation.webhook')
    ->withoutMiddleware(['throttle:api']);

Route::prefix('whatsapp/session')->name('whatsapp.session.')->withoutMiddleware(['throttle:api'])->group(function () {
    // Node service calls this when session status changes
    Route::post('status', [WhatsappSessionWebhookController::class, 'sessionStatus'])->name('status');
    // Node service calls this on startup to sync all sessions
    Route::post('sync', [WhatsappSessionWebhookController::class, 'syncAll'])->name('sync');
    // Get gateway status by session ID
    Route::get('status/{sessionId}', [WhatsappSessionWebhookController::class, 'getStatus'])->name('get');
});