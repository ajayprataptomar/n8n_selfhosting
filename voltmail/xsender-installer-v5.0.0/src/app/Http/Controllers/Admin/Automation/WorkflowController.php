<?php

namespace App\Http\Controllers\Admin\Automation;

use Exception;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;
use App\Traits\ModelAction;
use App\Models\ContactGroup;
use App\Models\Gateway;
use App\Models\Automation\AutomationWorkflow;
use App\Models\Automation\WorkflowNode;
use App\Models\Automation\WorkflowExecution;
use App\Services\Automation\WorkflowExecutionService;
use App\Services\Automation\TriggerHandlerService;
use App\Services\Automation\ConditionEvaluatorService;
use App\Jobs\StartWorkflowExecutionJob;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    use ModelAction;

    protected WorkflowExecutionService $executionService;
    protected TriggerHandlerService $triggerService;

    public function __construct()
    {
        $this->executionService = new WorkflowExecutionService();
        $this->triggerService = new TriggerHandlerService();
    }

    /**
     * Display workflow listing
     */
    public function index(): View
    {
        Session::put("menu_active", true);

        $title = translate("Automation Workflows");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Automation Workflows')],
        ];

        $workflows = AutomationWorkflow::whereNull('user_id')
            ->withCount(['nodes', 'executions'])
            ->orderByDesc('created_at')
            ->paginate(paginateNumber());

        // Statistics
        $stats = [
            'total_workflows'   => AutomationWorkflow::whereNull('user_id')->count(),
            'active_workflows'  => AutomationWorkflow::whereNull('user_id')->active()->count(),
            'total_enrolled'    => AutomationWorkflow::whereNull('user_id')->sum('total_enrolled'),
            'total_completed'   => AutomationWorkflow::whereNull('user_id')->sum('total_completed'),
            'active_executions' => WorkflowExecution::whereHas('workflow', fn($q) => $q->whereNull('user_id'))->active()->count(),
        ];

        return view('admin.automation.index', compact(
            'title', 'breadcrumbs', 'workflows', 'stats'
        ));
    }

    /**
     * Show workflow builder
     */
    public function create(): View
    {
        Session::put("menu_active", true);

        $title = translate("Create Workflow");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Automation'), 'url' => route('admin.automation.index')],
            ['name' => translate('Create Workflow')],
        ];

        $triggerTypes = AutomationWorkflow::TRIGGER_TYPES;
        $actionTypes = WorkflowNode::ACTION_TYPES;
        $conditionTypes = WorkflowNode::CONDITION_TYPES;
        $waitTypes = WorkflowNode::WAIT_TYPES;
        $operators = ConditionEvaluatorService::getFieldOperators();

        // Get resources for action configuration
        $groups = ContactGroup::whereNull('user_id')->orderBy('name')->get();
        $smsGateways = Gateway::whereNull('user_id')
            ->where('channel', ChannelTypeEnum::SMS->value)
            ->active()
            ->get();
        $emailGateways = Gateway::whereNull('user_id')
            ->where('channel', ChannelTypeEnum::EMAIL->value)
            ->active()
            ->get();
        $whatsappDevices = Gateway::whereNull('user_id')
            ->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->active()
            ->get();

        // AI configuration status for chatbot setup guide
        $aiModels = json_decode(site_settings('ai_models', '{}'), true);
        $aiConfigured = !empty($aiModels['open_ai_text']['status'])
            && $aiModels['open_ai_text']['status'] === '1'
            && !empty($aiModels['open_ai_text']['key'])
            && $aiModels['open_ai_text']['key'] !== '###';

        // Schema-driven properties form (admin scope = null user)
        $schemaService = new \App\Services\Automation\NodeFormSchema();
        $nodeSchemas   = $schemaService->all();
        $schemaOptions = $schemaService->resolveOptionLists(null);

        return view('admin.automation.builder', compact(
            'title', 'breadcrumbs', 'triggerTypes', 'actionTypes',
            'conditionTypes', 'waitTypes', 'operators',
            'groups', 'smsGateways', 'emailGateways', 'whatsappDevices',
            'aiConfigured', 'nodeSchemas', 'schemaOptions'
        ));
    }

    /**
     * Edit existing workflow
     */
    public function edit(string $uid): View
    {
        Session::put("menu_active", true);

        $workflow = AutomationWorkflow::where('uid', $uid)
            ->whereNull('user_id')
            ->with('nodes')
            ->firstOrFail();

        $title = translate("Edit Workflow");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Automation'), 'url' => route('admin.automation.index')],
            ['name' => $workflow->name],
        ];

        $triggerTypes = AutomationWorkflow::TRIGGER_TYPES;
        $actionTypes = WorkflowNode::ACTION_TYPES;
        $conditionTypes = WorkflowNode::CONDITION_TYPES;
        $waitTypes = WorkflowNode::WAIT_TYPES;
        $operators = ConditionEvaluatorService::getFieldOperators();

        // Get resources
        $groups = ContactGroup::whereNull('user_id')->orderBy('name')->get();
        $smsGateways = Gateway::whereNull('user_id')
            ->where('channel', ChannelTypeEnum::SMS->value)
            ->active()
            ->get();
        $emailGateways = Gateway::whereNull('user_id')
            ->where('channel', ChannelTypeEnum::EMAIL->value)
            ->active()
            ->get();
        $whatsappDevices = Gateway::whereNull('user_id')
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
        $schemaOptions = $schemaService->resolveOptionLists(null);

        return view('admin.automation.builder', compact(
            'title', 'breadcrumbs', 'workflow', 'triggerTypes', 'actionTypes',
            'conditionTypes', 'waitTypes', 'operators',
            'groups', 'smsGateways', 'emailGateways', 'whatsappDevices',
            'aiConfigured', 'nodeSchemas', 'schemaOptions'
        ));
    }

    /**
     * Store workflow
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name'           => 'required|string|max:255',
                'description'    => 'nullable|string|max:1000',
                'trigger_type'   => 'required|string',
                'trigger_config' => 'nullable|array',
                'nodes'          => 'required|array|min:1',
                'nodes.*.type'   => 'required|in:trigger,action,condition,wait',
                'nodes.*.action_type' => 'required|string',
                'nodes.*.config' => 'nullable|array',
                'nodes.*.label'  => 'nullable|string|max:100',
                'nodes.*.position_x' => 'nullable|integer',
                'nodes.*.position_y' => 'nullable|integer',
                'nodes.*.next_node_index' => 'nullable|integer',
                'nodes.*.condition_true_index' => 'nullable|integer',
                'nodes.*.condition_false_index' => 'nullable|integer',
            ]);

            $workflow = DB::transaction(function () use ($validated) {
                // Create workflow
                $workflow = AutomationWorkflow::create([
                    'user_id' => null,
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'status' => 'draft',
                    'trigger_type' => $validated['trigger_type'],
                    'trigger_config' => $validated['trigger_config'] ?? [],
                ]);

                // Create nodes and track IDs for linking
                $nodeIdMap = [];
                $order = 0;

                foreach ($validated['nodes'] as $index => $nodeData) {
                    $node = WorkflowNode::create([
                        'workflow_id' => $workflow->id,
                        'type' => $nodeData['type'],
                        'action_type' => $nodeData['action_type'],
                        'config' => $nodeData['config'] ?? [],
                        'label' => $nodeData['label'] ?? null,
                        'position_x' => $nodeData['position_x'] ?? 0,
                        'position_y' => $nodeData['position_y'] ?? ($order * 150),
                        'order' => $order++,
                    ]);
                    $nodeIdMap[$index] = $node;
                }

                // Link nodes together
                foreach ($validated['nodes'] as $index => $nodeData) {
                    $node = $nodeIdMap[$index];
                    $updates = [];

                    if (isset($nodeData['next_node_index']) && isset($nodeIdMap[$nodeData['next_node_index']])) {
                        $updates['next_node_id'] = $nodeIdMap[$nodeData['next_node_index']]->id;
                    }
                    if (isset($nodeData['condition_true_index']) && isset($nodeIdMap[$nodeData['condition_true_index']])) {
                        $updates['condition_true_node_id'] = $nodeIdMap[$nodeData['condition_true_index']]->id;
                    }
                    if (isset($nodeData['condition_false_index']) && isset($nodeIdMap[$nodeData['condition_false_index']])) {
                        $updates['condition_false_node_id'] = $nodeIdMap[$nodeData['condition_false_index']]->id;
                    }

                    if (!empty($updates)) {
                        $node->update($updates);
                    }
                }

                return $workflow;
            });

            return response()->json([
                'status' => true,
                'message' => translate('Workflow created successfully'),
                'data' => [
                    'workflow_id' => $workflow->uid,
                    'redirect_url' => route('admin.automation.show', $workflow->uid),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update workflow
     */
    public function update(Request $request, string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->whereNull('user_id')
                ->firstOrFail();

            $validated = $request->validate([
                'name'           => 'required|string|max:255',
                'description'    => 'nullable|string|max:1000',
                'trigger_type'   => 'required|string',
                'trigger_config' => 'nullable|array',
                'nodes'          => 'required|array|min:1',
                'nodes.*.id'     => 'nullable|integer',
                'nodes.*.type'   => 'required|in:trigger,action,condition,wait',
                'nodes.*.action_type' => 'required|string',
                'nodes.*.config' => 'nullable|array',
                'nodes.*.label'  => 'nullable|string|max:100',
                'nodes.*.position_x' => 'nullable|integer',
                'nodes.*.position_y' => 'nullable|integer',
                'nodes.*.next_node_index' => 'nullable|integer',
                'nodes.*.condition_true_index' => 'nullable|integer',
                'nodes.*.condition_false_index' => 'nullable|integer',
            ]);

            DB::transaction(function () use ($workflow, $validated) {
                // Update workflow
                $workflow->update([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'trigger_type' => $validated['trigger_type'],
                    'trigger_config' => $validated['trigger_config'] ?? [],
                ]);

                // Delete existing nodes
                $workflow->nodes()->delete();

                // Recreate nodes
                $nodeIdMap = [];
                $order = 0;

                foreach ($validated['nodes'] as $index => $nodeData) {
                    $node = WorkflowNode::create([
                        'workflow_id' => $workflow->id,
                        'type' => $nodeData['type'],
                        'action_type' => $nodeData['action_type'],
                        'config' => $nodeData['config'] ?? [],
                        'label' => $nodeData['label'] ?? null,
                        'position_x' => $nodeData['position_x'] ?? 0,
                        'position_y' => $nodeData['position_y'] ?? ($order * 150),
                        'order' => $order++,
                    ]);
                    $nodeIdMap[$index] = $node;
                }

                // Link nodes
                foreach ($validated['nodes'] as $index => $nodeData) {
                    $node = $nodeIdMap[$index];
                    $updates = [];

                    if (isset($nodeData['next_node_index']) && isset($nodeIdMap[$nodeData['next_node_index']])) {
                        $updates['next_node_id'] = $nodeIdMap[$nodeData['next_node_index']]->id;
                    }
                    if (isset($nodeData['condition_true_index']) && isset($nodeIdMap[$nodeData['condition_true_index']])) {
                        $updates['condition_true_node_id'] = $nodeIdMap[$nodeData['condition_true_index']]->id;
                    }
                    if (isset($nodeData['condition_false_index']) && isset($nodeIdMap[$nodeData['condition_false_index']])) {
                        $updates['condition_false_node_id'] = $nodeIdMap[$nodeData['condition_false_index']]->id;
                    }

                    if (!empty($updates)) {
                        $node->update($updates);
                    }
                }
            });

            return response()->json([
                'status' => true,
                'message' => translate('Workflow updated successfully'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
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
            ->whereNull('user_id')
            ->with(['nodes', 'triggerLogs' => fn($q) => $q->orderByDesc('triggered_at')->limit(10)])
            ->withCount(['nodes', 'executions'])
            ->firstOrFail();

        $title = $workflow->name;
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Automation'), 'url' => route('admin.automation.index')],
            ['name' => $workflow->name],
        ];

        $stats = $this->executionService->getWorkflowStats($workflow);

        // Recent executions
        $executions = $workflow->executions()
            ->with(['contact', 'currentNode'])
            ->latest()
            ->paginate(20);

        return view('admin.automation.show', compact(
            'title', 'breadcrumbs', 'workflow', 'stats', 'executions'
        ));
    }

    /**
     * Activate workflow
     */
    public function activate(string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->whereNull('user_id')
                ->firstOrFail();

            if ($workflow->activate()) {
                return response()->json([
                    'status' => true,
                    'message' => translate('Workflow activated successfully'),
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => translate('Workflow must have at least 2 nodes (trigger + action)'),
            ], Response::HTTP_BAD_REQUEST);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
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
                ->whereNull('user_id')
                ->firstOrFail();

            $workflow->pause();

            return response()->json([
                'status' => true,
                'message' => translate('Workflow paused successfully'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
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
                ->whereNull('user_id')
                ->firstOrFail();

            // Check for active executions
            $activeCount = $workflow->executions()->active()->count();
            if ($activeCount > 0) {
                return response()->json([
                    'status' => false,
                    'message' => translate('Cannot delete workflow with') . ' ' . $activeCount . ' ' . translate('active executions'),
                ], Response::HTTP_BAD_REQUEST);
            }

            $workflow->delete();

            return response()->json([
                'status' => true,
                'message' => translate('Workflow deleted successfully'),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Manually trigger workflow for selected contacts
     */
    public function trigger(Request $request, string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->whereNull('user_id')
                ->firstOrFail();

            if (!$workflow->isActive()) {
                return response()->json([
                    'status' => false,
                    'message' => translate('Workflow must be active to trigger'),
                ], Response::HTTP_BAD_REQUEST);
            }

            $validated = $request->validate([
                'contact_ids' => 'required|array|min:1',
                'contact_ids.*' => 'integer|exists:contacts,id',
            ]);

            $results = $this->triggerService->handleManualTrigger($workflow, $validated['contact_ids']);

            return response()->json([
                'status' => true,
                'message' => translate('Enrolled') . ' ' . $results['enrolled'] . ' ' . translate('contacts') . ' (' . $results['skipped'] . ' ' . translate('skipped') . ')',
                'data' => $results,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
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
            ->whereNull('user_id')
            ->firstOrFail();

        $execution = WorkflowExecution::where('uid', $executionUid)
            ->where('workflow_id', $workflow->id)
            ->with(['contact', 'currentNode', 'logs.node'])
            ->firstOrFail();

        $title = translate("Execution Details");
        $breadcrumbs = [
            ['name' => translate('Dashboard'), 'url' => route('admin.dashboard')],
            ['name' => translate('Automation'), 'url' => route('admin.automation.index')],
            ['name' => $workflow->name, 'url' => route('admin.automation.show', $workflow->uid)],
            ['name' => translate('Execution')],
        ];

        $timeline = $this->executionService->getExecutionTimeline($execution);

        return view('admin.automation.execution', compact(
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
                ->whereNull('user_id')
                ->firstOrFail();

            $execution = WorkflowExecution::where('uid', $executionUid)
                ->where('workflow_id', $workflow->id)
                ->firstOrFail();

            if ($this->executionService->cancelExecution($execution)) {
                return response()->json([
                    'status' => true,
                    'message' => translate('Execution cancelled successfully'),
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => translate('Execution cannot be cancelled'),
            ], Response::HTTP_BAD_REQUEST);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get workflow data as JSON (for builder)
     */
    public function getData(string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->whereNull('user_id')
                ->with('nodes')
                ->firstOrFail();

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $workflow->uid,
                    'name' => $workflow->name,
                    'description' => $workflow->description,
                    'status' => $workflow->status,
                    'trigger_type' => $workflow->trigger_type,
                    'trigger_config' => $workflow->trigger_config,
                    'nodes' => $workflow->nodes->map(function ($node) {
                        return [
                            'id' => $node->id,
                            'uid' => $node->uid,
                            'type' => $node->type,
                            'action_type' => $node->action_type,
                            'config' => $node->config,
                            'label' => $node->label,
                            'position_x' => $node->position_x,
                            'position_y' => $node->position_y,
                            'next_node_id' => $node->next_node_id,
                            'condition_true_node_id' => $node->condition_true_node_id,
                            'condition_false_node_id' => $node->condition_false_node_id,
                        ];
                    }),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Duplicate workflow
     */
    public function duplicate(string $uid): JsonResponse
    {
        try {
            $original = AutomationWorkflow::where('uid', $uid)
                ->whereNull('user_id')
                ->with('nodes')
                ->firstOrFail();

            $newWorkflow = DB::transaction(function () use ($original) {
                // Clone workflow
                $workflow = $original->replicate();
                $workflow->uid = null; // Will be auto-generated
                $workflow->name = $original->name . ' (Copy)';
                $workflow->status = 'draft';
                $workflow->total_enrolled = 0;
                $workflow->total_completed = 0;
                $workflow->total_failed = 0;
                $workflow->last_triggered_at = null;
                $workflow->save();
                $workflow->refresh(); // Refresh to get auto-generated uid

                // Clone nodes
                $nodeIdMap = [];
                foreach ($original->nodes as $originalNode) {
                    $node = $originalNode->replicate();
                    $node->uid = null;
                    $node->workflow_id = $workflow->id;
                    $node->next_node_id = null;
                    $node->condition_true_node_id = null;
                    $node->condition_false_node_id = null;
                    $node->save();
                    $nodeIdMap[$originalNode->id] = $node;
                }

                // Relink nodes
                foreach ($original->nodes as $originalNode) {
                    $newNode = $nodeIdMap[$originalNode->id];
                    $updates = [];

                    if ($originalNode->next_node_id && isset($nodeIdMap[$originalNode->next_node_id])) {
                        $updates['next_node_id'] = $nodeIdMap[$originalNode->next_node_id]->id;
                    }
                    if ($originalNode->condition_true_node_id && isset($nodeIdMap[$originalNode->condition_true_node_id])) {
                        $updates['condition_true_node_id'] = $nodeIdMap[$originalNode->condition_true_node_id]->id;
                    }
                    if ($originalNode->condition_false_node_id && isset($nodeIdMap[$originalNode->condition_false_node_id])) {
                        $updates['condition_false_node_id'] = $nodeIdMap[$originalNode->condition_false_node_id]->id;
                    }

                    if (!empty($updates)) {
                        $newNode->update($updates);
                    }
                }

                return $workflow;
            });

            return response()->json([
                'status' => true,
                'message' => translate('Workflow duplicated successfully'),
                'data' => [
                    'workflow_id' => $newWorkflow->uid,
                    'redirect_url' => route('admin.automation.edit', $newWorkflow->uid),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Return up to 10 recent contacts to populate the Test Run sample picker.
     */
    public function sampleContacts(string $uid): JsonResponse
    {
        AutomationWorkflow::where('uid', $uid)->whereNull('user_id')->firstOrFail();

        $contacts = \App\Models\Contact::whereNull('user_id')
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
     * Test Run / Dry Run — simulates the workflow against a sample contact
     * without performing any side effects (no SMS sent, no webhook called).
     */
    public function testRun(Request $request, string $uid): JsonResponse
    {
        try {
            $workflow = AutomationWorkflow::where('uid', $uid)
                ->whereNull('user_id')
                ->with('nodes')
                ->firstOrFail();

            $validated = $request->validate([
                'contact_id' => 'required|integer|exists:contacts,id',
            ]);

            $contact = \App\Models\Contact::findOrFail($validated['contact_id']);

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
