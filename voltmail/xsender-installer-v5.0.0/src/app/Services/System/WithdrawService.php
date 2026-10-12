<?php

namespace App\Services\System;

use App\Enums\Common\Status;
use App\Enums\WithdrawLogEnum;
use App\Exceptions\ApplicationException;
use App\Models\User;
use App\Models\WithdrawLog;
use App\Models\WithdrawMethod;
use App\Service\Admin\Core\FileService;
use App\Traits\Manageable;
use Illuminate\View\View;
use App\Services\Core\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class WithdrawService
{
     use Manageable;

     public UserService $userService;

     /**
      * Summary of getWithdrawLogs
      * @param \App\Models\User|null $user
      * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
      */
     public function getWithdrawLogs(User|null $user = null): View {

          $title         = translate("Withdraw Logs");
          $searchArray   = ["trx_code", "final_amount"];
          $filterArray   = ["status"];
          $relationArray = ["user", "method"];
          $attributes    = [];
          if($user) { 
               $attributes["user_id"] = $user->id;
          }
          $logs = $this->getPaginatedLogs(
               model: new WithdrawLog(),
               specificDateColumn: null,
               counters: null,
               relations: $relationArray,
               select: null,
               search: $searchArray,
               filter: $filterArray,
               attributes: $attributes
          );
          return view($user 
                            ? "user.withdraw.index" 
                            : 'admin.report.withdraw.index', 
            compact('title', 'logs'));
     }

     /**
      * Summary of createWithdrawRequest
      * @param \App\Models\User $user
      * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
      */
     public function createWithdrawRequest(User $user): View {

          $title = translate("Create Withdraw Request");
          $attributes    = [
               "status"  => Status::ACTIVE->value,
          ];
          $selectArray   = ["uid", "currency_code", "name", "duration", "image", "minimum_amount", "maximum_amount", "fixed_charge", "percent_charge", "note", "parameters"];
          $methods = $this->getCollectionLogs(
               model: new WithdrawMethod(),
               specificDateColumn: null,
               counters: null,
               relations: null,
               select: $selectArray,
               search: null,
               filter: null,
               attributes: $attributes
          );
          $pendingWithdrawSum = WithdrawLog::where('user_id', $user->id)
                                                  ->where('status', WithdrawLogEnum::PENDING->value)
                                                  ->sum('final_amount');
          $usableBalance = $user->wallet_balance - $pendingWithdrawSum;
          
          $usableBalance = convertCurrency($usableBalance, 'USD', getDefaultCurrencyCode());
          
          return view('user.withdraw.create', compact('title', 'methods', 'user', 'usableBalance'));
     }

     /**
      * Summary of saveWithdrawRequest
      * @param \App\Models\User $user
      * @param array $data
      * @throws \App\Exceptions\ApplicationException
      * @return JsonResponse
      */
     public function saveWithdrawRequest(User $user, array $data): JsonResponse|ApplicationException {
        
          $method = $this->getSpecificLogByColumn(new WithdrawMethod(), 'uid', $data['method_uid'] ?? null);
          if (!$method || $method->status != Status::ACTIVE->value) {
               throw new ApplicationException(translate("Withdraw method not found or inactive"), Response::HTTP_NOT_FOUND);
          }

          $amounts = $this->calculateWithdrawAmounts($data, $method);
          if (!$amounts) {
               throw new ApplicationException('Withdraw calculation mismatch', Response::HTTP_UNPROCESSABLE_ENTITY);
          }

          $customData = $this->prepareCustomData($data, $method);
          $withdrawLog = WithdrawLog::create([
               'method_id'      => $method->id,
               'user_id'        => $user->id,
               'currency_code'  => $method->currency_code,
               'trx_code'       => uniqid('trx_'),
               'amount'         => Arr::get($amounts, "amount", 0),
               'charge'         => Arr::get($amounts, "total_charge", 0),
               'final_amount'   => Arr::get($amounts, "final_amount", 0),
               'custom_data'    => $customData,
          ]);

          return response()->json([
               'reload'    => true,
               'status'    => true,
               'message'   => translate('Withdraw request created successfully'),
          ]);

     }










     ## -------------------------- ##
     ## Additional Private Methods ##
     ## -------------------------- ##

     /**
      * Summary of calculateWithdrawAmounts
      * @param array $data
      * @param \App\Models\WithdrawMethod $method
      * @return array{amount: float, final_amount: float, total_charge: float|null}
      */
     private function calculateWithdrawAmounts(array $data, WithdrawMethod $method): ?array
     {
          $currencies      = json_decode(site_settings('currencies'), true);
          $defaultCurrency = getDefaultCurrencyCode($currencies);
          if (!$defaultCurrency) return null;

          $walletBalance      = auth()->user()->wallet_balance;
          $amount             = floatval($data['withdraw_amount'] ?? 0);
          $fixed_charge       = floatval($data['withdraw_fixed_charge'] ?? 0);
          $percent_charge     = floatval($data['withdraw_percent_charge'] ?? 0);
          $total_charge       = floatval($data['withdraw_total_charge'] ?? 0);
          $total              = floatval($data['withdraw_total'] ?? 0);
          $final_amount       = floatval($data['withdraw_final_amount'] ?? 0);
             
          $minimumAmount = (double)convertCurrency($method->minimum_amount, $method->currency_code, $defaultCurrency);
          $maximumAmount = (double)convertCurrency($method->maximum_amount, $method->currency_code, $defaultCurrency);
          if (($amount < $minimumAmount || $amount > $maximumAmount)) {
               throw new ApplicationException('Amount must be within limits', Response::HTTP_UNPROCESSABLE_ENTITY);
          }
          if(convertCurrency(($final_amount), $method->currency_code, "USD") > $walletBalance) {
               throw new ApplicationException('Insufficient funds', Response::HTTP_UNPROCESSABLE_ENTITY);
          }
          $expected_percent_charge   = $amount * $method->percent_charge / 100;
          $expected_total_charge     = (double)convertCurrency($method->fixed_charge, $method->currency_code, $defaultCurrency) + $expected_percent_charge;
          $expected_total            = $amount + $expected_total_charge;
          $expected_final_amount     = (double)convertCurrency($expected_total, $defaultCurrency, $method->currency_code);

          if (
               abs($total_charge - $expected_total_charge) > 0.01 ||
               abs($total - $expected_total) > 0.01 ||
               abs($final_amount - $expected_final_amount) > 0.01
          ) {
               return null;
          }

          $amount_usd = (float) convertCurrency($amount, $defaultCurrency, 'USD');
          $total_charge_usd = (float) convertCurrency($total_charge, $defaultCurrency, 'USD');
          $final_amount_usd = (float) convertCurrency($final_amount, $method->currency_code, 'USD');

          return [
               'amount' => $amount_usd,
               'total_charge' => $total_charge_usd,
               'final_amount' => $final_amount_usd,
          ];
     }

     /**
      * Summary of prepareCustomData
      * @param array $data
      * @param \App\Models\WithdrawMethod $method
      * @return array
      */
     private function prepareCustomData(array $data, WithdrawMethod $method): array
     {
          $custom = [];
          if (is_array($method->parameters)) {
               foreach ($method->parameters as $key => $param) {
                    $field_name = $param['field_name'] ?? $key;
                    if (isset($data[$field_name])) {
                         if (($param['field_type'] ?? null) === 'file' && $data[$field_name] instanceof UploadedFile) {
                              try {

                                   $fileService = new FileService();
                                   $fileName = $fileService->uploadFile(file: $data[$field_name], key: "withdraw_request", delete_file:false);
                                   $custom[$field_name] = [
                                        "field_name" => $fileName,
                                        "field_type" => $param['field_type'],
                                   ];
                              } catch(\Exception $exp) {
                                   throw new ApplicationException(translate("Could not upload file"), Response::HTTP_INTERNAL_SERVER_ERROR);
                              }
                        
                         } else {
                              $custom[$field_name] = [
                                   "field_name" => $data[$field_name],
                                   "field_type" => $param['field_type'] ?? 'text'
                              ];
                         }
                    }
               }
          }
          return $custom;
     }
}