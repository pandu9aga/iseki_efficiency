<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Tractor.php
class Tractor extends Model
{
    protected $table = 'tractors';
    protected $primaryKey = 'Id_Tractor'; // ✅ harus sesuai
    public $timestamps = false;

    protected $fillable = [
        'Name_Tractor',
        'Group_Tractor',
        'Hour_Tractor'
    ];
}
