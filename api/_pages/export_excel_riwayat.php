<?php
require_once __DIR__ . '/koneksi.php';
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$bidang = $_GET['bidang'] ?? '';
$bulan  = $_GET['bulan'] ?? '';
$tahun  = $_GET['tahun'] ?? '';

// Riwayat: agenda yang sudah lewat (sama dengan halaman riwayat_agenda.php)
$where = "WHERE ((a.tanggal < CURDATE()) OR (a.tanggal = CURDATE() AND a.waktu < CURTIME()))";
if ($bidang !== '') {
    $where .= " AND a.bidang = '" . mysqli_real_escape_string($koneksi, $bidang) . "'";
}
if ($tahun !== '') {
    $where .= " AND YEAR(a.tanggal) = '" . mysqli_real_escape_string($koneksi, $tahun) . "'";
}
if ($bulan !== '') {
    $where .= " AND MONTH(a.tanggal) = '" . mysqli_real_escape_string($koneksi, $bulan) . "'";
}

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
    ORDER BY a.tanggal DESC, a.waktu_mulai DESC
";
$result = mysqli_query($koneksi, $query);

if (!$result) {
    die("Query gagal: " . mysqli_error($koneksi));
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Riwayat_Agenda_Kunjungan_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
echo "Nama Kegiatan,Ruang,Hari,Tanggal,Jam,Bidang,Jumlah Peserta,Catatan\n";

while ($row = mysqli_fetch_assoc($result)) {
    $data = array_map(function ($value) {
        return '"' . str_replace('"', '""', (string) $value) . '"';
    }, $row);
    echo implode(',', $data) . "\n";
}

mysqli_close($koneksi);
exit;
