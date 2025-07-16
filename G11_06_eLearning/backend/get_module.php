<?php
include 'connection.php';

if (!isset($_GET['id'])) {
    echo "Invalid module ID";
    exit;
}

$moduleID = $_GET['id'];

try {
    $stmt = $conn->prepare("
        SELECT 
            module.title, 
            module.description, 
            module.TotalViews, 
            users.FullName, 
            topic.TopicName
        FROM module
        INNER JOIN users ON module.UserID = users.UserID
        INNER JOIN topic ON module.TopicID = topic.TopicID
        WHERE module.ModuleID = ?
    ");
    $stmt->execute([$moduleID]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        echo "Module not found.";
        exit;
    }

    echo "<h2>" . htmlspecialchars($module['title']) . "</h2>";
    echo "<p><strong>Instructor:</strong> " . htmlspecialchars($module['FullName']) . "</p>";
    echo "<p><strong>Topic:</strong> " . htmlspecialchars($module['TopicName']) . "</p>";
    echo "<p><strong>Views:</strong> " . htmlspecialchars($module['TotalViews']) . "</p>";
    echo "<p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($module['description'])) . "</p>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
