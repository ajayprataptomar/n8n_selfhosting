<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Core\AddonManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AddonController extends Controller
{
    protected AddonManager $addonManager;

    public function __construct()
    {
        $this->addonManager = new AddonManager();
    }

    /**
     * List all installed add-ons
     */
    public function index()
    {
        Session::put('menu_active', true);

        $addons = $this->addonManager->getInstalled();

        return view('admin.addon.index', [
            'title'   => translate('Add-ons'),
            'addons'  => $addons,
        ]);
    }

    /**
     * Upload and install add-on ZIP
     */
    public function install(Request $request): JsonResponse
    {
        $request->validate([
            'addon_zip' => 'required|file|mimes:zip|max:102400', // Max 100MB
        ]);

        try {
            $result = $this->addonManager->install($request->file('addon_zip'));

            return response()->json([
                'status'  => true,
                'message' => $result['message'],
                'data'    => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Marketplace listing — fetches the configured remote catalog.
     */
    public function marketplace(Request $request)
    {
        \Illuminate\Support\Facades\Session::put('menu_active', true);
        $marketplace = new \App\Services\Core\AddonMarketplaceService();
        $listing  = $marketplace->listAvailable($request->boolean('refresh'));
        $title    = translate('Add-On Marketplace');
        // Local installs use 'name' as the unique key. Treat it as the slug for comparison
        // against marketplace listings (marketplace slug convention === installed name).
        $installedSlugs = collect($this->addonManager->getInstalled())
            ->map(fn ($a) => $a['slug'] ?? $a['name'] ?? null)
            ->filter()
            ->values()
            ->all();

        return view('admin.addon.marketplace', compact('title', 'listing', 'installedSlugs'));
    }

    /**
     * One-click install from a marketplace entry — downloads the remote ZIP
     * and hands off to the existing install pipeline.
     */
    public function marketplaceInstall(Request $request): JsonResponse
    {
        $request->validate([
            'download_url' => 'required|url',
            'slug'         => 'nullable|string|max:100',
        ]);

        $tempPath = null;
        try {
            $marketplace = new \App\Services\Core\AddonMarketplaceService();
            $tempPath    = $marketplace->downloadAddonZip($request->input('download_url'));

            // Laravel's UploadedFile in test-mode skips is_uploaded_file() so we can use it for a downloaded file.
            $uploaded = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $request->input('slug', 'marketplace_addon') . '.zip',
                'application/zip',
                null,
                true
            );

            $result = $this->addonManager->install($uploaded);

            return response()->json([
                'status'  => true,
                'message' => $result['message'] ?? translate('Add-on installed'),
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Activate an add-on
     */
    public function activate(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string']);

        try {
            $addon = $this->addonManager->getAddon($request->name);

            // Check if purchase verification is needed first
            if (!empty($addon['verification_required']) && empty($addon['purchase_code'])) {
                return response()->json([
                    'status'              => false,
                    'requires_verification' => true,
                    'message'             => translate('Please verify your purchase code to activate this add-on.'),
                ]);
            }

            $result = $this->addonManager->activate($request->name);

            return response()->json([
                'status'  => true,
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Deactivate an add-on
     */
    public function deactivate(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string']);

        try {
            $result = $this->addonManager->deactivate($request->name);

            return response()->json([
                'status'  => true,
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Uninstall an add-on
     */
    public function uninstall(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string']);

        try {
            $result = $this->addonManager->uninstall($request->name);

            return response()->json([
                'status'  => true,
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verify purchase code for an add-on
     */
    public function verifyPurchase(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string',
            'purchase_code' => 'required|string|min:10',
        ]);

        try {
            $result = $this->addonManager->verifyPurchase(
                $request->name,
                $request->purchase_code
            );

            return response()->json([
                'status'  => true,
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
