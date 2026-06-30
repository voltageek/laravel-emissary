<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_onboardings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('conversation_id')->nullable();
            $table->string('status', 20)->default('guest');
            $table->json('profile')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_version', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->unique('user_id');

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboardings');
    }
};
