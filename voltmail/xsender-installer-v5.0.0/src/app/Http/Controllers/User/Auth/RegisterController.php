<?php

namespace App\Http\Controllers\User\Auth;

use Illuminate\View\View;
use App\Managers\ThemeManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\UserStoreRequest;
use App\Services\System\User\AuthService;

class RegisterController extends Controller
{
    public AuthService $authService;
    protected ThemeManager $themeManager;

    public function __construct(AuthService $authService, ThemeManager $themeManager) {

        $this->authService = $authService;
        $this->themeManager = $themeManager;
    }

    /**
     * Summary of register
     * @param string|null $uid
     * @return \Illuminate\Contracts\View\View
     */
    public function register(string|null $uid = null): View {

        return view($this->themeManager->view('auth.user.register'), compact('uid'));
    }
    
    /**
     * Summary of store
     * @param \App\Http\Requests\UserStoreRequest $request
     * @param string|null $uid
     * @return RedirectResponse
     */
    public function store(UserStoreRequest $request, string|null $uid = null): RedirectResponse {

        return $this->authService->register($request, $uid);
    }
}
