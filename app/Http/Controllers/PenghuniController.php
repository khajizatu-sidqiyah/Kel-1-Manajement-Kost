<?php

namespace App\Http\Controllers;

use App\Models\Penghuni;
use Illuminate\Support\Facades\Auth;

class PenghuniController extends Controller
{
    /**
     * Mengakses data penghuni berdasarkan ID.
     * Penghuni hanya boleh mengakses data miliknya sendiri.
     */
    public function show($id)
    {
        $penghuni = Penghuni::findOrFail($id);

        // Pastikan data tersebut milik user yang sedang login
        if ($penghuni->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke data penghuni ini.');
        }

        return response()->json($penghuni);
    }
}