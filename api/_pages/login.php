<?php
require_once __DIR__ . '/session_boot.php';
session_start();
include "koneksi.php";

$error = '';

if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = trim($_POST['password']); // trim untuk hilangkan spasi ekstra

    // Query dengan prepared statement lebih aman (hindari injection)
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM admin WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 1) {
        $data = mysqli_fetch_assoc($result);

        // Bandingkan langsung (plaintext)
        if ($password === $data['password']) {
            $_SESSION['id_admin']   = $data['id_admin'];
            $_SESSION['nama_admin'] = $data['nama_admin'];
            header("Location: beranda.php");
            exit;
        } else {
            $error = "Username atau password salah!";
        }
    } else {
        $error = "Username atau password salah!";
    }

    // Tutup statement
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            background: #fff;
        }
        
        .login-left {
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 80px;
            background: #fff;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            margin-bottom: 32px;
        }
        
        .login-header h1 {
            font-size: 36px;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 8px;
        }
        
        .login-header p {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #2563eb;
            margin-bottom: 6px;
            margin-left: 4px;
        }
        
        .input-wrapper {
            position: relative;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            transition: all 0.3s;
        }
        
        .input-wrapper:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .input-wrapper i {
            color: #6b7280;
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        .input-wrapper input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 14px;
            color: #374151;
            background: transparent;
        }
        
        .input-wrapper input::placeholder {
            color: #9ca3af;
        }
        
        .toggle-password {
            cursor: pointer;
            color: #6b7280;
            flex-shrink: 0;
        }
        
        .toggle-password:hover {
            color: #374151;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }
        
        .btn-login:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .login-right {
            width: 50%;
            position: relative;
            overflow: hidden;
            background: #fff;
        }
        
        .login-right::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,0.1) 100%);
            z-index: 1;
        }
        
        .image-container {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border-top-left-radius: 60px;
            border-bottom-left-radius: 60px;
            overflow: hidden;
        }
        
        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .login-left { width: 100%; min-height: 100vh; padding: 40px; }
            .login-right { display: none; }
        }
    </style>
</head>
<body>
    <div class="login-left">
        <div class="login-container">
            <div class="login-header">
                <h1>Selamat Datang!</h1>
                <p>Masuk untuk melakukan pemesanan agenda kunjungan.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i data-feather="alert-circle" style="width: 16px; height: 16px;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <div class="input-wrapper">
                        <i data-feather="user"></i>
                        <input type="text" name="username" placeholder="Masukkan username" required autocomplete="off">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrapper">
                        <i data-feather="lock"></i>
                        <input type="password" name="password" id="password" placeholder="Masukkan password" required autocomplete="off">
                        <i data-feather="eye-off" class="toggle-password" id="toggleIcon" onclick="togglePassword()"></i>
                    </div>
                </div>
                
                <button type="submit" name="login" class="btn-login">Masuk</button>
            </form>
        </div>
    </div>
    
    <div class="login-right">
        <div class="image-container">
            <img src="images/gedung.jpg" alt="Gedung Depo Arsip">
        </div>
    </div>
    
    <script>
        feather.replace();
        
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.setAttribute('data-feather', 'eye');
            } else {
                passwordInput.type = 'password';
                toggleIcon.setAttribute('data-feather', 'eye-off');
            }
            
            feather.replace();
        }
    </script>
</body>
</html>