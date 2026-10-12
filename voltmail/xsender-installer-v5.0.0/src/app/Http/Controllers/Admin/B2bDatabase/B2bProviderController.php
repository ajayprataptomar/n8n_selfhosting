<?php

namespace App\Http\Controllers\Admin\B2bDatabase;

use App\Http\Controllers\Controller;
use App\Managers\B2bProviderManager;
use App\Models\B2bProvider;
use App\Models\B2bSearchLog;
use App\Services\B2bDatabase\B2bProviderService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class B2bProviderController extends Controller
{
    protected B2bProviderService $service;

    public function __construct()
    {
        $this->service = new B2bProviderService();
    }

    /**
     * Provider listing page
     */
    public function index(): View
    {
        $providers = B2bProvider::whereNull('user_id')
            ->latest()
            ->get();

        $availableTypes = B2bProviderManager::availableTypes();

        return view('admin.b2b_database.providers.index', compact('providers', 'availableTypes'));
    }

    /**
     * Store a new provider
     */
    public function store(Request $request)
    {
        $request->validate([
            'type'      => 'required|in:apollo,hunter,snov,rocketreach',
            'name'      => 'nullable|string|max:100',
            'meta_data' => 'required|array',
        ]);

        $provider = B2bProvider::create([
            'user_id'   => null, // Admin-level provider
            'type'      => $request->type,
            'name'      => $request->name ?: $request->type,
            'meta_data' => json_encode($request->meta_data),
        ]);

        // Test connection
        $test = $this->service->testConnection($provider);

        if (!$test['success']) {
            $provider->delete();
            return back()->with('error', translate('Connection failed: ') . $test['message']);
        }

        $notify[] = ['success', translate('Provider connected successfully')];
        return back()->withNotify($notify);
    }

    /**
     * Test existing provider connection
     */
    public function testConnection(B2bProvider $provider)
    {
        $result = $this->service->testConnection($provider);

        return response()->json($result);
    }

    /**
     * Update provider
     */
    public function update(Request $request, B2bProvider $provider)
    {
        $data = $request->only(['name', 'is_active']);

        if ($request->has('meta_data')) {
            $data['meta_data'] = json_encode($request->meta_data);
        }

        $provider->update($data);

        $notify[] = ['success', translate('Provider updated successfully')];
        return back()->withNotify($notify);
    }

    /**
     * Delete provider
     */
    public function destroy(B2bProvider $provider)
    {
        $provider->delete();

        $notify[] = ['success', translate('Provider removed')];
        return back()->withNotify($notify);
    }

    /**
     * Toggle provider active status
     */
    public function toggleStatus(B2bProvider $provider)
    {
        $provider->update(['is_active' => !$provider->is_active]);

        return response()->json(['success' => true, 'is_active' => $provider->is_active]);
    }
}
