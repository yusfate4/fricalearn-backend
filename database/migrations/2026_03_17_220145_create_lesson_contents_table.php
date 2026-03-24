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
    Schema::create('lesson_contents', function (Blueprint $table) {
        $table->id();
        $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
        $table->enum('content_type', ['video', 'document', 'audio', 'image']);
        $table->string('file_url', 500); 
        $table->timestamps();
        
        $table->index('lesson_id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_contents');
    }
};
