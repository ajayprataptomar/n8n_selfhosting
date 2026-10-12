<?php

namespace Tests\Feature;

use App\Models\ContentResource;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContentResourceControllerTest extends TestCase
{
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\SoftwareVerification::class,
            \App\Http\Middleware\PurchaseValidation::class,
            \App\Http\Middleware\CheckUserStatus::class,
            \App\Http\Middleware\AuthorizationMiddleware::class,
            \App\Http\Middleware\VersionUpgrade::class,
            \App\Http\Middleware\Sanitization::class,
        ]);

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

        $migration1 = require __DIR__ . '/../../database/migrations/2026_10_12_000001_create_content_resources_table.php';
        $migration1->up();

        $migration2 = require __DIR__ . '/../../database/migrations/2026_10_12_000002_create_content_generation_histories_table.php';
        $migration2->up();

        $this->userA = User::create(['email' => 'controller_a@voltmail.test']);
        $this->userB = User::create(['email' => 'controller_b@voltmail.test']);
    }

    public function test_api_store_creates_resource_with_json_response()
    {
        $response = $this->actingAs($this->userA)->postJson(route('user.content-resource.store'), [
            'name'                => 'API Profile',
            'company_name'        => 'NeuraVolt API',
            'business_type'       => 'Cloud API',
            'company_description' => 'Scalable cloud infrastructure',
            'website'             => 'https://neuravolt.cloud',
            'industry'            => 'Cloud Services',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
        ]);
        $this->assertDatabaseHas('content_resources', [
            'user_id'      => $this->userA->id,
            'company_name' => 'NeuraVolt API',
        ]);
    }

    public function test_api_show_returns_resource_for_owner()
    {
        $resource = ContentResource::create([
            'user_id'      => $this->userA->id,
            'name'         => 'Profile A',
            'company_name' => 'Company A',
        ]);

        $response = $this->actingAs($this->userA)->getJson(route('user.content-resource.show', $resource->id));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data'   => [
                'resource' => [
                    'id'   => $resource->id,
                    'name' => 'Profile A',
                ]
            ]
        ]);
    }

    public function test_api_show_blocks_cross_tenant_access()
    {
        $resource = ContentResource::create([
            'user_id'      => $this->userA->id,
            'name'         => 'Private A',
            'company_name' => 'Company A',
        ]);

        // User B tries to view User A's resource
        $this->actingAs($this->userB);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->withoutExceptionHandling()->getJson(route('user.content-resource.show', $resource->id));
    }

    public function test_api_preview_generation_returns_rendered_content()
    {
        $resource = ContentResource::create([
            'user_id'             => $this->userA->id,
            'name'                => 'Preview Profile',
            'company_name'        => 'NeuraVolt Engine',
            'company_description' => 'Automated workflow execution',
            'industry'            => 'Enterprise AI',
        ]);

        $response = $this->actingAs($this->userA)->postJson(route('user.content-resource.preview', $resource->id), [
            'record_usage' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'subject',
                'body',
                'template_id',
                'content_fingerprint',
                'variables_used',
                'is_repeated',
                'history_id',
            ]
        ]);

        $this->assertDatabaseHas('content_generation_histories', [
            'user_id'             => $this->userA->id,
            'content_resource_id' => $resource->id,
        ]);
    }

    public function test_api_destroy_deletes_resource()
    {
        $resource = ContentResource::create([
            'user_id'      => $this->userA->id,
            'name'         => 'To Delete',
            'company_name' => 'Delete Co',
        ]);

        $response = $this->actingAs($this->userA)->deleteJson(route('user.content-resource.destroy', $resource->id));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('content_resources', ['id' => $resource->id]);
    }
}
