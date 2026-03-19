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
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('interaction_type', ['tts', 'stt', 'translation', 'feedback', 'assessment', 'chat']);
            $table->text('input_data')->nullable();
            $table->text('output_data')->nullable();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'interaction_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};
