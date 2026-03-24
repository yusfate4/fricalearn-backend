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
    Schema::table('progress_records', function (Blueprint $table) {
        // We add the 'score' column as an integer, defaulting to 0
        $table->integer('score')->default(0)->after('lesson_id');
    });
}

public function down()
{
    Schema::table('progress_records', function (Blueprint $table) {
        $table->dropColumn('score');
    });
}
};
