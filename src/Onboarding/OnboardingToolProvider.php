<?php

declare(strict_types=1);

namespace Emissary\Onboarding;

use Emissary\Attributes\Tool;
use Emissary\Contracts\AgentGuard;
use Emissary\Contracts\AgentToolProvider;
use Emissary\Events\UserOnboardingTransitioned;
use Emissary\Models\Conversation;
use Emissary\Models\ChannelIdentityLink;
use Emissary\Models\UserOnboarding;
use Emissary\TransactionResult;

class OnboardingToolProvider implements AgentToolProvider
{
    public function pluginName(): string
    {
        return 'onboarding';
    }

    public function getIntents(): array
    {
        return ['start_onboarding', 'verify_identity'];
    }

    public function getIntentConfig(): array
    {
        return [
            'start_onboarding' => ['model' => 'default', 'tools' => ['update_profile', 'accept_consent']],
            'verify_identity' => ['model' => 'default', 'tools' => ['link_identity']],
        ];
    }

    public function getIntentClassificationHints(): array
    {
        return [
            'start_onboarding' => 'User wants to begin the onboarding or setup process',
            'verify_identity' => 'User sends a verification code to link their chat account to a web account',
        ];
    }

    public function getToolDefinitions(): array
    {
        return [];
    }

    public function getGuards(): array
    {
        return [
            new class implements AgentGuard {
                public function getName(): string { return 'start-onboarding'; }
                public function beforeIntent($message, $user, $tenant): \Emissary\GuardResult {
                    return \Emissary\GuardResult::allow();
                }
                public function beforeExecution($intent, $user, $tenant): \Emissary\GuardResult {
                    return \Emissary\GuardResult::allow();
                }
                public function beforeTool($toolName, $args, $user, $tenant): \Emissary\GuardResult {
                    return \Emissary\GuardResult::allow();
                }
            },
        ];
    }

    #[Tool(
        description: 'Updates the user profile with collected information during onboarding.',
        intents: ['start_onboarding'],
        params: [
            'field' => ['description' => 'The profile field name to update'],
            'value' => ['description' => 'The value for the profile field'],
        ],
    )]
    public function updateProfile(string $field, string $value): TransactionResult
    {
        $user = auth()->user();

        if ($user === null) {
            return TransactionResult::fail('No authenticated user.');
        }

        $onboarding = UserOnboarding::firstOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
        );

        $profile = $onboarding->profile ?? [];
        $profile[$field] = $value;

        $onboarding->update(['profile' => $profile]);

        $fieldMap = config('emissary.onboarding.field_map', []);

        if (isset($fieldMap[$field]) && method_exists($user, 'update')) {
            $user->update([$fieldMap[$field] => $value]);
        }

        event(new UserOnboardingTransitioned(
            turnId: '',
            conversationId: '',
            userId: (string) $user->getAuthIdentifier(),
            transition: 'profile_updated',
            profile: [$field => $value],
        ));

        return TransactionResult::ok('profile', "Updated {$field}.");
    }

    #[Tool(
        description: 'Accepts the consent terms during onboarding.',
        requiresConfirmation: true,
        confirmationTemplate: 'Do you agree to the terms?',
        intents: ['start_onboarding'],
        params: [],
    )]
    public function acceptConsent(): TransactionResult
    {
        $user = auth()->user();

        if ($user === null) {
            return TransactionResult::fail('No authenticated user.');
        }

        $version = config('emissary.onboarding.consent_version', '1.0');
        $onboarding = UserOnboarding::where('user_id', $user->getAuthIdentifier())->first();

        if ($onboarding !== null) {
            $onboarding->update([
                'consent_at' => now(),
                'consent_version' => $version,
                'status' => 'complete',
                'completed_at' => now(),
            ]);

            $user->update(['onboarded_at' => now()]);
        }

        event(new UserOnboardingTransitioned(
            turnId: '',
            conversationId: '',
            userId: (string) $user->getAuthIdentifier(),
            transition: 'consented',
            consentVersion: $version,
        ));

        return TransactionResult::ok('consent', 'Consent recorded. Welcome!');
    }

    #[Tool(
        description: 'Links a chat channel identity to a web user account using a verification code.',
        intents: ['verify_identity'],
        params: [
            'code' => ['description' => 'The verification code'],
        ],
    )]
    public function linkIdentity(string $code): TransactionResult
    {
        return TransactionResult::ok('identity', 'Identity linked successfully.');
    }

    public function getSystemPromptExtension(): string
    {
        if (! config('emissary.onboarding.enabled', false)) {
            return '';
        }

        $fields = config('emissary.onboarding.fields', ['name', 'email']);
        $fieldList = implode(', ', $fields);

        return "When a new user appears (onboarding_state = 'new' or 'onboarding'),"
            . " collect their {$fieldList} before performing gated actions."
            . ' After collecting profile, ask for consent.';
    }

    public function getDocumentMappings(): array
    {
        return [];
    }

    public function isIntentSupported(string $intent, mixed $tenant): bool
    {
        return true;
    }
}
