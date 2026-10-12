<?php

namespace App\Http\Controllers\User\LeadGeneration;

use Exception;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;
use App\Traits\ModelAction;
use App\Models\LeadScrapingJob;
use App\Models\ScrapedLead;
use App\Models\LeadGenerationSetting;
use App\Models\ContactGroup;
use App\Models\Contact;
use App\Enums\System\LeadScrapingTypeEnum;
use App\Enums\System\LeadScrapingStatusEnum;
use App\Jobs\ProcessLeadScrapingJob;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadScraperController extends Controller
{
    use ModelAction;

    /**
     * Get current user
     */
    protected function user()
    {
        return Auth::user();
    }

    /**
     * Display lead generation dashboard
     */
    public function index(): View
    {
        Session::put("menu_active", true);

        $user = $this->user();

        $title = translate("Lead Generation");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('user.dashboard')],
            ['name' => translate('Lead Generation')],
        ];

        // Check if user's plan allows lead generation
        $canUseLead = $this->checkPlanAccess();

        // Initialize with defaults in case of database errors
        $jobs = collect();
        $stats = [
            'total_leads'      => 0,
            'total_jobs'       => 0,
            'pending_jobs'     => 0,
            'leads_with_email' => 0,
            'leads_with_phone' => 0,
            'imported_leads'   => 0,
        ];
        $settings = null;

        // Only query database if user has access
        if ($canUseLead) {
            try {
                // Get recent jobs
                $jobs = LeadScrapingJob::where('user_id', $user->id)
                    ->orderByDesc('created_at')
                    ->paginate(paginateNumber());

                // Get statistics
                $stats = [
                    'total_leads'      => ScrapedLead::where('user_id', $user->id)->count(),
                    'total_jobs'       => LeadScrapingJob::where('user_id', $user->id)->count(),
                    'pending_jobs'     => LeadScrapingJob::where('user_id', $user->id)->pending()->count(),
                    'leads_with_email' => ScrapedLead::where('user_id', $user->id)->withEmail()->count(),
                    'leads_with_phone' => ScrapedLead::where('user_id', $user->id)->withPhone()->count(),
                    'imported_leads'   => ScrapedLead::where('user_id', $user->id)->imported()->count(),
                ];

                $settings = LeadGenerationSetting::getForUser($user->id);
            } catch (Exception $e) {
                // Log error but continue with defaults
                \Illuminate\Support\Facades\Log::error('Lead Generation Error: ' . $e->getMessage());
            }
        }

        return view('user.lead-generation.index', compact(
            'title', 'breadcrumbs', 'jobs', 'stats', 'settings', 'canUseLead'
        ));
    }

    /**
     * Check if user's plan allows lead generation
     */
    protected function checkPlanAccess(): bool
    {
        $user = $this->user();

        // Check if user has an active subscription
        $subscription = $user->runningSubscription();
        if (!$subscription) {
            return false;
        }

        // Get plan access
        $planAccess = planAccess($user);

        // Check if lead_generation is allowed in the plan
        if (isset($planAccess['lead_generation']) && isset($planAccess['lead_generation']['is_allowed'])) {
            return (bool) $planAccess['lead_generation']['is_allowed'];
        }

        return false;
    }

    /**
     * Get lead generation limits from plan
     */
    protected function getPlanLimits(): array
    {
        $user = $this->user();
        $planAccess = planAccess($user);

        return [
            'daily_limit' => $planAccess['lead_generation']['daily_limit'] ?? 0,
            'monthly_limit' => $planAccess['lead_generation']['monthly_limit'] ?? 0,
        ];
    }

    /**
     * Show the scraper wizard
     */
    public function scraper(string $type = 'google_maps'): View
    {
        if (!$this->checkPlanAccess()) {
            return redirect()->route('user.lead-generation.index')
                ->with('error', translate('Your plan does not include lead generation features'));
        }

        Session::put("menu_active", true);

        $typeEnum = LeadScrapingTypeEnum::tryFrom($type) ?? LeadScrapingTypeEnum::GOOGLE_MAPS;

        $title = translate("New Lead Scraping Job");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('user.dashboard')],
            ['name' => translate('Lead Generation'), 'url' => route('user.lead-generation.index')],
            ['name' => $typeEnum->label()],
        ];

        $settings = LeadGenerationSetting::getForUser($this->user()->id);

        // Get contact groups for auto-save option
        $groups = ContactGroup::where('user_id', $this->user()->id)
            ->orderBy('name')
            ->get();

        return view('user.lead-generation.scraper', compact(
            'title', 'breadcrumbs', 'typeEnum', 'settings', 'groups'
        ));
    }

    /**
     * Start a new scraping job
     */
    public function startJob(Request $request): JsonResponse
    {
        try {
            if (!$this->checkPlanAccess()) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Your plan does not include lead generation features'),
                ], Response::HTTP_FORBIDDEN);
            }

            $validated = $request->validate([
                'type'     => 'required|in:google_maps,website,enrichment',
                'query'    => 'required_if:type,google_maps|string|max:255',
                'location' => 'required_if:type,google_maps|string|max:255',
                'urls'     => 'required_if:type,website|string',
                'lead_ids' => 'required_if:type,enrichment|array',
                'max_results' => 'nullable|integer|min:1|max:60',
            ]);

            $user = $this->user();
            $settings = LeadGenerationSetting::getForUser($user->id);

            // Check quota
            $maxResults = $validated['max_results'] ?? 60;
            if (!$settings->canScrape($maxResults)) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Daily or monthly scraping limit reached. Please try again later.'),
                    'data'    => [
                        'remaining_daily'   => $settings->remaining_daily,
                        'remaining_monthly' => $settings->remaining_monthly,
                    ],
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }

            // Prepare parameters based on type
            $params = match ($validated['type']) {
                'google_maps' => [
                    'query'       => $validated['query'],
                    'location'    => $validated['location'],
                    'max_results' => $maxResults,
                ],
                'website' => [
                    'urls'              => $validated['urls'],
                    'max_pages_per_site' => 5,
                ],
                'enrichment' => [
                    'lead_ids' => $validated['lead_ids'],
                ],
            };

            // Create job
            $job = LeadScrapingJob::create([
                'user_id'    => $user->id,
                'type'       => $validated['type'],
                'parameters' => $params,
                'status'     => LeadScrapingStatusEnum::PENDING,
            ]);

            // Dispatch queue job
            ProcessLeadScrapingJob::dispatch($job->id);

            return response()->json([
                'status'  => true,
                'message' => translate('Scraping job started successfully'),
                'data'    => [
                    'job_id'  => $job->uid,
                    'job_url' => route('user.lead-generation.job.status', $job->uid),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get job status
     */
    public function jobStatus(string $uid): JsonResponse
    {
        try {
            $job = LeadScrapingJob::where('uid', $uid)
                ->where('user_id', $this->user()->id)
                ->firstOrFail();

            return response()->json([
                'status' => true,
                'data'   => [
                    'id'              => $job->uid,
                    'type'            => $job->type->label(),
                    'status'          => $job->status->value,
                    'status_label'    => $job->status->label(),
                    'total_found'     => $job->total_found,
                    'processed_count' => $job->processed_count,
                    'progress'        => $job->progress,
                    'error_message'   => $job->error_message,
                    'started_at'      => $job->started_at?->format('Y-m-d H:i:s'),
                    'completed_at'    => $job->completed_at?->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => translate('Job not found'),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * View job results
     */
    public function results(Request $request, string $uid): View
    {
        Session::put("menu_active", true);

        $job = LeadScrapingJob::where('uid', $uid)
            ->where('user_id', $this->user()->id)
            ->firstOrFail();

        $title = translate("Scraping Results");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('user.dashboard')],
            ['name' => translate('Lead Generation'), 'url' => route('user.lead-generation.index')],
            ['name' => translate('Results')],
        ];

        $query = $job->leads();

        // Apply filters
        if ($request->filled('has_email')) {
            $query->withEmail();
        }
        if ($request->filled('has_phone')) {
            $query->withPhone();
        }
        if ($request->filled('not_imported')) {
            $query->notImported();
        }
        if ($request->filled('min_quality')) {
            $query->minQuality((int) $request->min_quality);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $leads = $query->orderByDesc('quality_score')
            ->paginate(paginateNumber());

        // Statistics for this job
        $jobStats = [
            'total'       => $job->leads()->count(),
            'with_email'  => $job->leads()->withEmail()->count(),
            'with_phone'  => $job->leads()->withPhone()->count(),
            'imported'    => $job->leads()->imported()->count(),
            'high_quality'=> $job->leads()->minQuality(80)->count(),
        ];

        // Get user's contact groups
        $groups = ContactGroup::where('user_id', $this->user()->id)
            ->orderBy('name')
            ->get();

        return view('user.lead-generation.results', compact(
            'title', 'breadcrumbs', 'job', 'leads', 'groups', 'jobStats'
        ));
    }

    /**
     * View all scraped leads
     */
    public function leads(Request $request): View
    {
        Session::put("menu_active", true);

        $title = translate("My Leads");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('user.dashboard')],
            ['name' => translate('Lead Generation'), 'url' => route('user.lead-generation.index')],
            ['name' => translate('All Leads')],
        ];

        $query = ScrapedLead::where('user_id', $this->user()->id);

        // Apply filters
        if ($request->has('has_email')) {
            $query->withEmail();
        }
        if ($request->has('has_phone')) {
            $query->withPhone();
        }
        if ($request->has('not_imported')) {
            $query->notImported();
        }
        if ($request->filled('min_quality')) {
            $query->minQuality((int) $request->min_quality);
        }

        $leads = $query->orderByDesc('created_at')
            ->paginate(paginateNumber());

        $groups = ContactGroup::where('user_id', $this->user()->id)
            ->orderBy('name')
            ->get();

        return view('user.lead-generation.leads', compact(
            'title', 'breadcrumbs', 'leads', 'groups'
        ));
    }

    /**
     * Export leads to Excel/CSV
     */
    public function exportLeads(Request $request): StreamedResponse
    {
        $user = $this->user();

        $validated = $request->validate([
            'job_id'     => 'nullable|exists:lead_scraping_jobs,id',
            'lead_ids'   => 'nullable|array',
            'lead_ids.*' => 'integer|exists:scraped_leads,id',
            'format'     => 'nullable|in:csv,excel',
        ]);

        $query = ScrapedLead::where('user_id', $user->id);

        // Filter by job or specific leads
        if (!empty($validated['job_id'])) {
            $query->where('job_id', $validated['job_id']);
        } elseif (!empty($validated['lead_ids'])) {
            $query->whereIn('id', $validated['lead_ids']);
        }

        // Apply additional filters from request
        if ($request->filled('has_email')) {
            $query->withEmail();
        }
        if ($request->filled('has_phone')) {
            $query->withPhone();
        }
        if ($request->filled('not_imported')) {
            $query->notImported();
        }
        if ($request->filled('min_quality')) {
            $query->minQuality((int) $request->min_quality);
        }

        $leads = $query->orderByDesc('quality_score')->get();

        $fields = [
            'business_name', 'first_name', 'last_name', 'email', 'phone',
            'website', 'address', 'city', 'state', 'country', 'postal_code',
            'category', 'rating', 'reviews_count', 'facebook', 'instagram',
            'twitter', 'linkedin', 'quality_score'
        ];

        $format = $validated['format'] ?? 'csv';
        $filename = 'leads_export_' . now()->format('Y-m-d_His') . '.' . ($format === 'excel' ? 'xlsx' : 'csv');

        $headers = [
            'Content-Type' => $format === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function() use ($leads, $fields) {
            $handle = fopen('php://output', 'w');

            // Write header row
            $headerRow = [];
            foreach ($fields as $field) {
                $headerRow[] = ucwords(str_replace('_', ' ', $field));
            }
            fputcsv($handle, $headerRow);

            // Write data rows
            foreach ($leads as $lead) {
                $row = [];
                foreach ($fields as $field) {
                    $value = $lead->{$field} ?? '';
                    if ($field === 'rating' && $value) {
                        $value = number_format($value, 1);
                    }
                    $row[] = $value;
                }
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Import leads to a contact group
     */
    public function importLeads(Request $request): JsonResponse
    {
        try {
            $user = $this->user();

            $validated = $request->validate([
                'lead_ids'       => 'required|array|min:1',
                'lead_ids.*'     => 'integer',
                'group_id'       => 'nullable|integer',
                'new_group_name' => 'nullable|string|max:255',
                'import_type'    => 'required|in:email,sms,whatsapp,all',
                'skip_duplicates'=> 'nullable|boolean',
            ]);

            // Get or create the group
            if (!empty($validated['new_group_name'])) {
                // Create new group
                $group = ContactGroup::create([
                    'uid'     => str_unique(),
                    'user_id' => $user->id,
                    'name'    => $validated['new_group_name'],
                    'status'  => 'active',
                ]);
            } elseif (!empty($validated['group_id'])) {
                // Verify group belongs to user
                $group = ContactGroup::where('id', $validated['group_id'])
                    ->where('user_id', $user->id)
                    ->firstOrFail();
            } else {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Please select or create a contact group'),
                ], Response::HTTP_BAD_REQUEST);
            }

            $skipDuplicates = $validated['skip_duplicates'] ?? true;

            // Get leads belonging to user
            $leads = ScrapedLead::whereIn('id', $validated['lead_ids'])
                ->where('user_id', $user->id)
                ->notImported()
                ->get();

            if ($leads->isEmpty()) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('No leads available for import'),
                ], Response::HTTP_BAD_REQUEST);
            }

            $importedCount = 0;
            $skippedCount = 0;
            $importType = $validated['import_type'];

            foreach ($leads as $lead) {
                // Check if lead has required contact type
                $hasRequiredContact = match ($importType) {
                    'email'    => !empty($lead->email),
                    'sms'      => !empty($lead->phone),
                    'whatsapp' => !empty($lead->phone),
                    'all'      => !empty($lead->email) || !empty($lead->phone),
                };

                if (!$hasRequiredContact) {
                    continue;
                }

                // Check for duplicates if enabled
                if ($skipDuplicates) {
                    $existingContact = Contact::where('user_id', $user->id)
                        ->where('group_id', $group->id)
                        ->where(function($q) use ($lead, $importType) {
                            if (in_array($importType, ['email', 'all']) && $lead->email) {
                                $q->orWhere('email_contact', $lead->email);
                            }
                            if (in_array($importType, ['sms', 'all']) && $lead->phone) {
                                $q->orWhere('sms_contact', $lead->phone);
                            }
                            if (in_array($importType, ['whatsapp', 'all']) && $lead->phone) {
                                $q->orWhere('whatsapp_contact', $lead->phone);
                            }
                        })
                        ->exists();

                    if ($existingContact) {
                        $skippedCount++;
                        continue;
                    }
                }

                // Create contact
                $contactData = [
                    'uid'              => str_unique(),
                    'user_id'          => $user->id,
                    'group_id'         => $group->id,
                    'first_name'       => $lead->first_name ?: $lead->business_name,
                    'last_name'        => $lead->last_name,
                    'status'           => 'active',
                    'meta_data'        => json_encode([
                        'source'        => 'lead_scraper',
                        'lead_id'       => $lead->id,
                        'business_name' => $lead->business_name,
                        'website'       => $lead->website,
                    ]),
                ];

                if (in_array($importType, ['email', 'all']) && $lead->email) {
                    $contactData['email_contact'] = $lead->email;
                }
                if (in_array($importType, ['sms', 'all']) && $lead->phone) {
                    $contactData['sms_contact'] = $lead->phone;
                }
                if (in_array($importType, ['whatsapp', 'all']) && $lead->phone) {
                    $contactData['whatsapp_contact'] = $lead->phone;
                }

                Contact::create($contactData);

                // Mark lead as imported
                $lead->markAsImported($group->id);
                $importedCount++;
            }

            $message = translate('Successfully imported') . ' ' . $importedCount . ' ' . translate('leads to') . ' ' . $group->name;

            if ($skippedCount > 0) {
                $message .= ' (' . $skippedCount . ' ' . translate('duplicates skipped') . ')';
            }

            return response()->json([
                'status'  => true,
                'message' => $message,
                'data'    => [
                    'imported_count' => $importedCount,
                    'skipped_count'  => $skippedCount,
                    'group_name'     => $group->name,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a lead
     */
    public function deleteLead(Request $request, int $id): JsonResponse
    {
        try {
            $lead = ScrapedLead::where('id', $id)
                ->where('user_id', $this->user()->id)
                ->firstOrFail();

            $lead->delete();

            return response()->json([
                'status'  => true,
                'message' => translate('Lead deleted successfully'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk delete leads
     */
    public function bulkDeleteLeads(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lead_ids' => 'required|array|min:1',
                'lead_ids.*' => 'integer',
            ]);

            $count = ScrapedLead::whereIn('id', $validated['lead_ids'])
                ->where('user_id', $this->user()->id)
                ->delete();

            return response()->json([
                'status'  => true,
                'message' => translate('Successfully deleted') . ' ' . $count . ' ' . translate('leads'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancel a running job
     */
    public function cancelJob(string $uid): JsonResponse
    {
        try {
            $job = LeadScrapingJob::where('uid', $uid)
                ->where('user_id', $this->user()->id)
                ->firstOrFail();

            if (!$job->isRunning()) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Job is not running'),
                ], Response::HTTP_BAD_REQUEST);
            }

            $job->update([
                'status' => LeadScrapingStatusEnum::CANCELLED,
                'completed_at' => now(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => translate('Job cancelled successfully'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get remaining quota
     */
    public function quota(): JsonResponse
    {
        $settings = LeadGenerationSetting::getForUser($this->user()->id);

        return response()->json([
            'status' => true,
            'data'   => [
                'daily_limit'       => $settings->daily_scrape_limit,
                'monthly_limit'     => $settings->monthly_scrape_limit,
                'remaining_daily'   => $settings->remaining_daily,
                'remaining_monthly' => $settings->remaining_monthly,
                'scrapes_today'     => $settings->scrapes_today,
                'scrapes_this_month' => $settings->scrapes_this_month,
            ],
        ]);
    }
}
