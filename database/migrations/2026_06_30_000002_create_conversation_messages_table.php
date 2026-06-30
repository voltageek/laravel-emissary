<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('turn_id')->nullable();
            $table->string('role', 20);
            $table->text('content');
            $table->string('media_url')->nullable();
            $table->string('intent')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->cascadeOnDelete();

            $table->index(['conversation_id', 'created_at']);
            $table->index('turn_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
