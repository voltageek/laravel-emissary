<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('channel', 20);
            $table->string('label');
            $table->text('credentials');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_configs');
    }
};
