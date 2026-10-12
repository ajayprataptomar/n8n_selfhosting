<?php

namespace App\Http\Controllers\User\B2bDatabase;

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

    protected function checkB2bAccess(): bool
    {
        $planAccess = (array) planAccess(auth()->user());
        $leadGen = (array) ($planAccess['lead_generation'] ?? []);
        $b2b = (array) ($leadGen['b2b_database'] ?? []);
        return !empty($b2b['is_allowed']);
    }

    public function index()
    {
        if (!$this->checkB2bAccess()) {
            $notify[] = ['error', translate('Your plan does not include B2B Database access.')];
            return redirect()->route('user.plan.create')->withNotify($notify);
        }

        $user = auth()->user();
        $providers = B2bProvider::active()->forUser($user->id)->get();
        $groups = ContactGroup::where('user_id', $user->id)->latest()->get(['id', 'name']);

        return view('user.b2b_database.search.index', compact('providers', 'groups'));
    }

    public function search(Request $request): JsonResponse
    {
        if (!$this->checkB2bAccess()) {
            return response()->json(['success' => false, 'error' => translate('Your plan does not include B2B Database access.')], 403);
        }

        $request->validate([
            'provider_id' => 'required|exists:b2b_providers,id',
            'filters'     => 'required|array',
            'page'        => 'nullable|integer|min:1',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $user = auth()->user();
        $provider = B2bProvider::forUser($user->id)->findOrFail($request->provider_id);

        $result = $this->service->search(
            $provider,
            $request->filters,
            $request->input('page', 1),
            $request->input('per_page', 25),
            $user
        );

        return response()->json($result);
    }

    public function import(Request $request): JsonResponse
    {
        if (!$this->checkB2bAccess()) {
            return response()->json(['success' => false, 'error' => translate('Your plan does not include B2B Database access.')], 403);
        }

        $request->validate([
            'provider_id'     => 'required|exists:b2b_providers,id',
            'prospects'       => 'required|array|min:1',
            'group_id'        => 'nullable|exists:contact_groups,id',
            'new_group_name'  => 'nullable|string|max:255',
            'skip_duplicates' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $provider = B2bProvider::forUser($user->id)->findOrFail($request->provider_id);

        $groupId = $request->group_id;
        if (!$groupId && $request->new_group_name) {
            $group = ContactGroup::create([
                'user_id' => $user->id,
                'name'    => $request->new_group_name,
                'status'  => 'active',
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
            $user,
            $request->boolean('skip_duplicates', true)
        );

        return response()->json($result);
    }

    public function reports(): View
    {
        $user = auth()->user();

        $logs = B2bSearchLog::with(['provider', 'group'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        $stats = [
            'total_searches' => B2bSearchLog::where('user_id', $user->id)->count(),
            'total_imported' => B2bSearchLog::where('user_id', $user->id)->sum('imported_count'),
            'total_credits'  => B2bSearchLog::where('user_id', $user->id)->sum('credits_used'),
        ];

        return view('user.b2b_database.reports.index', compact('logs', 'stats'));
    }
}
