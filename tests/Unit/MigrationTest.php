<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

uses()->beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('conversations')) {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->assertExitCode(0);
    }
});

test('conversations table has expected columns', function (): void {
    expect(Schema::hasTable('conversations'))->toBeTrue();
    expect(Schema::hasColumns('conversations', [
        'id', 'tenant_id', 'channel', 'channel_ref', 'status',
        'onboarding_state', 'pending_action', 'summary', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('conversation_messages table has expected columns', function (): void {
    expect(Schema::hasTable('conversation_messages'))->toBeTrue();
    expect(Schema::hasColumns('conversation_messages', [
        'id', 'conversation_id', 'turn_id', 'role', 'content',
        'media_url', 'intent', 'error_code', 'created_at',
    ]))->toBeTrue();
});

test('agent_events table has expected columns', function (): void {
    expect(Schema::hasTable('agent_events'))->toBeTrue();
    expect(Schema::hasColumns('agent_events', [
        'id', 'turn_id', 'conversation_id', 'tenant_id', 'kind',
        'model', 'input_tokens', 'output_tokens', 'latency_ms', 'intent',
        'checkpoint', 'guard', 'tool_name', 'result', 'error_code',
        'error', 'payload', 'conversation_message_id', 'created_at',
    ]))->toBeTrue();
});

test('tool_invocations table has expected columns', function (): void {
    expect(Schema::hasTable('tool_invocations'))->toBeTrue();
    expect(Schema::hasColumns('tool_invocations', [
        'id', 'turn_id', 'conversation_id', 'tenant_id', 'tool_name',
        'arguments', 'result_summary', 'duration_ms', 'success',
        'validation_error', 'triggered_via', 'agent_event_id', 'created_at',
    ]))->toBeTrue();
});

test('channel_identity_links table exists', function (): void {
    expect(Schema::hasTable('channel_identity_links'))->toBeTrue();
    expect(Schema::hasColumns('channel_identity_links', [
        'id', 'user_id', 'channel', 'channel_ref', 'verified_at', 'created_at',
    ]))->toBeTrue();
});

test('llm_payloads table has expected columns', function (): void {
    expect(Schema::hasTable('llm_payloads'))->toBeTrue();
    expect(Schema::hasColumns('llm_payloads', [
        'id', 'agent_event_id', 'turn_id', 'request_messages',
        'tools_sent', 'response', 'created_at',
    ]))->toBeTrue();
});

test('agent_spans table has expected columns', function (): void {
    expect(Schema::hasTable('agent_spans'))->toBeTrue();
    expect(Schema::hasColumns('agent_spans', [
        'id', 'turn_id', 'conversation_id', 'stage', 'duration_ms', 'created_at',
    ]))->toBeTrue();
});

test('cost_ledgers table has expected columns', function (): void {
    expect(Schema::hasTable('cost_ledgers'))->toBeTrue();
    expect(Schema::hasColumns('cost_ledgers', [
        'id', 'conversation_id', 'tenant_id', 'month',
        'input_tokens', 'output_tokens', 'cost_usd', 'created_at',
    ]))->toBeTrue();
});

test('channel_configs table has expected columns', function (): void {
    expect(Schema::hasTable('channel_configs'))->toBeTrue();
    expect(Schema::hasColumns('channel_configs', [
        'id', 'tenant_id', 'channel', 'label', 'credentials', 'status',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('user_onboardings table has expected columns', function (): void {
    expect(Schema::hasTable('user_onboardings'))->toBeTrue();
    expect(Schema::hasColumns('user_onboardings', [
        'id', 'user_id', 'conversation_id', 'status', 'profile',
        'consent_at', 'consent_version', 'created_at', 'completed_at',
    ]))->toBeTrue();
});
