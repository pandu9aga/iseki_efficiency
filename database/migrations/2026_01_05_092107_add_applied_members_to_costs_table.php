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
            if (!Schema::hasColumn('costs', 'applied_members')) {
                $table->json('applied_members')->nullable()->comment('Array of selected nik or "all"');
            }
        });
    }

    public function down()
    {
        Schema::table('costs', function (Blueprint $table) {
            $table->dropColumn('applied_members');
        });
    }
};
