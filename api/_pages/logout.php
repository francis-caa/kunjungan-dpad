<?php
require_once __DIR__ . '/session_boot.php';
session_start();
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit;
