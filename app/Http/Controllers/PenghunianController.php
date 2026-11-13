<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
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
     * Menampilkan seluruh data penghunian dengan dukungan
     * Multiple Filter, Search, dan Pagination di Backend.
     */
        public function index(Request $request)
    {
        $query = Penghunian::with([
            'kamar',
            'penghuni',
            'pembayaran'
        ]);

        // FILTER SKEMA SEWA (MULTIPLE SELECT)
        if ($request->filled('skema')) {
            $skema = (array) $request->skema;

            $query->whereIn('skema_sewa', $skema);
        }

        // FILTER STATUS PEMBAYARAN (MULTIPLE SELECT)
        if ($request->filled('status_data')) {
            $statusData = (array) $request->status_data;

            $query->whereIn('status_pembayaran', $statusData);
        }

        // FILTER STATUS PENGHUNIAN (MULTIPLE SELECT)
        if ($request->filled('status')) {
            $status = (array) $request->status;

            $query->whereIn('status', $status);
        }

        // FILTER PERAN PENYEWA (MULTIPLE SELECT)
        if ($request->filled('peran')) {
            $peran = collect((array) $request->peran)
                ->map(function ($value) {
                    if (in_array($value, ['utama', '1', 1], true)) {
                        return 1;
                    }

                    if (in_array($value, ['tambahan', '0', 0], true)) {
                        return 0;
                    }

                    return null;
                })
                ->filter(fn ($value) => $value !== null)
                ->unique()
                ->values()
                ->all();

            if (!empty($peran)) {
                $query->whereIn('penyewa_utama', $peran);
            }
        }

        // FILTER LANTAI (MULTIPLE SELECT)
        if ($request->filled('lantai')) {
            $lantai = (array) $request->lantai;

            $query->whereHas('kamar', function ($q) use ($lantai) {
                $q->whereIn('lantai', $lantai);
            });
        }

        // SEARCH NAMA PENGHUNI
        $search = $request->input('q', $request->input('search'));

        if ($search) {
            $query->whereHas('penghuni', function ($q) use ($search) {
                $q->where('nama_penghuni', 'like', "%{$search}%");
            });
        }

        // URUTKAN DATA
        $query->orderByDesc('created_at');

        // TAMPILKAN SEMUA DATA
        $tampilkanSemua = $request->input('tampilkan_semua') == 1
            || $request->input('per_page') === 'all';

        if ($tampilkanSemua) {
            $data = $query->get();

            return response()->json([
                'message' => 'Data penghunian berhasil ditampilkan',
                'data' => $data,
                'pagination' => null
            ]);
        }

        // PAGINATION
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $data = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'message' => 'Data penghunian berhasil ditampilkan',
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage()
            ]
        ]);
    }

    /**
     * Menambahkan penghuni baru sekaligus membuat penghunian.
     * Penghuni pertama pada kamar otomatis menjadi penyewa utama.
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
                'nullable',
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
            )
                ->lockForUpdate()
                ->firstOrFail();

            $jumlahAktif = Penghunian::where(
                'id_kamar',
                $kamar->id_kamar
            )
                ->where('status', 'aktif')
                ->count();

            if ($jumlahAktif >= 2) {
                return [
                    'error' => true,
                    'status' => 422,
                    'message' => 'Kamar sudah penuh (kapasitas maksimal 2 penghuni).',
                    'code' => 'ROOM_FULL',
                ];
            }

            $adaPenghuniAktif = $jumlahAktif > 0;

            $penghuni = Penghuni::create([
                'nama_penghuni' => $data['nama_penghuni'],
                'no_telepon' => $data['no_telepon'] ?? null,
                'alamat' => $data['alamat'] ?? null,
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

        if (isset($hasil['error']) && $hasil['error']) {
            return response()->json([
                'message' => $hasil['message'],
                'error' => [
                    'code' => $hasil['code'] ?? 'INVALID_OPERATION',
                ],
            ], $hasil['status'] ?? 422);
        }

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
            )
                ->lockForUpdate()
                ->firstOrFail();

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
                    'status' => 409,
                    'code' => 'TENANT_ALREADY_REGISTERED',
                    'message' => 'Penghuni sudah terdaftar pada kamar ini.',
                ];
            }

            $jumlahAktif = Penghunian::where(
                'id_kamar',
                $data['id_kamar']
            )
                ->where('status', 'aktif')
                ->count();

            if ($jumlahAktif >= 2) {
                return [
                    'error' => true,
                    'status' => 422,
                    'code' => 'ROOM_FULL',
                    'message' => 'Kamar sudah penuh (kapasitas maksimal 2 penghuni).',
                ];
            }

            $adaPenghuniAktif = $jumlahAktif > 0;

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
                    'code' => $hasil['code'] ?? 'TENANT_ALREADY_REGISTERED',
                ],
            ], $hasil['status'] ?? 409);
        }

        return response()->json([
            'message' => 'Penghunian berhasil ditambahkan.',
            'data' => $hasil['data'],
        ], 201);
    }

    /**
     * Menampilkan detail penghunian.
     */
    public function show(int $id)
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
    public function update(Request $request, int $id)
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
    public function setPenyewaUtama(Request $request, int $id)
    {
        $validated = $request->validate([
            'id_kamar' => [
                'required',
                'integer',
            ],
        ]);

        $hasil = DB::transaction(function () use ($id, $validated) {
            Kamar::where(
                'id_kamar',
                $validated['id_kamar']
            )
                ->lockForUpdate()
                ->firstOrFail();

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
     * Endpoint pencatatan DP dialihkan ke PembayaranController.
     */
    public function catatDp(Request $request, int $id)
    {
        $request->merge([
            'id_penghunian' => $id,
            'jenis_pembayaran' => 'dp',
        ]);

        return app(PembayaranController::class)->store($request);
    }

    /**
     * Menghitung total sewa, DP minimum, dan tenggat berdasarkan skema.
     */
    public function hitung(Request $request, int $id)
    {
        $validated = $request->validate([
            'skema_sewa' => [
                'required',
                Rule::in([
                    'bulanan',
                    'tahunan',
                ]),
            ],
        ], [
            'skema_sewa.required' => 'Pilih skema sewa terlebih dahulu.',
            'skema_sewa.in' => 'Skema sewa harus bulanan atau tahunan.',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $penghunian = Penghunian::with('kamar')
                ->lockForUpdate()
                ->find($id);

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

            $penghuniAktif = Penghunian::where(
                'id_kamar',
                $penghunian->id_kamar
            )
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->get();

            $utama = $penghuniAktif->firstWhere(
                'penyewa_utama',
                true
            );

            if (
                !$utama &&
                $penghuniAktif->count() === 1
            ) {
                $utama = $penghuniAktif->first();

                $utama->update([
                    'penyewa_utama' => true,
                ]);
            }

            if (
                !$utama ||
                (int) $utama->id_penghunian !==
                (int) $penghunian->id_penghunian
            ) {
                return response()->json([
                    'message' => 'Skema sewa harus diatur melalui penghunian penyewa utama.',
                    'error' => [
                        'code' => 'NOT_PRIMARY_TENANT',
                    ],
                ], 422);
            }

            $idPenghunianAktif = Penghunian::where(
                'id_kamar',
                $penghunian->id_kamar
            )
                ->where('status', 'aktif')
                ->pluck('id_penghunian');

            $sudahAdaPembayaran = Pembayaran::whereIn(
                'id_penghunian',
                $idPenghunianAktif
            )->exists();

            if ($sudahAdaPembayaran) {
                return response()->json([
                    'message' => 'Skema sewa tidak dapat diubah setelah pembayaran dicatat.',
                    'error' => [
                        'code' => 'PAYMENT_ALREADY_EXISTS',
                    ],
                ], 422);
            }

            $kamar = $penghunian->kamar;

            if (!$kamar) {
                return response()->json([
                    'message' => 'Data kamar tidak ditemukan.',
                    'error' => [
                        'code' => 'ROOM_NOT_FOUND',
                    ],
                ], 404);
            }

            if ($validated['skema_sewa'] === 'bulanan') {
                if (
                    !is_numeric($kamar->harga) ||
                    (float) $kamar->harga <= 0
                ) {
                    return response()->json([
                        'message' => 'Harga sewa bulanan kamar belum diisi atau tidak valid.',
                        'error' => [
                            'code' => 'INVALID_MONTHLY_RENT',
                        ],
                    ], 422);
                }

                $totalSewa = round(
                    (float) $kamar->harga,
                    2
                );

                $dpMinimum = round(
                    $totalSewa * 0.5,
                    2
                );

                $tenggat = $penghunian->tanggal_mulai
                    ? Carbon::parse(
                        $penghunian->tanggal_mulai
                    )->toDateString()
                    : null;

                } else {
                    // Harga tahunan berdasarkan jumlah penghuni aktif
                    $jumlahPenghuni = $penghuniAktif->count();

                    if ($jumlahPenghuni < 1 || $jumlahPenghuni > 2) {
                        return response()->json([
                            'message' => 'Jumlah penghuni kamar tidak valid.',
                            'error' => [
                                'code' => 'INVALID_TENANT_COUNT',
                            ],
                        ], 422);
                    }

                    // Harga tahunan:
                    // 1 orang = Rp5.000.000
                    // 2 orang = Rp6.900.000
                    $totalSewa = $jumlahPenghuni === 1
                        ? 5000000
                        : 6900000;

                    // DP minimum tahunan tetap Rp300.000
                    $dpMinimum = 300000;

                    // Tenggat pelunasan 2 bulan setelah tanggal masuk
                    $tenggat = $penghunian->tanggal_mulai
                        ? Carbon::parse(
                            $penghunian->tanggal_mulai
                        )
                            ->addMonthsNoOverflow(2)
                            ->toDateString()
                        : null;
                }


            $penghunian->update([
                'skema_sewa' => $validated['skema_sewa'],
                'total_sewa' => $totalSewa,
                'dp_minimum' => $dpMinimum,
                'tenggat_pelunasan' => $tenggat,
                'status_pembayaran' => 'belum_lunas',
            ]);

            return response()->json([
                'message' => 'Perhitungan sewa, DP minimum, dan tenggat berhasil disimpan.',
                'data' => $penghunian->fresh()->load([
                    'kamar',
                    'penghuni',
                    'pembayaran',
                ]),
            ]);
        });
    }

    /**
     * Mengubah tenggat pembayaran tahunan.
     */
    public function ubahTenggat(Request $request, int $id)
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
    public function tenggatBerikutnya(int $id)
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
    public function selesai(int $id)
    {
        $hasil = DB::transaction(function () use ($id) {
            $penghunian = Penghunian::find($id);

            if (!$penghunian) {
                return null;
            }

            Kamar::where(
                'id_kamar',
                $penghunian->id_kamar
            )
                ->lockForUpdate()
                ->first();

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