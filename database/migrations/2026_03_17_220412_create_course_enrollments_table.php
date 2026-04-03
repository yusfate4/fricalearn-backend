<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // 📁 database/migrations/xxxx_create_course_enrollments_table.php

public function up(): void
{
    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('course_id')->constrained()->cascadeOnDelete();
        $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
        
        $table->timestamp('enrolled_at')->useCurrent();
        $table->timestamp('completed_at')->nullable();
        
        // 🚀 THE MISSING PIECE: Add this line back!
        $table->timestamp('expires_at')->nullable(); 
        
        $table->decimal('progress_percentage', 5, 2)->default(0.00);
        $table->enum('status', ['active', 'completed', 'dropped', 'paused'])->default('active');
        $table->timestamps();

        $table->unique(['course_id', 'student_id'], 'unique_enrollment');
        $table->index('student_id');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};
