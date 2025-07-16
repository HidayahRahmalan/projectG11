<?php
session_start();
$error = $_SESSION['error_message'] ?? 'An unknown error occurred.';
unset($_SESSION['error_message']); // Clear after showing
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Error</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background-color: #fff3f3;
            color: #d8000c;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            border: 1px solid #f5c2c7;
            padding: 20px;
            border-radius: 8px;
        }
        h1 {
            color: #b71c1c;
        }
        a {
            color: #ea4c89;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Oops! Something went wrong.</h1>
        <p><?= htmlspecialchars($error) ?></p>
        <p><a href="javascript:history.back()">Go back</a> or <a href="modules.html">Return to modules</a></p>
    </div>
</body>
</html>
