<?php

declare(strict_types=1);

use Emissary\GuardResult;

test('allow returns a GuardResult with allowed true', function (): void {
    $result = GuardResult::allow();

    expect($result->allowed)->toBeTrue();
    expect($result->userMessage)->toBeNull();
    expect($result->errorCode)->toBeNull();
});

test('deny returns a GuardResult with allowed false and correct message', function (): void {
    $result = GuardResult::deny('Access denied', 'auth.unauthorized');

    expect($result->allowed)->toBeFalse();
    expect($result->userMessage)->toBe('Access denied');
    expect($result->errorCode)->toBe('auth.unauthorized');
});

test('deny defaults error code to null when omitted', function (): void {
    $result = GuardResult::deny('Blocked');

    expect($result->allowed)->toBeFalse();
    expect($result->userMessage)->toBe('Blocked');
    expect($result->errorCode)->toBeNull();
});
