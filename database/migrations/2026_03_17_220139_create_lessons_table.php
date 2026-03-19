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
        Schema::create('lessons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('module_id')->constrained()->onDelete('cascade');
    $table->string('title');
    $table->text('content')->nullable(); // 👈 This is the one we need
    $table->string('video_url')->nullable();
    $table->integer('order_index')->default(0);
    $table->boolean('is_published')->default(false);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
