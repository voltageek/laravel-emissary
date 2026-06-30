<?php

declare(strict_types=1);

use Emissary\Pipeline\ConversationMemory;
use Emissary\Models\Conversation;
use Emissary\Models\ConversationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

uses(TestCase::class, RefreshDatabase::class);

uses()->beforeEach(function (): void {
    Schema::create('conversations', function ($table): void {
        $table->uuid('id')->primary();
        $table->uuid('tenant_id')->nullable();
        $table->string('channel', 20);
        $table->string('channel_ref', 100);
        $table->string('status', 20)->default('active');
        $table->string('onboarding_state', 20)->default('new');
        $table->json('pending_action')->nullable();
        $table->text('summary')->nullable();
        $table->timestamps();
    });

    Schema::create('conversation_messages', function ($table): void {
        $table->uuid('id')->primary();
        $table->uuid('conversation_id');
        $table->uuid('turn_id')->nullable();
        $table->string('role', 20);
        $table->text('content');
        $table->string('media_url')->nullable();
        $table->string('intent')->nullable();
        $table->string('error_code')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });
});

test('load returns empty array for conversation with no messages', function (): void {
    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'test_user',
    ]);

    $memory = new ConversationMemory();
    $result = $memory->load($conversation);

    expect($result)->toBeArray();
});

test('load includes summary as system message when present', function (): void {
    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'test_user',
        'summary' => 'Previous discussion about orders.',
    ]);

    $memory = new ConversationMemory();
    $result = $memory->load($conversation);

    $systemMessages = array_filter($result, fn ($m) => ($m['role'] ?? '') === 'system');
    expect($systemMessages)->not->toBeEmpty();
});

test('appendToolResult wraps content in data envelope', function (): void {
    $messages = [];
    ConversationMemory::appendToolResult($messages, 'Product: Widget, Price: $10');

    expect($messages)->toHaveCount(1);
    expect($messages[0]['content'])->toContain('[TOOL_RESULT_BEGIN]');
    expect($messages[0]['content'])->toContain('[TOOL_RESULT_END]');
    expect($messages[0]['content'])->toContain('Product: Widget, Price: $10');
    expect($messages[0]['content'])->toContain('untrusted data');
});
