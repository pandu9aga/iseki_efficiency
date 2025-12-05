<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'reports';
    protected $primaryKey = 'Id_Report'; // ✅ harus sesuai
    public $timestamps = false;

    protected $fillable = [
        'Day_Report',
        'Total_Hours_Report',
        'Total_Member_Report'
    ];
}
