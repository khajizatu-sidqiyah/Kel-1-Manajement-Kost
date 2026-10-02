<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Penghuni extends Model
{
    protected $table = 'penghuni';

    protected $primaryKey = 'id_penghuni';

    protected $fillable = [
        'user_id',
        'nama_penghuni',
        'no_telepon',
        'email',
        'alamat',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}