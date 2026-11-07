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
session_start();

$id_admin = $_SESSION['id_admin'];

// Ambil data admin untuk foto profil DAN nama bidang yang sedang login
$query_admin = "SELECT foto, nama_admin FROM admin WHERE id_admin = ?";
$stmt_admin = mysqli_prepare($koneksi, $query_admin);
mysqli_stmt_bind_param($stmt_admin, "i", $id_admin);
mysqli_stmt_execute($stmt_admin);
$result_admin = mysqli_stmt_get_result($stmt_admin);
$data_admin = mysqli_fetch_assoc($result_admin);

$foto_profil = !empty($data_admin['foto']) ? $data_admin['foto'] : 'profile.jpg';
$nama_bidang_header = $data_admin['nama_admin'] ?? 'Bidang Sekretariat';

// Ambil bidang login dari nama_admin, lalu hilangkan kata "Bidang " jika ada
$bidang_login = $data_admin['nama_admin'] ?? 'Sekretariat';
$bidang_login = trim(str_replace(['Bidang ', 'bidang '], '', $bidang_login)); // case-insensitive & hilangkan spasi ekstra

$ruangan = mysqli_query($koneksi, "SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC");

$keyword = $_GET['cari'] ?? '';
$id_ruang_filter = $_GET['ruang'] ?? '';

$sql = "
  SELECT 
      ak.*,
      r.nama_ruangan
  FROM agenda_kunjungan ak
  JOIN ruangan r ON r.id_ruangan = ak.id_ruangan
  WHERE ak.bidang = '" . mysqli_real_escape_string($koneksi, $bidang_login) . "'
    AND (
        ak.tanggal > CURDATE() 
        OR (
            ak.tanggal = CURDATE() 
            AND ak.waktu >= CURTIME()           -- waktu selesai belum lewat hari ini
        )
    )
";

if ($keyword !== '') {
    $sql .= " AND ak.nama_kegiatan LIKE '%" . mysqli_real_escape_string($koneksi, $keyword) . "%'";
}
if ($id_ruang_filter !== '') {
    $sql .= " AND ak.id_ruangan = " . (int)$id_ruang_filter;
}
$sql .= " ORDER BY ak.tanggal ASC, ak.waktu_mulai ASC";

$agenda = mysqli_query($koneksi, $sql);

