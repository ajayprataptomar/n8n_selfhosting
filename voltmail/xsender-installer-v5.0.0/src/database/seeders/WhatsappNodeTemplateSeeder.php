<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Template;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Str;

class WhatsappNodeTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $templates = [
            [
                'name' => 'Welcome Message',
                'template_data' => [
                    'message' => "Hi {{name}}, welcome! 👋\n\nWe're excited to have you here. If you have any questions, feel free to reach out!",
                    'variables' => ['name'],
                ],
            ],
            [
                'name' => 'Order Confirmation',
                'template_data' => [
                    'message' => "Hello {{name}}! 🎉\n\nYour order has been confirmed and is being processed. We'll notify you once it ships.\n\nThank you for your purchase!",
                    'image_url' => 'https://via.placeholder.com/800x400/4CAF50/FFFFFF?text=Order+Confirmed',
                    'variables' => ['name'],
                    'buttons' => [
                        [
                            'type' => 'url',
                            'text' => 'Track Order',
                            'url' => 'https://example.com/track',
                        ],
                        [
                            'type' => 'phone',
                            'text' => 'Call Support',
                            'phone' => '+1234567890',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Appointment Reminder',
                'template_data' => [
                    'message' => "Hi {{name}}, 📅\n\nThis is a friendly reminder about your appointment scheduled for tomorrow at 10:00 AM.\n\nSee you soon!",
                    'variables' => ['name'],
                    'buttons' => [
                        [
                            'type' => 'quick_reply',
                            'text' => 'Confirm',
                        ],
                        [
                            'type' => 'quick_reply',
                            'text' => 'Reschedule',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Shipping Update',
                'template_data' => [
                    'message' => "Good news, {{name}}! 📦\n\nYour package is on the way and should arrive within 2-3 business days.\n\nTracking details have been sent to {{phone}}.",
                    'image_url' => 'https://via.placeholder.com/800x400/2196F3/FFFFFF?text=Package+Shipped',
                    'variables' => ['name', 'phone'],
                    'buttons' => [
                        [
                            'type' => 'url',
                            'text' => 'Track Shipment',
                            'url' => 'https://example.com/track',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Promotional Offer',
                'template_data' => [
                    'message' => "Hey {{name}}! 🎁\n\nSpecial offer just for you: Get 20% OFF on your next purchase!\n\nUse code: SAVE20 at checkout.\nOffer valid until the end of the month.",
                    'image_url' => 'https://via.placeholder.com/800x400/FF5722/FFFFFF?text=20%25+OFF',
                    'variables' => ['name'],
                    'buttons' => [
                        [
                            'type' => 'url',
                            'text' => 'Shop Now',
                            'url' => 'https://example.com/shop',
                        ],
                        [
                            'type' => 'quick_reply',
                            'text' => 'More Details',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($templates as $template) {
            // Check if template already exists
            $exists = Template::where('name', $template['name'])
                ->where('channel', ChannelTypeEnum::WHATSAPP)
                ->whereNull('cloud_id')
                ->whereNull('user_id')
                ->first();

            if (!$exists) {
                Template::create([
                    'uid' => Str::random(32),
                    'user_id' => null,
                    'channel' => ChannelTypeEnum::WHATSAPP->value,
                    'name' => $template['name'],
                    'slug' => Str::slug($template['name']),
                    'template_data' => $template['template_data'],
                    'status' => 'active',
                    'plugin' => false,
                    'global' => false,
                    'default' => false,
                    'cloud_id' => null, // This makes it a Node template
                ]);
            }
        }

        $this->command->info('WhatsApp Node templates seeded successfully!');
    }
}
