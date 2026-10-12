<?php

namespace App\Http\Controllers\User\B2bDatabase;

use App\Http\Controllers\Controller;
use App\Managers\B2bProviderManager;
use App\Models\B2bProvider;
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
     * Check if user's plan allows B2B database access
     */
    protected function checkB2bAccess(): bool
    {
        $planAccess = (array) planAccess(auth()->user());
        $leadGen = (array) ($planAccess['lead_generation'] ?? []);
        $b2b = (array) ($leadGen['b2b_database'] ?? []);
        return !empty($b2b['is_allowed']);
    }

    public function index()
    {
        $user = auth()->user();

        if (!$this->checkB2bAccess()) {
            $notify[] = ['error', translate('Your current plan does not include B2B Database access. Please upgrade your plan.')];
            return redirect()->route('user.plan.create')->withNotify($notify);
        }

        $providers = B2bProvider::forUser($user->id)->latest()->get();
        $availableTypes = B2bProviderManager::availableTypes();

        return view('user.b2b_database.providers.index', compact('providers', 'availableTypes'));
    }

    public function store(Request $request)
    {
        if (!$this->checkB2bAccess()) {
            abort(403, translate('Your plan does not include B2B Database access.'));
        }

        $request->validate([
            'type'      => 'required|in:apollo,hunter,snov,rocketreach',
            'name'      => 'nullable|string|max:100',
            'meta_data' => 'required|array',
        ]);

        $provider = B2bProvider::create([
            'user_id'   => auth()->id(),
            'type'      => $request->type,
            'name'      => $request->name ?: $request->type,
            'meta_data' => json_encode($request->meta_data),
        ]);

        $test = $this->service->testConnection($provider);
        if (!$test['success']) {
            $provider->delete();
            return back()->with('error', translate('Connection failed: ') . $test['message']);
        }

        $notify[] = ['success', translate('Provider connected successfully')];
        return back()->withNotify($notify);
    }

    public function testConnection(B2bProvider $provider)
    {
        $this->authorizeProvider($provider);
        return response()->json($this->service->testConnection($provider));
    }

    public function update(Request $request, B2bProvider $provider)
    {
        $this->authorizeProvider($provider);

        $data = $request->only(['name', 'is_active']);
        if ($request->has('meta_data')) {
            $data['meta_data'] = json_encode($request->meta_data);
        }
        $provider->update($data);

        $notify[] = ['success', translate('Provider updated successfully')];
        return back()->withNotify($notify);
    }

    public function destroy(B2bProvider $provider)
    {
        $this->authorizeProvider($provider);
        $provider->delete();

        $notify[] = ['success', translate('Provider removed')];
        return back()->withNotify($notify);
    }

    protected function authorizeProvider(B2bProvider $provider): void
    {
        if ($provider->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
