<?php
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE Username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($password, $user['PasswordHash'])) {
                session_start();
                $_SESSION['username'] = $username;
                $_SESSION['user_id'] = $user['UserID']; // Assuming primary key is UserID
                $_SESSION['user_role'] = $user['UserRole'];

                switch ($user['UserRole']) {
                    case 'Student':
                        header("Location: ../Homepage/dashboard.html");
                        break;
                    case 'Instructor':
                        header("Location: ../instructor/instructorhomes.php");
                        break;
                    case 'Admin':
                        header("Location: ../admin/adminhome.html");
                        break;
                    default:
                        echo "Unknown user role.";
                        exit;
                }
                exit;
            } else {
                header("Location: ../auth/login.html?error=invalid_password");
                exit;
            }
        } else {
            header("Location: ../auth/login.html?error=user_not_found");
            exit;
        }

    } catch (PDOException $e) {
        echo "Login failed: " . $e->getMessage();
    }
}
?>
