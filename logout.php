<?php
require 'inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { check_token(); $_SESSION = []; session_destroy(); }
go('login.php');
