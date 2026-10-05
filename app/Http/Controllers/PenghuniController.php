<?php

namespace App\Http\Controllers;

use App\Models\Penghuni;
use Illuminate\Support\Facades\Auth;

class PenghuniController extends Controller
{
    /**
     * Penghuni hanya boleh melihat data miliknya sendiri.
     */
    public function show($id)
    {
        $penghuni = Penghuni::findOrFail($id);

        if ((int) $penghuni->user_id !== (int) Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke data penghuni ini.');
        }

        return response()->json([
            'message' => 'Data penghuni berhasil diakses.',
            'data' => $penghuni,
        ]);
    }
}