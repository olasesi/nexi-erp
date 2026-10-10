<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('youtube_video_id')->nullable();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('youtube_playlist_id')
                ->nullable()
                ->constrained('youtube_playlists')
                ->nullOnDelete();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_videos');
    }
};
