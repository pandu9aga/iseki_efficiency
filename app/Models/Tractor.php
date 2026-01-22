<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tractor extends Model
{
    protected $table = 'tractors';
    protected $primaryKey = 'Id_Tractor';
    public $timestamps = false;

    protected $fillable = [
        'Name_Tractor',
        'Group_Tractor',
        'Hour_Tractor',
        'Id_Area'
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }
}