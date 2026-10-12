<?php

namespace App\Http\Controllers\User\Communication;

use Exception;
use Illuminate\View\View;
use App\Traits\ModelAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Service\Admin\Core\CustomerService;
use App\Http\Requests\WhatsappDispatchRequest;
use App\Services\System\Contact\ContactService;
use App\Services\System\Communication\DispatchService;

class WhatsappDispatchController extends Controller
{
    use ModelAction;
    public $dispatchService;
    public $customerService;
    public $contactService;

    public function __construct() {

        $this->contactService = new ContactService();
        $this->customerService = new CustomerService();
        $this->dispatchService = new DispatchService();
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
        return $this->dispatchService->loadLogs(channel: ChannelTypeEnum::WHATSAPP, user: $user);
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
        return $this->dispatchService->createDispatchLog(channel: ChannelTypeEnum::WHATSAPP, user: $user);
    }

    /**
     * store
     *
     * @param WhatsappDispatchRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(WhatsappDispatchRequest $request): RedirectResponse
    {
        try {
            $user = auth()->user();
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(type: ChannelTypeEnum::WHATSAPP, request: $request, user: $user);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify)->withInput();

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify)->withInput();
        }

    }
}
