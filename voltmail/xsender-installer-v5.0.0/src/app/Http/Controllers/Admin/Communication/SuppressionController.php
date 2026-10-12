<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Models\BounceLog;
use App\Models\EmailSuppression;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class SuppressionController extends Controller
{
    /**
     * Display suppression list.
     */
    public function index(): View
    {
        Session::put("menu_active", true);
        $title = translate("Suppression List");

        $suppressions = EmailSuppression::search(['email_address'])
            ->filter(['reason', 'source'])
            ->latest('created_at')
            ->paginate(paginateNumber(site_settings("paginate_number")));

        return view('admin.communication.suppression.index', compact('title', 'suppressions'));
    }

    /**
     * Display bounce logs.
     */
    public function bounceLogs(): View
    {
        Session::put("menu_active", true);
        $title = translate("Bounce Logs");

        $logs = BounceLog::search(['email_address'])
            ->filter(['bounce_type', 'provider'])
            ->date()
            ->latest()
            ->paginate(paginateNumber(site_settings("paginate_number")));

        return view('admin.communication.suppression.bounce_logs', compact('title', 'logs'));
    }

    /**
     * Manually add email to suppression list.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email_address' => 'required|email|max:255',
            'reason' => 'required|in:hard_bounce,complaint,manual',
        ]);

        EmailSuppression::suppress(
            $request->input('email_address'),
            $request->input('reason'),
            'manual'
        );

        $notify[] = ['success', translate('Email added to suppression list')];
        return back()->withNotify($notify);
    }

    /**
     * Remove email from suppression list.
     */
    public function destroy(string $uid): RedirectResponse
    {
        $suppression = EmailSuppression::where('uid', $uid)->firstOrFail();
        $suppression->delete();

        $notify[] = ['success', translate('Email removed from suppression list')];
        return back()->withNotify($notify);
    }
}
