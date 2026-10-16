<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanSewa extends Model
{
    protected $table = 'pengaturan_sewa';

    protected $primaryKey = 'id_pengaturan';

    protected $fillable = [
        'dp_minimum_tahunan',
    ];

    protected function casts(): array
    {
        return [
            'dp_minimum_tahunan' => 'decimal:2',
        ];
    }
}