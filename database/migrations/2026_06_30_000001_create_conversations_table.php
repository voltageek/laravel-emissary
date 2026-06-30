<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('channel', 20);
            $table->string('channel_ref', 100);
            $table->string('status', 20)->default('active');
            $table->string('onboarding_state', 20)->default('new');
            $table->json('pending_action')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'channel_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
