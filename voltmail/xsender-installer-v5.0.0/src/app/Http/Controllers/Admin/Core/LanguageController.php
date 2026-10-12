<?php

namespace App\Http\Controllers\Admin\Core;

use Exception;
use App\Models\Language;
use Illuminate\View\View;
use Illuminate\Support\Arr;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Service\Admin\Core\LanguageService;
use App\Http\Requests\Admin\LanguageRequest;
use Illuminate\Validation\ValidationException;

class LanguageController extends Controller
{
    use ModelAction;

    public $languageService;
    public function __construct() {

        $this->languageService = new LanguageService();
    }

    /**
     * @return \Illuminate\View\View
     * 
     */
    public function index(): View {

        Session::put("menu_active", true);
        return $this->languageService->getLanguages();
    }

    /**
     *
     * @param LanguageRequest
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function store(LanguageRequest $request): RedirectResponse {

        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->languageService->store($data);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     *
     * @param LanguageRequest
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function update(LanguageRequest $request) {

        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->languageService->update($data);

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
     * @return string
     */
    public function updateStatus(Request $request): string {
        
        try {
            $this->validateStatusUpdate(
                request: $request,
                tableName: 'languages', 
                isJson: true,
                keyColumn: 'id'
            );

            $actionData = [
                'message' => translate('Language status updated successfully'),
                'model'   => new Language,
                'column'  => $request->input('column'),
                'filterable_attributes' => [
                    'id' => $request->input('id')
                ],
                'reload' => true
            ];
            
            $isDefault = $request->input("column", "status") != "status";
            if($isDefault) $actionData = Arr::set($actionData, "additional_adjustments", "default_language");

            $notify = $this->statusUpdate(
                request: $request->except('_token'),
                actionData: $actionData
            );

            return $notify;

        } catch (Exception $e) {
            
            return response()->json([
                'status'    => false,
                'message'   => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
        }
    }

    /**
     * delete
     *
     * @param string|int|null $id
     * 
     * @return RedirectResponse
     */
    public function delete(string|int|null $id = null): RedirectResponse {
        
        try {
            return $this->languageService->destoryLanguage($id);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * translate
     *
     * @param string|null $code
     * 
     * @return View
     */
    public function translate(string|null $code = null): View {

        Session::put("menu_active", true);
        return $this->languageService->getTranslations($code);
       
    }

    /**
     * languageDataUpdate
     *
     * @param Request $request
     * 
     * @return string
     */
    public function languageDataUpdate(Request $request): string {
        
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->languageService->translateLang(Arr::get($data, "data"));

        } catch (ApplicationException $e) {
            
            return response()->json([
                'status' => false,
                'message' => translate($e->getMessage()),
            ], $e->getStatusCode()); 

        } catch (Exception $e) {
            
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
        }
    }

    /**
     * languageDataDelete
     *
     * @param Request $request
     * 
     * @return RedirectResponse
     */
    public function languageDataDelete(Request $request): RedirectResponse {
        
        try {

            $request->validate(['uid' => 'required']);
            return $this->languageService->destoryKey($request->input('uid'));

        } catch (ValidationException $e) {

            $notify[] = ["error", translate("Required content is missing")];
            return back()->withNotify($notify);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
