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
        Schema::create('lesson_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->enum('content_type', ['video', 'document', 'image', 'audio', 'text', 'interactive']);
            $table->string('title')->nullable();
            $table->text('content_text')->nullable();
            $table->string('file_url', 500)->nullable();
            $table->integer('file_size')->nullable();
            $table->integer('order_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_contents');
    }
};
