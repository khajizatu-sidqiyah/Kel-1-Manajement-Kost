<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kamar extends Model
{
    protected $table = 'kamar';

    protected $primaryKey = 'id_kamar';

    protected $fillable = [
        'no_kamar',
        'tipe_kamar',
        'harga',
        'status',
        'id_kost',
    ];
}