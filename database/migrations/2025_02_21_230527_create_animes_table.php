<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('animes', function (Blueprint $table) {
            $table->id();
            $table->string('anime_id')->unique()->nullable(); // Maps to the provided "id" field
            $table->string('external_id')->nullable();
            $table->json('images')->nullable()->nullable(); // Stores poster_tall and poster_wide
            $table->string('title')->nullable();
            $table->integer('episode_count')->default(0);
            $table->integer('season_count')->default(0);
            $table->integer('series_launch_year')->default(0);
            $table->boolean('is_mature')->default(false);
            $table->integer('rating_total')->default(0);
            $table->decimal('rating_average', 3, 1)->nullable();
            $table->string('rating_unit')->nullable();
            $table->string('slug_title')->nullable();
            $table->string('linked_resource_key')->nullable();
            $table->string('channel_id')->nullable();
            $table->longText('description')->nullable();
            $table->boolean('is_new')->default(false);
            $table->json('series_metadata')->nullable(); // Stores series-related metadata
            $table->json('rating')->nullable(); // Stores rating details
            $table->string('type')->default('series');
            $table->timestamp('last_public')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animes');
    }
};
