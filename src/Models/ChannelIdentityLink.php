<?php

declare(strict_types=1);

namespace Emissary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChannelIdentityLink extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'channel',
        'channel_ref',
        'verified_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }
}
