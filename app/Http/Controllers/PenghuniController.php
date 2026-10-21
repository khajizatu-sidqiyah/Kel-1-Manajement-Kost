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
            'message' => '',
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
            // Diubah menjadi maksimal 13 digit
            'no_telepon' => 'nullable|numeric|digits_between:10,13',
            'email' => 'nullable|email|max:100',
            'alamat' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid request parameters.',
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'status' => 'INVALID_ARGUMENT',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $penghuni = Penghuni::create($validator->validated());

        return response()->json([
            'message' => 'Penghuni berhasil ditambahkan.',
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
                'message' => 'Data penghuni tidak ditemukan.',
                'error' => [
                    'code' => 404,
                    'message' => "Penghuni dengan ID {$id} tidak ditemukan.",
                    'status' => 'NOT_FOUND'
                ]
            ], 404);
        }

        return response()->json([
            'message' => '',
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
                'message' => 'Data penghuni tidak ditemukan.',
                'error' => [
                    'code' => 404,
                    'message' => "Penghuni dengan ID {$id} tidak ditemukan.",
                    'status' => 'NOT_FOUND'
                ]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_penghuni' => 'sometimes|required|string|max:100',
            // Diubah menjadi maksimal 13 digit
            'no_telepon' => 'sometimes|nullable|numeric|digits_between:10,13',
            'email' => 'sometimes|nullable|email|max:100',
            'alamat' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid request parameters.',
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'status' => 'INVALID_ARGUMENT',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $penghuni->update($validator->validated());

        return response()->json([
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
                'message' => 'Data penghuni tidak ditemukan.',
                'error' => [
                    'code' => 404,
                    'message' => "Penghuni dengan ID {$id} tidak ditemukan.",
                    'status' => 'NOT_FOUND'
                ]
            ], 404);
        }

        $penghuni->delete();

        return response()->json([
            'message' => 'Data penghuni berhasil dihapus.',
            'data' => null,
        ], 200);
    }
}