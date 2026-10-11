<?php
declare(strict_types=1);
session_start();

$host = 'localhost';
$db   = 'inventory_db';
$user = 'root';
$pass = ''; // Sesuaikan jika MySQL Anda memiliki password.

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    exit('Koneksi database gagal. Pastikan MySQL aktif dan database inventory_db sudah diimpor.');
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function logged_in(): bool {
    return isset($_SESSION['user']);
}
function require_login(): void {
    if (!logged_in()) {
        header('Location: index.php?page=login');
        exit;
    }
}
function is_admin(): bool {
    return logged_in() && $_SESSION['user']['role'] === 'admin';
}
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Akses ditolak. Fitur ini hanya untuk admin. <a href="index.php">Kembali</a>');
    }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf'] ?? '';
        if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
            http_response_code(419);
            exit('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
    }
}
function flash(string $message, string $type='success'): void {
    $_SESSION['flash'] = ['message'=>$message, 'type'=>$type];
}
function redirect(string $url): never {
    header('Location: '.$url);
    exit;
}
