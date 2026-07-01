<?php

return [
    'baseUrl' => getenv('JIGSAW_BASE_URL') ?: '',
    'production' => false,
    'siteName' => 'Emissary Docs',
    'siteDescription' => 'Agentic, multi-channel conversational AI for Laravel applications',
    'build' => [
        'source' => 'source',
        'destination' => getenv('JIGSAW_DEST') ?: 'build',
    ],
    'collections' => [],

    'navigation' => [
        [
            'title' => 'Getting Started',
            'items' => [
                ['label' => 'Getting Started', 'url' => '/', 'children' => []],
            ],
        ],
        [
            'title' => 'Core Concepts',
            'items' => [
                ['label' => 'Overview', 'url' => '/concepts', 'children' => []],
                ['label' => 'Intents', 'url' => '/concepts/intents', 'children' => []],
                ['label' => 'Tools', 'url' => '/concepts/tools', 'children' => []],
                ['label' => 'Guards', 'url' => '/concepts/guards', 'children' => []],
                ['label' => 'Pipeline', 'url' => '/concepts/pipeline', 'children' => []],
            ],
        ],
        [
            'title' => 'Guides',
            'items' => [
                ['label' => 'Tool Authoring', 'url' => '/guides/tool-authoring', 'children' => []],
                ['label' => 'Guard Authoring', 'url' => '/guides/guard-authoring', 'children' => []],
                ['label' => 'Onboarding & Consent', 'url' => '/guides/onboarding', 'children' => []],
                [
                    'label' => 'Channels',
                    'url' => '/guides/channels',
                    'children' => [
                        ['label' => 'Telegram', 'url' => '/guides/channels/telegram'],
                        ['label' => 'WhatsApp', 'url' => '/guides/channels/whatsapp'],
                        ['label' => 'Web Widget', 'url' => '/guides/channels/web'],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Reference',
            'items' => [
                ['label' => 'Configuration', 'url' => '/reference/config', 'children' => []],
                [
                    'label' => 'API',
                    'url' => '/reference/api',
                    'children' => [
                        ['label' => 'Contracts', 'url' => '/reference/api/contracts'],
                        ['label' => 'DTOs', 'url' => '/reference/api/dtos'],
                        ['label' => 'Attributes', 'url' => '/reference/api/attributes'],
                    ],
                ],
                ['label' => 'Artisan Commands', 'url' => '/reference/commands', 'children' => []],
            ],
        ],
        [
            'title' => 'Operations',
            'items' => [
                ['label' => 'Observability & Debugging', 'url' => '/operations/observability', 'children' => []],
                ['label' => 'Testing', 'url' => '/operations/testing', 'children' => []],
                ['label' => 'Migration', 'url' => '/operations/migration', 'children' => []],
            ],
        ],
    ],
];
