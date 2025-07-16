<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $userID = $_POST['id'];

    try {
        $stmt = $conn->prepare("UPDATE users SET users_status = 'Inactive' WHERE UserID = ?");
        $stmt->execute([$userID]);

        if ($stmt->rowCount()) {
            echo "success";
        } else {
            echo "not found or already inactive";
        }
    } catch (PDOException $e) {
        echo "error";
    }
} else {
    echo "invalid";
}
?>
