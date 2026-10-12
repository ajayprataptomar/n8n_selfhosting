<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Service\Admin\Core\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Orhanerday\OpenAi\OpenAi as OrhanerdayOpenAI;
use OpenAi;

class AiContentController extends Controller
{
    /**
     * Summary of generateText
     * @param \Illuminate\Http\Request $request
     * @return array{message: string, status: bool}
     */
    public function generateText(Request $request) : array{

        $aiModels  = json_decode(site_settings('ai_models'), true);
        $openAiTextStatus = Arr::get($aiModels, "open_ai_text.status", StatusEnum::FALSE->status());
        $apiKey  = Arr::get($aiModels, "open_ai_text.key", "###");
        $model   = Arr::get($aiModels, "open_ai_text.model", "gpt-4o-mini");
        $baseUrl = Arr::get($aiModels, "open_ai_text.base_url", "");

        if(!$request->input('custom_prompt')){
            return [
                "status"  => false,
                "message" => 'Prompt Field is Required'
            ];
        }

        try {

            if($openAiTextStatus == StatusEnum::FALSE->status()){
                return [
                    "status"  => false,
                    "message" => translate("AI Module is Currently off Now"),
                ];
            }

            $prompt = '';

            $option = Arr::get(get_ai_option(),$request->input('ai_option'),null);
            $tone   = Arr::get(get_ai_tone(),$request->input('ai_tone'),null);


            $prompt .= strip_tags($request->input('custom_prompt'))."\n".  Arr::get(@$tone?? [] ,'prompt') . Arr::get(@$option ?? [] ,'prompt');

            if($request->input("language")){
                $prompt = strip_tags($request->input('custom_prompt'))."\n".'Write the Above message in ' . $request->input("language") . ' language and Do not write translations.';
            }

            // Support custom base URLs (DeepSeek, Groq, Together AI, etc.)
            if (!empty($baseUrl)) {
                $client = OpenAI::factory()
                    ->withApiKey($apiKey)
                    ->withBaseUri($baseUrl)
                    ->make();
            } else {
                $client = OpenAI::client($apiKey);
            }

            $result = $client->chat()->create([
                'model' => $model ?: 'gpt-4o-mini',
                'messages' => [
                    [
                        "role"     => "user",
                        "content"  =>  $prompt
                    ]
                ],
            ]);
    
            if(isset($result['choices'][0]['message']['content'])){
                $realContent                   = $result['choices'][0]['message']['content'];
    
                return [
                    "status"  => true,
                    "message" => strip_tags(str_replace(["\r\n", "\r", "\n"] ,"<br>",$realContent))
                ];
            }
    
            return [
                "status"  => false,
                "message" => 'No Result Found!!'
            ];
    
        } catch (\Exception $ex) {
            return [
                "status"  => false,
                "message" => strip_tags($ex->getMessage())
            ];
        }
    }
    

    public function setImageRules(Request $request): array
    {
        $rules = [
            'image_prompt' => ['required'],
            'ai_model' => ['required', Rule::in(array_keys(config('setting.ai_image_generation_model', [])))],
            'ai_provider' => ['required', Rule::in(array_keys(config('setting.ai_image_secret', [])))],
            'ai_resolution' => [
                'required',
                Rule::in(array_keys(Arr::get(config('setting'), 'ai_image_resolution', ['256x256', '512x512', '1024x1024'])))
            ],
            'ai_quality' => [
                'nullable',
                Rule::in(array_keys(Arr::get(config('setting'), 'ai_image_quality', ['standard', 'hd'])))
            ],
            'number_of_images' => ['required', 'numeric', 'gt:0', 'max:10']
        ];

        $messages = [
            'image_prompt.required' => translate('Prompt field is required'),
            'ai_model.required' => translate('AI model is required'),
            'ai_model.in' => translate('Invalid AI model selected'),
            'ai_provider.required' => translate('Provider is required'),
            'ai_provider.in' => translate('Invalid provider selected'),
            'ai_resolution.required' => translate('Resolution is required'),
            'ai_resolution.in' => translate('Invalid resolution selected'),
            'ai_quality.in' => translate('Invalid image quality selected'),
            'number_of_images.required' => translate('Number of images field is required'),
            'number_of_images.max' => translate('Maximum number of images cannot exceed 10')
        ];

        return [
            'rules' => $rules,
            'messages' => $messages
        ];
    }

    public function generateImage(Request $request): string
    {
        try {
            $templateRules = $this->setImageRules($request);
            $request->validate(Arr::get($templateRules, 'rules', []), Arr::get($templateRules, 'messages', []));

            $response = $this->generateCustomPromptImageContent($request);

            return json_encode($response);
        } catch (\Exception $ex) {
            return json_encode([
                'status' => false,
                'message' => translate('Error generating image: ') . $ex->getMessage()
            ]);
        }
    }

