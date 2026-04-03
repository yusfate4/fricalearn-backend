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
    Schema::table('student_profiles', function (Blueprint $table) {
        $table->boolean('onboarding_completed')->default(false);
        $table->string('learning_goal')->nullable(); // e.g., "Travel", "Family", "Culture"
        $table->enum('starting_level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            //
        });
    }
};
