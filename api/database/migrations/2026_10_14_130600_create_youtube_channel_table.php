<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_channel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('channel_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('subscriber_count')->default(0);
            $table->unsignedBigInteger('video_count')->default(0);
            $table->boolean('is_connected')->default(false);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_channel');
    }
};
