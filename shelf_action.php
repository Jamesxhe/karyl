<?php
require 'inc.php'; need_login(); $u = me();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') go('bookshelf.php');
check_token();
$a = $_POST['a'] ?? ''; $id = (int)($_POST['id'] ?? 0);
if ($a === 'progress') {
  $s = $pdo->prepare('SELECT * FROM shelf WHERE id=? AND user_id=?'); $s->execute([$id, $u['id']]); $b = $s->fetch();
  if ($b) {
    $pg = max(0, (int)($_POST['page'] ?? 0)); if ($b['pages'] > 0) $pg = min($pg, $b['pages']);
    $st = $b['status'];
    if ($b['pages'] > 0 && $pg >= $b['pages']) { $st = 'finished'; flash('Finished! Nice work. Add a rating when you have a moment.'); }
    elseif ($pg > 0 && $st === 'want') $st = 'reading';
    $pdo->prepare('UPDATE shelf SET current_page=?,status=? WHERE id=?')->execute([$pg, $st, $id]);
    if (!flash_has()) flash('Progress saved.');
  }
} elseif ($a === 'delete') {
  $pdo->prepare('DELETE FROM shelf WHERE id=? AND user_id=?')->execute([$id, $u['id']]); flash('Book removed from your shelf.');
} elseif ($a === 'from_catalog') {
  flash(shelf_add_from_catalog($u['id'], (int)($_POST['cid'] ?? 0)) ? 'Saved to your shelf as Want to Read.' : 'That book is already on your shelf.');
}
back();
function flash_has(){ return isset($_SESSION['fl']); }
