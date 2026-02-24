<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('area_user')) {
            Schema::create('area_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id'); // Match User.Id_User type (likely BigInt)
                $table->unsignedBigInteger('area_id'); // Match Area.Id_Area type (likely BigInt)
                $table->timestamps();

                $table->foreign('user_id')->references('Id_User')->on('users')->onDelete('cascade');
                $table->foreign('area_id')->references('Id_Area')->on('areas')->onDelete('cascade');
            });

            // Migrate existing data
            $users = DB::table('users')->whereNotNull('Id_Area')->get();
            $inserts = [];
            foreach ($users as $user) {
                $inserts[] = [
                    'user_id' => $user->Id_User,
                    'area_id' => $user->Id_Area,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($inserts)) {
                DB::table('area_user')->insert($inserts);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('area_user');
    }
};
