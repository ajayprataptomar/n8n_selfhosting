<?php

namespace App\Http\Controllers\Admin\B2bDatabase;

use App\Http\Controllers\Controller;
use App\Models\B2bProvider;
use App\Models\B2bSearchLog;
use App\Models\ContactGroup;
use App\Services\B2bDatabase\B2bProviderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class B2bSearchController extends Controller
{
    protected B2bProviderService $service;

    public function __construct()
    {
        $this->service = new B2bProviderService();
    }

    /**
     * Search page
     */
    public function index(): View
    {
        $providers = B2bProvider::active()
            ->whereNull('user_id')
            ->get();

        $groups = ContactGroup::whereNull('user_id')
            ->latest()
            ->get(['id', 'name']);

        return view('admin.b2b_database.search.index', compact('providers', 'groups'));
    }

    /**
     * Execute search (AJAX)
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'provider_id' => 'required|exists:b2b_providers,id',
            'filters'     => 'required|array',
            'page'        => 'nullable|integer|min:1',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $provider = B2bProvider::findOrFail($request->provider_id);

        $result = $this->service->search(
            $provider,
            $request->filters,
            $request->input('page', 1),
            $request->input('per_page', 25)
        );

        return response()->json($result);
    }

    /**
     * Import selected contacts to a group (AJAX)
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'provider_id'     => 'required|exists:b2b_providers,id',
            'prospects'       => 'required|array|min:1',
            'group_id'        => 'nullable|exists:contact_groups,id',
            'new_group_name'  => 'nullable|string|max:255',
            'skip_duplicates' => 'nullable|boolean',
        ]);

        $provider = B2bProvider::findOrFail($request->provider_id);

        // Create new group if requested
        $groupId = $request->group_id;
        if (!$groupId && $request->new_group_name) {
            $group = ContactGroup::create([
                'name'   => $request->new_group_name,
                'status' => 'active',
            ]);
            $groupId = $group->id;
        }

        if (!$groupId) {
            return response()->json(['success' => false, 'error' => 'No contact group specified'], 422);
        }

        $result = $this->service->revealAndImport(
            $provider,
            $request->prospects,
            $groupId,
            null, // admin
            $request->boolean('skip_duplicates', true)
        );

        return response()->json($result);
    }

    /**
     * Reports page
     */
    public function reports(): View
    {
        $logs = B2bSearchLog::with(['provider', 'group'])
            ->whereHas('provider', fn($q) => $q->whereNull('user_id'))
            ->latest()
            ->paginate(20);

        $stats = [
            'total_searches' => B2bSearchLog::whereHas('provider', fn($q) => $q->whereNull('user_id'))->count(),
            'total_imported' => B2bSearchLog::whereHas('provider', fn($q) => $q->whereNull('user_id'))->sum('imported_count'),
            'total_credits'  => B2bSearchLog::whereHas('provider', fn($q) => $q->whereNull('user_id'))->sum('credits_used'),
        ];

        return view('admin.b2b_database.reports.index', compact('logs', 'stats'));
    }
}
