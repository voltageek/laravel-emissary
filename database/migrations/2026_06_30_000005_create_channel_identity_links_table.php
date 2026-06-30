<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_identity_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('channel', 20);
            $table->string('channel_ref', 100);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['channel', 'channel_ref']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_identity_links');
    }
};
