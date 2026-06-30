<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_spans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('turn_id')->nullable();
            $table->uuid('conversation_id');
            $table->string('stage', 48);
            $table->integer('duration_ms');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_spans');
    }
};
