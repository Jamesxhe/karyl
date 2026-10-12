<?php
require 'inc.php';
if (me()) go('bookshelf.php');
$err = ''; $v = ['name' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_token();
  $v = ['name' => trim($_POST['name'] ?? ''), 'email' => strtolower(trim($_POST['email'] ?? ''))];
  $pw = $_POST['password'] ?? '';
  $x = $pdo->prepare('SELECT 1 FROM users WHERE email=?'); $x->execute([$v['email']]);
  if (mb_strlen($v['name']) < 2) $err = 'Please enter your name.';
  elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email address.';
  elseif ($x->fetch()) $err = 'That email is already registered. Try logging in.';
  elseif (strlen($pw) < 6) $err = 'Password must be at least 6 characters.';
  elseif ($pw !== ($_POST['password2'] ?? '')) $err = 'Passwords do not match.';
  else {
    $pdo->prepare("INSERT INTO users(name,email,password,role,active) VALUES(?,?,?,'user',1)")->execute([$v['name'], $v['email'], password_hash($pw, PASSWORD_DEFAULT)]);
    session_regenerate_id(true); $_SESSION['uid'] = (int)$pdo->lastInsertId();
    flash('Welcome to BookNest! Add your first book to get started.'); go('bookshelf.php');
  }
}
auth_top('Create account'); ?>
<h2 class="hello">Join <b>Book Nest!</b></h2>
<p class="sub">Create your free account and start your shelf ♥</p>
<?php if ($err): ?><p class="toast err static" role="alert"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="stack">
  <?= tf() ?>
  <?= pf('user', 'name="name" value="' . e($v['name']) . '" placeholder="Your name" aria-label="Your name" autocomplete="name" required') ?>
  <?= pf('mail', 'type="email" name="email" value="' . e($v['email']) . '" placeholder="Email" aria-label="Email" autocomplete="email" inputmode="email" required') ?>
  <?= pf('lock', 'type="password" name="password" minlength="6" placeholder="Password (6+ characters)" aria-label="Password" autocomplete="new-password" required', true) ?>
  <?= pf('lock', 'type="password" name="password2" minlength="6" placeholder="Confirm password" aria-label="Confirm password" autocomplete="new-password" required', true) ?>
  <button class="btn glow wide">Create account ♥</button>
</form>
<p class="or">or</p>
<a class="btn ghost wide" href="login.php">I already have an account</a>
<?php auth_bottom();
