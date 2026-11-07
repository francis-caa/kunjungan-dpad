<?php
include "koneksi.php";

$id = $_GET['id'] ?? 0;

if ($id <= 0) {
    header("Location: agenda_bidang.php");
    exit;
}

// Cek apakah data ada (opsional, tapi bagus untuk keamanan)
$cek = $koneksi->prepare("SELECT id_kegiatan FROM agenda_kunjungan WHERE id_kegiatan = ?");
$cek->bind_param("i", $id);
$cek->execute();
$result = $cek->get_result();

if ($result->num_rows === 0) {
    echo "<script>window.location='agenda_bidang.php';</script>";
    exit;
}
$cek->close();

// Proses hapus via POST (dipanggil via AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hapus = $koneksi->prepare("DELETE FROM agenda_kunjungan WHERE id_kegiatan = ?");
    $hapus->bind_param("i", $id);

    if ($hapus->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Agenda berhasil dihapus.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus agenda: ' . $hapus->error]);
    }
    $hapus->close();
    exit;
}

mysqli_close($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Agenda</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f5f7fb;
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body>

<script>
// Langsung tampilkan konfirmasi saat halaman dibuka
Swal.fire({
    title: "Apakah Anda yakin?",
    text: "Data agenda akan dihapus permanen!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    cancelButtonColor: "#6b7280",
    confirmButtonText: "Ya, hapus!",
    cancelButtonText: "Batal"
}).then((result) => {
    if (result.isConfirmed) {
        // Kirim request hapus via AJAX
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'hapus=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    title: "Terhapus!",
                    text: data.message,
                    icon: "success",
                    confirmButtonColor: "#1976d2"
                }).then(() => {
                    window.location = 'agenda_bidang.php';
                });
            } else {
                Swal.fire({
                    title: "Gagal!",
                    text: data.message,
                    icon: "error",
                    confirmButtonColor: "#1976d2"
                }).then(() => {
                    window.location = 'agenda_bidang.php';
                });
            }
        })
        .catch(error => {
            Swal.fire({
                title: "Error!",
                text: "Terjadi kesalahan saat menghapus data.",
                icon: "error"
            }).then(() => {
                window.location = 'agenda_bidang.php';
            });
        });
    } else {
        // Batal → langsung kembali
        window.location = 'agenda_bidang.php';
    }
});
</script>

</body>
</html>