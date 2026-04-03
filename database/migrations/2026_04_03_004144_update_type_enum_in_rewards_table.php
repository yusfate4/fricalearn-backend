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
    Schema::table('rewards', function (Blueprint $table) {
        // 🚀 Change from ENUM to a flexible STRING
        // This prevents "Data truncated" errors forever
        $table->string('type')->change();
    });
}

public function down()
{
    Schema::table('rewards', function (Blueprint $table) {
        $table->enum('type', ['digital_asset', 'physical'])->change();
    });
}
};
