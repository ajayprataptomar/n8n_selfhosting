<?php

namespace App\Http\Controllers\Admin\Contact;

use Exception;
use App\Models\Contact;
use Illuminate\View\View;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\ContactRequest;
use Illuminate\Support\Facades\Session;
use App\Service\Admin\Core\FileService;
use App\Exceptions\ApplicationException;
use Illuminate\Validation\ValidationException;
use App\Services\System\Contact\ContactService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContactController extends Controller
{
    use ModelAction;
    
    public FileService $fileService;
    public ContactService $contactService;

    /**
     * __construct
     *
     * @param FileService $fileService
     * @param ContactService $contactService
     */
    public function __construct(ContactService $contactService, FileService $fileService) { 

        $this->contactService = $contactService;
        $this->fileService    = $fileService;
    }

    /**
     * index
     *
     * @param int|string|null $group_id
     * 
     * @return View
     */
    public function index(int|string|null $group_id = null): View {
        
        Session::put("menu_active", true);
        return $this->contactService->getContacts($group_id);
    }

    /**
     * exportContacts
     *
     * @param Request $request
     * @param int|string|null $id
     * 
     * @return mixed
     */
    public function exportContacts(Request $request, int|string|null $groupId = null): mixed {

        try {

            return $this->contactService->exportContacts($request, $groupId);
            
        } catch (Exception $e) {
            
            return response()->json([
                "status"  => false, 
                "message" => getEnvironmentMessage($e->getMessage())
            ]);
        }
    }

    /**
     * create
     *
     * @param int|string|null $group_id
     * 
     * @return View
     */
    public function create(int|string|null $group_id = null):View {
        
        Session::put("menu_active", true);
        return $this->contactService->createContact($group_id);
    }

    /**
     *
     * @param ContactRequest $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function store(ContactRequest $request) {
        
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->contactService->contactSave($data);

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
                tableName: 'contacts', 
                isJson: true,
                keyColumn: 'id'
            );

            $notify = $this->statusUpdate(
                request: $request->except('_token'),
                actionData: [
                    'message' => translate('Contact status updated successfully'),
                    'model'   => new Contact,
                    'column'  => $request->input('column'),
                    'filterable_attributes' => [
                        'id' => $request->input('id')
                    ],
                    'reload' => true
                ]
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
     * destroy
     *
     * @param Request $request
     * @param string $uid
     * 
     * @return RedirectResponse
     */
    public function destroy(Request $request, string $uid): RedirectResponse {
        
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->contactService->deleteContact($uid);

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
     * @param Request $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function bulk(Request $request) :RedirectResponse {

        try {

            return $this->bulkAction($request, null,[
                "model" => new Contact(),
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * singleEmailVerification
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function singleEmailVerification(Request $request): JsonResponse {

        try {

            $request->validate(['uid' => 'required']);
            $result = $this->contactService->singleContactEmailVerification($request);
            return $result; 

        } catch (ValidationException $e) {
            
            return response()->json([
                'status' => false,
                'message' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY); 

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
     * demoFile
     *
     * @param string|null $type
     * 
     * @return BinaryFileResponse
     */
    public function demoFile(?string $type = null):BinaryFileResponse|RedirectResponse {

        try {
            return $this->fileService->generateContactDemo(type: $type, allow_attribute: true);
        } catch (\Exception $e) {
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * uploadFile
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function uploadFile(Request $request): JsonResponse {

        try {

            return $this->contactService->contactUploadFile($request);
        } catch (Exception $e) {
            
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
        }
    }

    /**
     * deleteFile
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function deleteFile(Request $request): JsonResponse {

        try {

            return $this->fileService->deleteContactFile($request->input('file_name'));

        } catch (Exception $e) {

            return response()->json([
    
                'status'  => false, 
                'message' => getEnvironmentMessage($e->getMessage()),
            ]);
        }
    }

    /**
     * parseFile
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function parseFile(Request $request): JsonResponse {

        try {

            return $this->fileService->parseContactFile($request->input('filePath'));

        } catch (Exception $e) {

            return response()->json([

                'error' => getEnvironmentMessage($e->getMessage()),
            ]);
        }
    }

    /**
     * Search contacts for AJAX select
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse {

        $search = $request->input('search', '');
        $perPage = 20;

        $contacts = Contact::query()
            ->whereNull('user_id') // Admin contacts only
            ->active() // Use the scope for active status
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email_contact', 'like', "%{$search}%")
                      ->orWhere('sms_contact', 'like', "%{$search}%")
                      ->orWhere('whatsapp_contact', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->paginate($perPage);

        return response()->json($contacts);
    }
}
