<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Power extends Model
{
    protected $table = 'powers';
    protected $primaryKey = 'Id_Power';
    public $timestamps = false;

    // Tambahkan Id_Area ke fillable
    protected $fillable = [
        'Id_Member',
        'Leave_Hour_Power',
        'Keterangan_Power',
        'Start_Power',
        'Id_Area' // Ditambahkan
    ];

    // Relasi ke Member (tetap sama)
    public function member()
    {
        return $this->belongsTo(Member::class, 'Id_Member', 'id');
    }

    // Relasi ke Area
    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }
}
