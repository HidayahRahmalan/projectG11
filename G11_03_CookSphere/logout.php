<?php
session_start();
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Logged Out - CookSphere</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-6">

  <div class="bg-white p-6 rounded-lg shadow max-w-sm text-center">
    <h1 class="text-2xl font-bold mb-4 text-yellow-600">You have been logged out.</h1>
    <a href="index.php" class="inline-block bg-yellow-400 hover:bg-yellow-500 text-white font-semibold py-2 px-6 rounded shadow transition">
      Login Again
    </a>
  </div>

</body>
</html>
