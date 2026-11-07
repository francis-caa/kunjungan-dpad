<?php
// Koneksi database. Di Vercel dibaca dari Environment Variables,
// di XAMPP lokal otomatis memakai pengaturan bawaan.
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    $db_name = getenv('DB_NAME') ?: 'kunjungan';
    $db_port = (int) (getenv('DB_PORT') ?: 3306);
    $db_ssl  = getenv('DB_SSL') === '1';

    mysqli_report(MYSQLI_REPORT_OFF);
    $koneksi = mysqli_init();
    $flags = 0;
    if ($db_ssl) {
        mysqli_options($koneksi, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $flags = MYSQLI_CLIENT_SSL;
    }

    if (!@mysqli_real_connect($koneksi, $db_host, $db_user, $db_pass, $db_name, $db_port, null, $flags)) {
        die("Koneksi database gagal: " . mysqli_connect_error());
    }
    mysqli_set_charset($koneksi, 'utf8mb4');
    // kembalikan ke perilaku bawaan PHP 8.1+ (error query dilempar sebagai exception)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}
?>
