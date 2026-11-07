<?php
header('Content-Type: application/json');

$koneksi = mysqli_connect("localhost", "root", "", "kunjungan");
if (!$koneksi) {
    echo json_encode(['bentrok' => 0]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_conflict') {
    $id_ruangan   = (int)$_POST['id_ruangan'];
    $tanggal      = $_POST['tanggal'];
    $waktu_mulai  = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];

    $query = "SELECT COUNT(*) AS bentrok 
              FROM agenda_kunjungan 
              WHERE id_ruangan = ? 
                AND tanggal = ? 
                AND (
                    (waktu_mulai <= ? AND waktu >= ?) OR 
                    (waktu_mulai >= ? AND waktu_mulai < ?) OR 
                    (waktu > ? AND waktu <= ?)
                )";

    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("isssssss", $id_ruangan, $tanggal, $waktu_selesai, $waktu_mulai, $waktu_mulai, $waktu_selesai, $waktu_mulai, $waktu_selesai);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode(['bentrok' => (int)$row['bentrok']]);
    $stmt->close();
}

mysqli_close($koneksi);
?>