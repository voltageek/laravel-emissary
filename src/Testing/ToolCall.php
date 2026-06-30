<?php

declare(strict_types=1);

namespace Emissary\Testing;

readonly class ToolCall
{
    public function __construct(
        public string $name,
        public array $arguments = [],
    ) {}

    public static function make(string $name, array $arguments = []): self
    {
        return new self(name: $name, arguments: $arguments);
    }
}
