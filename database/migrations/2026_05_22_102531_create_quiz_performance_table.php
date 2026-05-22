<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quiz_performance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('lesson_id'); 
            $table->unsignedBigInteger('topic_id');
            $table->unsignedBigInteger('subject_id');
            
            // Performance data
            $table->integer('score'); 
            $table->integer('total_questions');
            $table->integer('correct_answers');
            $table->integer('wrong_answers');
            $table->json('wrong_question_ids'); 
            $table->boolean('passed');
            $table->integer('time_taken_seconds')->nullable();
            
            // For monthly reports
            $table->timestamp('completed_at');
            $table->integer('attempt_number')->default(1);
            
            $table->timestamps();
            
            // Indexes & Foreign Keys
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('external_lessons')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('external_topics')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('external_subjects')->onDelete('cascade');
            
            $table->index(['student_id', 'completed_at']);
            $table->index(['topic_id', 'student_id']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('quiz_performance');
    }
};