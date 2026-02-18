<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'Username_User',
        'Name_User',
        'Password_User',
        'Id_Type_User',
        'Id_Area',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relasi ke Area (Legacy: BelongsTo)
    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }

    // New: Many-to-Many
    public function areas()
    {
        return $this->belongsToMany(Area::class, 'area_user', 'user_id', 'area_id');
    }

    protected $table = 'users';
    protected $primaryKey = 'Id_User';
    public $timestamps = false;
}
