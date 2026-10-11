<?php
require __DIR__.'/config.php';
verify_csrf();
$page = $_GET['page'] ?? (logged_in() ? 'dashboard' : 'login');
$allowed = ['login','logout','dashboard','categories','products','suppliers','transactions','history','users'];
if (!in_array($page, $allowed, true)) $page = 'dashboard';

if ($page === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}
if ($page === 'login') {
    if (logged_in()) redirect('index.php');
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare('SELECT id,name,username,password,role FROM users WHERE username=? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            unset($user['password']);
            $_SESSION['user'] = $user;
            flash('Selamat datang, '.$user['name'].'!');
            redirect('index.php');
        }
        $error = 'Username atau password salah.';
    }
    $notice = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login Inventaris</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#eef2f7}.login{max-width:430px;margin:8vh auto}.brand{background:#142b4e;color:white}</style></head><body><div class="login card shadow border-0"><div class="brand p-4 rounded-top"><h3 class="mb-1">Inventory System</h3><div>Silakan masuk ke akun Anda</div></div><div class="card-body p-4">
<?php if($notice): ?><div class="alert alert-<?=e($notice['type'])?>"><?=e($notice['message'])?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label class="form-label">Username</label><input class="form-control mb-3" name="username" required autofocus><label class="form-label">Password</label><input class="form-control mb-3" type="password" name="password" required><button class="btn btn-primary w-100">Login</button></form><p class="small text-muted mt-3 mb-0">Belum ada admin? Jalankan <a href="setup.php">setup admin</a> terlebih dahulu.</p></div></div></body></html><?php
    exit;
}

require_login();
if (in_array($page, ['categories','products','suppliers','users'], true)) require_admin();

