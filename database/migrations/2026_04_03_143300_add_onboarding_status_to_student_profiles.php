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
        // 🚀 Add the boolean flag. Default is false (0).
        $table->boolean('has_completed_onboarding')->default(false)->after('total_coins');
    });
}

public function down()
{
    Schema::table('student_profiles', function (Blueprint $table) {
        $table->dropColumn('has_completed_onboarding');
    });
}
};
