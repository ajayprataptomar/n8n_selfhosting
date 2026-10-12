<?php

namespace App\Services\Automation;

use App\Models\Automation\AutomationWorkflow;
use App\Models\Automation\WorkflowNode;
use App\Models\Contact;

/**
 * WorkflowSimulationService
 * --------------------------
 * Walks a workflow against a sample contact and reports what each node WOULD do,
 * without performing any side effects (no SMS sent, no webhook called, no contact mutated).
 *
 * The simulator stops after MAX_STEPS to guard against accidental loops, and
 * collapses long wait nodes into a single "would wait X" line.
 */
class WorkflowSimulationService
{
    private const MAX_STEPS = 50;

    public function simulate(AutomationWorkflow $workflow, Contact $contact, array $triggerData = []): array
    {
        $steps = [];
        $startNode = $workflow->getFirstActionNode();

        if (!$startNode) {
            return [
                'success' => false,
                'reason'  => 'Workflow has no action nodes after the trigger. Add at least one action.',
                'steps'   => [],
            ];
        }

        $visited = [];
        $current = $startNode;
        $stepNumber = 0;

        while ($current !== null && $stepNumber < self::MAX_STEPS) {
            $stepNumber++;

            if (in_array($current->id, $visited, true)) {
                $steps[] = [
                    'step'    => $stepNumber,
                    'node_id' => $current->id,
                    'label'   => $current->display_label,
                    'type'    => $current->type,
                    'status'  => 'warning',
                    'message' => 'Loop detected — node already visited. Stopping simulation.',
                ];
                break;
            }
            $visited[] = $current->id;

            [$status, $message, $nextNodeId] = $this->simulateNode($current, $contact);

            $steps[] = [
                'step'        => $stepNumber,
                'node_id'     => $current->id,
                'label'       => $current->display_label,
                'type'        => $current->type,
                'action_type' => $current->action_type,
                'status'      => $status,
                'message'     => $message,
            ];

            if ($status === 'error' && ($current->config['stop_on_failure'] ?? false)) {
                $steps[] = [
                    'step'    => $stepNumber + 1,
                    'status'  => 'info',
                    'message' => 'stop_on_failure is enabled on this node — workflow would halt here.',
                ];
                break;
            }

            $current = $nextNodeId ? WorkflowNode::find($nextNodeId) : null;
        }

        if ($stepNumber >= self::MAX_STEPS) {
            $steps[] = [
                'status'  => 'warning',
                'message' => 'Simulation stopped after ' . self::MAX_STEPS . ' steps (max). The workflow is unusually long.',
            ];
        }

        $errorCount = count(array_filter($steps, fn ($s) => ($s['status'] ?? null) === 'error'));

        return [
            'success'    => $errorCount === 0,
            'step_count' => $stepNumber,
            'errors'     => $errorCount,
            'steps'      => $steps,
            'contact'    => [
                'id'         => $contact->id,
                'first_name' => $contact->first_name,
                'last_name'  => $contact->last_name,
                'email'      => $contact->email_contact,
                'phone'      => $contact->sms_contact,
                'whatsapp'   => $contact->whatsapp_contact,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: ?int}  [status, message, nextNodeId]
     */
    private function simulateNode(WorkflowNode $node, Contact $contact): array
    {
        $config = $node->config ?? [];

        switch ($node->type) {
            case 'action':
                return $this->simulateAction($node, $config, $contact);

            case 'condition':
                return $this->simulateCondition($node, $config, $contact);

            case 'wait':
                $wait = $this->describeWait($node, $config);
                return ['info', "Would wait: {$wait}", $node->next_node_id];

            default:
                return ['warning', "Unknown node type: {$node->type}", $node->next_node_id];
        }
    }

    private function simulateAction(WorkflowNode $node, array $config, Contact $contact): array
    {
        $next = $node->next_node_id;

        switch ($node->action_type) {
            case 'send_sms':
                if (empty($config['gateway_id'])) return ['error', 'Missing SMS gateway. Pick one in node settings.', $next];
                if (empty($config['message']))   return ['error', 'Missing SMS message body.', $next];
                if (empty($contact->sms_contact)) return ['error', "Contact has no phone number — SMS would be skipped.", $next];
                $preview = $this->renderVars($config['message'], $contact);
                return ['ok', "Would send SMS to {$contact->sms_contact}: \"" . mb_strimwidth($preview, 0, 80, '…') . '"', $next];

            case 'send_email':
                if (empty($config['gateway_id'])) return ['error', 'Missing email gateway.', $next];
                if (empty($config['subject']))    return ['error', 'Missing email subject.', $next];
                if (empty($config['message']))    return ['error', 'Missing email body.', $next];
                if (empty($contact->email_contact)) return ['error', "Contact has no email — email would be skipped.", $next];
                $subject = $this->renderVars($config['subject'], $contact);
                return ['ok', "Would send email to {$contact->email_contact} — subject: \"{$subject}\"", $next];

            case 'send_whatsapp':
                if (empty($config['device_id'])) return ['error', 'Missing WhatsApp device.', $next];
                if (empty($config['message']))   return ['error', 'Missing WhatsApp message.', $next];
                if (empty($contact->whatsapp_contact)) return ['error', "Contact has no WhatsApp number — message would be skipped.", $next];
                $preview = $this->renderVars($config['message'], $contact);
                return ['ok', "Would send WhatsApp to {$contact->whatsapp_contact}: \"" . mb_strimwidth($preview, 0, 80, '…') . '"', $next];

            case 'add_to_group':
            case 'remove_from_group':
                if (empty($config['group_id'])) return ['error', 'Missing group selection.', $next];
                $verb = $node->action_type === 'add_to_group' ? 'add to' : 'remove from';
                return ['ok', "Would {$verb} group #{$config['group_id']}.", $next];

            case 'add_tag':
                if (empty($config['tag'])) return ['error', 'Missing tag name.', $next];
                return ['ok', "Would add tag \"{$config['tag']}\" to contact.", $next];

            case 'update_contact':
                if (empty($config['field']))           return ['error', 'Missing field selection.', $next];
                if (!isset($config['value']))          return ['error', 'Missing new value.', $next];
                $rendered = $this->renderVars((string) $config['value'], $contact);
                return ['ok', "Would set {$config['field']} = \"{$rendered}\".", $next];

            case 'notify_admin':
                if (empty($config['recipient'])) return ['error', 'Missing recipient.', $next];
                if (empty($config['message']))   return ['error', 'Missing notification message.', $next];
                $method = $config['method'] ?? 'email';
                return ['ok', "Would notify admin via {$method} to {$config['recipient']}.", $next];

            case 'call_webhook':
                if (empty($config['url'])) return ['error', 'Missing webhook URL.', $next];
                $method = strtoupper($config['method'] ?? 'POST');
                if (!filter_var($config['url'], FILTER_VALIDATE_URL)) {
                    return ['error', "Invalid webhook URL: {$config['url']}", $next];
                }
                return ['ok', "Would {$method} to {$config['url']}.", $next];

            case 'ai_auto_reply':
                if (!config('setting.ai_enabled') && empty(config('services.openai.api_key'))) {
                    return ['error', 'AI is not configured (no OpenAI API key in settings).', $next];
                }
                $model = $config['model'] ?? 'gpt-4o-mini';
                return ['ok', "Would generate AI reply using {$model}. (Real calls cost OpenAI credits.)", $next];

            default:
                return ['warning', "Unknown action type: {$node->action_type}", $next];
        }
    }

    private function simulateCondition(WorkflowNode $node, array $config, Contact $contact): array
    {
        // Use the real condition evaluator so simulation is accurate
        $evaluator = new ConditionEvaluatorService();

        try {
            $result = $evaluator->evaluate($node, $contact, null);
        } catch (\Throwable $e) {
            return ['error', "Condition evaluation failed: {$e->getMessage()}", null];
        }

        $branch = $result ? 'TRUE' : 'FALSE';
        $next = $result
            ? $node->condition_true_node_id
            : $node->condition_false_node_id;

        return [
            'info',
            "Condition {$node->action_type} evaluated to {$branch} — would follow the {$branch} branch.",
            $next,
        ];
    }

    private function describeWait(WorkflowNode $node, array $config): string
    {
        switch ($node->action_type) {
            case 'delay':
                return ($config['duration'] ?? 1) . ' ' . ($config['unit'] ?? 'hours');
            case 'until_time':
                return 'until ' . ($config['time'] ?? '09:00');
            case 'until_date':
                return 'until ' . ($config['date'] ?? 'tomorrow') . ' ' . ($config['time'] ?? '09:00');
            default:
                return 'a configured duration';
        }
    }

    private function renderVars(string $template, Contact $contact): string
    {
        $replacements = [
            '{{first_name}}' => $contact->first_name ?? '',
            '{{last_name}}'  => $contact->last_name ?? '',
            '{{email}}'      => $contact->email_contact ?? '',
            '{{phone}}'      => $contact->sms_contact ?? '',
            '{{whatsapp}}'   => $contact->whatsapp_contact ?? '',
        ];

        return strtr($template, $replacements);
    }
}
