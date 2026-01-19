<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penanganan extends Model
{
    protected $table = 'penanganans';
    protected $primaryKey = 'Id_Penanganan';
    public $timestamps = false;

    // Tambahkan Id_Area ke fillable
    protected $fillable = [
        'Keterangan_Penanganan',
        'Hour_Penanganan',
        'Start_Penanganan',
        'Id_Area' // Ditambahkan
    ];

    // Relasi ke Area
    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }
}
