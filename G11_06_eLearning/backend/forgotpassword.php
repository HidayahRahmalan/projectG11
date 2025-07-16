<?php
require 'connection.php';
require '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $fullname = htmlspecialchars($_POST['fullname'] ?? 'User'); 

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "❌ Invalid email format.";
        exit;
    }

    $code = rand(100000, 999999); 

    try {
        
        $checkSql = "SELECT * FROM users WHERE Email = :email";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            
            $updateSql = "UPDATE users SET Codes = :code WHERE Email = :email";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bindParam(':code', $code);
            $updateStmt->bindParam(':email', $email);
            $updateStmt->execute();
            
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';        
            $mail->SMTPAuth = true;
            $mail->Username = 'dragonone123456@gmail.com';
            $mail->Password = 'sdmt hxyb oyph abef'; 
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('dragonone123456@gmail.com', 'Elearning');
            $mail->addAddress($email, $fullname);

            $mail->isHTML(true);
            $mail->Subject = 'Your Verification Code';
            $mail->Body = "
                <p>Hello,</p>
                <p>Your verification code is: <strong>$code</strong></p>
                <p>Please enter it on the next page to activate your account.</p>
            ";

            $mail->send();

            header("Location: ../auth/code.html");
            exit;
        } else {
            echo "❌ Email not found in the database.";
            header("Location: ../auth/forgotpassword.html");
        }

    } catch (Exception $e) {
        echo "Mailer Error: {$mail->ErrorInfo}";
    } catch (PDOException $e) {
        echo "Database Error: " . $e->getMessage();
    }

    $conn = null;
}
?>
