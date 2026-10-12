<?php

namespace App\Service\Admin\Core;

use App\Models\Setting;
use App\Models\Gateway;
use App\Enums\StatusEnum;
use App\Models\PricingPlan;
use Illuminate\Support\Arr;
use App\Enums\Common\Status;
use App\Enums\SettingKey;
use App\Models\AndroidSession;
use Illuminate\Http\UploadedFile;
use App\Enums\System\ChannelTypeEnum;
use App\Rules\FileExtentionCheckRule;
use Illuminate\Support\Facades\Cache;
use App\Enums\System\SessionStatusEnum;
use App\Exceptions\ApplicationException;
use App\Service\Admin\Core\FileService;
use Illuminate\Http\Response;

class SettingService {

    public function getIndex($type) {


        $data = [

            "title" => translate(ucfirst($type)),
        ];
        switch($type) {

            case "general":

                $type_data = [
                    "countries"     => json_decode(file_get_contents(resource_path('views/partials/country_file.json'))),
                    "timeLocations" => collect(timezone_identifiers_list())->groupBy(function($item) {
                        return explode('/', $item)[0];
                    })
                ];
                $data = array_merge($data, $type_data);
                break;

            case "member" :

                $type_data = [
                    
                    "plans"  => PricingPlan::active()
                                                ->select('id', 'name')
                                                ->latest()
                                                ->get(),

                    "sms_api_gateways"  => Gateway::whereNull('user_id')
                                                        ->where("channel", ChannelTypeEnum::SMS)
                                                        ->where('status', Status::ACTIVE)
                                                        ->orderBy('is_default', 'DESC')
                                                        ->get(),
                    "sms_android_gateways"  => AndroidSession::whereNull('user_id')
                                                                    ->where('status', SessionStatusEnum::CONNECTED)
                                                                    ->with(['androidSims'])
                                                                    ->orderBy('id', 'DESC')
                                                                    ->get(),
                    "mail_gateways" => Gateway::whereNull('user_id')
                                                    ->where("channel", ChannelTypeEnum::EMAIL)
                                                    ->where('status', Status::ACTIVE)
                                                    ->orderBy('is_default', 'DESC')
                                                    ->get(),
                ];
                $data = array_merge($data, $type_data);
                break;
            case "authentication":
                
                $data["title"] = translate("Authentication Page Setup");

                case "automation":
                    $phpPath = PHP_BINARY ?: '/usr/bin/php';
                    $queueConfig = site_settings('queue_connection_config', config('setting.site_settings.queue_connection_config', [
                            'driver' => 'database',
                            'connection' => [
                                'host'      => null,
                                'port'      => null,
                                'database'  => null,
                                'username'  => null,
                                'password'  => null,
                            ],
                        ]));
                    if(gettype($queueConfig) == "string") $queueConfig = json_decode($queueConfig, true);

                    // Get current automation mode
                    $currentMode = site_settings('automation_mode', 'auto');

                    $type_data = [
                        "title"     => translate("Automation Settings"),
                        "currentMode" => $currentMode,
                        "domain"    => request()->getHost(),
                        "curl" => [
                            "all_queues_url"    => route('queue.work'),
                            "cron_run_url"      => route('cron.run'), 
                            "queues" => [
                                "default"   => route('queue.work.default'),
                                "import-contacts"   => route('queue.work.import-contacts'),
                                "verify-email"      => route('queue.work.verify-email'),
                                "dispatch-logs"     => route('queue.work.dispatch-logs'),
                                "regular-sms"       => route('queue.work.regular-sms'),
                                "regular-email"     => route('queue.work.regular-email'),
                                "regular-whatsapp"  => route('queue.work.regular-whatsapp'),
                                "campaign-sms"      => route('queue.work.campaign-sms'),
                                "campaign-email"    => route('queue.work.campaign-email'),
                                "campaign-whatsapp" => route('queue.work.campaign-whatsapp'),
                            ],
                            "worker_trigger_command" => $phpPath . base_path('artisan') . " queue:work:worker-trigger",
                        ],
                        "command" => [
                            "commands" => [
                                "default"           => $phpPath . " ". base_path('artisan') . " queue:work",
                                "import-contacts"   => $phpPath . " ". base_path('artisan') . " queue:work:import-contacts",
                                "verify-email"      => $phpPath . " ". base_path('artisan') . " queue:work:verify-email",
                                "dispatch-logs"     => $phpPath . " ". base_path('artisan') . " queue:work:dispatch-logs",
                                "regular-sms"       => $phpPath . " ". base_path('artisan') . " queue:work:regular-sms",
                                "regular-email"     => $phpPath . " ". base_path('artisan') . " queue:work:regular-email",
                                "regular-whatsapp"  => $phpPath . " ". base_path('artisan') . " queue:work:regular-whatsapp",
                                "campaign-sms"      => $phpPath . " ". base_path('artisan') . " queue:work:campaign-sms",
                                "campaign-email"    => $phpPath . " ". base_path('artisan') . " queue:work:campaign-email",
                                "campaign-whatsapp" => $phpPath . " ". base_path('artisan') . " queue:work:campaign-whatsapp",
                                "worker-trigger"    => $phpPath . " ". base_path('artisan') . " queue:work:worker-trigger",
                            ],
                        ],
                        "supervisor" => [
                            "root_dir"      => base_path(),
                            "user"          => get_current_user() ?: 'www-data',
                            "group"         => function_exists('posix_getgrgid') && posix_getgrgid(posix_getegid())['name'] ?? 'www-data',
                            "artisan_path"  => base_path('artisan'),
                            "php_binary"    => $phpPath,
                        ],
                        "queue_info" => [   
                            "priority_order" => [
                                'dispatch_logs',
                                "regular-email",
                                "regular-sms",
                                "regular-whatsapp",
                                "campaign-email",
                                "campaign-sms",
                                "campaign-whatsapp",
                                'import-contacts',
                                'verify-email',
                            ],
                            "no_auth_warning" => translate("cURL routes are publicly accessible."),
                        ],
                        "cron_path" => base_path('artisan'),
                        "connections" => [
                            "driver"    => Arr::get($queueConfig, 'driver'),
                            "sync"      => [],
                            "database" => [],
                            "beanstalkd" => [
                                "host" => [
                                    "label"         => translate("Beanstalkd Host"),
                                    "placeholder"   => translate("e.g., localhost"),
                                    "required"      => true,
                                    "value"         => Arr::get($queueConfig, 'connection.host'),
                                ],
                                "port" => [
                                    "label" => translate("Beanstalkd Port"),
                                    "placeholder"   => translate("e.g., 11300"),
                                    "required"      => false,
                                    "value"         => Arr::get($queueConfig, 'connection.port'),
                                    
                                ],
                            ],
                            "sqs" => [
                                "key" => [
                                    "label"         => translate("AWS Access Key ID"),
                                    "placeholder"   => translate("e.g., AKIA..."),
                                    "required"      => true,
                                    "value"         => Arr::get($queueConfig, 'connection.key'),
                                    
                                ],
                                "secret" => [
                                    "label"         => translate("AWS Secret Access Key"),
                                    "placeholder"   => translate("e.g., your_secret"),
                                    "required"      => true,
                                    "value"         => Arr::get($queueConfig, 'connection.hsecretost'),
                                    
                                ],
                                "prefix" => [
                                    "label"         => translate("SQS Prefix"),
                                    "placeholder"   => translate("e.g., https://sqs.us-east-1.amazonaws.com/your-account-id"),
                                    "required"      => false,
                                    "value"         => Arr::get($queueConfig, 'connection.prefix'),
                                    
                                ],
                                "queue" => [
                                    "label"         => translate("SQS Queue Name"),
                                    "placeholder"   => translate("e.g., default"),
                                    "required"      => false,
                                    "value"         => Arr::get($queueConfig, 'connection.queue'),
                                    
                                ],
                                "region" => [
                                    "label"         => translate("AWS Region"),
                                    "placeholder"   => translate("e.g., us-east-1"),
                                    "required"      => false,
                                    "value"         => Arr::get($queueConfig, 'connection.region'),
                                    
                                ],
                            ],
                            "redis" => [
                                "host" => [
                                    "label"         => translate("Redis Host"),
                                    "placeholder"   => translate("e.g., 127.0.0.1"),
                                    "required"      => true,
                                    "value"         => Arr::get($queueConfig, 'connection.host'),
                                    
                                ],
                                "port" => [
                                    "label"         => translate("Redis Port"),
                                    "placeholder"   => translate("e.g., 6379"),
                                    "required"      => false,
                                    "value"         => Arr::get($queueConfig, 'connection.port'),
                                    
                                ],
                                "database" => [
                                    "label"         => translate("Redis Database"),
                                    "placeholder"   => translate("e.g., 0"),
                                    "required"      => false,
                                    "value"         => Arr::get($queueConfig, 'connection.database'),
                                    
                                ],
                                "username" => [
                                    "label"         => translate("Redis Username"),
                                    "placeholder"   => translate("Optional"),
                                    "required"      => false,
                                    "value"         => Arr::get($queueConfig, 'connection.username'),
                                    
                                ],
                                "password" => [
                                    "label"         => translate("Redis Password"),
                                    "placeholder"   => translate("Optional"),
                                    "required"      => false,
                                    "value"         => Arr::get($queueConfig, 'connection.password'),
                                    
                                ],
                            ],
                        ],
                    ];
                    $data = array_merge($data, $type_data);
                    break;
        }

        return $data;
    }
    
