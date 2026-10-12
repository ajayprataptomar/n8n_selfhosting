<?php

namespace App\Http\Controllers\User\Communication\Gateway;

use Exception;
use App\Models\SendingDomain;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Http\Controllers\Controller;
use App\Services\System\Communication\DkimService;
use Illuminate\Support\Facades\Session;

class SendingDomainController extends Controller
{
    protected DkimService $dkimService;

    public function __construct()
    {
        $this->dkimService = new DkimService();
    }

    /**
     * Display sending domains list for authenticated user.
     */
    public function index(): View
    {
        Session::put("menu_active", false);
        $title = translate("Sending Domains");
        $user = auth()->user();

        $allowed_access = planAccess($user);
        if (!$allowed_access) {
            $notify[] = ['error', translate('Please Purchase A Plan')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        $domains = SendingDomain::where('user_id', $user->id)
            ->search(['domain'])
            ->filter(['status'])
            ->date()
            ->latest()
            ->paginate(paginateNumber(site_settings("paginate_number")));

        $plan = $user->runningSubscription()?->currentPlan();
        $maxDomains = (int) ($plan?->email?->max_sending_domains ?? site_settings('max_sending_domains_per_user', 3));
        $currentCount = SendingDomain::where('user_id', $user->id)->count();

        return view('user.gateway.sending_domain.index', compact('title', 'domains', 'maxDomains', 'currentCount'));
    }

    /**
     * Store a new sending domain.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $request->validate([
            'domain' => 'required|string|max:255|regex:/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+$/',
            'dkim_selector' => 'nullable|string|max:63|alpha_dash',
        ]);

        // Check per-plan limit (falls back to global setting)
        $plan = $user->runningSubscription()?->currentPlan();
        $maxDomains = (int) ($plan?->email?->max_sending_domains ?? site_settings('max_sending_domains_per_user', 3));
        $currentCount = SendingDomain::where('user_id', $user->id)->count();

        if ($currentCount >= $maxDomains) {
            $notify[] = ['error', translate('You have reached the maximum number of sending domains') . " ({$maxDomains})"];
            return back()->withNotify($notify);
        }

        // Check uniqueness for this user
        $exists = SendingDomain::where('domain', strtolower($request->input('domain')))
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            $notify[] = ['error', translate('This domain is already added')];
            return back()->withNotify($notify);
        }

        try {
            $domain = SendingDomain::create([
                'domain' => strtolower($request->input('domain')),
                'dkim_selector' => $request->input('dkim_selector', 'xsender'),
                'user_id' => $user->id,
                'status' => 'pending',
            ]);

            try {
                $this->dkimService->generateKeyPair($domain, $domain->dkim_selector);
            } catch (Exception $e) {
                \Log::warning("DKIM key generation failed for {$domain->domain}: " . $e->getMessage());
            }

            $notify[] = ['success', translate('Sending domain added. Please configure DNS records.')];
            return redirect()->route('user.gateway.sending-domain.dns', $domain->uid)->withNotify($notify);

        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Show DNS records for a domain.
     */
    public function dnsRecords(string $uid): View
    {
        Session::put("menu_active", false);
        $title = translate("DNS Records");
        $user = auth()->user();

        $domain = SendingDomain::where('uid', $uid)->where('user_id', $user->id)->firstOrFail();

        // Auto-generate keys if missing (e.g. if generation failed during store)
        if (!$domain->isDkimConfigured()) {
            try {
                $this->dkimService->generateKeyPair($domain, $domain->dkim_selector);
                $domain->refresh();
            } catch (Exception $e) {
                \Log::warning("Auto DKIM key generation failed for {$domain->domain}: " . $e->getMessage());
            }
        }

        $dnsRecords = $this->dkimService->getDnsRecords($domain);

        // If keys still not generated, run OpenSSL diagnostic
        $opensslCheck = null;
        if (empty($dnsRecords['dkim']['value'])) {
            $opensslCheck = $this->dkimService->checkOpenSslReadiness();
        }

        return view('user.gateway.sending_domain.dns_records', compact('title', 'domain', 'dnsRecords', 'opensslCheck'));
    }

    /**
     * Regenerate DKIM keys for a domain.
     */
    public function regenerateKeys(string $uid): RedirectResponse
    {
        try {
            $user = auth()->user();
            $domain = SendingDomain::where('uid', $uid)->where('user_id', $user->id)->firstOrFail();

            $this->dkimService->generateKeyPair($domain, $domain->dkim_selector);

            // Reset verification since keys changed
            $domain->update([
                'dkim_verified' => 'no',
                'status' => 'pending',
                'verified_at' => null,
            ]);

            $notify[] = ['success', translate('DKIM keys regenerated. Please update your DNS records.')];
        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
        }

        return redirect()->route('user.gateway.sending-domain.dns', $uid)->withNotify($notify);
    }

    /**
     * Verify DNS records for a domain.
     */
    public function verify(string $uid): JsonResponse
    {
        try {
            $user = auth()->user();
            $domain = SendingDomain::where('uid', $uid)->where('user_id', $user->id)->firstOrFail();
            $results = $this->dkimService->verifyDns($domain);

            return response()->json([
                'status' => true,
                'dkim' => $results['dkim'],
                'spf' => $results['spf'],
                'dmarc' => $results['dmarc'],
                'messages' => $results['messages'],
                'domain_status' => $domain->fresh()->status,
                'message' => $results['dkim']
                    ? translate('DKIM verified successfully! Domain is now active.')
                    : translate('Verification incomplete. Please check your DNS records.'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ]);
        }
    }

    /**
     * Delete a sending domain.
     */
    public function destroy(string $uid): RedirectResponse
    {
        try {
            $user = auth()->user();
            $domain = SendingDomain::where('uid', $uid)->where('user_id', $user->id)->firstOrFail();
            $domain->delete();

            $notify[] = ['success', translate('Sending domain deleted successfully')];
        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
        }

        return back()->withNotify($notify);
    }
}
