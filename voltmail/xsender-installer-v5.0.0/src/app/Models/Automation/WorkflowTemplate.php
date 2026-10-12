<?php

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Model;

class WorkflowTemplate extends Model
{
    protected $fillable = [
        'uid',
        'name',
        'slug',
        'description',
        'category',
        'trigger_type',
        'trigger_config',
        'nodes',
        'icon',
        'is_active',
        'usage_count',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'nodes' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Template categories
     */
    public const CATEGORIES = [
        'welcome' => [
            'label' => 'Welcome & Onboarding',
            'icon' => 'ri-user-add-line',
            'color' => '#10b981',
        ],
        'engagement' => [
            'label' => 'Re-engagement',
            'icon' => 'ri-refresh-line',
            'color' => '#f59e0b',
        ],
        'sales' => [
            'label' => 'Sales & Promotions',
            'icon' => 'ri-shopping-cart-line',
            'color' => '#3b82f6',
        ],
        'reminder' => [
            'label' => 'Reminders',
            'icon' => 'ri-alarm-line',
            'color' => '#8b5cf6',
        ],
        'birthday' => [
            'label' => 'Birthday & Anniversary',
            'icon' => 'ri-cake-2-line',
            'color' => '#ec4899',
        ],
        'feedback' => [
            'label' => 'Feedback & Survey',
            'icon' => 'ri-survey-line',
            'color' => '#06b6d4',
        ],
    ];

    /**
     * Pre-built templates
     */
    public static function getDefaultTemplates(): array
    {
        return [
            // Welcome Series
            [
                'name' => 'Welcome Series',
                'slug' => 'welcome-series',
                'description' => 'Send a series of welcome messages to new contacts over 7 days',
                'category' => 'welcome',
                'trigger_type' => 'new_contact',
                'trigger_config' => [],
                'icon' => 'ri-user-add-line',
                'nodes' => [
                    [
                        'type' => 'trigger',
                        'action' => 'new_contact',
                        'config' => [],
                        'label' => 'New Contact Added',
                        'position_x' => 400,
                        'position_y' => 50,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => 'Welcome {{first_name}}! Thank you for joining us. Reply HELP for assistance.'],
                        'label' => 'Send Welcome SMS',
                        'position_x' => 400,
                        'position_y' => 180,
                    ],
                    [
                        'type' => 'wait',
                        'action' => 'wait_duration',
                        'config' => ['duration' => 2, 'unit' => 'days'],
                        'label' => 'Wait 2 Days',
                        'position_x' => 400,
                        'position_y' => 310,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => 'Hi {{first_name}}, did you know you can access exclusive deals? Check out our latest offers!'],
                        'label' => 'Send Follow-up',
                        'position_x' => 400,
                        'position_y' => 440,
                    ],
                    [
                        'type' => 'wait',
                        'action' => 'wait_duration',
                        'config' => ['duration' => 5, 'unit' => 'days'],
                        'label' => 'Wait 5 Days',
                        'position_x' => 400,
                        'position_y' => 570,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => '{{first_name}}, we hope you\'re enjoying being part of our community! Here\'s a special 10% discount: WELCOME10'],
                        'label' => 'Send Discount',
                        'position_x' => 400,
                        'position_y' => 700,
                    ],
                ],
            ],

