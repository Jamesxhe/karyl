<?php
require 'inc.php'; need_login(); $u = me(); $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$s = $pdo->prepare('SELECT o.*,us.name,us.email FROM orders o JOIN users us ON us.id=o.user_id WHERE o.id=?'); $s->execute([$id]); $o = $s->fetch();
if (!$o || ($o['user_id'] != $u['id'] && $u['role'] !== 'admin')) { http_response_code(404); die('Order not found.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $u['role'] === 'admin') { check_token(); flash(set_order_status($id, $_POST['status'] ?? '') ? 'Order status updated.' : 'No change made.'); go("order.php?id=$id"); }
$it = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$id]); $items = $it->fetchAll();
top('Order #' . $id, $u['role'] === 'admin' && $o['user_id'] != $u['id'] ? 'admin' : 'orders'); ?>
<div class="head"><div><h1>Order #<?= $id ?></h1><p class="mut">Placed <?= e($o['created_at']) ?> by <?= e($o['name']) ?></p></div>
  <span><button class="btn ghost sm" onclick="window.print()">Print / Save as PDF</button> <a class="btn ghost sm" href="<?= $u['role'] === 'admin' ? 'admin_orders.php' : 'orders.php' ?>">Back</a></span></div>
<section class="glass formcard"><h2>Order tracking</h2><?= tracker($o['status']) ?>
  <?php if ($u['role'] === 'admin'): ?><form class="frow" method="post"><?= tf() ?><input type="hidden" name="id" value="<?= $id ?>">
    <select name="status" aria-label="Order status"><?php foreach (ORDER_STATUSES as $x): ?><option <?= $o['status'] === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select><button class="btn glow sm">Update status</button></form><?php endif; ?></section>
<div class="split">
  <section class="glass tw"><table><thead><tr><th>Book</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td data-l="Book"><?= e($i['title']) ?></td><td data-l="Price"><?= money($i['price']) ?></td><td data-l="Qty"><?= (int)$i['qty'] ?></td><td data-l="Subtotal"><?= money($i['price'] * $i['qty']) ?></td></tr><?php endforeach; ?>
  </tbody></table><p class="total"><span>Total</span><b><?= money($o['total']) ?></b></p></section>
  <section class="glass formcard"><h2>Delivery</h2><p><?= nl2br(e($o['address'])) ?></p><p class="mut"><?= e($o['phone']) ?><br><?= e($o['email']) ?></p></section>
</div>
<?php bottom();
