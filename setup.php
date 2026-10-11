<?php
require __DIR__.'/config.php';
$count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $count === 0) {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name === '' || $username === '' || strlen($password) < 8) {
        $message = 'Isi semua data. Password minimal 8 karakter.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users(name,username,password,role) VALUES(?,?,?,'admin')");
        $stmt->execute([$name,$username,password_hash($password, PASSWORD_DEFAULT)]);
        flash('Akun admin berhasil dibuat. Silakan login.');
        redirect('index.php?page=login');
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Setup Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container py-5" style="max-width:560px"><div class="card shadow-sm"><div class="card-body p-4"><h3>Buat Admin Pertama</h3>
<?php if($count > 0): ?><div class="alert alert-info">Akun sudah tersedia. <a href="index.php?page=login">Masuk ke login</a>.</div>
<?php else: ?>
<?php if($message): ?><div class="alert alert-danger"><?=e($message)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label class="form-label mt-2">Nama</label><input class="form-control" name="name" required>
<label class="form-label mt-2">Username</label><input class="form-control" name="username" required>
<label class="form-label mt-2">Password (minimal 8 karakter)</label><input class="form-control" type="password" name="password" minlength="8" required>
<button class="btn btn-primary mt-3">Buat Admin</button></form>
<?php endif; ?></div></div><p class="text-muted small mt-3">Setelah akun admin dibuat, hapus file setup.php untuk keamanan.</p></div></body></html>
