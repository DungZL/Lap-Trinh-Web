<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin();

$qs = $_SERVER['QUERY_STRING'] ?? '';
redirect('admin/orders.php' . ($qs !== '' ? '?' . $qs : ''));
