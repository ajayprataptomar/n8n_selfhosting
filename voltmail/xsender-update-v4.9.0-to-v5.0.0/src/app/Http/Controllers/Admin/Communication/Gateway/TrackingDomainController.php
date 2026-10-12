<?php

namespace App\Http\Controllers\Admin\Communication\Gateway;

use Exception;
use App\Models\TrackingDomain;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class TrackingDomainController extends Controller
{
    /**
     * Display tracking domains list.
     */
    public function index(): View
    {
        Session::put("menu_active", true);
        $title = translate("Tracking Domains");

        $domains = TrackingDomain::whereNull('user_id')
            ->search(['domain'])
            ->filter(['status'])
            ->date()
            ->latest()
            ->paginate(paginateNumber(site_settings("paginate_number")));

        return view('admin.gateway.tracking_domain.index', compact('title', 'domains'));
    }

    /**
     * Store a new tracking domain.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255|regex:/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/|unique:tracking_domains,domain,NULL,id,user_id,NULL',
        ]);

        $submittedDomain = strtolower($request->input('domain'));
        $appDomain       = strtolower(parse_url(config('app.url'), PHP_URL_HOST) ?: '');

        if ($submittedDomain === $appDomain) {
            $notify[] = ['error', translate('You cannot use the application domain itself as a tracking domain. Use a subdomain of your own domain (e.g., track.yourdomain.com).')];
            return back()->withNotify($notify);
        }

        try {
            TrackingDomain::create([
                'domain'  => $submittedDomain,
                'user_id' => null,
                'status'  => 'pending',
            ]);

            $notify[] = ['success', translate('Tracking domain added. Please configure the CNAME record pointing to') . " {$appDomain}"];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Verify CNAME record for a tracking domain.
     */
    public function verify(string $uid): JsonResponse
    {
        try {
            $domain = TrackingDomain::where('uid', $uid)->whereNull('user_id')->firstOrFail();
            $result = $domain->verifyCname();

            if ($result['verified']) {
                $domain->update([
                    'status'      => 'active',
                    'verified_at' => now(),
                ]);

                return response()->json([
                    'status'  => true,
                    'message' => translate('CNAME verified! Tracking domain is now active.'),
                ]);
            }

            return response()->json([
                'status'  => false,
                'message' => $this->buildVerifyDiagnostic($domain->domain, $result),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ]);
        }
    }

    /**
     * Build a customer-friendly verification failure message with expected/found targets.
     */
    private function buildVerifyDiagnostic(string $domain, array $result): string
    {
        $expected = $result['expected'] ?? '';

        if (!empty($result['error'])) {
            return translate('DNS lookup error') . ': ' . $result['error']
                . ' ' . translate('Expected CNAME target') . ": {$expected}";
        }

        if (empty($result['found'])) {
            return translate('No CNAME record was found for') . " {$domain}. "
                . translate('Add a CNAME record:') . " {$domain} → {$expected} "
                . translate('(DNS changes can take up to 24 hours to propagate.)');
        }

        $found = implode(', ', $result['found']);
        return translate('CNAME found but points to the wrong target.')
            . " " . translate('Found') . ": {$found}. "
            . translate('Expected') . ": {$expected}.";
    }

    /**
     * Delete a tracking domain.
     */
    public function destroy(string $uid): RedirectResponse
    {
        try {
            $domain = TrackingDomain::where('uid', $uid)->whereNull('user_id')->firstOrFail();
            $domain->delete();
            $notify[] = ['success', translate('Tracking domain deleted')];
        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
        }

        return back()->withNotify($notify);
    }
}
