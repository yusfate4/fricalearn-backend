<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $user) {
            // ⏳ Tracks AI usage in minutes (Item 10)
            $user->integer('daily_ai_minutes')->default(0)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $user) {
            $user->dropColumn('daily_ai_minutes');
        });
    }
};