$error = '';
try {
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($page === 'categories' && $action === 'save') {
        require_admin();
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') throw new RuntimeException('Nama kategori wajib diisi.');
        if (!empty($_POST['id'])) {
            $s=$pdo->prepare('UPDATE categories SET name=?,description=? WHERE id=?'); $s->execute([$name,$desc,(int)$_POST['id']]);
        } else { $s=$pdo->prepare('INSERT INTO categories(name,description) VALUES(?,?)'); $s->execute([$name,$desc]); }
        flash('Kategori berhasil disimpan.'); redirect('index.php?page=categories');
    }
    if ($page === 'categories' && $action === 'delete') {
        require_admin(); $s=$pdo->prepare('DELETE FROM categories WHERE id=?'); $s->execute([(int)$_POST['id']]);
        flash('Kategori dihapus jika tidak sedang digunakan.','info'); redirect('index.php?page=categories');
    }
    if ($page === 'suppliers' && $action === 'save') {
        require_admin(); $name=trim($_POST['name']??''); $phone=trim($_POST['phone']??''); $address=trim($_POST['address']??'');
        if ($name==='') throw new RuntimeException('Nama supplier wajib diisi.');
        if (!empty($_POST['id'])) { $s=$pdo->prepare('UPDATE suppliers SET name=?,phone=?,address=? WHERE id=?'); $s->execute([$name,$phone,$address,(int)$_POST['id']]); }
        else { $s=$pdo->prepare('INSERT INTO suppliers(name,phone,address) VALUES(?,?,?)'); $s->execute([$name,$phone,$address]); }
        flash('Supplier berhasil disimpan.'); redirect('index.php?page=suppliers');
    }
    if ($page === 'suppliers' && $action === 'delete') {
        require_admin(); $s=$pdo->prepare('DELETE FROM suppliers WHERE id=?'); $s->execute([(int)$_POST['id']]); flash('Supplier dihapus.','info'); redirect('index.php?page=suppliers');
    }
    if ($page === 'products' && $action === 'save') {
        require_admin();
        $id=(int)($_POST['id']??0); $category=(int)($_POST['category_id']??0); $sku=trim($_POST['sku']??''); $name=trim($_POST['name']??'');
        $stock=max(0,(int)($_POST['stock']??0)); $min=max(0,(int)($_POST['min_stock']??5)); $price=max(0,(float)($_POST['price']??0));
        $suppliers=array_values(array_unique(array_map('intval', $_POST['suppliers']??[])));
        if (!$category || $sku==='' || $name==='') throw new RuntimeException('Kategori, SKU, dan nama produk wajib diisi.');
        $pdo->beginTransaction();
        if ($id) { $s=$pdo->prepare('UPDATE products SET category_id=?,sku=?,name=?,stock=?,min_stock=?,price=? WHERE id=?'); $s->execute([$category,$sku,$name,$stock,$min,$price,$id]); }
        else { $s=$pdo->prepare('INSERT INTO products(category_id,sku,name,stock,min_stock,price) VALUES(?,?,?,?,?,?)'); $s->execute([$category,$sku,$name,$stock,$min,$price]); $id=(int)$pdo->lastInsertId(); }
        $pdo->prepare('DELETE FROM product_suppliers WHERE product_id=?')->execute([$id]);
        $link=$pdo->prepare('INSERT INTO product_suppliers(product_id,supplier_id) VALUES(?,?)');
        foreach($suppliers as $sid) $link->execute([$id,$sid]);
        $pdo->commit(); flash('Produk berhasil disimpan.'); redirect('index.php?page=products');
    }
    if ($page === 'products' && $action === 'delete') {
        require_admin(); $s=$pdo->prepare('DELETE FROM products WHERE id=?'); $s->execute([(int)$_POST['id']]); flash('Produk dihapus jika belum memiliki transaksi.','info'); redirect('index.php?page=products');
    }
    if ($page === 'transactions' && $action === 'save') {
        $product=(int)($_POST['product_id']??0); $type=$_POST['type']??''; $qty=(int)($_POST['quantity']??0); $note=trim($_POST['note']??'');
        if (!$product || !in_array($type,['in','out'],true) || $qty<1) throw new RuntimeException('Pilih produk, jenis transaksi, dan jumlah minimal 1.');
        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT stock FROM products WHERE id=? FOR UPDATE'); $s->execute([$product]); $row=$s->fetch();
        if (!$row) throw new RuntimeException('Produk tidak ditemukan.');
        if ($type==='out' && (int)$row['stock']<$qty) throw new RuntimeException('Stok tidak mencukupi untuk barang keluar.');
        $delta=$type==='in'?$qty:-$qty;
        $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?')->execute([$delta,$product]);
        $pdo->prepare('INSERT INTO transactions(product_id,user_id,type,quantity,note) VALUES(?,?,?,?,?)')->execute([$product,$_SESSION['user']['id'],$type,$qty,$note]);
        $pdo->commit(); flash('Transaksi berhasil dicatat.'); redirect('index.php?page=transactions');
    }
    if ($page === 'users' && $action === 'save') {
        require_admin(); $name=trim($_POST['name']??''); $username=trim($_POST['username']??''); $password=$_POST['password']??''; $role=$_POST['role']??'staff';
        if ($name===''||$username===''||!in_array($role,['admin','staff'],true)) throw new RuntimeException('Data pengguna belum lengkap.');
        if (strlen($password)<8) throw new RuntimeException('Password minimal 8 karakter.');
        $s=$pdo->prepare('INSERT INTO users(name,username,password,role) VALUES(?,?,?,?)'); $s->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT),$role]);
        flash('Pengguna berhasil dibuat.'); redirect('index.php?page=users');
    }
}
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error = $ex instanceof PDOException ? 'Data gagal disimpan. Periksa duplikasi atau relasi data.' : $ex->getMessage();
}

