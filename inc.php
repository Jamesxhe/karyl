<?php
// BookNest core: database, seed data, auth, helpers, shared layout.
session_start();
const CUR = '₱';
const ORDER_STATUSES = ['Pending', 'Processing', 'Shipped', 'Completed', 'Cancelled'];
const READ = ['want' => 'Want to Read', 'reading' => 'Currently Reading', 'finished' => 'Finished'];
require __DIR__ . '/config.php';
try {
  $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
  $pdo->exec('USE `' . DB_NAME . '`');
  if (empty($_SESSION['dbv3'])) {            // create / repair tables once per session
    $sql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/database.sql'));
    foreach (array_values(array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)))) as $i => $st) {
      $pdo->exec(rtrim($st, "; \t\r\n"));
      if ($i === 1) migrate_users($pdo);       // after users + catalog, before tables that reference them
    }
    seed($pdo);
    $_SESSION['dbv3'] = 1;
  }
} catch (Throwable $e) {
  http_response_code(500);
  die('<pre style="padding:20px;font:15px monospace">Database error: ' . htmlspecialchars($e->getMessage()) . "\n\nCheck config.php and make sure MySQL is running in XAMPP.</pre>");
}

// Makes an existing `users` table (id without AUTO_INCREMENT/PRIMARY KEY, no created_at) usable. Data is kept.
function migrate_users(PDO $pdo){
  $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $one = function($sql, $p) use ($pdo){ $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); };
  foreach (['users', 'catalog'] as $t)
    if (strcasecmp((string)$one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=? AND table_name=?', [$db, $t]), 'InnoDB'))
      $pdo->exec("ALTER TABLE `$t` ENGINE=InnoDB");
  $try = function($sql) use ($pdo){ try { $pdo->exec($sql); } catch (Throwable $e) {} };
  if (!$pdo->query("SHOW KEYS FROM users WHERE Key_name='PRIMARY'")->fetch()) $try('ALTER TABLE users ADD PRIMARY KEY (id)');
  if (stripos((string)$one("SELECT EXTRA FROM information_schema.columns WHERE table_schema=? AND table_name='users' AND column_name='id'", [$db]), 'auto_increment') === false) $try('ALTER TABLE users MODIFY id INT NOT NULL AUTO_INCREMENT');
  if (!$pdo->query("SHOW INDEX FROM users WHERE Column_name='email' AND Non_unique=0")->fetch()) $try('ALTER TABLE users ADD UNIQUE (email)');
  if (!$one("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name='users' AND column_name='created_at'", [$db])) $try('ALTER TABLE users ADD created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
  $try("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'user', MODIFY active INT NOT NULL DEFAULT 1");
}

// Demo data. Never overwrites rows you already have.
function seed(PDO $pdo){
  $n = fn($q) => (int)$pdo->query($q)->fetchColumn();
  $demo = false; $uid = 0;
  $ins = $pdo->prepare('INSERT IGNORE INTO users(name,email,password,role,active) VALUES(?,?,?,?,1)');
  if (!$n('SELECT COUNT(*) FROM users')) {
    $ins->execute(['Admin', 'admin@booknest.test', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    $ins->execute(['Mia Reader', 'reader@booknest.test', password_hash('reader123', PASSWORD_DEFAULT), 'user']);
    $uid = (int)$pdo->lastInsertId(); $demo = true;
  } elseif (!$n("SELECT COUNT(*) FROM users WHERE role='admin'")) $ins->execute(['Admin', 'admin@booknest.test', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
  if (!$n('SELECT COUNT(*) FROM catalog')) {
    $c = $pdo->prepare('INSERT INTO catalog(title,author,isbn,genre,description,price,stock,pages) VALUES(?,?,?,?,?,?,?,?)');
    foreach ([
      ['The Hobbit','J.R.R. Tolkien','9780547928227','Fantasy','Bilbo Baggins is swept into a quest to reclaim a dragon-held treasure.',420,12,310],
      ['1984','George Orwell','9780451524935','Dystopian','A chilling story of surveillance, truth and control.',350,8,328],
      ['Pride and Prejudice','Jane Austen','9780141439518','Romance','Elizabeth Bennet and Mr. Darcy in Regency England.',299,5,432],
      ['To Kill a Mockingbird','Harper Lee','9780061120084','Classic','Justice and growing up in the American South.',380,0,281],
      ['The Great Gatsby','F. Scott Fitzgerald','9780743273565','Classic','Ambition and heartbreak in the Jazz Age.',320,9,180],
      ['Noli Me Tangere','José Rizal','','Historical Fiction','The novel that stirred a nation.',275,15,438],
      ['Dune','Frank Herbert','9780441013593','Science Fiction','Politics, prophecy and spice on the desert planet Arrakis.',490,3,612],
      ['The Alchemist','Paulo Coelho','9780062315007','Adventure','A shepherd follows his dream to the pyramids.',360,11,208],
      ['Sapiens','Yuval Noah Harari','9780062316097','History','A brief history of humankind.',520,6,464],
      ['Atomic Habits','James Clear','9780735211292','Self-Help','Tiny changes, remarkable results.',480,20,320],
      ['The Little Prince','Antoine de Saint-Exupéry','','Fable','A pilot meets a prince from a tiny asteroid.',250,14,96],
      ['Frankenstein','Mary Shelley','','Gothic','A scientist creates life and flees from it.',290,2,280],
    ] as $r) $c->execute($r);
  }
  $covers = ['The Hobbit'=>'hobbit','1984'=>'1984','Pride and Prejudice'=>'pride-and-prejudice','To Kill a Mockingbird'=>'mockingbird','The Great Gatsby'=>'gatsby',
    'Noli Me Tangere'=>'noli','Dune'=>'dune','The Alchemist'=>'alchemist','Sapiens'=>'sapiens','Atomic Habits'=>'atomic-habits','The Little Prince'=>'little-prince','Frankenstein'=>'frankenstein'];
  $cv = function() use ($pdo, $covers){   // only fills books that have no cover yet
    foreach ($covers as $t => $f) if (is_file(__DIR__ . "/uploads/seed-$f.jpg"))
      foreach (['catalog', 'shelf'] as $tb) $pdo->prepare("UPDATE $tb SET cover=? WHERE title=? AND (cover IS NULL OR cover='')")->execute(["uploads/seed-$f.jpg", $t]);
  };
  $cv();
  if (!$demo) return;
  $cid = function($t) use ($pdo){ $s = $pdo->prepare('SELECT id FROM catalog WHERE title=? LIMIT 1'); $s->execute([$t]); return $s->fetchColumn() ?: null; };
  $sh = $pdo->prepare('INSERT INTO shelf(user_id,catalog_id,title,author,isbn,genre,status,rating,pages,current_page,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
  foreach ([['Dune','Frank Herbert','9780441013593','Science Fiction','reading',0,612,240,'Loving the worldbuilding.'],
            ['The Hobbit','J.R.R. Tolkien','9780547928227','Fantasy','finished',5,310,310,'A perfect comfort read.'],
            ['Atomic Habits','James Clear','9780735211292','Self-Help','finished',4,320,320,''],
            ['1984','George Orwell','9780451524935','Dystopian','finished',5,328,328,'Still relevant.'],
            ['Noli Me Tangere','José Rizal','','Historical Fiction','reading',0,438,120,'Re-reading for fun.'],
            ['Sapiens','Yuval Noah Harari','9780062316097','History','want',0,464,0,''],
            ['The Little Prince','Antoine de Saint-Exupéry','','Fable','want',0,96,0,'']] as $r)
    $sh->execute([$uid, $cid($r[0]), ...$r]);
  $po = $pdo->prepare("INSERT INTO orders(user_id,status,total,address,phone,created_at) VALUES(?,?,?,'12 Mabini St, Cabuyao, Laguna','09171234567',NOW() - INTERVAL ? DAY)");
  $oi = $pdo->prepare('INSERT INTO order_items(order_id,catalog_id,title,price,qty) VALUES(?,?,?,?,1)');
  $po->execute([$uid, 'Completed', 770, 12]); $o1 = (int)$pdo->lastInsertId();
  $oi->execute([$o1, $cid('The Hobbit'), 'The Hobbit', 420]); $oi->execute([$o1, $cid('1984'), '1984', 350]);
  $po->execute([$uid, 'Shipped', 490, 2]); $oi->execute([(int)$pdo->lastInsertId(), $cid('Dune'), 'Dune', 490]);
  $cv();
}

// ---------- helpers ----------
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return CUR . number_format((float)$n, 2); }
function pw_ok($u, $pw){
  global $pdo; $h = (string)$u['password'];
  if (password_verify($pw, $h)) return true;
  $plain = !preg_match('/^\$(2y|2a|argon2)/', $h) && ($h !== '' && hash_equals($h, $pw) || (strlen($h) === 32 && hash_equals(strtolower($h), md5($pw))));
  if ($plain) { $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]); return true; } // upgrade old passwords
  return false;
}
function go($u){ header("Location: $u"); exit; }
function token(){ if (empty($_SESSION['t'])) $_SESSION['t'] = bin2hex(random_bytes(16)); return $_SESSION['t']; }
function tf(){ return '<input type="hidden" name="t" value="' . token() . '">'; }
function check_token(){ if (!hash_equals($_SESSION['t'] ?? '', $_POST['t'] ?? '')) { http_response_code(400); die('Invalid form token. Go back and try again.'); } }
function flash($m = null, $type = 'ok'){ if ($m !== null) { $_SESSION['fl'] = [$m, $type]; return; } $f = $_SESSION['fl'] ?? null; unset($_SESSION['fl']); return $f; }
function me(){
  global $pdo; static $u = false;
  if ($u === false) { $u = null;
    if (!empty($_SESSION['uid'])) { $s = $pdo->prepare('SELECT * FROM users WHERE id=? AND active=1'); $s->execute([$_SESSION['uid']]); $u = $s->fetch() ?: null; if (!$u) unset($_SESSION['uid']); } }
  return $u;
}
function need_login(){ if (!me()) go('login.php'); }
function need_admin(){ need_login(); if (me()['role'] !== 'admin') { http_response_code(403); die('Admins only.'); } }
function back($d = 'bookshelf.php'){ $b = $_POST['back'] ?? ''; go(preg_match('/^[a-z_]+\.php(\?[\w=&%.+-]*)?$/i', $b) ? $b : $d); }
function cart_count($uid){ global $pdo; $s = $pdo->prepare('SELECT COALESCE(SUM(qty),0) FROM cart WHERE user_id=?'); $s->execute([$uid]); return (int)$s->fetchColumn(); }
function stars($n){ $n = max(0, min(5, (int)$n)); return str_repeat('★', $n) . str_repeat('☆', 5 - $n); }
function pill($s){ return '<span class="pill st-' . strtolower(preg_replace('/\W/', '', $s)) . '">' . e(READ[$s] ?? $s) . '</span>'; }
function pct($cur, $pages){ return $pages > 0 ? (int)min(100, round($cur / $pages * 100)) : 0; }
function cover($c, $title){
  if ($c) return '<img class="cv" src="' . e($c) . '" alt="Cover of ' . e($title) . '" loading="lazy">';
  return '<div class="cv gen" style="--h:' . (crc32($title) % 360) . '"><b>' . e(mb_strtoupper(mb_substr($title, 0, 1))) . '</b><span>' . e($title) . '</span></div>';
}
// Returns [path-or-url, error]. Accepts a phone/PC photo (JPG, PNG, WEBP, GIF: fixed rotation, resized, re-encoded as JPG) or an http(s) link.
function save_cover($old = ''){
  $f = $_FILES['cover_file'] ?? null;
  if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) return [$old, 'That photo is too large for the server (limit ' . ini_get('upload_max_filesize') . ').'];
    if ($f['error'] !== UPLOAD_ERR_OK) return [$old, 'The photo could not be uploaded. Please try again.'];
    if ($f['size'] > 15 * 1024 * 1024) return [$old, 'Please choose a photo under 15 MB.'];
    $i = @getimagesize($f['tmp_name']); $t = $i[2] ?? 0;
    if (!in_array($t, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return [$old, 'Cover must be a JPG, PNG, WEBP or GIF image.'];
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
    $base = 'uploads/' . bin2hex(random_bytes(8));
    if (function_exists('imagecreatetruecolor')) {
      $src = match ($t) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($f['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
        default => @imagecreatefromgif($f['tmp_name']),
      };
      if (!$src) return [$old, 'That image could not be read. Try another photo.'];
      if ($t === IMAGETYPE_JPEG && function_exists('exif_read_data')) {          // phone photos: fix sideways rotation
        $ex = @exif_read_data($f['tmp_name']) ?: [];
        $deg = [3 => 180, 6 => -90, 8 => 90][$ex['Orientation'] ?? 1] ?? 0;
        if ($deg && ($rot = imagerotate($src, $deg, 0))) $src = $rot;
      }
      $w = imagesx($src); $h = imagesy($src); $k = min(1, 900 / $h, 700 / $w);
      $dst = imagecreatetruecolor(max(1, (int)round($w * $k)), max(1, (int)round($h * $k)));
      imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
      imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
      $name = $base . '.jpg';
      return imagejpeg($dst, __DIR__ . '/' . $name, 86) ? [$name, ''] : [$old, 'Could not save the image.'];
    }
    $name = $base . '.' . [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$t];
    if ($f['size'] > 3 * 1024 * 1024) return [$old, 'Please choose a photo under 3 MB (image resizing is not enabled on this server).'];
    return move_uploaded_file($f['tmp_name'], __DIR__ . '/' . $name) ? [$name, ''] : [$old, 'Could not save the image.'];
  }
  $u = trim($_POST['cover_url'] ?? '');
  if ($u !== '') return preg_match('#^https?://\S+$#i', $u) ? [$u, ''] : [$old, 'Cover link must start with http:// or https://'];
  if (!empty($_POST['remove_cover'])) return ['', ''];
  return [$old, ''];
}
// Cover picker used by the shelf form and the admin book form (live preview, gallery/camera upload, link, remove).
function cover_field($cover, $title){
  return '<div class="coverfield" data-cover><div class="cvw sm"><div class="prev">' . cover($cover, $title ?: 'New book') . '</div></div><div class="stack">'
    . '<label class="btn filebtn">📷 Choose photo<input type="file" name="cover_file" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Choose a cover photo"></label>'
    . '<p class="mut sm fname">JPG, PNG or WEBP from your gallery or computer. Big photos are resized automatically.</p>'
    . '<label>Or paste an image link<input name="cover_url" inputmode="url" placeholder="https://…"></label>'
    . ($cover ? '<label class="check"><input type="checkbox" name="remove_cover" value="1"> Remove current cover</label>' : '')
    . '</div></div>';
}
function ico($n){
  $p = ['user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 8l9 6 9-6"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="3"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eyeoff' => '<path d="M3 3l18 18"/><path d="M10.6 5.1A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.5 6.6C3.7 8.4 2 12 2 12s3.6 7 10 7c1.6 0 3-.4 4.3-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>'];
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$n] . '</svg>';
}
// One field in the pill style: icon + input (+ eye button for passwords). $attrs is trusted markup written in our own pages.
function pf($icon, $attrs, $eye = false){
  return '<div class="pf">' . ico($icon) . '<input ' . $attrs . '>' . ($eye
    ? '<button type="button" class="eye" aria-label="Show password" aria-pressed="false"><span class="open">' . ico('eye') . '</span><span class="off">' . ico('eyeoff') . '</span></button>' : '') . '</div>';
}
function shelf_add_from_catalog($uid, $cid, $status = 'want'){
  global $pdo;
  $x = $pdo->prepare('SELECT 1 FROM shelf WHERE user_id=? AND catalog_id=?'); $x->execute([$uid, $cid]); if ($x->fetch()) return false;
  $k = $pdo->prepare('SELECT * FROM catalog WHERE id=?'); $k->execute([$cid]); $k = $k->fetch(); if (!$k) return false;
  $pdo->prepare('INSERT INTO shelf(user_id,catalog_id,title,author,isbn,genre,status,pages,cover) VALUES(?,?,?,?,?,?,?,?,?)')
      ->execute([$uid, $cid, $k['title'], $k['author'], $k['isbn'], $k['genre'], $status, $k['pages'], $k['cover']]);
  return true;
}
function set_order_status($id, $new){
  global $pdo;
  if (!in_array($new, ORDER_STATUSES)) return false;
  $o = $pdo->prepare('SELECT * FROM orders WHERE id=?'); $o->execute([$id]); $o = $o->fetch();
  if (!$o || $o['status'] === $new) return false;
  $it = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$id]); $it = $it->fetchAll();
  $pdo->beginTransaction();
  foreach ($it as $i) if ($i['catalog_id']) {
    if ($new === 'Cancelled') $pdo->prepare('UPDATE catalog SET stock=stock+? WHERE id=?')->execute([$i['qty'], $i['catalog_id']]);
    if ($o['status'] === 'Cancelled') $pdo->prepare('UPDATE catalog SET stock=GREATEST(0,stock-?) WHERE id=?')->execute([$i['qty'], $i['catalog_id']]);
    if ($new === 'Completed') shelf_add_from_catalog($o['user_id'], $i['catalog_id']);
  }
  $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$new, $id]);
  $pdo->commit(); return true;
}
function shelf_rows($uid, $f){
  global $pdo; $w = ['user_id=?']; $p = [$uid];
  if (($f['q'] ?? '') !== '') { $w[] = '(title LIKE ? OR author LIKE ? OR isbn LIKE ? OR genre LIKE ?)'; array_push($p, ...array_fill(0, 4, '%' . $f['q'] . '%')); }
  if (isset(READ[$f['status'] ?? ''])) { $w[] = 'status=?'; $p[] = $f['status']; }
  if (($f['genre'] ?? '') !== '') { $w[] = 'genre=?'; $p[] = $f['genre']; }
  if (($f['author'] ?? '') !== '') { $w[] = 'author=?'; $p[] = $f['author']; }
  if ((int)($f['rating'] ?? 0) > 0) { $w[] = 'rating>=?'; $p[] = (int)$f['rating']; }
  $o = ['title' => 'title', 'author' => 'author', 'rating' => 'rating DESC, title'][$f['sort'] ?? ''] ?? 'id DESC';
  $s = $pdo->prepare('SELECT * FROM shelf WHERE ' . implode(' AND ', $w) . " ORDER BY $o"); $s->execute($p); return $s->fetchAll();
}
// One source for CSV export AND the printable report: [title, headers, rows]
function dataset($type, $f = []){
  global $pdo; need_login(); $u = me();
  if (in_array($type, ['inventory', 'allorders']) && $u['role'] !== 'admin') { http_response_code(403); die('Admins only.'); }
  if ($type === 'shelf') {
    $r = array_map(fn($b) => [$b['title'], $b['author'], $b['isbn'], $b['genre'], READ[$b['status']] ?? '', $b['rating'], $b['pages'], $b['current_page'], pct($b['current_page'], $b['pages'])], shelf_rows($u['id'], $f));
    return ['My Bookshelf', ['Title', 'Author', 'ISBN', 'Genre', 'Status', 'Rating (1-5)', 'Pages', 'Current page', 'Progress %'], $r];
  }
  if ($type === 'orders') {
    $s = $pdo->prepare('SELECT o.*,(SELECT COALESCE(SUM(qty),0) FROM order_items WHERE order_id=o.id) n FROM orders o WHERE user_id=? ORDER BY id DESC'); $s->execute([$u['id']]);
    return ['My Orders', ['Order #', 'Date', 'Items', 'Total', 'Status'], array_map(fn($o) => [$o['id'], $o['created_at'], $o['n'], number_format($o['total'], 2, '.', ''), $o['status']], $s->fetchAll())];
  }
  if ($type === 'inventory') {
    $r = $pdo->query('SELECT * FROM catalog ORDER BY title')->fetchAll();
    return ['Book Inventory', ['ID', 'Title', 'Author', 'ISBN', 'Genre', 'Price', 'Stock'], array_map(fn($b) => [$b['id'], $b['title'], $b['author'], $b['isbn'], $b['genre'], number_format($b['price'], 2, '.', ''), $b['stock']], $r)];
  }
  if ($type === 'allorders') {
    $st = in_array($f['status'] ?? '', ORDER_STATUSES) ? $f['status'] : null;
    $s = $pdo->prepare('SELECT o.*,u.name,u.email,(SELECT COALESCE(SUM(qty),0) FROM order_items WHERE order_id=o.id) n FROM orders o JOIN users u ON u.id=o.user_id' . ($st ? ' WHERE o.status=?' : '') . ' ORDER BY o.id DESC');
    $s->execute($st ? [$st] : []);
    return ['Customer Orders' . ($st ? " ($st)" : ''), ['Order #', 'Customer', 'Email', 'Date', 'Items', 'Total', 'Status', 'Address'], array_map(fn($o) => [$o['id'], $o['name'], $o['email'], $o['created_at'], $o['n'], number_format($o['total'], 2, '.', ''), $o['status'], $o['address']], $s->fetchAll())];
  }
  http_response_code(404); die('Unknown report.');
}
function tracker($status){
  if ($status === 'Cancelled') return '<p><span class="pill st-cancelled">Cancelled</span> This order was cancelled and the stock returned.</p>';
  $steps = ['Pending', 'Processing', 'Shipped', 'Completed']; $i = array_search($status, $steps);
  $h = '<ol class="track" style="--p:' . ($i / 3) . '">';
  foreach ($steps as $k => $s) $h .= '<li class="' . ($k < $i ? 'done' : ($k == $i ? 'now' : '')) . '"><span>' . ($k <= $i ? '✓' : $k + 1) . '</span><em>' . $s . '</em></li>';
  return $h . '</ol>';
}

