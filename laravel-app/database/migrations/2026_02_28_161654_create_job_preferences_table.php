<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('job_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->onDelete('cascade');
            $table->integer('keyword_weight')->default(40);
            $table->integer('semantic_weight')->default(60);
            $table->integer('skills_weight')->default(35);
            $table->integer('experience_weight')->default(20);
            $table->integer('education_weight')->default(25);
            $table->integer('cert_weight')->default(10);
            $table->integer('layout_weight')->default(0);
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('job_preferences');
    }
};