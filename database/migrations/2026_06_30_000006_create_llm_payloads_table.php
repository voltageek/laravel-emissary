<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_payloads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('agent_event_id');
            $table->uuid('turn_id')->nullable();
            $table->json('request_messages');
            $table->json('tools_sent')->nullable();
            $table->json('response');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('agent_event_id')
                ->references('id')
                ->on('agent_events')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_payloads');
    }
};
