<?php
require_once __DIR__ . '/session_boot.php';
session_start();

// Cek login
if (!isset($_SESSION['id_admin'])) {
    header("Location: login.php");
    exit;
}

$koneksi = mysqli_connect("localhost", "root", "", "kunjungan");
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

date_default_timezone_set("Asia/Jakarta");

$id_admin = $_SESSION['id_admin'];

// Ambil data admin untuk header (foto & nama)
$stmt = mysqli_prepare($koneksi, "SELECT foto, nama_admin FROM admin WHERE id_admin = ?");
mysqli_stmt_bind_param($stmt, "i", $id_admin);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data_admin = mysqli_fetch_assoc($result);

$foto_profil = !empty($data_admin['foto']) ? $data_admin['foto'] : 'profile.jpg';
$nama_bidang_header = $data_admin['nama_admin'] ?? 'Bidang Sekretariat';

$today = date('Y-m-d');
$now   = date('H:i:s');

// ────────────────────────────────────────────────
// Hitung card (tetap sama)
$agendaHariIni = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) total FROM agenda_kunjungan WHERE tanggal = '$today'"))['total'] ?? 0;

$agendaMendatang = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) total FROM agenda_kunjungan 
     WHERE tanggal BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
       AND (tanggal > CURDATE() OR (tanggal = CURDATE() AND waktu_mulai >= CURTIME()))"))['total'] ?? 0;

$ruangDipakai = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(DISTINCT id_ruangan) total FROM agenda_kunjungan 
     WHERE tanggal = '$today' 
       AND waktu_mulai <= CURTIME() AND waktu >= CURTIME()"))['total'] ?? 0;   

// ────────────────────────────────────────────────
// Data tabel: HANYA agenda yang BELUM selesai
// ────────────────────────────────────────────────
$query = "
    SELECT a.*, r.nama_ruangan 
    FROM agenda_kunjungan a
    JOIN ruangan r ON a.id_ruangan = r.id_ruangan
    WHERE a.tanggal > CURDATE()
       OR (
           a.tanggal = CURDATE() 
           AND (
               a.waktu_mulai > CURTIME()                       -- belum mulai
               OR (a.waktu_mulai <= CURTIME() AND a.waktu >= CURTIME())  -- sedang berlangsung
           )
       )
    ORDER BY a.tanggal ASC, a.waktu_mulai ASC
";

$resultAgenda = mysqli_query($koneksi, $query);
if (!$resultAgenda) {
    die("Query agenda gagal: " . mysqli_error($koneksi));
}

$agendaList = [];
while ($row = mysqli_fetch_assoc($resultAgenda)) {
    $agendaList[] = $row;
}

// Agenda bidang sendiri (belum lewat)
$agendaBidangSaya = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) total FROM agenda_kunjungan 
     WHERE (tanggal > CURDATE() OR (tanggal = CURDATE() AND waktu >= CURTIME()))
       AND bidang = '" . mysqli_real_escape_string($koneksi, $nama_bidang_header) . "'"
))['total'] ?? 0;

// ────────────────────────────────────────────────
// Data pendukung
// ────────────────────────────────────────────────
$hariIndo = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu',
    'Sunday'    => 'Minggu'
];

$bulanIndo = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];

function getStatusAgenda($tanggal, $waktu_mulai, $waktu
) {
    $today = date('Y-m-d');
    $now   = date('H:i:s');
    
    if ($tanggal > $today) {
        return ['text' => 'Akan Datang', 'class' => 'on'];
    }
    
    if ($tanggal == $today) {
        if ($waktu_mulai > $now) {
            return ['text' => 'Akan Datang', 'class' => 'on'];
        }
        if ($waktu_mulai <= $now && ($waktu ?? '23:59:59') >= $now) {
            return ['text' => 'Sedang Berlangsung', 'class' => 'off'];
        }
    }
    
    return ['text' => 'Selesai', 'class' => 'off']; // seharusnya tidak muncul karena sudah difilter
}

$badgeColors = [
    'Sekretariat'  => '#2563eb',
    'Keuangan'     => '#16a34a',
    'Umum'         => '#d97706',
    'SDM'          => '#dc2626',
    'Perencanaan'  => '#7c3aed',
];

function getBidangColor($bidang, $colors) {
    return $colors[$bidang] ?? '#6b7280';
}