            // Re-engagement Campaign
            [
                'name' => 'Re-engagement Campaign',
                'slug' => 're-engagement',
                'description' => 'Win back inactive contacts with targeted messages',
                'category' => 'engagement',
                'trigger_type' => 'no_response',
                'trigger_config' => ['days' => 30],
                'icon' => 'ri-refresh-line',
                'nodes' => [
                    [
                        'type' => 'trigger',
                        'action' => 'no_response',
                        'config' => ['days' => 30],
                        'label' => 'No Activity 30 Days',
                        'position_x' => 400,
                        'position_y' => 50,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => 'Hi {{first_name}}, we miss you! It\'s been a while. Come back and see what\'s new.'],
                        'label' => 'Send Miss You Message',
                        'position_x' => 400,
                        'position_y' => 180,
                    ],
                    [
                        'type' => 'wait',
                        'action' => 'wait_duration',
                        'config' => ['duration' => 3, 'unit' => 'days'],
                        'label' => 'Wait 3 Days',
                        'position_x' => 400,
                        'position_y' => 310,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => '{{first_name}}, here\'s a special 20% off just for you! Use code: COMEBACK20'],
                        'label' => 'Send Special Offer',
                        'position_x' => 400,
                        'position_y' => 440,
                    ],
                ],
            ],

            // Birthday Greeting
            [
                'name' => 'Birthday Greeting',
                'slug' => 'birthday-greeting',
                'description' => 'Send automated birthday wishes with special offers',
                'category' => 'birthday',
                'trigger_type' => 'schedule',
                'trigger_config' => ['type' => 'birthday'],
                'icon' => 'ri-cake-2-line',
                'nodes' => [
                    [
                        'type' => 'trigger',
                        'action' => 'schedule',
                        'config' => ['type' => 'birthday'],
                        'label' => 'Birthday Trigger',
                        'position_x' => 400,
                        'position_y' => 50,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => 'Happy Birthday {{first_name}}! 🎂 Enjoy 25% off your next purchase with code: BDAY25'],
                        'label' => 'Send Birthday Wish',
                        'position_x' => 400,
                        'position_y' => 180,
                    ],
                ],
            ],

            // Appointment Reminder
            [
                'name' => 'Appointment Reminder',
                'slug' => 'appointment-reminder',
                'description' => 'Send reminders before scheduled appointments',
                'category' => 'reminder',
                'trigger_type' => 'webhook',
                'trigger_config' => [],
                'icon' => 'ri-calendar-check-line',
                'nodes' => [
                    [
                        'type' => 'trigger',
                        'action' => 'webhook',
                        'config' => [],
                        'label' => 'Appointment Created',
                        'position_x' => 400,
                        'position_y' => 50,
                    ],
                    [
                        'type' => 'wait',
                        'action' => 'wait_duration',
                        'config' => ['duration' => 24, 'unit' => 'hours'],
                        'label' => 'Wait Until 24h Before',
                        'position_x' => 400,
                        'position_y' => 180,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => 'Reminder: {{first_name}}, you have an appointment tomorrow. Reply YES to confirm or RESCHEDULE to change.'],
                        'label' => 'Send Reminder',
                        'position_x' => 400,
                        'position_y' => 310,
                    ],
                ],
            ],

            // Feedback Request
            [
                'name' => 'Feedback Request',
                'slug' => 'feedback-request',
                'description' => 'Request feedback after purchase or service',
                'category' => 'feedback',
                'trigger_type' => 'webhook',
                'trigger_config' => [],
                'icon' => 'ri-star-line',
                'nodes' => [
                    [
                        'type' => 'trigger',
                        'action' => 'webhook',
                        'config' => [],
                        'label' => 'Purchase Complete',
                        'position_x' => 400,
                        'position_y' => 50,
                    ],
                    [
                        'type' => 'wait',
                        'action' => 'wait_duration',
                        'config' => ['duration' => 3, 'unit' => 'days'],
                        'label' => 'Wait 3 Days',
                        'position_x' => 400,
                        'position_y' => 180,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => 'Hi {{first_name}}, how was your experience with us? Reply with 1-5 stars. Your feedback helps us improve!'],
                        'label' => 'Request Feedback',
                        'position_x' => 400,
                        'position_y' => 310,
                    ],
                ],
            ],

            // A/B Test Campaign
            [
                'name' => 'A/B Test Campaign',
                'slug' => 'ab-test-campaign',
                'description' => 'Split contacts into two groups to test different messages',
                'category' => 'sales',
                'trigger_type' => 'manual',
                'trigger_config' => [],
                'icon' => 'ri-split-cells-horizontal',
                'nodes' => [
                    [
                        'type' => 'trigger',
                        'action' => 'manual',
                        'config' => [],
                        'label' => 'Manual Trigger',
                        'position_x' => 400,
                        'position_y' => 50,
                    ],
                    [
                        'type' => 'condition',
                        'action' => 'random_split',
                        'config' => ['percentage' => 50],
                        'label' => '50/50 Split',
                        'position_x' => 400,
                        'position_y' => 180,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => 'Version A: Check out our amazing deals today!'],
                        'label' => 'Message A',
                        'position_x' => 250,
                        'position_y' => 350,
                    ],
                    [
                        'type' => 'action',
                        'action' => 'send_sms',
                        'config' => ['message' => 'Version B: Don\'t miss out on exclusive offers!'],
                        'label' => 'Message B',
                        'position_x' => 550,
                        'position_y' => 350,
                    ],
                ],
            ],
        ];
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
