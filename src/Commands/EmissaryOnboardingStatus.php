<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Models\Conversation;
use Emissary\Models\UserOnboarding;
use Illuminate\Console\Command;

class EmissaryOnboardingStatus extends Command
{
    protected $signature = 'emissary:onboarding:status {channel_ref : The channel reference to check}';
    protected $description = 'Print onboarding state for a conversation sender';

    public function handle(): int
    {
        $channelRef = $this->argument('channel_ref');

        $conversation = Conversation::where('channel_ref', $channelRef)->first();

        if ($conversation === null) {
            $this->warn("No conversation found for channel_ref: {$channelRef}");

            return self::FAILURE;
        }

        $this->info("Conversation: {$conversation->id}");
        $this->line("  Channel:       {$conversation->channel}");
        $this->line("  Onboarding:    {$conversation->onboarding_state}");
        $this->line("  Pending action: " . ($conversation->pending_action ? 'yes' : 'no'));

        $link = \Emissary\Models\ChannelIdentityLink::where('channel_ref', $channelRef)->first();

        if ($link !== null) {
            $this->line("  User ID:       {$link->user_id}");
            $this->line("  Verified:      " . ($link->verified_at ?: 'no'));

            $onboarding = UserOnboarding::where('user_id', $link->user_id)->first();

            if ($onboarding !== null) {
                $this->line("  Profile:       " . json_encode($onboarding->profile));
                $this->line("  Consent:       " . ($onboarding->consent_at ?: 'not yet'));
                $this->line("  Status:        {$onboarding->status}");
            }
        }

        return self::SUCCESS;
    }
}
