<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $table = 'scans';
    protected $primaryKey = 'Id_Scan'; // ✅ harus sesuai
    public $timestamps = false;

    protected $fillable = [
        'Id_Member',
        'Id_Tractor',
        'Time_Scan',
        'Assigned_Hour_Scan'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'Id_Member', 'id');
    }

    public function tractor()
    {
        return $this->belongsTo(Tractor::class, 'Id_Tractor', 'Id_Tractor');
    }
}
