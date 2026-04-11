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
    Schema::create('tutor_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->text('bio')->nullable();
        $table->string('specialization')->nullable(); // e.g., 'Yoruba Language', 'Igbo Culture'
        $table->string('qualification')->nullable();
        $table->json('social_links')->nullable(); // For LinkedIn/Twitter
        $table->boolean('is_verified')->default(false);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutor_profiles');
    }
};
