<?php

declare(strict_types=1);

namespace Emissary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostLedger extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'tenant_id',
        'month',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_usd' => 'float',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
