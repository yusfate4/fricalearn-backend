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
    Schema::table('lessons', function (Blueprint $table) {
        // Add the column as nullable so old lessons don't break
        $table->foreignId('course_id')->nullable()->constrained()->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('lessons', function (Blueprint $table) {
        $table->dropForeign(['course_id']);
        $table->dropColumn('course_id');
    });
}

    
};
