<?php

namespace App\Managers;

use App\Enums\StatusEnum;
use App\Models\Blog;
use App\Models\FrontendSection;
use App\Models\PricingPlan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ThemeManager
{
    protected ?Collection $frontendContents = null;
    protected ?Collection $pricingPlans = null;

    /**
     * Summary of getActiveTheme
     * @return string
     */
    public function getActiveTheme(): string
    {
        return getActiveFrontendTheme();
    }

    /**
     * Summary of getAvailableThemes
     * @return array
     */
    public function getAvailableThemes(): array
    {
        return getAvailableFrontendThemes();
    }

    public function themeExists(string $theme): bool
    {
        $themePath = resource_path("views/frontend/themes/{$theme}");
        $assetPath = public_path("assets/theme/frontend/{$theme}");
        
        $requiredViewFiles  = ['layouts/main.blade.php', 'pages/home.blade.php'];
        $requiredAssetFiles = ['css/main.css'];

        $viewCheck  = collect($requiredViewFiles)->every(fn($file) => File::exists("{$themePath}/{$file}"));
        $assetCheck = collect($requiredAssetFiles)->every(fn($file) => File::exists("{$assetPath}/{$file}"));

        return $viewCheck && $assetCheck;
    }

    /**
     * Summary of view
     * @param string $view
     * @return string
     */
    public function view(string $view): string
    {
        $theme = $this->getActiveTheme();
        return "frontend.themes.{$theme}.{$view}";
    }

    /**
     * Summary of asset
     * @param string $path
     * @return string
     */
    public function asset(string $path): string
    {
        $theme = $this->getActiveTheme();
        return versioned_asset("assets/theme/frontend/{$theme}/{$path}");
    }

    /**
     * Summary of getFrontendContents
     * @return Collection|null
     */
    protected function getFrontendContents(): Collection
    {
        if ($this->frontendContents === null) {

            $this->frontendContents = Cache::remember('frontend_contents', 3600, function () {
                return FrontendSection::latest()->get();
            });
        }
        
        return $this->frontendContents;
    }

    /**
     * Summary of getPricingPlans
     * @return Collection|null
     */
    protected function getPricingPlans(): Collection
    {
        if ($this->pricingPlans == null) {
            
            $this->pricingPlans = Cache::remember('pricing_plans_ordered', 3600, function () {
                $plans = PricingPlan::where('status', StatusEnum::TRUE->status())
                                        ->where('amount', '>=', 1)
                                        ->orderBy('amount', 'ASC')
                                        ->get();

                $recommendedPlan = $plans->firstWhere('recommended_status', StatusEnum::TRUE->status());
                
                if ($recommendedPlan) {
                    $filteredPlans = $plans->filter(fn($plan) => $plan->id !== $recommendedPlan->id)->values();
                    $filteredPlans->splice(1, 0, [$recommendedPlan]);
                    return $filteredPlans;
                }

                return $plans;
            });
        }
        return $this->pricingPlans;
    }

    /**
     * Summary of filterByPattern
     * @param string $pattern
     * @return Collection
     */
    protected function filterByPattern(string $pattern): Collection
    {
        return $this->getFrontendContents()->filter(function ($item) use ($pattern) {
            return preg_match($pattern, $item->section_key);
        });
    }

    /**
     * Summary of getContentByKey
     * @param string $sectionKey
     * @return mixed
     */
    protected function getContentByKey(string $sectionKey): mixed
    {
        return $this->getFrontendContents()->where('section_key', $sectionKey)->first();
    }

    /**
     * Summary of getCollectionByKey
     * @param string $sectionKey
     * @return Collection
     */
    protected function getCollectionByKey(string $sectionKey): Collection
    {
        return $this->getFrontendContents()->where('section_key', $sectionKey);
    }

    /**
     * Define section data resolvers
     */
    protected function getSectionResolvers(): array
    {
        return [
            // Service Menu (Topbar)
            'service_menu' => [
                
                'service_menu_content'          => fn() => $this->filterByPattern('/^service_menu\.[^.]+\.fixed_content$/')->all(),
                'service_menu_multi_content'    => fn() => $this->filterByPattern('/^service_menu\.[^.]+\.multiple_static_content$/')->all(),
                'service_menu_element'          => fn() => $this->filterByPattern('/^service_menu\.[^.]+\.element_content$/')->all(),
            ],

            // Service Breadcrumb
            'service_breadcrumb' => [
                
                'service_menu_common'               => fn() => $this->getContentByKey(FrontendSection::SERVICE_MENU),
                'service_breadcrumb_content'        => fn() => $this->filterByPattern('/^service_breadcrumb\.[^.]+\.fixed_content$/')->all(),
                'service_breadcrumb_multi_content'  => fn() => $this->filterByPattern('/^service_breadcrumb\.[^.]+\.multiple_static_content$/')->all(),
                'service_breadcrumb_element'        => fn() => $this->filterByPattern('/^service_breadcrumb\.[^.]+\.element_content$/')->all(),
            ],

            // Service Overview
            'service_overview' => [
                'service_feature_element'           => fn() => $this->filterByPattern('/^service_feature\.[^.]+\.element_content$/')->all(),
                'service_breadcrumb_content'        => fn() => $this->filterByPattern('/^service_breadcrumb\.[^.]+\.fixed_content$/')->all(),
                'service_overview_content'          => fn() => $this->filterByPattern('/^service_overview\.[^.]+\.fixed_content$/')->all(),
                'service_overview_multi_content'    => fn() => $this->filterByPattern('/^service_overview\.[^.]+\.multiple_static_content$/')->all(),
            ],

            // Service Feature
            'service_feature' => [
                'service_feature_content'       => fn() => $this->filterByPattern('/^service_feature\.[^.]+\.fixed_content$/')->all(),
                'service_feature_multi_content' => fn() => $this->filterByPattern('/^service_feature\.[^.]+\.multiple_static_content$/')->all(),
                'service_feature_element'       => fn() => $this->filterByPattern('/^service_feature\.[^.]+\.element_content$/')->all(),
            ],

            // Service Details
            'service_details' => [
                'service_details_content'       => fn() => $this->filterByPattern('/^service_details\.[^.]+\.fixed_content$/')->all(),
                'service_details_multi_content' => fn() => $this->filterByPattern('/^service_details\.[^.]+\.multiple_static_content$/')->all(),
                'service_details_element'       => fn() => $this->filterByPattern('/^service_details\.[^.]+\.element_content$/')->all(),
            ],

            // Service Highlight
            'service_highlight' => [
                'service_highlight_content'         => fn() => $this->filterByPattern('/^service_highlight\.[^.]+\.fixed_content$/')->all(),
                'service_highlight_multi_content'   => fn() => $this->filterByPattern('/^service_highlight\.[^.]+\.multiple_static_content$/')->all(),
                'service_highlight_element'         => fn() => $this->filterByPattern('/^service_highlight\.[^.]+\.element_content$/')->all(),
            ],

            // Banner
            'banner' => [
                'users'             => fn() => User::orderBy('id', 'DESC')->latest()->get(),
                'social_icons'      => fn() => $this->getCollectionByKey(FrontendSection::SOCIAL_ICON),
                'banner_content'    => fn() => $this->getContentByKey(FrontendSection::BANNER_CONTENT),
                'banner_element'    => fn() => $this->getCollectionByKey(FrontendSection::BANNER_ELEMENT),
            ],

            // Client
            'client' => [
                'client_content'        => fn() => $this->getContentByKey(FrontendSection::CLIENT_CONTENT),
                'client_multi_content'  => fn() => $this->getContentByKey(FrontendSection::CLIENT_MULTI_CONTENT),
            ],

            // Feature
            'feature' => [
                'feature_content'       => fn() => $this->getContentByKey(FrontendSection::FEATURE_CONTENT),
                'feature_multi_content' => fn() => $this->getContentByKey(FrontendSection::FEATURE_MULTI_CONTENT),
                'feature_element'       => fn() => $this->getCollectionByKey(FrontendSection::FEATURE_ELEMENT),
            ],

            // Workflow
            'workflow' => [
                'workflow_content'          => fn() => $this->getContentByKey(FrontendSection::WORKFLOW_CONTENT),
                'workflow_multi_content'    => fn() => $this->getContentByKey(FrontendSection::WORKFLOW_MULTI_CONTENT),
                'workflow_element'          => fn() => $this->getCollectionByKey(FrontendSection::WORKFLOW_ELEMENT),
            ],

            // Feedback
            'feedback' => [
                'feedback_content'          => fn() => $this->getContentByKey(FrontendSection::FEEDBACK_CONTENT),
                'feedback_multi_content'    => fn() => $this->getContentByKey(FrontendSection::FEEDBACK_MULTI_CONTENT),
                'feedback_element'          => fn() => $this->getCollectionByKey(FrontendSection::FEEDBACK_ELEMENT),
            ],

            // Advantage
            'advantage' => [
                'advantage_content'         => fn() => $this->getContentByKey(FrontendSection::ADVANTAGE_CONTENT),
                'advantage_multi_content'   => fn() => $this->getContentByKey(FrontendSection::ADVANTAGE_MULTI_CONTENT),
                'advantage_element'         => fn() => $this->getCollectionByKey(FrontendSection::ADVANTAGE_ELEMENT),
            ],

            // FAQ
            'faq' => [
                'faq_content'       => fn() => $this->getContentByKey(FrontendSection::FAQ_CONTENT),
                'faq_multi_content' => fn() => $this->getContentByKey(FrontendSection::FAQ_MULTI_CONTENT),
                'faq_element'       => fn() => $this->getCollectionByKey(FrontendSection::FAQ_ELEMENT),
            ],

            // Plan
            'plan' => [
                'plan_content'  => fn() => $this->getContentByKey(FrontendSection::PLAN_CONTENT),
                'plans'         => fn() => $this->getPricingPlans(),
            ],

            // Gateway
            'gateway' => [
                'gateway_content'       => fn() => $this->getContentByKey(FrontendSection::GATEWAY_CONTENT),
                'gateway_multi_content' => fn() => $this->getContentByKey(FrontendSection::GATEWAY_MULTI_CONTENT),
                'gateway_element'       => fn() => $this->getCollectionByKey(FrontendSection::GATEWAY_ELEMENT),
            ],

            // Footer
            'footer' => [
                'footer_content'    => fn() => $this->getContentByKey(FrontendSection::FOOTER_CONTENT),
                'social_element'    => fn() => $this->getCollectionByKey(FrontendSection::SOCIAL_ICON),
                'pages'             => fn() => $this->getCollectionByKey(FrontendSection::POLICY_PAGES),
            ],

            // Breadcrumb sections
            'pricing_breadcrumb' => [
                'pricing_content' => fn() => $this->getContentByKey(FrontendSection::PRICING_BREADCRUMB),
            ],
            'about_breadcrumb' => [
                'about_content' => fn() => $this->getContentByKey(FrontendSection::ABOUT_BREADCRUMB),
            ],
            'contact_breadcrumb' => [
                'contact_content' => fn() => $this->getContentByKey(FrontendSection::CONTACT_BREADCRUMB),
            ],
            'blog_breadcrumb' => [
                'blog_content' => fn() => $this->getContentByKey(FrontendSection::BLOG_BREADCRUMB),
            ],
            'policy_breadcrumb' => [
                'page_content' => fn() => $this->getContentByKey(FrontendSection::POLICY_CONTENT),
            ],

            // Other sections
            'unsubscribe_success' => [
                'unsubscribe_content' => fn() => $this->getContentByKey(FrontendSection::UNSUBSCRIPTION_PAGE),
            ],
            'about_overview' => [
                'about_overview' => fn() => $this->getContentByKey(FrontendSection::ABOUT_OVERVIEW),
            ],
            'connect' => [
                'connect_content' => fn() => $this->getContentByKey(FrontendSection::CONNECT_SECTION),
                'connect_element' => fn() => $this->getCollectionByKey(FrontendSection::CONNECT_ELEMENT),
            ],
            'get_in_touch' => [
                'contact_content' => fn() => $this->getContentByKey(FrontendSection::GET_IN_TOUCH),
            ],
            'blog' => [
                'blog_content'  => fn() => $this->getContentByKey(FrontendSection::BLOG),
                'blogs'         => fn() => Blog::where('status', StatusEnum::TRUE->status())->latest()->get(),
            ],
            'service' => [
                'service_menu_common'           => fn() => $this->getContentByKey(FrontendSection::SERVICE_MENU),
                'service_breadcrumb_content'    => fn() => $this->filterByPattern('/^service_breadcrumb\.[^.]+\.fixed_content$/')->all(),
            ],
            'auth_content' => [
                'user_auth_content'         => fn() => $this->getContentByKey(FrontendSection::USER_AUTH_CONTENT),
                'user_auth_multi_content'   => fn() => $this->getContentByKey(FrontendSection::USER_AUTH_MULTI_CONTENT),
                'user_auth_element'         => fn() => $this->getCollectionByKey(FrontendSection::USER_AUTH_ELEMENT),
            ],
        ];
    }

    /**
     * Summary of getSectionData
     * @param string $viewName
     * @return array
     */
    public function getSectionData(string $viewName): array
    {
        
        $resolvers  = $this->getSectionResolvers();
        $sectionKey = $this->extractSectionKey($viewName);
        
        if (!$sectionKey || !isset($resolvers[$sectionKey])) return [];
        

        return collect($resolvers[$sectionKey])
                    ->mapWithKeys(fn($resolver, $key) => [$key => $resolver()])
                    ->all();
    }

    /**
     * Summary of extractSectionKey
     * @param string $viewName
     * @return string|null
     */
    protected function extractSectionKey(string $viewName): ?string
    {
        $patterns = [
            '/sections\.topbar/'                    => 'service_menu',
            '/service\.section\.breadcrumb_banner/' => 'service_breadcrumb',
            '/service\.section\.overview/'          => 'service_overview',
            '/service\.section\.feature/'           => 'service_feature',
            '/service\.section\.details/'           => 'service_details',
            '/service\.section\.highlight/'         => 'service_highlight',
            '/sections\.banner/'                    => 'banner',
            '/sections\.client/'                    => 'client',
            '/sections\.feature/'                   => 'feature',
            '/sections\.workflow/'                  => 'workflow',
            '/sections\.feedback/'                  => 'feedback',
            '/sections\.advantage/'                 => 'advantage',
            '/sections\.faq/'                       => 'faq',
            '/sections\.plan/'                      => 'plan',
            '/sections\.gateway/'                   => 'gateway',
            '/sections\.footer/'                    => 'footer',
            '/pricing\.section\.breadcrumb_banner/' => 'pricing_breadcrumb',
            '/about\.section\.breadcrumb_banner/'   => 'about_breadcrumb',
            '/contact\.section\.breadcrumb_banner/' => 'contact_breadcrumb',
            '/blog\.section\.breadcrumb_banner/'    => 'blog_breadcrumb',
            '/policy\.section\.breadcrumb_banner/'  => 'policy_breadcrumb',
            '/sections\.unsubscribe-success/'       => 'unsubscribe_success',
            '/about\.section\.overview/'            => 'about_overview',
            '/about\.section\.connect/'             => 'connect',
            '/contact\.section\.get_in_touch/'      => 'get_in_touch',
            '/sections\.blog/'                      => 'blog',
            '/sections\.service/'                   => 'service',
            '/auth\.partials\.content/'             => 'auth_content',
        ];

        foreach ($patterns as $pattern => $sectionKey) {
            if (preg_match($pattern, $viewName)) return $sectionKey;
        }

        return null;
    }

    /**
     * Summary of clearCache
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget('frontend_contents');
        Cache::forget('pricing_plans_ordered');
        $this->frontendContents = null;
        $this->pricingPlans     = null;
    }

    /**
     * Summary of getAllSectionData
     * @return array
     */
    public function getAllSectionData(): array
    {
        $resolvers = $this->getSectionResolvers();
        $allData = [];

        foreach ($resolvers as $sectionKey => $sectionResolvers) {
            $allData[$sectionKey] = collect($sectionResolvers)
                                        ->mapWithKeys(fn($resolver, $key) => [$key => $resolver()])
                                        ->all();
        }
        return $allData;
    }
}