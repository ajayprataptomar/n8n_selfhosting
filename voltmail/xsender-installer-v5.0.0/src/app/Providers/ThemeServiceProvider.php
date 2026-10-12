<?php

namespace App\Providers;

use App\Managers\ThemeManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeManager::class);
    }

    /**
     * Summary of boot
     * @param \App\Managers\ThemeManager $themeManager
     * @return void
     */
    public function boot(ThemeManager $themeManager): void
    {
        $activeTheme = $themeManager->getActiveTheme();
        $this->loadViewsFrom(resource_path("views/frontend/themes/{$activeTheme}"), 'frontend');
        View::share('themeManager', $themeManager);
        View::composer("frontend.themes.{$activeTheme}.*", function ($view) use ($themeManager) {
            
            $viewName   = str_replace("frontend.themes.{$themeManager->getActiveTheme()}.", '', $view->getName());
            $data       = $themeManager->getSectionData($viewName);
            
            if (!empty($data)) $view->with($data);
        });
    }
}