mysqli_close($koneksi);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { background:#f5f7fb; }

        /* Header - sama seperti profil.php */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }
        .header-left { display: flex; align-items: center; gap: 12px; }
        .header-left img { height: 42px; }
        .header-left h1 { font-size: 16px; font-weight: 600; color: #111827; }
        .header-right { display: flex; align-items: center; gap: 10px; }
        .header-right img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb; }
        .header-right span { font-size: 14px; color: #374151; font-weight: 500; }

        /* Sidebar - PERSIS SAMA seperti profil.php */
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

        /* Logout - tanpa underline, persis seperti profil.php */
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

        /* Konten */
        .content {
            margin-left: 240px;
            padding: 90px 32px 32px;
        }
        h2 {
            font-size: 24px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 30px;
        }

        /* Cards */
        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .card {
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card p {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
        .card h1 {
            font-size: 28px;
            margin: 6px 0;
            font-weight: 700;
        }
        .card span {
            font-size: 12px;
            color: #9ca3af;
            font-weight: 400;
        }

        /* Tabel */
        .table-box {
            background: #fff;
            border-radius: 14px;
            padding: 16px;
        }
        .table-box h3 {
            font-size: 16px;
            margin-bottom: 14px;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            padding: 12px;
            background: #f9fafb;
            font-weight: 600;
        }
        td {
            padding: 14px 12px;
            border-top: 1px solid #f1f1f1;
            font-size: 14px;
            color: #374151;
        }
        .status {
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status.on { background: #dcfce7; color: #166534; }
        .status.off { background: #e0f2fe; color: #0369a1; }

        .badge-bidang {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            color: white;
        }

        @media (max-width: 1200px) {
            .cards { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .content { margin-left: 0; }
        }
        @media (max-width: 768px) {
            .cards { grid-template-columns: 1fr; }
            .content { padding: 90px 20px 20px; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-left">
        <img src="images/logodpad.png" alt="Logo">
        <h1>SISTEM AGENDA KUNJUNGAN TAMU</h1>
    </div>
    <div class="header-right">
        <img src="<?= htmlspecialchars($foto_profil) ?>?v=<?= time() ?>" alt="Profile">
        <span><?= htmlspecialchars($nama_bidang_header) ?></span>
    </div>
</header>

<aside class="sidebar">
    <div class="menu">
        <a class="active" href="beranda.php"><i data-feather="grid"></i>Beranda</a>
        <a href="daftar_agenda.php"><i data-feather="list"></i>Daftar Agenda</a>
        <a href="pemesanan_ruang.php"><i data-feather="calendar"></i>Pemesanan Ruang</a>
        <a href="agenda_bidang.php"><i data-feather="users"></i>Agenda Bidang Saya</a>
        <a href="riwayat_agenda.php"><i data-feather="clock"></i>Riwayat Agenda</a>
        <a href="profil.php"><i data-feather="user"></i>Profil Bidang</a>
    </div>
    
    <a href="login.php" class="logout" onclick="return confirmLogout(event)">
        <i data-feather="log-out"></i>Keluar
    </a>
</aside>

<main class="content">
    <h2>Beranda</h2>

    <div class="cards">
        <div class="card">
            <p>Jumlah agenda hari ini</p>
            <h1><?= $agendaHariIni ?></h1>
            <span>Dari seluruh bidang</span>
        </div>
        <div class="card">
            <p>Agenda mendatang</p>
            <h1><?= $agendaMendatang ?></h1>
            <span>7 hari ke depan</span>
        </div>
        <div class="card">
            <p>Ruang dipakai saat ini</p>
            <h1><?= $ruangDipakai ?></h1>
            <span>Total ruangan</span>
        </div>
        <div class="card">
            <p>Agenda bidang sendiri</p>
            <h1><?= $agendaBidangSaya ?></h1>
            <span><?= htmlspecialchars($nama_bidang_header) ?></span>
        </div>
    </div>

    <div class="table-box">
        <h3>Daftar Agenda</h3>
        <table>
            <thead>
                <tr>
                    <th>STATUS</th>
                    <th>NAMA KEGIATAN</th>
                    <th>RUANG</th>
                    <th>HARI</th>
                    <th>TANGGAL</th>
                    <th>JAM</th>
                    <th>BIDANG</th>
                    <th>JUMLAH PESERTA</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($agendaList)): ?>
                    <?php foreach ($agendaList as $a): 
                        $status = getStatusAgenda($a['tanggal'], $a['waktu_mulai'], $a['waktu'] ?? $a['waktu'] ?? '23:59:59');
                        $hari = $hariIndo[date('l', strtotime($a['tanggal']))] ?? '-';
                        $tgl = date('d', strtotime($a['tanggal'])) . ' ' . 
                               ($bulanIndo[(int)date('m', strtotime($a['tanggal']))] ?? '???') . ' ' . 
                               date('Y', strtotime($a['tanggal']));
                        $bidangColor = getBidangColor($a['bidang'] ?? 'Lainnya', $badgeColors);
                    ?>
                        <tr>
                            <td><span class="status <?= $status['class'] ?>"><?= $status['text'] ?></span></td>
                            <td><?= htmlspecialchars($a['nama_kegiatan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['nama_ruangan'] ?? '-') ?></td>
                            <td><?= $hari ?></td>
                            <td><?= $tgl ?></td>
                            <td><?= substr($a['waktu_mulai'] ?? '00:00', 0, 5) ?> – <?= substr($a['waktu'] ?? $a['waktu'] ?? '23:59', 0, 5) ?></td>
                            <td><span class="badge-bidang" style="background: <?= $bidangColor ?>"><?= htmlspecialchars($a['bidang'] ?? 'Tidak diketahui') ?></span></td>
                            <td><?= $a['jumlah_peserta'] ?? '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                            Tidak ada agenda yang belum selesai
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
    feather.replace();

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
                window.location.href = 'login.php';
            }
        });
        return false;
    }
</script>

</body>
</html>