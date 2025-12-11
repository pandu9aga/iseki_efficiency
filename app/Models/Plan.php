<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $connection = 'podium';
    protected $table = 'plans'; // Nama tabel
    protected $primaryKey = 'Id_Plan'; // Nama primary key

    public $timestamps = false; // Jika tabel tidak memiliki created_at dan updated_at

    protected $fillable = [
        'Id_Plan',
        'Type_Plan',
        'Sequence_No_Plan',
        'Production_Date_Plan',
        'Model_Name_Plan',
        'Production_No_Plan',
        'Chasis_No_Plan',
        'Model_Label_Plan',
        'Safety_Frame_Label_Plan',
        'Model_Mower_Plan',
        'Mower_No_Plan',
        'Model_Collector_Plan',
        'Collector_No_Plan',
        'Record_Plan',
        'Lineoff_Plan',
        'Status_Plan'
    ];

    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'Id_User', 'Id_User');
    // }
}
