<?php

namespace App\Jobs;

use App\Enums\System\LeadScrapingTypeEnum;
use App\Models\LeadScrapingJob;
use App\Services\LeadGeneration\GoogleMapsScraperService;
use App\Services\LeadGeneration\WebsiteScraperService;
use App\Services\LeadGeneration\LeadEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLeadScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $jobId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;
        $this->onQueue('lead-scraping');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $job = LeadScrapingJob::find($this->jobId);

        if (!$job) {
            Log::error('Lead scraping job not found', ['job_id' => $this->jobId]);
            return;
        }

        // Skip if already processed
        if ($job->isCompleted() || $job->isFailed()) {
            return;
        }

        try {
            $service = $this->getServiceForType($job->type);

            if (!$service) {
                $job->markAsFailed('Unknown scraping type: ' . $job->type->value);
                return;
            }

            $service->processJob($job);

        } catch (\Exception $e) {
            Log::error('Lead scraping job failed', [
                'job_id' => $this->jobId,
                'type'   => $job->type->value,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);

            $job->markAsFailed($e->getMessage());

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Get the appropriate service for the job type.
     */
    protected function getServiceForType(LeadScrapingTypeEnum $type): ?object
    {
        return match ($type) {
            LeadScrapingTypeEnum::GOOGLE_MAPS => new GoogleMapsScraperService(),
            LeadScrapingTypeEnum::WEBSITE     => new WebsiteScraperService(),
            LeadScrapingTypeEnum::ENRICHMENT  => new LeadEnrichmentService(),
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $job = LeadScrapingJob::find($this->jobId);

        if ($job) {
            $job->markAsFailed($exception->getMessage());
        }

        Log::error('Lead scraping job permanently failed', [
            'job_id' => $this->jobId,
            'error'  => $exception->getMessage(),
        ]);
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(1);
    }
}
