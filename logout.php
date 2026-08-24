<?php
require_once __DIR__ . '/config/bootstrap.php';
auth_logout();
redirect('index.php');
