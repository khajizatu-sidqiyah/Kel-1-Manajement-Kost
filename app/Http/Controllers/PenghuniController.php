<?php

namespace App\Http\Controllers;

use App\Models\Penghuni;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PenghuniController extends Controller
{
    /**
     * Menampilkan semua data penghuni.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Data penghuni berhasil diambil.',
            'data' => Penghuni::all(),
        ], 200);
    }

    /**
     * Menambahkan penghuni baru.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_penghuni' => 'required|string|max:100',
            // Ubah 'string' menjadi 'numeric' (atau gunakan regex jika ingin aman dari simbol minus/plus)
            'no_telepon' => 'nullable|numeric|digits_between:10,20',
            'email' => 'nullable|email|max:100',
            'alamat' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $penghuni = Penghuni::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Penghuni berhasil ditambahkan',
            'data' => $penghuni,
        ], 201);
    }

    /**
     * Menampilkan detail satu penghuni.
     */
    public function show($id)
    {
        $penghuni = Penghuni::find($id);

        if (!$penghuni) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghuni tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data penghuni berhasil diakses.',
            'data' => $penghuni,
        ], 200);
    }

    /**
     * Memperbarui data penghuni.
     */
    public function update(Request $request, $id)
    {
        $penghuni = Penghuni::find($id);

        if (!$penghuni) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghuni tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'nama_penghuni' => 'sometimes|required|string|max:100',
            // Ubah 'string' menjadi 'numeric' di sini juga
            'no_telepon' => 'sometimes|nullable|numeric|digits_between:10,20',
            'email' => 'sometimes|nullable|email|max:100',
            'alamat' => 'sometimes|nullable|string',
        ]);

        $penghuni->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data penghuni berhasil diperbarui.',
            'data' => $penghuni->fresh(),
        ], 200);
    }

    /**
     * Menghapus data penghuni.
     */
    public function destroy($id)
    {
        $penghuni = Penghuni::find($id);

        if (!$penghuni) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghuni tidak ditemukan.',
            ], 404);
        }

        $penghuni->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data penghuni berhasil dihapus.',
        ], 200);
    }
}