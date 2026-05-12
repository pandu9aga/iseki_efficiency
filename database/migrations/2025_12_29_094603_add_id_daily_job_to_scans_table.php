<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('scans', function (Blueprint $table) {
            if (!Schema::hasColumn('scans', 'Id_Daily_Job')) {
                $table->unsignedInteger('Id_Daily_Job')->nullable()->after('Assigned_Hour_Scan');
                $table->foreign('Id_Daily_Job')
                    ->references('Id_Daily_Job')
                    ->on('daily_jobs')
                    ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropForeign(['Id_Daily_Job']);
            $table->dropColumn('Id_Daily_Job');
        });
    }
};
