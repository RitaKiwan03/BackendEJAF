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
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title_en');
        $table->string('title_ar');
        $table->text('excerpt_en');
        $table->text('excerpt_ar');
        $table->longText('content_en');
        $table->longText('content_ar');
        $table->string('slug')->unique();   
        $table->string('image')->nullable();
        $table->json('tags');               
        $table->date('created_at_display'); 
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('posts');
}
};
