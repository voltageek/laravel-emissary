<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_ledgers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('tenant_id')->nullable();
            $table->string('month', 7);
            $table->integer('input_tokens');
            $table->integer('output_tokens');
            $table->decimal('cost_usd', 12, 6);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['conversation_id', 'month']);

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_ledgers');
    }
};
