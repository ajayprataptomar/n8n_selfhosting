<?php

namespace App\Traits;

use App\Enums\Common\Status;
use App\Enums\StatusEnum;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\Gateway\SmsGatewayTypeEnum;
use App\Managers\GatewayManager;
use App\Models\AndroidSim;
use App\Models\Gateway;
use App\Models\Language;
use App\Models\User;
use App\Services\System\Communication\DispatchService;
use Illuminate\Support\Facades\Schema;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ModelAction
{
    /**
     * Summary of bulkAction
     * @param \Illuminate\Http\Request $request
     * @param string|null $dependentColumn
     * @param array|null $modelData
     * @return \Illuminate\Http\RedirectResponse
     */
    private function bulkAction(Request $request, string|null $dependentColumn = null, array|null $modelData = null): RedirectResponse
    {
        $status = 'success';
        $message = translate('Successfully performed bulk action');
        $model = Arr::get($modelData, 'model');
        $redirectUrl = Arr::get($modelData, 'redirect_url');
        $ids = Arr::get($request->all(), 'ids', []);

        if (empty($ids)) {
            $notify[] = ['error', translate('No items selected')];
            return back()->withNotify($notify);
        }

        $type = Arr::get($request->all(), 'type');

        try {
            DB::beginTransaction();

            if ($type === 'delete') {
                $chunkSize = 100;
                LazyCollection::make($ids)
                                    ->chunk($chunkSize)
                                    ->each(function ($chunk) use ($model, $modelData) {
                                        $chunk = $chunk->toArray();
                                        $tableName = (new $model)->getTable();
                            
                                        if (method_exists(new $model, 'getRelationships')) {
                                            $relationships = (new $model)->getRelationships() ?? [];
                                            foreach ($relationships as $relationship) {
                                                $relatedModel = (new $model)->$relationship()->getRelated();
                                                $relatedTable = $relatedModel->getTable();
                            
                                                $parentTableName = (new $model)->getTable();
                                                $singularParentTableName = Arr::get($modelData, "parent_column", Str::singular($parentTableName)."_id"); 
                                                DB::table($relatedTable)
                                                    ->whereIn($singularParentTableName, $chunk)
                                                    ->delete();
                                            }
                                        }
                            
                                        $query = DB::table($tableName)
                                            ->whereIn('id', $chunk);
                            
                                        $filterableAttributes = Arr::get($modelData, 'filterable_attributes', []);
                                        foreach ($filterableAttributes as $attribute => $value) {
                                            $query->where($attribute, $value);
                                        }
                            
                                        $query->delete();
                                    });

                $message = translate('Successfully deleted selected items');
            } elseif ($type === 'status') {
                $statusValue = Arr::get($request->all(), 'status');
                $channel = Arr::get($request->all(), 'channel');
                $gatewayId = Arr::get($request->all(), 'gateway_id');
                $method = Arr::get($request->all(), 'method');
                $column = 'status';

                $actionData = [
                    'model' => $model,
                    'filterable_attributes' => Arr::get($modelData, 'filterable_attributes', []),
                    'column' => $column,
                    'message' => translate('Status updated for item'),
                    'reload' => false,
                    'redirect' => false,
                ];

                if ($dependentColumn && $statusValue == \App\Enums\StatusEnum::FALSE->status()) {
                    $actionData['additional_adjustments'] = 'dependent_column';
                    $actionData['additional_data'] = $dependentColumn;
                } elseif ($channel && $gatewayId && $method) {
                    $actionData['additional_adjustments'] = 'channel';
                    $actionData['additional_data'] = 'gateway_id';
                }

                foreach ($ids as $id) {
                    $requestData = [
                        'id' => $id,
                        'value' => $statusValue,
                        'channel' => $channel,
                        'gateway_id' => $gatewayId,
                        'method' => $method,
                        'status' => $statusValue,
                        'column' => $column,
                    ];

                    $this->statusUpdate($requestData, $actionData, true);
                }

                $message = translate('Successfully updated status for selected items');
            }

            DB::commit();

            $notify[] = [$status, $message];
            return $redirectUrl
                ? redirect($redirectUrl)->withNotify($notify)
                : back()->withNotify($notify);
        } catch (\Exception $e) {
            DB::rollBack();
            $notify[] = ['error', translate('Failed to perform bulk action: ') . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete model with its relations
     *
     * @param Model $model
     * @return void
     */
    private function deleteWithRelations(Model $model): void {

        if (method_exists($model, 'getRelationships')) {

            foreach ($model->getRelationships() as $relation) {

                $relatedItems = $model->$relation()->get();
                foreach ($relatedItems as $relatedItem) {
                    
                    $relatedItem->delete();
                }
            }
        }
        $model->delete();
    }

    /**
     * Validate the status update request dynamically.
     *
     * @param Request $request
     * @param string $tableName The table name for existence check
     * @param string $keyColumn The column name for existence check (default: 'uid')
     * @param array $additionalRules Additional validation rules to merge
     * @return array Validated data with normalized value
     * @throws ValidationException
     */
    public function validateStatusUpdate(
        Request $request,
        string $tableName,
        bool $isJson = true,
        string $keyColumn = 'uid',
        array $additionalRules = []
    ): array {

        $rules = array_merge([
            $keyColumn => ['required', 'string', "exists:{$tableName},{$keyColumn}"],
            'column'   => ['nullable', 'string'],
            'value'    => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!in_array($value, [0, 1, '0', '1']) && !in_array($value, Status::getValues())) {
                        $fail(translate('Invalid Request'));
                    }
                },
            ],
        ], $additionalRules);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {

            throw new ValidationException($validator, $isJson ? response()->json([
                'status'  => false,
                'message' => $validator->errors()
            ]) : translate("Invalid Data"));
        }
        
        $value = $request->input('value');
        if (in_array($value, [0, '0'])) {
            $value = Status::INACTIVE;
        } elseif (in_array($value, [1, '1'])) {
            $value = Status::ACTIVE;
        }

        return array_merge($request->all(), ['value' => $value]);
    }

    /**
     * statusUpdate
     *
     * @param array $request
     * @param array $actionData
     * @param bool $isBulk
     * 
     * @return string
     */
    public function statusUpdate(array $request, array $actionData, bool $isBulk = false): string|null
    {
        $status  = true;
        $reload  = Arr::get($actionData, 'reload', false);
        $message = Arr::get($actionData, 'message', translate('Status Updated'));

        $model  = Arr::get($actionData, 'model');
        
        $data = $model::where(Arr::get($actionData, 'filterable_attributes', []))
                        ->when(Arr::get($actionData, 'recycle', false), fn(Builder $q) => $q->withTrashed())
                        ->where(function ($query) use ($request, $model) {
                            $query->where('id', Arr::get($request, 'id'));

                            if (Schema::hasColumn((new $model)->getTable(), 'uid')) {
                                $query->orWhere('uid', Arr::get($request, 'uid'));
                            }
                        })
                        ->first();
        
        $column = Arr::get($actionData, 'column', 'status');
        $value  = Arr::get($request, 'value');
        
        if (
            $column === 'status' && 
            Schema::hasColumn((new $model)->getTable(), 'is_default') && 
            $data->is_default && 
            ($value == StatusEnum::FALSE->status() || $value == Status::INACTIVE->value)
        ) {
            $status = false;
            $message = translate('Cannot deactivate a default item');
            return $isBulk 
                ? null 
                : json_encode([
                    'reload'  => $reload,
                    'status'  => $status,
                    'message' => $message,
                ]);
        }
        if($column === 'is_default' && empty($data->getRawOriginal('status')) || $data->getRawOriginal('status') === '') {

            $status = false;
            $message = translate('Cannot make an inactive item default');
            return $isBulk 
                ? null 
                : json_encode([
                    'reload'  => $reload,
                    'status'  => $status,
                    'message' => $message,
                ]);
        }
        if (
            $column === 'is_default' && 
            ($data->status == StatusEnum::FALSE->status() || @$data?->status?->value == Status::INACTIVE->value) && 
            ($value == StatusEnum::TRUE->status() || $value == Status::ACTIVE->value)
        ) {
            $status = false;
            $message = translate('Cannot make an inactive item default');
            return $isBulk 
                ? null 
                : json_encode([
                    'reload'  => $reload,
                    'status'  => $status,
                    'message' => $message,
                ]);
        }
        
        if(Arr::has($actionData, 'additional_adjustments')) 
            $this->updateAdditionalData($request, $actionData, $data);
        
        $data->setRawAttributes([$column => $value]);
        $data->save();
        
        return $isBulk 
                    ? null 
                    : (Arr::get($actionData, 'redirect') 
                        ? $message 
                        : json_encode([
                            'reload'  => $reload,
                            'status'  => $status,
                            'message' => $message,
                        ]));
    }

    /**
     * updateAdditionalData
     *
     * @param array $request
     * @param array $actionData
     * @param Model $data
     * @param User|null $user
     * 
     * @return void
     */
    public function updateAdditionalData(array $request, array $actionData, Model $data, ?User $user = null): void {
        
        if(Arr::get($actionData, "additional_adjustments") == "channel" 
            && Arr::get($actionData, "additional_data") == "gateway_id"
            && Arr::get($request, "channel")) {
                
            $gatewayData = $this->gatewayManager->getGatewayForDispatch(Arr::get($request, "channel"), Arr::get($request, "gateway_id"), Arr::get($request, "method"), $user);
            
            $gatewayableType = (Arr::get($request, "channel") == ChannelTypeEnum::SMS 
                                    && Arr::get($request, "method") == SmsGatewayTypeEnum::ANDROID->value)
                                        ? AndroidSim::class
                                        : Gateway::class;
                
            if ($gatewayData instanceof Collection && Arr::get($request, "gateway_id") == "-1") {
                
                $gateway = $gatewayData->first();
                $gatewayableId = $gateway->id;
            } elseif($gatewayData instanceof Collection && Arr::get($request, "gateway_id") == "0") {

                $gateway = $gatewayData->random();
                $gatewayableId = $gateway->id;
            } else {

                $gatewayableId = $gatewayData->id;
            }

            $data->gatewayable_type = $gatewayableType; 
            $data->gatewayable_id   = $gatewayableId; 
            $data->save();
        }

        if(Arr::get($actionData, "additional_adjustments") == "default_gateway") { 
            
            Gateway::where("channel", Arr::get($actionData, "filterable_attributes.channel"))
                        ->where("user_id", $data->user_id)
                        ->where("is_default", true)
                        ->update(["is_default" => false]);
        }

        if(Arr::get($actionData, "additional_adjustments") == "default_language") { 
            
            Language::where('id', '!=',$data->id)->update(["is_default" => StatusEnum::FALSE->status()]);
            $data->is_default = StatusEnum::TRUE->status();
            $data->update();
            session(['locale' => $data->code]);
        }
        
    }
}
