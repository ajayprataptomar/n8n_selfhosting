<?php
namespace App\Service\Admin\Core;

use App\Enums\StatusEnum;
use App\Exceptions\ApplicationException;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LanguageService
{
    ## -------------------------- ##
    ## Language Related Functions ##
    ## -------------------------- ##

    /**
     * getLanguages
     *
     * @return View
     */
    public function getLanguages(): View  {

        $title     = translate("Manage language");
        $languages = Language::search(['name'])
                                ->date()
                                ->orderBy("is_default", "DESC")
                                ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                                ->appends(request()->all());
        $countries = json_decode(file_get_contents(resource_path(config('constants.options.country_code')) . 'countries.json'),true);
        return view('admin.language.index', compact('title', 'languages','countries'));
    }

    /**
     * store
     *
     * @param array $data
     * 
     * @return RedirectResponse
     */
    public function store(array $data) :RedirectResponse {
        
        $country = explode("//", Arr::get($data, "name"));
        $code    = strtolower($country[1]);

        if(Language::where('name', $country[0])->exists())
            throw new ApplicationException("The Language is already added for that country", Response::HTTP_NOT_FOUND);

        $flag = false;
        DB::transaction(function () use($data, $country, $code, &$flag) {
            
            $language   = Language::create([
                                'ltr'        => Arr::get($data, "ltr") == StatusEnum::TRUE->status() ? StatusEnum::FALSE->status() : StatusEnum::TRUE->status(),
                                'name'       => $country[0],
                                'code'       => $code,
                                'is_default' => StatusEnum::FALSE->status(),
                            ]);
    
            $translations = Translation::where('code', 'us')->get();
            $translationsToCreate = [];
    
            foreach ($translations as $k) {
                $translationsToCreate[] = [
                    "uid"   => Str::random(40),
                    'code'  => $language->code,
                    'key'   => $k->key,
                    'value' => $k->value
                ];
            }
            Translation::insert($translationsToCreate);
            $flag = true;
        });

        $status  = $flag ? "success" : 'error';
        $message = $flag ? translate("Language has been added") : translate("Something went wrong");
    
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

    /**
     * update
     *
     * @param array $data
     * 
     * @return RedirectResponse
     */
    public function update(array $data) :RedirectResponse {

        $language = Language::where("id", Arr::get($data, "id"))
                                ->first();
        if(!$language) throw new ApplicationException("Language not found", Response::HTTP_NOT_FOUND);
        
        $language->update([
            'name' => Arr::get($data, "name", $language->name),
            'ltr'  => Arr::get($data, "ltr", $language->ltr) == StatusEnum::TRUE->status() ? StatusEnum::FALSE->status() : StatusEnum::TRUE->status()
        ]);
        optimize_clear();
        $notify[] = ['success', translate("Language has been updated")];
        return back()->withNotify($notify);
    }

    /**
     * destoryLanguage
     *
     * @param int|string|null $id
     * 
     * @return RedirectResponse
     */
    public function destoryLanguage(int|string|null $id = null) :RedirectResponse {

        $language = Language::where('id',$id)->first();
        if(!$language) throw new ApplicationException("Invalid Language", Response::HTTP_NOT_FOUND);
        
        if($language->code == 'us' || $language->is_default == StatusEnum::TRUE->status()) throw new ApplicationException("Default & English Language Can Not Be Deleted", Response::HTTP_FORBIDDEN);
        
        Translation::where("code",$language->code)->delete();
        $language->delete();
        optimize_clear();

        $notify[] = ["success", translate("Language has been deleted")];
        return back()->withNotify($notify);
    }


    ## ----------------------------- ##
    ## Translation Related Functions ##
    ## ----------------------------- ##

    /**
     * getTranslations
     *
     * @param string|null|null $code
     * 
     * @return View
     */
    public function getTranslations(string|null $code = null): View {

        $language     = Language::where('code',$code)->first();
        $title        = translate("Update: ").$language->name.translate(" language keywords");
        $translations = $this->translationVal($code);
        return view('admin.language.edit', compact('title', 'translations', 'language', 'code'));
    }

    /**
     * translationVal
     *
     * @param string|null $code
     * 
     * @return LengthAwarePaginator
     */
    public function translationVal(string|null $code = null): LengthAwarePaginator {

        return Translation::where('code',$code) 
                            ->search(['value'])
                            ->orderBy('key', 'asc')
                            ->paginate(paginateNumber(site_settings("paginate_number")))
                            ->appends(request()->all());
    }

    /**
     * translateLang
     *
     * @param array $data
     * 
     * @return RedirectResponse
     */
    public function translateLang(array $data): string {
       
        $translationKey = Translation::where("uid", Arr::get($data, "uid"))->first();
        
        if(!$translationKey) throw new ApplicationException("Invalid translation key", Response::HTTP_NOT_FOUND);
        
        $translationKey->value = Arr::get($data, "value");
        $translationKey->update();
        optimize_clear();

        return json_encode([
            'reload'  => false,
            'status'  => true,
            'message' => translate("Language key updated successfully")
        ]);
    }

    /**
     * destoryKey
     *
     * @param int |
     * 
     * @return RedirectResponse
     */
    public function destoryKey(int | string $uid):RedirectResponse {

        $translationData = Translation::where('uid',$uid)->first();
        if(!$translationData) throw new ApplicationException("Invalid translation key", Response::HTTP_NOT_FOUND);

        $translationData->delete();
        optimize_clear();

        $notify[] = ["success", translate("Language key has been deleted")];
        return back()->withNotify($notify);
    }
}
