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
        Schema::create('learning_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('recommendation_type', ['lesson', 'practice', 'review', 'challenge']);
            $table->string('recommended_item_type', 100)->nullable();
            $table->unsignedBigInteger('recommended_item_id')->nullable();
            $table->text('reason')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'is_completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_recommendations');
    }
};
