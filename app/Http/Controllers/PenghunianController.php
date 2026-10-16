<?php

namespace App\Http\Controllers;

use App\Models\Penghunian;
use App\Models\Kamar;
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
            'data' => Penghunian::with(['kamar', 'penghuni', 'pembayaran'])->get(),
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


    // Memperbarui tanggal mulai atau status relasi penghuni-kamar.
    public function update(Request $request, $id)
    {
        $penghunian = Penghunian::find($id);
        if (!$penghunian) {
            return response()->json(['message' => 'Data penghunian tidak ditemukan.'], 404);
        }
        $validated = $request->validate([
            'tanggal_mulai' => 'sometimes|nullable|date',
            'tanggal_selesai' => 'sometimes|nullable|date|after_or_equal:tanggal_mulai',
        ]);
        $penghunian->update($validated);
        return response()->json([
            'message' => 'Data penghunian berhasil diperbarui.',
            'data' => $penghunian->fresh()->load(['kamar', 'penghuni', 'pembayaran']),
        ]);
    }

    // Memindahkan penanda penyewa utama pada satu kamar secara atomik.
    public function setPenyewaUtama(Request $request, $id)
    {
        $validated = $request->validate(['id_kamar' => 'required|integer|exists:kamar,id_kamar']);
        $target = Penghunian::where('id_penghunian', $id)
            ->where('id_kamar', $validated['id_kamar'])->where('status', 'aktif')->first();
        if (!$target) {
            return response()->json(['message' => 'Penghunian aktif untuk kamar tersebut tidak ditemukan.'], 404);
        }
        DB::transaction(function () use ($target, $validated) {
            Penghunian::where('id_kamar', $validated['id_kamar'])->where('status', 'aktif')
                ->update(['penyewa_utama' => false]);
            $target->update(['penyewa_utama' => true]);
        });
        return response()->json(['message' => 'Penyewa utama berhasil diubah.', 'data' => $target->fresh()->load(['kamar', 'penghuni'])]);
    }

    // Catat DP sekali per kamar. Pembayaran DP ditandai pada kolom status.
    public function catatDp(Request $request, $id)
    {
        $penghunian = Penghunian::with('kamar')->find($id);
        if (!$penghunian || $penghunian->status !== 'aktif') {
            return response()->json(['message' => 'Data penghunian aktif tidak ditemukan.'], 404);
        }
        $validated = $request->validate([
            'tanggal_bayar' => 'required|date',
            'jumlah' => 'required|numeric|min:1',
            'metode_pembayaran' => 'required|string|max:30',
        ]);
        $duplikat = DB::table('pembayaran')
            ->join('penghunian', 'pembayaran.id_penghunian', '=', 'penghunian.id_penghunian')
            ->where('penghunian.id_kamar', $penghunian->id_kamar)
            ->where(function ($q) {
                $q->where('pembayaran.status', 'dp')->orWhere('pembayaran.metode_pembayaran', 'like', 'DP%');
            })->exists();
        if ($duplikat) {
            return response()->json(['message' => 'DP untuk kamar ini sudah tercatat.'], 422);
        }
        $paymentId = DB::table('pembayaran')->insertGetId([
            'id_penghunian' => $penghunian->id_penghunian,
            'tanggal_bayar' => $validated['tanggal_bayar'],
            'jumlah' => $validated['jumlah'],
            'metode_pembayaran' => 'DP - '.$validated['metode_pembayaran'],
            'status' => 'dp',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'DP berhasil dicatat.', 'data' => DB::table('pembayaran')->where('id_pembayaran', $paymentId)->first()], 201);
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
}