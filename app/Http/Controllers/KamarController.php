<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use Illuminate\Http\Request;

class KamarController extends Controller
{
    /**
     * GET /api/kamar
     * Menampilkan semua data kamar.
     */
    public function index()
    {
        $kamar = Kamar::all();

        return response()->json([
            'success' => true,
            'message' => 'Data kamar berhasil diambil',
            'data' => $kamar
        ], 200);
    }

    /**
     * POST /api/kamar
     * Menambahkan data kamar.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_kost' => ['required', 'exists:kos,id_kost'],
            'no_kamar' => ['required', 'string', 'max:20'],
            'tipe_kamar' => ['nullable', 'string', 'max:50'],
            'harga' => ['required', 'numeric'],
            'status' => ['required', 'in:kosong,terisi'],
        ]);

        $kamar = Kamar::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data kamar berhasil ditambahkan',
            'data' => $kamar
        ], 201);
    }

    /**
     * PUT /api/kamar/{id}
     * Mengubah data kamar.
     */
    public function update(Request $request, $id)
    {
        $kamar = Kamar::find($id);

        if (!$kamar) {
            return response()->json([
                'success' => false,
                'message' => 'Data kamar tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'id_kost' => ['required', 'exists:kos,id_kost'],
            'no_kamar' => ['required', 'string', 'max:20'],
            'tipe_kamar' => ['nullable', 'string', 'max:50'],
            'harga' => ['required', 'numeric'],
            'status' => ['required', 'in:kosong,terisi'],
        ]);

        $kamar->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data kamar berhasil diperbarui',
            'data' => $kamar
        ], 200);
    }

    public function show($id)
    {
        // Mencari kamar berdasarkan ID
        $kamar = Kamar::find($id);

        // Jika kamar dengan ID 26 tidak ditemukan di database
        if (!$kamar) {
            return response()->json([
                'message' => 'Data kamar tidak ditemukan.',
                'error' => [
                    'code' => 404,
                    'message' => "Kamar dengan ID {$id} tidak ditemukan.",
                    'status' => 'NOT_FOUND'
                ]
            ], 404);
        }

        // Jika kamar ditemukan, kembalikan dengan format wrapper standar
        return response()->json([
            'message' => '',
            'data' => $kamar
        ], 200);
    }

    public function destroy($id)
{
    // 1. Cari data kamar berdasarkan ID
    $kamar = Kamar::find($id);

    // 2. Jika kamar tidak ditemukan, kembalikan 404
    if (!$kamar) {
        return response()->json([
            'message' => 'Data kamar tidak ditemukan.',
            'error' => [
                'code' => 404,
                'message' => "Kamar dengan ID {$id} tidak ditemukan.",
                'status' => 'NOT_FOUND'
            ]
        ], 404);
    }

    // 3. Hapus data dari database
    $kamar->delete();

    // 4. Kembalikan response sukses
    return response()->json([
        'message' => 'Data kamar berhasil dihapus.',
        'data' => null
    ], 200);
}
}