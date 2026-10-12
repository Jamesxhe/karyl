<?php
require 'inc.php'; need_login(); $u = me();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$b = ['title' => '', 'author' => '', 'isbn' => '', 'genre' => '', 'status' => 'want', 'rating' => 0, 'pages' => '', 'current_page' => 0, 'cover' => '', 'notes' => ''];
if ($id) { $s = $pdo->prepare('SELECT * FROM shelf WHERE id=? AND user_id=?'); $s->execute([$id, $u['id']]); $b = $s->fetch() ?: die('Book not found.'); }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_token();
  foreach (['title', 'author', 'isbn', 'genre', 'notes'] as $k) $b[$k] = trim($_POST[$k] ?? '');
  $b['status'] = isset(READ[$_POST['status'] ?? '']) ? $_POST['status'] : 'want';
  $b['rating'] = max(0, min(5, (int)($_POST['rating'] ?? 0)));
  $b['pages'] = max(0, (int)($_POST['pages'] ?? 0)); $b['current_page'] = max(0, (int)($_POST['current_page'] ?? 0));
  [$b['cover'], $ce] = save_cover($b['cover']);
  if ($b['title'] === '' || $b['author'] === '') $err = 'Title and author are required.';
  elseif ($ce) $err = $ce;
  else {
    if ($b['pages'] > 0) $b['current_page'] = min($b['current_page'], $b['pages']);
    if ($b['status'] === 'finished' && $b['pages'] > 0) $b['current_page'] = $b['pages'];
    if ($b['status'] === 'want') $b['current_page'] = 0;
    $v = [$b['title'], $b['author'], $b['isbn'], $b['genre'], $b['status'], $b['rating'], $b['pages'], $b['current_page'], $b['cover'], mb_substr($b['notes'], 0, 1000)];
    if ($id) { $pdo->prepare('UPDATE shelf SET title=?,author=?,isbn=?,genre=?,status=?,rating=?,pages=?,current_page=?,cover=?,notes=? WHERE id=? AND user_id=?')->execute([...$v, $id, $u['id']]); flash('Book updated.'); }
    else { $pdo->prepare('INSERT INTO shelf(title,author,isbn,genre,status,rating,pages,current_page,cover,notes,user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([...$v, $u['id']]); flash('Book added to your shelf.'); }
    go('bookshelf.php');
  }
}
$gl = $pdo->prepare("SELECT DISTINCT genre FROM shelf WHERE user_id=? AND genre<>''"); $gl->execute([$u['id']]);
top($id ? 'Edit book' : 'Add book', 'shelf'); ?>
<div class="head"><h1><?= $id ? 'Edit book' : 'Add a book' ?></h1><a class="btn ghost" href="bookshelf.php">Back</a></div>
<?php if ($err): ?><p class="toast err static" role="alert"><?= e($err) ?></p><?php endif; ?>
<form class="glass formcard" method="post" enctype="multipart/form-data">
  <?= tf() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="fgrid">
    <label>Title<input name="title" value="<?= e($b['title']) ?>" required></label>
    <label>Author<input name="author" value="<?= e($b['author']) ?>" required></label>
    <label>ISBN<input name="isbn" value="<?= e($b['isbn']) ?>" inputmode="numeric"></label>
    <label>Genre<input name="genre" value="<?= e($b['genre']) ?>" list="gl"><datalist id="gl"><?php foreach ($gl->fetchAll() as $g): ?><option value="<?= e($g['genre']) ?>"><?php endforeach; ?></datalist></label>
    <label>Reading status<select name="status"><?php foreach (READ as $k => $l): ?><option value="<?= $k ?>" <?= $b['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    <label>My rating<select name="rating"><option value="0">Not rated</option><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= (int)$b['rating'] === $i ? 'selected' : '' ?>><?= stars($i) ?></option><?php endfor; ?></select></label>
    <label>Total pages<input type="number" name="pages" min="0" value="<?= e($b['pages']) ?>"></label>
    <label>Current page<input type="number" name="current_page" min="0" value="<?= e($b['current_page']) ?>"></label>
  </div>
  <?= cover_field($b['cover'], $b['title']) ?>
  <label>Notes<textarea name="notes" rows="3" maxlength="1000"><?= e($b['notes']) ?></textarea></label>
  <button class="btn glow wide"><?= $id ? 'Save changes' : 'Add to shelf' ?></button>
</form>
<?php bottom();
