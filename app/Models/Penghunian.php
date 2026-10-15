<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penghunian extends Model
{
    protected $table = 'penghunian';

    protected $primaryKey = 'id_penghunian';

    protected $fillable = [
        'id_kamar',
        'id_penghuni',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'penyewa_utama',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'penyewa_utama' => 'boolean',
        ];
    }

    public function kamar()
    {
        return $this->belongsTo(
            Kamar::class,
            'id_kamar',
            'id_kamar'
        );
    }

    public function penghuni()
    {
        return $this->belongsTo(
            Penghuni::class,
            'id_penghuni',
            'id_penghuni'
        );
    }
}