    /**
     * settings validations
     * 
     * @return array
     */
    public function validationRules(array $request_data ,string $key = 'site_settings') :array{

        $rules      = [];
        $message    = [];

        foreach ($request_data as $data_key => $data_value) {

            if ($data_value instanceof UploadedFile) {

                $rules[$key . "." . $data_key] = ['nullable', 'image', new FileExtentionCheckRule(json_decode(site_settings('mime_types'), true))];
            } else {
                
                $rules[$key . "." . $data_key] = ['nullable'];
                $messages[$key . "." . $data_key . '.nullable'] = ucfirst(str_replace('_', ' ', $data_key)) . ' ' . translate('Field is Required');
            }
        }
        return [
            'rules'   => $rules,
            'message' => $message
        ];
    }

    /**
     * updateSettings
     *
     * @param array $request_data
     * @param string|null|null $channel
     * 
     * @return void
     */
    public function updateSettings(array $request_data, string|null $channel = null): void {

        $json_keys = Arr::get(config('setting'), 'json_object', []);
        $fileService = new FileService();
        foreach ($request_data as $key => $value) {
            
            if ($value instanceof UploadedFile) {
                
                $filePath = config("setting.file_path.$key")['path'];
                $fileName = $fileService->uploadFile(file: $value, key: $key, file_path: $filePath);
                
                if ($fileName) {
                    
                    $value = $fileName;
                }
            } elseif (in_array($key, $json_keys)) {

                $value              = $this->processNestedFiles($value, $key, $fileService);
                $existingSetting    = Setting::where('key', $key)->first();
                $existingData       = $existingSetting && $existingSetting->value ? json_decode($existingSetting->value, true) : [];

                // Keys that should REPLACE existing data instead of merging
                // These are settings where users can add/remove items dynamically
                $replaceKeys = [
                    SettingKey::COMMON_DOMAIN->value,           // Domain typos - user can delete rows
                    SettingKey::DISPOSABLE_DOMAIN_LIST->value,  // Disposable domains list
                    SettingKey::EMAIL_ROLE_LIST->value,         // Email roles list
                    SettingKey::TLD_LIST->value,                // TLD list
                ];

                $specialKeys = [

                    SettingKey::ANDROID_OFF_CANVAS_GUIDE->value => ['external_guide', 'written_guide'],
                ];

               if (array_key_exists($key, $specialKeys)) {
                    $mergedData = [];
                    foreach ($specialKeys[$key] as $subKey) {
                        $currentValue = isset($value[$subKey]) ? $value[$subKey] : $value;
                        $currentExisting = isset($existingData[$subKey]) ? $existingData[$subKey] : $existingData;
                        $currentExisting = is_array($currentExisting) ? $currentExisting : [];
                        $mergedData[$subKey] = array_merge($currentExisting, (array)$currentValue);
                    }
                } elseif (in_array($key, $replaceKeys)) {
                    // For replaceable settings, use new value directly (allows deletion)
                    // Filter out empty values to clean up the data
                    $mergedData = array_values(array_filter((array)$value, function($item) {
                        if (is_array($item)) {
                            // For nested arrays like common_domain, check if name is not empty
                            $name = Arr::get($item, 'name.0', Arr::get($item, 'name', ''));
                            return !empty($name);
                        }
                        return !empty($item);
                    }));
                } else {
                    $mergedData = array_merge($existingData, (array)$value);
                    if (array_keys($mergedData) === range(0, count($mergedData) - 1)) {
                        $mergedData = array_values(array_unique($mergedData, SORT_REGULAR));
                    }
                }

                $value = json_encode($mergedData);
            }
            try {
                Setting::updateOrInsert(
                    [
                        'key'   => $key
                    ],
                    [
                        'channel' => $channel,
                        'value' => $value
                    ]
                );
                
                Cache::forget("site_settings");
            } catch (\Exception $th){
            }
        }
    }

