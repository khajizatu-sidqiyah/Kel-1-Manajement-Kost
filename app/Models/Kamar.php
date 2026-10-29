<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kamar extends Model
{
    protected $table = 'kamar';

    protected $primaryKey = 'id_kamar';

    public function kos()
    {
        return $this->belongsTo(Kos::class, 'id_kost', 'id_kost');
    }

    protected $fillable = [
        'no_kamar',
        'tipe_kamar',
        'harga',
        'harga_tahunan',
        'status',
        'id_kost',
    ];
}