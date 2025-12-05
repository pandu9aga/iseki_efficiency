<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    /**
     * Koneksi ke database eksternal 'rifa'.
     */
    protected $connection = 'rifa';

    /**
     * Nama tabel di database 'rifa'.
     */
    protected $table = 'divisions';

    /**
     * Primary key tabel.
     */
    protected $primaryKey = 'id';

    /**
     * Kolom yang bisa diisi.
     */
    protected $fillable = ['nama']; // sesuaikan dengan kolom di tabel divisions

    // Jika tidak ada kolom timestamps (created_at, updated_at)
    public $timestamps = false;
}
