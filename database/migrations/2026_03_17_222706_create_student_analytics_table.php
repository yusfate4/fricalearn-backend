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
        Schema::create('student_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->integer('total_time_spent_minutes')->default(0);
            $table->integer('lessons_completed')->default(0);
            $table->integer('quizzes_taken')->default(0);
            $table->decimal('average_quiz_score', 5, 2)->nullable();
            $table->integer('points_earned')->default(0);
            $table->integer('login_count')->default(0);
            $table->timestamps();

            $table->unique(['student_id', 'date'], 'unique_student_date');
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_analytics');
    }
};
