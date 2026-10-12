<?php
require 'inc.php'; need_admin();
$q = fn($sql) => $pdo->query($sql)->fetchColumn();
$st = ['Customers' => $q("SELECT COUNT(*) FROM users WHERE role='user'"), 'Titles' => $q('SELECT COUNT(*) FROM catalog'), 'Units in stock' => $q('SELECT COALESCE(SUM(stock),0) FROM catalog'),
  'Low stock' => $q('SELECT COUNT(*) FROM catalog WHERE stock<=5'), 'Pending orders' => $q("SELECT COUNT(*) FROM orders WHERE status='Pending'"), 'Revenue' => money($q("SELECT COALESCE(SUM(total),0) FROM orders WHERE status<>'Cancelled'"))];
$by = array_column($pdo->query('SELECT status,COUNT(*) n FROM orders GROUP BY status')->fetchAll(), 'n', 'status'); $max = max(1, ...array_values($by ?: [1]));
$recent = $pdo->query('SELECT o.*,u.name FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 6')->fetchAll();
$low = $pdo->query('SELECT * FROM catalog WHERE stock<=5 ORDER BY stock,title LIMIT 6')->fetchAll();
top('Admin', 'admin'); ?>
<div class="head"><h1>Admin Dashboard</h1></div><?php admin_nav('dash'); ?>
<section class="stats"><?php foreach ($st as $l => $v): ?><div class="stat glass"><b><?= e($v) ?></b><span><?= e($l) ?></span></div><?php endforeach; ?></section>
<div class="split">
  <section class="glass formcard"><h2>Orders by status</h2>
    <?php foreach (ORDER_STATUSES as $s): $n = $by[$s] ?? 0; ?><div class="hb"><span><?= $s ?></span><div class="bar"><i style="--w:<?= round($n / $max * 100) ?>%"></i></div><b><?= $n ?></b></div><?php endforeach; ?>
    <h2 class="mt">Low stock</h2>
    <?php foreach ($low as $b): ?><p class="rowp"><span><?= e($b['title']) ?></span><?= $b['stock'] ? '<span class="pill st-pending">' . (int)$b['stock'] . ' left</span>' : '<span class="pill st-cancelled">Out</span>' ?></p><?php endforeach; ?>
    <?php if (!$low): ?><p class="mut">Everything is well stocked.</p><?php endif; ?>
  </section>
  <section class="glass tw"><h2 class="pad">Recent orders</h2><table><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($recent as $o): ?><tr><td data-l="Order"><a href="order.php?id=<?= $o['id'] ?>">#<?= $o['id'] ?></a></td><td data-l="Customer"><?= e($o['name']) ?></td><td data-l="Total"><?= money($o['total']) ?></td><td data-l="Status"><?= pill($o['status']) ?></td></tr><?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="4">No orders yet.</td></tr><?php endif; ?></tbody></table></section>
</div>
<?php bottom();
