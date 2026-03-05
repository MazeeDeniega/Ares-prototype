<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('job_preferences', function (Blueprint $table) {
            $table->boolean('pref_formatting')->default(false)->after('layout_weight');
            $table->boolean('pref_language')->default(false)->after('pref_formatting');
            $table->boolean('pref_conciseness')->default(false)->after('pref_language');
            $table->boolean('pref_organization')->default(false)->after('pref_conciseness');
        });

        Schema::table('preferences', function (Blueprint $table) {
            $table->boolean('pref_formatting')->default(false)->after('layout_weight');
            $table->boolean('pref_language')->default(false)->after('pref_formatting');
            $table->boolean('pref_conciseness')->default(false)->after('pref_language');
            $table->boolean('pref_organization')->default(false)->after('pref_conciseness');
        });
    }

    public function down() {
        Schema::table('job_preferences', function (Blueprint $table) {
            $table->dropColumn(['pref_formatting', 'pref_language', 'pref_conciseness', 'pref_organization']);
        });
        Schema::table('preferences', function (Blueprint $table) {
            $table->dropColumn(['pref_formatting', 'pref_language', 'pref_conciseness', 'pref_organization']);
        });
    }
};