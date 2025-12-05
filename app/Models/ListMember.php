<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Tractor.php
class ListMember extends Model
{
    protected $table = 'list_members';
    protected $primaryKey = 'Id_List_Member'; // ✅ harus sesuai
    public $timestamps = false;

    protected $fillable = [
        'Id_Member'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'Id_Member', 'id');
    }
}
