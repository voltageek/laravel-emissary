<?php

declare(strict_types=1);

namespace Emissary\Contracts;

interface AgentToolProvider
{
    public function pluginName(): string;

    public function getIntents(): array;

    public function getIntentConfig(): array;

    public function getIntentClassificationHints(): array;

    public function getToolDefinitions(): array;

    public function getGuards(): array;

    public function getSystemPromptExtension(): string;

    public function getDocumentMappings(): array;

    public function isIntentSupported(string $intent, mixed $tenant): bool;
}
