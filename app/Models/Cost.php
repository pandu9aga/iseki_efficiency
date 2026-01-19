<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cost extends Model
{
    protected $table = 'costs';
    protected $primaryKey = 'Id_Cost';
    public $timestamps = false;

    protected $fillable = [
        'Non_Operational_Cost',
        'Keterangan_Cost',
        'calculation_type',
        'applied_members', // ✅ pastikan ini ada
        'Start_Cost',
        'Id_Area'
    ];

    protected $casts = [
        'applied_members' => 'json', // Laravel otomatis handle serialize/deserialize
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }

    // HAPUS relasi members() karena tidak dipakai
}
