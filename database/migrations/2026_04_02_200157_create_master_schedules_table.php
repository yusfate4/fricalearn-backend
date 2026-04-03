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
    Schema::create('master_schedules', function (Blueprint $table) {
        $table->id();
        $table->string('day_of_week'); // e.g., 'Saturday'
        // We use 'time' column type for the 24h format (e.g., 12:00:00)
        $table->time('start_time_wat'); 
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_schedules');
    }
};
