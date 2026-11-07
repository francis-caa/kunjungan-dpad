<?php
require_once __DIR__ . '/session_boot.php';
$koneksi = mysqli_connect("localhost", "root", "", "kunjungan");
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
date_default_timezone_set("Asia/Jakarta");
session_start();
$id_admin = $_SESSION['id_admin'] ?? 1;  

// Ambil data admin untuk foto profil
$query_admin = "SELECT foto, nama_admin FROM admin WHERE id_admin = $id_admin";
$result_admin = mysqli_query($koneksi, $query_admin);
$data_admin = mysqli_fetch_assoc($result_admin);
$foto_profil = !empty($data_admin['foto']) ? $data_admin['foto'] : 'profile.jpg';
$nama_bidang_header = $data_admin['nama_admin'] ?? 'Bidang Sekretariat';

$query_ruangan = "SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC";
$result_ruangan = mysqli_query($koneksi, $query_ruangan);

// Proses simpan
$success = false;
$error = '';
if (isset($_POST['simpan'])) {
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

    $today = date('Y-m-d');
    $now   = date('H:i:s');

    // Validasi 1: Tanggal tidak boleh di masa lalu
    if ($tanggal < $today) {
        $error = "Tanggal sudah lewat! Tidak bisa memesan untuk tanggal sebelum hari ini.";
    }
    // Validasi 2: Jika tanggal hari ini, waktu mulai tidak boleh sudah lewat
    elseif ($tanggal == $today && $waktu_mulai < $now) {
        $error = "Waktu mulai sudah terlewat pada hari ini!";
    }
    else {
        // Cek bentrok ruangan
        $cek = $koneksi->prepare("
            SELECT COUNT(*) AS bentrok 
            FROM agenda_kunjungan 
            WHERE id_ruangan = ? 
              AND tanggal = ? 
              AND (
                  (waktu_mulai <= ? AND waktu >= ?) OR 
                  (waktu_mulai >= ? AND waktu_mulai < ?) OR 
                  (waktu > ? AND waktu <= ?)
              )
        ");
        $cek->bind_param("isssssss", 
            $id_ruangan, $tanggal, 
            $waktu, $waktu_mulai, 
            $waktu_mulai, $waktu, 
            $waktu_mulai, $waktu
        );
        $cek->execute();
        $result_cek = $cek->get_result();
        $row_cek = $result_cek->fetch_assoc();

        if ($row_cek['bentrok'] > 0) {
            $error = "Ruangan sudah dipesan pada jam tersebut!";
        } else {
            $stmt = $koneksi->prepare("
                INSERT INTO agenda_kunjungan
                (id_ruangan, id_admin, nama_kegiatan, hari, tanggal, waktu_mulai, waktu, jumlah_peserta, bidang, nama_pemesan, catatan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "iisssssisss",
                $id_ruangan, $id_admin, $nama_kegiatan, $hari, $tanggal,
                $waktu_mulai, $waktu, $jumlah_peserta, $bidang, $nama_pemesan, $catatan
            );
            
            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = $stmt->error;
            }
            $stmt->close();
        }
        $cek->close();
    }
}

mysqli_close($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemesanan Ruang Rapat</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { background:#f5f7fb; }

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
        .header-left { display: flex; align-items: center; gap: 12px; }
        .header-left img { height: 42px; }
        .header-left h1 { font-size: 16px; font-weight: 600; color: #374151; }
        .header-right { display: flex; align-items: center; gap: 10px; }
        .header-right img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb; }
        .header-right span { font-size: 14px; color: #374151; font-weight: 500; }

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
        .menu a:hover { background: #f3f4f6; }
        .menu a.active { background: #e8f0ff; color: #2563eb; font-weight: 600; }
        .menu a i { width: 20px; height: 20px; }

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
        h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            max-width: 1100px;
            margin: 0 auto;
        }
        .form-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 32px;
        }
        .form-group {
            margin-bottom: 28px;
        }
        label {
            font-size: 13px;
            font-weight: 500;
            color: #444;
            margin-bottom: 10px;
            display: block;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper input,
        .input-wrapper select,
        .input-wrapper textarea {
            width: 100%;
            height: 48px;
            padding: 0 16px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .input-wrapper input:focus,
        .input-wrapper select:focus,
        .input-wrapper textarea:focus {
            border-color: #1976d2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(25,118,210,0.15);
        }
        .input-wrapper select {
            -webkit-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9l-4-4h8z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
        }
        .input-wrapper textarea {
            min-height: 110px;
            height: auto;
            padding: 14px 16px;
            resize: vertical;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 36px 32px;
        }
        .form-action {
            text-align: right;
            margin-top: 40px;
        }
        .btn {
            background: #1976d2;
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
        }
        .btn:hover {
            background: #1565c0;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        #conflict-message {
            color: #dc3545;
            font-size: 14px;
            margin-top: 8px;
            display: none;
        }
        @media (max-width: 1100px) {
            .grid-2 { grid-template-columns: 1fr; gap: 32px; }
            .content { padding: 90px 30px 40px; }
            .form-card { padding: 32px; }
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
        <a class="active" href="pemesanan_ruang.php"><i data-feather="calendar"></i>Pemesanan Ruang</a>
        <a href="agenda_bidang.php"><i data-feather="users"></i>Agenda Bidang Saya</a>
        <a href="riwayat_agenda.php"><i data-feather="clock"></i>Riwayat Agenda</a>
        <a href="profil.php"><i data-feather="user"></i>Profil Bidang</a>
    </div>
    <a href="logout.php" class="logout" onclick="return confirmLogout(event)">
        <i data-feather="log-out"></i>Keluar
    </a>
</aside>

<main class="content">
    <h2>Pemesanan Ruang Rapat</h2>
    <div class="form-card">
        <form method="POST" id="pesanForm">
            <div class="form-group">
                <label>Nama Kegiatan</label>
                <div class="input-wrapper">
                    <input type="text" name="nama_kegiatan" required placeholder="">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Pilih Ruang</label>
                    <div class="input-wrapper">
                        <select name="id_ruangan" id="id_ruangan" required>
                            <?php while ($row = mysqli_fetch_assoc($result_ruangan)): ?>
                                <option value="<?= $row['id_ruangan'] ?>"><?= htmlspecialchars($row['nama_ruangan']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tanggal</label>
                    <div class="input-wrapper">
                        <input type="date" name="tanggal" id="tanggal" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Hari</label>
                    <div class="input-wrapper">
                        <input type="text" name="hari" id="hari" readonly required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Jam Mulai</label>
                    <div class="input-wrapper">
                        <input type="time" name="waktu_mulai" id="waktu_mulai" required>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Jam Selesai</label>
                    <div class="input-wrapper">
                        <input type="time" name="waktu_selesai" id="waktu_selesai" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Jumlah Peserta</label>
                    <div class="input-wrapper">
                        <input type="number" name="jumlah_peserta" min="1" required>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Bidang</label>
                    <div class="input-wrapper">
                        <select name="bidang" required>
                            <option value="Sekretariat">Sekretariat</option>
                            <option value="P2P">P2P</option>
                            <option value="PBPI">PBPI</option>
                            <option value="PPSK">PPSK</option>
                            <option value="PAS">PAS</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Nama Pemesan</label>
                    <div class="input-wrapper">
                        <input type="text" name="nama_pemesan" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Catatan</label>
                <div class="input-wrapper">
                    <textarea name="catatan" rows="4"></textarea>
                </div>
            </div>

            <div id="conflict-message"></div>

            <input type="hidden" name="simpan" value="1">

            <div class="form-action">
                <button type="submit" class="btn" id="submitBtn" onclick="return validateForm()">Simpan Pemesanan</button>
            </div>
        </form>
    </div>
</main>

<script>
    feather.replace();

    // Update hari otomatis
    document.getElementById('tanggal').addEventListener('change', function() {
        const tanggal = new Date(this.value);
        if (!isNaN(tanggal)) {
            const hariIndonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            document.getElementById('hari').value = hariIndonesia[tanggal.getDay()];
        } else {
            document.getElementById('hari').value = '';
        }
        checkConflict();
    });

    // Cek bentrok ruangan secara real-time (opsional, tetap dipertahankan)
    const inputsToCheck = ['id_ruangan', 'tanggal', 'waktu_mulai', 'waktu_selesai'];
    inputsToCheck.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', checkConflict);
    });

    async function checkConflict() {
        // Fungsi ini bisa dipertahankan untuk cek bentrok ruangan via AJAX jika ada file check_conflict.php
        // Untuk sekarang, kita fokus ke validasi waktu di PHP
        document.getElementById('conflict-message').style.display = 'none';
    }

    function validateForm() {
        const form = document.getElementById('pesanForm');
        let valid = true;

        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.style.borderColor = '#dc3545';
            } else {
                field.style.borderColor = '#ccc';
            }
        });

        if (!valid) {
            Swal.fire({
                title: 'Perhatian!',
                text: 'Harap isi semua bidang yang wajib!',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return false;
        }

        return true;
    }

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

<?php if ($success): ?>
<script>
    Swal.fire({
        title: "Berhasil!",
        text: "Pemesanan berhasil disimpan",
        icon: "success",
        confirmButtonText: 'OK'
    }).then(() => {
        window.location = 'daftar_agenda.php';
    });
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
    Swal.fire({
        title: "Gagal!",
        text: "<?= htmlspecialchars($error) ?>",
        icon: "error",
        confirmButtonText: 'OK'
    });
</script>
<?php endif; ?>

</body>
</html>