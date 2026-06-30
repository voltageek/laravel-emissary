<?php

declare(strict_types=1);

use Emissary\Contracts\ConfirmationGate;
use Emissary\Models\Conversation;
use Emissary\Pipeline\DatabaseConfirmationGate;
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
});

test('propose stores pending action on conversation', function (): void {
    $gate = new DatabaseConfirmationGate();
    $gate->setTurnContext('turn-1', 'conv-1');

    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'test_user',
    ]);

    $action = ['tool_name' => 'placeOrder', 'fields' => ['product_id' => 42, 'quantity' => 3]];
    $message = $gate->propose($conversation, $action);

    $conversation->refresh();
    expect($conversation->pending_action)->not->toBeNull();
    expect($conversation->pending_action['tool_name'])->toBe('placeOrder');
});

test('execute returns pending action and clears it', function (): void {
    $gate = new DatabaseConfirmationGate();
    $gate->setTurnContext('turn-2', 'conv-2');

    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'test_user',
    ]);

    $action = ['tool_name' => 'placeOrder', 'fields' => ['product_id' => 42], 'proposed_at' => now()->toIso8601String()];
    $conversation->update(['pending_action' => $action]);

    $result = $gate->execute($conversation);

    expect($result['tool_name'])->toBe('placeOrder');
    $conversation->refresh();
    expect($conversation->pending_action)->toBeNull();
});

test('cancel clears pending action', function (): void {
    $gate = new DatabaseConfirmationGate();
    $gate->setTurnContext('turn-3', 'conv-3');

    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'test_user',
    ]);

    $conversation->update(['pending_action' => ['tool_name' => 'delete', 'proposed_at' => now()->toIso8601String()]]);

    $gate->cancel($conversation);

    $conversation->refresh();
    expect($conversation->pending_action)->toBeNull();
});

test('isExpired returns true after confirmation timeout', function (): void {
    config()->set('emissary.confirmation_timeout_seconds', 900);

    $gate = new DatabaseConfirmationGate();
    $gate->setTurnContext('turn-4', 'conv-4');

    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'expired_user',
    ]);

    $conversation->pending_action = [
        'tool_name' => 'delete',
        'proposed_at' => now()->subMinutes(20)->toIso8601String(),
    ];
    $conversation->save();

    expect($gate->isExpired($conversation))->toBeTrue();
});

test('isExpired returns false for recent pending action', function (): void {
    $gate = new DatabaseConfirmationGate();
    $gate->setTurnContext('turn-5', 'conv-5');

    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'test_user',
    ]);

    $conversation->update([
        'pending_action' => [
            'tool_name' => 'delete',
            'proposed_at' => now()->subSeconds(60)->toIso8601String(),
        ],
    ]);

    expect($gate->isExpired($conversation))->toBeFalse();
});
