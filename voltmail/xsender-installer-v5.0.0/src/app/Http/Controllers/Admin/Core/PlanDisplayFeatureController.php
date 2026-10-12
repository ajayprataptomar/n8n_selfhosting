<?php

namespace App\Http\Controllers\Admin\Core;

use App\Http\Controllers\Controller;
use App\Models\PlanDisplayFeature;
use App\Managers\ThemeManager;
use App\Traits\ModelAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PlanDisplayFeatureController extends Controller
{
    use ModelAction;

    /**
     * Display list of plan display features
     */
    public function index(): View
    {
        $title = translate('Plan Display Features');
        $features = PlanDisplayFeature::ordered()->get();

        return view('admin.setting.plan_features', compact('title', 'features'));
    }

    /**
     * Store a new feature
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $maxOrder = PlanDisplayFeature::max('sort_order') ?? 0;

        PlanDisplayFeature::create([
            'name' => $request->input('name'),
            'icon' => $request->input('icon', 'ri-checkbox-circle-line'),
            'description' => $request->input('description'),
            'sort_order' => $maxOrder + 1,
            'status' => 'active',
        ]);

        // Clear cache so frontend reflects changes immediately
        app(ThemeManager::class)->clearCache();

        $notify[] = ['success', translate('Feature added successfully')];
        return back()->withNotify($notify);
    }

    /**
     * Update a feature
     */
    public function update(Request $request, string $uid): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $feature = PlanDisplayFeature::where('uid', $uid)->firstOrFail();

        $feature->update([
            'name' => $request->input('name'),
            'icon' => $request->input('icon'),
            'description' => $request->input('description'),
        ]);

        // Clear cache so frontend reflects changes immediately
        app(ThemeManager::class)->clearCache();

        $notify[] = ['success', translate('Feature updated successfully')];
        return back()->withNotify($notify);
    }

    /**
     * Delete a feature
     */
    public function destroy(string $uid): RedirectResponse
    {
        $feature = PlanDisplayFeature::where('uid', $uid)->firstOrFail();
        $feature->delete();

        // Clear cache so frontend reflects changes immediately
        app(ThemeManager::class)->clearCache();

        $notify[] = ['success', translate('Feature deleted successfully')];
        return back()->withNotify($notify);
    }

    /**
     * Update feature status
     */
    public function updateStatus(Request $request): JsonResponse
    {
        try {
            $feature = PlanDisplayFeature::findOrFail($request->input('id'));
            $feature->status = $feature->status === 'active' ? 'inactive' : 'active';
            $feature->save();

            // Clear cache so frontend reflects changes immediately
            app(ThemeManager::class)->clearCache();

            return response()->json([
                'status' => true,
                'message' => translate('Status updated successfully'),
                'reload' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => translate('Failed to update status'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update sort order via AJAX
     */
    public function updateOrder(Request $request): JsonResponse
    {
        try {
            $order = $request->input('order', []);

            foreach ($order as $index => $id) {
                PlanDisplayFeature::where('id', $id)->update(['sort_order' => $index + 1]);
            }

            // Clear cache so frontend reflects changes immediately
            app(ThemeManager::class)->clearCache();

            return response()->json([
                'status' => true,
                'message' => translate('Order updated successfully'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => translate('Failed to update order'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all active features (for AJAX)
     */
    public function getFeatures(): JsonResponse
    {
        $features = PlanDisplayFeature::active()->ordered()->get();

        return response()->json([
            'status' => true,
            'features' => $features,
        ]);
    }
}
