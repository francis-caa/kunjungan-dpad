<?php
require_once __DIR__ . '/session_boot.php';
// Aktifkan error reporting sementara (hapus setelah stabil)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_admin']) || !is_numeric($_SESSION['id_admin'])) {
    header("Location: login.php");
    exit;
}

$id_admin = (int)$_SESSION['id_admin'];

include "koneksi.php";

date_default_timezone_set("Asia/Jakarta");

// TAMBAHAN: Session & Ambil data admin untuk header profil
session_start();
$id_admin = $_SESSION['id_admin'] ?? 1;

// Ambil data admin untuk foto profil
$query_admin = "SELECT foto, nama_admin FROM admin WHERE id_admin = $id_admin";
$result_admin = mysqli_query($koneksi, $query_admin);
$data_admin = mysqli_fetch_assoc($result_admin);
$foto_profil = !empty($data_admin['foto']) ? $data_admin['foto'] : 'profile.jpg';
$nama_bidang_header = $data_admin['nama_admin'] ?? 'Bidang Sekretariat';

// Parameter filter
$bidang  = isset($_GET['bidang']) ? $_GET['bidang'] : '';
$tahun   = isset($_GET['tahun']) ? $_GET['tahun'] : '';
$bulan   = isset($_GET['bulan']) ? $_GET['bulan'] : '';
$page    = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

$limit  = 10;
$offset = ($page - 1) * $limit;

// PERBAIKAN: Query untuk daftar bidang (harus dijalankan SEBELUM query utama)
$qBidangList = mysqli_query($koneksi, "SELECT DISTINCT bidang FROM agenda_kunjungan ORDER BY bidang");
$bidangOptions = [];
while($b = mysqli_fetch_assoc($qBidangList)) {
    $bidangOptions[] = $b['bidang'];
}

// Cek apakah ada filter aktif
$hasFilter = ($bidang !== '' || $tahun !== '' || $bulan !== '');

// Riwayat: agenda yang sudah lewat
$where = "WHERE ((a.tanggal < CURDATE()) OR (a.tanggal = CURDATE() AND a.waktu < CURTIME()))";

// Filter bidang
if ($bidang !== '') {
    $bidangEscaped = mysqli_real_escape_string($koneksi, $bidang);
    $where .= " AND a.bidang = '$bidangEscaped'";
}

// Filter tahun
if ($tahun !== '') {
    $tahunEscaped = mysqli_real_escape_string($koneksi, $tahun);
    $where .= " AND YEAR(a.tanggal) = '$tahunEscaped'";
}

// Filter bulan
if ($bulan !== '') {
    $bulanEscaped = mysqli_real_escape_string($koneksi, $bulan);
    $where .= " AND MONTH(a.tanggal) = '$bulanEscaped'";
}

