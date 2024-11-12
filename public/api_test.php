<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/kindeAuthentication.php';
require_once __DIR__ . '/../src/kindeApi.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Initialize API client
$api = new kindeApi();

// Make a simple API call (for example, try to get a user by email)
$result = $api->getUserIdByEmailAddress('test@example.com');

// Return the result
header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
    'session' => $_SESSION
]);
