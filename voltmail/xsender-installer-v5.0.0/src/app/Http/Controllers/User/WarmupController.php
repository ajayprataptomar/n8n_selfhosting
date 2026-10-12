<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarmupScheduleRequest;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use App\Services\Warmup\WarmupMailboxService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarmupController extends Controller
{
    protected WarmupMailboxService $warmupService;

    public function __construct(WarmupMailboxService $warmupService)
    {
        $this->warmupService = $warmupService;
    }

    /**
     * Display listing of warmup schedules for current tenant
     */
    public function index(): View
    {
        $title = translate('Email Warmup');
        $user = auth()->user();

        $schedules = WarmupSchedule::with(['gateway', 'dispatches'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(site_settings('paginate_number', 10));

        $stats = [
            'total_active'     => WarmupSchedule::where('user_id', $user->id)->where('status', 'active')->count(),
            'total_dispatches' => WarmupDispatch::where('user_id', $user->id)->count(),
            'total_delivered'  => WarmupDispatch::where('user_id', $user->id)->where('status', WarmupDispatch::STATUS_DELIVERED)->count(),
        ];

        return view('user.warmup.index', compact('title', 'schedules', 'stats'));
    }

    /**
     * Show form to select an existing email gateway and configure warmup
     */
    public function create(): View
    {
        $title = translate('Add Mailbox to Warmup');
        $user = auth()->user();

        $eligibleGateways = $this->warmupService->getEligibleGateways($user->id);

        return view('user.warmup.create', compact('title', 'eligibleGateways'));
    }

    /**
     * Store new warmup schedule reusing selected email gateway
     */
    public function store(WarmupScheduleRequest $request): RedirectResponse
    {
        try {
            $user = auth()->user();

            $imapSettings = null;
            if ($request->filled('imap_host')) {
                $imapSettings = [
                    'host'       => $request->input('imap_host'),
                    'port'       => (int) $request->input('imap_port', 993),
                    'encryption' => $request->input('imap_encryption', 'ssl'),
                    'username'   => $request->input('imap_username'),
                    'password'   => $request->input('imap_password'),
                ];
            }

            $schedule = $this->warmupService->attachWarmupToGateway(
                userId: $user->id,
                gatewayId: (int) $request->input('gateway_id'),
                scheduleSettings: $request->validated(),
                imapSettings: $imapSettings
            );

            $notify[] = ['success', translate('Email account added to warmup successfully.')];
            return redirect()->route('user.warmup.index')->withNotify($notify);

        } catch (AuthorizationException $e) {
            $notify[] = ['error', translate($e->getMessage())];
            return back()->withInput()->withNotify($notify);
        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withInput()->withNotify($notify);
        }
    }

    /**
     * Show schedule details and dispatch activity
     */
    public function show(int $id): View|RedirectResponse
    {
        $title = translate('Warmup Mailbox Details');
        $user = auth()->user();

        $schedule = WarmupSchedule::with(['gateway'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$schedule) {
            $notify[] = ['error', translate('Warmup schedule not found.')];
            return redirect()->route('user.warmup.index')->withNotify($notify);
        }

        $dispatches = WarmupDispatch::where('warmup_schedule_id', $schedule->id)
            ->latest()
            ->paginate(15);

        return view('user.warmup.show', compact('title', 'schedule', 'dispatches'));
    }

    /**
     * Show edit form for schedule
     */
    public function edit(int $id): View|RedirectResponse
    {
        $title = translate('Edit Warmup Settings');
        $user = auth()->user();

        $schedule = WarmupSchedule::with('gateway')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$schedule) {
            $notify[] = ['error', translate('Warmup schedule not found.')];
            return redirect()->route('user.warmup.index')->withNotify($notify);
        }

        return view('user.warmup.edit', compact('title', 'schedule'));
    }

    /**
     * Update schedule settings
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'daily_limit'        => ['required', 'integer', 'min:5', 'max:500'],
            'rampup_increment'   => ['required', 'integer', 'min:1', 'max:50'],
            'reply_rate_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        try {
            $user = auth()->user();
            $this->warmupService->updateSchedule($user->id, $id, $request->all());

            $notify[] = ['success', translate('Warmup settings updated successfully.')];
            return redirect()->route('user.warmup.index')->withNotify($notify);

        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Toggle status between active and paused
     */
    public function toggleStatus(int $id): RedirectResponse
    {
        try {
            $user = auth()->user();
            $schedule = $this->warmupService->toggleStatus($user->id, $id);

            $statusText = $schedule->status === 'active' ? translate('activated') : translate('paused');
            $notify[] = ['success', translate("Warmup schedule {$statusText} successfully.")];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Detach / delete warmup schedule
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $user = auth()->user();
            $this->warmupService->detachWarmup($user->id, $id);

            $notify[] = ['success', translate('Warmup schedule removed successfully.')];
            return redirect()->route('user.warmup.index')->withNotify($notify);

        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
