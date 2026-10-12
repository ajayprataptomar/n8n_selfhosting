<?php

namespace App\Http\Controllers;

use App\Enums\StatusEnum;
use App\Http\Requests\LicenseRequest;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CoreController extends Controller
{
    public function domainNotVerified()
    {
        $title = translate('Domain Verification failed');

        return view('domain_not_verified', [
            'title' => $title,
        ]);

    }

    /**
     * checkLicense
     *
     * @param LicenseRequest $request
     * 
     * @return [type]
     */
    public function checkLicense(LicenseRequest $request)
    {
        $current_time = Carbon::now();

        try {
            $params = [
                'domain'            => url('/'),
                'software_id'       => config('installer.software_id'),
                'version'           => config('installer.version'),
                'purchase_key'      => $request->input('purchase_key'),
                'envato_username'   => $request->input('username'),
            ];

            $url = 'https://verifylicense.online/api/licence-verification/check-domain';
            $response = Http::timeout(120)->post($url, $params);
            
            $apiResponse = $response->json();
            



            Setting::updateOrInsert(
                ['key' => 'next_verification'],
                ['value' => $current_time->addDays(3)]
            );


            if ($response->successful() && ($apiResponse = $response->json()) && ($apiResponse['success'] ?? false) && ($apiResponse['code'] ?? null) === 200) {


                Setting::updateOrInsert(
                    ['key' => 'is_domain_verified'],
                    ['value' => StatusEnum::TRUE->status()]
                );

                Setting::updateOrInsert(
                    ['key' => 'domain_verified_at'],
                    ['value' => $current_time]
                );

                if(site_settings("app_version") == "3.2.4") {

                    Setting::updateOrInsert(
                        ['key' => 'app_version'],
                        ['value' => config("installer.version")]
                    );
                }

                update_env('PURCHASE_KEY',$request->input('purchase_key'));
                update_env('ENVATO_USERNAME',$request->input('username'));

                // Clear all caches first
                optimize_clear();

                // Then set the domain verification cache so middleware allows access
                Cache::forget('domain_verification_status');
                Cache::put('domain_verification_status', 'verified', now()->addDays(7));

                $notify[] = ["success", 'Domain is verified'];
                return redirect()->route('home')->withNotify($notify);
            }

            Setting::updateOrInsert(['key' => 'is_domain_verified'], ['value' => StatusEnum::FALSE->status()]);
            $message = Arr::get($apiResponse, "data.error", 'Invalid Domain');
            $notify[] = ["error", $message];
            
            return back()->withNotify($notify);

        } catch (\Exception $ex) {
            
            Setting::updateOrInsert(['key' => 'is_domain_verified'], ['value' => StatusEnum::FALSE->status()]);
            $notify[] = ["error", 'Domain verification failed.'];
            return back()->withNotify($notify);
        }

    }
}
