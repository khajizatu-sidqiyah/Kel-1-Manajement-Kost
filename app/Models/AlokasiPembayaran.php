<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlokasiPembayaran extends Model
{
    protected $table = 'alokasi_pembayaran';

    protected $primaryKey = 'id_alokasi';

    protected $fillable = [
        'id_tagihan',
        'id_pembayaran',
        'jumlah_dialokasikan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_dialokasikan' => 'decimal:2',
        ];
    }

    public function tagihan()
    {
        return $this->belongsTo(
            Tagihan::class,
            'id_tagihan',
            'id_tagihan'
        );
    }

    public function pembayaran()
    {
        return $this->belongsTo(
            Pembayaran::class,
            'id_pembayaran',
            'id_pembayaran'
        );
    }
}