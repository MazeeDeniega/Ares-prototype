<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->date('date_available')->nullable();
            $table->string('desired_pay')->nullable();
            $table->string('highest_education')->nullable();
            $table->string('college_university')->nullable();
            $table->string('referred_by')->nullable();
            $table->text('references')->nullable();
            $table->string('engagement_type')->nullable();
            $table->string('tor_path')->nullable();
            $table->string('cert_path')->nullable();
            $table->string('email')->nullable();
        });
    }

    public function down()
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'city', 'province', 'postal_code', 'country',
                'date_available', 'desired_pay', 'highest_education', 'college_university',
                'referred_by', 'references', 'engagement_type', 'tor_path', 'cert_path', 'email'
            ]);
        });
    }
};