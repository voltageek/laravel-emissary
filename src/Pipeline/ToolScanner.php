<?php

declare(strict_types=1);

namespace Emissary\Pipeline;

use Emissary\Attributes\Tool;
use Emissary\Contracts\AgentToolProvider;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

class ToolScanner
{
    private const PHP_TO_JSON_TYPE = [
        'string' => 'string',
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'number',
        'double' => 'number',
        'bool' => 'string',
        'boolean' => 'string',
    ];

    public function scan(AgentToolProvider $provider): array
    {
        $definitions = [];
        $reflection = new ReflectionClass($provider);

        foreach ($reflection->getMethods() as $method) {
            if (! $method->isPublic() || $method->isStatic()) {
                continue;
            }

            $toolAttributes = $method->getAttributes(Tool::class);

            if (empty($toolAttributes)) {
                continue;
            }

            $tool = $toolAttributes[0]->newInstance();
            $methodName = $method->getName();
            $properties = [];
            $required = [];

            foreach ($method->getParameters() as $param) {
                $paramInfo = $this->buildParamSchema($param, $tool->params);

                $properties[$param->getName()] = $paramInfo;

                if ($paramInfo['required'] ?? true) {
                    $required[] = $param->getName();
                }
            }

            $this->validateParamKeys($tool->params, array_keys($properties), $methodName, $provider->pluginName());

            $definitions[$methodName] = [
                'definition' => [
                    'name' => $methodName,
                    'description' => $tool->description,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                    ],
                    'requires_confirmation' => $tool->requiresConfirmation,
                    'confirmation_template' => $tool->confirmationTemplate,
                    'intents' => $tool->intents,
                ],
                'handler' => [$provider, $methodName],
            ];
        }

        $escapeHatch = $provider->getToolDefinitions();

        foreach ($escapeHatch as $def) {
            $name = $def['name'];

            if (isset($definitions[$name])) {
                trigger_error(
                    "[AgentToolProvider] Tool '{$name}': getToolDefinitions() entry overrides"
                    . " #[Tool] method on '{$provider->pluginName()}'. Reflected entry discarded.",
                    E_USER_WARNING,
                );
            }

            $definitions[$name] = [
                'definition' => $def,
                'handler' => $def['handler'] ?? null,
            ];
        }

        return $definitions;
    }

    private function buildParamSchema(ReflectionParameter $param, array $paramsMeta): array
    {
        $type = $param->getType();
        $jsonType = 'string';
        $isRequired = ! $param->isOptional() && ! $param->allowsNull();

        if ($type instanceof ReflectionNamedType) {
            $phpType = $type->getName();

            if (isset(self::PHP_TO_JSON_TYPE[$phpType])) {
                $jsonType = self::PHP_TO_JSON_TYPE[$phpType];
            }

            if ($phpType === 'array' && ! $type->isBuiltin()) {
                $jsonType = 'object';
            }
        }

        $meta = $paramsMeta[$param->getName()] ?? [];
        $schema = [
            'type' => $meta['type'] ?? $jsonType,
            'required' => $meta['required'] ?? $isRequired,
        ];

        if (isset($meta['description'])) {
            $schema['description'] = $meta['description'];
        }

        if (isset($meta['enum'])) {
            $schema['enum'] = $meta['enum'];
        }

        if (($meta['type'] ?? $jsonType) === 'array' && isset($meta['items'])) {
            $schema['items'] = $meta['items'];
        }

        return $schema;
    }

    private function validateParamKeys(array $params, array $paramNames, string $methodName, string $pluginName): void
    {
        foreach (array_keys($params) as $key) {
            if (! in_array($key, $paramNames, true)) {
                $suggestion = $this->closest($key, $paramNames);

                throw new \RuntimeException(
                    "[AgentToolProvider] Tool '{$methodName}' on '{$pluginName}':"
                    . " params key '{$key}' does not match any parameter."
                    . ($suggestion ? " Did you mean '{$suggestion}'?" : ''),
                );
            }
        }
    }

    private function closest(string $target, array $candidates): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $distance = levenshtein($target, $candidate);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $bestDistance <= 3 ? $best : null;
    }
}
