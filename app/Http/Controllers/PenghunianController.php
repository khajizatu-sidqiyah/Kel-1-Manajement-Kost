<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\Penghuni;
use App\Models\Penghunian;
use App\Models\Pembayaran;
use App\Models\PengaturanSewa;
use App\Services\TenggatSewaService;
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
        $validator = Validator::make($request->all(), [
    'skema_sewa' => [
        'required',
        'string',
        Rule::in(['bulanan', 'tahunan']),
    ],
], [
    'skema_sewa.required' => 'Pilih skema sewa terlebih dahulu.',
    'skema_sewa.in' => 'Skema sewa harus bulanan atau tahunan.',
]);

if ($validator->fails()) {
    return $this->apiErrorResponse(
        422,
        'Validasi request gagal.',
        'VALIDATION_ERROR',
        $validator->errors()->toArray()
    );
}

$validated = $validator->validated();

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
            return $this->apiErrorResponse(
                422,
                'Skema sewa harus diatur melalui penghunian penyewa utama.',
                'VALIDATION_ERROR',
            [
                'penyewa_utama' => [
                    'Pengaturan skema sewa hanya dapat dilakukan melalui penyewa utama.',
                ],
            ]
        );
    }

            // Simpan harga saat skema masa sewa pertama kali dihitung.
            // Perubahan harga kamar berikutnya tidak boleh mengubah kontrak aktif.
            if (
                $penghunian->skema_sewa === $validated['skema_sewa'] &&
                (float) ($penghunian->total_sewa ?? 0) > 0 &&
                (float) ($penghunian->dp_minimum ?? 0) > 0
            ) {
                // Jika tanggal masuk baru dilengkapi setelah DP dihitung,
                // isi tenggat dari tanggal tersebut tanpa membaca ulang harga kamar.
                if (!$penghunian->tenggat_pelunasan && $penghunian->tanggal_mulai) {
                    $tanggalMasuk = Carbon::parse($penghunian->tanggal_mulai);
                    $tenggatTersimpan = $validated['skema_sewa'] === 'tahunan'
                        ? $tanggalMasuk->copy()->addMonthsNoOverflow(3)->toDateString()
                        : $tanggalMasuk->toDateString();

                    $penghunian->update([
                        'tenggat_pelunasan' => $tenggatTersimpan,
                    ]);
                }

                $penghunian->refresh();

                return response()->json([
                    'message' => $penghunian->tenggat_pelunasan
                        ? 'Perhitungan sewa menggunakan harga yang telah disimpan.'
                        : 'DP berhasil dihitung. Isi tanggal masuk untuk menghitung tenggat.',
                    'data' => $penghunian->load([
                        'kamar',
                        'penghuni',
                        'pembayaran',
                    ]),
                ]);
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
                // Harga bulanan dikunci pada penghunian saat skema dimulai.
                if (!is_numeric($kamar->harga) || (float) $kamar->harga <= 0) {
                    return response()->json([
                        'message' => 'Harga sewa kamar belum diisi',
                        'error' => ['code' => 'INVALID_RENT'],
                    ], 422);
                }

                $totalSewa = round((float) $kamar->harga, 2);
                $dpMinimum = round($totalSewa * 0.5, 2);
                // Bulanan: sisa bulan pertama jatuh tempo pada tanggal masuk.
                $tenggat = $penghunian->tanggal_mulai
                    ? Carbon::parse($penghunian->tanggal_mulai)->toDateString()
                    : null;
            } else {
                $jumlahPenghuni = $penghuniAktif->count();

                if ($jumlahPenghuni < 1 || $jumlahPenghuni > 2) {
                    return response()->json([
                        'message' => 'Jumlah penghuni kamar tidak valid.',
                        'error' => ['code' => 'INVALID_TENANT_COUNT'],
                    ], 422);
                }

                    /// Tarif tahunan dibaca dari data kamar, bukan ditulis di controller.
                $totalSewa = $jumlahPenghuni === 1
                    ? $kamar->harga_tahunan_1_orang
                    : $kamar->harga_tahunan_2_orang;

                if (!is_numeric($totalSewa) || (float) $totalSewa <= 0) {
                    return response()->json([
                        'message' => 'Harga sewa kamar belum diisi',
                        'error' => ['code' => 'INVALID_RENT'],
                    ], 422);
                }

                $pengaturan = PengaturanSewa::query()->first();
                if (
                    !$pengaturan ||
                    !is_numeric($pengaturan->dp_minimum_tahunan) ||
                    (float) $pengaturan->dp_minimum_tahunan <= 0
                ) {
                    return response()->json([
                        'message' => 'Pengaturan DP minimum tahunan belum tersedia atau tidak valid.',
                        'error' => ['code' => 'INVALID_ANNUAL_DP_SETTING'],
                    ], 422);
                }

                $totalSewa = round((float) $totalSewa, 2);
                $dpMinimum = round((float) $pengaturan->dp_minimum_tahunan, 2);

                // Aturan R-03: pelunasan tahunan tiga bulan setelah tanggal masuk.
                $tenggat = $penghunian->tanggal_mulai
                    ? Carbon::parse($penghunian->tanggal_mulai)
                        ->addMonthsNoOverflow(3)
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
                'message' => $tenggat === null
                    ? 'DP berhasil dihitung. Isi tanggal masuk untuk menghitung tenggat.'
                    : 'Perhitungan sewa, DP minimum, dan tenggat berhasil disimpan.',
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
    $validator = Validator::make($request->all(), [
        'tenggat_pelunasan' => [
            'required',
            'date',
        ],
    ], [
        'tenggat_pelunasan.required' => 'Tanggal tenggat wajib diisi.',
        'tenggat_pelunasan.date' => 'Format tanggal tenggat tidak valid.',
    ]);

    if ($validator->fails()) {
        return $this->apiErrorResponse(
            422,
            'Validasi request gagal.',
            'VALIDATION_ERROR',
            $validator->errors()->toArray()
        );
    }

    $validated = $validator->validated();

    $penghunian = Penghunian::find($id);

    if (!$penghunian) {
        return $this->apiErrorResponse(
            404,
            'Data penghunian tidak ditemukan.',
            'NOT_FOUND',
            []
        );
    }

    if ($penghunian->skema_sewa !== 'tahunan') {
        return $this->apiErrorResponse(
            422,
            'Pengubahan tenggat hanya berlaku untuk sewa tahunan.',
            'VALIDATION_ERROR',
            [
                'skema_sewa' => [
                    'Skema sewa harus tahunan.',
                ],
            ]
        );
    }

    if (!$penghunian->tanggal_mulai) {
        return $this->apiErrorResponse(
            422,
            'Isi tanggal masuk terlebih dahulu sebelum mengubah tenggat.',
            'VALIDATION_ERROR',
            [
                'tanggal_mulai' => [
                    'Tanggal mulai sewa wajib tersedia.',
                ],
            ]
        );
    }

    $tanggalMulai = Carbon::parse(
        $penghunian->tanggal_mulai
    )->startOfDay();

    $tanggalTenggat = Carbon::parse(
        $validated['tenggat_pelunasan']
    )->startOfDay();

    if ($tanggalTenggat->lt($tanggalMulai)) {
        return $this->apiErrorResponse(
            422,
            'Tenggat tidak boleh sebelum tanggal mulai sewa.',
            'VALIDATION_ERROR',
            [
                'tenggat_pelunasan' => [
                    'Tenggat tidak boleh sebelum tanggal mulai sewa.',
                ],
            ]
        );
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
    ], 200);
}


    /**
     * Menghitung tenggat bulanan berikutnya.
     */
    public function tenggatBerikutnya(int $id)
    {
        return DB::transaction(function () use ($id) {
        $penghunian = Penghunian::whereKey($id)
            ->lockForUpdate()
            ->first();

        if (!$penghunian) {
            return $this->apiErrorResponse(
                404,
                'Data penghunian tidak ditemukan.',
                'NOT_FOUND',
                []
            );
        }

        if ($penghunian->status !== 'aktif') {
            return $this->apiErrorResponse(
                422,
                'Skema sewa hanya dapat diatur untuk penghunian aktif.',
                'VALIDATION_ERROR',
                [
                    'status' => [
                'Penghunian harus berstatus aktif.',
            ],
        ]
    );
}

        // Hanya berlaku untuk skema bulanan.
        if ($penghunian->skema_sewa !== 'bulanan') {
            return response()->json([
                'message' => 'Fitur ini hanya berlaku untuk sewa bulanan.',
                'error' => [
                    'code' => 422,
                    'message' => 'Fitur ini hanya berlaku untuk sewa bulanan.',
                    'status' => 'VALIDATION_ERROR',
                    'details' => [
                        'skema_sewa' => ['Skema sewa harus bulanan.'],
                    ],
                ],
            ], 422);
        }

        if (!$penghunian->tenggat_pelunasan) {
            return response()->json([
                'message' => 'Tenggat pembayaran belum tersedia.',
                'error' => [
                    'code' => 422,
                    'message' => 'Tenggat pembayaran belum tersedia.',
                    'status' => 'VALIDATION_ERROR',
                    'details' => [
                        'tenggat_pelunasan' => [
                            'Tenggat pembayaran wajib tersedia.',
                        ],
                    ],
                ],
            ], 422);
        }

        $totalTagihan = round(
            (float) ($penghunian->total_sewa ?? 0),
            2
        );

        if ($totalTagihan <= 0) {
            return response()->json([
                'message' => 'Total tagihan belum tersedia.',
                'error' => [
                    'code' => 422,
                    'message' => 'Total tagihan belum tersedia.',
                    'status' => 'VALIDATION_ERROR',
                    'details' => [
                        'total_sewa' => ['Total sewa harus lebih besar dari nol.'],
                    ],
                ],
            ], 422);
        }

        $tenggatSekarang = Carbon::parse(
            $penghunian->tenggat_pelunasan
        );

        // Tagihan bulanan mengikuti bulan pada tenggat saat ini.
        $periodeTagihan = $tenggatSekarang
            ->copy()
            ->startOfMonth()
            ->toDateString();

        // Hanya pembayaran terverifikasi yang dihitung.
        $totalTerverifikasi = (float) Pembayaran::query()
            ->where('id_penghunian', $penghunian->id_penghunian)
            ->where('status', 'terverifikasi')
            ->whereDate('periode_tagihan', $periodeTagihan)
            ->sum('jumlah');

        $totalTerverifikasi = round($totalTerverifikasi, 2);

        // Belum lunas: kembalikan 422 dan jangan mengubah tenggat.
        if ($totalTerverifikasi < $totalTagihan) {
            return response()->json([
                'message' => 'Pembayaran bulan berjalan belum lunas. Tenggat tidak berubah.',
                'error' => [
                    'code' => 422,
                    'message' => 'Pembayaran bulan berjalan belum lunas.',
                    'status' => 'PAYMENT_NOT_SETTLED',
                    'details' => [
                        'total_tagihan' => $totalTagihan,
                        'total_terverifikasi' => $totalTerverifikasi,
                        'sisa_tagihan' => round(
                            $totalTagihan - $totalTerverifikasi,
                            2
                        ),
                    ],
                ],
            ], 422);
        }

        // Gunakan service 5.1 untuk aturan akhir bulan.
        $tenggatBaru = app(TenggatSewaService::class)
            ->majukanJikaLunas(
                $penghunian,
                $periodeTagihan,
                $totalTerverifikasi
            );

        if (!$tenggatBaru) {
            return response()->json([
                'message' => 'Tenggat tidak dapat dimajukan untuk periode ini.',
                'error' => [
                    'code' => 422,
                    'message' => 'Tenggat tidak dapat dimajukan untuk periode ini.',
                    'status' => 'DUE_DATE_NOT_ADVANCED',
                    'details' => (object) [],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Tenggat berikutnya berhasil dihitung.',
            'data' => [
                'tenggat_sekarang' => $tenggatSekarang->toDateString(),
                'tenggat_berikutnya' => $tenggatBaru,
            ],
        ], 200);
    });
}

    private function apiErrorResponse(
    int $httpStatus,
    string $message,
    string $status,
    array $details = []
) {
    return response()->json([
        'message' => $message,
        'error' => [
            'code' => $httpStatus,
            'message' => $message,
            'status' => $status,
            'details' => $details,
        ],
    ], $httpStatus);
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