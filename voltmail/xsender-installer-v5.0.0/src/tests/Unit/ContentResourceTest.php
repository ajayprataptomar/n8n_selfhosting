<?php

namespace Tests\Unit;

use App\Models\ContentResource;
use App\Models\User;
use App\Services\Content\ContentResourceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContentResourceTest extends TestCase
{
    protected ContentResourceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup clean SQLite test schema for users & content resources
        Schema::dropIfExists('content_generation_histories');
        Schema::dropIfExists('content_resources');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uid', 100)->nullable();
            $table->string('name')->default('Test User');
            $table->string('email')->unique();
            $table->string('password')->default('secret');
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });

        // Run our content_resources migration
        $migration1 = require __DIR__ . '/../../database/migrations/2026_10_12_000001_create_content_resources_table.php';
        $migration1->up();

        $migration2 = require __DIR__ . '/../../database/migrations/2026_10_12_000002_create_content_generation_histories_table.php';
        $migration2->up();

        $this->service = new ContentResourceService();
    }

    public function test_can_create_content_resource_for_tenant()
    {
        $user = User::create(['email' => 'tenant1@voltmail.test']);

        $resource = $this->service->createForUser($user->id, [
            'name'                => 'SaaS Profile',
            'company_name'        => 'NeuraVolt Technologies',
            'business_type'       => 'Cloud Platform',
            'company_description' => 'Autonomous cloud infrastructure & n8n hosting',
            'website'             => 'https://neuravolt.cloud',
            'industry'            => 'Enterprise DevOps',
            'target_audience'     => 'DevOps Leads and CTOs',
            'brand_voice'         => 'Authoritative & Technical',
            'products_services'   => [
                ['name' => 'NeuraVolt VPS', 'benefit' => 'Automated backup and sub-second scaling']
            ],
            'faqs'                => [
                ['question' => 'How does pricing work?', 'answer' => 'Predictable monthly billing with zero egress fees.']
            ],
            'status'              => 'active',
        ]);

        $this->assertInstanceOf(ContentResource::class, $resource);
        $this->assertEquals($user->id, $resource->user_id);
        $this->assertEquals('NeuraVolt Technologies', $resource->company_name);
        $this->assertTrue($resource->is_default); // First resource auto-marked default
        $this->assertNotEmpty($resource->uid);
        $this->assertIsArray($resource->products_services);
        $this->assertEquals('NeuraVolt VPS', $resource->products_services[0]['name']);
    }

    public function test_tenant_isolation_prevents_cross_tenant_access()
    {
        $tenantA = User::create(['email' => 'tenantA@voltmail.test']);
        $tenantB = User::create(['email' => 'tenantB@voltmail.test']);

        $resourceA = $this->service->createForUser($tenantA->id, [
            'name'         => 'Company A Profile',
            'company_name' => 'Company A Corp',
        ]);

        // Tenant A can access own resource
        $found = $this->service->getForUser($tenantA->id, $resourceA->id);
        $this->assertEquals($resourceA->id, $found->id);

        // Tenant B trying to access Tenant A's resource MUST throw AuthorizationException
        $this->expectException(AuthorizationException::class);
        $this->service->getForUser($tenantB->id, $resourceA->id);
    }

    public function test_tenant_isolation_in_listing()
    {
        $tenantA = User::create(['email' => 'tenantA_list@voltmail.test']);
        $tenantB = User::create(['email' => 'tenantB_list@voltmail.test']);

        $this->service->createForUser($tenantA->id, [
            'name'         => 'Resource A1',
            'company_name' => 'Company A',
        ]);
        $this->service->createForUser($tenantA->id, [
            'name'         => 'Resource A2',
            'company_name' => 'Company A',
        ]);

        $this->service->createForUser($tenantB->id, [
            'name'         => 'Resource B1',
            'company_name' => 'Company B',
        ]);

        $listA = $this->service->listForUser($tenantA->id);
        $listB = $this->service->listForUser($tenantB->id);

        $this->assertCount(2, $listA->items());
        $this->assertCount(1, $listB->items());
        $this->assertEquals('Resource B1', $listB->items()[0]->name);
    }

    public function test_can_update_resource_with_tenant_ownership()
    {
        $user = User::create(['email' => 'updater@voltmail.test']);

        $resource = $this->service->createForUser($user->id, [
            'name'         => 'Original Name',
            'company_name' => 'Original Corp',
        ]);

        $updated = $this->service->updateForUser($user->id, $resource->id, [
            'name'         => 'Updated Name',
            'company_name' => 'Updated Corp',
            'brand_voice'  => 'Friendly & Conversational',
        ]);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('Updated Corp', $updated->company_name);
        $this->assertEquals('Friendly & Conversational', $updated->brand_voice);
    }

    public function test_can_delete_resource_with_tenant_ownership()
    {
        $user = User::create(['email' => 'deleter@voltmail.test']);

        $resource = $this->service->createForUser($user->id, [
            'name'         => 'To Delete',
            'company_name' => 'Delete Corp',
        ]);

        $result = $this->service->deleteForUser($user->id, $resource->id);
        $this->assertTrue($result);
        $this->assertDatabaseMissing('content_resources', ['id' => $resource->id]);
    }
}
