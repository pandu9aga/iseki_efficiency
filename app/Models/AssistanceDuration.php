<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceDuration extends Model
{
    protected $table = 'assistance_durations';
    protected $primaryKey = 'Id_Duration';
    protected $fillable = ['NIK_Assistance', 'Id_Daily_Job', 'Total_Minutes'];
}