$riwayat = mysqli_query($koneksi, "
    SELECT nama_kegiatan, tanggal
    FROM agenda_kunjungan
    WHERE bidang = '" . mysqli_real_escape_string($koneksi, $bidang_login) . "'
    ORDER BY tanggal DESC, waktu_mulai DESC
    LIMIT 5
");

mysqli_close($koneksi);

function tanggalIndo($t)
{
    $namaBulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $split = explode('-', $t);
    return (int)$split[2] . ' ' . $namaBulan[(int)$split[1]] . ' ' . $split[0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Agenda Bidang Saya</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --blue: #2563eb;
            --red: #ef4444;
            --gray: #6b7280;
            --bg: #f5f7fb;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: var(--bg);
        }
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
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
        .content {
            margin-left: 240px;
            padding: 100px 32px 32px;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .top-bar h2 {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
        }
        .filter-row {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .filter-row input[type=text] {
            width: 240px;
            height: 38px;
            padding: 0 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-row select {
            height: 38px;
            padding: 0 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
        }
        .btn-reset {
            background: #fff;
            border: 1px solid #ccc;
            padding: 0 14px;
            height: 38px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-reset:hover {
            background: #f3f4f6;
        }
        .cols {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
        }
        .agenda-box{
            background:#fff;
            border-radius:12px;
            padding:20px 24px;
            box-shadow:0 2px 8px rgba(0,0,0,.08);
            margin-bottom:16px;
            position:relative;
        }
        .agenda-header{
            display:flex;
            align-items:center;
            gap:8px;
            font-size:14px;
            color:#6b7280;
            margin-bottom:12px;
        }
        .agenda-title{
            font-size:18px;
            font-weight:600;
            color:#111827;
            margin-bottom:10px;
        }
        .icon-jam,
        .icon-lokasi{
            width:16px;
            height:16px;
            color:#9ca3af;
        }
        .txt-jam{ margin-right:16px; }
        .txt-lokasi{ color:#374151; }
        .agenda-meta{
            display:flex;
            align-items:center;
            gap:6px;
            font-size:13px;
            color:#6b7280;
        }
        .icon-meta{
            width:14px;
            height:14px;
            color:#9ca3af;
        }
        .bullet{ margin:0 4px; }
        .badge-peserta {
            display: inline-flex;
            align-items: center;
            background: #dbeafe;
            color: #2563eb;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 12px;
        }
        .agenda-actions{
            position:absolute;
            right:24px;
            bottom:20px;
            display:flex;
            gap:10px;
        }
        .btn-act{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:6px 14px;
            border-radius:8px;
            font-size:13px;
            font-weight:500;
            text-decoration:none;
            border:1px solid;
        }
        .btn-edit{
            background:#fff;
            color:#6b7280;
            border-color:#ccc;
        }
        .btn-edit:hover{ background:#eff6ff; }
        .btn-hapus{
            background:#ef4444;
            color:#fff;
            border-color:#ef4444;
        }
        .btn-hapus:hover{ background:#ef4444; }
        .kalender-mini {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        .kalender-mini h4 {
            margin-bottom: 12px;
            font-size: 16px;
            font-weight: 600;
        }
        .mini-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .mini-nav button {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--blue);
            font-size: 18px;
        }
        .mini-nav span {
            font-weight: 600;
            font-size: 14px;
        }
        .mini-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
            text-align: center;
            font-size: 13px;
        }
        .mini-grid .day {
            padding: 6px 0;
            border-radius: 6px;
        }
        .mini-grid .day.label {
            font-weight: 600;
            color: var(--gray);
        }
        .mini-grid .day.active {
            background: var(--blue);
            color: #fff;
            font-weight: 600;
        }
        .mini-grid .day:not(.label):hover {
            background: #e5e7eb;
            cursor: pointer;
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
        <a class="active" href="agenda_bidang.php"><i data-feather="users"></i>Agenda Bidang Saya</a>
        <a href="riwayat_agenda.php"><i data-feather="clock"></i>Riwayat Agenda</a>
        <a href="profil.php"><i data-feather="user"></i>Profil Bidang</a>
    </div>
    <a href="logout.php" class="logout" onclick="return confirmLogout(event)">
        <i data-feather="log-out"></i>Keluar
    </a>
</aside>

<main class="content">
    <!-- judul -->
    <h2 style="font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 24px;">Agenda Bidang Saya</h2>

    <!-- filter di bawah judul -->
    <form method="GET" class="filter-row" style="margin-bottom:24px;">
        <input type="date" name="tgl" value="<?= $_GET['tgl'] ?? '' ?>"
               style="width:200px;padding:0 12px;height:38px;border:1px solid #ccc;border-radius:8px;font-size:14px;">

        <input type="text" name="cari" placeholder="Cari nama agenda…"
               value="<?= htmlspecialchars($keyword) ?>"
               style="width:200px;padding:0 12px;height:38px;border:1px solid #ccc;border-radius:8px;font-size:14px;">

        <button type="submit" class="btn-reset"
                style="background:var(--blue);color:#fff;border:none;padding:0 18px;height:38px;border-radius:8px;font-size:14px;cursor:pointer;">
            Reset Filter
        </button>
    </form>

    <div class="cols">
        <!-- daftar agenda -->
        <div class="card">
            <?php if (mysqli_num_rows($agenda) === 0): ?>
                <p style="text-align:center;color:var(--gray)">
                    Belum ada agenda untuk bidang <?= htmlspecialchars($bidang_login) ?>.
                </p>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($agenda)): ?>
                    <div class="agenda-box">
                        <div class="agenda-title"><?= htmlspecialchars($row['nama_kegiatan']) ?></div>
                        <div class="agenda-meta">
                            <i data-feather="calendar" class="icon-meta"></i>
                            <span><?= tanggalIndo($row['tanggal']) ?></span>
                            <span class="bullet">•</span>
                            <i data-feather="clock" class="icon-meta"></i>
                            <span><?= substr($row['waktu_mulai'],0,5) ?> - <?= substr($row['waktu'],0,5) ?></span>
                            <span class="bullet">•</span>
                            <i data-feather="home" class="icon-meta"></i>
                            <span>Ruang <?= htmlspecialchars($row['nama_ruangan']) ?></span>
                        </div>
                        <?php if (!empty($row['catatan'])): ?>
                        <div class="agenda-meta" style="margin-top: 8px;">
                            <span><?= htmlspecialchars($row['catatan']) ?></span>
                        </div>
                        <?php else: ?>
                        <div class="agenda-meta" style="margin-top: 8px;">
                            <span>-</span>
                        </div>
                        <?php endif; ?>
                        <div class="badge-peserta">
                            <?= $row['jumlah_peserta'] ?> Peserta
                        </div>
                        <div class="agenda-actions">
                            <a href="edit_agenda.php?id=<?= $row['id_kegiatan'] ?>" class="btn-act btn-edit">
                                <i data-feather="edit-2"></i> Edit
                            </a>
                            <a href="hapus_agenda.php?id=<?= $row['id_kegiatan'] ?>"
                               class="btn-act btn-hapus">
                                <i data-feather="trash-2"></i> Hapus
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- kalender mini -->
        <div class="kalender-mini">
            <h4>Kalender Mini</h4>
            <div class="mini-nav">
                <button id="prev"><</button>
                <span id="bulan-tahun"></span>
                <button id="next">></button>
            </div>
            <div class="mini-grid" id="tgl-grid"></div>
        </div>
    </div>
</main>

<script>
    feather.replace();

    const namaBulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    let tglNow = new Date();
    let bulan = tglNow.getMonth();
    let tahun = tglNow.getFullYear();

    function renderKalender() {
        const first = new Date(tahun, bulan, 1).getDay();
        const daysInMonth = new Date(tahun, bulan + 1, 0).getDate();
        const grid = document.getElementById('tgl-grid');
        grid.innerHTML = '';

        const labelHari = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        labelHari.forEach(h => {
            const d = document.createElement('div');
            d.className = 'day label';
            d.textContent = h;
            grid.appendChild(d);
        });

        for (let i = 0; i < first; i++) {
            const d = document.createElement('div');
            d.className = 'day';
            grid.appendChild(d);
        }

        for (let t = 1; t <= daysInMonth; t++) {
            const d = document.createElement('div');
            d.className = 'day';
            d.textContent = t;
            const iso = `${tahun}-${String(bulan + 1).padStart(2, '0')}-${String(t).padStart(2, '0')}`;
            if (iso === `<?= date('Y-m-d') ?>`) d.classList.add('active');
            grid.appendChild(d);
        }

        document.getElementById('bulan-tahun').textContent = `${namaBulan[bulan]} ${tahun}`;
    }

    document.getElementById('prev').onclick = () => {
        bulan--;
        if (bulan < 0) {
            bulan = 11;
            tahun--;
        }
        renderKalender();
    };
    document.getElementById('next').onclick = () => {
        bulan++;
        if (bulan > 11) {
            bulan = 0;
            tahun++;
        }
        renderKalender();
    };
    renderKalender();

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
                window.location.href = 'login.php'
            }
        });
        return false;
    }
</script>

</body>
</html>