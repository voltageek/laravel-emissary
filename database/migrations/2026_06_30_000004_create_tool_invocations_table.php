<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_invocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('turn_id')->nullable();
            $table->uuid('conversation_id');
            $table->uuid('tenant_id')->nullable();
            $table->string('tool_name');
            $table->json('arguments');
            $table->text('result_summary')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->boolean('success');
            $table->string('validation_error')->nullable();
            $table->string('triggered_via', 24);
            $table->uuid('agent_event_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->cascadeOnDelete();

            $table->foreign('agent_event_id')
                ->references('id')
                ->on('agent_events')
                ->nullOnDelete();

            $table->index(['conversation_id', 'tool_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_invocations');
    }
};
