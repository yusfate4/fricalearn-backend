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
        Schema::create('quiz_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('quiz_answer_options')->nullOnDelete();
            $table->text('text_answer')->nullable();
            $table->string('audio_url', 500)->nullable(); // For student pronunciation recordings
            $table->boolean('is_correct')->nullable();
            $table->text('ai_feedback')->nullable();
            $table->decimal('points_earned', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_responses');
    }
};
