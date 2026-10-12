<?php
// CSV export (opens directly in Excel). Cells starting with = + - @ are neutralised against CSV injection.
require 'inc.php'; need_login();
$type = $_GET['type'] ?? '';
[$title, $head, $rows] = dataset($type, $_GET);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="booknest-' . preg_replace('/\W+/', '-', $type) . '-' . date('Y-m-d') . '.csv"');
echo "\xEF\xBB\xBF";
$o = fopen('php://output', 'w');
$safe = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
fputcsv($o, $head, ',', '"', '\\');
foreach ($rows as $r) fputcsv($o, array_map($safe, $r), ',', '"', '\\');
