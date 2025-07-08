<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid recipe ID.");
}

$recipe_id = (int)$_GET['id'];
$userid = $_SESSION['userid'];

// Verify recipe belongs to this student
$sql = "SELECT UserID FROM recipe WHERE RecipeID = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Recipe not found.");
}

$row = $result->fetch_assoc();
if ($row['UserID'] != $userid) {
    die("You do not have permission to delete this recipe.");
}
$stmt->close();

// Delete media first (optional but recommended)
$stmt = $conn->prepare("DELETE FROM media WHERE RecipeID = ?");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$stmt->close();

// Delete steps (optional)
$stmt = $conn->prepare("DELETE FROM step WHERE RecipeID = ?");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$stmt->close();

// Delete recipe
$stmt = $conn->prepare("DELETE FROM recipe WHERE RecipeID = ?");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("i", $recipe_id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: student_my_recipes.php?msg=Recipe+deleted+successfully");
    exit();
} else {
    $error = "Error deleting recipe.";
    $stmt->close();
    $conn->close();
    die($error);
}
?>
