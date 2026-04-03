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
    Schema::table('rewards', function (Blueprint $table) {
        // If image_path is already there, you only need to add file_path
        if (!Schema::hasColumn('rewards', 'image_path')) {
            $table->string('image_path')->nullable()->after('description');
        }
        if (!Schema::hasColumn('rewards', 'file_path')) {
            $table->string('file_path')->nullable()->after('image_path');
        }
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'file_path']);
            // If you renamed columns in up(), remember to rename them back here
        });
    }
};