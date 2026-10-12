<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Traits\ModelAction;
use Exception;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Service\Admin\Core\CustomerService;
use App\Http\Requests\WhatsappDispatchRequest;
use App\Models\DispatchLog;
use App\Services\System\Contact\ContactService;
use App\Services\System\Communication\DispatchService;

class WhatsappDispatchController extends Controller
{
    use ModelAction;

    public $contactService;
    public $dispatchService;
    public $customerService;

    public function __construct() {

        $this->contactService   = new ContactService();
        $this->customerService  = new CustomerService();
        $this->dispatchService  = new DispatchService();
    }

    /**
     * index
     *
     * @return View
     */
    public function index(): View
    {
        Session::put("menu_active", true);
        return $this->dispatchService->loadLogs(ChannelTypeEnum::WHATSAPP);
    }

    /**
     * Summary of chats
     * @return View
     */
    public function chats(): View
    {
        Session::put("menu_active", true);
        return $this->dispatchService->loadChats();
    }

    /**
     * create
     *
     * @return View
     */
    public function create(): View
    {
        Session::put("menu_active", true);
        return $this->dispatchService->createDispatchLog(ChannelTypeEnum::WHATSAPP);
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
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(ChannelTypeEnum::WHATSAPP, $request);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify)->withInput();

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify)->withInput();
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
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
                'channel'   => ChannelTypeEnum::WHATSAPP,
                'value'     => $request->input('status'),
            ]);

            return $this->bulkAction($request, null,[
                "model" => new DispatchLog(),
                'additional_adjustments' => "channel",
                'additional_data'        => "gateway_id",
                'redirect_url'           => route("admin.communication.whatsapp.index"),
            ]);

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
            return $this->dispatchService->updateDispatchLogStatus(ChannelTypeEnum::WHATSAPP, $request);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
