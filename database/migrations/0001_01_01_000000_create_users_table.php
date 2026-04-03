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
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        
        // --- 🚀 FricaLearn Custom Fields ---
        // Added 'student' as default to prevent null errors
        $table->enum('role', ['admin', 'tutor', 'student', 'parent'])->default('student'); 
        
        // Added is_admin to match our Sidebar security logic
        $table->boolean('is_admin')->default(false); 

        $table->string('country', 100)->nullable();
        $table->string('timezone', 100)->nullable();
        $table->string('avatar_url', 500)->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_login_at')->nullable();
        
        $table->rememberToken();
        $table->timestamps();

        // Indexes for faster querying
        $table->index('role');
        $table->index('email');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
