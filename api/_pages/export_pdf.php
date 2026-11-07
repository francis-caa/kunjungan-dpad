<?php
$koneksi = mysqli_connect("localhost", "root", "", "kunjungan");
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

date_default_timezone_set("Asia/Jakarta");

// Ambil parameter
$keyword = $_GET['keyword'] ?? '';
$bulan   = $_GET['bulan'] ?? '';
$tahun   = $_GET['tahun'] ?? '';
$type    = $_GET['type'] ?? 'aktif';  // default: aktif

// Tentukan judul dan kondisi waktu berdasarkan type
if ($type === 'riwayat') {
    $judul_halaman = "Riwayat Agenda Kunjungan Tamu";
    $kondisi_waktu = "a.tanggal < CURDATE()";  // sudah lewat
} else {
    $judul_halaman = "Daftar Agenda Aktif";
    $kondisi_waktu = "(a.tanggal > CURDATE() OR (a.tanggal = CURDATE() AND a.waktu_mulai >= CURTIME()))";
}

// Bangun WHERE
$where = "WHERE $kondisi_waktu";
if ($keyword !== '') {
    $where .= " AND a.nama_kegiatan LIKE '%" . mysqli_real_escape_string($koneksi, $keyword) . "%'";
}
if ($bulan !== '') {
    $where .= " AND MONTH(a.tanggal) = '" . mysqli_real_escape_string($koneksi, $bulan) . "'";
}
if ($tahun !== '') {
    $where .= " AND YEAR(a.tanggal) = '" . mysqli_real_escape_string($koneksi, $tahun) . "'";
}

// Query utama
$query = "
    SELECT 
        a.nama_kegiatan,
        r.nama_ruangan,
        DAYNAME(a.tanggal) AS hari,
        DATE_FORMAT(a.tanggal, '%d %M %Y') AS tanggal,
        CONCAT(SUBSTRING(a.waktu_mulai, 1, 5), ' - ', SUBSTRING(a.waktu, 1, 5)) AS jam,
        a.bidang,
        a.jumlah_peserta,
        IFNULL(a.catatan, '-') AS catatan
    FROM agenda_kunjungan a
    JOIN ruangan r ON a.id_ruangan = r.id_ruangan
    $where
    ORDER BY a.tanggal, a.waktu_mulai
";
$result = mysqli_query($koneksi, $query);
if (!$result) {
    die("Query gagal: " . mysqli_error($koneksi));
}

// Warna bidang (sama seperti sebelumnya)
$badgeColors = [
    'Sekretariat' => '#10b981',
    'PBPI'        => '#f97316',
    'PPSK'        => '#3b82f6',
    'P2P'         => '#06b6d4',
    'PAS'         => '#8b5cf6',
    'default'     => '#6b7280'
];

function getBidangColor($bidang, $colors) {
    return $colors[$bidang] ?? $colors['default'];
}

// Hari & Bulan Indo
$hariIndo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulanIndo = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($judul_halaman) ?> - Cetak</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background:#fff;
            color:#1f2937;
            font-size:11pt;
            line-height:1.5;
        }
        .container {
            width:100%;
            max-width:210mm;
            margin:0 auto;
            padding:15mm 12mm;
        }
        header { text-align:center; margin-bottom:12mm; }
        h1 {
            font-size:18pt;
            font-weight:700;
            color:#111827;
            margin-bottom:4mm;
        }
        .info {
            font-size:10pt;
            color:#4b5563;
            margin-bottom:2mm;
        }
        .info strong { color:#1f2937; }
        .filter-info {
            font-style:italic;
            color:#6b7280;
            margin-top:2mm;
        }
        table {
            width:100%;
            border-collapse:collapse;
            margin-top:8mm;
            font-size:10pt;
        }
        th, td {
            border:1px solid #d1d5db;
            padding:8px 10px;
            text-align:left;
            vertical-align:top;
        }
        th {
            background:#f3f4f6;
            font-weight:600;
            text-transform:uppercase;
            font-size:9pt;
            color:#4b5563;
            letter-spacing:0.5px;
        }
        td { color:#374151; }
        tr:nth-child(even) { background:#f9fafb; }
        .bidang-badge {
            display:inline-block;
            padding:4px 10px;
            border-radius:999px;
            font-size:9pt;
            font-weight:500;
            color:#ffffff;
            min-width:80px;
            text-align:center;
        }
        .nama-kegiatan {
            font-weight:500;
            color:#2563eb;
        }
        .print-controls {
            text-align:center;
            margin:20px 0;
        }
        .btn-print {
            background:#2563eb;
            color:white;
            border:none;
            border-radius:8px;
            padding:10px 24px;
            font-size:14px;
            font-weight:500;
            cursor:pointer;
            transition:all 0.2s;
        }
        .btn-print:hover { background:#1d4ed8; }
        @media print {
            body { margin:0; }
            .container { padding:10mm; }
            .print-controls { display:none !important; }
            tr { page-break-inside:avoid; }
            table { page-break-inside:auto; }
        }
    </style>
</head>
<body onload="window.print();">

<div class="container">

    <header>
        <h1><?= htmlspecialchars($judul_halaman) ?></h1>
        
        <div class="info">
            Tanggal cetak: <?= date('d F Y H:i') ?> WIB
        </div>
        
        <?php
        $filterParts = [];
        if ($keyword) $filterParts[] = "Kata kunci: <strong>" . htmlspecialchars($keyword) . "</strong>";
        if ($bulan)   $filterParts[] = "Bulan: <strong>" . $bulanIndo[(int)$bulan] . "</strong>";
        if ($tahun)   $filterParts[] = "Tahun: <strong>$tahun</strong>";
        if (!empty($filterParts)):
        ?>
            <div class="filter-info">
                Filter: <?= implode(' | ', $filterParts) ?>
            </div>
        <?php endif; ?>
    </header>

    <table>
        <thead>
            <tr>
                <th>Nama Kegiatan</th>
                <th>Ruang</th>
                <th>Hari</th>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Bidang</th>
                <th>Jumlah Peserta</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): 
                    $bidangColor = getBidangColor($row['bidang'], $badgeColors);
                    $hari = $hariIndo[$row['hari']] ?? ucfirst($row['hari']);
                ?>
                    <tr>
                        <td class="nama-kegiatan"><?= htmlspecialchars($row['nama_kegiatan']) ?></td>
                        <td><?= htmlspecialchars($row['nama_ruangan']) ?></td>
                        <td><?= $hari ?></td>
                        <td><?= $row['tanggal'] ?></td>
                        <td><?= $row['jam'] ?></td>
                        <td>
                            <span class="bidang-badge" style="background: <?= $bidangColor ?>">
                                <?= htmlspecialchars($row['bidang']) ?>
                            </span>
                        </td>
                        <td style="text-align:center;"><?= $row['jumlah_peserta'] ?></td>
                        <td><?= htmlspecialchars($row['catatan']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:30px; color:#6b7280; font-style:italic;">
                        Tidak ada data agenda <?= ($type === 'riwayat') ? 'riwayat' : 'aktif' ?> dengan filter tersebut
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- Tombol cetak ulang (hanya di layar) -->
<div class="print-controls">
    <button class="btn-print" onclick="window.print()">
        Cetak / Simpan sebagai PDF
    </button>
</div>

</body>
</html>

<?php mysqli_close($koneksi); ?>