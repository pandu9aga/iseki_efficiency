<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cost extends Model
{
    protected $table = 'costs';
    protected $primaryKey = 'Id_Cost'; // ✅ harus sesuai
    public $timestamps = false;

    protected $fillable = [
        'Non_Operational_Cost',
        'Keterangan_Cost',
        'Start_Cost'
    ];
}
