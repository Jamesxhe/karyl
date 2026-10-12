<?php
require 'inc.php'; need_login(); $u = me(); $uid = $u['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_token(); if (isset($_POST['rm'])) { $_POST['a'] = 'remove'; $_POST['id'] = $_POST['rm']; }
  $a = $_POST['a'] ?? ''; $id = (int)($_POST['id'] ?? 0);
  if ($a === 'add') {
    $k = $pdo->prepare('SELECT stock FROM catalog WHERE id=?'); $k->execute([$id]); $stock = $k->fetchColumn();
    if ($stock === false || $stock <= 0) flash('Sorry, that book is out of stock.', 'err');
    else {
      $pdo->prepare('INSERT IGNORE INTO cart(user_id,catalog_id,qty) VALUES(?,?,0)')->execute([$uid, $id]);
      $pdo->prepare('UPDATE cart SET qty=LEAST(qty+1,?) WHERE user_id=? AND catalog_id=?')->execute([$stock, $uid, $id]);
      flash('Added to your cart.');
    }
    back('shop.php');
  }
  if ($a === 'update') {
    foreach (($_POST['qty'] ?? []) as $cid => $n) {
      $k = $pdo->prepare('SELECT stock FROM catalog WHERE id=?'); $k->execute([(int)$cid]); $stock = (int)$k->fetchColumn(); $n = (int)$n;
      if ($n <= 0) $pdo->prepare('DELETE FROM cart WHERE user_id=? AND catalog_id=?')->execute([$uid, (int)$cid]);
      else $pdo->prepare('UPDATE cart SET qty=? WHERE user_id=? AND catalog_id=?')->execute([max(1, min($n, $stock)), $uid, (int)$cid]);
    }
    flash('Cart updated.'); go('cart.php');
  }
  if ($a === 'remove') { $pdo->prepare('DELETE FROM cart WHERE user_id=? AND catalog_id=?')->execute([$uid, $id]); flash('Item removed.'); go('cart.php'); }
  if ($a === 'checkout') {
    $addr = trim($_POST['address'] ?? ''); $ph = trim($_POST['phone'] ?? '');
    if (mb_strlen($addr) < 8 || strlen($ph) < 7) { flash('Please enter your delivery address and phone number.', 'err'); go('cart.php'); }
    $pdo->beginTransaction();
    $s = $pdo->prepare('SELECT c.catalog_id,c.qty,k.title,k.price,k.stock FROM cart c JOIN catalog k ON k.id=c.catalog_id WHERE c.user_id=?'); $s->execute([$uid]); $items = $s->fetchAll();
    if (!$items) { $pdo->rollBack(); go('cart.php'); }
    foreach ($items as $i) if ($i['qty'] > $i['stock']) { $pdo->rollBack(); flash('“' . $i['title'] . '” only has ' . $i['stock'] . ' left. Please update your cart.', 'err'); go('cart.php'); }
    $total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $items));
    $pdo->prepare('INSERT INTO orders(user_id,total,address,phone) VALUES(?,?,?,?)')->execute([$uid, $total, $addr, $ph]); $oid = (int)$pdo->lastInsertId();
    foreach ($items as $i) {
      $pdo->prepare('INSERT INTO order_items(order_id,catalog_id,title,price,qty) VALUES(?,?,?,?,?)')->execute([$oid, $i['catalog_id'], $i['title'], $i['price'], $i['qty']]);
      $pdo->prepare('UPDATE catalog SET stock=stock-? WHERE id=?')->execute([$i['qty'], $i['catalog_id']]);
    }
    $pdo->prepare('DELETE FROM cart WHERE user_id=?')->execute([$uid]); $pdo->commit();
    flash('Order #' . $oid . ' placed! Track it below.'); go('order.php?id=' . $oid);
  }
  go('cart.php');
}
$s = $pdo->prepare('SELECT c.qty,k.* FROM cart c JOIN catalog k ON k.id=c.catalog_id WHERE c.user_id=? ORDER BY k.title'); $s->execute([$uid]); $items = $s->fetchAll();
$total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $items));
top('Cart', 'cart'); ?>
<div class="head"><h1>Your Cart</h1><a class="btn ghost" href="shop.php">Keep shopping</a></div>
<?php if (!$items): ?><div class="glass empty"><p>Your cart is empty.</p><a class="btn glow" href="shop.php">Browse books</a></div>
<?php else: ?>
<div class="split">
  <form class="glass formcard" method="post"><?= tf() ?><input type="hidden" name="a" value="update">
    <?php foreach ($items as $i): ?>
    <div class="line"><div class="cvw xs"><?= cover($i['cover'], $i['title']) ?></div>
      <div class="grow"><b><?= e($i['title']) ?></b><p class="mut sm"><?= e($i['author']) ?> · <?= money($i['price']) ?></p></div>
      <input class="qty" type="number" name="qty[<?= $i['id'] ?>]" value="<?= (int)$i['qty'] ?>" min="0" max="<?= (int)$i['stock'] ?>" aria-label="Quantity for <?= e($i['title']) ?>">
      <b class="lt"><?= money($i['price'] * $i['qty']) ?></b>
      <button class="btn danger sm" name="rm" value="<?= $i['id'] ?>" formnovalidate aria-label="Remove <?= e($i['title']) ?>">✕</button>
    </div>
    <?php endforeach; ?>
    <button class="btn ghost">Update quantities</button>
  </form>
  <form class="glass formcard" method="post"><?= tf() ?><input type="hidden" name="a" value="checkout">
    <h2>Checkout</h2><p class="total"><span>Total</span><b><?= money($total) ?></b></p>
    <label>Delivery address<textarea name="address" rows="3" required placeholder="House no., street, city"></textarea></label>
    <label>Phone<input name="phone" inputmode="tel" required></label>
    <button class="btn glow wide">Place order</button><p class="mut sm">Payment is collected on delivery.</p>
  </form>
</div>
<?php endif; bottom();
