<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentResourceRequest;
use App\Services\Content\ContentEngineInterface;
use App\Services\Content\ContentResourceService;
use App\Services\Content\DeterministicContentEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class ContentResourceController extends Controller
{
    protected ContentResourceService $service;
    protected ContentEngineInterface $engine;

    public function __construct(
        ContentResourceService $service,
        DeterministicContentEngine $engine
    ) {
        $this->service = $service;
        $this->engine  = $engine;
    }

    /**
     * Display a listing of user content resources
     */
    public function index(): View
    {
        Session::put('menu_active', true);
        $user = auth()->user();
        $resources = $this->service->listForUser($user->id, 15);

        return view('user.content_resource.index', [
            'title'     => translate('Business Content Resources'),
            'resources' => $resources,
        ]);
    }

    /**
     * Show form to create a new content resource
     */
    public function create(): View
    {
        Session::put('menu_active', true);

        return view('user.content_resource.create', [
            'title' => translate('Add Business Content Resource'),
        ]);
    }

    /**
     * Store a newly created content resource in storage
     */
    public function store(ContentResourceRequest $request)
    {
        $user = auth()->user();

        try {
            $resource = $this->service->createForUser($user->id, $request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => true,
                    'message' => translate('Content resource created successfully'),
                    'data'    => $resource,
                ], 201);
            }

            $notify[] = ['success', translate('Content resource created successfully')];
            return redirect()->route('user.content-resource.index')->withNotify($notify);

        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            $notify[] = ['error', $e->getMessage()];
            return back()->withInput()->withNotify($notify);
        }
    }

    /**
     * Display the specified content resource
     */
    public function show(string|int $id): View|JsonResponse
    {
        Session::put('menu_active', true);
        $user = auth()->user();
        $resource = $this->service->getForUser($user->id, $id);
        $histories = $resource->generationHistories()->latest('id')->take(10)->get();

        if (request()->wantsJson()) {
            return response()->json([
                'status' => true,
                'data'   => [
                    'resource'  => $resource,
                    'histories' => $histories,
                ],
            ]);
        }

        return view('user.content_resource.show', [
            'title'     => translate('Resource Details') . ': ' . $resource->name,
            'resource'  => $resource,
            'histories' => $histories,
        ]);
    }

    /**
     * Show form to edit content resource
     */
    public function edit(string|int $id): View
    {
        Session::put('menu_active', true);
        $user = auth()->user();
        $resource = $this->service->getForUser($user->id, $id);

        return view('user.content_resource.edit', [
            'title'    => translate('Edit Content Resource') . ': ' . $resource->name,
            'resource' => $resource,
        ]);
    }

    /**
     * Update the specified content resource
     */
    public function update(ContentResourceRequest $request, string|int $id)
    {
        $user = auth()->user();

        try {
            $resource = $this->service->updateForUser($user->id, $id, $request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => true,
                    'message' => translate('Content resource updated successfully'),
                    'data'    => $resource,
                ]);
            }

            $notify[] = ['success', translate('Content resource updated successfully')];
            return redirect()->route('user.content-resource.index')->withNotify($notify);

        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            $notify[] = ['error', $e->getMessage()];
            return back()->withInput()->withNotify($notify);
        }
    }

    /**
     * Delete the specified content resource
     */
    public function destroy(string|int $id)
    {
        $user = auth()->user();

        try {
            $this->service->deleteForUser($user->id, $id);

            if (request()->wantsJson()) {
                return response()->json([
                    'status'  => true,
                    'message' => translate('Content resource deleted successfully'),
                ]);
            }

            $notify[] = ['success', translate('Content resource deleted successfully')];
            return redirect()->route('user.content-resource.index')->withNotify($notify);

        } catch (\Throwable $e) {
            if (request()->wantsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => $e->getMessage(),
                ], 403);
            }

            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Test / Preview deterministic content generation for a resource
     */
    public function previewGeneration(Request $request, string|int $id): JsonResponse
    {
        $user = auth()->user();
        $resource = $this->service->getForUser($user->id, $id);

        $options = [
            'intent'      => $request->input('intent'),
            'gateway_id'  => $request->input('gateway_id'),
        ];

        $generated = $this->engine->generateContent($resource, $options);

        // If requested to record sample usage
        if ($request->boolean('record_usage')) {
            $history = $this->engine->recordUsage(
                $user->id,
                $resource->id,
                $options['gateway_id'] ? (int) $options['gateway_id'] : null,
                $generated,
                'manual_preview_' . str_unique(8)
            );
            $generated['history_id'] = $history->id;
        }

        return response()->json([
            'status' => true,
            'data'   => $generated,
        ]);
    }
}
