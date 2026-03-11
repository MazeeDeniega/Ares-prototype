<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('preferences', function (Blueprint $table) {
            $table->integer('qual_weight')->default(100);
        });
        Schema::table('job_preferences', function (Blueprint $table) {
            $table->integer('qual_weight')->default(100);
        });
    }
    public function down(): void
    {
        Schema::table('preferences',     fn($t) => $t->dropColumn('qual_weight'));
        Schema::table('job_preferences', fn($t) => $t->dropColumn('qual_weight'));
    }
};