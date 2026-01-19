<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyJob extends Model
{
    protected $table = 'daily_jobs';
    protected $primaryKey = 'Id_Daily_Job';
    public $timestamps = false;

    protected $fillable = [
        'Nik_Daily_Job',
        'Id_Job_Member',
        'Id_Area',
        'Sequence_No_Plan',
        'Production_Date_Plan',
        'Type_Daily_Job',
        'Nik_Replace_Daily_Job'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'Nik_Daily_Job', 'nik');
    }

    public function replacedMember()
    {
        return $this->belongsTo(Member::class, 'Nik_Replace_Daily_Job', 'nik');
    }

    public function jobMember()
    {
        return $this->belongsTo(JobMember::class, 'Id_Job_Member', 'Id_Job_Member');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }
    public function dailyJob()
    {
        return $this->belongsTo(DailyJob::class, 'Id_Daily_Job', 'Id_Daily_Job');
    }
}
