<?php

declare(strict_types=1);

use Emissary\WahaSessionState;

test('fromApiResponse maps STOPPED to Stopped', function (): void {
    expect(WahaSessionState::fromApiResponse('STOPPED'))->toBe(WahaSessionState::Stopped);
});

test('fromApiResponse maps STARTING to Starting', function (): void {
    expect(WahaSessionState::fromApiResponse('STARTING'))->toBe(WahaSessionState::Starting);
});

test('fromApiResponse maps SCAN_QR_CODE to ScanQrCode', function (): void {
    expect(WahaSessionState::fromApiResponse('SCAN_QR_CODE'))->toBe(WahaSessionState::ScanQrCode);
});

test('fromApiResponse maps WORKING to Working', function (): void {
    expect(WahaSessionState::fromApiResponse('WORKING'))->toBe(WahaSessionState::Working);
});

test('fromApiResponse maps FAILED to Failed', function (): void {
    expect(WahaSessionState::fromApiResponse('FAILED'))->toBe(WahaSessionState::Failed);
});

test('fromApiResponse throws ValueError for unknown status', function (): void {
    expect(fn () => WahaSessionState::fromApiResponse('UNKNOWN'))
        ->toThrow(\ValueError::class);
});
