<?php
// Satu-satunya fungsi serverless. Semua halaman dimuat dari api/_pages/
// supaya tetap di bawah batas 12 fungsi paket Hobby Vercel.
$halaman = [
    'login', 'logout', 'beranda', 'daftar_agenda', 'pemesanan_ruang',
    'agenda_bidang', 'riwayat_agenda', 'edit_agenda', 'hapus_agenda',
    'profil', 'check_conflict', 'export_excel', 'export_excel_riwayat', 'export_pdf',
];

$p = $_GET['p'] ?? 'login';
if (!is_string($p) || !in_array($p, $halaman, true)) {
    http_response_code(404);
    echo 'Halaman tidak ditemukan.';
    exit;
}

// buang parameter internal agar tidak ikut terbaca oleh halaman
unset($_GET['p'], $_REQUEST['p']);

require __DIR__ . '/_pages/' . $p . '.php';
