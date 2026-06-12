<?php
/**
 * admin/logout.php
 */
require_once __DIR__ . '/../includes/config.php';

unset($_SESSION['is_admin']);
session_destroy();

header('Location: login.php');
exit;
