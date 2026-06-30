<?php

declare(strict_types=1);

use Emissary\TransactionResult;

test('ok returns success true with reference and message', function (): void {
    $result = TransactionResult::ok('order_123', 'Order placed successfully.');

    expect($result->success)->toBeTrue();
    expect($result->referenceId)->toBe('order_123');
    expect($result->message)->toBe('Order placed successfully.');
});

test('ok defaults message to null', function (): void {
    $result = TransactionResult::ok('ref_456');

    expect($result->success)->toBeTrue();
    expect($result->referenceId)->toBe('ref_456');
    expect($result->message)->toBeNull();
});

test('fail returns success false with message', function (): void {
    $result = TransactionResult::fail('Something went wrong.');

    expect($result->success)->toBeFalse();
    expect($result->referenceId)->toBeNull();
    expect($result->message)->toBe('Something went wrong.');
});
