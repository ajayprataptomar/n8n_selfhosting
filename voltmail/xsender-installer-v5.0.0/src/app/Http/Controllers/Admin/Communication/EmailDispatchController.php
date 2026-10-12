<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Traits\ModelAction;
use Exception;
use Illuminate\View\View;
use App\Managers\GatewayManager;
use App\Http\Controllers\Controller;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\EmailDispatchRequest;
use App\Models\DispatchLog;
use App\Services\System\Communication\DispatchService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmailDispatchController extends Controller
{
    use ModelAction;

    public $dispatchService;
    public $gatewayManager;

    public function __construct() {
        $this->dispatchService = new DispatchService();
        $this->gatewayManager = new GatewayManager();
    }

    /**
     * index
     *
     * @return View
     */
    public function index(): View
    {
        Session::put("menu_active", true);
        return $this->dispatchService->loadLogs(ChannelTypeEnum::EMAIL);
    }

    /**
     * create
     *
     * @return View
     */
    public function create(): View
    {
        Session::put("menu_active", true);
        return $this->dispatchService->createDispatchLog(ChannelTypeEnum::EMAIL);
    }

    /**
     * show
     *
     * @param mixed $id
     * 
     * @return RedirectResponse
     */
    public function show(Request $request, $id): View
    {
        Session::put("menu_active", true);
        $raw = $request->query('raw') == '1';
        return $this->dispatchService->showDispatchLog(ChannelTypeEnum::EMAIL, $id, raw: $raw);
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
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(ChannelTypeEnum::EMAIL, $request);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify)->withInput();

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify)->withInput();
        }

    }

    /**
     * destroy
     *
     * @param mixed $id
     * 
     * @return RedirectResponse
     */
    public function destroy($id): RedirectResponse
    {
        try {
            return $this->dispatchService->destroyDispatchLog($id);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    public function bulk(Request $request): RedirectResponse {

        try {
            $request->merge([
                'column'    => 'id',
                'channel'   => ChannelTypeEnum::EMAIL,
                'value'     => $request->input('status'),
            ]);

            return $this->bulkAction($request, null,[
                "model" => new DispatchLog(),
                'additional_adjustments' => "channel",
                'additional_data'        => "gateway_id",
                'redirect_url'           => route("admin.communication.email.index"),
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
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
            return $this->dispatchService->resendDispatchLog(ChannelTypeEnum::EMAIL, $id);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * updateStatus
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function updateStatus(Request $request): RedirectResponse {

        try {
            return $this->dispatchService->updateDispatchLogStatus(ChannelTypeEnum::EMAIL, $request);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Download or view an email attachment securely.
     * Use ?view=1 to display inline (for images/PDFs), otherwise forces download.
     */
    public function downloadAttachment(Request $request, $logId, $storedName)
    {
        $log = DispatchLog::with('message')->findOrFail($logId);

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
