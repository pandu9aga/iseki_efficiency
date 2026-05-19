<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkDay extends Model
{
    protected $table = 'work_days';
    protected $primaryKey = 'Id_Work_Day';
    public $timestamps = false;

    protected $fillable = [
        'Moth_Work_Day',
        'Total_Work_Day',
    ];
}