// Query total data
$qTotal = mysqli_query($koneksi,
"SELECT COUNT(*) as total
 FROM agenda_kunjungan a
 JOIN ruangan r ON a.id_ruangan=r.id_ruangan
 $where");

$totalData = 0;
if ($qTotal) {
    $row = mysqli_fetch_assoc($qTotal);
    $totalData = $row['total'];
}
$totalPage = ceil($totalData / $limit);

// Query data agenda
$qAgenda = mysqli_query($koneksi,
"SELECT a.*, r.nama_ruangan
 FROM agenda_kunjungan a
 JOIN ruangan r ON a.id_ruangan=r.id_ruangan
 $where
 ORDER BY a.tanggal DESC, a.waktu_mulai DESC
 LIMIT $limit OFFSET $offset");

// Simpan data agenda dalam array
$agendaData = [];
if ($qAgenda) {
    while($row = mysqli_fetch_assoc($qAgenda)) {
        $agendaData[] = $row;
    }
}

// Mapping warna badge berdasarkan bidang
$badgeColors = [
    'Sekretariat' => ['bg' => '#10b981', 'color' => '#fff'],
    'PPI' => ['bg' => '#f97316', 'color' => '#fff'],
    'PPSK' => ['bg' => '#3b82f6', 'color' => '#fff'],
    'P2P' => ['bg' => '#06b6d4', 'color' => '#fff'],
    'PAS' => ['bg' => '#8b5cf6', 'color' => '#fff'],
    'default' => ['bg' => '#e0f2fe', 'color' => '#0369a1']
];

function getBadgeColor($bidang, $colors) {
    return isset($colors[$bidang]) ? $colors[$bidang] : $colors['default'];
}

// Mapping nama hari ke Indonesia
$hariIndo = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

// Mapping bulan ke Indonesia
$bulanIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Agenda</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: #f5f7fb;
        }

        /* HEADER */
        .header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 70px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            z-index: 100;
        }
        .header-inner {
            height: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 24px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-left img {
            height: 42px;
        }
        .header-left h1 {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-right img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        .header-right span {
            font-size: 14px;
            color: #374151;
            font-weight: 500;
        }

        /* SIDEBAR - PERSIS SAMA seperti halaman lain */
        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            width: 240px;
            height: calc(100vh - 70px);
            background: #fff;
            border-right: 1px solid #e5e7eb;
            padding: 20px 14px;
            z-index: 999;
        }
        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #6b7280;
            text-decoration: none;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .menu a:hover {
            background: #f3f4f6;
        }
        .menu a.active {
            background: #e8f0ff;
            color: #2563eb;
            font-weight: 600;
        }
        .menu a i {
            width: 20px;
            height: 20px;
        }
        .logout {
            position: absolute;
            bottom: 20px;
            left: 14px;
            right: 14px;
            color: #ef4444;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            padding: 10px 14px;
            border-radius: 10px;
            transition: all 0.2s;
            text-decoration: none !important;
        }
        .logout:hover,
        .logout:focus,
        .logout:active {
            background: #fef2f2;
            text-decoration: none !important;
            color: #dc2626;
        }

        /* CONTENT */
        .content {
            margin-left: 240px;
            padding: 100px 32px 32px;
        }

        /* FILTER */
        .filter-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .filter-left {
            display: flex;
            gap: 12px;
        }
        .filter-box {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            height: 42px;
            padding: 0 12px;
            min-width: 140px;
        }
        .filter-box select {
            border: none;
            outline: none;
            font-size: 14px;
            padding-left: 8px;
            background: none;
            font-weight: 400;
            color: #6b7280;
            width: 100%;
            cursor: pointer;
        }
        .filter-box i {
            color: #9ca3af;
            width: 16px;
            height: 16px;
        }
        .btn-show {
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0 20px;
            height: 42px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-show:hover {
            background: #1d4ed8;
        }
        .btn-show.reset {
            background: #6b7280;
        }
        .btn-show.reset:hover {
            background: #4b5563;
        }
        .filter-right {
            display: flex;
            gap: 10px;
        }
        .btn-export-excel {
            background: #1D6F42;
            color: #fff;
            padding: 0 16px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-export-excel:hover {
            background: #165532;
        }
        .btn-export-excel i {
            width: 18px;
            height: 18px;
        }
        .btn-export-pdf {
            background: #ef4444;
            color: #fff;
            padding: 0 16px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-export-pdf:hover {
            background: #dc2626;
        }
        .btn-export-pdf i {
            width: 18px;
            height: 18px;
        }

        /* TABLE */
        h2 {
            font-size: 20px;
            margin-bottom: 16px;
            font-weight: 600;
            color: #111827;
        }
        .table-box {
            background: #fff;
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .table-scroll {
            overflow-x: auto;
        }
        table {
            border-collapse: collapse;
            min-width: 1200px;
            width: 100%;
        }
        th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            padding: 12px;
            background: #f9fafb;
            font-weight: 600;
            white-space: nowrap;
        }
        td {
            padding: 16px 12px;
            border-top: 1px solid #f3f4f6;
            font-size: 14px;
            color: #374151;
            font-weight: 400;
            vertical-align: top;
        }
        tr:hover td {
            background: #f9fafb;
        }
        .badge {
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
        }
        .nama-kegiatan {
            color: #2563eb;
            font-weight: 500;
        }

        /* PAGINATION - DIUBAH MENJADI BENTUK SEPERTI GAMBAR 2 */
        .pagination-wrapper {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .pagination-info {
            font-size: 14px;
            color: #6b7280;
        }
        .pagination {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .pagination a {
            background: #f3f4f6;
            color: #374151;
        }
        .pagination a:hover {
            background: #e5e7eb;
        }
        .pagination a.active {
            background: #2563eb;
            color: #fff;
            font-weight: 600;
        }
        .pagination .dots {
            color: #6b7280;
            padding: 0 8px;
            min-width: auto;
        }
        .pagination .arrow {
            background: #f3f4f6;
            color: #374151;
            width: 36px;
            height: 36px;
        }
        .pagination .arrow:hover {
            background: #e5e7eb;
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-inner">
        <div class="header-left">
            <img src="images/logodpad.png" alt="Logo">
            <h1>SISTEM AGENDA KUNJUNGAN TAMU</h1>
        </div>
        <div class="header-right">
            <img src="<?= htmlspecialchars($foto_profil) ?>?v=<?= time() ?>" alt="Profile">
            <span><?= htmlspecialchars($nama_bidang_header) ?></span>
        </div>
    </div>
</header>

<aside class="sidebar">
    <div class="menu">
        <a href="beranda.php"><i data-feather="grid"></i>Beranda</a>
        <a href="daftar_agenda.php"><i data-feather="list"></i>Daftar Agenda</a>
        <a href="pemesanan_ruang.php"><i data-feather="calendar"></i>Pemesanan Ruang</a>
        <a href="agenda_bidang.php"><i data-feather="users"></i>Agenda Bidang Saya</a>
        <a class="active" href="riwayat_agenda.php"><i data-feather="clock"></i>Riwayat Agenda</a>
        <a href="profil.php"><i data-feather="user"></i>Profil Bidang</a>
    </div>
    <a href="logout.php" class="logout" onclick="return confirmLogout(event)">
        <i data-feather="log-out"></i>Keluar
    </a>
</aside>

<main class="content">
    <form method="GET" class="filter-wrapper">
        <div class="filter-left">
            <div class="filter-box">
                <select name="bidang">
                    <option value="">Pilih bidang</option>
                    <?php foreach($bidangOptions as $b): ?>
                        <option value="<?= htmlspecialchars($b) ?>" <?= ($bidang === $b) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-box">
                <select name="tahun">
                    <option value="">Pilih tahun</option>
                    <?php for($y = date('Y')-2; $y <= date('Y')+2; $y++): ?>
                        <option value="<?= $y ?>" <?= ($tahun == $y) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-box">
                <i data-feather="calendar"></i>
                <select name="bulan">
                    <option value="">Pilih bulan</option>
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?= $i ?>" <?= ($bulan == $i) ? 'selected' : '' ?>>
                            <?= $bulanIndo[$i] ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <?php if($hasFilter): ?>
                <a href="riwayat_agenda.php" class="btn-show reset">Reset</a>
            <?php else: ?>
                <button class="btn-show" type="submit">Tampilkan</button>
            <?php endif; ?>
        </div>
        <div class="filter-right">
            <a class="btn-export-excel" href="export_excel_riwayat.php?<?= http_build_query($_GET) ?>">
                <i data-feather="file-text"></i>Ekspor Excel
            </a>
            <a class="btn-export-pdf" href="export_pdf.php?<?= http_build_query($_GET) ?>&type=riwayat" target="_blank">
    <i data-feather="file-text"></i>Ekspor PDF
</a>
        </div>
    </form>

    <h2>Riwayat Agenda</h2>

    <div class="table-box">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>NAMA KEGIATAN</th>
                        <th>RUANG</th>
                        <th>HARI</th>
                        <th>TANGGAL</th>
                        <th>JAM</th>
                        <th>BIDANG</th>
                        <th>JUMLAH PESERTA</th>
                        <th>PEMESAN</th>
                        <th>CATATAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach($agendaData as $a): 
                        $badge = getBadgeColor($a['bidang'], $badgeColors);
                        $hari = $hariIndo[date('l', strtotime($a['tanggal']))];
                        $tgl = date('d', strtotime($a['tanggal'])) . ' ' . $bulanIndo[(int)date('m', strtotime($a['tanggal']))] . ' ' . date('Y', strtotime($a['tanggal']));
                    ?>
                        <tr>
                            <td class="nama-kegiatan"><?= htmlspecialchars($a['nama_kegiatan']) ?></td>
                            <td><?= htmlspecialchars($a['nama_ruangan']) ?></td>
                            <td><?= $hari ?></td>
                            <td><?= $tgl ?></td>
                            <td><?= substr($a['waktu_mulai'], 0, 5) ?> - <?= substr($a['waktu'], 0, 5) ?></td>
                            <td>
                                <span class="badge" style="background: <?= $badge['bg'] ?>; color: <?= $badge['color'] ?>">
                                    <?= htmlspecialchars($a['bidang']) ?>
                                </span>
                            </td>
                            <td><?= $a['jumlah_peserta'] ?></td>
                            <td><?= htmlspecialchars($a['nama_pemesan'] ?? '-') ?></td>
                            <td><?= $a['catatan'] ? htmlspecialchars($a['catatan']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination - bentuk baru sesuai gambar 2 -->
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Menampilkan <?= (($page - 1) * $limit) + 1 ?>-<?= min($page * $limit, $totalData) ?> data dari <?= $totalData ?> data
            </div>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&bidang=<?= urlencode($bidang) ?>&tahun=<?= $tahun ?>&bulan=<?= $bulan ?>" class="arrow">
                        <i data-feather="chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPage, $page + 2);

                if ($startPage > 1) {
                    echo '<a href="?page=1&bidang='.urlencode($bidang).'&tahun='.$tahun.'&bulan='.$bulan.'">1</a>';
                    if ($startPage > 2) {
                        echo '<span class="dots">...</span>';
                    }
                }

                for ($i = $startPage; $i <= $endPage; $i++) {
                    $class = ($i == $page) ? 'active' : '';
                    echo "<a href=\"?page=$i&bidang=".urlencode($bidang)."&tahun=$tahun&bulan=$bulan\" class=\"$class\">$i</a>";
                }

                if ($endPage < $totalPage) {
                    if ($endPage < $totalPage - 1) {
                        echo '<span class="dots">...</span>';
                    }
                    echo '<a href="?page='.$totalPage.'&bidang='.urlencode($bidang).'&tahun='.$tahun.'&bulan='.$bulan.'">'.$totalPage.'</a>';
                }
                ?>

                <?php if ($page < $totalPage): ?>
                    <a href="?page=<?= $page+1 ?>&bidang=<?= urlencode($bidang) ?>&tahun=<?= $tahun ?>&bulan=<?= $bulan ?>" class="arrow">
                        <i data-feather="chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
    feather.replace();

    // Konfirmasi logout - sama persis seperti halaman lain
    function confirmLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Keluar?',
            text: "Anda yakin ingin keluar dari sistem?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Keluar',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
        return false;
    }
</script>
</body>
</html>