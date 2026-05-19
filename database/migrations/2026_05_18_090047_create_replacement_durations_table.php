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
        Schema::create('replacement_durations', function (Blueprint $table) {
            $table->id('Id_Duration');
            $table->string('NIK_Replacement', 20)->nullable();
            $table->unsignedBigInteger('Id_Daily_Job')->nullable();
            $table->integer('Total_Minutes')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replacement_durations');
    }
};
