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
        Schema::create('replacements', function (Blueprint $table) {
            $table->id('Id_Replacement');
            $table->string('NIK_Replacement', 20)->nullable();
            $table->unsignedBigInteger('Id_Daily_Job')->nullable();
            $table->string('Sequence_No_Plan', 50)->nullable();
            $table->string('Production_Date_Plan', 50)->nullable();
            $table->string('Model_Mower_Plan', 50)->nullable();
            $table->string('Model_Collector_Plan', 50)->nullable();
            $table->string('Name_Tractor', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replacements');
    }
};
