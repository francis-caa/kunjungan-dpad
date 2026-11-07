<?php
require_once __DIR__ . '/session_boot.php';
$koneksi = mysqli_connect("localhost", "root", "", "kunjungan");
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
date_default_timezone_set("Asia/Jakarta");
session_start();

$bidang_login = 'Sekretariat';  
$id_agenda = $_GET['id'] ?? 0;

// Ambil data agenda yang akan diedit
$query = "SELECT * FROM agenda_kunjungan WHERE id_kegiatan = ?";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $id_agenda);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "<script>alert('Data tidak ditemukan'); window.location='agenda_bidang.php';</script>";
    exit;
}

// Ambil data ruangan
$query_ruangan = "SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC";
$result_ruangan = mysqli_query($koneksi, $query_ruangan);

// Ambil data untuk background (sama seperti agenda_bidang.php)
$ruangan = mysqli_query($koneksi, "SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC");
$keyword = $_GET['cari'] ?? '';
$tgl = $_GET['tgl'] ?? '';

$sql = "
  SELECT ak.*, r.nama_ruangan
  FROM agenda_kunjungan ak
  JOIN ruangan r ON r.id_ruangan = ak.id_ruangan
  WHERE ak.bidang = '" . mysqli_real_escape_string($koneksi, $bidang_login) . "'
";
if ($keyword !== '') {
    $sql .= " AND ak.nama_kegiatan LIKE '%" . mysqli_real_escape_string($koneksi, $keyword) . "%'";
}
if ($tgl !== '') {
    $sql .= " AND ak.tanggal = '" . mysqli_real_escape_string($koneksi, $tgl) . "'";
}
$sql .= " ORDER BY ak.tanggal ASC, ak.waktu_mulai ASC";
$agenda = mysqli_query($koneksi, $sql);

