<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
header('Location: login.php');
