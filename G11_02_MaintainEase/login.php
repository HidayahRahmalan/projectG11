<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($password === $user['password']) {
              $_SESSION['user_id'] = $user['user_id'];
              $_SESSION['name'] = $user['name'];
              $_SESSION['role'] = $user['role'];
              header('Location: home.php');
              exit();
          } else {
              $error = 'Incorrect password.';
          }
          
        } else {
            $error = 'User not found.';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - MaintainEase</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');

        :root {
            --color-bg: #ffffff;
            --color-text-body: #6b7280;
            --color-text-head: #111827;
            --color-shadow: rgba(0, 0, 0, 0.05);
            --color-button-bg: #111827;
            --color-button-bg-hover: #000000;
            --radius: 0.75rem;
            --transition: 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f2f5f7;
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            position: sticky;
            top: 0;
            background: var(--color-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            box-shadow: 0 2px 6px var(--color-shadow);
            z-index: 10;
        }

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--color-text-head);
            user-select: none;
        }

        nav {
            display: flex;
            gap: 1.5rem;
        }

        nav a {
            color: var(--color-text-body);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition);
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }

        nav a:hover,
        nav a:focus {
            color: var(--color-button-bg);
            outline: none;
            background-color: rgba(0, 0, 0, 0.05);
        }

        .login-container {
            background: #ffffff;
            padding: 2rem 3rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            margin: 5rem auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #111827;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        input:focus {
            border-color: #111827;
            outline: none;
            box-shadow: 0 0 8px #111827;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background-color: #111827;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #000000;
        }

        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>

    <header>
        <div class="logo" tabindex="0">MaintainEase</div>
        <nav aria-label="Primary navigation">
            <a href="home.php" class="active" aria-current="page">Home</a>
            <a href="register.php">Register</a>
        </nav>
    </header>

    <div class="login-container">
        <h1>Login</h1>

        <?php if (!empty($error)) : ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
        </form>
    </div>

</body>

</html>