// Proses update
if (isset($_POST['simpan'])) {
    $stmt = $koneksi->prepare("
        UPDATE agenda_kunjungan SET
        id_ruangan = ?, nama_kegiatan = ?, hari = ?, tanggal = ?, 
        waktu_mulai = ?, waktu = ?, jumlah_peserta = ?, 
        bidang = ?, nama_pemesan = ?, catatan = ?
        WHERE id_kegiatan = ?
    ");
    
    $id_ruangan     = $_POST['id_ruangan'];
    $nama_kegiatan  = $_POST['nama_kegiatan'];
    $hari           = $_POST['hari'];
    $tanggal        = $_POST['tanggal'];
    $waktu_mulai    = $_POST['waktu_mulai'];
    $waktu          = $_POST['waktu_selesai'];
    $jumlah_peserta = $_POST['jumlah_peserta'];
    $bidang         = $_POST['bidang'];
    $nama_pemesan   = $_POST['nama_pemesan'];
    $catatan        = $_POST['catatan'] ?? '';
    
    $stmt->bind_param(
        "isssssisssi",
        $id_ruangan, $nama_kegiatan, $hari, $tanggal,
        $waktu_mulai, $waktu, $jumlah_peserta,
        $bidang, $nama_pemesan, $catatan, $id_agenda
    );
    
    if ($stmt->execute()) {
        echo "<script>window.location='agenda_bidang.php';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

mysqli_close($koneksi);

function tanggalIndo($t){
    $namaBulan = [1=>'Januari','Februari','Maret','April','Mei','Juni',
                  'Juli','Agustus','September','Oktober','November','Desember'];
    $split = explode('-', $t);
    return (int)$split[2].' '.$namaBulan[(int)$split[1]].' '.$split[0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Agenda</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root{--blue:#2563eb;--red:#ef4444;--gray:#6b7280;--bg:#f5f7fb;}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
        
        body{background:var(--bg);}
        
        /* Header */
        .header{position:fixed;top:0;left:0;right:0;height:70px;background:#fff;border-bottom:1px solid #e5e7eb;z-index:100;}
        .header-inner{height:100%;display:flex;justify-content:space-between;align-items:center;padding:0 24px;}
        .header-left{display:flex;align-items:center;gap:12px;}
        .header-left img{height:42px;}
        .header-left h1{font-size:16px;font-weight:600;}
        .header-right{display:flex;align-items:center;gap:10px;}
        .header-right img{width:36px;height:36px;border-radius:50%;}
        
        /* Sidebar */
        .sidebar{position:fixed;top:70px;left:0;width:240px;height:calc(100vh - 70px);background:#fff;border-right:1px solid #e5e7eb;padding:20px 14px;}
        .menu a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;color:var(--gray);text-decoration:none;margin-bottom:6px;font-size:14px;font-weight:500;}
        .menu a.active{background:#e8f0ff;color:var(--blue);font-weight:600;}
        .logout{position:absolute;bottom:20px;left:14px;color:var(--red);display:flex;gap:10px;cursor:pointer;font-weight:500;}
        
        /* Konten Agenda */
        .content{margin-left:240px;padding:90px 32px 32px;}
        h2{font-size:22px;font-weight:600;margin-bottom:12px;}
        .filter-row{display:flex;gap:12px;align-items:center;margin-bottom:24px;}
        .filter-row input[type=date], .filter-row input[type=text]{width:200px;height:38px;padding:0 12px;border:1px solid #ccc;border-radius:8px;font-size:14px;}
        .btn-reset{background:var(--blue);color:#fff;border:none;padding:0 18px;height:38px;border-radius:8px;font-size:14px;cursor:pointer;}
        .cols{display:grid;grid-template-columns:1fr 320px;gap:24px;}
        
        /* Kartu Agenda */
        .agenda-box{background:#fff;border-radius:12px;padding:20px 24px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:16px;position:relative;}
        .agenda-header{display:flex;align-items:center;gap:8px;font-size:14px;color:var(--gray);margin-bottom:12px;}
        .agenda-title{font-size:18px;font-weight:600;color:#111827;margin-bottom:10px;}
        .agenda-meta{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--gray);margin-bottom:8px;}
        .icon-meta{width:14px;height:14px;color:#9ca3af;}
        .agenda-actions{position:absolute;right:24px;bottom:20px;display:flex;gap:10px;}
        .btn-act{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:8px;font-size:13px;font-weight:500;text-decoration:none;border:1px solid;}
        .btn-edit{background:#fff;color:var(--gray);border-color:#ccc;}
        .btn-hapus{background:var(--red);color:#fff;border-color:var(--red);}
        
        /* Kalender Mini */
        .kalender-mini{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.08);}
        .mini-nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}
        .mini-nav button{background:none;border:none;cursor:pointer;color:var(--blue);font-size:18px;}
        .mini-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;font-size:13px;}
        .mini-grid .day{padding:6px 0;border-radius:6px;}
        .mini-grid .day.label{font-weight:600;color:var(--gray);}
        .mini-grid .day.active{background:var(--blue);color:#fff;font-weight:600;}
        
        /* Modal */
        .modal-overlay{
            position:fixed;top:0;left:0;right:0;bottom:0;
            background:rgba(0,0,0,0.6);z-index:1000;
            display:flex;justify-content:center;align-items:center;
            padding:20px;overflow-y:auto;
        }
        .modal-box{
            background:#fff;border-radius:12px;padding:40px;
            box-shadow:0 4px 24px rgba(0,0,0,0.15);
            max-width:700px;width:100%;max-height:90vh;overflow-y:auto;
            position:relative;
        }
        .btn-close{
            position:absolute;top:16px;right:20px;background:none;border:none;
            cursor:pointer;color:#6b7280;width:32px;height:32px;
            display:flex;align-items:center;justify-content:center;border-radius:50%;
            transition:all 0.2s;
        }
        .btn-close:hover{background:#f3f4f6;color:#374151;}
        
        /* Form - persis seperti pemesanan_ruang.php */
        .modal-box h3{font-size:18px;font-weight:600;margin-bottom:32px;color:#111827;}
        .form-group{margin-bottom:28px;}
        .form-group label{font-size:13px;font-weight:500;color:#444;margin-bottom:10px;display:block;}
        .input-wrapper input,
        .input-wrapper select,
        .input-wrapper textarea{
            width:100%;height:48px;padding:0 16px;border:1px solid #ccc;border-radius:8px;
            font-size:14px;transition:border-color 0.3s ease;
        }
        .input-wrapper input:focus,
        .input-wrapper select:focus,
        .input-wrapper textarea:focus{
            border-color:#1976d2;outline:none;box-shadow:0 0 0 3px rgba(25,118,210,0.15);
        }
        .input-wrapper select{
            -webkit-appearance:none;appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9l-4-4h8z'/%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right 12px center;background-size:12px;
        }
        .input-wrapper textarea{min-height:110px;height:auto;padding:14px 16px;resize:vertical;}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:36px 32px;}
        .form-action{text-align:right;margin-top:40px;}
        .btn{background:#1976d2;color:white;border:none;padding:12px 40px;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;}
        .btn:hover{background:#1565c0;}

        @media (max-width:1100px){
            .grid-2{grid-template-columns:1fr;gap:32px;}
            .modal-box{padding:32px;}
        }
        @media (max-width:768px){
            .content{margin-left:0;padding:90px 20px 20px;}
            .sidebar{display:none;}
            .header-left h1{display:none;}
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="header-inner">
        <div class="header-left">
            <img src="images/logodpad.png" alt="Logo">
            <h1>SISTEM AGENDA KUNJUNGAN TAMU</h1>
        </div>
        <div class="header-right">
            <img src="profile.jpg" alt="Profile">
            <span>Bidang <?= htmlspecialchars($bidang_login) ?></span>
        </div>
    </div>
</header>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="menu">
        <a href="beranda.php"><i data-feather="grid"></i>Beranda</a>
        <a href="daftar_agenda.php"><i data-feather="list"></i>Daftar Agenda</a>
        <a href="pemesanan_ruang.php"><i data-feather="calendar"></i>Pemesanan Ruang</a>
        <a class="active"><i data-feather="users"></i>Agenda Bidang Saya</a>
        <a href="riwayat_agenda.php"><i data-feather="clock"></i>Riwayat Agenda</a>
        <a href="profil.php"><i data-feather="user"></i>Profil Bidang</a>
    </div>
    <div class="logout"><i data-feather="log-out"></i>Keluar</div>
</aside>

<!-- Konten Agenda -->
<main class="content">
    <h2>Agenda Bidang Saya</h2>
    
    <form method="GET" class="filter-row">
        <input type="date" name="tgl" value="<?= $tgl ?>">
        <input type="text" name="cari" placeholder="Cari nama agenda…" value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit" class="btn-reset">Reset Filter</button>
    </form>

    <div class="cols">
        <div>
            <?php if (mysqli_num_rows($agenda) === 0): ?>
                <p style="text-align:center;color:var(--gray)">Belum ada agenda.</p>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($agenda)): ?>
                    <div class="agenda-box">
                        <div class="agenda-title"><?= htmlspecialchars($row['nama_kegiatan']) ?></div>
                        <div class="agenda-meta">
                            <i data-feather="calendar" class="icon-meta"></i>
                            <span><?= tanggalIndo($row['tanggal']) ?></span>
                            <span>•</span>
                            <i data-feather="clock" class="icon-meta"></i>
                            <span><?= substr($row['waktu_mulai'],0,5) ?> - <?= substr($row['waktu'],0,5) ?></span>
                            <span>•</span>
                            <i data-feather="home" class="icon-meta"></i>
                            <span><?= htmlspecialchars($row['nama_ruangan']) ?></span>
                        </div>
                        <div class="agenda-meta">
                            <span><?= htmlspecialchars($row['catatan'] ?? '') ?></span>
                        </div>
                        <div class="agenda-meta">
                            <span><?= $row['jumlah_peserta'] ?> peserta</span>
                        </div>
                        <div class="agenda-actions">
                            <a href="#" class="btn-act btn-edit"><i data-feather="edit-2"></i>Edit</a>
                            <a href="#" class="btn-act btn-hapus"><i data-feather="trash-2"></i>Hapus</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <div class="kalender-mini">
            <div class="mini-nav">
                <button>&lt;</button>
                <span><?= date('F Y') ?></span>
                <button>&gt;</button>
            </div>
            <div class="mini-grid">
                <?php 
                $hariIni = date('j');
                for($i=1; $i<=31; $i++): 
                    $class = ($i == $hariIni) ? 'day active' : 'day';
                ?>
                    <div class="<?= $class ?>"><?= $i ?></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</main>

<!-- Modal Edit -->
<div class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="btn-close" onclick="window.location='agenda_bidang.php'">
            <i data-feather="x"></i>
        </button>
        
        <h3>Edit Agenda</h3>
        
        <form method="POST" id="editForm">
            <div class="form-group">
                <label>Nama Kegiatan</label>
                <div class="input-wrapper">
                    <input type="text" name="nama_kegiatan" value="<?= htmlspecialchars($data['nama_kegiatan']) ?>" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Pilih Ruang</label>
                    <div class="input-wrapper">
                        <select name="id_ruangan" required>
                            <?php 
                            mysqli_data_seek($result_ruangan, 0);
                            while ($row = mysqli_fetch_assoc($result_ruangan)): ?>
                                <option value="<?= $row['id_ruangan'] ?>" <?= $data['id_ruangan'] == $row['id_ruangan'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['nama_ruangan']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tanggal</label>
                    <div class="input-wrapper">
                        <input type="date" name="tanggal" id="tanggal" value="<?= $data['tanggal'] ?>" required>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Hari</label>
                    <div class="input-wrapper">
                        <input type="text" name="hari" id="hari" value="<?= htmlspecialchars($data['hari']) ?>" readonly required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Jam Mulai</label>
                    <div class="input-wrapper">
                        <input type="time" name="waktu_mulai" value="<?= substr($data['waktu_mulai'], 0, 5) ?>" required>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Jam Selesai</label>
                    <div class="input-wrapper">
                        <input type="time" name="waktu_selesai" value="<?= substr($data['waktu'], 0, 5) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Jumlah Peserta</label>
                    <div class="input-wrapper">
                        <input type="number" name="jumlah_peserta" value="<?= $data['jumlah_peserta'] ?>" min="1" required>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Bidang</label>
                    <div class="input-wrapper">
                        <select name="bidang" required>
                            <option value="Sekretariat" <?= $data['bidang'] == 'Sekretariat' ? 'selected' : '' ?>>Sekretariat</option>
                            <option value="P2P" <?= $data['bidang'] == 'P2P' ? 'selected' : '' ?>>P2P</option>
                            <option value="PBPI" <?= $data['bidang'] == 'PBPI' ? 'selected' : '' ?>>PBPI</option>
                            <option value="PPSK" <?= $data['bidang'] == 'PPSK' ? 'selected' : '' ?>>PPSK</option>
                            <option value="PAS" <?= $data['bidang'] == 'PAS' ? 'selected' : '' ?>>PAS</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Nama Pemesan</label>
                    <div class="input-wrapper">
                        <input type="text" name="nama_pemesan" value="<?= htmlspecialchars($data['nama_pemesan']) ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Catatan</label>
                <div class="input-wrapper">
                    <textarea name="catatan" rows="4"><?= htmlspecialchars($data['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <input type="hidden" name="simpan" value="1">

            <div class="form-action">
                <button type="button" class="btn" onclick="simpanPerubahan()">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    feather.replace();

    document.getElementById('tanggal').addEventListener('change', function() {
        const tanggal = new Date(this.value);
        if (!isNaN(tanggal.getTime())) {
            const hariIndonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            document.getElementById('hari').value = hariIndonesia[tanggal.getDay()];
        }
    });

    if (document.getElementById('tanggal').value) {
        document.getElementById('tanggal').dispatchEvent(new Event('change'));
    }

    function simpanPerubahan() {
        const form = document.getElementById('editForm');
        let valid = true;
        
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) valid = false;
        });

        if (!valid) {
            Swal.fire({title: 'Error!', text: 'Harap isi semua bidang wajib!', icon: 'error'});
            return;
        }

        Swal.fire({
            title: "Berhasil!",
            text: "Perubahan berhasil disimpan",
            icon: "success",
            confirmButtonText: 'OK',
            confirmButtonColor: '#1976d2'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    }
</script>

</body>
</html>