<?php
require 'inc.php'; need_admin(); $me = me();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_token(); $id = (int)($_POST['id'] ?? 0); $a = $_POST['a'] ?? '';
  if ($id === (int)$me['id']) flash('You cannot change your own account here.', 'err');
  elseif ($a === 'active') { $pdo->prepare('UPDATE users SET active=1-active WHERE id=?')->execute([$id]); flash('Account updated.'); }
  elseif ($a === 'role') { $pdo->prepare("UPDATE users SET role=CASE role WHEN 'admin' THEN 'user' ELSE 'admin' END WHERE id=?")->execute([$id]); flash('Role updated.'); }
  elseif ($a === 'delete') { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]); flash('User deleted along with their shelf, cart and orders.'); }
  go('admin_users.php');
}
$rows = $pdo->query('SELECT u.*,(SELECT COUNT(*) FROM orders WHERE user_id=u.id) ocount,(SELECT COUNT(*) FROM shelf WHERE user_id=u.id) books FROM users u ORDER BY u.id')->fetchAll();
top('Users', 'admin'); ?>
<div class="head"><h1>Users</h1></div><?php admin_nav('users'); ?>
<section class="glass tw"><table><thead><tr><th>Name</th><th>Role</th><th>Shelf</th><th>Orders</th><th>Joined</th><th>Account</th></tr></thead><tbody>
<?php foreach ($rows as $r): $self = $r['id'] == $me['id']; ?><tr>
  <td data-l="Name"><b><?= e($r['name']) ?></b><p class="mut sm"><?= e($r['email']) ?></p></td>
  <td data-l="Role"><span class="pill <?= $r['role'] === 'admin' ? 'st-processing' : '' ?>"><?= e($r['role']) ?></span></td>
  <td data-l="Shelf"><?= (int)$r['books'] ?></td><td data-l="Orders"><?= (int)$r['ocount'] ?></td><td data-l="Joined"><?= e(substr($r['created_at'], 0, 10)) ?></td>
  <td class="acts"><?php if ($self): ?><span class="mut sm">You</span><?php else: ?>
    <form method="post"><?= tf() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="a" value="active"><button class="btn ghost sm"><?= $r['active'] ? 'Disable' : 'Enable' ?></button></form>
    <form method="post" data-confirm="Change this user's role?"><?= tf() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="a" value="role"><button class="btn ghost sm"><?= $r['role'] === 'admin' ? 'Make user' : 'Make admin' ?></button></form>
    <form method="post" data-confirm="Delete <?= e($r['name']) ?> and all their data?"><?= tf() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="a" value="delete"><button class="btn danger sm">Delete</button></form><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></section>
<?php bottom();
