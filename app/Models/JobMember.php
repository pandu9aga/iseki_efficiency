<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobMember extends Model
{
    protected $table = 'job_members';
    protected $primaryKey = 'Id_Job_Member';
    public $timestamps = false;

    protected $fillable = ['Name_Job_Member', 'Id_Area'];

    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }
}