    /**
     * processNestedFiles
     *
     * @param array $data
     * @param string $key
     * @param FileService $fileService
     * 
     * @return array
     */
    private function processNestedFiles(array $data, string $key, FileService $fileService): array
    {
        foreach ($data as $index => $item) {
            if ($item instanceof UploadedFile) {
                
                $filePath = config("setting.file_path.$key")['path'];

                $fileName = $fileService->uploadFile(file: $item, key: $key, file_path: $filePath);

                if ($fileName) {
                    $data[$index] = $fileName;
                } else {
                    // If file upload fails, remove the field to avoid storing the UploadedFile object
                    unset($data[$index]);
                }
            } elseif (is_array($item)) {

                $data[$index] = $this->processNestedFiles($item, $key, $fileService);
            }
        }

        return $data;
    }

    public function prepData($request)
    {
        $is_default = null;
        $data['currencies'] = json_decode(site_settings("currencies"), true);

        if ($request->has('old_code')) {
            if ($request->input('code') != $request->input('old_code')) {
                throw new ApplicationException("Currency code cannot be changed during update", Response::HTTP_NOT_FOUND);
            }
            $is_default = $data['currencies'][$request->input('old_code')]['is_default'];
            unset($data['currencies'][$request->input('old_code')]);
        }

        $data['currencies'][$request->input('code')] = [
            'name'       => $request->input('name'),
            'symbol'     => $request->input('symbol'),
            'rate'       => $request->input('rate'),
            'status'     => StatusEnum::TRUE->status(),
            'is_default' => $is_default == StatusEnum::TRUE->status() ? StatusEnum::TRUE->status() : StatusEnum::FALSE->status(),
        ];

        return $data;
    }

