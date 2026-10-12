<?php
require 'inc.php'; need_admin();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_token(); $a = $_POST['a'] ?? ''; $id = (int)($_POST['id'] ?? 0);
  if ($a === 'delete') { $pdo->prepare('DELETE FROM catalog WHERE id=?')->execute([$id]); flash('Book deleted from the catalog.'); go('admin_books.php'); }
  if ($a === 'stock') { $pdo->prepare('UPDATE catalog SET stock=? WHERE id=?')->execute([max(0, (int)$_POST['stock']), $id]); flash('Stock updated.'); go('admin_books.php?' . http_build_query(['q' => $_POST['q'] ?? ''])); }
  if ($a === 'save') {
    $b = []; foreach (['title', 'author', 'isbn', 'genre', 'description'] as $k) $b[$k] = trim($_POST[$k] ?? '');
    $b['price'] = (float)($_POST['price'] ?? 0); $b['stock'] = max(0, (int)($_POST['stock'] ?? 0)); $b['pages'] = max(0, (int)($_POST['pages'] ?? 0));
    $old = ''; if ($id) { $o = $pdo->prepare('SELECT cover FROM catalog WHERE id=?'); $o->execute([$id]); $old = (string)$o->fetchColumn(); }
    [$b['cover'], $ce] = save_cover($old);
    if ($b['title'] === '' || $b['author'] === '') $err = 'Title and author are required.';
    elseif ($b['price'] < 0) $err = 'Price cannot be negative.';
    elseif ($ce) $err = $ce;
    else {
      $v = [$b['title'], $b['author'], $b['isbn'], $b['genre'], $b['description'], $b['price'], $b['stock'], $b['pages'], $b['cover']];
      if ($id) { $pdo->prepare('UPDATE catalog SET title=?,author=?,isbn=?,genre=?,description=?,price=?,stock=?,pages=?,cover=? WHERE id=?')->execute([...$v, $id]); flash('Book updated.'); }
      else { $pdo->prepare('INSERT INTO catalog(title,author,isbn,genre,description,price,stock,pages,cover) VALUES(?,?,?,?,?,?,?,?,?)')->execute($v); flash('Book added to the catalog.'); }
      go('admin_books.php');
    }
    $edit = $b + ['id' => $id];
  }
}
$edit = $edit ?? null;
if (!$edit && isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM catalog WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch() ?: null; }
if (!$edit && isset($_GET['new'])) $edit = ['id' => 0, 'title' => '', 'author' => '', 'isbn' => '', 'genre' => '', 'description' => '', 'price' => '', 'stock' => 10, 'pages' => '', 'cover' => ''];
$q = trim($_GET['q'] ?? ''); $w = ''; $p = [];
if ($q !== '') { $w = ' WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? OR genre LIKE ?'; $p = array_fill(0, 4, "%$q%"); }
$s = $pdo->prepare("SELECT * FROM catalog$w ORDER BY title"); $s->execute($p); $rows = $s->fetchAll();
top('Books & stock', 'admin'); ?>
<div class="head"><h1>Books &amp; stock</h1><a class="btn glow" href="?new=1">+ New book</a></div><?php admin_nav('books'); ?>
<?php if ($err): ?><p class="toast err static" role="alert"><?= e($err) ?></p><?php endif; ?>
<?php if ($edit): ?>
<form class="glass formcard" method="post" enctype="multipart/form-data"><?= tf() ?><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <h2><?= $edit['id'] ? 'Edit book' : 'New book' ?></h2>
  <div class="fgrid">
    <label>Title<input name="title" value="<?= e($edit['title']) ?>" required></label><label>Author<input name="author" value="<?= e($edit['author']) ?>" required></label>
    <label>ISBN<input name="isbn" value="<?= e($edit['isbn']) ?>"></label><label>Genre<input name="genre" value="<?= e($edit['genre']) ?>"></label>
    <label>Price (<?= CUR ?>)<input type="number" step="0.01" min="0" name="price" value="<?= e($edit['price']) ?>" required></label><label>Stock<input type="number" min="0" name="stock" value="<?= e($edit['stock']) ?>" required></label>
    <label>Pages<input type="number" min="0" name="pages" value="<?= e($edit['pages']) ?>"></label>
  </div>
  <?= cover_field($edit['cover'], $edit['title']) ?>
  <label>Description<textarea name="description" rows="3"><?= e($edit['description']) ?></textarea></label>
  <div class="acts"><button class="btn glow">Save book</button><a class="btn ghost" href="admin_books.php">Cancel</a></div>
</form>
<?php endif; ?>
<form class="glass filters" method="get"><div class="frow"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search the catalog" aria-label="Search"><button class="btn sm">Search</button>
  <a class="btn ghost sm" href="export.php?type=inventory">Export CSV (Excel)</a><a class="btn ghost sm" href="report.php?type=inventory" target="_blank">Print / PDF</a></div></form>
<section class="glass tw"><table><thead><tr><th>Book</th><th>Genre</th><th>Price</th><th>Stock</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $b): ?><tr>
  <td data-l="Book"><div class="mini"><div class="cvw xs"><?= cover($b['cover'], $b['title']) ?></div><div><b><?= e($b['title']) ?></b><p class="mut sm"><?= e($b['author']) ?></p></div></div></td>
  <td data-l="Genre"><?= e($b['genre']) ?></td><td data-l="Price"><?= money($b['price']) ?></td>
  <td data-l="Stock"><form class="prog" method="post"><?= tf() ?><input type="hidden" name="a" value="stock"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
    <input type="number" name="stock" min="0" value="<?= (int)$b['stock'] ?>" aria-label="Stock for <?= e($b['title']) ?>"><button class="btn sm">Save</button></form></td>
  <td class="acts"><a class="btn ghost sm" href="?edit=<?= $b['id'] ?>">Edit</a>
    <form method="post" data-confirm="Delete “<?= e($b['title']) ?>” from the catalog?"><?= tf() ?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn danger sm">Delete</button></form></td>
</tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="5">No books found.</td></tr><?php endif; ?></tbody></table></section>
<?php bottom();
