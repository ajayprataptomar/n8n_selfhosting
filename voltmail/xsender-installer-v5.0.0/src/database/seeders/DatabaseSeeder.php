<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\LangSeeder;
use Database\Seeders\SettingsSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            LangSeeder::class,
            BlogSeeder::class,
            AdminSeeder::class,
            UsersSeeder::class,
            SettingsSeeder::class,
            TemplatesSeeder::class,
            AISettingsSeeder::class,
            SmsGatewaysSeeder::class,
            PricingPlansSeeder::class,
            OAuthClientsSeeder::class,
            SubscriptionsSeeder::class,
            PaymentMethodsSeeder::class,
            FrontendSectionsSeeder::class,
            OAuthPersonalAccessClientSeeder::class,
        ]);
    }
}
