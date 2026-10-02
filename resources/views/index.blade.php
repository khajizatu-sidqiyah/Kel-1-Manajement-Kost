<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Denah Seluruh Kamar - KelolaKos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base Reset */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background-color: #f8fafc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #0f172a;
            padding: 24px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Section Lantai Header */
        .floor-section {
            margin-bottom: 32px;
        }
        .floor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .floor-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
        }
        .floor-title span {
            font-weight: 400;
            color: #64748b;
        }
        .floor-stats {
            background-color: #f1f5f9;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        /* Grid Kamar (4 Kolom di Desktop) */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        @media (max-width: 1024px) {
            .room-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .room-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .room-grid { grid-template-columns: repeat(1, 1fr); }
        }

        /* Room Card Base */
        .room-card {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
        }

        /* Top Status Bar */
        .card-status-bar {
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-transform: uppercase;
        }
        .card-status-bar.kosong {
            background-color: #059669; /* Hijau Kosong */
        }
        .card-status-bar.terisi {
            background-color: #e11d48; /* Merah Terisi */
        }
        .card-status-bar .dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            background-color: #fff;
            border-radius: 50%;
            margin-right: 6px;
        }

        /* Body Card */
        .card-body {
            padding: 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .room-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2px;
        }
        .room-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .badge-floor {
            background-color: #f1f5f9;
            color: #64748b;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
        }
        .room-subtitle {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 12px;
        }

        /* Dynamic Content Box */
        .info-box {
            border-radius: 8px;
            padding: 10px 12px;
            margin-top: auto;
            margin-bottom: 12px;
            font-size: 12px;
        }
        .info-box.kosong {
            background-color: #f0fdf4;
            border: 1px solid #dcfce7;
            color: #166534;
        }
        .info-box.terisi {
            background-color: #fff1f2;
            border: 1px solid #ffe4e6;
            color: #9f1239;
        }

        .info-box-title {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 2px;
        }
        .info-box-price {
            font-size: 14px;
            font-weight: 700;
            color: #166534;
        }
        .info-box-status {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            margin-top: 4px;
            color: #15803d;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-label {
            color: #64748b;
        }
        .info-val {
            font-weight: 700;
            color: #0f172a;
        }
        .info-val.highlight-date {
            color: #e11d48;
        }

        /* Buttons */
        .btn-action {
            width: 100%;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .btn-action.kosong {
            background-color: #064e3b;
            color: #ffffff;
        }
        .btn-action.kosong:hover {
            background-color: #022c22;
        }
        .btn-action.terisi {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-action.terisi:hover {
            background-color: #e2e8f0;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- ==================== LANTAI 1 ==================== -->
    <div class="floor-section">
        <div class="floor-header">
            <div class="floor-title">Lantai 1 <span>— 10 Kamar (Akses Parkir & Area Depan)</span></div>
            <div class="floor-stats">5 Kosong • 5 Terisi</div>
        </div>

        <div class="room-grid">
            <!-- Kamar 01 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 01</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 01</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Standard AC • KM Luar</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.600.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 02 -->
            <div class="room-card">
                <div class="card-status-bar terisi">
                    <span><i class="dot"></i>TERISI</span>
                    <span>Kamar 02</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 02</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Deluxe AC • KM Dalam</div>
                    <div class="info-box terisi">
                        <div class="info-row"><span class="info-label">Penghuni</span><span class="info-val">Dimas Aditya</span></div>
                        <div class="info-row"><span class="info-label">Jatuh Tempo</span><span class="info-val highlight-date">05 Nov 2024</span></div>
                    </div>
                    <button class="btn-action terisi">Detail Sewa</button>
                </div>
            </div>

            <!-- Kamar 03 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 03</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 03</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Standard AC • Kasur King</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.600.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 04 -->
            <div class="room-card">
                <div class="card-status-bar terisi">
                    <span><i class="dot"></i>TERISI</span>
                    <span>Kamar 04</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 04</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Deluxe AC • KM Dalam</div>
                    <div class="info-box terisi">
                        <div class="info-row"><span class="info-label">Penghuni</span><span class="info-val">Siti Rahma</span></div>
                        <div class="info-row"><span class="info-label">Jatuh Tempo</span><span class="info-val highlight-date">18 Nov 2024 (Lunas)</span></div>
                    </div>
                    <button class="btn-action terisi">Detail Sewa</button>
                </div>
            </div>

            <!-- Kamar 05 -->
            <div class="room-card">
                <div class="card-status-bar terisi">
                    <span><i class="dot"></i>TERISI</span>
                    <span>Kamar 05</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 05</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Standard AC • KM Luar</div>
                    <div class="info-box terisi">
                        <div class="info-row"><span class="info-label">Penghuni</span><span class="info-val">Andi Wijaya</span></div>
                        <div class="info-row"><span class="info-label">Jatuh Tempo</span><span class="info-val highlight-date">20 Nov 2024</span></div>
                    </div>
                    <button class="btn-action terisi">Detail Sewa</button>
                </div>
            </div>

            <!-- Kamar 06 -->
            <div class="room-card">
                <div class="card-status-bar terisi">
                    <span><i class="dot"></i>TERISI</span>
                    <span>Kamar 06</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 06</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Deluxe AC • KM Dalam</div>
                    <div class="info-box terisi">
                        <div class="info-row"><span class="info-label">Penghuni</span><span class="info-val">Rian Fathur</span></div>
                        <div class="info-row"><span class="info-label">Jatuh Tempo</span><span class="info-val highlight-date">12 Des 2024</span></div>
                    </div>
                    <button class="btn-action terisi">Detail Sewa</button>
                </div>
            </div>

            <!-- Kamar 07 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 07</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 07</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Standard AC • Kasur King</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.600.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 08 -->
            <div class="room-card">
                <div class="card-status-bar terisi">
                    <span><i class="dot"></i>TERISI</span>
                    <span>Kamar 08</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 08</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Standard AC • Kasur Single</div>
                    <div class="info-box terisi">
                        <div class="info-row"><span class="info-label">Penghuni</span><span class="info-val">Bayu Nugroho</span></div>
                        <div class="info-row"><span class="info-label">Jatuh Tempo</span><span class="info-val highlight-date">22 Des 2024</span></div>
                    </div>
                    <button class="btn-action terisi">Detail Sewa</button>
                </div>
            </div>

            <!-- Kamar 09 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 09</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 09</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Standard AC • Kasur Queen</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.650.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 21 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 21</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 21</h2><span class="badge-floor">Lt. 1</span></div>
                    <div class="room-subtitle">Deluxe Plus • Balkon Depan</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.700.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== LANTAI 2 ==================== -->
    <div class="floor-section">
        <div class="floor-header">
            <div class="floor-title">Lantai 2 <span>— 13 Kamar (Akses Balkon & Rooftop)</span></div>
            <div class="floor-stats">9 Kosong • 4 Terisi</div>
        </div>

        <div class="room-grid">
            <!-- Kamar 10 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 10</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 10</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Standard AC • Kasur King</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.650.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 11 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 11</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 11</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Standard AC • Kasur Single</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.650.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 12 -->
            <div class="room-card">
                <div class="card-status-bar terisi">
                    <span><i class="dot"></i>TERISI</span>
                    <span>Kamar 12</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 12</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Deluxe Plus • KM Dalam</div>
                    <div class="info-box terisi">
                        <div class="info-row"><span class="info-label">Penghuni</span><span class="info-val">Taufik Hidayat</span></div>
                        <div class="info-row"><span class="info-label">Jatuh Tempo</span><span class="info-val highlight-date">16 Des 2024</span></div>
                    </div>
                    <button class="btn-action terisi">Detail Sewa</button>
                </div>
            </div>

            <!-- Kamar 13 -->
            <div class="room-card">
                <div class="card-status-bar terisi">
                    <span><i class="dot"></i>TERISI</span>
                    <span>Kamar 13</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 13</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Deluxe AC • KM Dalam</div>
                    <div class="info-box terisi">
                        <div class="info-row"><span class="info-label">Penghuni</span><span class="info-val">Nadia Putri</span></div>
                        <div class="info-row"><span class="info-label">Jatuh Tempo</span><span class="info-val highlight-date">27 Des 2024</span></div>
                    </div>
                    <button class="btn-action terisi">Detail Sewa</button>
                </div>
            </div>

            <!-- Kamar 14 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 14</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 14</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Standard AC • Kasur King</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.650.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 15 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 15</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 15</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Deluxe AC • Kasur Queen</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.700.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 16 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 16</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 16</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Deluxe AC • KM Dalam</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.700.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 17 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 17</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 17</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Deluxe AC • View Rooftop</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.700.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 18 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 18</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 18</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Standard AC • Kasur Queen</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.700.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 19 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 19</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 19</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Deluxe AC • KM Dalam</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.750.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 20 -->
            <div class="room-card">
                <div class="card-status-bar kosong">
                    <span><i class="dot"></i>KOSONG</span>
                    <span>Kamar 20</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 20</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Deluxe AC • Ventilasi Luas</div>
                    <div class="info-box kosong">
                        <div class="info-box-title">Harga Sewa</div>
                        <div class="info-box-price">Rp 1.750.000/bln</div>
                        <div class="info-box-status">✓ Siap Huni Bersih</div>
                    </div>
                    <button class="btn-action kosong">Check-in / Isi Kamar</button>
                </div>
            </div>

            <!-- Kamar 22 -->
            <div class="room-card">
                <div class="card-status-bar terisi">
                    <span><i class="dot"></i>TERISI</span>
                    <span>Kamar 22</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 22</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Standard AC • KM Luar</div>
                    <div class="info-box terisi">
                        <div class="info-row"><span class="info-label">Penghuni</span><span class="info-val">Hendra Subroto</span></div>
                        <div class="info-row"><span class="info-label">Jatuh Tempo</span><span class="info-val highlight-date">10 Des 2024</span></div>
                    </div>
                    <button class="btn-action terisi">Detail Sewa</button>
                </div>
            </div>

            <!-- Kamar 23 -->
            <div class="room-card">
                <div class="card-status-bar terisi">
                    <span><i class="dot"></i>TERISI</span>
                    <span>Kamar 23</span>
                </div>
                <div class="card-body">
                    <div class="room-head"><h2 class="room-name">Kamar 23</h2><span class="badge-floor">Lt. 2</span></div>
                    <div class="room-subtitle">Standard AC • Kasur Queen</div>
                    <div class="info-box terisi">
                        <div class="info-row"><span class="info-label">Penghuni</span><span class="info-val">Anisa Rahmawati</span></div>
                        <div class="info-row"><span class="info-label">Jatuh Tempo</span><span class="info-val highlight-date">08 Des 2024</span></div>
                    </div>
                    <button class="btn-action terisi">Detail Sewa</button>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>