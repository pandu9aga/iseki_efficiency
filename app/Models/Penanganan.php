<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penanganan extends Model
{
    protected $table = 'penanganans';
    protected $primaryKey = 'Id_Penanganan'; // ✅ harus sesuai
    public $timestamps = false;

    protected $fillable = [
        'Keterangan_Penanganan',
        'Hour_Penanganan',
        'Start_Penanganan'
    ];
}
