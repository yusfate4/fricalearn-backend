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
    Schema::create('badges', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('description');
        $table->string('icon')->default('Zap'); // Needed for Seeder
        $table->integer('required_points')->default(0); // Needed for Seeder
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
