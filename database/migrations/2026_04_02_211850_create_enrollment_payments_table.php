<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('enrollment_payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('parent_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
        $table->string('child_name');
        $table->decimal('amount', 10, 2);
        $table->string('currency', 3);
        $table->string('receipt_path');
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        $table->timestamp('expires_at')->nullable();
        
        // 🚀 ADD THIS LINE IF IT IS MISSING
        $table->timestamps(); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollment_payments');
    }
};
