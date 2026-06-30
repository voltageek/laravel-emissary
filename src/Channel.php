<?php

declare(strict_types=1);

namespace Emissary;

enum Channel: string
{
    case WhatsApp = 'whatsapp';
    case Telegram = 'telegram';
    case Web = 'web';
}
