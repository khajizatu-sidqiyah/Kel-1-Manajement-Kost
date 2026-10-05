<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kos extends Model
{
    protected $table = 'kos';

    protected $primaryKey = 'id_kost';

    protected $fillable = [
        'nama_kost',
        'alamat',
        'deskripsi',
        'fasilitas',
        'id_pemilik',
    ];
}