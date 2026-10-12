<?php
require 'inc.php';
if (me()) go('bookshelf.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_token();
  if (($_SESSION['lock'] ?? 0) > time()) $err = 'Too many attempts. Wait 30 seconds and try again.';
  else {
    $s = $pdo->prepare('SELECT * FROM users WHERE email=?'); $s->execute([strtolower(trim($_POST['email'] ?? ''))]); $u = $s->fetch();
    if ($u && pw_ok($u, $_POST['password'] ?? '')) {
      if (!$u['active']) $err = 'This account is disabled. Please contact an admin.';
      else { session_regenerate_id(true); $_SESSION['uid'] = $u['id']; unset($_SESSION['fails']); go($u['role'] === 'admin' ? 'admin.php' : 'bookshelf.php'); }
    } else {
      $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
      if ($_SESSION['fails'] >= 5) { $_SESSION['lock'] = time() + 30; $_SESSION['fails'] = 0; }
      $err = 'Wrong email or password.';
    }
  }
}
auth_top('Log in'); ?>
<h2 class="hello">Welcome to <b>Book Nest!</b></h2>
<p class="sub">Log in to continue your reading journey ♥</p>
<?php if ($err): ?><p class="toast err static" role="alert"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="stack">
  <?= tf() ?>
  <?= pf('mail', 'type="email" name="email" placeholder="Email" aria-label="Email" autocomplete="email" inputmode="email" required autofocus') ?>
  <?= pf('lock', 'type="password" name="password" placeholder="Password" aria-label="Password" autocomplete="current-password" required', true) ?>
  <button class="btn glow wide">Login ♥</button>
</form>
<p class="or">or</p>
<a class="btn ghost wide" href="register.php">Create an Account</a>
<p class="demo"><b>Demo:</b> reader@booknest.test / reader123<br>Admin: admin@booknest.test / admin123</p>
<?php auth_bottom();
