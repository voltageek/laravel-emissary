# Configuration & Wiring

> config/agent.php, service-provider bindings, plugin registration.

---

## Configuration Reference

```php
return [
    // Model configuration
    'default_model' => env('AGENT_DEFAULT_MODEL', 'google/gemma-4-31b-it'),
    'complex_model' => env('AGENT_COMPLEX_MODEL', 'google/gemma-4-31b-it'),
    'vision_model'  => env('AGENT_VISION_MODEL',  'google/gemma-4-31b-it'),

    // Cost tracking (per million tokens) — only needed if UpdateCostLedger listener is used
    'model_rates' => [
        'google/gemma-4-31b-it' => ['input_per_m' => 0.12, 'output_per_m' => 0.35],
    ],

    // LLM gateway
    'openrouter' => [
        'api_key'  => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'timeout'  => 60,
        'retries'  => 2,
    ],

    // Model routing
    'complex_intents'                => ['query_financials', 'unknown'],
    'confidence_escalation_threshold' => 0.5,  // below this → complex_model

    // Intent fallback
    'intent_confidence_threshold' => 0.4,  // below this → clarification response

    // Base intent → tools mapping (extended by plugins)
    'intents' => [
        'record_sale' => ['model' => 'default', 'tools' => ['record_transaction']],
        // ...
    ],

    // Rate limiting (enforced by built-in RateLimitGuard)
    'rate_limit' => [
        'per_minute' => 10,
    ],

    // Intents that require an authenticated user (enforced by AuthenticatedUserGuard)
    'require_auth_intents' => [],

    // Conversation limits
    'confirmation_timeout_seconds' => 900,   // 15 min
    'max_conversation_turns'       => 24,
    'max_tool_call_rounds'         => 5,

    // Memory & session management
    'memory' => [
        'activity_gap_minutes' => 30,
        'token_budget'         => 4096,
    ],

    // Channel adapters + credentials (read by ConfigChannelCredentialStore).
    // Multi-tenant apps swap the store binding to EncryptedChannelCredentialStore
    // and provision via `agent:channel:add` instead of these env keys.
    'webhook_path' => 'webhooks',   // route prefix; absolute URL uses APP_URL

    'channels' => [
        'whatsapp' => [
            'adapter'        => WhatsAppAdapter::class,
            'access_token'   => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id'=> env('WHATSAPP_PHONE_NUMBER_ID'),
            'app_secret'     => env('WHATSAPP_APP_SECRET'),       // verifySecret (HMAC)
            'verify_token'   => env('WHATSAPP_VERIFY_TOKEN'),     // handshakeToken (GET handshake)
        ],
        'telegram' => [
            'adapter'      => TelegramAdapter::class,
            'bot_token'    => env('TELEGRAM_BOT_TOKEN'),          // accessToken
            'secret_token' => env('TELEGRAM_SECRET_TOKEN'),       // verifySecret (header)
        ],
        'web' => [
            'adapter'     => WebChatAdapter::class,
            // verifySecret derived from CSRF key / session — no external credentials
        ],
    ],

    // Credential store seam (see ChannelCredentialStore). Default reads the block above.
    'channel_credential_store' => ConfigChannelCredentialStore::class,

    // Error messages shown to users (overridable per locale)
    'error_messages' => [
        'guard.denied'            => 'Sorry, you\'re not able to do that.',
        'auth.unauthenticated'    => 'You need to be logged in to do that.',
        'auth.unauthorized'       => 'You don\'t have permission to do that.',
        'intent.low_confidence'   => 'I\'m not sure I understood that — could you rephrase?',
        'intent.unknown'          => 'I can help with [list your intents here]. What would you like to do?',
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
    ],

    // Cost alerts — per_conversation_max_usd is enforced by CostCapGuard
    'cost_alerts' => [
        'per_conversation_max_usd' => 0.10,
    ],

    // Security (see "Security" section)
    'security' => [
        'jailbreak' => [
            'enabled' => true,            // JailbreakDetectionGuard on by default
            'model'   => null,            // optional model-based classifier; null = heuristic only
        ],
        'tool_result_wrap'      => true,  // wrap tool output in a data envelope (indirect injection)
        'require_webhook_verify'=> true,  // hard-fail (401) when ChannelAdapter::verify() is false
    ],

    // Data retention — enforced by `php artisan agent:prune`
    'retention' => [
        'message_ttl_days' => 90,
        'event_ttl_days'   => 90,
        'payload_ttl_days' => 30,   // llm_payloads (opt-in capture)
        'span_ttl_days'    => 14,   // agent_spans (opt-in capture)
    ],

    // Observability (see "Events & Observability")
    'observability' => [
        'trace_guard_allows'   => false, // emit GuardDecision on allow too (verbose)
        'capture_llm_payloads' => false, // store full prompts/responses for replay (storage heavy)
        'capture_trace_spans'  => false, // per-stage latency spans
        'otel' => ['enabled' => false],  // future OTel exporter listener (not built in v2.3)
    ],

    // User onboarding (see "User Onboarding") — opt-in
    'onboarding' => [
        'enabled'         => false,                  // master switch; existing apps see no change
        'mode'            => 'hybrid',               // hybrid|web_centric|channel_first (governs guest creation)
        'welcome_message' => 'Hi! I can help with a few things — let\'s get you set up first.',
        'fields'          => ['name', 'email'],      // profile fields collected by start_onboarding
        'field_map'       => [],                     // collected field => User attribute (optional)
        'require_consent' => true,
        'consent_text'    => 'Our assistant uses an AI service; messages are processed to respond. Continue?',
        'consent_version' => '1.0',
        'gated_intents'   => ['*'],                  // intents blocked until onboarding complete
        'guest_role'      => 'guest',
    ],
];
```

