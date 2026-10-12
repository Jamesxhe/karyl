<?php
// Printable report: use the browser's Print dialog or "Save as PDF".
require 'inc.php'; need_login();
[$title, $head, $rows] = dataset($_GET['type'] ?? '', $_GET);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · BookNest</title>
<style>
body{font:14px/1.5 system-ui,sans-serif;margin:0;padding:24px;color:#1b1030;background:#fff}
h1{margin:0;color:#5b21b6}.meta{color:#6b6480;margin:2px 0 16px}
.bar{display:flex;gap:8px;margin-bottom:18px}.bar button,.bar a{padding:10px 16px;border:0;border-radius:999px;background:#7c3aed;color:#fff;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
.bar a{background:#ede9fe;color:#4c1d95}
.tw{overflow-x:auto}table{width:100%;border-collapse:collapse}th{background:#ede9fe;text-align:left}th,td{padding:8px 10px;border-bottom:1px solid #ddd6fe;font-size:13px}
@media print{.bar{display:none}body{padding:0}th{background:#eee!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}tr{break-inside:avoid}}
</style></head><body>
<div class="bar"><button onclick="window.print()">Print / Save as PDF</button><a href="javascript:history.back()">Back</a></div>
<h1>BookNest · <?= e($title) ?></h1>
<p class="meta">Generated <?= date('Y-m-d H:i') ?> for <?= e(me()['name']) ?> · <?= count($rows) ?> row<?= count($rows) == 1 ? '' : 's' ?></p>
<div class="tw"><table><thead><tr><?php foreach ($head as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $c): ?><td><?= e($c) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="<?= count($head) ?>">No records.</td></tr><?php endif; ?>
</tbody></table></div></body></html>
