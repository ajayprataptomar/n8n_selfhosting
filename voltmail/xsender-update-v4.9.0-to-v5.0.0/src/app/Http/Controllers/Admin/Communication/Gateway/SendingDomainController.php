<?php

namespace App\Http\Controllers\Admin\Communication\Gateway;

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
     * Display sending domains list.
     */
    public function index(): View
    {
        Session::put("menu_active", true);
        $title = translate("Sending Domains");

        $domains = SendingDomain::whereNull('user_id')
            ->search(['domain'])
            ->filter(['status'])
            ->date()
            ->latest()
            ->paginate(paginateNumber(site_settings("paginate_number")));

        return view('admin.gateway.sending_domain.index', compact('title', 'domains'));
    }

    /**
     * Store a new sending domain.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255|regex:/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+$/|unique:sending_domains,domain,NULL,id,user_id,NULL',
            'dkim_selector' => 'nullable|string|max:63|alpha_dash',
        ]);

        try {
            $domain = SendingDomain::create([
                'domain' => strtolower($request->input('domain')),
                'dkim_selector' => $request->input('dkim_selector', 'xsender'),
                'user_id' => null,
                'status' => 'pending',
            ]);

            try {
                $this->dkimService->generateKeyPair($domain, $domain->dkim_selector);
            } catch (Exception $e) {
                \Log::warning("DKIM key generation failed for {$domain->domain}: " . $e->getMessage());
            }

            $notify[] = ['success', translate('Sending domain added. Please configure DNS records.')];
            return redirect()->route('admin.gateway.sending-domain.dns', $domain->uid)->withNotify($notify);

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
        Session::put("menu_active", true);
        $title = translate("DNS Records");

        $domain = SendingDomain::where('uid', $uid)->whereNull('user_id')->firstOrFail();

        // Auto-generate keys if missing
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

        return view('admin.gateway.sending_domain.dns_records', compact('title', 'domain', 'dnsRecords', 'opensslCheck'));
    }

    /**
     * Verify DNS records for a domain.
     */
    public function verify(string $uid): JsonResponse
    {
        try {
            $domain = SendingDomain::where('uid', $uid)->whereNull('user_id')->firstOrFail();
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
     * Regenerate DKIM keys for a domain.
     */
    public function regenerateKeys(string $uid): RedirectResponse
    {
        try {
            $domain = SendingDomain::where('uid', $uid)->whereNull('user_id')->firstOrFail();

            $this->dkimService->generateKeyPair($domain, $domain->dkim_selector);

            $domain->update([
                'dkim_verified' => 'no',
                'spf_verified' => 'no',
                'dmarc_verified' => 'no',
                'status' => 'pending',
                'verified_at' => null,
            ]);

            $notify[] = ['success', translate('DKIM keys regenerated. Please update your DNS records.')];
            return redirect()->route('admin.gateway.sending-domain.dns', $domain->uid)->withNotify($notify);

        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete a sending domain.
     */
    public function destroy(string $uid): RedirectResponse
    {
        try {
            $domain = SendingDomain::where('uid', $uid)->whereNull('user_id')->firstOrFail();
            $domain->delete();

            $notify[] = ['success', translate('Sending domain deleted successfully')];
        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
        }

        return back()->withNotify($notify);
    }

    /**
     * Toggle domain status (active/inactive).
     */
    public function statusUpdate(Request $request): string
    {
        try {
            $domain = SendingDomain::where('id', $request->input('id'))->whereNull('user_id')->firstOrFail();

            if ($request->input('column') === 'status') {
                $newStatus = $domain->status === 'active' ? 'inactive' : 'active';

                // Only allow activating if DKIM is verified
                if ($newStatus === 'active' && $domain->dkim_verified !== 'yes') {
                    return json_encode([
                        'status' => false,
                        'message' => translate('Cannot activate domain. DKIM is not verified.'),
                    ]);
                }

                $domain->update(['status' => $newStatus]);
            }

            return json_encode([
                'status' => true,
                'reload' => true,
                'message' => translate('Domain status updated successfully'),
            ]);
        } catch (Exception $e) {
            return json_encode([
                'status' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
