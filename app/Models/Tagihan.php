<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tagihan extends Model
{
    protected $table = 'tagihan';

    protected $primaryKey = 'id_tagihan';

    protected $fillable = [
        'id_penghunian',
        'jenis_tagihan',
        'periode',
        'kode_periode',
        'jatuh_tempo',
        'jumlah_tagihan',
        'jumlah_terbayar',
        'sisa_tagihan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'jatuh_tempo' => 'date',
            'jumlah_tagihan' => 'decimal:2',
            'jumlah_terbayar' => 'decimal:2',
            'sisa_tagihan' => 'decimal:2',
        ];
    }

    public function penghunian()
    {
        return $this->belongsTo(
            Penghunian::class,
            'id_penghunian',
            'id_penghunian'
        );
    }

    public function alokasiPembayaran()
    {
        return $this->hasMany(
            AlokasiPembayaran::class,
            'id_tagihan',
            'id_tagihan'
        );
    }
}