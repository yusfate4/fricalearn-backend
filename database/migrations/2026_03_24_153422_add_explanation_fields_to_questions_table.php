<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            // Adding the columns after 'correct_answer'
            $table->string('explanation_video_url')->nullable()->after('correct_answer');
            $table->text('explanation_text')->nullable()->after('explanation_video_url');
        });
    }

    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['explanation_video_url', 'explanation_text']);
        });
    }
};