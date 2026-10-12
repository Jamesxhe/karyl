<?php
require 'inc.php'; need_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_token(); flash(set_order_status((int)($_POST['id'] ?? 0), $_POST['status'] ?? '') ? 'Order #' . (int)$_POST['id'] . ' is now ' . $_POST['status'] . '.' : 'No change made.');
  go('admin_orders.php' . (($_POST['f'] ?? '') !== '' ? '?status=' . urlencode($_POST['f']) : ''));
}
$f = in_array($_GET['status'] ?? '', ORDER_STATUSES) ? $_GET['status'] : '';
$s = $pdo->prepare('SELECT o.*,u.name,u.email,(SELECT COALESCE(SUM(qty),0) FROM order_items WHERE order_id=o.id) n FROM orders o JOIN users u ON u.id=o.user_id' . ($f ? ' WHERE o.status=?' : '') . ' ORDER BY o.id DESC');
$s->execute($f ? [$f] : []); $rows = $s->fetchAll();
top('Orders', 'admin'); ?>
<div class="head"><h1>Customer orders</h1><span><a class="btn ghost sm" href="export.php?type=allorders&status=<?= urlencode($f) ?>">Export CSV (Excel)</a> <a class="btn ghost sm" href="report.php?type=allorders&status=<?= urlencode($f) ?>" target="_blank">Print / PDF</a></span></div><?php admin_nav('orders'); ?>
<div class="chips pad"><a class="chip <?= $f === '' ? 'on' : '' ?>" href="admin_orders.php">All</a><?php foreach (ORDER_STATUSES as $x): ?><a class="chip <?= $f === $x ? 'on' : '' ?>" href="?status=<?= $x ?>"><?= $x ?></a><?php endforeach; ?></div>
<section class="glass tw"><table><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $o): ?><tr>
  <td data-l="Order"><a href="order.php?id=<?= $o['id'] ?>">#<?= $o['id'] ?></a></td><td data-l="Customer"><?= e($o['name']) ?><p class="mut sm"><?= e($o['email']) ?></p></td>
  <td data-l="Date"><?= e(substr($o['created_at'], 0, 10)) ?></td><td data-l="Items"><?= (int)$o['n'] ?></td><td data-l="Total"><?= money($o['total']) ?></td>
  <td data-l="Status"><form class="prog" method="post"><?= tf() ?><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="f" value="<?= e($f) ?>">
    <select name="status" aria-label="Status of order <?= $o['id'] ?>"><?php foreach (ORDER_STATUSES as $x): ?><option <?= $o['status'] === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select><button class="btn sm">Update</button></form></td>
  <td><a class="btn ghost sm" href="order.php?id=<?= $o['id'] ?>">View</a></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7">No orders found.</td></tr><?php endif; ?></tbody></table></section>
<?php bottom();
