<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}
redirect('login.php');