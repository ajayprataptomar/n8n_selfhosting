<?php
namespace App\Service\Admin\Core;

use App\Enums\AndroidApiSimEnum;
use App\Enums\StatusEnum;
use App\Enums\System\SessionStatusEnum;
use App\Models\AndroidApi;
use App\Models\AndroidSession;
use App\Models\Gateway;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\System\DeviceLimitService;

class PricingPlanService
{
    public function planLog() {

        return PricingPlan::search(['name'])
                            ->filter(['status'])
                            ->orderBy('recommended_status', 'DESC')
                            ->latest()
                            ->date()
                            ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                            ->appends(request()->all());
    }

    public function statusUpdate($request) {

        $status = "error";
        $message = "Something went wrong";

        try {
            $status   = true;
            $reload   = false;
            $message  = translate('Pricing plan status updated successfully');
            $gateway  = PricingPlan::where("id",$request->input('id'))->first();
            $column   = $request->input("column");

            if (!$gateway) {
                return json_encode([
                    'reload'  => false,
                    'status'  => false,
                    'message' => translate("Plan not found")
                ]);
            }

            if($column != "recommended_status") {

                // Toggle based on current DB value instead of request value
                if($gateway->status == StatusEnum::TRUE->status()) {
                    $gateway->status = StatusEnum::FALSE->status();
                    if($gateway->recommended_status == StatusEnum::TRUE->status()) {
                        $gateway->recommended_status = StatusEnum::FALSE->status();
                        $reload = true;
                    }
                } else {
                    $gateway->status = StatusEnum::TRUE->status();
                }
                $gateway->update();

            } elseif($column == "recommended_status") {

                $reload = true;
                $message  = translate('Recommended plan updated successfully');
                PricingPlan::where('id', '!=',$request->id)->update(["recommended_status" => StatusEnum::FALSE->status()]);
                $gateway->$column = StatusEnum::TRUE->status();
                $gateway->status  = StatusEnum::TRUE->status();

                $gateway->update();
            }

        } catch (\Exception $error) {

            $status  = false;
            $errorMsg = $error->getMessage();
            // Ensure message is always a string (not object/array)
            $message = is_string($errorMsg) ? $errorMsg : translate('Failed to update plan status');
        }

        return json_encode([
            'reload'  => $reload ?? false,
            'status'  => $status,
            'message' => $message
        ]);
    }

    /**
     * 
     * @param $id
     *
     * @return array
     */
    public function deleteplan($id): array {

        $plan = $this->fetchWithId($id);
        
        if($plan && $plan->recommended_status != StatusEnum::TRUE->status()) {
            
            $plan->delete();
            Subscription::where('plan_id',$id)->delete();
            $status  = 'success';
            $message = translate("plan ").$plan->name.translate(' has been deleted successfully from admin panel');
        } elseif($plan->recommended_status == StatusEnum::TRUE->status()) {

            $status  = 'error';
            $message = translate("Can not delete recommended plan. Please inactive the plan or make another plan recommended in order to delete this plan."); 
        } else {

            $status  = 'error';
            $message = translate("plan couldn't be found"); 
        }
        return [
            
            $status, 
            $message
        ];
    }

    /**
     * 
     * @param string $id
     *
     * @return plan $plan
     */
    public function fetchWithId($id) {

        return PricingPlan::where("id", $id)->first();
    }

    /**
     * Update device limits for all users when a plan is modified
     *
     * This method is called when an admin edits a plan's device limits.
     * Instead of disconnecting all devices, it smartly enforces the new limits
     * by only deactivating excess devices beyond the new plan limits.
     *
     * @param int $planId The ID of the plan being edited
     * @param PricingPlan|null $oldPlanData The plan data before changes (for comparison)
     * @return array Summary of actions taken for all affected users
     */
    public function updatePlanRelatedModels($planId, ?PricingPlan $oldPlanData = null)
    {
        $plan = PricingPlan::find($planId);
        if (!$plan) {
            return ['error' => 'Plan not found'];
        }

        // Get all users with active subscriptions to this plan
        $userIds = Subscription::where('plan_id', $planId)
            ->whereIn('status', [Subscription::RUNNING, Subscription::RENEWED])
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return ['message' => 'No active users on this plan', 'affected_users' => 0];
        }

        $deviceLimitService = new DeviceLimitService();
        $results = [
            'plan_id' => $planId,
            'plan_name' => $plan->name,
            'affected_users' => $userIds->count(),
            'user_results' => []
        ];

        // Process each user's devices according to new plan limits
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (!$user) continue;

            try {
                // Treat plan edit as potential downgrade - enforce new limits
                // Pass the old plan data if available for smart comparison
                $userResult = $deviceLimitService->handleSubscriptionChange(
                    $user,
                    $oldPlanData, // Old plan data (before changes)
                    $plan, // Current plan (after changes)
                    'plan_edit_by_admin'
                );

                $results['user_results'][$userId] = [
                    'status' => 'success',
                    'change_type' => $userResult['change_type'] ?? 'unknown',
                    'whatsapp' => $userResult['whatsapp'] ?? [],
                    'sms' => $userResult['sms'] ?? [],
                    'email' => $userResult['email'] ?? [],
                    'android' => $userResult['android'] ?? []
                ];
            } catch (\Exception $e) {
                $results['user_results'][$userId] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                \Log::error('PricingPlanService: Error updating user devices', [
                    'user_id' => $userId,
                    'plan_id' => $planId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        \Log::info('PricingPlanService: Plan edit device management completed', [
            'plan_id' => $planId,
            'affected_users' => $userIds->count(),
            'summary' => array_count_values(array_column($results['user_results'], 'status'))
        ]);

        return $results;
    }

    /**
     * Enforce device limits for a single user based on their current plan
     * Useful for manual enforcement or scheduled checks
     *
     * @param User $user
     * @return array
     */
    public function enforceUserDeviceLimits(User $user): array
    {
        $subscription = $user->runningSubscription();
        if (!$subscription) {
            return ['error' => 'No active subscription'];
        }

        $plan = $subscription->currentPlan();
        if (!$plan) {
            return ['error' => 'No plan found'];
        }

        $deviceLimitService = new DeviceLimitService();

        // Pass the same plan as both old and new to just enforce current limits
        return $deviceLimitService->handleSubscriptionChange(
            $user,
            $plan, // Same as new plan - just enforcing current limits
            $plan,
            'limit_enforcement'
        );
    }
}
