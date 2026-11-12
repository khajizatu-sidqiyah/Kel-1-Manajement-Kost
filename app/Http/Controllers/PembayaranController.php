<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\Pembayaran;
use App\Models\Penghunian;
use App\Services\TenggatSewaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PembayaranController extends Controller
{
    /**
     * Menampilkan data pembayaran dengan filter, pencarian,
     * pagination, dan opsi tampilkan semua.
     */
    public function index(Request $request)
    {
        $query = Pembayaran::with([
            'penghunian.kamar',
            'penghunian.penghuni',
        ]);

        // Filter dapat menerima satu nilai atau beberapa nilai.
        $jenis = $request->input('jenis_pembayaran', []);
        $jenis = is_array($jenis) ? $jenis : [$jenis];
        $jenis = array_values(array_filter(
            $jenis,
            fn ($value) => $value !== null && $value !== ''
        ));

        if (!empty($jenis)) {
            $query->whereIn('jenis_pembayaran', $jenis);
        }

        $status = $request->input('status', []);
        $status = is_array($status) ? $status : [$status];
        $status = array_values(array_filter(
            $status,
            fn ($value) => $value !== null && $value !== ''
        ));

        if (!empty($status)) {
            $query->whereIn('status', $status);
        }

        $metode = $request->input('metode_pembayaran', []);
        $metode = is_array($metode) ? $metode : [$metode];
        $metode = array_values(array_filter(
            $metode,
            fn ($value) => $value !== null && $value !== ''
        ));

        if (!empty($metode)) {
            $query->whereIn('metode_pembayaran', $metode);
        }

        // Pencarian nama penghuni.
        $keyword = trim((string) $request->input(
            'q',
            $request->input('search', '')
        ));

        if ($keyword !== '') {
            $query->whereHas(
                'penghunian.penghuni',
                function ($q) use ($keyword) {
                    $q->where(
                        'nama_penghuni',
                        'like',
                        '%' . addcslashes($keyword, '%_\\') . '%'
                    );
                }
            );
        }

        // Filter rentang tanggal.
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate(
                'tanggal_bayar',
                '>=',
                $request->input('tanggal_mulai')
            );
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate(
                'tanggal_bayar',
                '<=',
                $request->input('tanggal_selesai')
            );
        }

        $baseQuery = $query
            ->orderByDesc('tanggal_bayar')
            ->orderByDesc('created_at');

        $showAll = $request->boolean('tampilkan_semua')
            || strtolower((string) $request->input('per_page')) === 'all';

        if ($showAll) {
            $rows = $baseQuery->get();
            $total = $rows->count();

            $pagination = [
                'total' => $total,
                'per_page' => 'all',
                'current_page' => 1,
                'last_page' => 1,
                'from' => $total > 0 ? 1 : null,
                'to' => $total > 0 ? $total : null,
                'show_all' => true,
            ];
        } else {
            $perPage = (int) $request->input('per_page', 10);
            $perPage = max(1, min($perPage, 100));
            $page = max(1, (int) $request->input('page', 1));

            $paginator = $baseQuery->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );

            $rows = $paginator->items();

            $pagination = [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'show_all' => false,
            ];
        }

        return response()->json([
            'message' => 'Data pembayaran berhasil diambil.',
            'data' => $rows,
            'pagination' => $pagination,
        ]);
    }

    /**
     * Menampilkan detail pembayaran.
     */
    public function show(int $id)
    {
        $pembayaran = Pembayaran::with([
            'penghunian.kamar',
            'penghunian.penghuni',
        ])->find($id);

        if (!$pembayaran) {
            return response()->json([
                'message' => 'Data pembayaran tidak ditemukan.',
                'error' => [
                    'code' => 'PAYMENT_NOT_FOUND',
                ],
            ], 404);
        }

        return response()->json([
            'message' => 'Detail pembayaran berhasil diambil.',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Mencatat DP, cicilan, atau pelunasan.
     *
     * Pembayaran kamar bersama dicatat melalui penghunian
     * penyewa utama. DP di bawah minimum langsung ditolak.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_penghunian' => [
                'required',
                'integer',
                'exists:penghunian,id_penghunian',
            ],
            'jenis_pembayaran' => [
                'required',
                Rule::in(['dp', 'cicilan', 'pelunasan']),
            ],
            'tanggal_bayar' => [
                'required',
                'date',
            ],
            'jumlah' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'metode_pembayaran' => [
                'required',
                'string',
                'max:30',
            ],
            'bukti_pembayaran' => [
                'nullable',
                'string',
                'max:255',
            ],
            'tenggat_pelunasan' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ], [
            'jenis_pembayaran.in' =>
                'Jenis pembayaran hanya dp, cicilan, atau pelunasan.',
            'jumlah.gt' =>
                'Jumlah pembayaran harus lebih besar dari nol.',
        ]);

        $hasil = DB::transaction(function () use ($validated) {
            $penghunianInput = Penghunian::find(
                $validated['id_penghunian']
            );

            if (!$penghunianInput) {
                return [
                    'status' => 404,
                    'body' => [
                        'message' => 'Data penghunian tidak ditemukan.',
                        'error' => ['code' => 'OCCUPANCY_NOT_FOUND'],
                    ],
                ];
            }

            // Kunci kamar terlebih dahulu untuk mencegah transaksi bersamaan.
            $kamar = Kamar::where(
                'id_kamar',
                $penghunianInput->id_kamar
            )
                ->lockForUpdate()
                ->first();

            if (!$kamar) {
                return [
                    'status' => 404,
                    'body' => [
                        'message' => 'Data kamar tidak ditemukan.',
                        'error' => ['code' => 'ROOM_NOT_FOUND'],
                    ],
                ];
            }

            // Ambil dan kunci seluruh penghunian aktif pada kamar tersebut.
            $penghuniAktif = Penghunian::where(
                'id_kamar',
                $kamar->id_kamar
            )
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->get();

            $penghunianInputAktif = $penghuniAktif->firstWhere(
                'id_penghunian',
                $penghunianInput->id_penghunian
            );

            if (!$penghunianInputAktif) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Pembayaran hanya dapat dicatat untuk penghunian aktif.',
                        'error' => ['code' => 'OCCUPANCY_NOT_ACTIVE'],
                    ],
                ];
            }

            // Pastikan hanya ada satu penyewa utama.
            $daftarUtama = $penghuniAktif->where('penyewa_utama', true);

            if ($daftarUtama->count() > 1) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Terdapat lebih dari satu penyewa utama pada kamar ini.',
                        'error' => ['code' => 'MULTIPLE_PRIMARY_TENANTS'],
                    ],
                ];
            }

            $penghunian = $daftarUtama->first();

            // Jika hanya ada satu penghuni aktif, jadikan penyewa utama otomatis.
            if (!$penghunian && $penghuniAktif->count() === 1) {
                $penghunian = $penghuniAktif->first();
                $penghunian->update(['penyewa_utama' => true]);
            }

            if (!$penghunian) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Penyewa utama belum ditentukan.',
                        'error' => ['code' => 'PRIMARY_TENANT_NOT_FOUND'],
                    ],
                ];
            }

            $penghunian->refresh();

            $jenis = $validated['jenis_pembayaran'];
            $jumlah = round((float) $validated['jumlah'], 2);
            $skema = $penghunian->skema_sewa;
            $totalSewa = round((float) ($penghunian->total_sewa ?? 0), 2);
            $periodeTagihan = null;

            // Setiap pembayaran bulanan masuk ke satu periode berdasarkan tenggat aktif.
            if ($skema === 'bulanan') {
                if (!$penghunian->tenggat_pelunasan) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Tenggat pembayaran belum tersedia. Lengkapi tanggal masuk dan hitung skema sewa terlebih dahulu.',
                            'error' => ['code' => 'DUE_DATE_NOT_SET'],
                        ],
                    ];
                }

                $periodeTagihan = Carbon::parse(
                    $penghunian->tenggat_pelunasan
                )->startOfMonth()->toDateString();
            }

            if (
                !in_array($skema, ['bulanan', 'tahunan'], true) ||
                $totalSewa <= 0
            ) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Pilih dan hitung skema sewa terlebih dahulu sebelum mencatat pembayaran.',
                        'error' => ['code' => 'RENT_SCHEME_NOT_SET'],
                    ],
                ];
            }

            // Cicilan hanya berlaku untuk skema tahunan.
            if ($skema === 'bulanan' && $jenis === 'cicilan') {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Cicilan hanya diperbolehkan untuk skema sewa tahunan.',
                        'error' => ['code' => 'INSTALLMENT_NOT_ALLOWED'],
                    ],
                ];
            }

            $idPenghunianAktif = $penghuniAktif
                ->pluck('id_penghunian')
                ->all();

            $tenggatBaru = null;

            if ($jenis === 'dp') {
                // DP hanya satu kali untuk masa sewa kamar dan DP ditolak boleh diajukan ulang.
                $sudahAdaDp = Pembayaran::whereIn(
                    'id_penghunian',
                    $idPenghunianAktif
                )
                    ->where('jenis_pembayaran', 'dp')
                    ->where('status', '!=', 'ditolak')
                    ->exists();

                if ($sudahAdaDp) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'DP untuk masa sewa ini sudah pernah dicatat dan belum ditolak.',
                            'error' => ['code' => 'DP_ALREADY_EXISTS'],
                        ],
                    ];
                }

                $dpMinimum = round((float) ($penghunian->dp_minimum ?? 0), 2);

                if ($dpMinimum <= 0) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'DP minimum belum tersedia. Hitung skema sewa terlebih dahulu.',
                            'error' => ['code' => 'DP_MINIMUM_NOT_SET'],
                        ],
                    ];
                }

                if ($jumlah > $totalSewa) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Jumlah DP tidak boleh melebihi total sewa.',
                            'error' => [
                                'code' => 'DP_EXCEEDS_RENT',
                                'sisa_tagihan' => $totalSewa,
                            ],
                        ],
                    ];
                }

                // Aturan pilihan A: DP di bawah minimum langsung ditolak.
                if ($jumlah < $dpMinimum) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Pembayaran DP ditolak karena jumlahnya kurang dari DP minimum Rp' .
                                number_format($dpMinimum, 0, ',', '.') . '.',
                            'error' => [
                                'code' => 'DP_BELOW_MINIMUM',
                                'dp_minimum' => $dpMinimum,
                                'jumlah_dp' => $jumlah,
                            ],
                        ],
                    ];
                }

                // Perubahan tenggat dari form DP hanya untuk sewa tahunan.
                if (
                    !empty($validated['tenggat_pelunasan']) &&
                    $skema !== 'tahunan'
                ) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Pengubahan tenggat melalui form DP hanya berlaku untuk sewa tahunan.',
                            'error' => ['code' => 'INVALID_DUE_DATE_SCHEME'],
                        ],
                    ];
                }

                if (!empty($validated['tenggat_pelunasan'])) {
                    if (
                        $penghunian->tanggal_mulai &&
                        Carbon::parse($validated['tenggat_pelunasan'])->lt(
                            Carbon::parse($penghunian->tanggal_mulai)
                        )
                    ) {
                        return [
                            'status' => 422,
                            'body' => [
                                'message' => 'Tenggat pelunasan tidak boleh sebelum tanggal mulai sewa.',
                                'error' => ['code' => 'INVALID_DUE_DATE'],
                            ],
                        ];
                    }

                    $tenggatBaru = $validated['tenggat_pelunasan'];
                }
            }

            // Untuk bulanan, hitung hanya pembayaran pada periode aktif.
            $queryTerbayarSebelum = Pembayaran::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )->where('status', 'terverifikasi');

            if ($skema === 'bulanan') {
                $queryTerbayarSebelum->whereDate(
                    'periode_tagihan',
                    $periodeTagihan
                );
            }

            $totalTerbayarSebelum = round(
                (float) $queryTerbayarSebelum->sum('jumlah'),
                2
            );

            $sisaSebelum = max(
                0,
                round($totalSewa - $totalTerbayarSebelum, 2)
            );

            if ($sisaSebelum <= 0) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Tagihan sudah lunas. Tidak dapat menambahkan pembayaran lagi.',
                        'error' => ['code' => 'BILL_ALREADY_PAID'],
                    ],
                ];
            }

            if ($jumlah > $sisaSebelum) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Jumlah pembayaran melebihi sisa tagihan.',
                        'error' => [
                            'code' => 'PAYMENT_EXCEEDS_REMAINING',
                            'sisa_tagihan' => $sisaSebelum,
                        ],
                    ],
                ];
            }

            if ($jenis === 'pelunasan' && $jumlah != $sisaSebelum) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Jumlah pelunasan harus sama dengan sisa tagihan.',
                        'error' => [
                            'code' => 'INVALID_SETTLEMENT_AMOUNT',
                            'sisa_tagihan' => $sisaSebelum,
                        ],
                    ],
                ];
            }

            // Cicilan tidak boleh menghabiskan seluruh sisa tagihan.
            if ($jenis === 'cicilan' && $jumlah >= $sisaSebelum) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Jika membayar seluruh sisa tagihan, pilih jenis pembayaran pelunasan.',
                        'error' => [
                            'code' => 'USE_SETTLEMENT_FOR_FULL_PAYMENT',
                            'sisa_tagihan' => $sisaSebelum,
                        ],
                    ],
                ];
            }

            // Simpan perubahan tenggat setelah seluruh validasi lolos.
            if ($tenggatBaru !== null) {
                $penghunian->update([
                    'tenggat_pelunasan' => $tenggatBaru,
                ]);
            }

            // Pembayaran yang dicatat pemilik langsung terverifikasi.
            $pembayaran = Pembayaran::create([
                'id_penghunian' => $penghunian->id_penghunian,
                'jenis_pembayaran' => $jenis,
                'tanggal_bayar' => $validated['tanggal_bayar'],
                'periode_tagihan' => $periodeTagihan,
                'jumlah' => $jumlah,
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'bukti_pembayaran' => $validated['bukti_pembayaran'] ?? null,
                'status' => 'terverifikasi',
            ]);

            $queryTotalTerbayar = Pembayaran::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )->where('status', 'terverifikasi');

            if ($skema === 'bulanan') {
                $queryTotalTerbayar->whereDate(
                    'periode_tagihan',
                    $periodeTagihan
                );
            }

            $totalTerbayar = round(
                (float) $queryTotalTerbayar->sum('jumlah'),
                2
            );

            $sisaTagihan = max(0, round($totalSewa - $totalTerbayar, 2));
            $statusPembayaran = $sisaTagihan <= 0 ? 'lunas' : 'belum_lunas';
            $tenggatBaruOtomatis = null;

            if ($skema === 'bulanan' && $statusPembayaran === 'lunas') {
                $tenggatBaruOtomatis = app(TenggatSewaService::class)
                    ->majukanJikaLunas(
                        $penghunian,
                        $periodeTagihan,
                        $totalTerbayar
                    );
            }

            // Jika periode sudah lunas dan tenggat maju, status penghunian
            // mewakili periode baru yang belum dibayar.
            $penghunian->update([
                'status_pembayaran' => $tenggatBaruOtomatis
                    ? 'belum_lunas'
                    : $statusPembayaran,
            ]);

            return [
                'status' => 201,
                'body' => [
                    'message' => $jenis === 'dp'
                        ? 'DP berhasil dicatat.'
                        : 'Pembayaran berhasil dicatat.',
                    'data' => [
                        'pembayaran' => $pembayaran->fresh()->load([
                            'penghunian.kamar',
                            'penghunian.penghuni',
                        ]),
                        'total_sewa' => $totalSewa,
                        'total_terbayar' => $totalTerbayar,
                        'sisa_tagihan' => $sisaTagihan,
                        'status_pembayaran' => $statusPembayaran,
                        'periode_tagihan' => $periodeTagihan,
                        'tenggat_pelunasan_baru' => $tenggatBaruOtomatis,
                    ],
                ],
            ];
        });

        return response()->json($hasil['body'], $hasil['status']);
    }

    /**
     * Memperbarui pembayaran yang belum terverifikasi.
     */
    public function update(Request $request, int $id)
    {
        $pembayaran = Pembayaran::find($id);

        if (!$pembayaran) {
            return response()->json([
                'message' => 'Data pembayaran tidak ditemukan.',
                'error' => ['code' => 'PAYMENT_NOT_FOUND'],
            ], 404);
        }

        if ($pembayaran->status === 'terverifikasi') {
            return response()->json([
                'message' => 'Pembayaran yang sudah terverifikasi tidak dapat diubah.',
                'error' => ['code' => 'PAYMENT_ALREADY_VERIFIED'],
            ], 422);
        }

        $validated = $request->validate([
            'tanggal_bayar' => [
                'sometimes',
                'date',
            ],
            'jumlah' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],
            'metode_pembayaran' => [
                'sometimes',
                'string',
                'max:30',
            ],
            'bukti_pembayaran' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $pembayaran->update($validated);

        return response()->json([
            'message' => 'Data pembayaran berhasil diperbarui.',
            'data' => $pembayaran->fresh()->load([
                'penghunian.kamar',
                'penghunian.penghuni',
            ]),
        ]);
    }

    /**
     * Mengubah status pembayaran dan menghitung ulang status
     * pembayaran pada penghunian.
     */
    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'menunggu_verifikasi',
                    'terverifikasi',
                    'ditolak',
                ]),
            ],
        ]);

        $hasil = DB::transaction(function () use ($validated, $id) {
            // Ambil data awal untuk mengetahui kamar yang perlu dikunci.
            $pembayaranAwal = Pembayaran::find($id);

            if (!$pembayaranAwal) {
                return [
                    'status' => 404,
                    'body' => [
                        'message' => 'Data pembayaran tidak ditemukan.',
                        'error' => ['code' => 'PAYMENT_NOT_FOUND'],
                    ],
                ];
            }

            $penghunianAwal = Penghunian::find(
                $pembayaranAwal->id_penghunian
            );

            if (!$penghunianAwal) {
                return [
                    'status' => 404,
                    'body' => [
                        'message' => 'Data penghunian tidak ditemukan.',
                        'error' => ['code' => 'OCCUPANCY_NOT_FOUND'],
                    ],
                ];
            }

            // Urutan penguncian dibuat konsisten: kamar, penghunian, pembayaran.
            $kamar = Kamar::where(
                'id_kamar',
                $penghunianAwal->id_kamar
            )
                ->lockForUpdate()
                ->first();

            if (!$kamar) {
                return [
                    'status' => 404,
                    'body' => [
                        'message' => 'Data kamar tidak ditemukan.',
                        'error' => ['code' => 'ROOM_NOT_FOUND'],
                    ],
                ];
            }

            $penghuniAktif = Penghunian::where(
                'id_kamar',
                $kamar->id_kamar
            )
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->get();

            $penghunian = Penghunian::where(
                'id_penghunian',
                $pembayaranAwal->id_penghunian
            )
                ->lockForUpdate()
                ->first();

            $pembayaran = Pembayaran::where(
                'id_pembayaran',
                $id
            )
                ->lockForUpdate()
                ->first();

            if (!$pembayaran || !$penghunian) {
                return [
                    'status' => 404,
                    'body' => [
                        'message' => 'Data pembayaran atau penghunian tidak ditemukan.',
                        'error' => ['code' => 'DATA_NOT_FOUND'],
                    ],
                ];
            }

            $statusBaru = $validated['status'];
            $statusSebelumnya = $pembayaran->status;
            $totalSewa = round((float) ($penghunian->total_sewa ?? 0), 2);
            $periodeTagihan = $pembayaran->periode_tagihan
                ? Carbon::parse($pembayaran->periode_tagihan)->startOfMonth()->toDateString()
                : Carbon::parse($pembayaran->tanggal_bayar)->startOfMonth()->toDateString();

            if ($statusBaru === 'terverifikasi') {
                if (
                    !in_array($penghunian->skema_sewa, ['bulanan', 'tahunan'], true) ||
                    $totalSewa <= 0
                ) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Skema sewa dan total sewa belum dihitung.',
                            'error' => ['code' => 'RENT_SCHEME_NOT_SET'],
                        ],
                    ];
                }

                if (
                    $penghunian->skema_sewa === 'bulanan' &&
                    $pembayaran->jenis_pembayaran === 'cicilan'
                ) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Cicilan hanya tersedia untuk skema tahunan.',
                            'error' => ['code' => 'INSTALLMENT_NOT_ALLOWED'],
                        ],
                    ];
                }

                if ($pembayaran->jenis_pembayaran === 'dp') {
                    $dpMinimum = round((float) ($penghunian->dp_minimum ?? 0), 2);
                    $jumlahDp = round((float) $pembayaran->jumlah, 2);

                    if ($dpMinimum <= 0) {
                        return [
                            'status' => 422,
                            'body' => [
                                'message' => 'DP minimum belum dihitung.',
                                'error' => ['code' => 'DP_MINIMUM_NOT_SET'],
                            ],
                        ];
                    }

                    if ($jumlahDp < $dpMinimum) {
                        return [
                            'status' => 422,
                            'body' => [
                                'message' => 'DP tidak dapat diverifikasi karena jumlahnya kurang dari DP minimum Rp' .
                                    number_format($dpMinimum, 0, ',', '.') . '.',
                                'error' => [
                                    'code' => 'DP_BELOW_MINIMUM',
                                    'dp_minimum' => $dpMinimum,
                                    'jumlah_dp' => $jumlahDp,
                                ],
                            ],
                        ];
                    }

                    // Cegah DP ganda pada masa sewa kamar yang sama.
                    $idPenghunianAktif = $penghuniAktif
                        ->pluck('id_penghunian')
                        ->all();

                    $dpLain = Pembayaran::whereIn(
                        'id_penghunian',
                        $idPenghunianAktif
                    )
                        ->where('jenis_pembayaran', 'dp')
                        ->where('status', '!=', 'ditolak')
                        ->where('id_pembayaran', '!=', $pembayaran->id_pembayaran)
                        ->exists();

                    if ($dpLain) {
                        return [
                            'status' => 422,
                            'body' => [
                                'message' => 'DP lain sudah tercatat untuk masa sewa kamar ini.',
                                'error' => ['code' => 'DP_ALREADY_EXISTS'],
                            ],
                        ];
                    }
                }

                // Jumlah pembayaran terverifikasi lainnya.
                // Sewa bulanan dihitung per periode, sewa tahunan tetap akumulatif.
                $queryPembayaranLain = Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->where('id_pembayaran', '!=', $pembayaran->id_pembayaran);

                if ($penghunian->skema_sewa === 'bulanan') {
                    $queryPembayaranLain->whereDate(
                        'periode_tagihan',
                        $periodeTagihan
                    );
                }

                $totalPembayaranLain = round(
                    (float) $queryPembayaranLain->sum('jumlah'),
                    2
                );

                $sisaSebelum = max(
                    0,
                    round($totalSewa - $totalPembayaranLain, 2)
                );

                $jumlah = round((float) $pembayaran->jumlah, 2);

                if ($jumlah > $sisaSebelum) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Verifikasi pembayaran menyebabkan jumlah pembayaran melebihi sisa tagihan.',
                            'error' => [
                                'code' => 'PAYMENT_EXCEEDS_REMAINING',
                                'sisa_tagihan' => $sisaSebelum,
                            ],
                        ],
                    ];
                }

                if (
                    $pembayaran->jenis_pembayaran === 'pelunasan' &&
                    $jumlah != $sisaSebelum
                ) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Jumlah pelunasan harus sama dengan sisa tagihan.',
                            'error' => [
                                'code' => 'INVALID_FINAL_PAYMENT_AMOUNT',
                                'sisa_tagihan' => $sisaSebelum,
                            ],
                        ],
                    ];
                }

                if (
                    $pembayaran->jenis_pembayaran === 'cicilan' &&
                    $jumlah >= $sisaSebelum
                ) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Jika membayar seluruh sisa tagihan, pilih jenis pembayaran pelunasan.',
                            'error' => [
                                'code' => 'USE_SETTLEMENT_FOR_FULL_PAYMENT',
                                'sisa_tagihan' => $sisaSebelum,
                            ],
                        ],
                    ];
                }
            }

            $pembayaran->update([
                'status' => $statusBaru,
            ]);

            $queryTotalTerbayar = Pembayaran::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )->where('status', 'terverifikasi');

            if ($penghunian->skema_sewa === 'bulanan') {
                $queryTotalTerbayar->whereDate(
                    'periode_tagihan',
                    $periodeTagihan
                );
            }

            $totalTerbayar = round(
                (float) $queryTotalTerbayar->sum('jumlah'),
                2
            );

            $sisaTagihan = max(0, round($totalSewa - $totalTerbayar, 2));
            $statusPembayaran = (
                $totalSewa > 0 &&
                $sisaTagihan <= 0
            ) ? 'lunas' : 'belum_lunas';

            $tenggatBaruOtomatis = null;

            // Hanya transisi menuju terverifikasi yang boleh memajukan tenggat.
            if (
                $penghunian->skema_sewa === 'bulanan' &&
                $statusSebelumnya !== 'terverifikasi' &&
                $statusBaru === 'terverifikasi' &&
                $statusPembayaran === 'lunas'
            ) {
                $tenggatBaruOtomatis = app(TenggatSewaService::class)
                    ->majukanJikaLunas(
                        $penghunian,
                        $periodeTagihan,
                        $totalTerbayar
                    );
            }

            $penghunian->update([
                'status_pembayaran' => $tenggatBaruOtomatis
                    ? 'belum_lunas'
                    : $statusPembayaran,
            ]);

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Status pembayaran berhasil diperbarui.',
                    'data' => [
                        'pembayaran' => $pembayaran->fresh()->load([
                            'penghunian.kamar',
                            'penghunian.penghuni',
                        ]),
                        'total_sewa' => $totalSewa,
                        'total_terbayar' => $totalTerbayar,
                        'sisa_tagihan' => $sisaTagihan,
                        'status_pembayaran' => $statusPembayaran,
                        'periode_tagihan' => $penghunian->skema_sewa === 'bulanan'
                            ? $periodeTagihan
                            : null,
                        'tenggat_pelunasan_baru' => $tenggatBaruOtomatis,
                    ],
                ],
            ];
        });

        return response()->json($hasil['body'], $hasil['status']);
    }

    /**
     * Daftar pembayaran per kamar.
     * Satu kamar hanya menghasilkan satu baris dan nama yang ditampilkan
     * adalah nama penyewa utama. Jika hanya satu penghuni aktif, ia menjadi
     * penyewa utama secara fallback.
     */
    public function perKamar(Request $request)
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $penghunianAktif = Penghunian::with(['kamar', 'penghuni'])
            ->where('status', 'aktif')
            ->get();

        $idSemuaPenghunian = Penghunian::pluck('id_penghunian')->all();
        $adaPembayaran = !empty($idSemuaPenghunian)
            && Pembayaran::whereIn('id_penghunian', $idSemuaPenghunian)->exists();

        if (!$adaPembayaran) {
            return response()->json([
                'message' => 'Belum ada pembayaran.',
                'data' => [],
            ]);
        }

        $rows = $penghunianAktif->groupBy('id_kamar')->map(function ($daftar) {
            $utama = $daftar->firstWhere('penyewa_utama', true);
            if (!$utama && $daftar->count() === 1) {
                $utama = $daftar->first();
            }

            $idPenghunianKamar = Penghunian::where('id_kamar', $daftar->first()->id_kamar)
                ->pluck('id_penghunian')->all();
            $jumlahPembayaran = Pembayaran::whereIn('id_penghunian', $idPenghunianKamar)->count();

            $penyewaUtama = $utama ? [
                'id_penghuni' => $utama->id_penghuni,
                'nama_penghuni' => $utama->penghuni->nama_penghuni ?? '-',
            ] : null;

            $statusPembayaran = 'belum_ada_pembayaran';
            if ($jumlahPembayaran > 0 && $utama) {
                $periode = $utama->skema_sewa === 'bulanan' && $utama->tenggat_pelunasan
                    ? Carbon::parse($utama->tenggat_pelunasan)->startOfMonth()->toDateString()
                    : null;

                $query = Pembayaran::whereIn('id_penghunian', $idPenghunianKamar)
                    ->where('status', 'terverifikasi');
                if ($periode) {
                    $query->whereDate('periode_tagihan', $periode);
                }
                $terbayar = (float) $query->sum('jumlah');
                $tagihan = (float) ($utama->total_sewa ?? 0);
                $statusPembayaran = ($tagihan > 0 && $terbayar >= $tagihan)
                    ? 'lunas'
                    : 'belum_lunas';
            } elseif ($jumlahPembayaran > 0) {
                $statusPembayaran = 'belum_lunas';
            }

            $kamar = $daftar->first()->kamar;
            return [
                'id_kamar' => $daftar->first()->id_kamar,
                'no_kamar' => $kamar->no_kamar ?? '-',
                'penyewa_utama' => $penyewaUtama,
                'jumlah_penghuni_aktif' => $daftar->count(),
                'jumlah_pembayaran' => $jumlahPembayaran,
                'status_pembayaran' => $statusPembayaran,
                'riwayat_url' => '/api/pembayaran/kamar/' . $daftar->first()->id_kamar . '/riwayat',
            ];
        })->values();

        if ($rows->isEmpty()) {
            return response()->json([
                'message' => 'Belum ada pembayaran.',
                'data' => [],
            ]);
        }

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $total = $rows->count();
        $data = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'message' => 'Daftar pembayaran per kamar berhasil diambil.',
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Riwayat pembayaran satu kamar, termasuk pembayaran dari
     * penghunian lama maupun seluruh penghuni pada kamar yang sama.
     */
    public function riwayatPerKamar(int $id_kamar)
    {
        $kamar = Kamar::find($id_kamar);
        if (!$kamar) {
            return response()->json([
                'message' => 'Kamar tidak ditemukan.',
                'error' => [
                    'code' => 404,
                    'message' => 'Kamar tidak ditemukan.',
                    'status' => 'NOT_FOUND',
                    'details' => (object) [],
                ],
            ], 404);
        }

        $penghunianKamar = Penghunian::with('penghuni')
            ->where('id_kamar', $id_kamar)
            ->get();
        $idPenghunian = $penghunianKamar->pluck('id_penghunian')->all();

        $utama = $penghunianKamar->where('status', 'aktif')
            ->firstWhere('penyewa_utama', true);
        if (!$utama) {
            $utama = $penghunianKamar->where('status', 'aktif')->first();
        }
        if (!$utama) {
            $utama = $penghunianKamar->sortByDesc('id_penghunian')->first();
        }

        $pembayaran = Pembayaran::with('penghunian.penghuni')
            ->whereIn('id_penghunian', $idPenghunian)
            ->orderByDesc('tanggal_bayar')
            ->orderByDesc('id_pembayaran')
            ->get()
            ->map(function ($item) {
                return [
                    'id_pembayaran' => $item->id_pembayaran,
                    'id_penghunian' => $item->id_penghunian,
                    'nama_penghuni' => $item->penghunian->penghuni->nama_penghuni ?? '-',
                    'jenis_pembayaran' => $item->jenis_pembayaran,
                    'tanggal_bayar' => $item->tanggal_bayar
                        ? Carbon::parse($item->tanggal_bayar)->toDateString()
                        : null,
                    'jumlah' => (float) $item->jumlah,
                    'metode_pembayaran' => $item->metode_pembayaran,
                    'status' => $item->status,
                ];
            })->values();

        return response()->json([
            'message' => $pembayaran->isEmpty()
                ? 'Belum ada pembayaran.'
                : 'Riwayat pembayaran kamar berhasil diambil.',
            'data' => [
                'id_kamar' => $kamar->id_kamar,
                'no_kamar' => $kamar->no_kamar,
                'penyewa_utama' => $utama->penghuni->nama_penghuni ?? null,
                'pembayaran' => $pembayaran,
            ],
        ]);
    }


}
