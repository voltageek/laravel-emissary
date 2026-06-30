<?php

declare(strict_types=1);

namespace Emissary\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Tool
{
    public function __construct(
        public string $description,
        public bool $requiresConfirmation = false,
        public ?string $confirmationTemplate = null,
        public array $intents = [],
        public array $params = [],
    ) {}
}
