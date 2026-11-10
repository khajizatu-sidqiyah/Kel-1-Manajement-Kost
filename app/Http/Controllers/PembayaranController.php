<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\Pembayaran;
use App\Models\Penghunian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PembayaranController extends Controller
{
    /**
     * Menampilkan data pembayaran dengan filter,
     * pencarian, pagination, dan tampilkan semua.
     */
    public function index(Request $request)
    {
        $query = Pembayaran::with([
            'penghunian.kamar',
            'penghunian.penghuni',
        ]);

        // FILTER JENIS PEMBAYARAN
        $jenis = $request->input('jenis_pembayaran', []);

        if (!is_array($jenis)) {
            $jenis = [$jenis];
        }

        $jenis = array_values(array_filter(
            $jenis,
            fn ($value) => $value !== null && $value !== ''
        ));

        if (!empty($jenis)) {
            $query->whereIn('jenis_pembayaran', $jenis);
        }

        // FILTER STATUS PEMBAYARAN
        $status = $request->input('status', []);

        if (!is_array($status)) {
            $status = [$status];
        }

        $status = array_values(array_filter(
            $status,
            fn ($value) => $value !== null && $value !== ''
        ));

        if (!empty($status)) {
            $query->whereIn('status', $status);
        }

        // FILTER METODE PEMBAYARAN
        $metode = $request->input('metode_pembayaran', []);

        if (!is_array($metode)) {
            $metode = [$metode];
        }

        $metode = array_values(array_filter(
            $metode,
            fn ($value) => $value !== null && $value !== ''
        ));

        if (!empty($metode)) {
            $query->whereIn('metode_pembayaran', $metode);
        }

        // PENCARIAN NAMA PENGHUNI
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

        // FILTER RENTANG TANGGAL
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

        // URUTKAN PEMBAYARAN TERBARU
        $baseQuery = $query->orderByDesc('tanggal_bayar');

        $total = (clone $baseQuery)->count();

        // TAMPILKAN SEMUA
        $showAll = $request->boolean('tampilkan_semua')
            || strtolower((string) $request->input('per_page')) === 'all';

        if ($showAll) {
            $rows = $baseQuery->get();

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
            // DEFAULT 10 DATA PER HALAMAN
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
            'konfirmasi_dp' => [
                'sometimes',
                'boolean',
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

        return DB::transaction(function () use ($validated) {

            $penghunianInput = Penghunian::find(
                $validated['id_penghunian']
            );

            if (
                !$penghunianInput ||
                $penghunianInput->status !== 'aktif'
            ) {
                return response()->json([
                    'message' =>
                        'Pembayaran hanya dapat dicatat untuk penghunian aktif.',
                    'error' => [
                        'code' => 'OCCUPANCY_NOT_ACTIVE',
                    ],
                ], 422);
            }

            // Kunci kamar untuk mencegah transaksi bersamaan.
            Kamar::where(
                'id_kamar',
                $penghunianInput->id_kamar
            )->lockForUpdate()->first();

            // Ambil penghuni aktif pada kamar tersebut.
            $penghunianAktif = Penghunian::where(
                'id_kamar',
                $penghunianInput->id_kamar
            )
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->get();

            // Tentukan penyewa utama.
            $penghunian = $penghunianAktif->firstWhere(
                'penyewa_utama',
                true
            );

            // Jika hanya ada satu penghuni aktif,
            // otomatis jadikan penyewa utama.
            if (
                !$penghunian &&
                $penghunianAktif->count() === 1
            ) {
                $penghunian = $penghunianAktif->first();

                $penghunian->update([
                    'penyewa_utama' => true,
                ]);
            }

            if (!$penghunian) {
                return response()->json([
                    'message' =>
                        'Penyewa utama kamar belum ditentukan.',
                    'error' => [
                        'code' => 'PRIMARY_TENANT_NOT_FOUND',
                    ],
                ], 422);
            }

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
                return response()->json([
                    'message' =>
                        'Pilih dan hitung skema sewa terlebih dahulu sebelum mencatat DP.',
                    'error' => [
                        'code' => 'RENT_SCHEME_NOT_SET',
                    ],
                ], 422);
            }

            // Cicilan hanya tersedia untuk sewa tahunan.
            if (
                $skema === 'bulanan' &&
                $jenis === 'cicilan'
            ) {
                return response()->json([
                    'message' =>
                        'Jenis pembayaran cicilan hanya tersedia untuk skema tahunan.',
                    'error' => [
                        'code' => 'INSTALLMENT_NOT_ALLOWED',
                    ],
                ], 422);
            }

            // VALIDASI DP
            if ($jenis === 'dp') {

                $sudahAdaDp = Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('jenis_pembayaran', 'dp')
                    ->exists();

                if ($sudahAdaDp) {
                    return response()->json([
                        'message' =>
                            'DP untuk kamar ini sudah pernah dicatat.',
                        'error' => [
                            'code' => 'DP_ALREADY_EXISTS',
                        ],
                    ], 422);
                }

                $dpMinimum = round(
                    (float) ($penghunian->dp_minimum ?? 0),
                    2
                );

                if ($dpMinimum <= 0) {
                    return response()->json([
                        'message' =>
                            'DP minimum belum dihitung. Hitung skema sewa terlebih dahulu.',
                        'error' => [
                            'code' => 'DP_MINIMUM_NOT_SET',
                        ],
                    ], 422);
                }

                if ($jumlah > $totalSewa) {
                    return response()->json([
                        'message' =>
                            'Jumlah DP tidak boleh melebihi total sewa.',
                        'error' => [
                            'code' => 'DP_EXCEEDS_RENT',
                            'sisa_tagihan' => $totalSewa,
                        ],
                    ], 422);
                }

                // DP di bawah minimum memerlukan konfirmasi.
                if (
                    $jumlah < $dpMinimum &&
                    !($validated['konfirmasi_dp'] ?? false)
                ) {
                    $formatMinimum = 'Rp' .
                        number_format($dpMinimum, 0, ',', '.');

                    return response()->json([
                        'message' =>
                            'DP di bawah minimum ' . $formatMinimum . '.',
                        'data' => [
                            'peringatan' =>
                                'DP di bawah minimum ' . $formatMinimum . '.',
                            'konfirmasi_diperlukan' => true,
                            'dp_minimum' => $dpMinimum,
                            'jumlah_dp' => $jumlah,
                        ],
                    ], 200);
                }

                // Simpan tenggat jika pemilik mengirim perubahan.
                if (
                    array_key_exists('tenggat_pelunasan', $validated) &&
                    $validated['tenggat_pelunasan'] !== null
                ) {
                    $tanggalTenggat = $validated['tenggat_pelunasan'];

                    if (
                        $penghunian->tanggal_mulai &&
                        $tanggalTenggat <
                        $penghunian->tanggal_mulai->toDateString()
                    ) {
                        return response()->json([
                            'message' =>
                                'Tenggat tidak boleh sebelum tanggal mulai sewa.',
                            'error' => [
                                'code' => 'INVALID_DUE_DATE',
                            ],
                        ], 422);
                    }

                    $penghunian->update([
                        'tenggat_pelunasan' => $tanggalTenggat,
                    ]);
                }
            }

            // Hitung pembayaran terverifikasi sebelumnya.
            $totalTerbayarSebelum = round(
                (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->sum('jumlah'),
                2
            );

            $sisaSebelum = max(
                0,
                round($totalSewa - $totalTerbayarSebelum, 2)
            );

            // VALIDASI CICILAN DAN PELUNASAN
            if ($jenis !== 'dp') {

                if ($sisaSebelum <= 0) {
                    return response()->json([
                        'message' => 'Tagihan kamar sudah lunas.',
                        'error' => [
                            'code' => 'RENT_ALREADY_PAID',
                        ],
                    ], 422);
                }

                if ($jumlah > $sisaSebelum) {
                    return response()->json([
                        'message' =>
                            'Jumlah pembayaran melebihi sisa tagihan.',
                        'error' => [
                            'code' => 'PAYMENT_EXCEEDS_REMAINING',
                            'sisa_tagihan' => $sisaSebelum,
                        ],
                    ], 422);
                }

                if (
                    $jenis === 'pelunasan' &&
                    $jumlah != $sisaSebelum
                ) {
                    return response()->json([
                        'message' =>
                            'Jumlah pelunasan harus sama dengan sisa tagihan.',
                        'error' => [
                            'code' => 'INVALID_FINAL_PAYMENT_AMOUNT',
                            'sisa_tagihan' => $sisaSebelum,
                        ],
                    ], 422);
                }
            }

            // SIMPAN PEMBAYARAN
            $pembayaran = Pembayaran::create([
                'id_penghunian' => $penghunian->id_penghunian,
                'jenis_pembayaran' => $jenis,
                'tanggal_bayar' => $validated['tanggal_bayar'],
                'jumlah' => $jumlah,
                'metode_pembayaran' =>
                    $validated['metode_pembayaran'],
                'bukti_pembayaran' =>
                    $validated['bukti_pembayaran'] ?? null,
                'status' => 'terverifikasi',
            ]);

            // Hitung total pembayaran setelah transaksi.
            $totalTerbayar = round(
                (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->sum('jumlah'),
                2
            );

            $sisaTagihan = max(
                0,
                round($totalSewa - $totalTerbayar, 2)
            );

            $statusPembayaran = $sisaTagihan <= 0
                ? 'lunas'
                : 'belum_lunas';

            $penghunian->update([
                'status_pembayaran' => $statusPembayaran,
            ]);

            return response()->json([
                'message' => $jenis === 'dp'
                    ? 'DP berhasil dicatat.'
                    : 'Pembayaran berhasil dicatat.',
                'data' => [
                    'pembayaran' => $pembayaran->load([
                        'penghunian.kamar',
                        'penghunian.penghuni',
                    ]),
                    'total_sewa' => $totalSewa,
                    'total_terbayar' => $totalTerbayar,
                    'sisa_tagihan' => $sisaTagihan,
                    'status_pembayaran' => $statusPembayaran,
                ],
            ], 201);
        });
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
                'error' => [
                    'code' => 'PAYMENT_NOT_FOUND',
                ],
            ], 404);
        }

        if ($pembayaran->status === 'terverifikasi') {
            return response()->json([
                'message' =>
                    'Pembayaran yang sudah terverifikasi tidak dapat diubah.',
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
     * Mengubah status pembayaran dan menghitung ulang
     * status pembayaran pada penghunian.
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

        return DB::transaction(function () use ($validated, $id) {

            $pembayaran = Pembayaran::lockForUpdate()->find($id);

            if (!$pembayaran) {
                return response()->json([
                    'message' => 'Data pembayaran tidak ditemukan.',
                    'error' => [
                        'code' => 'PAYMENT_NOT_FOUND',
                    ],
                ], 404);
            }

            $penghunian = Penghunian::lockForUpdate()->find(
                $pembayaran->id_penghunian
            );

            if (!$penghunian) {
                return response()->json([
                    'message' => 'Data penghunian tidak ditemukan.',
                    'error' => [
                        'code' => 'OCCUPANCY_NOT_FOUND',
                    ],
                ], 404);
            }

            $totalSewa = round(
                (float) ($penghunian->total_sewa ?? 0),
                2
            );

            // Cegah pembayaran terverifikasi melebihi total sewa.
            if (
                $validated['status'] === 'terverifikasi' &&
                $totalSewa > 0
            ) {
                $pembayaranLain = (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->where(
                        'id_pembayaran',
                        '!=',
                        $pembayaran->id_pembayaran
                    )
                    ->sum('jumlah');

                if (
                    round(
                        $pembayaranLain +
                        (float) $pembayaran->jumlah,
                        2
                    ) > $totalSewa
                ) {
                    return response()->json([
                        'message' =>
                            'Verifikasi menyebabkan pembayaran melebihi total sewa.',
                        'error' => [
                            'code' => 'PAYMENT_EXCEEDS_RENT',
                        ],
                    ], 422);
                }
            }

            $pembayaran->update([
                'status' => $validated['status'],
            ]);

            $totalTerbayar = round(
                (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->sum('jumlah'),
                2
            );

            $sisaTagihan = max(
                0,
                round($totalSewa - $totalTerbayar, 2)
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

            return response()->json([
                'message' =>
                    'Status pembayaran berhasil diperbarui.',
                'data' => [
                    'pembayaran' => $pembayaran->fresh(),
                    'total_sewa' => $totalSewa,
                    'total_terbayar' => $totalTerbayar,
                    'sisa_tagihan' => $sisaTagihan,
                    'status_pembayaran' => $statusPembayaran,
                ],
            ]);
        });
    }
}