// ---------- layout ----------
function aurora(){ return '<div class="aurora" aria-hidden="true"><i></i><i></i><i></i></div>'; }
function top($title, $act = ''){
  $u = me(); $f = flash();
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · BookNest</title><link rel="stylesheet" href="style.css"></head><body>' . aurora();
  if ($u) {
    $items = [['bookshelf.php', 'shelf', '📚', 'Bookshelf'], ['shop.php', 'shop', '🛍️', 'Shop'], ['cart.php', 'cart', '🛒', 'Cart'], ['orders.php', 'orders', '📦', 'Orders']];
    if ($u['role'] === 'admin') $items[] = ['admin.php', 'admin', '⚙️', 'Admin'];
    $n = cart_count($u['id']);
    echo '<header class="nav glass"><a class="brand" href="bookshelf.php"><span class="lg">📖</span>BookNest</a><nav class="links">';
    foreach ($items as [$h, $k, $i, $l]) echo '<a href="' . $h . '" class="' . ($act === $k ? 'on' : '') . '"><span class="ic">' . $i . '</span>' . $l . ($k === 'cart' && $n ? '<b class="badge">' . $n . '</b>' : '') . '</a>';
    echo '</nav><form class="me" method="post" action="logout.php">' . tf() . '<span>' . e($u['name']) . '</span><button class="btn ghost sm">Log out</button></form></header>';
  }
  echo '<main class="wrap">';
  if ($f) echo '<div class="toast ' . $f[1] . '" role="status">' . e($f[0]) . '</div>';
}
function bottom(){
  $js = <<<'JS'
document.querySelectorAll("[data-confirm]").forEach(function(f){f.addEventListener("submit",function(e){if(!confirm(f.dataset.confirm))e.preventDefault()})});
var t=document.querySelector(".toast");if(t)setTimeout(function(){t.classList.add("bye")},4200);
document.querySelectorAll("[data-cover]").forEach(function(w){
  var p=w.querySelector(".prev"),f=w.querySelector("input[type=file]"),u=w.querySelector("[name=cover_url]"),n=w.querySelector(".fname");
  function show(s){var i=new Image();i.className="cv";i.alt="Cover preview";i.onload=function(){p.replaceChildren(i)};i.src=s}
  f.addEventListener("change",function(){var x=f.files[0];if(!x)return;
    if(x.size>15728640){n.textContent="That photo is over 15 MB. Please pick a smaller one.";f.value="";return}
    n.textContent=x.name+" ("+Math.round(x.size/1024)+" KB)";u.value="";show(URL.createObjectURL(x))});
  u.addEventListener("change",function(){if(/^https?:\/\//i.test(u.value)){f.value="";n.textContent="Using the image link";show(u.value)}});
});
JS;
  echo '</main><script>' . $js . '</script></body></html>';
}
function nest_svg(){
  return <<<'SVG'
<svg class="nestsvg" viewBox="0 0 300 250" aria-hidden="true">
<path d="M18 205C80 188 150 200 282 150" stroke="#8a5a2b" stroke-width="12" fill="none" stroke-linecap="round"/>
<path d="M36 200c-30-8-24-40-12-52 26 8 34 34 12 52z" fill="#7bb661"/><path d="M240 166c10-32 42-34 52-24-4 28-26 36-52 24z" fill="#7bb661"/>
<ellipse cx="150" cy="170" rx="106" ry="46" fill="#d9a05b"/>
<circle cx="150" cy="108" r="54" fill="#fff"/><path d="M138 60q12-24 26 0z" fill="#fff"/>
<circle cx="118" cy="120" r="9" fill="#f9a8d4" opacity=".85"/><circle cx="182" cy="120" r="9" fill="#f9a8d4" opacity=".85"/>
<path d="M116 104q9-10 18 0M166 104q9-10 18 0" stroke="#4c1d95" stroke-width="4" fill="none" stroke-linecap="round"/>
<path d="M142 112h16l-8 12z" fill="#fb923c"/>
<path d="M98 142l52 14 52-14v58l-52 14-52-14z" fill="#8b5cf6"/><path d="M150 156v58" stroke="#c4b5fd" stroke-width="3"/>
<path d="M112 160l28 8M112 172l28 8M188 160l-28 8M188 172l-28 8" stroke="#ddd6fe" stroke-width="3" stroke-linecap="round"/>
<path d="M44 174C60 238 240 238 256 174 220 204 80 204 44 174z" fill="#c98339"/>
<path d="M70 202q40 18 80 4M140 214q50 10 92-14" stroke="#8a5a2b" stroke-width="3" fill="none" opacity=".55" stroke-linecap="round"/>
</svg>
SVG;
}
function auth_top($title){
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · BookNest</title><link rel="stylesheet" href="style.css"></head><body class="auth">' . aurora()
     . '<main class="authwrap"><div class="authgrid"><aside class="mascot"><i class="dd" style="--x:6%;--y:8%" aria-hidden="true">✦</i><i class="dd" style="--x:88%;--y:18%;--d:-1.2s" aria-hidden="true">♥</i><i class="dd" style="--x:12%;--y:70%;--d:-2.2s" aria-hidden="true">★</i><i class="dd" style="--x:84%;--y:76%;--d:-3s" aria-hidden="true">✧</i>'
     . nest_svg() . '<p class="wordmark"><span>Book</span><span>Nest</span></p><p class="tag">Your books, safe in one nest ♥</p></aside><section class="glass authcard">';
}
function auth_bottom(){
  $js = <<<'JS'
document.querySelectorAll(".eye").forEach(function(b){b.addEventListener("click",function(){
  var i=b.parentElement.querySelector("input"),show=i.type==="password";
  i.type=show?"text":"password";b.classList.toggle("on",show);b.setAttribute("aria-pressed",show?"true":"false");b.setAttribute("aria-label",show?"Hide password":"Show password");
})});
JS;
  echo '</section></div></main><script>' . $js . '</script></body></html>';
}
function admin_nav($a){
  $t = ['admin.php' => ['dash', 'Dashboard'], 'admin_books.php' => ['books', 'Books & stock'], 'admin_orders.php' => ['orders', 'Orders'], 'admin_users.php' => ['users', 'Users']];
  echo '<nav class="sub">'; foreach ($t as $h => [$k, $l]) echo '<a href="' . $h . '" class="' . ($a === $k ? 'on' : '') . '">' . $l . '</a>'; echo '</nav>';
}
