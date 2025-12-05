<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Power extends Model
{
    protected $table = 'powers';
    protected $primaryKey = 'Id_Power'; // ✅ harus sesuai
    public $timestamps = false;

    protected $fillable = [
        'Id_Member',
        'Leave_Hour_Power',
        'Keterangan_Power',
        'Start_Power'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'Id_Member', 'id');
    }
}
