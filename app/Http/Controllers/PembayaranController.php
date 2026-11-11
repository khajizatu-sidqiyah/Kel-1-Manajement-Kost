<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\Pembayaran;
use App\Models\Penghunian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PembayaranController extends Controller
{
    /**
     * Menampilkan seluruh pembayaran dengan filter,
     * search, dan pagination.
     */
    public function index(Request $request)
    {
        $query = Pembayaran::with([
            'penghunian.kamar',
            'penghunian.penghuni',
        ]);

        if ($request->filled('jenis_pembayaran')) {
            $query->where(
                'jenis_pembayaran',
                $request->jenis_pembayaran
            );
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('metode_pembayaran')) {
            $query->where(
                'metode_pembayaran',
                $request->metode_pembayaran
            );
        }

        $search = $request->input(
            'q',
            $request->input('search')
        );

        if ($search) {
            $query->whereHas(
                'penghunian.penghuni',
                function ($q) use ($search) {
                    $q->where(
                        'nama_penghuni',
                        'like',
                        "%{$search}%"
                    );
                }
            );
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate(
                'tanggal_bayar',
                '>=',
                $request->tanggal_mulai
            );
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate(
                'tanggal_bayar',
                '<=',
                $request->tanggal_selesai
            );
        }

        $query->orderByDesc('tanggal_bayar')
            ->orderByDesc('created_at');

        $tampilkanSemua =
            $request->input('tampilkan_semua') == 1 ||
            $request->input('per_page') === 'all';

        if ($tampilkanSemua) {
            return response()->json([
                'message' => 'Data pembayaran berhasil ditampilkan.',
                'data' => $query->get(),
                'pagination' => null,
            ]);
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $data = $query->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'message' => 'Data pembayaran berhasil ditampilkan.',
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
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
            'message' => 'Detail pembayaran berhasil ditampilkan.',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Mencatat DP, cicilan, atau pelunasan.
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
                Rule::in([
                    'dp',
                    'cicilan',
                    'pelunasan',
                ]),
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
        ]);

        $hasil = DB::transaction(function () use ($validated) {

            /*
             * Ambil penghunian yang dikirim.
             */
            $penghunianInput = Penghunian::find(
                $validated['id_penghunian']
            );

            if (!$penghunianInput) {
                return [
                    'status' => 404,
                    'body' => [
                        'message' => 'Data penghunian tidak ditemukan.',
                        'error' => [
                            'code' => 'OCCUPANCY_NOT_FOUND',
                        ],
                    ],
                ];
            }

            /*
             * Kunci data kamar agar transaksi pembayaran
             * tidak berjalan bersamaan tanpa kontrol.
             */
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
                        'error' => [
                            'code' => 'ROOM_NOT_FOUND',
                        ],
                    ],
                ];
            }

            /*
             * Ambil semua penghuni aktif dalam kamar.
             */
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
                        'error' => [
                            'code' => 'OCCUPANCY_NOT_ACTIVE',
                        ],
                    ],
                ];
            }

            /*
             * Cari penyewa utama.
             */
            $daftarUtama = $penghuniAktif->where(
                'penyewa_utama',
                true
            );

            if ($daftarUtama->count() > 1) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Terdapat lebih dari satu penyewa utama pada kamar ini.',
                        'error' => [
                            'code' => 'MULTIPLE_PRIMARY_TENANTS',
                        ],
                    ],
                ];
            }

            $utama = $daftarUtama->first();

            /*
             * Jika hanya ada satu penghuni aktif,
             * otomatis jadikan penyewa utama.
             */
            if (!$utama && $penghuniAktif->count() === 1) {
                $utama = $penghuniAktif->first();

                $utama->update([
                    'penyewa_utama' => true,
                ]);
            }

            if (!$utama) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Penyewa utama belum ditentukan.',
                        'error' => [
                            'code' => 'PRIMARY_TENANT_NOT_FOUND',
                        ],
                    ],
                ];
            }

            /*
             * Semua pembayaran kamar ini dicatat
             * melalui penghunian penyewa utama.
             */
            $penghunian = $utama->fresh();

            $jenis = $validated['jenis_pembayaran'];
            $jumlah = round((float) $validated['jumlah'], 2);

            $skema = $penghunian->skema_sewa;

            $totalSewa = round(
                (float) ($penghunian->total_sewa ?? 0),
                2
            );

            if (
                !in_array($skema, ['bulanan', 'tahunan'], true) ||
                $totalSewa <= 0
            ) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Pilih dan simpan skema sewa terlebih dahulu.',
                        'error' => [
                            'code' => 'RENT_SCHEME_NOT_SET',
                        ],
                    ],
                ];
            }

            /*
             * Validasi jenis pembayaran sesuai skema.
             *
             * Bulanan:
             * - DP
             * - Pelunasan
             *
             * Tahunan:
             * - DP
             * - Cicilan
             * - Pelunasan
             */
            if (
                $skema === 'bulanan' &&
                $jenis === 'cicilan'
            ) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Cicilan hanya diperbolehkan untuk skema sewa tahunan.',
                        'error' => [
                            'code' => 'INSTALLMENT_NOT_ALLOWED',
                        ],
                    ],
                ];
            }

            /*
             * Siapkan variabel tenggat.
             * Tenggat baru tidak langsung disimpan sebelum
             * seluruh validasi pembayaran berhasil.
             */
            $tenggatBaru = null;

            /*
             * Ambil ID seluruh penghunian aktif
             * untuk pengecekan DP satu kali per masa sewa.
             */
            $idPenghunianAktif = $penghuniAktif
                ->pluck('id_penghunian')
                ->all();

            /*
             * DP hanya boleh dicatat satu kali.
             */
            if ($jenis === 'dp') {
                $sudahAdaDp = Pembayaran::whereIn(
                    'id_penghunian',
                    $idPenghunianAktif
                )
                    ->where('jenis_pembayaran', 'dp')
                    ->exists();

                if ($sudahAdaDp) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'DP untuk masa sewa ini sudah pernah dicatat.',
                            'error' => [
                                'code' => 'DP_ALREADY_EXISTS',
                            ],
                        ],
                    ];
                }

                /*
                 * DP tidak boleh melebihi total sewa.
                 */
                if ($jumlah > $totalSewa) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Jumlah DP tidak boleh melebihi total sewa.',
                            'error' => [
                                'code' => 'DP_EXCEEDS_RENT',
                            ],
                        ],
                    ];
                }

                /*
                 * Ambil batas minimum DP.
                 */
                $dpMinimum = round(
                    (float) ($penghunian->dp_minimum ?? 0),
                    2
                );

                if ($dpMinimum <= 0) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'DP minimum belum tersedia. Atur skema sewa terlebih dahulu.',
                            'error' => [
                                'code' => 'DP_MINIMUM_NOT_SET',
                            ],
                        ],
                    ];
                }

                /*
                 * DP di bawah minimum langsung ditolak.
                 */
                if ($jumlah < $dpMinimum) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Jumlah DP tidak boleh kurang dari DP minimum.',
                            'error' => [
                                'code' => 'DP_BELOW_MINIMUM',
                            ],
                            'dp_minimum' => $dpMinimum,
                            'jumlah_dp' => $jumlah,
                        ],
                    ];
                }

                /*
                 * Pengubahan tenggat melalui pencatatan DP
                 * hanya diperbolehkan untuk skema tahunan.
                 */
                if (
                    !empty($validated['tenggat_pelunasan']) &&
                    $skema !== 'tahunan'
                ) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Pengubahan tenggat melalui form DP hanya berlaku untuk sewa tahunan.',
                            'error' => [
                                'code' => 'INVALID_DUE_DATE_SCHEME',
                            ],
                        ],
                    ];
                }

                /*
                 * Validasi tanggal tenggat pelunasan.
                 */
                if (!empty($validated['tenggat_pelunasan'])) {
                    if (
                        $penghunian->tanggal_mulai &&
                        Carbon::parse(
                            $validated['tenggat_pelunasan']
                        )->lt(
                            Carbon::parse($penghunian->tanggal_mulai)
                        )
                    ) {
                        return [
                            'status' => 422,
                            'body' => [
                                'message' => 'Tenggat pelunasan tidak boleh sebelum tanggal mulai sewa.',
                                'error' => [
                                    'code' => 'INVALID_DUE_DATE',
                                ],
                            ],
                        ];
                    }

                    /*
                     * Simpan sementara.
                     * Belum mengubah database.
                     */
                    $tenggatBaru = $validated['tenggat_pelunasan'];
                }
            }

            /*
             * Hitung total pembayaran yang sudah terverifikasi.
             */
            $totalTerbayar = round(
                (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->sum('jumlah'),
                2
            );

            $sisaTagihan = round(
                max(0, $totalSewa - $totalTerbayar),
                2
            );

            if ($sisaTagihan <= 0) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Tagihan sudah lunas. Tidak dapat menambahkan pembayaran lagi.',
                        'error' => [
                            'code' => 'BILL_ALREADY_PAID',
                        ],
                    ],
                ];
            }

            /*
             * Jumlah pembayaran tidak boleh melebihi
             * sisa tagihan.
             */
            if ($jumlah > $sisaTagihan) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Jumlah pembayaran melebihi sisa tagihan.',
                        'error' => [
                            'code' => 'PAYMENT_EXCEEDS_REMAINING',
                        ],
                        'sisa_tagihan' => $sisaTagihan,
                    ],
                ];
            }

            /*
             * Pelunasan wajib sama dengan sisa tagihan.
             */
            if (
                $jenis === 'pelunasan' &&
                $jumlah != $sisaTagihan
            ) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Jumlah pelunasan harus sama dengan sisa tagihan.',
                        'error' => [
                            'code' => 'INVALID_SETTLEMENT_AMOUNT',
                        ],
                        'sisa_tagihan' => $sisaTagihan,
                    ],
                ];
            }

            /*
             * Cicilan tahunan tidak boleh menghabiskan
             * seluruh sisa tagihan.
             *
             * Jika ingin membayar seluruh sisa tagihan,
             * gunakan jenis pembayaran pelunasan.
             */
            if (
                $jenis === 'cicilan' &&
                $jumlah >= $sisaTagihan
            ) {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'Jika membayar seluruh sisa tagihan, pilih jenis pembayaran pelunasan.',
                        'error' => [
                            'code' => 'USE_SETTLEMENT_FOR_FULL_PAYMENT',
                        ],
                        'sisa_tagihan' => $sisaTagihan,
                    ],
                ];
            }

            /*
             * Jika semua validasi berhasil,
             * baru simpan perubahan tenggat pelunasan.
             */
            if ($tenggatBaru !== null) {
                $penghunian->update([
                    'tenggat_pelunasan' => $tenggatBaru,
                ]);
            }

            /*
             * Simpan pembayaran.
             * Status terverifikasi mengikuti alur controller
             * sebelumnya.
             */
            $pembayaran = Pembayaran::create([
                'id_penghunian' => $penghunian->id_penghunian,
                'jenis_pembayaran' => $jenis,
                'tanggal_bayar' => $validated['tanggal_bayar'],
                'jumlah' => $jumlah,
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'bukti_pembayaran' => $validated['bukti_pembayaran'] ?? null,
                'status' => 'terverifikasi',
            ]);

            /*
             * Hitung ulang total pembayaran.
             */
            $totalTerbayarBaru = round(
                (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->sum('jumlah'),
                2
            );

            $sisaBaru = round(
                max(0, $totalSewa - $totalTerbayarBaru),
                2
            );

            $statusPembayaran = $sisaBaru <= 0
                ? 'lunas'
                : 'belum_lunas';

            $penghunian->update([
                'status_pembayaran' => $statusPembayaran,
            ]);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Pembayaran berhasil dicatat.',
                    'data' => $pembayaran->fresh()->load([
                        'penghunian.kamar',
                        'penghunian.penghuni',
                    ]),
                    'total_sewa' => $totalSewa,
                    'total_terbayar' => $totalTerbayarBaru,
                    'sisa_tagihan' => $sisaBaru,
                    'status_pembayaran' => $statusPembayaran,
                ],
            ];
        });

        return response()->json(
            $hasil['body'],
            $hasil['status']
        );
    }

    /**
     * Mengubah data pembayaran yang belum terverifikasi.
     */
    public function update(Request $request, int $id)
    {
        $pembayaran = Pembayaran::find($id);

        if (!$pembayaran) {
            return response()->json([
                'message' => 'Data pembayaran tidak ditemukan.',
                'error' => [
                    'code' => 'PAYMENT_NOT_FOUND',
                ],
            ], 404);
        }

        if ($pembayaran->status === 'terverifikasi') {
            return response()->json([
                'message' => 'Pembayaran yang sudah terverifikasi tidak dapat diubah.',
                'error' => [
                    'code' => 'PAYMENT_ALREADY_VERIFIED',
                ],
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
     * Mengubah status pembayaran.
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

        $hasil = DB::transaction(function () use (
            $id,
            $validated
        ) {
            $pembayaran = Pembayaran::where(
                'id_pembayaran',
                $id
            )
                ->lockForUpdate()
                ->first();

            if (!$pembayaran) {
                return [
                    'status' => 404,
                    'body' => [
                        'message' => 'Data pembayaran tidak ditemukan.',
                        'error' => [
                            'code' => 'PAYMENT_NOT_FOUND',
                        ],
                    ],
                ];
            }

            $penghunian = Penghunian::where(
                'id_penghunian',
                $pembayaran->id_penghunian
            )
                ->lockForUpdate()
                ->first();

            if (!$penghunian) {
                return [
                    'status' => 404,
                    'body' => [
                        'message' => 'Data penghunian tidak ditemukan.',
                        'error' => [
                            'code' => 'OCCUPANCY_NOT_FOUND',
                        ],
                    ],
                ];
            }

            $statusBaru = $validated['status'];

            $totalSewa = round(
                (float) ($penghunian->total_sewa ?? 0),
                2
            );

            /*
             * Jika akan diverifikasi, pastikan total pembayaran
             * tidak melebihi total sewa.
             */
            if ($statusBaru === 'terverifikasi') {
                $totalPembayaranLain = round(
                    (float) Pembayaran::where(
                        'id_penghunian',
                        $penghunian->id_penghunian
                    )
                        ->where('status', 'terverifikasi')
                        ->where(
                            'id_pembayaran',
                            '!=',
                            $pembayaran->id_pembayaran
                        )
                        ->sum('jumlah'),
                    2
                );

                $totalSetelahVerifikasi = round(
                    $totalPembayaranLain +
                    (float) $pembayaran->jumlah,
                    2
                );

                if ($totalSetelahVerifikasi > $totalSewa) {
                    return [
                        'status' => 422,
                        'body' => [
                            'message' => 'Pembayaran tidak dapat diverifikasi karena melebihi total sewa.',
                            'error' => [
                                'code' => 'PAYMENT_EXCEEDS_RENT',
                            ],
                        ],
                    ];
                }
            }

            $pembayaran->update([
                'status' => $statusBaru,
            ]);

            /*
             * Hitung ulang pembayaran terverifikasi.
             */
            $totalTerbayar = round(
                (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->sum('jumlah'),
                2
            );

            $sisaTagihan = round(
                max(0, $totalSewa - $totalTerbayar),
                2
            );

            $statusPembayaran = (
                $totalSewa > 0 &&
                $sisaTagihan <= 0
            )
                ? 'lunas'
                : 'belum_lunas';

            $penghunian->update([
                'status_pembayaran' => $statusPembayaran,
            ]);

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Status pembayaran berhasil diperbarui.',
                    'data' => $pembayaran->fresh()->load([
                        'penghunian.kamar',
                        'penghunian.penghuni',
                    ]),
                    'total_sewa' => $totalSewa,
                    'total_terbayar' => $totalTerbayar,
                    'sisa_tagihan' => $sisaTagihan,
                    'status_pembayaran' => $statusPembayaran,
                ],
            ];
        });

        return response()->json(
            $hasil['body'],
            $hasil['status']
        );
    }
}
