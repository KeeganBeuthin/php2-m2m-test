<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/kindeAuthentication.php';
require_once __DIR__ . '/../src/kindeApi.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Initialize authentication
$auth = new KindeAuthentication();
$auth->login();

// Create a simple HTML page with AJAX test
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kinde SDK Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Kinde SDK Test</h1>
    <div id="status">Logged in</div>
    <button id="testApi">Test API Call</button>
    <div id="result"></div>

    <script>
    $(document).ready(function() {
        $('#testApi').click(function() {
            $.ajax({
                url: 'api_test.php',
                method: 'POST',
                success: function(response) {
                    $('#result').html('API call completed. Check cookies in dev tools.');
                    console.log(response);
                },
                error: function(xhr, status, error) {
                    $('#result').html('Error: ' + error);
                }
            });
        });
    });
    </script>
</body>
</html>
