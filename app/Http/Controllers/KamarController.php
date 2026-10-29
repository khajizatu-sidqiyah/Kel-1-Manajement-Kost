<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\Kos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KamarController extends Controller
{
    public function index()
    {
        return response()->json(['message' => '', 'data' => Kamar::with('kos')->get()], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_kamar' => ['required', 'string', 'max:20'],
            'tipe_kamar' => ['nullable', 'string', 'max:50'],
            'harga' => ['required', 'numeric', 'min:0'],
            'harga_tahunan' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:kosong,terisi'],
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Parameter permintaan tidak valid.', 'error' => ['code' => 422, 'message' => 'Validasi gagal.', 'status' => 'INVALID_ARGUMENT', 'details' => $validator->errors()]], 422);
        $kos = Kos::query()->first();
        if (!$kos) return response()->json(['message' => 'Data kost belum tersedia.', 'error' => ['code' => 422, 'message' => 'Kost belum dikonfigurasi.', 'status' => 'INVALID_ARGUMENT', 'details' => (object) []]], 422);
        $data = $validator->validated();
        $data['id_kost'] = $kos->id_kost;
        $data['status'] = $data['status'] ?? 'kosong';
        $kamar = Kamar::create($data);
        return response()->json(['message' => 'Data kamar berhasil ditambahkan.', 'data' => $kamar->load('kos')], 201);
    }

    public function show($id)
    {
        $kamar = Kamar::with('kos')->find($id);
        if (!$kamar) return response()->json(['message' => 'Data kamar tidak ditemukan.', 'error' => ['code' => 404, 'message' => "Kamar dengan ID {$id} tidak ditemukan.", 'status' => 'NOT_FOUND', 'details' => (object) []]], 404);
        return response()->json(['message' => '', 'data' => $kamar], 200);
    }

    public function update(Request $request, $id)
    {
        $kamar = Kamar::find($id);
        if (!$kamar) return response()->json(['message' => 'Data kamar tidak ditemukan.', 'error' => ['code' => 404, 'message' => "Kamar dengan ID {$id} tidak ditemukan.", 'status' => 'NOT_FOUND', 'details' => (object) []]], 404);
        $validator = Validator::make($request->all(), [
            'no_kamar' => ['sometimes', 'required', 'string', 'max:20'],
            'tipe_kamar' => ['sometimes', 'nullable', 'string', 'max:50'],
            'harga' => ['sometimes', 'required', 'numeric', 'min:0'],
            'harga_tahunan' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', 'in:kosong,terisi'],
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Parameter permintaan tidak valid.', 'error' => ['code' => 422, 'message' => 'Validasi gagal.', 'status' => 'INVALID_ARGUMENT', 'details' => $validator->errors()]], 422);
        $kamar->update($validator->validated());
        return response()->json(['message' => 'Data kamar berhasil diperbarui.', 'data' => $kamar->fresh()->load('kos')], 200);
    }

    public function destroy($id)
    {
        $kamar = Kamar::find($id);
        if (!$kamar) return response()->json(['message' => 'Data kamar tidak ditemukan.', 'error' => ['code' => 404, 'message' => "Kamar dengan ID {$id} tidak ditemukan.", 'status' => 'NOT_FOUND', 'details' => (object) []]], 404);
        $kamar->delete();
        return response()->json(['message' => 'Data kamar berhasil dihapus.', 'data' => null], 200);
    }
}
