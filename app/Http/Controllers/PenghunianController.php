<?php

namespace app\Http\Controllers;

use App\Models\Kamar;
use App\Models\PengaturanSewa;
use App\Models\Penghuni;
use App\Models\Penghunian;
use App\Models\Pembayaran;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PenghunianController extends Controller
{
    /**
     * Menampilkan seluruh data penghunian.
     */
    public function index()
    {
        $penghunian = Penghunian::with([
            'kamar',
            'penghuni',
            'pembayaran',
        ])->orderByDesc('id_penghunian')->get();

        return response()->json([
            'message' => 'Data penghunian berhasil diambil.',
            'data' => $penghunian,
        ]);
    }

    /**
     * Menambahkan penghuni baru sekaligus membuat penghunian.
     */
    public function storeDenganPenghuni(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_kamar' => [
                'required',
                'integer',
                'exists:kamar,id_kamar',
            ],
            'nama_penghuni' => [
                'required',
                'string',
                'max:255',
            ],
            'no_telepon' => [
                'nullable',
                'string',
                'max:20',
            ],
            'alamat' => [
                'required',
                'string',
            ],
            'tanggal_mulai' => [
                'nullable',
                'date',
            ],
            'tanggal_selesai' => [
                'nullable',
                'date',
                'after_or_equal:tanggal_mulai',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data yang dikirim tidak valid.',
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $data = $validator->validated();

        $hasil = DB::transaction(function () use ($data) {

            $kamar = Kamar::where(
                'id_kamar',
                $data['id_kamar']
            )->lockForUpdate()->firstOrFail();

            $adaPenghuniAktif = Penghunian::where(
                'id_kamar',
                $kamar->id_kamar
            )
                ->where('status', 'aktif')
                ->exists();

            $penghuni = Penghuni::create([
                'nama_penghuni' => $data['nama_penghuni'],
                'no_telepon' => $data['no_telepon'] ?? null,
                'alamat' => $data['alamat'],
            ]);

            $penghunian = Penghunian::create([
                'id_kamar' => $kamar->id_kamar,
                'id_penghuni' => $penghuni->id_penghuni,
                'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
                'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
                'status' => 'aktif',
                'penyewa_utama' => !$adaPenghuniAktif,
                'status_pembayaran' => 'belum_lunas',
            ]);

            $kamar->update([
                'status' => 'terisi',
            ]);

            return [
                'penghuni' => $penghuni,
                'penghunian' => $penghunian->load([
                    'kamar',
                    'penghuni',
                    'pembayaran',
                ]),
            ];
        });

        return response()->json([
            'message' => 'Penghuni dan penghunian berhasil ditambahkan.',
            'data' => $hasil,
        ], 201);
    }

    /**
     * Menambahkan penghunian untuk penghuni yang sudah terdaftar.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_kamar' => [
                'required',
                'integer',
                'exists:kamar,id_kamar',
            ],
            'id_penghuni' => [
                'required',
                'integer',
                'exists:penghuni,id_penghuni',
            ],
            'tanggal_mulai' => [
                'nullable',
                'date',
            ],
            'tanggal_selesai' => [
                'nullable',
                'date',
                'after_or_equal:tanggal_mulai',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data yang dikirim tidak valid.',
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $data = $validator->validated();

        $hasil = DB::transaction(function () use ($data) {

            $kamar = Kamar::where(
                'id_kamar',
                $data['id_kamar']
            )->lockForUpdate()->firstOrFail();

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

            $penghunian = Penghunian::create([
                'id_kamar' => $data['id_kamar'],
                'id_penghuni' => $data['id_penghuni'],
                'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
                'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
                'status' => 'aktif',
                'penyewa_utama' => !$adaPenghuniAktif,
                'status_pembayaran' => 'belum_lunas',
            ]);

            $kamar->update([
                'status' => 'terisi',
            ]);

            return [
                'error' => false,
                'data' => $penghunian->load([
                    'kamar',
                    'penghuni',
                    'pembayaran',
                ]),
            ];
        });

        if ($hasil['error']) {
            return response()->json([
                'message' => $hasil['message'],
                'error' => [
                    'code' => 'TENANT_ALREADY_REGISTERED',
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Penghunian berhasil ditambahkan.',
            'data' => $hasil['data'],
        ], 201);
    }

    /**
     * Menampilkan detail penghunian.
     */
    public function show($id)
    {
        $penghunian = Penghunian::with([
            'kamar',
            'penghuni',
            'pembayaran',
        ])->find($id);

        if (!$penghunian) {
            return response()->json([
                'message' => 'Data penghunian tidak ditemukan.',
                'error' => [
                    'code' => 'OCCUPANCY_NOT_FOUND',
                ],
            ], 404);
        }

        return response()->json([
            'message' => 'Data penghunian berhasil diambil.',
            'data' => $penghunian,
        ]);
    }

    /**
     * Memperbarui tanggal penghunian.
     */
    public function update(Request $request, $id)
    {
        $penghunian = Penghunian::find($id);

        if (!$penghunian) {
            return response()->json([
                'message' => 'Data penghunian tidak ditemukan.',
                'error' => [
                    'code' => 'OCCUPANCY_NOT_FOUND',
                ],
            ], 404);
        }

        $validated = $request->validate([
            'tanggal_mulai' => [
                'sometimes',
                'nullable',
                'date',
            ],
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
        ]);
    }

    /**
     * Mengubah penyewa utama pada satu kamar.
     */
    public function setPenyewaUtama(Request $request, $id)
    {
        $validated = $request->validate([
            'id_kamar' => [
                'required',
                'integer',
                'exists:kamar,id_kamar',
            ],
        ]);

        $hasil = DB::transaction(function () use ($id, $validated) {

            Kamar::where(
                'id_kamar',
                $validated['id_kamar']
            )->lockForUpdate()->firstOrFail();

            $target = Penghunian::where(
                'id_penghunian',
                $id
            )
                ->where('id_kamar', $validated['id_kamar'])
                ->where('status', 'aktif')
                ->first();

            if (!$target) {
                return null;
            }

            Penghunian::where(
                'id_kamar',
                $validated['id_kamar']
            )
                ->where('status', 'aktif')
                ->update([
                    'penyewa_utama' => false,
                ]);

            $target->update([
                'penyewa_utama' => true,
            ]);

            return $target->fresh()->load([
                'kamar',
                'penghuni',
            ]);
        });

        if (!$hasil) {
            return response()->json([
                'message' => 'Penghunian aktif untuk kamar tersebut tidak ditemukan.',
                'error' => [
                    'code' => 'OCCUPANCY_NOT_FOUND',
                ],
            ], 404);
        }

        return response()->json([
            'message' => 'Penyewa utama berhasil diubah.',
            'data' => $hasil,
        ]);
    }

    /**
     * Endpoint lama pencatatan DP.
     *
     * Pencatatan diteruskan ke PembayaranController agar
     * tidak ada dua mekanisme pencatatan DP yang berbeda.
     */
    public function catatDp(Request $request, $id)
    {
        $penghunian = Penghunian::find($id);

        if (!$penghunian || $penghunian->status !== 'aktif') {
            return response()->json([
                'message' => 'Data penghunian aktif tidak ditemukan.',
                'error' => [
                    'code' => 'OCCUPANCY_NOT_FOUND',
                ],
            ], 404);
        }

        $request->merge([
            'id_penghunian' => $id,
            'jenis_pembayaran' => 'dp',
        ]);

        $controller = app(PembayaranController::class);

        return $controller->store($request);
    }

    /**
     * Menghitung total sewa, DP minimum, dan tenggat.
     */
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

        $penghunian = Penghunian::with('kamar')->find($id);

        if (!$penghunian) {
            return response()->json([
                'message' => 'Data penghunian tidak ditemukan.',
                'error' => [
                    'code' => 'OCCUPANCY_NOT_FOUND',
                ],
            ], 404);
        }

        if ($penghunian->status !== 'aktif') {
            return response()->json([
                'message' => 'Skema sewa hanya dapat diatur untuk penghunian aktif.',
                'error' => [
                    'code' => 'OCCUPANCY_NOT_ACTIVE',
                ],
            ], 422);
        }

        if (!$penghunian->penyewa_utama) {
            return response()->json([
                'message' => 'Skema sewa harus diatur melalui penghunian penyewa utama.',
                'error' => [
                    'code' => 'NOT_PRIMARY_TENANT',
                ],
            ], 422);
        }

        $kamar = $penghunian->kamar;

        if (!$kamar || !is_numeric($kamar->harga) || (float) $kamar->harga <= 0) {
            return response()->json([
                'message' => 'Harga sewa bulanan kamar belum diisi atau tidak valid.',
                'error' => [
                    'code' => 'INVALID_MONTHLY_RENT',
                ],
            ], 422);
        }

        /*
         * Hitung harga berdasarkan skema.
         */
        if ($data['skema_sewa'] === 'bulanan') {

            $totalSewa = (float) $kamar->harga;
            $dpMinimum = $totalSewa * 0.5;

        } else {

            $hargaTahunan = $kamar->harga_tahunan;

            if (
                $hargaTahunan === null ||
                !is_numeric($hargaTahunan) ||
                (float) $hargaTahunan <= 0
            ) {
                return response()->json([
                    'message' => 'Harga sewa tahunan kamar belum diisi.',
                    'error' => [
                        'code' => 'INVALID_ANNUAL_RENT',
                    ],
                ], 422);
            }

            $pengaturan = PengaturanSewa::first();

            if (
                !$pengaturan ||
                $pengaturan->dp_minimum_tahunan === null ||
                !is_numeric($pengaturan->dp_minimum_tahunan) ||
                (float) $pengaturan->dp_minimum_tahunan <= 0
            ) {
                return response()->json([
                    'message' => 'Pengaturan DP minimum tahunan belum tersedia atau tidak valid.',
                    'error' => [
                        'code' => 'INVALID_ANNUAL_DP_SETTING',
                    ],
                ], 422);
            }

            $totalSewa = (float) $hargaTahunan;
            $dpMinimum = (float) $pengaturan->dp_minimum_tahunan;
        }

        /*
         * Perhitungan tenggat.
         *
         * Bulanan: mengikuti tanggal masuk.
         * Tahunan: dua bulan setelah tanggal masuk.
         */
        $tenggat = null;

        if ($penghunian->tanggal_mulai) {

            $tanggalMulai = Carbon::parse(
                $penghunian->tanggal_mulai
            );

            if ($data['skema_sewa'] === 'bulanan') {

                $tenggat = $tanggalMulai->toDateString();

            } else {

                $tenggat = $tanggalMulai
                    ->copy()
                    ->addMonthsNoOverflow(2)
                    ->toDateString();
            }
        }

        /*
         * Simpan hasil perhitungan.
         */
        $penghunian->update([
            'skema_sewa' => $data['skema_sewa'],
            'total_sewa' => $totalSewa,
            'dp_minimum' => $dpMinimum,
            'tenggat_pelunasan' => $tenggat,
            'status_pembayaran' => 'belum_lunas',
        ]);

        $penghunian->refresh();

        return response()->json([
            'message' => $tenggat
                ? 'Perhitungan sewa dan tenggat berhasil disimpan.'
                : 'Perhitungan sewa berhasil disimpan. Isi tanggal masuk untuk menentukan tenggat.',
            'data' => $penghunian->load([
                'kamar',
                'penghuni',
                'pembayaran',
            ]),
        ]);
    }

    /**
     * Mengubah tenggat pembayaran tahunan.
     */
    public function ubahTenggat(Request $request, $id)
    {
        $validated = $request->validate([
            'tenggat_pelunasan' => [
                'required',
                'date',
            ],
        ]);

        $penghunian = Penghunian::find($id);

        if (!$penghunian) {
            return response()->json([
                'message' => 'Data penghunian tidak ditemukan.',
                'error' => [
                    'code' => 'OCCUPANCY_NOT_FOUND',
                ],
            ], 404);
        }

        if ($penghunian->skema_sewa !== 'tahunan') {
            return response()->json([
                'message' => 'Pengubahan tenggat hanya berlaku untuk sewa tahunan.',
                'error' => [
                    'code' => 'INVALID_RENT_SCHEME',
                ],
            ], 422);
        }

        if (!$penghunian->tanggal_mulai) {
            return response()->json([
                'message' => 'Isi tanggal masuk terlebih dahulu sebelum mengubah tenggat.',
                'error' => [
                    'code' => 'START_DATE_NOT_SET',
                ],
            ], 422);
        }

        $tanggalMulai = Carbon::parse(
            $penghunian->tanggal_mulai
        )->startOfDay();

        $tanggalTenggat = Carbon::parse(
            $validated['tenggat_pelunasan']
        )->startOfDay();

        if ($tanggalTenggat->lt($tanggalMulai)) {
            return response()->json([
                'message' => 'Tenggat tidak boleh sebelum tanggal mulai sewa.',
                'error' => [
                    'code' => 'INVALID_DUE_DATE',
                ],
            ], 422);
        }

        $penghunian->update([
            'tenggat_pelunasan' => $tanggalTenggat->toDateString(),
        ]);

        return response()->json([
            'message' => 'Tenggat pelunasan berhasil diubah.',
            'data' => $penghunian->fresh()->load([
                'kamar',
                'penghuni',
                'pembayaran',
            ]),
        ]);
    }

    /**
     * Menghitung tenggat bulanan berikutnya.
     */
    public function tenggatBerikutnya($id)
    {
        $penghunian = Penghunian::find($id);

        if (!$penghunian) {
            return response()->json([
                'message' => 'Data penghunian tidak ditemukan.',
                'error' => [
                    'code' => 'OCCUPANCY_NOT_FOUND',
                ],
            ], 404);
        }

        if ($penghunian->skema_sewa !== 'bulanan') {
            return response()->json([
                'message' => 'Fitur ini hanya berlaku untuk sewa bulanan.',
                'error' => [
                    'code' => 'INVALID_RENT_SCHEME',
                ],
            ], 422);
        }

        if (!$penghunian->tenggat_pelunasan) {
            return response()->json([
                'message' => 'Tenggat pembayaran saat ini belum tersedia.',
                'error' => [
                    'code' => 'DUE_DATE_NOT_SET',
                ],
            ], 422);
        }

        $tenggatSekarang = Carbon::parse(
            $penghunian->tenggat_pelunasan
        );

        $tanggalBerikutnya = $tenggatSekarang
            ->copy()
            ->addMonthNoOverflow();

        if ($tenggatSekarang->isLastOfMonth()) {
            $tanggalBerikutnya->endOfMonth();
        }

        $penghunian->update([
            'tenggat_pelunasan' => $tanggalBerikutnya->toDateString(),
        ]);

        return response()->json([
            'message' => 'Tenggat berikutnya berhasil dihitung.',
            'data' => [
                'tenggat_sekarang' => $tenggatSekarang->toDateString(),
                'tenggat_berikutnya' => $tanggalBerikutnya->toDateString(),
            ],
        ]);
    }

    /**
     * Menyelesaikan penghunian.
     */
    public function selesai($id)
    {
        $hasil = DB::transaction(function () use ($id) {

            $penghunian = Penghunian::find($id);

            if (!$penghunian) {
                return null;
            }

            Kamar::where(
                'id_kamar',
                $penghunian->id_kamar
            )->lockForUpdate()->first();

            $penghunian->update([
                'status' => 'selesai',
                'penyewa_utama' => false,
            ]);

            $penghuniAktif = Penghunian::where(
                'id_kamar',
                $penghunian->id_kamar
            )
                ->where('status', 'aktif')
                ->orderBy('id_penghunian')
                ->get();

            if ($penghuniAktif->isEmpty()) {

                Kamar::where(
                    'id_kamar',
                    $penghunian->id_kamar
                )->update([
                    'status' => 'kosong',
                ]);

            } else {

                $adaPenyewaUtama = $penghuniAktif
                    ->contains('penyewa_utama', true);

                if (!$adaPenyewaUtama) {

                    $penghuniAktif->first()->update([
                        'penyewa_utama' => true,
                    ]);
                }
            }

            return $penghunian->fresh()->load([
                'kamar',
                'penghuni',
                'pembayaran',
            ]);
        });

        if (!$hasil) {
            return response()->json([
                'message' => 'Data penghunian tidak ditemukan.',
                'error' => [
                    'code' => 'OCCUPANCY_NOT_FOUND',
                ],
            ], 404);
        }

        return response()->json([
            'message' => 'Penghunian berhasil diselesaikan.',
            'data' => $hasil,
        ]);
    }
}