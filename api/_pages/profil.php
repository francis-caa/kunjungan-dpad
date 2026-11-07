<?php
require_once __DIR__ . '/session_boot.php';
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['id_admin'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/koneksi.php';
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

date_default_timezone_set("Asia/Jakarta");

$id_admin = $_SESSION['id_admin'];

// Ambil data admin
$stmt = mysqli_prepare($koneksi, "SELECT * FROM admin WHERE id_admin = ?");
mysqli_stmt_bind_param($stmt, "i", $id_admin);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profil = mysqli_fetch_assoc($result);

$nama_admin  = $profil['nama_admin']  ?? 'Bidang Sekretariat';
$username    = $profil['username']    ?? 'sekretariat';
$foto_profil = !empty($profil['foto']) ? $profil['foto'] : 'profile.jpg';

$success = '';
$error   = '';

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Proses upload foto profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto']) && !isset($_POST['update_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token keamanan tidak valid!";
    } else {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $file_name   = time() . "_" . basename($_FILES["foto"]["name"]);
        $target_file = $target_dir . $file_name;
        $ext         = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["foto"]["tmp_name"]);
        if ($check !== false) {
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                    $stmt_up = mysqli_prepare($koneksi, "UPDATE admin SET foto = ? WHERE id_admin = ?");
                    mysqli_stmt_bind_param($stmt_up, "si", $target_file, $id_admin);
                    if (mysqli_stmt_execute($stmt_up)) {
                        $foto_profil = $target_file;
                        $_SESSION['success'] = "Foto profil berhasil diubah!";
                        header("Location: profil.php");
                        exit;
                    } else {
                        $error = "Gagal menyimpan foto ke database.";
                    }
                } else {
                    $error = "Gagal mengunggah file.";
                }
            } else {
                $error = "Hanya format jpg, jpeg, png, gif yang diperbolehkan.";
            }
        } else {
            $error = "File bukan gambar yang valid.";
        }
    }
}

// Proses ubah password (PLAIN TEXT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token keamanan tidak valid!";
    } else {
        $password_lama     = trim($_POST['password_lama'] ?? '');
        $password_baru     = trim($_POST['password_baru'] ?? '');
        $konfirmasi_password = trim($_POST['konfirmasi_password'] ?? '');

        // Ambil password saat ini dari database
        $stmt_pass = mysqli_prepare($koneksi, "SELECT password FROM admin WHERE id_admin = ?");
        mysqli_stmt_bind_param($stmt_pass, "i", $id_admin);
        mysqli_stmt_execute($stmt_pass);
        $result_pass = mysqli_stmt_get_result($stmt_pass);
        $data = mysqli_fetch_assoc($result_pass);
        $stored_password = trim($data['password'] ?? '');

        if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
            $error = "Semua kolom kata sandi harus diisi!";
        } elseif ($password_lama !== $stored_password) {
            $error = "Kata sandi lama salah!";
        } elseif ($password_baru !== $konfirmasi_password) {
            $error = "Konfirmasi kata sandi tidak cocok!";
        } elseif (strlen($password_baru) < 6) {
            $error = "Kata sandi baru minimal 6 karakter!";
        } else {
            // Simpan password baru (plaintext)
            $stmt_update = mysqli_prepare($koneksi, "UPDATE admin SET password = ? WHERE id_admin = ?");
            mysqli_stmt_bind_param($stmt_update, "si", $password_baru, $id_admin);

            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['success'] = "Kata sandi berhasil diubah! Gunakan kata sandi baru untuk login berikutnya.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // refresh token
                header("Location: profil.php");
                exit;
            } else {
                $error = "Gagal menyimpan kata sandi baru: " . mysqli_error($koneksi);
            }
        }
    }
}

// Ambil pesan sukses (flash message)
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

