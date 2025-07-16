<?php
session_start();
require 'connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_SESSION['email'];
    $inputCode = $_POST['code'];

    try {
        $sql = "SELECT Codes FROM users WHERE Email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['Codes'] == $inputCode) {
            $update = "UPDATE users SET users_status = 'active' WHERE Email = :email";
            $stmt = $conn->prepare($update);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            $updateCode = "UPDATE users SET Codes = NULL WHERE Email = :email";
            $stmt = $conn->prepare($updateCode);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            header("Location: ../auth/login.html");
            exit;
        } else {

            echo "❌ Invalid code or email.";

            header("Location: ../auth/code.html");
        }

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    $conn = null;
}
?>
