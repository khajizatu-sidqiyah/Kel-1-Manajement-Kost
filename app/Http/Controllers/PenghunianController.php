<?php

namespace App\Http\Controllers;

use App\Models\Penghunian;
use App\Models\Kamar;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PenghunianController extends Controller
{
    // Menampilkan semua data penghunian
    public function index()
    {
        return response()->json([
            'message' => 'Data penghunian berhasil diambil.',
            'data' => Penghunian::with(['kamar', 'penghuni'])->get(),
        ], 200);
    }

    // Menambahkan data penghunian
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_kamar' => 'required|integer|exists:kamar,id_kamar',
            'id_penghuni' => 'required|integer|exists:penghuni,id_penghuni',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $hasil = DB::transaction(function () use ($data) {
            $kamar = Kamar::findOrFail($data['id_kamar']);

            // Cegah penghuni yang sama tercatat dua kali
            // pada kamar yang sama dengan status aktif.
            $sudahTerdaftar = Penghunian::where(
                'id_kamar',
                $data['id_kamar']
            )
                ->where('id_penghuni', $data['id_penghuni'])
                ->where('status', 'aktif')
                ->exists();

            if ($sudahTerdaftar) {
                return [
                    'error' => true,
                    'message' => 'Penghuni sudah terdaftar pada kamar ini.',
                ];
            }

            // Periksa apakah kamar sudah mempunyai penghuni aktif.
            $adaPenghuniAktif = Penghunian::where(
                'id_kamar',
                $data['id_kamar']
            )
                ->where('status', 'aktif')
                ->exists();

            // Penghuni aktif pertama menjadi penyewa utama.
            $data['penyewa_utama'] = !$adaPenghuniAktif;
            $data['status'] = 'aktif';

            $penghunian = Penghunian::create($data);

            // Ubah status kamar menjadi terisi.
            $kamar->status = 'terisi';
            $kamar->save();

            return [
                'error' => false,
                'data' => $penghunian->load(['kamar', 'penghuni']),
            ];
        });

        if ($hasil['error']) {
            return response()->json([
                'message' => $hasil['message'],
            ], 422);
        }

        return response()->json([
            'message' => 'Penghunian berhasil ditambahkan.',
            'data' => $hasil['data'],
        ], 201);
    }

    // Menampilkan satu data penghunian
    public function show($id)
    {
        $penghunian = Penghunian::with(['kamar', 'penghuni'])
            ->find($id);

        if (!$penghunian) {
            return response()->json([
                'message' => 'Data penghunian tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Data penghunian berhasil diambil.',
            'data' => $penghunian,
        ], 200);
    }
    
    // Memperbarui tanggal masuk pada data penghunian yang sudah ada.
    public function update(Request $request, $id)
    {
        $penghunian = Penghunian::find($id);

        if (!$penghunian) {
            return response()->json([
                'message' => 'Data penghunian tidak ditemukan.'
            ], 404);
        }

        $validated = $request->validate([
            'tanggal_mulai' => 'sometimes|nullable|date',
            'tanggal_selesai' => 'sometimes|nullable|date',
        ]);

        $penghunian->update($validated);

        return response()->json([
            'message' => 'Data penghunian berhasil diperbarui.',
            'data' => $penghunian->fresh()->load(['kamar', 'penghuni']),
        ], 200);
    }
    
    // Mengganti penyewa utama pada kamar yang sama.
    public function setPenyewaUtama(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'id_kamar' => 'required|integer|exists:kamar,id_kamar',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $penghunian = Penghunian::where('id_penghunian', $id)
            ->where('id_kamar', $request->id_kamar)
            ->where('status', 'aktif')
            ->first();

        if (!$penghunian) {
            return response()->json([
                'message' => 'Penghunian aktif tidak ditemukan pada kamar tersebut.',
            ], 404);
        }

        DB::transaction(function () use ($penghunian) {
            Penghunian::where('id_kamar', $penghunian->id_kamar)
                ->where('status', 'aktif')
                ->update(['penyewa_utama' => false]);

            $penghunian->penyewa_utama = true;
            $penghunian->save();
        });

        return response()->json([
            'message' => 'Penyewa utama berhasil diperbarui.',
            'data' => $penghunian->fresh()->load(['kamar', 'penghuni']),
        ], 200);
    }
    
public function catatDp(Request $request, $id)
{
    $penghunian = Penghunian::find($id);

    if (!$penghunian) {
        return response()->json([
            'message' => 'Data penghunian tidak ditemukan.',
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'tanggal_bayar' => 'required|date',
        'jumlah' => 'required|numeric|min:1',
        'metode_pembayaran' => 'required|string|max:100',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Data pembayaran tidak valid.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $data = $validator->validated();

// Cek apakah pembayaran untuk penghunian ini sudah ada
$dpSudahAda = Pembayaran::where(
    'id_penghunian',
    $penghunian->id_penghunian
)->exists();

if ($dpSudahAda) {
    return response()->json([
        'message' => 'DP untuk penghunian ini sudah tercatat.',
    ], 422);
}

// Baru simpan jika belum ada
$pembayaran = Pembayaran::create([
    'id_penghunian' => $penghunian->id_penghunian,
    'tanggal_bayar' => $data['tanggal_bayar'],
    'jumlah' => $data['jumlah'],
    'metode_pembayaran' => $data['metode_pembayaran'],
]);

}


}