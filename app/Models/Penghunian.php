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
    'skema_sewa',
    'total_sewa',
    'dp_minimum',
    'tenggat_pelunasan',
];

    protected function casts(): array
{
    return [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'penyewa_utama' => 'boolean',
        'tenggat_pelunasan' => 'date',
        'total_sewa' => 'decimal:2',
        'dp_minimum' => 'decimal:2',
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

    public function pembayaran()
    {
        return $this->hasMany(\App\Models\Pembayaran::class, 'id_penghunian', 'id_penghunian');
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