<?php
require __DIR__ . '/config.php';
session_start();
if (!empty($_SESSION['user_id'])) {
    json_out([
        'logged_in'  => true,
        'first_name' => $_SESSION['first_name'],
        'last_name'  => $_SESSION['last_name'] ?? '',
        'email'      => $_SESSION['email'],
        'is_admin'   => !empty($_SESSION['is_admin']),
    ]);
} else {
    json_out(['logged_in' => false]);
}
