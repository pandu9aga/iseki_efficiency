<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'areas';
    protected $primaryKey = 'Id_Area';
    public $timestamps = false;

    protected $fillable = ['Name_Area', 'Password_Area'];

    public function jobMembers()
    {
        return $this->hasMany(JobMember::class, 'Id_Area', 'Id_Area');
    }
}
