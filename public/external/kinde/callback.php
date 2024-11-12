<?php
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/kindeAuthentication.php';

try {
    $auth = new KindeAuthentication();
    $auth->callback();
    header('Location: /test.php');
} catch (Exception $e) {
    error_log('Kinde callback error: ' . $e->getMessage());
    header('Location: /test.php?error=callback_failed');
}
exit();
