<?php

declare(strict_types=1);

namespace Emissary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChannelConfig extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'channel',
        'label',
        'credentials',
        'status',
    ];
}
