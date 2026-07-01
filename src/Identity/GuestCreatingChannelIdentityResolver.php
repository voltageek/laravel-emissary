<?php

declare(strict_types=1);

namespace Emissary\Identity;

use Emissary\Contracts\ChannelIdentityResolver;
use Emissary\InboundMessage;
use Emissary\Models\ChannelIdentityLink;
use Illuminate\Contracts\Auth\Authenticatable;
use Ramsey\Uuid\Uuid;

class GuestCreatingChannelIdentityResolver implements ChannelIdentityResolver
{
    public function resolveUser(InboundMessage $message): ?Authenticatable
    {
        $link = ChannelIdentityLink::where('channel', $message->channel->value)
            ->where('channel_ref', $message->conversationRef)
            ->first();

        if ($link !== null) {
            $model = config('auth.providers.users.model', \App\Models\User::class);

            return $model::find($link->user_id);
        }

        $modelClass = config('auth.providers.users.model', \App\Models\User::class);
        $user = new $modelClass();
        $user->id = Uuid::uuid4()->toString();
        $user->name = $message->conversationRef;
        $user->email = $message->conversationRef . '@guest.emissary.local';
        $user->password = bcrypt(Uuid::uuid4()->toString());
        $user->save();

        ChannelIdentityLink::create([
            'user_id' => $user->id,
            'channel' => $message->channel->value,
            'channel_ref' => $message->conversationRef,
        ]);

        return $user;
    }
}
