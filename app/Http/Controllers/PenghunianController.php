<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\PengaturanSewa;
use App\Models\Penghunian;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PenghunianController extends Controller
{
    // Menampilkan semua data penghunian.
    public function index()
    {
        return response()->json([
            'message' => 'Data penghunian berhasil diambil.',
            'data' => Penghunian::with([
                'kamar',
                'penghuni',
                'pembayaran',
            ])->get(),
        ], 200);
    }

    // Menambahkan data penghunian.
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

            $adaPenghuniAktif = Penghunian::where(
                'id_kamar',
                $data['id_kamar']
            )
                ->where('status', 'aktif')
                ->exists();

            $data['penyewa_utama'] = !$adaPenghuniAktif;
            $data['status'] = 'aktif';

            $penghunian = Penghunian::create($data);

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

    // Memperbarui tanggal mulai atau tanggal selesai penghunian.
    public function update(Request $request, $id)
    {
        $penghunian = Penghunian::find($id);

        if (!$penghunian) {
            return response()->json([
                'message' => 'Data penghunian tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'tanggal_mulai' => 'sometimes|nullable|date',
            'tanggal_selesai' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:tanggal_mulai',
            ],
        ]);

        $penghunian->update($validated);

        return response()->json([
            'message' => 'Data penghunian berhasil diperbarui.',
            'data' => $penghunian->fresh()->load([
                'kamar',
                'penghuni',
                'pembayaran',
            ]),
        ], 200);
    }

    // Memindahkan penanda penyewa utama pada satu kamar.
    public function setPenyewaUtama(Request $request, $id)
    {
        $validated = $request->validate([
            'id_kamar' => 'required|integer|exists:kamar,id_kamar',
        ]);

        $target = Penghunian::where('id_penghunian', $id)
            ->where('id_kamar', $validated['id_kamar'])
            ->where('status', 'aktif')
            ->first();

        if (!$target) {
            return response()->json([
                'message' => 'Penghunian aktif untuk kamar tersebut tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($target, $validated) {
            Penghunian::where('id_kamar', $validated['id_kamar'])
                ->where('status', 'aktif')
                ->update(['penyewa_utama' => false]);

            $target->update(['penyewa_utama' => true]);
        });

        return response()->json([
            'message' => 'Penyewa utama berhasil diubah.',
            'data' => $target->fresh()->load(['kamar', 'penghuni']),
        ], 200);
    }

    // Mencatat DP sekali untuk satu kamar.
    public function catatDp(Request $request, $id)
    {
        $penghunian = Penghunian::with('kamar')->find($id);

        if (!$penghunian || $penghunian->status !== 'aktif') {
            return response()->json([
                'message' => 'Data penghunian aktif tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'tanggal_bayar' => 'required|date',
            'jumlah' => 'required|numeric|min:1',
            'metode_pembayaran' => 'required|string|max:30',
        ]);

        $duplikat = DB::table('pembayaran')
            ->join(
                'penghunian',
                'pembayaran.id_penghunian',
                '=',
                'penghunian.id_penghunian'
            )
            ->where('penghunian.id_kamar', $penghunian->id_kamar)
            ->where(function ($query) {
                $query->where('pembayaran.status', 'dp')
                    ->orWhere(
                        'pembayaran.metode_pembayaran',
                        'like',
                        'DP%'
                    );
            })
            ->exists();

        if ($duplikat) {
            return response()->json([
                'message' => 'DP untuk kamar ini sudah tercatat.',
            ], 422);
        }

        $paymentId = DB::table('pembayaran')->insertGetId([
            'id_penghunian' => $penghunian->id_penghunian,
            'tanggal_bayar' => $validated['tanggal_bayar'],
            'jumlah' => $validated['jumlah'],
            'metode_pembayaran' =>
                'DP - ' . $validated['metode_pembayaran'],
            'status' => 'dp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'DP berhasil dicatat.',
            'data' => DB::table('pembayaran')
                ->where('id_pembayaran', $paymentId)
                ->first(),
        ], 201);
    }

    // Menghitung DP minimum dan tenggat pelunasan.
    public function hitung(Request $request, $id)
    {
        $data = $request->validate([
            'skema_sewa' => [
                'required',
                Rule::in(['bulanan', 'tahunan']),
            ],
        ], [
            'skema_sewa.required' =>
                'Pilih skema sewa terlebih dahulu.',
            'skema_sewa.in' =>
                'Skema sewa harus bulanan atau tahunan.',
        ]);

        $penghunian = Penghunian::with('kamar')->findOrFail($id);

        // Harga kamar wajib tersedia dan lebih besar dari nol.
        if (
            !$penghunian->kamar ||
            $penghunian->kamar->harga === null ||
            $penghunian->kamar->harga === '' ||
            !is_numeric($penghunian->kamar->harga) ||
            (float) $penghunian->kamar->harga <= 0
        ) {
            return response()->json([
                'message' => 'Harga sewa kamar belum diisi',
            ], 422);
        }

        $hargaBulanan = (float) $penghunian->kamar->harga;

        if ($data['skema_sewa'] === 'bulanan') {
            // DP bulanan minimal 50% dari harga sewa.
            $totalSewa = $hargaBulanan;
            $dpMinimum = $hargaBulanan * 0.5;
        } else {
            // Nilai DP tahunan dibaca dari pengaturan database.
            $pengaturan = PengaturanSewa::first();

            if (
                !$pengaturan ||
                $pengaturan->dp_minimum_tahunan === null ||
                !is_numeric($pengaturan->dp_minimum_tahunan) ||
                (float) $pengaturan->dp_minimum_tahunan <= 0
            ) {
                return response()->json([
                    'message' =>
                        'Pengaturan DP minimum tahunan belum tersedia atau tidak valid.',
                ], 422);
            }

            $totalSewa = $hargaBulanan * 12;
            $dpMinimum = (float) $pengaturan->dp_minimum_tahunan;
        }

        // DP tetap dihitung meskipun tanggal masuk belum diisi.
        $tanggalMulai = $penghunian->tanggal_mulai;
        $tenggat = null;

        if ($tanggalMulai) {
            $tanggalMulai = Carbon::parse($tanggalMulai);

            if ($data['skema_sewa'] === 'bulanan') {
                // Tenggat awal bulanan mengikuti tanggal masuk.
                $tenggat = $tanggalMulai->toDateString();
            } else {
                // Tenggat tahunan adalah tiga bulan setelah tanggal masuk.
                $tenggat = $tanggalMulai
                    ->copy()
                    ->addMonthsNoOverflow(3)
                    ->toDateString();
            }
        }

        $penghunian->update([
            'skema_sewa' => $data['skema_sewa'],
            'total_sewa' => $totalSewa,
            'dp_minimum' => $dpMinimum,
            'tenggat_pelunasan' => $tenggat,
        ]);

        return response()->json([
            'message' => $tanggalMulai
                ? 'Perhitungan DP dan tenggat berhasil disimpan.'
                : 'DP berhasil dihitung. Isi tanggal masuk untuk menghitung tenggat.',
            'skema_sewa' => $data['skema_sewa'],
            'total_sewa' => $totalSewa,
            'dp_minimum' => $dpMinimum,
            'tenggat_pelunasan' => $tenggat,
        ], 200);
    }

    // Mengubah tenggat pelunasan sewa tahunan.
    public function ubahTenggat(Request $request, $id)
    {
        $data = $request->validate([
            'tenggat_pelunasan' => [
                'required',
                'date',
            ],
        ], [
            'tenggat_pelunasan.required' =>
                'Tanggal tenggat pelunasan wajib diisi.',
            'tenggat_pelunasan.date' =>
                'Tanggal tenggat pelunasan tidak valid.',
        ]);

        $penghunian = Penghunian::findOrFail($id);

        if ($penghunian->skema_sewa !== 'tahunan') {
            return response()->json([
                'message' =>
                    'Pengubahan tenggat hanya berlaku untuk sewa tahunan.',
            ], 422);
        }

        if (!$penghunian->tanggal_mulai) {
            return response()->json([
                'message' =>
    'Isi tanggal masuk terlebih dahulu sebelum mengubah tenggat.',
            ], 422);
        }

        $tanggalMulai = Carbon::parse($penghunian->tanggal_mulai);
        $tanggalTenggat = Carbon::parse($data['tenggat_pelunasan']);

        if ($tanggalTenggat->lt($tanggalMulai)) {
            return response()->json([
                'message' =>
                    'Tenggat tidak boleh sebelum tanggal mulai sewa.',
            ], 422);
        }

        $penghunian->update([
            'tenggat_pelunasan' => $tanggalTenggat->toDateString(),
        ]);

        return response()->json([
            'message' => 'Tenggat pelunasan berhasil diubah.',
            'tenggat_pelunasan' => $tanggalTenggat->toDateString(),
        ], 200);
    }

    // Menghitung tenggat bulanan berikutnya.
    public function tenggatBerikutnya($id)
    {
        $penghunian = Penghunian::findOrFail($id);

        if ($penghunian->skema_sewa !== 'bulanan') {
            return response()->json([
                'message' => 'Fitur ini hanya berlaku untuk sewa bulanan.',
            ], 422);
        }

        if (!$penghunian->tenggat_pelunasan) {
            return response()->json([
                'message' => 'Tenggat pelunasan saat ini belum tersedia.',
            ], 422);
        }

        $tenggatSekarang = Carbon::parse(
            $penghunian->tenggat_pelunasan
        );

        $tanggalBerikutnya = $tenggatSekarang
            ->copy()
            ->addMonthNoOverflow();

        // Jika tanggal sekarang akhir bulan, pertahankan akhir bulan.
        if ($tenggatSekarang->isLastOfMonth()) {
            $tanggalBerikutnya->endOfMonth();
        }

        $penghunian->update([
            'tenggat_pelunasan' => $tanggalBerikutnya->toDateString(),
        ]);

        return response()->json([
            'message' => 'Tenggat berikutnya berhasil dihitung.',
            'tenggat_sekarang' => $tenggatSekarang->toDateString(),
            'tenggat_berikutnya' => $tanggalBerikutnya->toDateString(),
        ], 200);
    }

    // Menampilkan satu data penghunian.
    public function show($id)
    {
        $penghunian = Penghunian::with([
            'kamar',
            'penghuni',
        ])->find($id);

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