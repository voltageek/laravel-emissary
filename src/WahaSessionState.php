<?php

declare(strict_types=1);

namespace Emissary;

enum WahaSessionState: string
{
    case Stopped = 'STOPPED';
    case Starting = 'STARTING';
    case ScanQrCode = 'SCAN_QR_CODE';
    case Working = 'WORKING';
    case Failed = 'FAILED';

    public static function fromApiResponse(string $status): self
    {
        return match ($status) {
            'STOPPED'      => self::Stopped,
            'STARTING'     => self::Starting,
            'SCAN_QR_CODE' => self::ScanQrCode,
            'WORKING'      => self::Working,
            'FAILED'       => self::Failed,
            default        => throw new \ValueError("Unknown WAHA session status: {$status}"),
        };
    }
}
