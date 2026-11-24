<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\Pembayaran;
use App\Models\Penghunian;
use App\Services\TenggatSewaService;
use App\Models\AlokasiPembayaran;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Tagihan;
use App\Services\TagihanService;
use Illuminate\Database\Eloquent\Collection;

class PembayaranController extends Controller
{
    /**
     * Format response error mengikuti API contract.
     */
    private function errorResponse(
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
     * Validasi request.
     */
    private function validateRequest(
        Request $request,
        array $rules,
        array $messages = []
    ) {
        $validator = Validator::make(
            $request->all(),
            $rules,
            $messages
        );

        if ($validator->fails()) {
            return $this->errorResponse(
                422,
                'Validasi request gagal.',
                'VALIDATION_ERROR',
                $validator->errors()->toArray()
            );
        }

        return $validator->validated();
    }

    /**
     * Menampilkan data pembayaran dengan filter,
     * pencarian, pagination, dan opsi tampilkan semua.
     */
    public function index(Request $request)
    {
        $query = Pembayaran::with([
            'penghunian.kamar',
            'penghunian.penghuni',
        ]);

        // Filter jenis pembayaran.
        $jenis = $request->input('jenis_pembayaran', []);
        $jenis = is_array($jenis) ? $jenis : [$jenis];
        $jenis = array_values(array_filter(
            $jenis,
            fn ($value) => $value !== null && $value !== ''
        ));

        if (!empty($jenis)) {
            $query->whereIn('jenis_pembayaran', $jenis);
        }

        // Filter status.
        $status = $request->input('status', []);
        $status = is_array($status) ? $status : [$status];
        $status = array_values(array_filter(
            $status,
            fn ($value) => $value !== null && $value !== ''
        ));

        if (!empty($status)) {
            $query->whereIn('status', $status);
        }

        // Filter metode pembayaran.
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
            return $this->errorResponse(
                404,
                'Data pembayaran tidak ditemukan.',
                'NOT_FOUND'
            );
        }

        return response()->json([
            'message' => 'Detail pembayaran berhasil diambil.',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Mencatat DP, cicilan, atau pelunasan.
     *
     * Pembayaran kamar bersama dicatat atas penghunian
     * penyewa utama.
     */
    public function store(Request $request)
    {
        $validated = $this->validateRequest(
            $request,
            [
                'id_penghunian' => [
                    'required',
                    'integer',
                    'min:1',
                    'exists:penghunian,id_penghunian',
                ],
                'jenis_pembayaran' => [
                    'required',
                    'string',
                    Rule::in(['dp', 'cicilan', 'pelunasan']),
                ],
                'tanggal_bayar' => [
                    'sometimes',
                    'nullable',
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
                    'sometimes',
                    'nullable',
                    'string',
                    'max:255',
                ],
                'tenggat_pelunasan' => [
                    'sometimes',
                    'nullable',
                    'date',
                ],
            ],
            [
                'id_penghunian.required' =>
                    'ID penghunian wajib diisi.',
                'id_penghunian.exists' =>
                    'Data penghunian tidak ditemukan.',
                'jenis_pembayaran.required' =>
                    'Jenis pembayaran wajib diisi.',
                'jenis_pembayaran.in' =>
                    'Jenis pembayaran hanya dp, cicilan, atau pelunasan.',
                'jumlah.required' =>
                    'Jumlah pembayaran wajib diisi.',
                'jumlah.numeric' =>
                    'Jumlah pembayaran harus berupa angka.',
                'jumlah.gt' =>
                    'Jumlah pembayaran harus lebih besar dari nol.',
                'metode_pembayaran.required' =>
                    'Metode pembayaran wajib diisi.',
            ]
        );

        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        return DB::transaction(function () use ($validated) {

            // 1. Memeriksa penghunian.
            $penghunianInput = Penghunian::find(
                $validated['id_penghunian']
            );

            if (
                !$penghunianInput ||
                $penghunianInput->status !== 'aktif'
            ) {
                return $this->errorResponse(
                    422,
                    'Pembayaran hanya dapat dicatat untuk penghunian aktif.',
                    'OCCUPANCY_NOT_ACTIVE'
                );
            }

            // 2. Mengunci kamar.
            $kamar = Kamar::where(
                'id_kamar',
                $penghunianInput->id_kamar
            )
                ->lockForUpdate()
                ->first();

            if (!$kamar) {
                return $this->errorResponse(
                    404,
                    'Data kamar tidak ditemukan.',
                    'ROOM_NOT_FOUND'
                );
            }

            // 3. Mengambil seluruh penghuni aktif pada kamar.
            $penghunianAktif = Penghunian::where(
                'id_kamar',
                $penghunianInput->id_kamar
            )
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->get();

            $penghunianInputAktif = $penghunianAktif->firstWhere(
                'id_penghunian',
                $penghunianInput->id_penghunian
            );

            if (!$penghunianInputAktif) {
                return $this->errorResponse(
                    422,
                    'Pembayaran hanya dapat dicatat untuk penghunian aktif.',
                    'OCCUPANCY_NOT_ACTIVE'
                );
            }

            // 4. Memastikan hanya ada satu penyewa utama.
            $daftarUtama = $penghunianAktif->where(
                'penyewa_utama',
                true
            );

            if ($daftarUtama->count() > 1) {
                return $this->errorResponse(
                    422,
                    'Terdapat lebih dari satu penyewa utama pada kamar ini.',
                    'MULTIPLE_PRIMARY_TENANTS'
                );
            }

            $penghunian = $daftarUtama->first();

            // Jika hanya satu penghuni aktif, jadikan penyewa utama.
            if (!$penghunian && $penghunianAktif->count() === 1) {
                $penghunian = $penghunianAktif->first();

                $penghunian->update([
                    'penyewa_utama' => true,
                ]);
            }

            if (!$penghunian) {
                return $this->errorResponse(
                    422,
                    'Penyewa utama belum ditentukan.',
                    'PRIMARY_TENANT_NOT_FOUND'
                );
            }

            $penghunian->refresh();

            // 5. Mengambil skema dan total sewa.
            $jenis = $validated['jenis_pembayaran'];

            $jumlah = round(
                (float) $validated['jumlah'],
                2
            );

            $skema = $penghunian->skema_sewa;

            $totalSewa = round(
                (float) ($penghunian->total_sewa ?? 0),
                2
            );

            $periodeTagihan = null;
            $tenggatManual = null;

            // Pembayaran bulanan berdasarkan periode tagihan.
            if ($skema === 'bulanan') {
                if (!$penghunian->tenggat_pelunasan) {
                    return $this->errorResponse(
                        422,
                        'Tenggat pembayaran belum tersedia. Lengkapi tanggal masuk dan hitung skema sewa terlebih dahulu.',
                        'DUE_DATE_NOT_SET'
                    );
                }

                $periodeTagihan = Carbon::parse(
                    $penghunian->tenggat_pelunasan
                )->startOfMonth()->toDateString();
            }

            if (
                !in_array($skema, ['bulanan', 'tahunan'], true) ||
                $totalSewa <= 0
            ) {
                return $this->errorResponse(
                    422,
                    'Pilih dan hitung skema sewa terlebih dahulu sebelum mencatat pembayaran.',
                    'RENT_SCHEME_NOT_SET'
                );
            }

            // 6. Validasi jenis pembayaran berdasarkan skema.
            if ($skema === 'bulanan' && $jenis === 'cicilan') {
                return $this->errorResponse(
                    422,
                    'Cicilan hanya diperbolehkan untuk skema sewa tahunan.',
                    'INSTALLMENT_NOT_ALLOWED'
                );
            }

            // 7. Validasi pembayaran DP.
            if ($jenis === 'dp') {

                // DP hanya satu kali untuk masa sewa kamar.
                $idPenghunianAktif = $penghunianAktif
                    ->pluck('id_penghunian')
                    ->all();

                $sudahAdaDp = Pembayaran::whereIn(
                    'id_penghunian',
                    $idPenghunianAktif
                )
                    ->where('jenis_pembayaran', 'dp')
                    ->where('status', '!=', 'ditolak')
                    ->exists();

                if ($sudahAdaDp) {
                    return $this->errorResponse(
                        422,
                        'DP untuk masa sewa ini sudah pernah dicatat dan belum ditolak.',
                        'DP_ALREADY_EXISTS'
                    );
                }

                $dpMinimum = round(
                    (float) ($penghunian->dp_minimum ?? 0),
                    2
                );

                if ($dpMinimum <= 0) {
                    return $this->errorResponse(
                        422,
                        'DP minimum belum tersedia. Hitung skema sewa terlebih dahulu.',
                        'DP_MINIMUM_NOT_SET'
                    );
                }

                if ($jumlah > $totalSewa) {
                    return $this->errorResponse(
                        422,
                        'Jumlah DP tidak boleh melebihi total sewa.',
                        'DP_EXCEEDS_RENT',
                        [
                            'sisa_tagihan' => $totalSewa,
                        ]
                    );
                }

                if ($jumlah < $dpMinimum) {
                    return $this->errorResponse(
                        422,
                        'Pembayaran DP ditolak karena jumlahnya kurang dari DP minimum Rp' .
                            number_format($dpMinimum, 0, ',', '.') . '.',
                        'DP_BELOW_MINIMUM',
                        [
                            'dp_minimum' => $dpMinimum,
                            'jumlah_dp' => $jumlah,
                        ]
                    );
                }

                // Perubahan tenggat melalui DP hanya untuk tahunan.
                if (
                    !empty($validated['tenggat_pelunasan']) &&
                    $skema !== 'tahunan'
                ) {
                    return $this->errorResponse(
                        422,
                        'Pengubahan tenggat melalui form DP hanya berlaku untuk sewa tahunan.',
                        'INVALID_DUE_DATE_SCHEME'
                    );
                }

                if (!empty($validated['tenggat_pelunasan'])) {
                    if (
                        $penghunian->tanggal_mulai &&
                        Carbon::parse($validated['tenggat_pelunasan'])->lt(
                            Carbon::parse($penghunian->tanggal_mulai)
                        )
                    ) {
                        return $this->errorResponse(
                            422,
                            'Tenggat pelunasan tidak boleh sebelum tanggal mulai sewa.',
                            'INVALID_DUE_DATE'
                        );
                    }

                    $tenggatManual = $validated['tenggat_pelunasan'];
                }
            }

            // 8. Menghitung total pembayaran terverifikasi sebelumnya.
            $queryTerbayarSebelum = Pembayaran::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )
                ->where('status', 'terverifikasi');

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

            // 9. Validasi cicilan dan pelunasan.
            if ($jenis !== 'dp') {

                if ($sisaSebelum <= 0) {
                    return $this->errorResponse(
                        422,
                        'Tagihan sudah lunas. Tidak dapat menambahkan pembayaran lagi.',
                        'BILL_ALREADY_PAID'
                    );
                }

                if ($jumlah > $sisaSebelum) {
                    return $this->errorResponse(
                        422,
                        'Jumlah pembayaran melebihi sisa tagihan.',
                        'PAYMENT_EXCEEDS_REMAINING',
                        [
                            'sisa_tagihan' => $sisaSebelum,
                        ]
                    );
                }

                if (
                    $jenis === 'cicilan' &&
                    $jumlah >= $sisaSebelum
                ) {
                    return $this->errorResponse(
                        422,
                        'Jika membayar seluruh sisa tagihan, pilih jenis pembayaran pelunasan.',
                        'USE_SETTLEMENT_FOR_FULL_PAYMENT',
                        [
                            'sisa_tagihan' => $sisaSebelum,
                        ]
                    );
                }

                if (
                    $jenis === 'pelunasan' &&
                    round($jumlah, 2) !== round($sisaSebelum, 2)
                ) {
                    return $this->errorResponse(
                        422,
                        'Jumlah pelunasan harus sama dengan sisa tagihan.',
                        'INVALID_SETTLEMENT_AMOUNT',
                        [
                            'sisa_tagihan' => $sisaSebelum,
                        ]
                    );
                }
            }

            // Menerbitkan tagihan terlebih dahulu.
            // Penerbitan bersifat idempoten sehingga tidak membuat duplikat.
            app(TagihanService::class)->terbitkanTagihan($penghunian);

            // 10. Menyimpan pembayaran.
            $pembayaran = Pembayaran::create([
                'id_penghunian' => $penghunian->id_penghunian,
                'jenis_pembayaran' => $jenis,
                'tanggal_bayar' => $validated['tanggal_bayar'] ?? null,
                'periode_tagihan' => $periodeTagihan,
                'jumlah' => $jumlah,
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'bukti_pembayaran' => $validated['bukti_pembayaran'] ?? null,
                'status' => 'terverifikasi',
            ]);

            // Mengalokasikan pembayaran ke tagihan tertua yang belum lunas.
            app(TagihanService::class)->alokasikanPembayaran($pembayaran);

            // Simpan tenggat manual jika dikirim pada pembayaran DP tahunan.
            if ($tenggatManual !== null) {
                $penghunian->update([
                    'tenggat_pelunasan' => $tenggatManual,
                ]);
            }

            // 11. Menghitung ulang total pembayaran.
            $queryTotalTerbayar = Pembayaran::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )
                ->where('status', 'terverifikasi');

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

            $sisaTagihan = max(
                0,
                round($totalSewa - $totalTerbayar, 2)
            );

            $statusPembayaran = $sisaTagihan <= 0
                ? 'lunas'
                : 'belum_lunas';

            $tenggatBaruOtomatis = null;

            // 12. Memajukan tenggat jika pembayaran bulanan lunas.
            if (
                $skema === 'bulanan' &&
                $statusPembayaran === 'lunas'
            ) {
                $tenggatBaruOtomatis = app(TenggatSewaService::class)
                    ->majukanJikaLunas(
                        $penghunian,
                        $periodeTagihan,
                        $totalTerbayar
                    );
            }

            // 13. Memperbarui status pembayaran penghunian.
            $penghunian->update([
                'status_pembayaran' => $statusPembayaran,
        ]);
            return response()->json([
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
            return $this->errorResponse(
                404,
                'Data pembayaran tidak ditemukan.',
                'NOT_FOUND'
            );
        }

        if ($pembayaran->status === 'terverifikasi') {
            return $this->errorResponse(
                422,
                'Pembayaran yang sudah terverifikasi tidak dapat diubah.',
                'PAYMENT_ALREADY_VERIFIED'
            );
        }

        $validated = $this->validateRequest(
            $request,
            [
                'tanggal_bayar' => [
                    'sometimes',
                    'nullable',
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
            ],
            [
                'jumlah.gt' =>
                    'Jumlah pembayaran harus lebih besar dari nol.',
            ]
        );

        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        if (empty($validated)) {
            return $this->errorResponse(
                422,
                'Tidak ada data yang dikirim untuk diperbarui.',
                'VALIDATION_ERROR',
                [
                    'request' => [
                        'Minimal satu field harus dikirim.',
                    ],
                ]
            );
        }

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
        $validated = $this->validateRequest(
            $request,
            [
                'status' => [
                    'required',
                    'string',
                    Rule::in([
                        'menunggu_verifikasi',
                        'terverifikasi',
                        'ditolak',
                    ]),
                ],
            ],
            [
                'status.required' =>
                    'Status pembayaran wajib diisi.',
                'status.in' =>
                    'Status hanya boleh menunggu_verifikasi, terverifikasi, atau ditolak.',
            ]
        );

        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        return DB::transaction(function () use ($validated, $id) {

            // Mengambil data awal pembayaran.
            $pembayaranAwal = Pembayaran::find($id);

            if (!$pembayaranAwal) {
                return $this->errorResponse(
                    404,
                    'Data pembayaran tidak ditemukan.',
                    'PAYMENT_NOT_FOUND'
                );
            }

            $penghunianAwal = Penghunian::find(
                $pembayaranAwal->id_penghunian
            );

            if (!$penghunianAwal) {
                return $this->errorResponse(
                    404,
                    'Data penghunian tidak ditemukan.',
                    'OCCUPANCY_NOT_FOUND'
                );
            }

            // Mengunci kamar terlebih dahulu.
            $kamar = Kamar::where(
                'id_kamar',
                $penghunianAwal->id_kamar
            )
                ->lockForUpdate()
                ->first();

            if (!$kamar) {
                return $this->errorResponse(
                    404,
                    'Data kamar tidak ditemukan.',
                    'ROOM_NOT_FOUND'
                );
            }

            // Mengunci penghunian aktif pada kamar.
            $penghunianAktif = Penghunian::where(
                'id_kamar',
                $kamar->id_kamar
            )
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->get();

            // Mengunci penghunian dan pembayaran.
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
                return $this->errorResponse(
                    404,
                    'Data pembayaran atau penghunian tidak ditemukan.',
                    'DATA_NOT_FOUND'
                );
            }

            $statusBaru = $validated['status'];
            $statusSebelumnya = $pembayaran->status;

            $totalSewa = round(
                (float) ($penghunian->total_sewa ?? 0),
                2
            );

            $skema = $penghunian->skema_sewa;

            // Menentukan periode tagihan bulanan.
            $periodeTagihan = null;

            if ($skema === 'bulanan') {
                if ($pembayaran->periode_tagihan) {
                    $periodeTagihan = Carbon::parse(
                        $pembayaran->periode_tagihan
                    )->startOfMonth()->toDateString();
                } elseif ($penghunian->tenggat_pelunasan) {
                    $periodeTagihan = Carbon::parse(
                        $penghunian->tenggat_pelunasan
                    )->startOfMonth()->toDateString();
                } elseif ($pembayaran->tanggal_bayar) {
                    $periodeTagihan = Carbon::parse(
                        $pembayaran->tanggal_bayar
                    )->startOfMonth()->toDateString();
                } else {
                    return $this->errorResponse(
                        422,
                        'Periode tagihan pembayaran belum tersedia.',
                        'BILLING_PERIOD_NOT_SET'
                    );
                }
            }

            // Validasi ketika pembayaran akan diverifikasi.
            if ($statusBaru === 'terverifikasi') {

                if (
                    !in_array($skema, ['bulanan', 'tahunan'], true) ||
                    $totalSewa <= 0
                ) {
                    return $this->errorResponse(
                        422,
                        'Skema sewa dan total sewa belum dihitung.',
                        'RENT_SCHEME_NOT_SET'
                    );
                }

                if (
                    $skema === 'bulanan' &&
                    $pembayaran->jenis_pembayaran === 'cicilan'
                ) {
                    return $this->errorResponse(
                        422,
                        'Cicilan hanya tersedia untuk skema tahunan.',
                        'INSTALLMENT_NOT_ALLOWED'
                    );
                }

                // Validasi DP.
                if ($pembayaran->jenis_pembayaran === 'dp') {

                    $dpMinimum = round(
                        (float) ($penghunian->dp_minimum ?? 0),
                        2
                    );

                    $jumlahDp = round(
                        (float) $pembayaran->jumlah,
                        2
                    );

                    if ($dpMinimum <= 0) {
                        return $this->errorResponse(
                            422,
                            'DP minimum belum dihitung.',
                            'DP_MINIMUM_NOT_SET'
                        );
                    }

                    if ($jumlahDp < $dpMinimum) {
                        return $this->errorResponse(
                            422,
                            'DP tidak dapat diverifikasi karena jumlahnya kurang dari DP minimum Rp' .
                                number_format($dpMinimum, 0, ',', '.') . '.',
                            'DP_BELOW_MINIMUM',
                            [
                                'dp_minimum' => $dpMinimum,
                                'jumlah_dp' => $jumlahDp,
                            ]
                        );
                    }

                    // Mencegah DP ganda pada kamar yang sama.
                    $idPenghunianAktif = $penghunianAktif
                        ->pluck('id_penghunian')
                        ->all();

                    $dpLain = Pembayaran::whereIn(
                        'id_penghunian',
                        $idPenghunianAktif
                    )
                        ->where('jenis_pembayaran', 'dp')
                        ->where('status', '!=', 'ditolak')
                        ->where(
                            'id_pembayaran',
                            '!=',
                            $pembayaran->id_pembayaran
                        )
                        ->exists();

                    if ($dpLain) {
                        return $this->errorResponse(
                            422,
                            'DP lain sudah tercatat untuk masa sewa kamar ini.',
                            'DP_ALREADY_EXISTS'
                        );
                    }
                }

                // Menghitung pembayaran terverifikasi lainnya.
                $queryPembayaranLain = Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->where(
                        'id_pembayaran',
                        '!=',
                        $pembayaran->id_pembayaran
                    );

                if ($skema === 'bulanan') {
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

                $jumlah = round(
                    (float) $pembayaran->jumlah,
                    2
                );

                if ($jumlah > $sisaSebelum) {
                    return $this->errorResponse(
                        422,
                        'Verifikasi pembayaran menyebabkan jumlah pembayaran melebihi sisa tagihan.',
                        'PAYMENT_EXCEEDS_REMAINING',
                        [
                            'sisa_tagihan' => $sisaSebelum,
                        ]
                    );
                }

                if (
                    $pembayaran->jenis_pembayaran === 'pelunasan' &&
                    round($jumlah, 2) !== round($sisaSebelum, 2)
                ) {
                    return $this->errorResponse(
                        422,
                        'Jumlah pelunasan harus sama dengan sisa tagihan.',
                        'INVALID_FINAL_PAYMENT_AMOUNT',
                        [
                            'sisa_tagihan' => $sisaSebelum,
                        ]
                    );
                }

                if (
                    $pembayaran->jenis_pembayaran === 'cicilan' &&
                    $jumlah >= $sisaSebelum
                ) {
                    return $this->errorResponse(
                        422,
                        'Jika membayar seluruh sisa tagihan, pilih jenis pembayaran pelunasan.',
                        'USE_SETTLEMENT_FOR_FULL_PAYMENT',
                        [
                            'sisa_tagihan' => $sisaSebelum,
                        ]
                    );
                }
            }

            // Memperbarui status pembayaran.
            $pembayaran->update([
                'status' => $statusBaru,
            ]);

            // Alokasikan pembayaran setelah status menjadi terverifikasi.
            if (
                $statusBaru === 'terverifikasi' &&
                $statusSebelumnya !== 'terverifikasi'
            ) {
                app(TagihanService::class)->alokasikanPembayaran($pembayaran);
        }

            // Menghitung ulang total pembayaran terverifikasi.
            $queryTotalTerbayar = Pembayaran::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )
                ->where('status', 'terverifikasi');

            if ($skema === 'bulanan' && $periodeTagihan) {
                $queryTotalTerbayar->whereDate(
                    'periode_tagihan',
                    $periodeTagihan
                );
            }

            $totalTerbayar = round(
                (float) $queryTotalTerbayar->sum('jumlah'),
                2
            );

            $sisaTagihan = max(
                0,
                round($totalSewa - $totalTerbayar, 2)
            );

            $statusPembayaran = (
                $totalSewa > 0 &&
                $sisaTagihan <= 0
            ) ? 'lunas' : 'belum_lunas';

            $tenggatBaruOtomatis = null;

            // Tenggat hanya maju ketika status berubah menjadi terverifikasi.
            if (
                $skema === 'bulanan' &&
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

            // Memperbarui status pembayaran penghunian.
            $penghunian->update([
                'status_pembayaran' => $statusPembayaran,
        ]);
            return response()->json([
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
                    'periode_tagihan' => $skema === 'bulanan'
                        ? $periodeTagihan
                        : null,
                    'tenggat_pelunasan_baru' => $tenggatBaruOtomatis,
                ],
            ]);
        });
    }

    /**
     * Daftar pembayaran per kamar.
     *
     * Satu kamar menghasilkan satu baris dan nama yang ditampilkan
     * adalah nama penyewa utama.
     */
    public function perKamar(Request $request)
{
    $validated = $request->validate([
        'page' => ['sometimes', 'integer', 'min:1'],
        'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

        'status_pembayaran' => [
            'sometimes',
            Rule::in([
                'belum_bayar',
                'belum_lunas',
                'sudah_lunas',
            ]),
        ],

        'jenis_tagihan' => [
            'sometimes',
            'required',
            Rule::in(['bulanan', 'tahunan']),
        ],

        'lantai' => [
            'sometimes',
            'required',
            Rule::in(['1', '2']),
        ],
    ]);

    $tagihanService = app(TagihanService::class);

    $penghunianAktif = Penghunian::with([
        'kamar',
        'penghuni',
    ])
        ->where('status', 'aktif')
        ->get();

    
// Pastikan setiap kamar memiliki penyewa utama dan skema sewa.
$kelompokPenghunian = $penghunianAktif->groupBy('id_kamar');

foreach ($kelompokPenghunian as $daftar) {
    $daftarUtama = $daftar->filter(
        fn ($item) => (bool) $item->penyewa_utama
    );

    if ($daftarUtama->count() > 1) {
        return $this->errorResponse(
            422,
            'Terdapat lebih dari satu penyewa utama pada kamar.',
            'MULTIPLE_PRIMARY_TENANTS',
            [
                'id_kamar' => $daftar->first()->id_kamar,
            ]
        );
    }

    // Jika hanya ada satu penghuni, gunakan penghuni tersebut.
    $utama = $daftarUtama->first();

    if (!$utama && $daftar->count() === 1) {
        $utama = $daftar->first();
    }

    // Kamar dengan beberapa penghuni harus memiliki penyewa utama.
    if (!$utama) {
        return $this->errorResponse(
            422,
            'Penyewa utama belum ditentukan pada salah satu kamar.',
            'PRIMARY_TENANT_NOT_FOUND',
            [
                'id_kamar' => $daftar->first()->id_kamar,
            ]
        );
    }

    if (!in_array($utama->skema_sewa, ['bulanan', 'tahunan'], true)) {
        return $this->errorResponse(
            422,
            'Skema sewa penyewa utama belum ditentukan.',
            'RENT_SCHEME_NOT_SET',
            [
                'id_kamar' => $daftar->first()->id_kamar,
                'id_penghunian' => $utama->id_penghunian,
            ]
        );
    }

    if (
        $utama->skema_sewa === 'bulanan' &&
        !$utama->tenggat_pelunasan
    ) {
        return $this->errorResponse(
            422,
            'Tenggat pembayaran bulanan belum tersedia.',
            'DUE_DATE_NOT_SET',
            [
                'id_kamar' => $daftar->first()->id_kamar,
            ]
        );
    }
}


    $rows = $penghunianAktif
        ->groupBy('id_kamar')
        ->map(function ($daftar) use ($tagihanService) {

            // Mengambil penyewa utama.
            $utama = $daftar->firstWhere(
                'penyewa_utama',
                true
            );

            if (!$utama && $daftar->count() === 1) {
                $utama = $daftar->first();
            }

            $kamar = $daftar->first()->kamar;

            if (!$utama || !$kamar) {
                return null;
            }

            // Membuat tagihan jika belum tersedia.
            $tagihanService->terbitkanTagihan($utama);

            // Mengambil tagihan yang belum lunas,
            // dimulai dari jatuh tempo paling lama.
            $tagihan = Tagihan::where(
                'id_penghunian',
                $utama->id_penghunian
            )
                ->where('sisa_tagihan', '>', 0)
                ->orderBy('jatuh_tempo')
                ->orderBy('id_tagihan')
                ->first();

            // Jika seluruh tagihan lunas, ambil tagihan terakhir.
            if (!$tagihan) {
                $tagihan = Tagihan::where(
                    'id_penghunian',
                    $utama->id_penghunian
                )
                    ->orderByDesc('jatuh_tempo')
                    ->orderByDesc('id_tagihan')
                    ->first();
            }

            $jumlahTagihan = (float) (
                $tagihan->jumlah_tagihan
                ?? $utama->total_sewa
                ?? 0
            );

            $jumlahTerbayar = (float) (
                $tagihan->jumlah_terbayar ?? 0
            );

            $sisaTagihan = (float) (
                $tagihan->sisa_tagihan
                ?? $jumlahTagihan
            );

            $status = $tagihan->status ?? 'belum_bayar';

            // Mengikuti status yang ditentukan Card 5.2b.
            if ($sisaTagihan <= 0 && $tagihan) {
                $status = 'sudah_lunas';
            } elseif ($jumlahTerbayar > 0) {
                $status = 'belum_lunas';
            } else {
                $status = 'belum_bayar';
            }

            // Tenggat berikutnya jika tagihan bulanan sudah lunas.
            $jatuhTempo = $tagihan->jatuh_tempo ?? null;

            if (
                $status === 'sudah_lunas' &&
                $utama->skema_sewa === 'bulanan'
            ) {
                $jatuhTempo = $utama->tenggat_pelunasan;
            }

            if (
                $status === 'sudah_lunas' &&
                $utama->skema_sewa === 'tahunan'
            ) {
                $jatuhTempo = null;
            }

            // Memeriksa apakah DP sudah pernah tercatat.
            $sudahAdaDp = Pembayaran::where(
                'id_penghunian',
                $utama->id_penghunian
            )
                ->where('jenis_pembayaran', 'dp')
                ->where('status', '!=', 'ditolak')
                ->exists();

            $aksi = $status === 'sudah_lunas'
                ? 'tidak_ada'
                : (
                    $sudahAdaDp
                        ? 'bayar'
                        : 'catat_dp'
                );

            return [
                'id_kamar' => $kamar->id_kamar,
                'no_kamar' => $kamar->no_kamar,
                'penyewa_utama' => [
                    'id_penghuni' => $utama->id_penghuni,
                    'nama_penghuni' =>
                        $utama->penghuni->nama_penghuni ?? '-',
                ],
                'id_penghunian' => $utama->id_penghunian,
                'jumlah_penghuni_aktif' => $daftar->count(),
                'jenis_tagihan' => $utama->skema_sewa,
                'periode' => (
                    $tagihan &&
                    $tagihan->jenis_tagihan === 'bulanan' &&
                    $tagihan->periode
                )
                    ? Carbon::parse($tagihan->periode)
                        ->translatedFormat('F Y')
                    : '-',
                'jatuh_tempo' => $jatuhTempo
                    ? Carbon::parse($jatuhTempo)->toDateString()
                    : null,
                'jumlah_tagihan' => $jumlahTagihan,
                'jumlah_terbayar' => $jumlahTerbayar,
                'sisa_tagihan' => $sisaTagihan,
                'status_pembayaran' => $status,
                'status_tagihan' => $status,
                'id_tagihan' => $tagihan->id_tagihan ?? null,
                'aksi_pembayaran' => $aksi,

                'riwayat_url' =>
                    '/api/pembayaran/kamar/' .
                    $kamar->id_kamar .
                    '/riwayat',
            ];
        })
        ->filter()
        ->values();

    // Filter skema.
    if (!empty($validated['jenis_tagihan'])) {
        $rows = $rows->where(
            'jenis_tagihan',
            $validated['jenis_tagihan']
        )->values();
    }

    // Filter status.
    if (!empty($validated['status_pembayaran'])) {
        $rows = $rows->where(
            'status_pembayaran',
            $validated['status_pembayaran']
        )->values();
    }

    // Filter lantai.
    if (!empty($validated['lantai'])) {
        $rows = $rows->filter(function ($row) use ($validated) {
            return (string) Kamar::find(
                $row['id_kamar']
            )?->lantai === (string) $validated['lantai'];
        })->values();
    }

    $page = (int) ($validated['page'] ?? 1);
    $perPage = (int) ($validated['per_page'] ?? 10);

    $total = $rows->count();

    $data = $rows
        ->slice(($page - 1) * $perPage, $perPage)
        ->values();

    return response()->json([
        'message' => $data->isEmpty()
            ? 'Belum ada kamar berpenghuni.'
            : 'Daftar tagihan per kamar berhasil diambil.',

        'data' => $data,

        'meta' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(
                1,
                (int) ceil($total / $perPage)
            ),
        ],
    ]);
}

private function riwayatPembayaranQuery(
    Request $request,
    ?int $idKamar = null
) {
    $validated = $request->validate([
        'periode' => [
            'sometimes',
            'required',
            Rule::in([
                'bulan_ini',
                'bulan_lalu',
                '6_bulan_terakhir',
                'tahun_ini',
                'tahun_lalu',
                '2_tahun_terakhir',
            ]),
        ],

        'jenis_tagihan' => [
            'sometimes',
            'required',
            Rule::in(['bulanan', 'tahunan']),
        ],

        'lantai' => [
            'sometimes',
            'required',
            Rule::in(['1', '2']),
        ],
    ]);

    $query = Pembayaran::with([
        'penghunian.penghuni',
        'penghunian.kamar',
        'alokasiTagihan.tagihan',
    ]);

    if ($idKamar !== null) {
        $query->whereHas('penghunian', function ($q) use ($idKamar) {
            $q->where('id_kamar', $idKamar);
        });
    }

    if (!empty($validated['jenis_tagihan'])) {
        $query->whereHas('penghunian', function ($q) use ($validated) {
            $q->where(
                'skema_sewa',
                $validated['jenis_tagihan']
            );
        });
    }

    if (!empty($validated['lantai'])) {
        $query->whereHas('penghunian.kamar', function ($q) use ($validated) {
            $q->where('lantai', $validated['lantai']);
        });
    }

    // Filter periode berdasarkan tanggal transaksi.
    $today = Carbon::today();

    if (!empty($validated['periode'])) {
        $awal = null;
        $akhir = null;

        switch ($validated['periode']) {
            case 'bulan_ini':
                $awal = $today->copy()->startOfMonth();
                $akhir = $today->copy()->endOfMonth();
                break;

            case 'bulan_lalu':
                $bulanLalu = $today->copy()->subMonthNoOverflow();
                $awal = $bulanLalu->startOfMonth();
                $akhir = $bulanLalu->endOfMonth();
                break;

            case '6_bulan_terakhir':
                $awal = $today->copy()->subMonthsNoOverflow(5)->startOfMonth();
                $akhir = $today->copy()->endOfDay();
                break;

            case 'tahun_ini':
                $awal = $today->copy()->startOfYear();
                $akhir = $today->copy()->endOfYear();
                break;
                
            case 'tahun_lalu':
                $tahunLalu = $today->copy()->subYear();
                $awal = $tahunLalu->copy()->startOfYear();
                $akhir = $tahunLalu->copy()->endOfYear();
                break;

            case '2_tahun_terakhir':
                $awal = $today->copy()->subYears()->startOfYear();
                $akhir = $today->copy()->endOfYear();
                break;
        }

     if ($awal && $akhir) {
        $query->whereHas('alokasiTagihan.tagihan', function ($q) use (
            $awal,
            $akhir
        ) {
            $q->whereBetween('periode', [
                $awal->toDateString(),
                $akhir->toDateString(),
            ]);
        });
    }
}

    return $query;
}

private function formatRiwayatPembayaran(Collection$pembayaran)
{
    return $pembayaran->map(function ($item) {

        $alokasi = $item->alokasiTagihan->map(function ($data) {

            $tagihan = $data->tagihan;

            return [
                'id_tagihan' => $tagihan->id_tagihan ?? null,

                'periode' => (
                    $tagihan &&
                    $tagihan->periode
                )
                    ? Carbon::parse($tagihan->periode)
                        ->translatedFormat('F Y')
                    : '-',

                'jumlah_dialokasikan' =>
                    (float) $data->jumlah_dialokasikan,
            ];
        })->values();

        // Periode transaksi berasal dari alokasi tagihan.
        $periode = $alokasi
            ->pluck('periode')
            ->filter(fn ($value) => $value !== '-')
            ->unique()
            ->implode(', ');

        // Data lama tetap bisa dibaca jika belum memiliki alokasi.
        if ($periode === '' && $item->periode_tagihan) {
            $periode = Carbon::parse(
                $item->periode_tagihan
            )->translatedFormat('F Y');
        }

        return [
            'id_pembayaran' => $item->id_pembayaran,

            'id_penghunian' => $item->id_penghunian,

            'nama_penghuni' =>
                $item->penghunian->penghuni->nama_penghuni ?? '-',

            'no_kamar' =>
                $item->penghunian->kamar->no_kamar ?? '-',

            // Field tambahan yang diminta.
            'jenis_tagihan' =>
                $item->penghunian->skema_sewa ?? '-',

            'jenis_pembayaran' => $item->jenis_pembayaran,

            'tanggal_bayar' => $item->tanggal_bayar
                ? Carbon::parse($item->tanggal_bayar)->toDateString()
                : null,

            'periode' => $periode !== '' ? $periode : '-',

            'jumlah_dibayar' => (float) $item->jumlah,

            'metode_pembayaran' => $item->metode_pembayaran,

            'status' => $item->status,

            'alokasi_tagihan' => $alokasi,
        ];
    })->values();
}

    /**
     * Riwayat pembayaran satu kamar.
     * Termasuk penghunian lama dan seluruh penghuni kamar.
     */
    
public function riwayatPerKamar(int $id_kamar)
{
    // Validasi ID kamar.
    if ($id_kamar < 1) {
        return $this->errorResponse(
            422,
            'ID kamar minimal 1.',
            'VALIDATION_ERROR',
            [
                'id_kamar' => [
                    'ID kamar harus berupa bilangan bulat minimal 1.',
                ],
            ]
        );
    }

    $kamar = Kamar::find($id_kamar);

    if (!$kamar) {
        return $this->errorResponse(
            404,
            'Kamar tidak ditemukan.',
            'NOT_FOUND'
        );
    }

    // Ambil seluruh masa penghunian kamar.
    $penghunianKamar = Penghunian::with('penghuni')
        ->where('id_kamar', $id_kamar)
        ->get();

    $idPenghunian = $penghunianKamar
        ->pluck('id_penghunian')
        ->all();

    // Tentukan penyewa utama yang masih aktif.
    $utama = $penghunianKamar
        ->where('status', 'aktif')
        ->firstWhere('penyewa_utama', true);

    // Jika tidak ada penanda utama, gunakan penghuni aktif
    // apabila hanya ada satu orang.
    if (!$utama) {
        $penghuniAktif = $penghunianKamar
            ->where('status', 'aktif');

        if ($penghuniAktif->count() === 1) {
            $utama = $penghuniAktif->first();
        }
    }

    // Ambil seluruh transaksi dari seluruh masa penghunian kamar.
    $pembayaran = Pembayaran::with([
        'penghunian.penghuni',
    ])
        ->whereIn('id_penghunian', $idPenghunian)
        ->orderByDesc('tanggal_bayar')
        ->orderByDesc('id_pembayaran')
        ->get();

    // Ambil semua alokasi sekaligus untuk menghindari N+1 query.
    $idPembayaran = $pembayaran
        ->pluck('id_pembayaran')
        ->all();

    $alokasiPerPembayaran = AlokasiPembayaran::with('tagihan')
        ->whereIn('id_pembayaran', $idPembayaran)
        ->get()
        ->groupBy('id_pembayaran');

    $dataPembayaran = $pembayaran->map(function ($item) use (
        $alokasiPerPembayaran,
        $kamar
    ) {
        $alokasi = $alokasiPerPembayaran->get(
            $item->id_pembayaran,
            collect()
        );

        $daftarAlokasi = $alokasi->map(function ($itemAlokasi) {
            $tagihan = $itemAlokasi->tagihan;

            return [
                'id_tagihan' => $tagihan?->id_tagihan,

                'periode' => $tagihan?->periode
                    ? Carbon::parse($tagihan->periode)->toDateString()
                    : 'Tahunan',

                'jumlah_dialokasikan' =>
                    (float) $itemAlokasi->jumlah_dialokasikan,
            ];
        })->values();

        // Periode transaksi hanya diisi jika tepat satu
        // alokasi tagihan memiliki periode yang jelas.
        $periodeAlokasi = $alokasi
            ->map(function ($itemAlokasi) {
                return $itemAlokasi->tagihan?->periode
                    ? Carbon::parse(
                        $itemAlokasi->tagihan->periode
                    )->toDateString()
                    : null;
            })
            ->filter()
            ->unique()
            ->values();

        $periode = $alokasi->count() === 1 &&
            $periodeAlokasi->count() === 1
                ? $periodeAlokasi->first()
                : null;

        $penghunian = $item->penghunian;

        return [
            'id_pembayaran' => $item->id_pembayaran,
            'id_penghunian' => $item->id_penghunian,

            'nama_penghuni' =>
                $penghunian?->penghuni?->nama_penghuni ?? '-',

            'no_kamar' => $kamar->no_kamar,

            'periode' => $periode,

            'jumlah_dibayar' => (float) $item->jumlah,

            'tanggal_bayar' => $item->tanggal_bayar
                ? Carbon::parse($item->tanggal_bayar)->toDateString()
                : $item->created_at?->toDateString(),

            'metode_pembayaran' =>
                $item->metode_pembayaran ?? '-',

            'alokasi_tagihan' => $daftarAlokasi,
        ];
    })->values();

    return response()->json([
        'message' => $dataPembayaran->isEmpty()
            ? 'Belum ada pembayaran.'
            : 'Riwayat pembayaran kamar berhasil diambil.',

        'data' => [
            'id_kamar' => $kamar->id_kamar,
            'no_kamar' => $kamar->no_kamar,

            'penyewa_utama' =>
                $utama?->penghuni?->nama_penghuni,

            'pembayaran' => $dataPembayaran,
        ],
    ]);
}

public function riwayat(Request $request)
{
    $query = $this->riwayatPembayaranQuery($request);

    $pembayaran = $query
        ->orderByDesc('tanggal_bayar')
        ->orderByDesc('id_pembayaran')
        ->get();

    $data = $this->formatRiwayatPembayaran($pembayaran);

    return response()->json([
        'message' => $data->isEmpty()
            ? 'Belum ada pembayaran.'
            : 'Riwayat pembayaran berhasil diambil.',

        'data' => $data,
    ]);
}
}