---

## Plugin Registration Flow

```
1. Plugin ServiceProvider::boot()
   → $this->app->tag([MyPluginProvider::class], 'agent.providers')

2. AppServiceProvider::boot() → bootAgentProviders() (in $app->booted() callback)
   For each tagged AgentToolProvider:
     → ToolRegistry::registerProvider($provider)
         │  └─ ToolScanner::scan($provider)
         │      ├─ reflect #[Tool] methods → schemas + handlers
         │      ├─ merge getToolDefinitions() escape hatch (wins on clash)
         │      └─ validate params keys → throw on mismatch
     → GuardRegistry::register(...$provider->getGuards()) // guards
     → IntentRouter::registerIntents($provider->getIntents())
     → IntentRouter::registerClassificationHints($provider->getClassificationHints())
     → ConfirmationGate::registerTemplates(...)   // sourced from reflected confirmationTemplate + defs

3. TaskAgent::buildSystemPrompt()
   → Iterates tagged providers filtered by $tenant->available_plugins (or all if no tenancy)
   → Appends each getSystemPromptExtension() to system prompt
```

---

## Service Provider Bindings

```php
// Singletons (state preserved across requests)
$app->singleton(IntentRouter::class);
$app->singleton(ToolRegistry::class);
$app->singleton(GuardRegistry::class);

// Tenancy — swap NullTenancyResolver for your own implementation
$app->bind(TenancyResolver::class, NullTenancyResolver::class);

// Channel identity — resolves the ?Authenticatable user behind a message.
// Mode-gated: GuestCreatingChannelIdentityResolver when onboarding.mode is
// channel_first|hybrid (creates guests on first chat contact); AuthChannelIdentityResolver otherwise.
$app->bind(ChannelIdentityResolver::class, match (config('agent.onboarding.mode')) {
    'channel_first', 'hybrid' => GuestCreatingChannelIdentityResolver::class,
    default                    => AuthChannelIdentityResolver::class,
});

// Channel credentials — default reads config/env; swap for EncryptedChannelCredentialStore (DB-backed)
$app->bind(ChannelCredentialStore::class, config('agent.channel_credential_store'));

// Interface → implementation
$app->bind(ConfirmationGate::class, DatabaseConfirmationGate::class);

// Plugin tagging (done by plugin service providers)
$app->tag([MyPluginAgentToolProvider::class], 'agent.providers');
```
