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
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('period', ['daily', 'weekly', 'monthly', 'all_time']);
            $table->integer('points');
            $table->integer('rank')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();

            $table->index(['period', 'period_start', 'period_end']);
            $table->index('rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
    }
};
