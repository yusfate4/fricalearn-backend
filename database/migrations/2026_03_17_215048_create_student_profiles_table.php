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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('language')->default('Yoruba');
            $table->integer('total_points')->default(0);
            
            // 👇 ADD THIS LINE FOR GAMIFICATION COINS
            $table->integer('total_coins')->default(0); 
            
            // 👇 ADD THIS LINE FOR RANKS (If you don't have it already)
            $table->string('current_level')->default('Omode'); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
