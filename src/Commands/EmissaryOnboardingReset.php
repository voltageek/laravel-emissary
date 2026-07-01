<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Models\Conversation;
use Emissary\Models\UserOnboarding;
use Illuminate\Console\Command;

class EmissaryOnboardingReset extends Command
{
    protected $signature = 'emissary:onboarding:reset {channel_ref : The channel reference to reset}';
    protected $description = 'Reset onboarding state for a conversation sender';

    public function handle(): int
    {
        $channelRef = $this->argument('channel_ref');

        $conversation = Conversation::where('channel_ref', $channelRef)->first();

        if ($conversation === null) {
            $this->warn("No conversation found for channel_ref: {$channelRef}");

            return self::FAILURE;
        }

        $conversation->update(['onboarding_state' => 'new']);

        $link = \Emissary\Models\ChannelIdentityLink::where('channel_ref', $channelRef)->first();

        if ($link !== null) {
            UserOnboarding::where('user_id', $link->user_id)->delete();
        }

        $this->info("Onboarding reset for {$channelRef}.");

        return self::SUCCESS;
    }
}
