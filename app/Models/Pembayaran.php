<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pembayaran extends Model
{
    protected $table = 'pembayaran';

    protected $primaryKey = 'id_pembayaran';

    protected $fillable = [
        'id_penghunian',
        'tanggal_bayar',
        'jumlah',
        'metode_pembayaran',
        'status',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date',
        'jumlah' => 'decimal:2',
    ];

    public function penghunian()
    {
        return $this->belongsTo(
            Penghunian::class,
            'id_penghunian',
            'id_penghunian'
        );
    }
}
