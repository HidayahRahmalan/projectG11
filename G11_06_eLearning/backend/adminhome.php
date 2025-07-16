<?php
include 'connection.php';

try {
    $stmt = $conn->prepare("
        SELECT 
            module.ModuleID,
            module.title, 
            users.FullName, 
            topic.TopicName, 
            module.TotalViews, 
            AVG(modulerating.RatingValue) AS average_rating
        FROM module
        INNER JOIN users ON module.UserID = users.UserID
        INNER JOIN topic ON module.TopicID = topic.TopicID
        LEFT JOIN modulerating ON module.ModuleID = modulerating.ModuleID
        GROUP BY module.ModuleID
    ");
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($modules as $module): ?>
        <tr>
            <td data-label="Module Title"><?= htmlspecialchars($module['title']) ?></td>
            <td data-label="Instructor"><?= htmlspecialchars($module['FullName']) ?></td>
            <td data-label="Topic"><?= htmlspecialchars($module['TopicName']) ?></td>
            <td data-label="Views"><?= htmlspecialchars($module['TotalViews']) ?></td>
            <td data-label="Avg. Rating">
                <span class="rating">
                    <?= $module['average_rating'] !== null ? number_format($module['average_rating'], 1) . " ★" : "No ratings" ?>
                </span>
            </td>
            <td data-label="Actions" class="actions">
                <a href="#" class="view-btn" data-id="<?= $module['ModuleID'] ?>">View</a>
            </td>
        </tr>
    <?php endforeach;

} catch (PDOException $e) {
    echo "<tr><td colspan='6'>Error: " . $e->getMessage() . "</td></tr>";
}
?>
