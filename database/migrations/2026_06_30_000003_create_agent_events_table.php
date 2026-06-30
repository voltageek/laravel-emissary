<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('turn_id')->nullable();
            $table->uuid('conversation_id');
            $table->uuid('tenant_id')->nullable();
            $table->string('kind', 20);
            $table->string('model')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->string('intent')->nullable();
            $table->string('checkpoint')->nullable();
            $table->string('guard')->nullable();
            $table->string('tool_name')->nullable();
            $table->string('result')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->uuid('conversation_message_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->cascadeOnDelete();

            $table->index(['turn_id', 'created_at']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_events');
    }
};
