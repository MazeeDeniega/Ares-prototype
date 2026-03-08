<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_preferences', function (Blueprint $table) {
            $table->integer('formatting_weight')->default(25);
            $table->integer('language_weight')->default(25);
            $table->integer('concise_weight')->default(25);
            $table->integer('organization_weight')->default(25);
            $table->boolean('pref_formatting')->default(false)->after('layout_weight');
            $table->boolean('pref_language')->default(false)->after('pref_formatting');
            $table->boolean('pref_conciseness')->default(false)->after('pref_language');
            $table->boolean('pref_organization')->default(false)->after('pref_conciseness');
        });

        Schema::table('preferences', function (Blueprint $table) {
            $table->integer('keyword_weight')->default(40);
            $table->integer('semantic_weight')->default(60);
            $table->integer('layout_weight')->default(0);
            $table->integer('formatting_weight')->default(25);
            $table->integer('language_weight')->default(25);
            $table->integer('concise_weight')->default(25);
            $table->integer('organization_weight')->default(25);
            $table->boolean('pref_formatting')->default(false)->after('layout_weight');
            $table->boolean('pref_language')->default(false)->after('pref_formatting');
            $table->boolean('pref_conciseness')->default(false)->after('pref_language');
            $table->boolean('pref_organization')->default(false)->after('pref_conciseness');
        });
    }

    public function down(): void
    {
        Schema::table('job_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'layout_weight', 'formatting_weight', 'language_weight',
                'concise_weight', 'organization_weight',
                'pref_formatting', 'pref_language', 'pref_conciseness', 'pref_organization',
            ]);
        });

        Schema::table('preferences', function (Blueprint $table) {
            $table->dropColumn([
                'keyword_weight', 'semantic_weight',
                'layout_weight', 'formatting_weight', 'language_weight',
                'concise_weight', 'organization_weight',
                'pref_formatting', 'pref_language', 'pref_conciseness', 'pref_organization',
            ]);
        });
    }
};