<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Pembayaran;

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
        'status_pembayaran',
    ];

    protected $appends = [
        'kamar_sudah_ada_dp',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tenggat_pelunasan' => 'date',
            'penyewa_utama' => 'boolean',
            'total_sewa' => 'float',
            'dp_minimum' => 'float',
        ];
    }

    /**
     * Mengecek apakah masa sewa aktif pada kamar
     * sudah memiliki pembayaran DP.
     */
    public function getKamarSudahAdaDpAttribute(): bool
    {
        $idPenghunianAktif = self::where(
            'id_kamar',
            $this->id_kamar
        )
            ->where('status', 'aktif')
            ->pluck('id_penghunian');

        return Pembayaran::whereIn(
            'id_penghunian',
            $idPenghunianAktif
        )
            ->where('jenis_pembayaran', 'dp')
            ->exists();
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

    public function pembayaran()
    {
        return $this->hasMany(
            Pembayaran::class,
            'id_penghunian',
            'id_penghunian'
        );
    }

    public function pembayaranTerverifikasi()
    {
        return $this->hasMany(
            Pembayaran::class,
            'id_penghunian',
            'id_penghunian'
        )->where('status', 'terverifikasi');
    }

    public function getTotalTerbayarAttribute()
    {
        return (float) $this->pembayaranTerverifikasi()
            ->sum('jumlah');
    }

    public function getSisaTagihanAttribute()
    {
        $totalSewa = (float) ($this->total_sewa ?? 0);

        return max(
            0,
            $totalSewa - $this->total_terbayar
        );
    }
}
