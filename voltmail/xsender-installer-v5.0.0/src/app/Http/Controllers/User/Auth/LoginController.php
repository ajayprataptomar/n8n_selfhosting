<?php

namespace App\Http\Controllers\User\Auth;

use App\Enums\SettingKey;
use Illuminate\View\View;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Auth\LoginRequest;
use App\Managers\ThemeManager;
use App\Services\System\User\AuthService;

class LoginController extends Controller
{
    public AuthService $authService;
    protected ThemeManager $themeManager;

    public function __construct(AuthService $authService, ThemeManager $themeManager) {
        $this->authService = $authService;
        $this->themeManager = $themeManager;
    }

    /**
     * create
     *
     * @return View
     */
    public function create(): View {

        return view($this->themeManager->view('auth.user.login'));
        
    }

    /**
     * store
     *
     * @param LoginRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(LoginRequest $request): RedirectResponse {

        $request->authenticate();
        $request->session()->regenerate();
        return redirect()->intended(SettingKey::ROUTE_USER_DASHBOARD->value);
    }
}
