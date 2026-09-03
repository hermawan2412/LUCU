<?php
require_once __DIR__ . '/config/bootstrap.php';
if (auth_check()) {
    log_aktivitas($db, 'logout');
}
auth_logout();
redirect('index.php');
