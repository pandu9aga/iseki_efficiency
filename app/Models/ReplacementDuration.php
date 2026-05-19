<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReplacementDuration extends Model
{
    protected $table = 'replacement_durations';
    protected $primaryKey = 'Id_Duration';
    protected $fillable = ['NIK_Replacement', 'Id_Daily_Job', 'Total_Minutes'];
}
