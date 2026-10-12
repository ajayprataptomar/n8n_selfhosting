<?php

namespace App\Http\Controllers\User\Automation;

use Exception;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;
use App\Traits\ModelAction;
use App\Models\Automation\AutomationWorkflow;
use App\Models\Automation\WorkflowNode;
use App\Models\Automation\WorkflowExecution;
use App\Models\ContactGroup;
use App\Models\Template;
use App\Models\Gateway;
use App\Enums\System\ChannelTypeEnum;
use App\Services\Automation\WorkflowExecutionService;
use App\Services\Automation\TriggerHandlerService;
use App\Services\Automation\ConditionEvaluatorService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    use ModelAction;

    /**
     * Get current user
     */
    protected function user()
    {
        return Auth::user();
    }

    /**
     * Check if user's plan allows automation workflows
     */
    protected function checkPlanAccess(): bool
    {
        $user = $this->user();

        // Check if user has an active subscription
        $subscription = $user->runningSubscription();
        if (!$subscription) {
            return false;
        }

        // Get plan access
        $planAccess = planAccess($user);

        // Check if automation is allowed in the plan
        if (isset($planAccess['automation']) && isset($planAccess['automation']['is_allowed'])) {
            return (bool) $planAccess['automation']['is_allowed'];
        }

        // Fallback - allow if user has subscription (for backwards compatibility)
        return true;
    }

    /**
     * Get automation limits from plan
     */
    protected function getPlanLimits(): array
    {
        $user = $this->user();
        $planAccess = planAccess($user);

        return [
            'max_workflows' => $planAccess['automation']['max_workflows'] ?? 10,
            'max_nodes_per_workflow' => $planAccess['automation']['max_nodes'] ?? 20,
        ];
    }

    /**
     * Display workflow list
     */
    public function index(): View
    {
        Session::put("menu_active", true);

        $user = $this->user();

        $title = translate("Automation Workflows");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('user.dashboard')],
            ['name' => translate('Automation Workflows')],
        ];

        $canUseAutomation = $this->checkPlanAccess();
        $limits = $this->getPlanLimits();

        // Initialize with defaults
        $workflows = collect();
        $stats = [
            'total_workflows'      => 0,
            'active_workflows'     => 0,
            'total_executions'     => 0,
            'completed_executions' => 0,
        ];

        // Only query database if user has access
        if ($canUseAutomation) {
            try {
                $workflows = AutomationWorkflow::where('user_id', $user->id)
                    ->withCount(['nodes', 'executions'])
                    ->orderByDesc('updated_at')
                    ->paginate(paginateNumber());

                // Get statistics
                $stats = [
                    'total_workflows'      => AutomationWorkflow::where('user_id', $user->id)->count(),
                    'active_workflows'     => AutomationWorkflow::where('user_id', $user->id)->active()->count(),
                    'total_executions'     => WorkflowExecution::whereHas('workflow', fn($q) => $q->where('user_id', $user->id))->count(),
                    'completed_executions' => WorkflowExecution::whereHas('workflow', fn($q) => $q->where('user_id', $user->id))->completed()->count(),
                ];
            } catch (Exception $e) {
                \Illuminate\Support\Facades\Log::error('Automation Index Error: ' . $e->getMessage());
            }
        }

        return view('user.automation.index', compact(
            'title', 'breadcrumbs', 'workflows', 'stats', 'canUseAutomation', 'limits'
        ));
    }

    /**
     * Show create workflow form
     */
    public function create(): View|RedirectResponse
    {
        if (!$this->checkPlanAccess()) {
            $notify[] = ['error', translate('Your plan does not include automation features')];
            return redirect()->route('user.automation.index')->withNotify($notify);
        }

        $limits = $this->getPlanLimits();
        $currentWorkflows = AutomationWorkflow::where('user_id', $this->user()->id)->count();

        if ($currentWorkflows >= $limits['max_workflows']) {
            $notify[] = ['error', translate('You have reached the maximum number of workflows for your plan')];
            return redirect()->route('user.automation.index')->withNotify($notify);
        }

        Session::put("menu_active", true);

        $user = $this->user();

        $title = translate("Create Workflow");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('user.dashboard')],
            ['name' => translate('Automation'), 'url' => route('user.automation.index')],
            ['name' => translate('Create')],
        ];

        $triggerTypes = AutomationWorkflow::TRIGGER_TYPES;
        $actionTypes = WorkflowNode::ACTION_TYPES;
        $conditionTypes = WorkflowNode::CONDITION_TYPES;
        $waitTypes = WorkflowNode::WAIT_TYPES;
        $operators = ConditionEvaluatorService::getFieldOperators();

        // Get user's resources
        $groups = ContactGroup::where('user_id', $user->id)->get();
        $templates = Template::where('user_id', $user->id)->get();

        // Get user's gateways
        $smsGateways = Gateway::where('user_id', $user->id)
            ->where('channel', ChannelTypeEnum::SMS->value)
            ->active()
            ->get();
        $emailGateways = Gateway::where('user_id', $user->id)
            ->where('channel', ChannelTypeEnum::EMAIL->value)
            ->active()
            ->get();
        $whatsappDevices = Gateway::where('user_id', $user->id)
            ->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->active()
            ->get();

        $aiModels = json_decode(site_settings('ai_models', '{}'), true);
        $aiConfigured = !empty($aiModels['open_ai_text']['status'])
            && $aiModels['open_ai_text']['status'] === '1'
            && !empty($aiModels['open_ai_text']['key'])
            && $aiModels['open_ai_text']['key'] !== '###';

        $schemaService = new \App\Services\Automation\NodeFormSchema();
        $nodeSchemas   = $schemaService->all();
        $schemaOptions = $schemaService->resolveOptionLists($this->user()->id);

        return view('user.automation.builder', compact(
            'title', 'breadcrumbs', 'triggerTypes', 'actionTypes',
            'conditionTypes', 'waitTypes', 'operators', 'limits',
            'groups', 'templates', 'smsGateways', 'emailGateways', 'whatsappDevices',
            'aiConfigured', 'nodeSchemas', 'schemaOptions'
        ));
    }

    /**
     * Show edit workflow form
     */
    public function edit(string $uid): View|RedirectResponse
    {
        if (!$this->checkPlanAccess()) {
            $notify[] = ['error', translate('Your plan does not include automation features')];
            return redirect()->route('user.automation.index')->withNotify($notify);
        }

        Session::put("menu_active", true);

        $user = $this->user();

        $workflow = AutomationWorkflow::where('uid', $uid)
            ->where('user_id', $user->id)
            ->with('nodes')
            ->firstOrFail();

        $title = translate("Edit Workflow") . ': ' . $workflow->name;
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('user.dashboard')],
            ['name' => translate('Automation'), 'url' => route('user.automation.index')],
            ['name' => translate('Edit')],
        ];

        $triggerTypes = AutomationWorkflow::TRIGGER_TYPES;
        $actionTypes = WorkflowNode::ACTION_TYPES;
        $conditionTypes = WorkflowNode::CONDITION_TYPES;
        $waitTypes = WorkflowNode::WAIT_TYPES;
        $operators = ConditionEvaluatorService::getFieldOperators();
        $limits = $this->getPlanLimits();

        // Get user's resources
        $groups = ContactGroup::where('user_id', $user->id)->get();
        $templates = Template::where('user_id', $user->id)->get();

        // Get user's gateways
        $smsGateways = Gateway::where('user_id', $user->id)
            ->where('channel', ChannelTypeEnum::SMS->value)
            ->active()
            ->get();
        $emailGateways = Gateway::where('user_id', $user->id)
            ->where('channel', ChannelTypeEnum::EMAIL->value)
            ->active()
            ->get();
        $whatsappDevices = Gateway::where('user_id', $user->id)
            ->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->active()
            ->get();

        $aiModels = json_decode(site_settings('ai_models', '{}'), true);
        $aiConfigured = !empty($aiModels['open_ai_text']['status'])
            && $aiModels['open_ai_text']['status'] === '1'
            && !empty($aiModels['open_ai_text']['key'])
            && $aiModels['open_ai_text']['key'] !== '###';

        return view('user.automation.builder', compact(
            'title', 'breadcrumbs', 'workflow', 'triggerTypes', 'actionTypes',
            'conditionTypes', 'waitTypes', 'operators', 'limits',
            'groups', 'templates', 'smsGateways', 'emailGateways', 'whatsappDevices',
            'aiConfigured'
        ));
    }

    /**
     * Store a new workflow
     */
    public function store(Request $request): JsonResponse
    {
        try {
            if (!$this->checkPlanAccess()) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Your plan does not include automation features'),
                ], Response::HTTP_FORBIDDEN);
            }

            $limits = $this->getPlanLimits();
            $currentWorkflows = AutomationWorkflow::where('user_id', $this->user()->id)->count();

            if ($currentWorkflows >= $limits['max_workflows']) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('You have reached the maximum number of workflows for your plan'),
                ], Response::HTTP_FORBIDDEN);
            }

            $validated = $request->validate([
                'name'           => 'required|string|max:255',
                'description'    => 'nullable|string|max:1000',
                'trigger_type'   => 'required|string',
                'trigger_config' => 'nullable|array',
                'nodes'          => 'required|array|min:1',
                'nodes.*.type'   => 'required|in:trigger,action,condition,wait',
                'nodes.*.action_type' => 'required|string',
                'nodes.*.config' => 'nullable|array',
                'nodes.*.label'  => 'nullable|string',
                'nodes.*.position_x' => 'required|numeric',
                'nodes.*.position_y' => 'required|numeric',
                'nodes.*.next_node_index' => 'nullable|integer',
                'nodes.*.condition_true_index' => 'nullable|integer',
                'nodes.*.condition_false_index' => 'nullable|integer',
            ]);

            // Check node limit
            if (count($validated['nodes']) > $limits['max_nodes_per_workflow']) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Maximum') . ' ' . $limits['max_nodes_per_workflow'] . ' ' . translate('nodes allowed per workflow'),
                ], Response::HTTP_FORBIDDEN);
            }

            DB::beginTransaction();

            // Create workflow
            $workflow = AutomationWorkflow::create([
                'uid'            => str_unique(),
                'user_id'        => $this->user()->id,
                'name'           => $validated['name'],
                'description'    => $validated['description'] ?? null,
                'trigger_type'   => $validated['trigger_type'],
                'trigger_config' => $validated['trigger_config'] ?? [],
                'status'         => 'draft',
            ]);

            // First pass: create all nodes and build index map
            $nodeIndexMap = [];
            $createdNodes = [];

            foreach ($validated['nodes'] as $index => $nodeData) {
                $node = WorkflowNode::create([
                    'workflow_id'  => $workflow->id,
                    'type'         => $nodeData['type'],
                    'action_type'  => $nodeData['action_type'],
                    'config'       => $nodeData['config'] ?? [],
                    'label'        => $nodeData['label'] ?? null,
                    'position_x'   => $nodeData['position_x'],
                    'position_y'   => $nodeData['position_y'],
                ]);
                $nodeIndexMap[$index] = $node->id;
                $createdNodes[$index] = $node;
            }

            // Second pass: update node connections using index references
            foreach ($validated['nodes'] as $index => $nodeData) {
                $updates = [];

                if (isset($nodeData['next_node_index']) && isset($nodeIndexMap[$nodeData['next_node_index']])) {
                    $updates['next_node_id'] = $nodeIndexMap[$nodeData['next_node_index']];
                }
                if (isset($nodeData['condition_true_index']) && isset($nodeIndexMap[$nodeData['condition_true_index']])) {
                    $updates['condition_true_node_id'] = $nodeIndexMap[$nodeData['condition_true_index']];
                }
                if (isset($nodeData['condition_false_index']) && isset($nodeIndexMap[$nodeData['condition_false_index']])) {
                    $updates['condition_false_node_id'] = $nodeIndexMap[$nodeData['condition_false_index']];
                }

                if (!empty($updates)) {
                    WorkflowNode::where('id', $nodeIndexMap[$index])->update($updates);
                }
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => translate('Workflow created successfully'),
                'data'    => [
                    'workflow_id' => $workflow->uid,
                    'redirect_url' => route('user.automation.show', $workflow->uid),
                ],
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a workflow
     */
    public function update(Request $request, string $uid): JsonResponse
    {
        try {
            if (!$this->checkPlanAccess()) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Your plan does not include automation features'),
                ], Response::HTTP_FORBIDDEN);
            }

            $workflow = AutomationWorkflow::where('uid', $uid)
                ->where('user_id', $this->user()->id)
                ->firstOrFail();

            $limits = $this->getPlanLimits();

            $validated = $request->validate([
                'name'           => 'required|string|max:255',
                'description'    => 'nullable|string|max:1000',
                'trigger_type'   => 'required|string',
                'trigger_config' => 'nullable|array',
                'nodes'          => 'required|array|min:1',
                'nodes.*.type'   => 'required|in:trigger,action,condition,wait',
                'nodes.*.action_type' => 'required|string',
                'nodes.*.config' => 'nullable|array',
                'nodes.*.label'  => 'nullable|string',
                'nodes.*.position_x' => 'required|numeric',
                'nodes.*.position_y' => 'required|numeric',
                'nodes.*.next_node_index' => 'nullable|integer',
                'nodes.*.condition_true_index' => 'nullable|integer',
                'nodes.*.condition_false_index' => 'nullable|integer',
            ]);

            // Check node limit
            if (count($validated['nodes']) > $limits['max_nodes_per_workflow']) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Maximum') . ' ' . $limits['max_nodes_per_workflow'] . ' ' . translate('nodes allowed per workflow'),
                ], Response::HTTP_FORBIDDEN);
            }

            DB::beginTransaction();

            // Update workflow
            $workflow->update([
                'name'           => $validated['name'],
                'description'    => $validated['description'] ?? null,
                'trigger_type'   => $validated['trigger_type'],
                'trigger_config' => $validated['trigger_config'] ?? [],
            ]);

            // Delete existing nodes
            $workflow->nodes()->delete();

            // First pass: create all nodes and build index map
            $nodeIndexMap = [];

            foreach ($validated['nodes'] as $index => $nodeData) {
                $node = WorkflowNode::create([
                    'workflow_id'  => $workflow->id,
                    'type'         => $nodeData['type'],
                    'action_type'  => $nodeData['action_type'],
                    'config'       => $nodeData['config'] ?? [],
                    'label'        => $nodeData['label'] ?? null,
                    'position_x'   => $nodeData['position_x'],
                    'position_y'   => $nodeData['position_y'],
                ]);
                $nodeIndexMap[$index] = $node->id;
            }

            // Second pass: update node connections using index references
            foreach ($validated['nodes'] as $index => $nodeData) {
                $updates = [];

                if (isset($nodeData['next_node_index']) && isset($nodeIndexMap[$nodeData['next_node_index']])) {
                    $updates['next_node_id'] = $nodeIndexMap[$nodeData['next_node_index']];
                }
                if (isset($nodeData['condition_true_index']) && isset($nodeIndexMap[$nodeData['condition_true_index']])) {
                    $updates['condition_true_node_id'] = $nodeIndexMap[$nodeData['condition_true_index']];
                }
                if (isset($nodeData['condition_false_index']) && isset($nodeIndexMap[$nodeData['condition_false_index']])) {
                    $updates['condition_false_node_id'] = $nodeIndexMap[$nodeData['condition_false_index']];
                }

                if (!empty($updates)) {
                    WorkflowNode::where('id', $nodeIndexMap[$index])->update($updates);
                }
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => translate('Workflow updated successfully'),
                'data'    => [
                    'workflow_id' => $workflow->uid,
                    'redirect_url' => route('user.automation.show', $workflow->uid),
                ],
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Show workflow details
     */
    public function show(string $uid): View
    {
        Session::put("menu_active", true);

        $workflow = AutomationWorkflow::where('uid', $uid)
            ->where('user_id', $this->user()->id)
            ->with('nodes')
            ->withCount('nodes')
            ->firstOrFail();

        $title = translate("Workflow") . ': ' . $workflow->name;
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('user.dashboard')],
            ['name' => translate('Automation'), 'url' => route('user.automation.index')],
            ['name' => $workflow->name],
        ];

        // Get executions
        $executions = $workflow->executions()
            ->with('contact')
            ->orderByDesc('started_at')
            ->paginate(paginateNumber());

        // Get statistics
        $stats = [
            'total_enrolled'    => $workflow->executions()->count(),
            'total_completed'   => $workflow->executions()->completed()->count(),
            'total_failed'      => $workflow->executions()->failed()->count(),
            'currently_running' => $workflow->executions()->running()->count(),
            'currently_waiting' => $workflow->executions()->waiting()->count(),
            'completion_rate'   => $workflow->executions()->count() > 0
                ? round(($workflow->executions()->completed()->count() / $workflow->executions()->count()) * 100)
                : 0,
        ];

        return view('user.automation.show', compact(
            'title', 'breadcrumbs', 'workflow', 'executions', 'stats'
        ));
    }

    /**
     * Get workflow data for builder
     */
    public function getData(string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->where('user_id', $this->user()->id)
                ->with('nodes')
                ->firstOrFail();

            $nodes = $workflow->nodes->map(function ($node) {
                return [
                    'id'                       => 'node_' . $node->id,
                    'db_id'                    => $node->id,
                    'type'                     => $node->type,
                    'action_type'              => $node->action_type,
                    'label'                    => $node->label,
                    'config'                   => $node->config,
                    'position_x'               => $node->position_x,
                    'position_y'               => $node->position_y,
                    'next_node_id'             => $node->next_node_id ? 'node_' . $node->next_node_id : null,
                    'condition_true_node_id'   => $node->condition_true_node_id ? 'node_' . $node->condition_true_node_id : null,
                    'condition_false_node_id'  => $node->condition_false_node_id ? 'node_' . $node->condition_false_node_id : null,
                ];
            });

            return response()->json([
                'status' => true,
                'data'   => [
                    'name'           => $workflow->name,
                    'description'    => $workflow->description,
                    'trigger_type'   => $workflow->trigger_type,
                    'trigger_config' => $workflow->trigger_config,
                    'status'         => $workflow->status,
                    'nodes'          => $nodes,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Activate workflow
     */
    public function activate(string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->where('user_id', $this->user()->id)
                ->firstOrFail();

            // Validate workflow has required nodes
            if ($workflow->nodes()->count() < 2) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Workflow must have at least a trigger and one action'),
                ], Response::HTTP_BAD_REQUEST);
            }

            $workflow->activate();

            return response()->json([
                'status'  => true,
                'message' => translate('Workflow activated successfully'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Pause workflow
     */
    public function pause(string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->where('user_id', $this->user()->id)
                ->firstOrFail();

            $workflow->pause();

            return response()->json([
                'status'  => true,
                'message' => translate('Workflow paused successfully'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete workflow
     */
    public function destroy(string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->where('user_id', $this->user()->id)
                ->firstOrFail();

            // Delete related data
            $workflow->executions()->each(function ($execution) {
                $execution->logs()->delete();
            });
            $workflow->executions()->delete();
            $workflow->nodes()->delete();
            $workflow->delete();

            return response()->json([
                'status'  => true,
                'message' => translate('Workflow deleted successfully'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Duplicate workflow
     */
    public function duplicate(string $uid): JsonResponse
    {
        try {
            if (!$this->checkPlanAccess()) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Your plan does not include automation features'),
                ], Response::HTTP_FORBIDDEN);
            }

            $limits = $this->getPlanLimits();
            $currentWorkflows = AutomationWorkflow::where('user_id', $this->user()->id)->count();

            if ($currentWorkflows >= $limits['max_workflows']) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('You have reached the maximum number of workflows for your plan'),
                ], Response::HTTP_FORBIDDEN);
            }

            $workflow = AutomationWorkflow::where('uid', $uid)
                ->where('user_id', $this->user()->id)
                ->with('nodes')
                ->firstOrFail();

            DB::beginTransaction();

            // Create new workflow
            $newWorkflow = AutomationWorkflow::create([
                'uid'            => str_unique(),
                'user_id'        => $this->user()->id,
                'name'           => $workflow->name . ' (Copy)',
                'description'    => $workflow->description,
                'trigger_type'   => $workflow->trigger_type,
                'trigger_config' => $workflow->trigger_config,
                'status'         => 'draft',
            ]);

            // Duplicate nodes
            $nodeIdMap = [];
            foreach ($workflow->nodes as $node) {
                $newNode = WorkflowNode::create([
                    'workflow_id' => $newWorkflow->id,
                    'type'        => $node->type,
                    'action'      => $node->action,
                    'config'      => $node->config,
                    'position_x'  => $node->position_x,
                    'position_y'  => $node->position_y,
                ]);
                $nodeIdMap[$node->id] = $newNode->id;
            }

            // Update connections
            foreach ($workflow->nodes as $node) {
                $updates = [];
                if ($node->next_node_id && isset($nodeIdMap[$node->next_node_id])) {
                    $updates['next_node_id'] = $nodeIdMap[$node->next_node_id];
                }
                if ($node->condition_true_node_id && isset($nodeIdMap[$node->condition_true_node_id])) {
                    $updates['condition_true_node_id'] = $nodeIdMap[$node->condition_true_node_id];
                }
                if ($node->condition_false_node_id && isset($nodeIdMap[$node->condition_false_node_id])) {
                    $updates['condition_false_node_id'] = $nodeIdMap[$node->condition_false_node_id];
                }

                if (!empty($updates) && isset($nodeIdMap[$node->id])) {
                    WorkflowNode::where('id', $nodeIdMap[$node->id])->update($updates);
                }
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => translate('Workflow duplicated successfully'),
                'data'    => [
                    'uid'      => $newWorkflow->uid,
                    'redirect' => route('user.automation.edit', $newWorkflow->uid),
                ],
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Manual trigger workflow for contacts
     */
    public function trigger(Request $request, string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->where('user_id', $this->user()->id)
                ->active()
                ->firstOrFail();

            $validated = $request->validate([
                'contact_ids' => 'required|array|min:1',
                'contact_ids.*' => 'integer',
            ]);

            $triggerService = app(TriggerHandlerService::class);
            $enrolledCount = 0;

            foreach ($validated['contact_ids'] as $contactId) {
                if ($triggerService->handleManualTrigger($workflow->id, $contactId)) {
                    $enrolledCount++;
                }
            }

            return response()->json([
                'status'  => true,
                'message' => translate('Successfully enrolled') . ' ' . $enrolledCount . ' ' . translate('contacts'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * View execution details
     */
    public function execution(string $workflowUid, string $executionUid): View
    {
        Session::put("menu_active", true);

        $workflow = AutomationWorkflow::where('uid', $workflowUid)
            ->where('user_id', $this->user()->id)
            ->firstOrFail();

        $execution = WorkflowExecution::where('uid', $executionUid)
            ->where('workflow_id', $workflow->id)
            ->with(['contact', 'currentNode', 'logs'])
            ->firstOrFail();

        $title = translate("Execution Details");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('user.dashboard')],
            ['name' => translate('Automation'), 'url' => route('user.automation.index')],
            ['name' => $workflow->name, 'url' => route('user.automation.show', $workflow->uid)],
            ['name' => translate('Execution')],
        ];

        // Build timeline from logs
        $timeline = $execution->logs->map(function ($log) {
            return [
                'node_label'  => $log->node?->display_label ?? 'Unknown',
                'action'      => $log->action,
                'result'      => $log->result,
                'data'        => $log->data,
                'error'       => $log->error_message,
                'executed_at' => $log->executed_at,
            ];
        });

        return view('user.automation.execution', compact(
            'title', 'breadcrumbs', 'workflow', 'execution', 'timeline'
        ));
    }

    /**
     * Cancel execution
     */
    public function cancelExecution(string $workflowUid, string $executionUid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $workflowUid)
                ->where('user_id', $this->user()->id)
                ->firstOrFail();

            $execution = WorkflowExecution::where('uid', $executionUid)
                ->where('workflow_id', $workflow->id)
                ->firstOrFail();

            if (!$execution->canContinue()) {
                return response()->json([
                    'status'  => false,
                    'message' => translate('Execution cannot be cancelled'),
                ], Response::HTTP_BAD_REQUEST);
            }

            $execution->update([
                'status'       => 'cancelled',
                'completed_at' => now(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => translate('Execution cancelled successfully'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List all available templates
     */
    public function listTemplates()
    {
        $templates = \App\Models\Automation\WorkflowTemplate::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // If no templates in database, return default templates
        if ($templates->isEmpty()) {
            $defaultTemplates = \App\Models\Automation\WorkflowTemplate::getDefaultTemplates();
            $templates = collect($defaultTemplates)->map(function ($template) {
                return (object) array_merge($template, [
                    'is_active' => true,
                    'usage_count' => 0,
                ]);
            });
        }

        return response()->json([
            'status' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Return up to 10 of the user's most recent contacts for the Test Run picker.
     */
    public function sampleContacts(string $uid): JsonResponse
    {
        $userId = $this->user()->id;
        AutomationWorkflow::where('uid', $uid)->where('user_id', $userId)->firstOrFail();

        $contacts = \App\Models\Contact::where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'email_contact', 'sms_contact', 'whatsapp_contact'])
            ->map(fn ($c) => [
                'id'        => $c->id,
                'name'      => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: '(no name)',
                'email'     => $c->email_contact,
                'phone'     => $c->sms_contact,
                'whatsapp'  => $c->whatsapp_contact,
            ]);

        return response()->json(['status' => true, 'contacts' => $contacts]);
    }

    /**
     * Test Run / Dry Run — simulates workflow against a sample contact, no side effects.
     */
    public function testRun(Request $request, string $uid): JsonResponse
    {
        try {
            $userId = $this->user()->id;
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->where('user_id', $userId)
                ->with('nodes')
                ->firstOrFail();

            $validated = $request->validate([
                'contact_id' => 'required|integer|exists:contacts,id',
            ]);

            $contact = \App\Models\Contact::where('id', $validated['contact_id'])
                ->where('user_id', $userId)
                ->firstOrFail();

            $simulator = new \App\Services\Automation\WorkflowSimulationService();
            $result = $simulator->simulate($workflow, $contact);

            return response()->json([
                'status'  => true,
                'message' => translate('Test run complete. No real messages were sent.'),
                'result'  => $result,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->validator->errors()->first(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
