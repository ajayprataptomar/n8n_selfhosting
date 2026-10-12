<?php

namespace App\Http\Controllers\Admin\LeadGeneration;

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
use App\Services\LeadGeneration\GoogleMapsScraperService;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadScraperController extends Controller
{
    use ModelAction;

    /**
     * Display lead generation dashboard
     */
    public function index(): View
    {
        Session::put("menu_active", true);

        $title = translate("Lead Generation");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Lead Generation')],
        ];

        // Get recent jobs
        $jobs = LeadScrapingJob::whereNull('user_id')
            ->orderByDesc('created_at')
            ->paginate(paginateNumber());

        // Get statistics
        $stats = [
            'total_leads'      => ScrapedLead::whereNull('user_id')->count(),
            'total_jobs'       => LeadScrapingJob::whereNull('user_id')->count(),
            'pending_jobs'     => LeadScrapingJob::whereNull('user_id')->pending()->count(),
            'leads_with_email' => ScrapedLead::whereNull('user_id')->withEmail()->count(),
            'leads_with_phone' => ScrapedLead::whereNull('user_id')->withPhone()->count(),
            'imported_leads'   => ScrapedLead::whereNull('user_id')->imported()->count(),
            'high_quality'     => ScrapedLead::whereNull('user_id')->minQuality(80)->count(),
        ];

        $settings = LeadGenerationSetting::getForUser(null);

        return view('admin.lead-generation.index', compact(
            'title', 'breadcrumbs', 'jobs', 'stats', 'settings'
        ));
    }

    /**
     * Display settings page
     */
    public function settings(): View
    {
        Session::put("menu_active", true);

        $title = translate("Lead Generation Settings");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Lead Generation'), 'url' => route('admin.lead-generation.index')],
            ['name' => translate('Settings')],
        ];

        $settings = LeadGenerationSetting::getForUser(null);

        return view('admin.lead-generation.settings', compact(
            'title', 'breadcrumbs', 'settings'
        ));
    }

    /**
     * Show the scraper wizard
     */
    public function scraper(string $type = 'google_maps'): View
    {
        Session::put("menu_active", true);

        $typeEnum = LeadScrapingTypeEnum::tryFrom($type) ?? LeadScrapingTypeEnum::GOOGLE_MAPS;

        $title = translate("New Lead Scraping Job");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Lead Generation'), 'url' => route('admin.lead-generation.index')],
            ['name' => $typeEnum->label()],
        ];

        $settings = LeadGenerationSetting::getForUser(null);

        // Get contact groups for auto-save option
        $groups = ContactGroup::whereNull('user_id')
            ->orderBy('name')
            ->get();

        return view('admin.lead-generation.scraper', compact(
            'title', 'breadcrumbs', 'typeEnum', 'settings', 'groups'
        ));
    }

    /**
     * Start a new scraping job
     */
    public function startJob(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type'             => 'required|in:google_maps,website,enrichment',
                'query'            => 'required_if:type,google_maps|string|max:255',
                'location'         => 'required_if:type,google_maps|string|max:255',
                'urls'             => 'required_if:type,website|string',
                'lead_ids'         => 'required_if:type,enrichment|array',
                'max_results'      => 'nullable|integer|min:1|max:100',
                // New enhanced fields
                'lead_type'        => 'nullable|in:all,email,phone,whatsapp',
                'min_rating'       => 'nullable|numeric|min:0|max:5',
                'min_quality'      => 'nullable|integer|min:0|max:100',
                'require_website'  => 'nullable|boolean',
                'skip_duplicates'  => 'nullable|boolean',
                'save_option'      => 'nullable|in:review,existing,new',
                'existing_group_id'=> 'nullable|exists:contact_groups,id',
                'new_group_name'   => 'nullable|string|max:255',
            ]);

            $settings = LeadGenerationSetting::getForUser(null);

            // Admin has unlimited scraping - no quota check needed
            $maxResults = $validated['max_results'] ?? 60;

            // Prepare parameters based on type
            $params = match ($validated['type']) {
                'google_maps' => [
                    'query'           => $validated['query'],
                    'location'        => $validated['location'],
                    'max_results'     => $maxResults,
                    'lead_type'       => $validated['lead_type'] ?? 'all',
                    'min_rating'      => $validated['min_rating'] ?? null,
                    'min_quality'     => $validated['min_quality'] ?? 0,
                    'require_website' => $validated['require_website'] ?? false,
                    'skip_duplicates' => $validated['skip_duplicates'] ?? true,
                ],
                'website' => [
                    'urls'              => $validated['urls'],
                    'max_pages_per_site' => 5,
                    'lead_type'         => $validated['lead_type'] ?? 'all',
                ],
                'enrichment' => [
                    'lead_ids' => $validated['lead_ids'],
                ],
            };

            // Handle auto-save options
            $autoSaveGroupId = null;
            $saveOption = $validated['save_option'] ?? 'review';

            if ($saveOption === 'existing' && !empty($validated['existing_group_id'])) {
                $autoSaveGroupId = $validated['existing_group_id'];
            } elseif ($saveOption === 'new') {
                // Create new contact group
                $groupName = $validated['new_group_name'] ?? $this->generateGroupName($validated);
                $newGroup = ContactGroup::create([
                    'uid'     => str_unique(),
                    'user_id' => null,
                    'name'    => $groupName,
                    'status'  => 'active',
                ]);
                $autoSaveGroupId = $newGroup->id;
            }

            $params['auto_save_group_id'] = $autoSaveGroupId;
            $params['save_option'] = $saveOption;

            // Create job
            $job = LeadScrapingJob::create([
                'user_id'    => null, // Admin job
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
                    'job_url' => route('admin.lead-generation.job.status', $job->uid),
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
     * Generate group name from job parameters
     */
    private function generateGroupName(array $params): string
    {
        $parts = [];

        if (!empty($params['query'])) {
            $parts[] = ucfirst(trim(explode(',', $params['query'])[0]));
        }

        if (!empty($params['location'])) {
            $locationParts = explode(',', $params['location']);
            $parts[] = trim($locationParts[0]);
        }

        if (empty($parts)) {
            $parts[] = 'Leads';
        }

        return implode(' - ', $parts) . ' ' . now()->format('M j');
    }

    /**
     * Get job status
     */
    public function jobStatus(string $uid): JsonResponse
    {
        try {
            $job = LeadScrapingJob::where('uid', $uid)->firstOrFail();

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

        $job = LeadScrapingJob::where('uid', $uid)->firstOrFail();

        $title = translate("Scraping Results");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Lead Generation'), 'url' => route('admin.lead-generation.index')],
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

        // Get contact groups for import
        $groups = ContactGroup::whereNull('user_id')
            ->orderBy('name')
            ->get();

        return view('admin.lead-generation.results', compact(
            'title', 'breadcrumbs', 'job', 'leads', 'groups', 'jobStats'
        ));
    }

    /**
     * View all scraped leads
     */
    public function leads(Request $request): View
    {
        Session::put("menu_active", true);

        $title = translate("All Leads");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Lead Generation'), 'url' => route('admin.lead-generation.index')],
            ['name' => translate('All Leads')],
        ];

        $query = ScrapedLead::whereNull('user_id');

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

        $leads = $query->orderByDesc('created_at')
            ->paginate(paginateNumber());

        $groups = ContactGroup::whereNull('user_id')
            ->orderBy('name')
            ->get();

        return view('admin.lead-generation.leads', compact(
            'title', 'breadcrumbs', 'leads', 'groups'
        ));
    }

    /**
     * Export leads to Excel/CSV
     */
    public function exportLeads(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'job_id'     => 'nullable|exists:lead_scraping_jobs,id',
            'lead_ids'   => 'nullable|array',
            'lead_ids.*' => 'integer|exists:scraped_leads,id',
            'format'     => 'nullable|in:csv,excel',
            'fields'     => 'nullable|array',
        ]);

        $query = ScrapedLead::whereNull('user_id');

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

        // Define export fields
        $defaultFields = [
            'business_name', 'first_name', 'last_name', 'email', 'phone',
            'website', 'address', 'city', 'state', 'country', 'postal_code',
            'category', 'rating', 'reviews_count', 'facebook', 'instagram',
            'twitter', 'linkedin', 'quality_score'
        ];

        $fields = $validated['fields'] ?? $defaultFields;
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

            // Write header row with readable names
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
                    // Handle special fields
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
            $validated = $request->validate([
                'lead_ids'    => 'required|array|min:1',
                'lead_ids.*'  => 'integer|exists:scraped_leads,id',
                'group_id'    => 'nullable|exists:contact_groups,id',
                'new_group'   => 'nullable|string|max:255',
                'import_type' => 'required|in:email,sms,whatsapp,all',
            ]);

            // Create new group if specified
            if (!empty($validated['new_group'])) {
                $group = ContactGroup::create([
                    'uid'     => str_unique(),
                    'user_id' => null,
                    'name'    => $validated['new_group'],
                    'status'  => 'active',
                ]);
            } else {
                $group = ContactGroup::findOrFail($validated['group_id']);
            }

            $leads = ScrapedLead::whereIn('id', $validated['lead_ids'])
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
                    $skippedCount++;
                    continue;
                }

                // Check for duplicates in the group
                $duplicateQuery = Contact::where('group_id', $group->id);
                if ($importType === 'email' && $lead->email) {
                    $duplicateQuery->where('email_contact', $lead->email);
                } elseif (in_array($importType, ['sms', 'whatsapp']) && $lead->phone) {
                    $duplicateQuery->where(function($q) use ($lead) {
                        $q->where('sms_contact', $lead->phone)
                          ->orWhere('whatsapp_contact', $lead->phone);
                    });
                }

                if ($duplicateQuery->exists()) {
                    $skippedCount++;
                    continue;
                }

                // Create contact
                $contactData = [
                    'uid'              => str_unique(),
                    'group_id'         => $group->id,
                    'first_name'       => $lead->first_name ?: $lead->business_name,
                    'last_name'        => $lead->last_name,
                    'status'           => 'active',
                    'meta_data'        => json_encode([
                        'source'        => 'lead_scraper',
                        'lead_id'       => $lead->id,
                        'business_name' => $lead->business_name,
                        'website'       => $lead->website,
                        'category'      => $lead->category,
                        'rating'        => $lead->rating,
                        'address'       => $lead->address,
                        'city'          => $lead->city,
                        'country'       => $lead->country,
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
                $message .= ' (' . $skippedCount . ' ' . translate('skipped - missing contact or duplicate') . ')';
            }

            return response()->json([
                'status'  => true,
                'message' => $message,
                'data'    => [
                    'imported_count' => $importedCount,
                    'skipped_count'  => $skippedCount,
                    'group_name'     => $group->name,
                    'group_id'       => $group->id,
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
            $lead = ScrapedLead::findOrFail($id);
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
                'lead_ids.*' => 'integer|exists:scraped_leads,id',
            ]);

            $count = ScrapedLead::whereIn('id', $validated['lead_ids'])->delete();

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
     * Update settings
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'google_maps_api_key'  => 'nullable|string|max:255',
                'api_docs_url'         => 'nullable|url|max:500',
            ]);

            $settings = LeadGenerationSetting::getForUser(null);
            $settings->update($validated);

            // Validate API key if provided
            if (!empty($validated['google_maps_api_key'])) {
                $scraper = new GoogleMapsScraperService($validated['google_maps_api_key']);
                if (!$scraper->validateApiKey()) {
                    $notify[] = ['warning', translate('Settings saved, but Google Maps API key appears to be invalid')];
                    return back()->withNotify($notify);
                }
            }

            $notify[] = ['success', translate('Settings updated successfully')];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Cancel a running job
     */
    public function cancelJob(string $uid): JsonResponse
    {
        try {
            $job = LeadScrapingJob::where('uid', $uid)->firstOrFail();

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
}
