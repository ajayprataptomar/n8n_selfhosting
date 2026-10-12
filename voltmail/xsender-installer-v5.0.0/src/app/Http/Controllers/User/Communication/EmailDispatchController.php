<?php

namespace App\Http\Controllers\User\Communication;

use Exception;
use Illuminate\View\View;
use App\Traits\ModelAction;
use App\Managers\GatewayManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\EmailDispatchRequest;
use App\Models\DispatchLog;
use App\Services\System\Communication\DispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmailDispatchController extends Controller
{
    use ModelAction;

    public $gatewayManager;
    public $dispatchService;

    /**
     * __construct
     *
     */
    public function __construct() {
        $this->gatewayManager   = new GatewayManager();
        $this->dispatchService  = new DispatchService();
    }

    /**
     * index
     *
     * @return View
     */
    public function index(): View
    {
        $user = auth()->user();
        Session::put("menu_active", true);
        return $this->dispatchService->loadLogs(channel: ChannelTypeEnum::EMAIL, user: $user);
    }

    /**
     * create
     *
     * @return View
     */
    public function create(): View
    {
        $user = auth()->user();
        Session::put("menu_active", true);
        return $this->dispatchService->createDispatchLog(channel: ChannelTypeEnum::EMAIL, user: $user);
    }

    /**
     * show
     *
     * @param Request $request
     * @param mixed $id
     *
     * @return View
     */
    public function show(Request $request, $id): View
    {
        $user = auth()->user();
        Session::put("menu_active", true);
        $raw = $request->query('raw') == '1';
        return $this->dispatchService->showDispatchLog(channel: ChannelTypeEnum::EMAIL, id: $id, user: $user, raw: $raw);
    }

    /**
     * resend
     *
     * @param mixed $id
     *
     * @return RedirectResponse
     */
    public function resend($id): RedirectResponse
    {
        try {
            $user = auth()->user();
            return $this->dispatchService->resendDispatchLog(channel: ChannelTypeEnum::EMAIL, id: $id, user: $user);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * store
     *
     * @param EmailDispatchRequest $request
     *
     * @return RedirectResponse
     */
    public function store(EmailDispatchRequest $request): RedirectResponse
    {
        try {
            $user = auth()->user();
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(type: ChannelTypeEnum::EMAIL, request: $request, user: $user);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify)->withInput();

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * Download or view an email attachment securely (user can only access own).
     * Use ?view=1 to display inline (for images/PDFs), otherwise forces download.
     */
    public function downloadAttachment(Request $request, $logId, $storedName)
    {
        $log = DispatchLog::where('user_id', auth()->id())
            ->with('message')
            ->findOrFail($logId);

        $attachments = Arr::get($log->message->file_info ?? [], 'attachments', []);
        $attachment = collect($attachments)->firstWhere('stored_name', $storedName);

        if (!$attachment) {
            abort(404);
        }

        $urlFile = Arr::get($attachment, 'url_file', '');
        $mimeType = Arr::get($attachment, 'mime_type', 'application/octet-stream');
        $originalName = Arr::get($attachment, 'name', $storedName);
        $inline = $request->query('view') == '1';
        $filePath = null;

        if (str_starts_with($urlFile, 'storage:email_attachments/')) {
            $filename = str_replace('storage:email_attachments/', '', $urlFile);
            $disk = Storage::disk('email_attachments');
            if (!$disk->exists($filename)) {
                abort(404);
            }
            $filePath = $disk->path($filename);
        } elseif ($urlFile && file_exists($urlFile)) {
            $filePath = $urlFile;
        }

        if (!$filePath) {
            abort(404);
        }

        if ($inline) {
            return response()->file($filePath, [
                'Content-Type' => $mimeType,
            ]);
        }

        return response()->download($filePath, $originalName, [
            'Content-Type' => $mimeType,
        ]);
    }
}