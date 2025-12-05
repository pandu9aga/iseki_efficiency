<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Type_User extends Model
{
    use HasFactory;

    protected $table = 'type_users'; // Nama tabel
    protected $primaryKey = 'Id_Type_User'; // Nama primary key

    public $timestamps = false; // Jika tabel tidak memiliki created_at dan updated_at
}
