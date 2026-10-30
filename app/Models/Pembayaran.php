<?php

namespace app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Penghunian;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';

    protected $primaryKey = 'id_pembayaran';

    protected $fillable = [
        'id_penghunian',
        'jenis_pembayaran',
        'tanggal_bayar',
        'jumlah',
        'metode_pembayaran',
        'bukti_pembayaran',
        'status',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date',
        'jumlah' => 'decimal:2',
    ];

    /**
     * Relasi ke data penghunian.
     * Satu pembayaran dimiliki oleh satu data penghunian.
     */
    public function penghunian()
    {
        return $this->belongsTo(
            Penghunian::class,
            'id_penghunian',
            'id_penghunian'
        );
    }

    /**
     * Scope untuk mengambil pembayaran yang sudah terverifikasi.
     */
    public function scopeTerverifikasi($query)
    {
        return $query->where('status', 'terverifikasi');
    }

    /**
     * Scope untuk mengambil pembayaran yang masih menunggu verifikasi.
     */
    public function scopeMenungguVerifikasi($query)
    {
        return $query->where('status', 'menunggu_verifikasi');
    }
}