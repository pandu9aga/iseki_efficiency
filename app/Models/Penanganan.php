<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penanganan extends Model
{
    protected $table = 'penanganans';
    protected $primaryKey = 'Id_Penanganan';
    public $timestamps = false;

    // ✅ Gunakan nama kolom yang SAMA PERSIS seperti di database
    protected $fillable = [
        'Keterangan_Penanganan',
        'Hour_Penanganan',
        'Start_Penanganan',
        'Id_Area',
        'catatan_internal',
        'Applied_Members', // ← Ini harus SAMA seperti di DB
    ];

    // ✅ Casting agar otomatis jadi JSON/array
    protected $casts = [
        'Applied_Members' => 'array', // ← Nama kolom sesuai DB
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }

    public function dailyJob()
    {
        return $this->belongsTo(DailyJob::class, 'Id_Daily_Job', 'Id_Daily_Job');
    }
}
