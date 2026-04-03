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
        // 🚀 Step 1: Add the column as NULLABLE so it doesn't crash on existing data
        $table->foreignId('student_id')
              ->nullable() 
              ->after('id')
              ->constrained('users')
              ->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('progress_records', function (Blueprint $table) {
        $table->dropForeign(['student_id']);
        $table->dropColumn('student_id');
    });
}
};
