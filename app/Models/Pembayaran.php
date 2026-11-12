<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Pembayaran extends Model
{
    protected $table = 'pembayaran';

    protected $primaryKey = 'id_pembayaran';

    protected $fillable = [
        'id_penghunian',
        'jenis_pembayaran',
        'tanggal_bayar',
        'periode_tagihan',
        'jumlah',
        'metode_pembayaran',
        'bukti_pembayaran',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_bayar' => 'date',
            'periode_tagihan' => 'date',
            'jumlah' => 'decimal:2',
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

    public function scopeTerverifikasi(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'terverifikasi'
        );
    }

    public function scopeMenungguVerifikasi(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'menunggu_verifikasi'
        );
    }
}
