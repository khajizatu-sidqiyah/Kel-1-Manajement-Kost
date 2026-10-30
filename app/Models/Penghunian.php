<?php

namespace app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penghunian extends Model
{
    use HasFactory;

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

    /**
     * Konversi tipe data otomatis.
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tenggat_pelunasan' => 'date',

            'penyewa_utama' => 'boolean',

            'total_sewa' => 'decimal:2',
            'dp_minimum' => 'decimal:2',
        ];
    }

    /**
     * Relasi ke tabel kamar.
     */
    public function kamar()
    {
        return $this->belongsTo(
            Kamar::class,
            'id_kamar',
            'id_kamar'
        );
    }

    /**
     * Relasi ke tabel penghuni.
     */
    public function penghuni()
    {
        return $this->belongsTo(
            Penghuni::class,
            'id_penghuni',
            'id_penghuni'
        );
    }

    /**
     * Relasi ke tabel pembayaran.
     */
    public function pembayaran()
    {
        return $this->hasMany(
            Pembayaran::class,
            'id_penghunian',
            'id_penghunian'
        );
    }

    /**
     * Relasi untuk mengambil pembayaran yang sudah terverifikasi.
     */
    public function pembayaranTerverifikasi()
    {
        return $this->hasMany(
            Pembayaran::class,
            'id_penghunian',
            'id_penghunian'
        )->where('status', 'terverifikasi');
    }

    /**
     * Menghitung total pembayaran yang sudah terverifikasi.
     */
    public function getTotalTerbayarAttribute()
    {
        return (float) $this->pembayaranTerverifikasi()
            ->sum('jumlah');
    }

    /**
     * Menghitung sisa tagihan.
     */
    public function getSisaTagihanAttribute()
    {
        $totalSewa = (float) ($this->total_sewa ?? 0);

        return max(
            0,
            $totalSewa - $this->total_terbayar
        );
    }
}