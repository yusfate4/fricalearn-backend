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
    Schema::table('student_profiles', function (Blueprint $table) {
        // 🚀 Adding the missing columns
        $table->string('date_of_birth')->nullable()->after('user_id');
        $table->string('grade_level')->nullable()->after('date_of_birth');
        // We'll also ensure learning_language exists if it doesn't
        if (!Schema::hasColumn('student_profiles', 'learning_language')) {
            $table->string('learning_language')->default('Yoruba');
        }
    });
}

public function down(): void
{
    Schema::table('student_profiles', function (Blueprint $table) {
        $table->dropColumn(['date_of_birth', 'grade_level', 'learning_language']);
    });
}
};
