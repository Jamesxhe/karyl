<?php
require 'inc.php'; need_login(); $u = me();
$s = $pdo->prepare('SELECT o.*,(SELECT COALESCE(SUM(qty),0) FROM order_items WHERE order_id=o.id) n FROM orders o WHERE user_id=? ORDER BY id DESC'); $s->execute([$u['id']]); $rows = $s->fetchAll();
top('My Orders', 'orders'); ?>
<div class="head"><h1>My Orders</h1><span><a class="btn ghost sm" href="export.php?type=orders">Export CSV (Excel)</a> <a class="btn ghost sm" href="report.php?type=orders" target="_blank">Print / PDF</a></span></div>
<?php if (!$rows): ?><div class="glass empty"><p>You haven't ordered anything yet.</p><a class="btn glow" href="shop.php">Browse books</a></div>
<?php else: ?><section class="glass tw"><table>
  <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $o): ?><tr><td data-l="Order">#<?= $o['id'] ?></td><td data-l="Date"><?= e(substr($o['created_at'], 0, 10)) ?></td><td data-l="Items"><?= (int)$o['n'] ?></td>
    <td data-l="Total"><?= money($o['total']) ?></td><td data-l="Status"><?= pill($o['status']) ?></td><td><a class="btn ghost sm" href="order.php?id=<?= $o['id'] ?>">Track</a></td></tr><?php endforeach; ?>
</tbody></table></section><?php endif; bottom();