$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
$search=trim($_GET['search']??''); $sort=$_GET['sort']??'id'; $dir=strtolower($_GET['dir']??'desc')==='asc'?'ASC':'DESC';
$perPage=8; $current=max(1,(int)($_GET['p']??1)); $offset=($current-1)*$perPage;
function url_page(string $page, array $extra=[]): string { return 'index.php?'.http_build_query(array_merge(['page'=>$page],$extra)); }
function table_rows(PDO $pdo, string $sql, array $params=[]): array { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Inventory Management</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f4f6fa}.sidebar{background:#142b4e;min-height:100vh}.sidebar a{color:#e7edf7;text-decoration:none;display:block;padding:10px 12px;border-radius:8px}.sidebar a:hover{background:#29466f}.top{background:#fff}.card{border:0;box-shadow:0 2px 12px #142b4e0b}.table th{white-space:nowrap}</style></head><body>
<div class="container-fluid"><div class="row">
<aside class="col-md-2 sidebar p-3"><h4 class="text-white mb-4">Inventory</h4>
<a href="index.php">📊 Dashboard</a><a href="index.php?page=categories">📁 Kategori</a><a href="index.php?page=products">📦 Produk</a><a href="index.php?page=suppliers">🚚 Supplier</a><a href="index.php?page=transactions">🔄 Transaksi</a><a href="index.php?page=history">🕘 Histori</a><?php if(is_admin()): ?><a href="index.php?page=users">👥 Pengguna</a><?php endif; ?><hr class="text-white"><a href="index.php?page=logout">Keluar</a></aside>
<main class="col-md-10 p-0"><div class="top px-4 py-3 d-flex justify-content-between align-items-center"><div><strong>Inventory Management System</strong><div class="text-muted small">Pengelolaan persediaan barang</div></div><div><?=e($_SESSION['user']['name'])?> <span class="badge text-bg-secondary"><?=e($_SESSION['user']['role'])?></span></div></div>
<div class="p-4">
<?php if($flash): ?><div class="alert alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php if($page==='dashboard'):
$stats=[
['Total Produk',(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),'primary'],
['Total Kategori',(int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),'success'],
['Total Supplier',(int)$pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn(),'warning'],
['Stok Menipis',(int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock<=min_stock')->fetchColumn(),'danger']];
?>
<h3 class="mb-4">Dashboard</h3><div class="row g-3 mb-4"><?php foreach($stats as $st): ?><div class="col-sm-6 col-xl-3"><div class="card p-3"><div class="text-muted"><?=e($st[0])?></div><div class="display-6 fw-semibold text-<?=e($st[2])?>"><?=e($st[1])?></div></div></div><?php endforeach; ?></div>
<div class="card p-3 mb-4"><h5>Produk dengan Stok Menipis</h5><div class="table-responsive"><table class="table"><thead><tr><th>SKU</th><th>Produk</th><th>Stok</th><th>Minimum</th></tr></thead><tbody><?php foreach(table_rows($pdo,'SELECT sku,name,stock,min_stock FROM products WHERE stock<=min_stock ORDER BY stock ASC LIMIT 10') as $r): ?><tr><td><?=e($r['sku'])?></td><td><?=e($r['name'])?></td><td><span class="badge text-bg-danger"><?=e($r['stock'])?></span></td><td><?=e($r['min_stock'])?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card p-3"><h5>Transaksi Terbaru</h5><div class="table-responsive"><table class="table"><thead><tr><th>Waktu</th><th>Produk</th><th>Jenis</th><th>Jumlah</th><th>Petugas</th></tr></thead><tbody><?php foreach(table_rows($pdo,"SELECT t.*,p.name product,u.name username FROM transactions t JOIN products p ON p.id=t.product_id JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 8") as $r): ?><tr><td><?=e($r['created_at'])?></td><td><?=e($r['product'])?></td><td><?= $r['type']==='in'?'Masuk':'Keluar' ?></td><td><?=e($r['quantity'])?></td><td><?=e($r['username'])?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php elseif($page==='categories'):
$edit=null;if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM categories WHERE id=?');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch();}
$rows=table_rows($pdo,'SELECT * FROM categories WHERE name LIKE ? OR description LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?',["%$search%","%$search%",$perPage,$offset]);$total=(int)$pdo->prepare('SELECT COUNT(*) FROM categories');$cnt=$pdo->prepare('SELECT COUNT(*) FROM categories WHERE name LIKE ? OR description LIKE ?');$cnt->execute(["%$search%","%$search%"]);$total=(int)$cnt->fetchColumn();
?>
<h3>Kategori</h3><div class="row g-3"><div class="col-lg-4"><div class="card p-3"><h5><?= $edit?'Edit':'Tambah' ?> Kategori</h5><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($edit['id']??'')?>"><label class="form-label">Nama</label><input class="form-control mb-2" name="name" required value="<?=e($edit['name']??'')?>"><label class="form-label">Deskripsi</label><textarea class="form-control mb-3" name="description"><?=e($edit['description']??'')?></textarea><button class="btn btn-primary">Simpan</button></form></div></div><div class="col-lg-8"><div class="card p-3"><?php include __DIR__.'/views/list_search.php'; ?><div class="table-responsive"><table class="table"><tr><th>Nama</th><th>Deskripsi</th><th>Aksi</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['name'])?></td><td><?=e($r['description'])?></td><td><a class="btn btn-sm btn-outline-primary" href="index.php?page=categories&edit=<?=$r['id']?>">Edit</a> <form class="d-inline" method="post" onsubmit="return confirm('Hapus kategori ini?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php endforeach; ?></table></div><?php pagination('categories',$current,$perPage,$total,$search); ?></div></div></div>
<?php elseif($page==='suppliers'):
$edit=null;if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM suppliers WHERE id=?');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch();}
$cnt=$pdo->prepare('SELECT COUNT(*) FROM suppliers WHERE name LIKE ? OR phone LIKE ?');$cnt->execute(["%$search%","%$search%"]);$total=(int)$cnt->fetchColumn();$rows=table_rows($pdo,'SELECT * FROM suppliers WHERE name LIKE ? OR phone LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?',["%$search%","%$search%",$perPage,$offset]);
?>
<h3>Supplier</h3><div class="row g-3"><div class="col-lg-4"><div class="card p-3"><h5><?= $edit?'Edit':'Tambah' ?> Supplier</h5><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($edit['id']??'')?>"><label class="form-label">Nama</label><input class="form-control mb-2" name="name" required value="<?=e($edit['name']??'')?>"><label class="form-label">Telepon</label><input class="form-control mb-2" name="phone" value="<?=e($edit['phone']??'')?>"><label class="form-label">Alamat</label><textarea class="form-control mb-3" name="address"><?=e($edit['address']??'')?></textarea><button class="btn btn-primary">Simpan</button></form></div></div><div class="col-lg-8"><div class="card p-3"><?php include __DIR__.'/views/list_search.php'; ?><div class="table-responsive"><table class="table"><tr><th>Nama</th><th>Telepon</th><th>Alamat</th><th>Aksi</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['name'])?></td><td><?=e($r['phone'])?></td><td><?=e($r['address'])?></td><td><a class="btn btn-sm btn-outline-primary" href="index.php?page=suppliers&edit=<?=$r['id']?>">Edit</a> <form class="d-inline" method="post" onsubmit="return confirm('Hapus supplier ini?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php endforeach; ?></table></div><?php pagination('suppliers',$current,$perPage,$total,$search); ?></div></div></div>
<?php elseif($page==='products'):
$cats=table_rows($pdo,'SELECT * FROM categories ORDER BY name');$suppliers=table_rows($pdo,'SELECT * FROM suppliers ORDER BY name');$edit=null;$selected=[];
if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM products WHERE id=?');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch();if($edit){$s=$pdo->prepare('SELECT supplier_id FROM product_suppliers WHERE product_id=?');$s->execute([$edit['id']]);$selected=array_map('intval',array_column($s->fetchAll(),'supplier_id'));}}
$cnt=$pdo->prepare('SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ?');$cnt->execute(["%$search%","%$search%","%$search%"]);$total=(int)$cnt->fetchColumn();
$sorts=['id'=>'p.id','name'=>'p.name','stock'=>'p.stock','price'=>'p.price'];$sortSql=$sorts[$sort]??'p.id';
$rows=table_rows($pdo,"SELECT p.*,c.name category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ? ORDER BY $sortSql $dir LIMIT ? OFFSET ?",["%$search%","%$search%","%$search%",$perPage,$offset]);
?>
<h3>Produk</h3><div class="row g-3"><div class="col-lg-4"><div class="card p-3"><h5><?= $edit?'Edit':'Tambah' ?> Produk</h5><?php if(!$cats): ?><p class="text-danger">Tambahkan kategori dahulu.</p><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($edit['id']??'')?>"><label class="form-label">Kategori</label><select class="form-select mb-2" name="category_id" required><?php foreach($cats as $c): ?><option value="<?=$c['id']?>" <?=($edit['category_id']??'')==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach; ?></select><label class="form-label">SKU / Kode</label><input class="form-control mb-2" name="sku" required value="<?=e($edit['sku']??'')?>"><label class="form-label">Nama produk</label><input class="form-control mb-2" name="name" required value="<?=e($edit['name']??'')?>"><div class="row"><div class="col"><label class="form-label">Stok</label><input class="form-control mb-2" type="number" min="0" name="stock" value="<?=e($edit['stock']??0)?>"></div><div class="col"><label class="form-label">Stok minimum</label><input class="form-control mb-2" type="number" min="0" name="min_stock" value="<?=e($edit['min_stock']??5)?>"></div></div><label class="form-label">Harga</label><input class="form-control mb-2" type="number" min="0" step="0.01" name="price" value="<?=e($edit['price']??0)?>"><label class="form-label">Supplier (boleh pilih beberapa)</label><select class="form-select mb-3" name="suppliers[]" multiple><?php foreach($suppliers as $s): ?><option value="<?=$s['id']?>" <?=in_array((int)$s['id'],$selected,true)?'selected':''?>><?=e($s['name'])?></option><?php endforeach; ?></select><button class="btn btn-primary">Simpan Produk</button></form><?php endif; ?></div></div><div class="col-lg-8"><div class="card p-3"><?php include __DIR__.'/views/list_search.php'; ?><div class="d-flex gap-2 mb-2"><span class="small text-muted">Urutkan:</span><a href="<?=url_page('products',['search'=>$search,'sort'=>'name','dir'=>'asc'])?>">Nama</a><a href="<?=url_page('products',['search'=>$search,'sort'=>'stock','dir'=>'asc'])?>">Stok</a><a href="<?=url_page('products',['search'=>$search,'sort'=>'price','dir'=>'desc'])?>">Harga</a></div><div class="table-responsive"><table class="table"><tr><th>SKU</th><th>Produk</th><th>Kategori</th><th>Stok</th><th>Harga</th><th>Aksi</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['sku'])?></td><td><?=e($r['name'])?></td><td><?=e($r['category'])?></td><td><?=e($r['stock'])?> <?= $r['stock']<=$r['min_stock']?'<span class="badge text-bg-danger">Menipis</span>':'' ?></td><td>Rp <?=number_format((float)$r['price'],0,',','.')?></td><td><a class="btn btn-sm btn-outline-primary" href="index.php?page=products&edit=<?=$r['id']?>">Edit</a> <form class="d-inline" method="post" onsubmit="return confirm('Hapus produk ini?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php endforeach; ?></table></div><?php pagination('products',$current,$perPage,$total,$search); ?></div></div></div>
<?php elseif($page==='transactions'):
$products=table_rows($pdo,'SELECT id,sku,name,stock FROM products ORDER BY name');
?>
<h3>Transaksi Barang</h3><div class="row g-3"><div class="col-lg-5"><div class="card p-3"><h5>Catat Transaksi</h5><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><label class="form-label">Produk</label><select class="form-select mb-3" name="product_id" required><?php foreach($products as $p): ?><option value="<?=$p['id']?>"><?=e($p['sku'].' - '.$p['name'].' (stok: '.$p['stock'].')')?></option><?php endforeach; ?></select><label class="form-label">Jenis transaksi</label><select class="form-select mb-3" name="type"><option value="in">Barang Masuk</option><option value="out">Barang Keluar</option></select><label class="form-label">Jumlah</label><input class="form-control mb-3" type="number" min="1" name="quantity" required><label class="form-label">Catatan</label><textarea class="form-control mb-3" name="note"></textarea><button class="btn btn-primary">Simpan Transaksi</button></form></div></div><div class="col-lg-7"><div class="card p-3"><h5>Petunjuk</h5><ul><li>Barang masuk akan menambah stok.</li><li>Barang keluar akan mengurangi stok.</li><li>Stok tidak boleh menjadi minus.</li><li>Semua transaksi tersimpan di halaman Histori.</li></ul></div></div></div>
<?php elseif($page==='history'):
$cnt=$pdo->prepare('SELECT COUNT(*) FROM transactions t JOIN products p ON p.id=t.product_id JOIN users u ON u.id=t.user_id WHERE p.name LIKE ? OR p.sku LIKE ? OR u.name LIKE ?');$cnt->execute(["%$search%","%$search%","%$search%"]);$total=(int)$cnt->fetchColumn();
$rows=table_rows($pdo,'SELECT t.*,p.name product,p.sku,u.name username FROM transactions t JOIN products p ON p.id=t.product_id JOIN users u ON u.id=t.user_id WHERE p.name LIKE ? OR p.sku LIKE ? OR u.name LIKE ? ORDER BY t.id DESC LIMIT ? OFFSET ?',["%$search%","%$search%","%$search%",$perPage,$offset]);
?>
<h3>Histori Transaksi</h3><div class="card p-3"><?php include __DIR__.'/views/list_search.php'; ?><div class="table-responsive"><table class="table"><tr><th>Tanggal</th><th>SKU</th><th>Produk</th><th>Jenis</th><th>Jumlah</th><th>Petugas</th><th>Catatan</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['created_at'])?></td><td><?=e($r['sku'])?></td><td><?=e($r['product'])?></td><td><span class="badge text-bg-<?=$r['type']==='in'?'success':'warning'?>"><?=$r['type']==='in'?'Masuk':'Keluar'?></span></td><td><?=e($r['quantity'])?></td><td><?=e($r['username'])?></td><td><?=e($r['note'])?></td></tr><?php endforeach; ?></table></div><?php pagination('history',$current,$perPage,$total,$search); ?></div>
<?php elseif($page==='users'):
$rows=table_rows($pdo,'SELECT id,name,username,role,created_at FROM users ORDER BY id DESC');
?>
<h3>Pengguna</h3><div class="row g-3"><div class="col-lg-5"><div class="card p-3"><h5>Tambah Pengguna</h5><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><label class="form-label">Nama</label><input class="form-control mb-2" name="name" required><label class="form-label">Username</label><input class="form-control mb-2" name="username" required><label class="form-label">Password (min. 8 karakter)</label><input class="form-control mb-2" type="password" name="password" minlength="8" required><label class="form-label">Role</label><select class="form-select mb-3" name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select><button class="btn btn-primary">Buat Pengguna</button></form></div></div><div class="col-lg-7"><div class="card p-3"><h5>Daftar Pengguna</h5><table class="table"><tr><th>Nama</th><th>Username</th><th>Role</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['name'])?></td><td><?=e($r['username'])?></td><td><?=e($r['role'])?></td></tr><?php endforeach; ?></table></div></div></div>
<?php endif; ?>
</div></main></div></div></body></html>
<?php
function pagination(string $page,int $current,int $perPage,int $total,string $search): void {
    $pages=max(1,(int)ceil($total/$perPage));
    echo '<nav><ul class="pagination pagination-sm mb-0">';
    for($i=1;$i<=$pages;$i++){
        $url='index.php?'.http_build_query(['page'=>$page,'p'=>$i,'search'=>$search]);
        echo '<li class="page-item '.($i===$current?'active':'').'"><a class="page-link" href="'.e($url).'">'.$i.'</a></li>';
    }
    echo '</ul></nav>';
}
?>
