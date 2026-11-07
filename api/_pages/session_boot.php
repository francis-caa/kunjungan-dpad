<?php
// Menyimpan session PHP di database supaya login tetap berlaku di Vercel (serverless).
// Cukup didaftarkan sekali; session_start() di tiap halaman tetap dipakai seperti biasa.
require_once __DIR__ . '/koneksi.php';

class DbSessionHandler implements SessionHandlerInterface
{
    private $db;
    public function __construct($db) { $this->db = $db; }

    private function ensureTable(): void {
        mysqli_query($this->db, "CREATE TABLE IF NOT EXISTS php_sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data MEDIUMTEXT NOT NULL,
            updated_at INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false {
        try {
            $stmt = mysqli_prepare($this->db, "SELECT data FROM php_sessions WHERE id = ?");
        } catch (mysqli_sql_exception $e) {
            $this->ensureTable();
            $stmt = mysqli_prepare($this->db, "SELECT data FROM php_sessions WHERE id = ?");
        }
        mysqli_stmt_bind_param($stmt, "s", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $data);
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $found ? (string) $data : '';
    }

    public function write(string $id, string $data): bool {
        $now = time();
        $stmt = mysqli_prepare($this->db, "REPLACE INTO php_sessions (id, data, updated_at) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssi", $id, $data, $now);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    public function destroy(string $id): bool {
        $stmt = mysqli_prepare($this->db, "DELETE FROM php_sessions WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "s", $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    public function gc(int $max_lifetime): int|false {
        $limit = time() - $max_lifetime;
        mysqli_query($this->db, "DELETE FROM php_sessions WHERE updated_at < " . (int) $limit);
        return 0;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_save_handler(new DbSessionHandler($koneksi), true);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
