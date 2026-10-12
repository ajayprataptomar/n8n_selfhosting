<?php

namespace App\Http\Controllers;

use App\Jobs\TriggerQueueWorker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;

class QueueController extends Controller
{
    /**
     * Process all queues in priority order (synchronous, legacy behavior).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processAllQueues(): JsonResponse
    {
        if (Session::get('queue_restart', true)) {
            Artisan::call('queue:restart');
            Session::forget('queue_restart');
        }

        $queues = implode(',', [
            'default',
            'dispatch-logs',
            'regular-email',
            'regular-sms',
            'regular-whatsapp',
            'campaign-email',
            'campaign-sms',
            'campaign-whatsapp',
            'chat-whatsapp',
            'import-contacts',
            'verify-email',
            'data_migration',
            'worker-trigger'
        ]);

        Artisan::call('queue:work', [
            '--queue' => $queues,
            '--stop-when-empty' => true,
            '--tries' => 3,
            '--timeout' => 90, 
        ]);

        return response()->json(['status' => 'Queue processing triggered']);
    }

    /**
     * Process regular-sms queue asynchronously.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processRegularSms():JsonResponse
    {
        TriggerQueueWorker::dispatch('regular-sms')->onQueue('worker-trigger');

        return response()->json(['status' => 'Regular SMS queue processing queued']);
    }

    /**
     * Process regular-email queue asynchronously.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processRegularEmail():JsonResponse
    {
        TriggerQueueWorker::dispatch('regular-email')->onQueue('worker-trigger');

        return response()->json(['status' => 'Regular Email queue processing queued']);
    }

    /**
     * Process regular-whatsapp queue asynchronously.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processRegularWhatsapp():JsonResponse
    {
        TriggerQueueWorker::dispatch('regular-whatsapp')->onQueue('worker-trigger');

        return response()->json(['status' => 'Regular WhatsApp queue processing queued']);
    }

    /**
     * Process campaign-sms queue asynchronously.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processCampaignSms():JsonResponse
    {
        TriggerQueueWorker::dispatch('campaign-sms')->onQueue('worker-trigger');

        return response()->json(['status' => 'Campaign SMS queue processing queued']);
    }

    /**
     * Process campaign-email queue asynchronously.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processCampaignEmail():JsonResponse
    {
        TriggerQueueWorker::dispatch('campaign-email')->onQueue('worker-trigger');

        return response()->json(['status' => 'Campaign Email queue processing queued']);
    }

    /**
     * Process campaign-whatsapp queue asynchronously.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processCampaignWhatsapp():JsonResponse
    {
        TriggerQueueWorker::dispatch('campaign-whatsapp')->onQueue('worker-trigger');

        return response()->json(['status' => 'Campaign WhatsApp queue processing queued']);
    }

    /**
     * processContactImport
     *
     * @return JsonResponse
     */
    public function processContactImport():JsonResponse
    {
        TriggerQueueWorker::dispatch('import-contacts')->onQueue('worker-trigger');

        return response()->json(['status' => 'Contact Import queue processing queued']);
    }

    /**
     * processEmailVerify
     *
     * @return JsonResponse
     */
    public function processEmailVerify():JsonResponse
    {
        TriggerQueueWorker::dispatch('verify-email')->onQueue('worker-trigger');

        return response()->json(['status' => 'Email verify queue processing queued']);
    }

    public function processDispatchlogs():JsonResponse
    {
        TriggerQueueWorker::dispatch('dispatch-logs')->onQueue('worker-trigger');

        return response()->json(['status' => 'Dispatch Log queue processing queued']);
    }

    public function processDefault():JsonResponse
    {
        TriggerQueueWorker::dispatch('default')->onQueue('worker-trigger');

        return response()->json(['status' => 'Default queue processing queued']);
    }

    /**
     * Process chat-whatsapp queue asynchronously (for real-time chat messages).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processChatWhatsapp():JsonResponse
    {
        TriggerQueueWorker::dispatch('chat-whatsapp')->onQueue('worker-trigger');

        return response()->json(['status' => 'Chat WhatsApp queue processing queued']);
    }
}