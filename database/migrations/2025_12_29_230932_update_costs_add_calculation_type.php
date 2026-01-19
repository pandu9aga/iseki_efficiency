<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('costs', function (Blueprint $table) {
            $table->enum('calculation_type', ['all', 'partial'])->default('all')->after('Keterangan_Cost');
        });

        // Tabel pivot untuk partial cost
        Schema::create('cost_member', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('Id_Cost');
            $table->unsignedBigInteger('member_id'); // id dari tabel employees
            $table->timestamps();

            $table->foreign('Id_Cost')->references('Id_Cost')->on('costs')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('employees'); // pastikan nama tabel sesuai
        });
    }
};
