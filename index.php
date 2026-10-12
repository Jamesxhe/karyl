<?php
require 'inc.php';
go(me() ? (me()['role'] === 'admin' ? 'admin.php' : 'bookshelf.php') : 'login.php');
