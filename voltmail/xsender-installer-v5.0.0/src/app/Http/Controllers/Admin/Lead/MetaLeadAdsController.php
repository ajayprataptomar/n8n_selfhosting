<?php

namespace App\Http\Controllers\Admin\Lead;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use App\Models\MetaLeadFormSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class MetaLeadAdsController extends Controller
{
    public function index(): View
    {
        Session::put('menu_active', true);

        $title         = translate('Meta Lead Ads');
        $subscriptions = MetaLeadFormSubscription::whereNull('user_id')
            ->orderByDesc('created_at')
            ->paginate(paginateNumber());
        $webhookUrl    = url('/api/webhook/meta-lead-ads');
        $verifyToken   = site_settings('meta_lead_ads_verify_token', '');

        return view('admin.lead-generation.meta-lead-ads.index', compact(
            'title', 'subscriptions', 'webhookUrl', 'verifyToken'
        ));
    }

    public function create(): View
    {
        Session::put('menu_active', true);

        $title  = translate('Add Meta Lead Form Subscription');
        $groups = ContactGroup::whereNull('user_id')->orderBy('name')->get(['id', 'name']);
        $workflows = \App\Models\Automation\AutomationWorkflow::whereNull('user_id')
            ->active()
            ->get(['id', 'name']);

        return view('admin.lead-generation.meta-lead-ads.form', compact(
            'title', 'groups', 'workflows'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:150',
            'page_id'           => 'required|string|max:64',
            'page_name'         => 'nullable|string|max:150',
            'page_access_token' => 'required|string',
            'form_id'           => 'required|string|max:64',
            'form_name'         => 'nullable|string|max:150',
            'contact_group_id'  => 'nullable|exists:contact_groups,id',
            'workflow_id'       => 'nullable|exists:automation_workflows,id',
            'is_active'         => 'nullable|boolean',
        ]);

        try {
            MetaLeadFormSubscription::create([
                ...$validated,
                'is_active' => $request->boolean('is_active', true),
            ]);
            return redirect()->route('admin.lead-generation.meta-lead-ads.index')
                ->withNotify([['success', translate('Meta Lead form subscription added')]]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Likely the unique(page_id, form_id) constraint
            return back()->withInput()
                ->withNotify([['error', translate('A subscription for this page + form already exists.')]]);
        }
    }

    public function edit(string $uid): View
    {
        Session::put('menu_active', true);

        $subscription = MetaLeadFormSubscription::where('uid', $uid)->firstOrFail();
        $title        = translate('Edit Meta Lead Form Subscription');
        $groups       = ContactGroup::whereNull('user_id')->orderBy('name')->get(['id', 'name']);
        $workflows    = \App\Models\Automation\AutomationWorkflow::whereNull('user_id')
            ->active()
            ->get(['id', 'name']);

        return view('admin.lead-generation.meta-lead-ads.form', compact(
            'title', 'subscription', 'groups', 'workflows'
        ));
    }

    public function update(Request $request, string $uid): RedirectResponse
    {
        $subscription = MetaLeadFormSubscription::where('uid', $uid)->firstOrFail();

        $validated = $request->validate([
            'name'              => 'required|string|max:150',
            'page_id'           => 'required|string|max:64',
            'page_name'         => 'nullable|string|max:150',
            'page_access_token' => 'nullable|string',
            'form_id'           => 'required|string|max:64',
            'form_name'         => 'nullable|string|max:150',
            'contact_group_id'  => 'nullable|exists:contact_groups,id',
            'workflow_id'       => 'nullable|exists:automation_workflows,id',
            'is_active'         => 'nullable|boolean',
        ]);

        // Don't overwrite token if blank submission (user just editing other fields).
        if (empty($validated['page_access_token'])) {
            unset($validated['page_access_token']);
        }

        $subscription->update([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.lead-generation.meta-lead-ads.index')
            ->withNotify([['success', translate('Subscription updated')]]);
    }

    public function destroy(string $uid): RedirectResponse
    {
        MetaLeadFormSubscription::where('uid', $uid)->delete();
        return back()->withNotify([['success', translate('Subscription removed')]]);
    }
}
