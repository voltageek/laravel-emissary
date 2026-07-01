<?php

declare(strict_types=1);

use Emissary\AgentError;
use Emissary\Guards\OnboardingGuard;
use Emissary\Models\Conversation;
use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('allows when onboarding is disabled', function (): void {
    config()->set('emissary.onboarding.enabled', false);

    $guard = new OnboardingGuard();
    $result = $guard->beforeExecution('place_order', null, null);

    expect($result->allowed)->toBeTrue();
});

test('allows when conversation is in complete state', function (): void {
    config()->set('emissary.onboarding.enabled', true);

    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'user1',
        'onboarding_state' => 'complete',
    ]);

    $guard = new OnboardingGuard();
    $guard->setConversation($conversation);
    $result = $guard->beforeExecution('place_order', null, null);

    expect($result->allowed)->toBeTrue();
});

test('allows when conversation is in skipped state', function (): void {
    config()->set('emissary.onboarding.enabled', true);

    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'user2',
        'onboarding_state' => 'skipped',
    ]);

    $guard = new OnboardingGuard();
    $guard->setConversation($conversation);
    $result = $guard->beforeExecution('place_order', null, null);

    expect($result->allowed)->toBeTrue();
});

test('denies gated intents when onboarding is new', function (): void {
    config()->set('emissary.onboarding.enabled', true);
    config()->set('emissary.onboarding.gated_intents', ['*']);

    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'user3',
        'onboarding_state' => 'new',
    ]);

    $guard = new OnboardingGuard();
    $guard->setConversation($conversation);
    $result = $guard->beforeExecution('place_order', null, null);

    expect($result->allowed)->toBeFalse();
    expect($result->errorCode)->toBe(AgentError::ONBOARDING_REQUIRED);
});

test('denies when onboarding state is onboarding', function (): void {
    config()->set('emissary.onboarding.enabled', true);
    config()->set('emissary.onboarding.gated_intents', ['place_order', 'cancel_order']);

    $conversation = Conversation::create([
        'channel' => 'web',
        'channel_ref' => 'user4',
        'onboarding_state' => 'onboarding',
    ]);

    $guard = new OnboardingGuard();
    $guard->setConversation($conversation);
    $result = $guard->beforeExecution('place_order', null, null);

    expect($result->allowed)->toBeFalse();
});
