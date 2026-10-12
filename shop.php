<?php
require 'inc.php'; need_login();
$q = trim($_GET['q'] ?? ''); $g = $_GET['genre'] ?? ''; $sort = $_GET['sort'] ?? 'title';
$w = []; $p = [];
if ($q !== '') { $w[] = '(title LIKE ? OR author LIKE ? OR isbn LIKE ? OR genre LIKE ?)'; array_push($p, ...array_fill(0, 4, "%$q%")); }
if ($g !== '') { $w[] = 'genre=?'; $p[] = $g; }
$o = ['price' => 'price ASC', 'price_desc' => 'price DESC', 'new' => 'id DESC'][$sort] ?? 'title';
$s = $pdo->prepare('SELECT * FROM catalog' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY (stock=0), $o"); $s->execute($p); $rows = $s->fetchAll();
$genres = array_column($pdo->query("SELECT DISTINCT genre v FROM catalog WHERE genre<>'' ORDER BY genre")->fetchAll(), 'v');
top('Shop', 'shop'); ?>
<div class="head"><div><h1>Book Shop</h1><p class="mut">Browse available books and add them to your cart.</p></div><a class="btn ghost" href="cart.php">View cart</a></div>
<form class="glass filters" method="get"><div class="frow">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search title, author, ISBN or genre" aria-label="Search">
  <select name="genre" onchange="this.form.submit()" aria-label="Genre"><option value="">All genres</option><?php foreach ($genres as $x): ?><option <?= $g === $x ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?></select>
  <select name="sort" onchange="this.form.submit()" aria-label="Sort"><?php foreach (['title' => 'Title', 'price' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'new' => 'Newest'] as $k => $l): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <button class="btn sm">Search</button></div></form>
<?php if (!$rows): ?><div class="glass empty"><p>No books match your search.</p><a class="btn ghost" href="shop.php">Clear filters</a></div><?php else: ?>
<section class="grid">
<?php foreach ($rows as $i => $b): ?>
  <article class="book glass" style="--i:<?= min($i, 12) ?>">
    <div class="cvw"><?= cover($b['cover'], $b['title']) ?></div>
    <div class="bi">
      <h3><?= e($b['title']) ?></h3><p class="by"><?= e($b['author']) ?></p>
      <p class="pills"><?= $b['genre'] ? '<span class="pill">' . e($b['genre']) . '</span>' : '' ?>
        <?= $b['stock'] <= 0 ? '<span class="pill st-cancelled">Out of stock</span>' : ($b['stock'] <= 5 ? '<span class="pill st-pending">Only ' . (int)$b['stock'] . ' left</span>' : '<span class="pill st-completed">In stock</span>') ?></p>
      <p class="mut sm clamp"><?= e($b['description']) ?></p>
      <p class="price"><?= money($b['price']) ?></p>
      <div class="acts">
        <form method="post" action="cart.php"><?= tf() ?><input type="hidden" name="a" value="add"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn glow sm" <?= $b['stock'] <= 0 ? 'disabled' : '' ?>>Add to cart</button></form>
        <form method="post" action="shelf_action.php"><?= tf() ?><input type="hidden" name="a" value="from_catalog"><input type="hidden" name="cid" value="<?= $b['id'] ?>"><input type="hidden" name="back" value="shop.php"><button class="btn ghost sm" title="Save to my bookshelf">♡ Save</button></form>
      </div>
    </div>
  </article>
<?php endforeach; ?></section><?php endif; bottom();
