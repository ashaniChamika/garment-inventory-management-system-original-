<?php
require_once __DIR__ . '/config/config.php';

logout();
session_start();
flash('success', 'You have been signed out successfully.');
redirect('login.php');