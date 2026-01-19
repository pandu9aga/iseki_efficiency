<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListMember extends Model
{
    protected $table = 'list_members';
    protected $primaryKey = 'Id_List_Member';
    public $timestamps = false;

    protected $fillable = [
        'Id_Member',
        'Id_Area'
    ];

    // Relasi ke Member
    public function member()
    {
        return $this->belongsTo(Member::class, 'Id_Member', 'id');
    }

    // Relasi ke Area
    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }
}