   public function statusUpdate($request)
    {
        $status = true;
        $reload = false;
        $column = $request->input("column");
        $message = $column != "is_default" ? translate('Currency status updated successfully') : translate("Default currency changed");
        $data['currencies'] = json_decode(site_settings('currencies'), true);
        $code = $request->input('id');

        if (!isset($data['currencies'][$code])) {
            throw new ApplicationException("Currency not found", Response::HTTP_NOT_FOUND);
        }

        if ($column != 'is_default' && $data['currencies'][$code]['status'] == StatusEnum::TRUE->status() && $data['currencies'][$code]['is_default'] != StatusEnum::TRUE->status()) {
            $data['currencies'][$code]['status'] = StatusEnum::FALSE->status();
        } elseif ($column != 'is_default' && $data['currencies'][$code]['status'] == StatusEnum::FALSE->status()) {
            $data['currencies'][$code]['status'] = StatusEnum::TRUE->status();
        } elseif ($column == 'is_default') {
            $reload = true;
            $old_default_rate = null;
            foreach ($data['currencies'] as $key => &$currency) {
                if ($currency['is_default'] == StatusEnum::TRUE->status()) {
                    $old_default_rate = $currency['rate'];
                }
                $currency['is_default'] = StatusEnum::FALSE->status();
            }
            $data['currencies'][$code]['is_default'] = StatusEnum::TRUE->status();
            $data['currencies'][$code]['status'] = StatusEnum::TRUE->status();

            // Adjust exchange rates if is_default is changed
            if ($old_default_rate !== null) {
                $data['currencies'] = $this->updateExchangeRatesForDefault($data['currencies'], $code);
            }
        } else {
            $status = false;
            $reload = true;
            $message = translate("Can not disable default currency status");
        }

        return [
            $status,
            $reload,
            $message,
            $data
        ];
    }

    protected function updateExchangeRatesForDefault(array $currencies, string $newDefaultCode)
    {
        $newDefaultRate = $currencies[$newDefaultCode]['rate'];
        $updatedCurrencies = [];

        foreach ($currencies as $code => $currency) {
            if ($code === $newDefaultCode) {
                // Set new default currency rate to 1
                $currency['rate'] = "1";
            } else {
                // Calculate new rate: old_rate / new_default_rate
                $currency['rate'] = bcdiv($currency['rate'], $newDefaultRate, 8);
            }
            $updatedCurrencies[$code] = $currency;
        }

        return $updatedCurrencies;
    }
}
