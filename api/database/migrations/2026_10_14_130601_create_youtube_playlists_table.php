<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('youtube_playlist_id')->nullable();
            $table->text('description')->nullable();
            $table->string('visibility')->default('public');
            $table->unsignedBigInteger('videos_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_playlists');
    }
};
