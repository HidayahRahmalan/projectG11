<?php
include 'connection.php';

try {
    $stmt = $conn->prepare("SELECT UserID, Email, FullName, UserRole, users_status FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user): 
        $userID = htmlspecialchars($user['UserID']);
        $fullName = htmlspecialchars($user['FullName']);
        $email = htmlspecialchars($user['Email']);
        $role = htmlspecialchars($user['UserRole']);
        $status = htmlspecialchars($user['users_status']);
        $isActive = strtolower($status) === 'active';
        $actionLabel = $isActive ? 'Disable' : 'Enable';
        $actionClass = $isActive ? 'disable-btn' : 'enable-btn';
    ?>
        <tr id="row-<?= $userID ?>">
            <td data-label="Name"><?= $fullName ?></td>
            <td data-label="Email"><?= $email ?></td>
            <td data-label="Role">
              <span class="role-tag"><?= $role ?></span>
            </td>
            <td data-label="Status" class="user-status"><?= $status ?></td>
            <td data-label="Actions" class="actions">
              <a href="#" class="<?= $actionClass ?>" data-id="<?= $userID ?>"><?= $actionLabel ?></a>
            </td>
        </tr>
    <?php endforeach;

} catch (PDOException $e) {
    echo "<tr><td colspan='5'>Error: " . $e->getMessage() . "</td></tr>";
}
?>
