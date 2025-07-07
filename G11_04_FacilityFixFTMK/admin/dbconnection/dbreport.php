<?php
session_start();
include '../../dbconnection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve report details
    $userId = $_SESSION['user_id'];
    $reportId = $_POST['report_id'];
    $resolution = $_POST['resolution'];
    $cost = $_POST['resolutionCost'];
    $status = $_POST['status'];

    // First get the original report details to find related reports
    $getReport = $conn->prepare("SELECT Location, Category FROM Maintenance_report WHERE report_id = ?");
    $getReport->bind_param("i", $reportId);
    $getReport->execute();
    $reportData = $getReport->get_result()->fetch_assoc();
    $getReport->close();

    // Start transaction for atomic updates
    $conn->begin_transaction();

    try {
        // Update the main report
        $stmt = $conn->prepare("UPDATE Maintenance_report SET resolution = ?, cost = ?, status = ? WHERE report_id = ?");
        $stmt->bind_param("sssi", $resolution, $cost, $status, $reportId);
        $stmt->execute();
        
        // If this was a critical report, update all related critical reports
        if ($status === 'Resolved') {
            $updateRelated = $conn->prepare("
                UPDATE Maintenance_report 
                SET resolution = ?, cost = ?, status = ? 
                WHERE Location = ? 
                AND Category = ? 
                AND Urgency_level = 'Critical' 
                AND report_id != ?
                AND status IN ('New', 'In Progress')
            ");
            $updateRelated->bind_param("sssssi", $resolution, $cost, $status, 
                                      $reportData['Location'], $reportData['Category'], $reportId);
            $updateRelated->execute();
            $affectedRows = $updateRelated->affected_rows;
            $updateRelated->close();
        }

        // Log the action for the main report
        $actionType = 'Update';
        $actionDetail = $resolution . ($affectedRows > 0 ? " (and {$affectedRows} related reports)" : "");
        $stmt = $conn->prepare("INSERT INTO Systemlog (Report_id, User_id, Action_type, Action_detail) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $reportId, $userId, $actionType, $actionDetail);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        $_SESSION['status'] = 'Report is successfully updated' . 
                             ($affectedRows > 0 ? " along with {$affectedRows} related reports." : ".");
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['EmailMessage'] = 'Error updating report: ' . $e->getMessage();
    }

    $stmt->close();
    $conn->close();

    header("Location: ../report.php");
    exit();
}
?>
