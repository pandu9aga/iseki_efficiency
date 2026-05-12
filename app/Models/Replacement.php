<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Replacement extends Model
{
    protected $table = 'replacements';
    protected $primaryKey = 'Id_Replacement';
    protected $fillable = [
        'NIK_Replacement',
        'Id_Daily_Job',
        'Sequence_No_Plan',
        'Production_Date_Plan',
        'Model_Mower_Plan',
        'Model_Collector_Plan',
        'Name_Tractor'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'NIK_Replacement', 'nik');
    }

    public function dailyJob()
    {
        return $this->belongsTo(DailyJob::class, 'Id_Daily_Job', 'Id_Daily_Job');
    }
}
