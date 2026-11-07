<?php


$koneksi = mysqli_connect("localhost", "root", "", "kunjungan");
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$keyword = $_GET['keyword'] ?? '';
$bulan   = $_GET['bulan'] ?? '';
$tahun   = $_GET['tahun'] ?? '';

$where = "WHERE 1=1";
if ($keyword !== '') {
    $where .= " AND a.nama_kegiatan LIKE '%" . mysqli_real_escape_string($koneksi, $keyword) . "%'";
}
if ($bulan !== '') {
    $where .= " AND MONTH(a.tanggal) = '" . mysqli_real_escape_string($koneksi, $bulan) . "'";
}
if ($tahun !== '') {
    $where .= " AND YEAR(a.tanggal) = '" . mysqli_real_escape_string($koneksi, $tahun) . "'";
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
    ORDER BY a.tanggal, a.waktu_mulai
";
$result = mysqli_query($koneksi, $query);

if (!$result) {
    die("Query gagal: " . mysqli_error($koneksi));
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Daftar_Agenda_Kunjungan_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
echo "Nama Kegiatan,Ruang,Hari,Tanggal,Jam,Bidang,Jumlah Peserta,Catatan\n";

while ($row = mysqli_fetch_assoc($result)) {

    $data = array_map(function($value) {
        return '"' . str_replace('"', '""', $value) . '"';
    }, $row);
    echo implode(',', $data) . "\n";
}

mysqli_close($koneksi);
exit;
?>