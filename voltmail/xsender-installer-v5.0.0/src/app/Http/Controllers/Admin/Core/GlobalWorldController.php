<?php

namespace App\Http\Controllers\Admin\Core;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use App\Service\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class GlobalWorldController extends Controller
{
    /**
     * @return View
     */
    public function index(): View
    {
        Session::put("menu_active", true);
        $title = translate('Spam Word');
        $path = base_path('lang/globalworld/offensive.json');
        $offensiveData = [];

        if(file_exists($path)){
            $offensiveData = json_decode(file_get_contents($path), true);
        }

        return view('admin.global_world.index', compact('title', 'offensiveData'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required',
            'value' => 'required',
        ]);

        $path = base_path('lang/globalworld/');
        $fileName = 'offensive.json';

        if(!file_exists($path)){
            mkdir($path, 0777, true);
            $location = $path. $fileName;
            File::put($location ,'{}');
        }

        $offensiveData = json_decode(file_get_contents($path.$fileName), true);

        if(!array_key_exists($request->input('key'), $offensiveData)){
            $offensiveData += [$request->input('key') => $request->input('value')];
            File::put($path.$fileName, json_encode($offensiveData));
            $notify[] = ['success', 'Word Added successfully'];
        } else{
            $notify[] = ['error', 'Word Already Exist'];
        }

        return back()->withNotify($notify);
    }

    public function update(Request $request)
    {
        $request->validate([
            'value' => 'required',
        ]);

        $path = base_path('lang/globalworld/offensive.json');
        $offensiveData = json_decode(file_get_contents($path), true);

        if(array_key_exists($request->input('key'), $offensiveData)){
            $offensiveData[$request->input('key')] = $request->input('value');
            File::put($path, json_encode($offensiveData));

            $notify[] = ['success', 'Word Updated successfully'];
        } else{
            $notify[] = ['error', 'Word Does not  exist'];
        }

    	return back()->withNotify($notify);
    }

    public function delete(Request $request)
    {
        $path = base_path('lang/globalworld/offensive.json');
        $offensiveData = json_decode(file_get_contents($path), true);

        if(in_array($offensiveData[$request->input('id')], $offensiveData)){
            unset($offensiveData[$request->input('id')]);
            File::put($path, json_encode($offensiveData));
        }

        $notify[] = ['success', 'Word Deleted successfully'];
    	return back()->withNotify($notify);
    }


    /**
     * Change the language and update local storage via client-side
     *
     * @param string|null $id
     * @return JsonResponse
     */
    public function languageChange($id = null): JsonResponse
    {
        $language = Language::where('id', $id)->first();
        $locale = $language ? $language->code : 'us';
        
        // Set session locale for server-side consistency
        session(['locale' => $locale]);

        return response()->json([
            'status' => true,
            'lang_code' => $locale,
            'message' => translate('Language set to ') . ($language ? $language->name : 'English')
        ]);
    }

    /**
     * Fetch translations for a language code with fallback
     *
     * @param string $lang_code
     * @return JsonResponse
     */
    public function getTranslations($lang_code): JsonResponse
    {
        // Validate language code
        $language = Language::where('code', $lang_code)->first();
        if (!$language) {
            $lang_code = Language::where('is_default', StatusEnum::TRUE->status())->value('code') ?? 'us';
        }

        // Fetch translations for the requested language
        $translations = Cache::remember('translations-' . $lang_code, now()->addHour(), function () use ($lang_code) {
            return Translation::where('code', $lang_code)->pluck('value', 'key')->toArray();
        });

        // Fetch fallback translations (default language)
        $default_lang = Language::where('is_default', StatusEnum::TRUE->status())->value('code') ?? 'us';
        $fallback_translations = $lang_code !== $default_lang ? Cache::remember('translations-' . $default_lang, now()->addHour(), function () use ($default_lang) {
            return Translation::where('code', $default_lang)->pluck('value', 'key')->toArray();
        }) : [];

        return response()->json([
            'status' => true,
            'lang_code' => $lang_code,
            'translations' => $translations,
            'fallback_translations' => $fallback_translations
        ]);
    }

    /**
     * verifyEmail
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function verifyEmail(Request $request):JsonResponse {
        
        $mailService    = new MailService();
        $email          = $request->input('email');
        $result         = $mailService->verifyEmail($email);
        $status         = Arr::get($result, "valid", false);
        $message        = $mailService->processMailVerificationMessage($result);
        
        return response()->json([
            'status'  => $status,
            'message' => $message
        ]);
    }
}
