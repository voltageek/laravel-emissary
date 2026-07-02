<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    */

    'default_model' => env('AGENT_DEFAULT_MODEL', 'google/gemma-4-31b-it'),
    'complex_model' => env('AGENT_COMPLEX_MODEL', 'google/gemma-4-31b-it'),
    'vision_model'  => env('AGENT_VISION_MODEL',  'google/gemma-4-31b-it'),

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    | Per-million token rates. Only needed if the UpdateCostLedger listener is
    | registered (opt-in).
    */

    'model_rates' => [
        'google/gemma-4-31b-it' => ['input_per_m' => 0.12, 'output_per_m' => 0.35],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Gateway (OpenRouter / OpenAI-compatible)
    |--------------------------------------------------------------------------
    */

    'openrouter' => [
        'api_key'  => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'timeout'  => 60,
        'retries'  => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Routing
    |--------------------------------------------------------------------------
    | complex_intents: intents routed to the complex model.
    | confidence_escalation_threshold: below this → escalate to complex model.
    */

    'complex_intents'                => ['query_financials', 'unknown'],
    'confidence_escalation_threshold' => 0.5,

    /*
    |--------------------------------------------------------------------------
    | Intent Fallback
    |--------------------------------------------------------------------------
    | Below this threshold → the user receives a clarification response.
    */

    'intent_confidence_threshold' => 0.4,

    /*
    |--------------------------------------------------------------------------
    | Base Intents → Tools Mapping
    |--------------------------------------------------------------------------
    | Extended by plugins via AgentToolProvider::getIntentConfig().
    */

    'intents' => [
        // 'record_sale' => ['model' => 'default', 'tools' => ['record_transaction']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | Enforced by the built-in RateLimitGuard (beforeIntent checkpoint).
    */

    'rate_limit' => [
        'per_minute' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    | Intents that require an authenticated user.
    | Enforced by AuthenticatedUserGuard (beforeExecution checkpoint).
    */

    'require_auth_intents' => [],

    /*
    |--------------------------------------------------------------------------
    | Conversation Limits
    |--------------------------------------------------------------------------
    */

    'confirmation_timeout_seconds' => 900,   // 15 min
    'max_conversation_turns'       => 24,
    'max_tool_call_rounds'         => 5,

    /*
    |--------------------------------------------------------------------------
    | Memory & Session Management
    |--------------------------------------------------------------------------
    */

    'memory' => [
        'activity_gap_minutes' => 30,
        'token_budget'         => 4096,
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Configuration
    |--------------------------------------------------------------------------
    | Single-tenant apps configure credentials here (read by
    | ConfigChannelCredentialStore). Multi-tenant apps swap the store binding
    | to EncryptedChannelCredentialStore and provision via
    | `emissary:channel:add`.
    */

    'webhook_path' => 'webhooks',

    'channels' => [
        'whatsapp' => [
            'backend'        => env('WHATSAPP_BACKEND', 'waha'),
            'adapter'        => \Emissary\Channels\WahaWhatsAppAdapter::class,
            'waha_api_url'   => env('WAHA_API_URL', 'http://localhost:3000'),
            'waha_api_key'   => env('WAHA_API_KEY'),
            'waha_session'   => env('WAHA_SESSION', 'default'),
            'waha_hmac_key'  => env('WAHA_HMAC_KEY'),
            'waha_version'   => env('WAHA_VERSION', 'free'),
            'access_token'   => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id'=> env('WHATSAPP_PHONE_NUMBER_ID'),
            'app_secret'     => env('WHATSAPP_APP_SECRET'),
            'verify_token'   => env('WHATSAPP_VERIFY_TOKEN'),
        ],
        'telegram' => [
            'adapter'      => Emissary\Channels\TelegramAdapter::class,
            'bot_token'    => env('TELEGRAM_BOT_TOKEN'),
            'secret_token' => env('TELEGRAM_SECRET_TOKEN'),
        ],
        'web' => [
            'adapter' => Emissary\Channels\WebChatAdapter::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Credential Store Seam
    |--------------------------------------------------------------------------
    | Default reads the channels block above. Swap for
    | EncryptedChannelCredentialStore for DB-backed, per-tenant provisioning.
    */

    'channel_credential_store' => Emissary\Channels\ConfigChannelCredentialStore::class,

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    | User-facing messages per AgentError code. Overridable per locale.
    */

    'error_messages' => [
        'guard.denied'            => 'Sorry, you\'re not able to do that.',
        'auth.unauthenticated'    => 'You need to be logged in to do that.',
        'auth.unauthorized'       => 'You don\'t have permission to do that.',
        'intent.low_confidence'   => 'I\'m not sure I understood that — could you rephrase?',
        'intent.unknown'          => 'I can help with several things. What would you like to do?',
        'tool.execution_failed'   => 'Something went wrong processing your request. Please try again.',
        'tool.invalid_arguments'  => 'I couldn\'t complete that with the details given. Could you rephrase?',
        'security.jailbreak'      => 'I can\'t help with that.',
        'cost.limit_exceeded'     => 'You\'ve hit the usage limit for this conversation. Please start a new one.',
        'onboarding.required'     => 'Let\'s get you set up before we do that — just say "start".',
        'agent.max_rounds'        => 'I\'ve reached my step limit. Please try a simpler request.',
        'llm.timeout'             => 'I\'m taking too long to respond. Please try again.',
        'llm.rate_limited'        => 'I\'m temporarily unavailable. Please try again shortly.',
        'llm.error'               => 'I encountered an error. Please try again.',
        'conversation.max_turns'  => 'We\'ve reached the limit for this conversation. Start a new one?',
        'channel.delivery_failed' => 'I couldn\'t deliver that message. Please try again.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Alerts
    |--------------------------------------------------------------------------
    | per_conversation_max_usd is enforced by CostCapGuard (beforeIntent).
    */

    'cost_alerts' => [
        'per_conversation_max_usd' => 0.10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'security' => [
        'jailbreak' => [
            'enabled' => true,
            'model'   => null,
        ],
        'tool_result_wrap'      => true,
        'require_webhook_verify'=> true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    | Enforced by `php artisan emissary:prune`.
    */

    'retention' => [
        'message_ttl_days' => 90,
        'event_ttl_days'   => 90,
        'payload_ttl_days' => 30,
        'span_ttl_days'    => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    */

    'observability' => [
        'trace_guard_allows'   => false,
        'capture_llm_payloads' => false,
        'capture_trace_spans'  => false,
        'otel' => ['enabled' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Onboarding
    |--------------------------------------------------------------------------
    | Opt-in. Enable to activate the first-contact welcome → profile → consent
    | flow for new users.
    */

    'onboarding' => [
        'enabled'         => false,
        'mode'            => 'hybrid',
        'welcome_message' => 'Hi! I can help with a few things — let\'s get you set up first.',
        'fields'          => ['name', 'email'],
        'field_map'       => [],
        'require_consent' => true,
        'consent_text'    => 'Our assistant uses an AI service; messages are processed to respond. Continue?',
        'consent_version' => '1.0',
        'gated_intents'   => ['*'],
        'guest_role'      => 'guest',
    ],

];
