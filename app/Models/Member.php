<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    /**
     * Koneksi database eksternal.
     */
    protected $connection = 'rifa';

    /**
     * Nama tabel di database iseki_rifa.
     */
    protected $table = 'employees';

    /**
     * Primary key tabel.
     */
    protected $primaryKey = 'id';

    /**
     * Kolom yang bisa diisi mass assignment.
     */
    protected $fillable = ['nama', 'nik', 'team', 'division_id', 'status'];

    /**
     * Relasi ke Division (pastikan tabel divisions ada di DB 'rifa').
     */
    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
    }
    public function area()
    {
        return $this->belongsTo(Area::class, 'Id_Area', 'Id_Area');
    }

    // Hapus relasi ini jika ada:
    // public function listMembers()
    // {
    //     return $this->hasMany(ListMember::class, 'Id_Member', 'id');
    // }
}
