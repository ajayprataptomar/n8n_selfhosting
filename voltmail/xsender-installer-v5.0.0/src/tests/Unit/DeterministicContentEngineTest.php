<?php

namespace Tests\Unit;

use App\Models\ContentGenerationHistory;
use App\Models\ContentResource;
use App\Models\User;
use App\Services\Content\DeterministicContentEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeterministicContentEngineTest extends TestCase
{
    protected DeterministicContentEngine $engine;
    protected User $user;
    protected ContentResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->engine = new DeterministicContentEngine();

        $this->user = User::create(['email' => 'engine_user@voltmail.test']);
        $this->resource = ContentResource::create([
            'user_id'             => $this->user->id,
            'name'                => 'NeuraVolt Engine Profile',
            'company_name'        => 'NeuraVolt Cloud',
            'business_type'       => 'Managed Automation Cloud',
            'company_description' => 'high-availability self-hosted n8n and workflow infrastructure',
            'website'             => 'https://neuravolt.cloud',
            'industry'            => 'Workflow Automation',
            'target_audience'     => 'Automation Engineers',
            'brand_voice'         => 'Technical & Direct',
            'products_services'   => [
                ['name' => 'Managed n8n Cloud', 'benefit' => '99.9% uptime with automated webhook scaling']
            ],
            'faqs'                => [
                ['question' => 'Can I use custom community nodes?', 'answer' => 'Yes, full Docker access with 1-click npm install.']
            ],
            'status'              => 'active',
            'is_default'          => true,
        ]);
    }

    public function test_generate_content_substitutes_resource_variables()
    {
        $result = $this->engine->generateContent($this->resource);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('template_id', $result);
        $this->assertArrayHasKey('content_fingerprint', $result);
        $this->assertArrayHasKey('variables_used', $result);
        $this->assertArrayHasKey('is_repeated', $result);
        $this->assertArrayHasKey('reuse_count', $result);

        $this->assertNotEmpty($result['subject']);
        $this->assertNotEmpty($result['body']);
        $this->assertEquals(64, strlen($result['content_fingerprint'])); // SHA-256
        $this->assertFalse($result['is_repeated']);
        $this->assertEquals(0, $result['reuse_count']);

        // Check that variables were populated
        $this->assertStringContainsString('NeuraVolt Cloud', $result['body']);
    }

    public function test_fingerprint_normalization_is_deterministic()
    {
        $subject1 = "  Quick Question Regarding NeuraVolt! ";
        $body1    = "<p>Hello, this is a test from NeuraVolt.</p>";

        $subject2 = "quick question regarding neuravolt!";
        $body2    = "Hello, this is a test from NeuraVolt.   ";

        $hash1 = $this->engine->computeFingerprint($subject1, $body1);
        $hash2 = $this->engine->computeFingerprint($subject2, $body2);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_records_usage_and_detects_repetition()
    {
        $first = $this->engine->generateContent($this->resource);
        $this->assertFalse($first['is_repeated']);

        // Record usage of this content
        $history = $this->engine->recordUsage(
            $this->user->id,
            $this->resource->id,
            null,
            $first,
            'dispatch_slot_1'
        );

        $this->assertInstanceOf(ContentGenerationHistory::class, $history);
        $this->assertEquals(1, $history->reuse_count);
        $this->assertEquals($first['content_fingerprint'], $history->content_fingerprint);

        // Record a second time
        $history2 = $this->engine->recordUsage(
            $this->user->id,
            $this->resource->id,
            null,
            $first,
            'dispatch_slot_2'
        );

        $this->assertEquals(2, $history2->reuse_count);
    }

    public function test_engine_selects_unrepeated_templates_before_repeating()
    {
        // Generate and record 1st content
        $content1 = $this->engine->generateContent($this->resource);
        $this->engine->recordUsage($this->user->id, $this->resource->id, null, $content1);

        // Generate 2nd content: engine should pick a DIFFERENT candidate template that has 0 reuse
        $content2 = $this->engine->generateContent($this->resource);

        $this->assertNotEquals($content1['template_id'], $content2['template_id']);
        $this->assertFalse($content2['is_repeated']);
    }
}
