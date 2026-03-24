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
    // 👈 Changed 'quiz_questions' to 'questions'
    Schema::table('questions', function (Blueprint $table) {
        $table->string('explanation_video_url')->nullable()->after('question_text');
        $table->text('explanation_text')->nullable()->after('explanation_video_url');
    });
}

public function down()
{
    Schema::table('questions', function (Blueprint $table) {
        $table->dropColumn(['explanation_video_url', 'explanation_text']);
    });
}
    /**
     * Reverse the migrations.
     */
 
};
