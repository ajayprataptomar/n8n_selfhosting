<?php

namespace App\Http\Controllers\Admin\Contact;

use App\Enums\Common\Status;
use App\Enums\StatusEnum;
use App\Enums\System\ContactImportStatusEnum;
use Exception;
use App\Models\Contact;
use Illuminate\View\View;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use App\Models\ContactGroup;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\ContactGroupRequest;
use App\Models\ContactImport;
use App\Services\System\Contact\ContactService;
use Illuminate\Support\Facades\Log;

class ContactGroupController extends Controller
{
    use ModelAction;

    public ContactService $contactService;

    public function __construct(ContactService $contactService) { 

        $this->contactService = $contactService;
    }

    /**
     * index
     *
     * @param string|null $uid
     * 
     * @return View
     */
    public function index(string|null $uid = null): View {
        
        Session::put("menu_active", true);
        return $this->contactService->getContactGroups($uid); 
    }

    /**
     * store
     *
     * @param ContactGroupRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(ContactGroupRequest $request): RedirectResponse {
        
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->contactService->saveContactGroups($data);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * update
     *
     * @param ContactGroupRequest $request
     * @param string $uid
     * 
     * @return RedirectResponse
     */
    public function update(ContactGroupRequest $request, string $uid): RedirectResponse {
        
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->contactService->saveContactGroups($data, $uid);

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
    public function updateStatus(Request $request): string
    {
        try {

            $this->validateStatusUpdate(
                request: $request,
                tableName: 'contact_groups', 
                isJson: true,
                keyColumn: 'uid'
            );

            $notify = $this->statusUpdate(
                request: $request->except('_token'),
                actionData: [
                    'message'               => translate('Group status updated successfully'),
                    'model'                 => new ContactGroup,
                    'column'                => $request->input('column'),
                    'filterable_attributes' => [
                        'uid' => $request->input('uid')
                    ],
                    'reload'                => true
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
     * 
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, string $uid): RedirectResponse {
        
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->contactService->deleteContactGroup($data, $uid);

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
    public function bulk(Request $request): RedirectResponse {

        try {

            return $this->bulkAction($request, null,[
                "model" => new ContactGroup(),
                "parent_column" => "group_id"
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }





    //todo: Update after contacts
    public function fetch(Request $request, $type = null) {
        
        try {
            
            if ($type == "meta_data") {

                $groupIds = $request->input('group_ids');
                $channel = $request->input('channel');
               
                if($groupIds) {

                    $contacts = Contact::whereIn('group_id', $groupIds)
                                            ->where($channel.'_contact', '!=', '')
                                            ->get();

                    if ($contacts->isNotEmpty()) {

                        $groupAttributes = ContactGroup::whereIn('id', $groupIds)
                                                            ->whereNotNull('meta_data')
                                                            ->pluck('meta_data');
            
                        $mergedAttributes = [];
            
                        foreach ($groupAttributes as $attributes) {
                            $decodedAttributes = json_decode($attributes, true);
            
                            foreach ($decodedAttributes as $key => $attribute) {
    
                                if ($attribute['status'] === true) {
    
                                    if (!isset($mergedAttributes[$key]) || $mergedAttributes[$key] !== $attribute['type']) {
                                        $mergedAttributes[$key] = $attribute['type'];
                                    }
                                }
                            }
                        }
                        return response()->json(['status' => true, 'merged_attributes' => $mergedAttributes]);
                    } else {
    
                        return response()->json(['status' => false, 'message' => "No $channel contacts found for the selected groups"]);
                    }
                }
                else {
                    return response()->json(['status' => false, 'message' => translate("No groups are selected")]);
                }
            }
            
        } catch (\Exception $e) {
            
            $notify[] = ['error', translate('Something Went Wrong')];
            return back()->withNotify($notify);
        }
        
    }


    public function getImportProgress(Request $request)
    {
        $groupIds = $request->query('group_ids', []);

        if (empty($groupIds)) {
            return response()->json([]);
        }

        $groups = ContactGroup::whereIn('id', $groupIds)
            ->withCount('contacts')
            ->get()
            ->keyBy('id');
        
        $imports = ContactImport::whereIn('group_id', $groupIds)
            ->with(["group", "file"])
            ->get()
            ->groupBy('group_id');
        $response = [];
        foreach ($groupIds as $groupId) {
            $group = $groups[$groupId] ?? null;
            $groupImports = $imports[$groupId] ?? collect();

            $response[$groupId] = [
                'contacts_count' => $group ? $group->contacts_count : 0,
            ];
            
            if ($groupImports->isEmpty()) {
                $response[$groupId]['status'] = 'none';
                continue;
            }
            $activeImport = $groupImports->first(function ($import) {
                return in_array($import->status->value, [ContactImportStatusEnum::PENDING->value, ContactImportStatusEnum::PROCESSING->value]);
            });
            

            if ($activeImport) {
                $progress = $activeImport->total_contacts > 0
                    ? ($activeImport->processed_contacts / $activeImport->total_contacts) * 100
                    : 0;
                $response[$groupId]['status'] = $activeImport->status->value;
                $response[$groupId]['progress'] = $progress;
                $response[$groupId]['processed_contacts'] = $activeImport->processed_contacts;
                $response[$groupId]['total_contacts'] = $activeImport->total_contacts;
                $response[$groupId]['file_name'] = $activeImport->file?->name ?? 'Unknown';
                $response[$groupId]['created_at'] = $activeImport->created_at->toDateTimeString();
            } else {
                $latestImport = $groupImports->sortByDesc('created_at')->first();

                $response[$groupId]['status'] = $latestImport->status->value;
                $response[$groupId]['progress'] = 100;

                if ($latestImport->status->value === ContactImportStatusEnum::COMPLETED->value &&
                    (site_settings('email_contact_verification') == StatusEnum::TRUE->status() ||
                    site_settings('email_contact_verification') == Status::ACTIVE->value) &&
                    $latestImport->total_emails > 0) {

                    $emailVerificationProgress = $latestImport->total_emails > 0
                        ? ($latestImport->processed_emails / $latestImport->total_emails) * 100
                        : 0;

                    $response[$groupId]['email_verification_progress'] = $emailVerificationProgress;
                    $response[$groupId]['processed_emails'] = $latestImport->processed_emails;
                    $response[$groupId]['total_emails'] = $latestImport->total_emails;
                    $response[$groupId]['is_email_verification_in_progress'] = $latestImport->processed_emails < $latestImport->total_emails;

                    if ($response[$groupId]['is_email_verification_in_progress']) {
                        $response[$groupId]['status'] = 'VERIFYING_EMAILS';
                    }
                }
            }
        }

        return response()->json($response);
    }
}
