<?php
session_start();
require 'connection.php';
require '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $code = rand(100000, 999999); 

    $_SESSION['email'] = $_POST['email'];

    $sql = "INSERT INTO users (Username, PasswordHash, Email, FullName, UserRole, Codes, users_status)
            VALUES (:username, :passwordhash, :email, :fullname,  'Student', :code, 'inactive')";

    $stmt = $conn->prepare($sql);
    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt->bindParam(':username', $_POST['username']);
    $stmt->bindParam(':passwordhash', $hashedPassword);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':fullname', $_POST['fullname']);
    $stmt->bindParam(':code', $code);

    $stmt->execute();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';        
    $mail->SMTPAuth = true;
    $mail->Username = 'dragonone123456@gmail.com';      
    $mail->Password = 'sdmt hxyb oyph abef';             
    $mail->SMTPSecure = 'tls';                          
    $mail->Port = 587;

    $mail->setFrom('dragonone123456@gmail.com', 'Elearning');
    $mail->addAddress($_POST['email'], $_POST['fullname']);
    $mail->isHTML(true);
    $mail->Subject = 'Your Verification Code';
    $mail->Body = "
        <p>Hello <strong>{$_POST['fullname']}</strong>,</p>
        <p>Your verification code is: <strong>$code</strong></p>
        <p>Please enter it on the next page to activate your account.</p>
    ";

    $mail->send();

    header("Location: ../auth/code.html");
    exit;

} catch(PDOException $e) {
    echo "Database Error: " . $e->getMessage();
} catch(Exception $e) {
    echo "Mailer Error: " . $mail->ErrorInfo;
}

$conn = null;
?>
