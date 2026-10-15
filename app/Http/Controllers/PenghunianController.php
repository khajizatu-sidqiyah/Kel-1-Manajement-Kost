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
            'data' => Penghunian::with(['kamar', 'penghuni'])->get(),
        ], 200);
    }

    // Menambahkan data penghunian
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_kamar' => 'required|integer|exists:kamar,id_kamar',
            'id_penghuni' => 'required|integer|exists:penghuni,id_penghuni',
            'tanggal_mulai' => 'required|date',
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
}