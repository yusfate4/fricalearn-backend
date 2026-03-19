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
    Schema::create('progress_records', function (Blueprint $table) {
        $table->id();
        // This is the missing column!
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
        
        $table->string('status')->default('started'); // started, in_progress, completed
        $table->timestamp('started_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_records');
    }
};
