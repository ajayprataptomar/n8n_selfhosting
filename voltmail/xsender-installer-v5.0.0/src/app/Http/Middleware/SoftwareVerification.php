<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\InstallerManager;

class SoftwareVerification
{
    use InstallerManager;

    /**
     * Handle an incoming request.
     * Checks if the application is properly installed before allowing access.
     * Redirects to the installer if not installed or if a critical error occurs.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Step 1: Check installation status (isolated try-catch)
        // Only installation-related failures should redirect to installer
        try {
            $this->ensureEnvFileExists();

            if (!$this->is_installed()) {
                return redirect()->route('install.init');
            }
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('install.init');
        } catch (\PDOException $e) {
            return redirect()->route('install.init');
        } catch (\RuntimeException $e) {
            $this->ensureEnvFileExists();
            return redirect()->route('install.init');
        }

        // Step 2: Continue to downstream middleware/controllers
        // DO NOT catch exceptions here — they should be handled by Laravel's
        // exception handler, not redirect to the installer. A DB query failure
        // in a controller does NOT mean the app is uninstalled.
        return $next($request);
    }

    /**
     * Ensure .env file exists. If not, create from .env.example.
     * This handles the fresh-install scenario where only .env.example ships in the zip.
     */
    private function ensureEnvFileExists(): void
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        if (!file_exists($envPath) && file_exists($examplePath)) {
            copy($examplePath, $envPath);
        }
    }
}