mysqli_close($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Bidang</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { background:#f5f7fb; }

        .header {
            position:fixed; top:0; left:0; right:0; height:70px; background:#fff;
            border-bottom:1px solid #e5e7eb; z-index:1000; display:flex;
            align-items:center; justify-content:space-between; padding:0 24px;
        }
        .header-left { display:flex; align-items:center; gap:12px; }
        .header-left img { height:42px; }
        .header-left h1 { font-size:16px; font-weight:600; color:#111827; }
        .header-right { display:flex; align-items:center; gap:10px; }
        .header-right img { width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid #e5e7eb; }
        .header-right span { font-size:14px; color:#374151; font-weight:500; }

        .sidebar {
            position:fixed; top:70px; left:0; width:240px; height:calc(100vh - 70px);
            background:#fff; border-right:1px solid #e5e7eb; padding:20px 14px; z-index:999;
        }
        .menu a {
            display:flex; align-items:center; gap:12px; padding:10px 14px;
            border-radius:10px; color:#6b7280; text-decoration:none; margin-bottom:6px;
            font-size:14px; font-weight:500; transition:all 0.2s;
        }
        .menu a:hover { background:#f3f4f6; }
        .menu a.active { background:#e8f0ff; color:#2563eb; font-weight:600; }
        .menu a i { width:20px; height:20px; }

        .logout {
            position:absolute; bottom:20px; left:14px; right:14px; color:#ef4444;
            display:flex; align-items:center; gap:10px; cursor:pointer;
            font-weight:500; font-size:14px; padding:10px 14px; border-radius:10px;
            transition:all 0.2s; text-decoration:none !important;
        }
        .logout:hover, .logout:focus, .logout:active {
            background:#fef2f2; text-decoration:none !important; color:#dc2626;
        }

        .content { margin-left:240px; padding:90px 32px 32px; }
        h2 { font-size:24px; font-weight:600; color:#111827; margin-bottom:30px; }

        .profile-wrapper { display:flex; flex-direction:column; align-items:center; margin-bottom:30px; }
        .profile-photo { width:100px; height:100px; border-radius:50%; object-fit:cover; margin-bottom:12px; border:3px solid #e5e7eb; background:#f3f4f6; }
        .btn-upload {
            display:inline-flex; align-items:center; gap:6px; padding:8px 16px;
            border:1px solid #d1d5db; border-radius:8px; background:#fff; color:#374151;
            font-size:14px; font-weight:500; cursor:pointer; transition:all 0.2s;
        }
        .btn-upload:hover { background:#f9fafb; border-color:#9ca3af; }

        .cards-wrapper { display:flex; justify-content:center; }
        .cards-container { display:grid; grid-template-columns:400px 400px; gap:20px; width:100%; max-width:820px; }
        .card { background:#fff; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
        .card h3 { font-size:16px; font-weight:600; color:#111827; margin-bottom:20px; }

        .form-group { margin-bottom:16px; }
        label { display:block; font-size:14px; font-weight:500; color:#374151; margin-bottom:6px; }
        .form-control {
            width:100%; height:42px; padding:0 12px; border:1px solid #d1d5db;
            border-radius:8px; font-size:14px; color:#111827; background:#fff;
            transition:border-color 0.2s;
        }
        .form-control:focus { outline:none; border-color:#2563eb; }
        .form-control[readonly] { background:#f9fafb; color:#6b7280; }

        .security-text { font-size:14px; color:#6b7280; margin-bottom:16px; line-height:1.5; }
        .btn-primary {
            background:#2563eb; color:#fff; border:none; padding:10px 20px;
            border-radius:8px; font-size:14px; font-weight:500; cursor:pointer;
            transition:all 0.2s;
        }
        .btn-primary:hover { background:#1d4ed8; }

        .modal-overlay {
            display:none; position:fixed; top:0; left:0; right:0; bottom:0;
            background:rgba(0,0,0,0.5); z-index:2000; justify-content:center;
            align-items:center; opacity:0; transition:opacity 0.3s ease;
        }
        .modal-overlay.active { display:flex; opacity:1; }
        .modal {
            background:#fff; border-radius:12px; width:90%; max-width:400px;
            box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); transform:scale(0.9);
            transition:transform 0.3s ease;
        }
        .modal-overlay.active .modal { transform:scale(1); }
        .modal-header { padding:20px 20px 0; display:flex; justify-content:space-between; align-items:center; }
        .modal-title { font-size:18px; font-weight:600; color:#111827; }
        .modal-close { background:none; border:none; cursor:pointer; color:#9ca3af; padding:4px; transition:color 0.2s; }
        .modal-close:hover { color:#6b7280; }
        .modal-body { padding:12px 20px 20px; }
        .modal-desc { font-size:14px; color:#6b7280; margin-bottom:20px; }
        .modal-footer { padding:0 20px 20px; display:flex; justify-content:flex-end; gap:10px; }

        .password-wrapper { position:relative; }
        .password-wrapper input {
            width:100%; height:42px; padding:0 40px 0 12px; border:1px solid #d1d5db;
            border-radius:8px; font-size:14px; transition:border-color 0.2s;
        }
        .password-wrapper input:focus { outline:none; border-color:#2563eb; }
        .toggle-eye {
            position:absolute; right:12px; top:50%; transform:translateY(-50%);
            background:none; border:none; cursor:pointer; color:#9ca3af; padding:0;
            transition:color 0.2s;
        }
        .toggle-eye:hover { color:#6b7280; }
        .hint { font-size:12px; color:#6b7280; margin-top:4px; }

        .btn-secondary {
            background:#fff; color:#374151; border:1px solid #d1d5db;
            padding:10px 20px; border-radius:8px; font-size:14px; font-weight:500;
            cursor:pointer; transition:all 0.2s;
        }
        .btn-secondary:hover { background:#f9fafb; }

        #fileInput { display:none; }

        .loading {
            display:none; position:fixed; top:0; left:0; right:0; bottom:0;
            background:rgba(255,255,255,0.8); z-index:3000; justify-content:center;
            align-items:center;
        }
        .loading.active { display:flex; }
        .spinner {
            width:40px; height:40px; border:4px solid #e5e7eb; border-top-color:#2563eb;
            border-radius:50%; animation:spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }

        @media (max-width:900px) {
            .cards-container { grid-template-columns:1fr; max-width:400px; }
            .sidebar { transform:translateX(-100%); }
            .content { margin-left:0; }
        }
    </style>
</head>
<body>

<div class="loading" id="loadingOverlay"><div class="spinner"></div></div>

<header class="header">
    <div class="header-left">
        <img src="images/logodpad.png" alt="Logo">
        <h1>SISTEM AGENDA KUNJUNGAN TAMU</h1>
    </div>
    <div class="header-right">
        <img src="<?= htmlspecialchars($foto_profil) ?>?v=<?= time() ?>" alt="Profile" id="headerPhoto">
        <span><?= htmlspecialchars($nama_admin) ?></span>
    </div>
</header>

<aside class="sidebar">
    <div class="menu">
        <a href="beranda.php"><i data-feather="grid"></i>Beranda</a>
        <a href="daftar_agenda.php"><i data-feather="list"></i>Daftar Agenda</a>
        <a href="pemesanan_ruang.php"><i data-feather="calendar"></i>Pemesanan Ruang</a>
        <a href="agenda_bidang.php"><i data-feather="users"></i>Agenda Bidang Saya</a>
        <a href="riwayat_agenda.php"><i data-feather="clock"></i>Riwayat Agenda</a>
        <a class="active" href="profil.php"><i data-feather="user"></i>Profil Bidang</a>
    </div>
    <a href="logout.php" class="logout" onclick="return confirmLogout(event)">
        <i data-feather="log-out"></i>Keluar
    </a>
</aside>

<main class="content">
    <h2>Profil Bidang</h2>

    <div class="profile-wrapper">
        <img src="<?= htmlspecialchars($foto_profil) ?>?v=<?= time() ?>" alt="Foto Profil" class="profile-photo" id="mainPhoto">
        
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="file" name="foto" id="fileInput" accept="image/*" onchange="uploadPhoto()">
            <button type="button" class="btn-upload" onclick="document.getElementById('fileInput').click()">
                <i data-feather="upload"></i> Ubah Foto Profil
            </button>
        </form>
    </div>

    <div class="cards-wrapper">
        <div class="cards-container">
            <div class="card">
                <h3>Informasi Akun</h3>
                <div class="form-group">
                    <label>Nama Bidang</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($nama_admin) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
                </div>
            </div>

            <div class="card">
                <h3>Pengaturan Keamanan</h3>
                <p class="security-text">Perbarui kata sandi Anda untuk menjaga keamanan akun.</p>
                <button class="btn-primary" onclick="openModal()">Ubah Kata Sandi</button>
            </div>
        </div>
    </div>
</main>

<div class="modal-overlay" id="passwordModal">
    <div class="modal">
        <div class="modal-header">
            <h4 class="modal-title">Ubah Kata Sandi</h4>
            <button class="modal-close" onclick="closeModal()">
                <i data-feather="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="modal-desc">Masukkan kata sandi lama dan kata sandi baru Anda.</p>
            
            <form method="POST" id="passwordForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="update_password" value="1">
                
                <div class="form-group">
                    <label>Kata Sandi Sekarang</label>
                    <div class="password-wrapper">
                        <input type="password" name="password_lama" id="passLama" required placeholder="Masukkan kata sandi lama">
                        <button type="button" class="toggle-eye" onclick="togglePass('passLama', this)">
                            <i data-feather="eye-off"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Kata Sandi Baru</label>
                    <div class="password-wrapper">
                        <input type="password" name="password_baru" id="passBaru" required minlength="6" placeholder="Minimal 6 karakter">
                        <button type="button" class="toggle-eye" onclick="togglePass('passBaru', this)">
                            <i data-feather="eye-off"></i>
                        </button>
                    </div>
                    <p class="hint">Minimal 6 karakter.</p>
                </div>
                
                <div class="form-group">
                    <label>Konfirmasi Kata Sandi</label>
                    <div class="password-wrapper">
                        <input type="password" name="konfirmasi_password" id="passKonfirm" required placeholder="Ulangi kata sandi baru">
                        <button type="button" class="toggle-eye" onclick="togglePass('passKonfirm', this)">
                            <i data-feather="eye-off"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
            <button type="button" class="btn-primary" onclick="submitPassword()">Simpan Perubahan</button>
        </div>
    </div>
</div>

<script>
feather.replace();

function uploadPhoto() {
    const fileInput = document.getElementById('fileInput');
    const file = fileInput.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        Swal.fire({ title:"Gagal!", text:"Ukuran file maksimal 2MB.", icon:"error", confirmButtonColor:"#2563eb" });
        fileInput.value = '';
        return;
    }

    const allowed = ['image/jpeg','image/jpg','image/png','image/gif'];
    if (!allowed.includes(file.type)) {
        Swal.fire({ title:"Gagal!", text:"Hanya JPG, PNG, GIF yang diperbolehkan.", icon:"error", confirmButtonColor:"#2563eb" });
        fileInput.value = '';
        return;
    }

    document.getElementById('loadingOverlay').classList.add('active');
    document.getElementById('uploadForm').submit();
}

function openModal() {
    const modal = document.getElementById('passwordModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    feather.replace();
    setTimeout(() => document.getElementById('passLama').focus(), 100);
}

function closeModal() {
    const modal = document.getElementById('passwordModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('passwordForm').reset();
    document.querySelectorAll('.toggle-eye i').forEach(i => i.setAttribute('data-feather','eye-off'));
    feather.replace();
}

function togglePass(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-feather', 'eye');
    } else {
        input.type = 'password';
        icon.setAttribute('data-feather', 'eye-off');
    }
    feather.replace();
}

function submitPassword() {
    const lama = document.getElementById('passLama').value.trim();
    const baru = document.getElementById('passBaru').value;
    const konf = document.getElementById('passKonfirm').value;

    if (!lama || !baru || !konf) {
        Swal.fire({ title:"Perhatian!", text:"Semua kolom harus diisi!", icon:"warning", confirmButtonColor:"#2563eb" });
        return;
    }
    if (baru !== konf) {
        Swal.fire({ title:"Gagal!", text:"Konfirmasi kata sandi tidak cocok!", icon:"error", confirmButtonColor:"#2563eb" });
        return;
    }
    if (baru.length < 6) {
        Swal.fire({ title:"Gagal!", text:"Kata sandi baru minimal 6 karakter!", icon:"error", confirmButtonColor:"#2563eb" });
        return;
    }
    if (lama === baru) {
        Swal.fire({ title:"Perhatian!", text:"Kata sandi baru tidak boleh sama dengan yang lama!", icon:"warning", confirmButtonColor:"#2563eb" });
        return;
    }

    Swal.fire({
        title: 'Menyimpan...',
        text: 'Mohon tunggu sebentar',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading()
    });

    document.getElementById('passwordForm').submit();
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
    }).then(result => {
        if (result.isConfirmed) window.location.href = 'login.php';
    });
    return false;
}

document.getElementById('passwordModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

<?php if ($success): ?>
document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
        title: "Berhasil!",
        text: <?= json_encode($success) ?>,
        icon: "success",
        confirmButtonColor: "#2563eb",
        timer: 4000
    });
});
<?php endif; ?>

<?php if ($error): ?>
document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
        title: "Gagal",
        text: <?= json_encode($error) ?>,
        icon: "error",
        confirmButtonColor: "#2563eb"
    });
});
<?php endif; ?>
</script>

</body>
</html>