    public function generateCustomPromptImageContent(Request $request): array
    {
        $customPrompt = $request->input('image_prompt') ?? '';
        $badWords = site_settings('ai_bad_words');
        $processBadWords = $badWords ? explode(',', $badWords) : [];
        if (!empty($processBadWords)) {
            $customPrompt = str_replace($processBadWords, '', $customPrompt);
        }

        $imageQuality = $request->input('ai_quality') ?? site_settings('default_image_quality', 'standard');
        $imageResolution = $request->input('ai_resolution') ?? site_settings('default_image_resolution', '1024x1024');
        $maxResult = (int) ($request->input('number_of_images') ?? site_settings('default_max_image_result', 1));

        $aiParams = [
            'model' => $request->input('ai_model') ?? 'dall-e-3',
            'prompt' => $customPrompt,
            'n' => min($maxResult, 10),
            'size' => $imageResolution,
            'quality' => $imageQuality
        ];

        $customPrompt .= ' Generate a clear and relevant image based on the provided description. Ensure the content is safe, appropriate, and visually coherent. Avoid any copyrighted elements or inappropriate content.';
        $aiParams['prompt'] = $customPrompt;

        return $this->generateImageContent($aiParams);
    }

    protected function generateImageContent(array $aiParams): array
    {
        $status = false;
        $message = translate('Invalid Request');
        $fileService = new FileService();

        $aiModels  = json_decode(site_settings('ai_models'), true);
        $status = Arr::get($aiModels, "open_ai_image.status", "###");

        if ($status == StatusEnum::FALSE->status()) {
            return [
                'status' => $status,
                'message' => translate('Image generation is not available at the moment'),
                'image_content' => null
            ];
        }
        $modelConfig = [
            'dall-e-2' => [
                'provider' => 'openai',
                'api_key_func' => 'openai_Image_key',
                'supported_sizes' => ['256x256', '512x512', '1024x1024']
            ],
            'dall-e-3' => [
                'provider' => 'openai',
                'api_key_func' => 'openai_Image_key',
                'supported_sizes' => ['1024x1024', '1792x1024', '1024x1792']
            ]
        ];

        if (!isset($aiParams['model']) || !isset($modelConfig[$aiParams['model']])) {
            return [
                'status' => $status,
                'message' => translate('Invalid or unsupported model. Supported models: ') . implode(', ', array_keys($modelConfig)),
                'image_content' => null
            ];
        }

        $config = $modelConfig[$aiParams['model']];
        $provider = $config['provider'];

        if (empty($aiParams['prompt'])) {
            return [
                'status' => $status,
                'message' => translate('Prompt is required'),
                'image_content' => null
            ];
        }

        $size = $aiParams['size'] ?? '1024x1024';
        if (!in_array($size, $config['supported_sizes'])) {
            return [
                'status' => $status,
                'message' => translate("Invalid size for {$aiParams['model']}. Supported sizes: ") . implode(', ', $config['supported_sizes']),
                'image_content' => null
            ];
        }

        try {
            if ($provider === 'openai') {
                $open_ai = new OrhanerdayOpenAI(call_user_func($config['api_key_func']));
            } else {
                throw new \Exception("Unsupported provider: {$provider}");
            }

            $params = [
                'prompt' => $aiParams['prompt'],
                'model' => $aiParams['model'],
                'n' => $aiParams['n'] ?? 1,
                'size' => $size,
                'response_format' => 'url'
            ];

            $image_results = json_decode($open_ai->image($params), true);

            if (isset($image_results['error'])) {
                $message = Arr::get($image_results['error'], 'message', translate('Invalid Request'));
            } else {
                if (isset($image_results['data']) && is_array($image_results['data'])) {
                    $image_urls = [];
                    foreach ($image_results['data'] as $item) {
                        $image_url = $item['url'];
                        // Fetch and upload the image
                        $response = \Illuminate\Support\Facades\Http::get($image_url);
                        if ($response->successful()) {
                            $tmpFilePath = tempnam(sys_get_temp_dir(), 'ai_img_');
                            file_put_contents($tmpFilePath, $response->body());

                            $file = new \Illuminate\Http\UploadedFile(
                                $tmpFilePath,
                                'generated_image.png',
                                'image/png',
                                null,
                                true
                            );
                            $file_name = $fileService->uploadFile(file: $file, key: 'ai_images', file_path: null, file_size: null, delete_file: false);
                            $path = config("setting.file_path.ai_images.path");
                            $image_urls[] = asset($path. '/'. $file_name);

                            @unlink($tmpFilePath);
                        }
                    }
                    $status = true;
                    $message = translate('Image generated');
                }
            }
        } catch (\Exception $e) {
            $message = translate('Error generating image: ') . $e->getMessage();
        }

        return [
            'status' => $status,
            'message' => $message,
            'image_content' => $image_urls ?? null
        ];
    }
}
