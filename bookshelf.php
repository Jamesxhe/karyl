<?php
require 'inc.php'; need_login(); $u = me();
$f = array_intersect_key($_GET, array_flip(['q', 'status', 'genre', 'author', 'rating', 'sort']));
$f['q'] = trim($f['q'] ?? '');
$rows = shelf_rows($u['id'], $f);
$all = $pdo->prepare('SELECT status,COUNT(*) n,AVG(NULLIF(rating,0)) a FROM shelf WHERE user_id=? GROUP BY status'); $all->execute([$u['id']]);
$cnt = ['want' => 0, 'reading' => 0, 'finished' => 0]; $sum = 0; $tot = 0;
foreach ($all->fetchAll() as $r) { $cnt[$r['status']] = (int)$r['n']; $tot += $r['n']; }
$avg = $pdo->prepare('SELECT AVG(rating) FROM shelf WHERE user_id=? AND rating>0'); $avg->execute([$u['id']]); $avg = $avg->fetchColumn();
$opt = function($col) use ($pdo, $u){ $s = $pdo->prepare("SELECT DISTINCT $col v FROM shelf WHERE user_id=? AND $col<>'' ORDER BY $col"); $s->execute([$u['id']]); return array_column($s->fetchAll(), 'v'); };
$genres = $opt('genre'); $authors = $opt('author');
$qs = fn($o = []) => http_build_query(array_filter(array_merge($f, $o), fn($x) => $x !== '' && $x !== null));
top('My Bookshelf', 'shelf'); ?>
<div class="head"><div><h1>My Bookshelf</h1><p class="mut">Hi <?= e(explode(' ', $u['name'])[0]) ?>, here's what you're reading.</p></div>
  <a class="btn glow" href="shelf_form.php">+ Add book</a></div>

<section class="stats">
  <div class="stat glass"><b><?= $tot ?></b><span>Books</span></div>
  <div class="stat glass"><b><?= $cnt['reading'] ?></b><span>Reading</span></div>
  <div class="stat glass"><b><?= $cnt['finished'] ?></b><span>Finished</span></div>
  <div class="stat glass"><b><?= $cnt['want'] ?></b><span>Want to read</span></div>
  <div class="stat glass"><b><?= $avg ? number_format($avg, 1) : '–' ?></b><span>Avg rating</span></div>
</section>

<section class="glass filters">
  <div class="chips">
    <a class="chip <?= empty($f['status']) ? 'on' : '' ?>" href="?<?= $qs(['status' => '']) ?>">All</a>
    <?php foreach (READ as $k => $l): ?><a class="chip <?= ($f['status'] ?? '') === $k ? 'on' : '' ?>" href="?<?= $qs(['status' => $k]) ?>"><?= $l ?></a><?php endforeach; ?>
  </div>
  <form method="get" class="frow">
    <input type="hidden" name="status" value="<?= e($f['status'] ?? '') ?>">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search title, author, ISBN or genre" aria-label="Search">
    <select name="genre" onchange="this.form.submit()" aria-label="Genre"><option value="">All genres</option><?php foreach ($genres as $g): ?><option <?= ($f['genre'] ?? '') === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?></select>
    <select name="author" onchange="this.form.submit()" aria-label="Author"><option value="">All authors</option><?php foreach ($authors as $a): ?><option <?= ($f['author'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select>
    <select name="rating" onchange="this.form.submit()" aria-label="Minimum rating"><option value="">Any rating</option><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= (int)($f['rating'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?>★ & up</option><?php endfor; ?></select>
    <select name="sort" onchange="this.form.submit()" aria-label="Sort"><?php foreach (['recent' => 'Newest', 'title' => 'Title', 'author' => 'Author', 'rating' => 'Top rated'] as $k => $l): ?><option value="<?= $k ?>" <?= ($f['sort'] ?? 'recent') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn sm">Search</button>
  </form>
  <div class="tools"><span class="mut"><?= count($rows) ?> book<?= count($rows) == 1 ? '' : 's' ?></span>
    <span><a class="btn ghost sm" href="export.php?type=shelf&<?= $qs() ?>">Export CSV (Excel)</a> <a class="btn ghost sm" href="report.php?type=shelf&<?= $qs() ?>" target="_blank">Print / PDF</a></span></div>
</section>

<?php if (!$rows): ?>
  <div class="glass empty"><p>Nothing here yet.</p><a class="btn glow" href="shelf_form.php">Add your first book</a> <a class="btn ghost" href="shop.php">Browse the shop</a></div>
<?php else: ?>
<section class="grid">
<?php foreach ($rows as $i => $b): $p = pct($b['current_page'], $b['pages']); ?>
  <article class="book glass" style="--i:<?= min($i, 12) ?>">
    <a class="cvw" href="shelf_form.php?id=<?= $b['id'] ?>" aria-label="Edit <?= e($b['title']) ?>"><?= cover($b['cover'], $b['title']) ?></a>
    <div class="bi">
      <h3><?= e($b['title']) ?></h3><p class="by"><?= e($b['author']) ?></p>
      <p class="pills"><?= $b['genre'] ? '<span class="pill">' . e($b['genre']) . '</span>' : '' ?><?= pill($b['status']) ?></p>
      <p class="stars" aria-label="Rating <?= (int)$b['rating'] ?> of 5"><?= stars($b['rating']) ?></p>
      <?php if ($b['pages'] > 0): ?>
        <div class="bar" title="<?= $p ?>%"><i style="--w:<?= $p ?>%"></i></div>
        <p class="mut sm"><?= (int)$b['current_page'] ?> / <?= (int)$b['pages'] ?> pages · <?= $p ?>%</p>
        <?php if ($b['status'] !== 'finished'): ?>
        <form class="prog" method="post" action="shelf_action.php"><?= tf() ?><input type="hidden" name="a" value="progress"><input type="hidden" name="id" value="<?= $b['id'] ?>">
          <input type="number" name="page" min="0" max="<?= (int)$b['pages'] ?>" value="<?= (int)$b['current_page'] ?>" aria-label="Current page"><button class="btn sm">Update</button></form>
        <?php endif; ?>
      <?php endif; ?>
      <div class="acts"><a class="btn ghost sm" href="shelf_form.php?id=<?= $b['id'] ?>">Edit</a>
        <form method="post" action="shelf_action.php" data-confirm="Remove “<?= e($b['title']) ?>” from your shelf?"><?= tf() ?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn danger sm">Delete</button></form></div>
    </div>
  </article>
<?php endforeach; ?>
</section>
<?php endif; bottom();
