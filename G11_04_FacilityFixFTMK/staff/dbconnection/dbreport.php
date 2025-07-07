<?php
session_start();
include '../../dbconnection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve report details
    $userId = $_SESSION['user_id'];
    $title = $_POST['Title'];
    $description = $_POST['Description'];
    $location = $_POST['Location'];
    $date = $_POST['Date'];
    $category = $_POST['Category'];
    $urgencyLevel = $_POST['UrgencyLevel'];

    // Check for duplicate media (before inserting report)
    $hasDuplicateMedia = false;
    if (isset($_FILES['Proof'])) {
        foreach ($_FILES['Proof']['tmp_name'] as $key => $tmpName) {
            $fileTmpPath = $_FILES['Proof']['tmp_name'][$key];
            
            // Generate file hash (MD5)
            $fileHash = md5_file($fileTmpPath);

            // Check if this file content already exists in the database
            $checkMedia = $conn->prepare("SELECT * FROM Multimedia WHERE file_hash = ?");
            $checkMedia->bind_param("s", $fileHash);
            $checkMedia->execute();
            $result = $checkMedia->get_result();

            if ($result->num_rows > 0) {
                $hasDuplicateMedia = true;
                break; // No need to check further if at least one duplicate is found
            }
            $checkMedia->close();
        }
    }

    // Check for duplicate reports (same location + category)
    $checkReport = $conn->prepare("
        SELECT Report_id 
        FROM maintenance_report 
        WHERE Location = ? AND Category = ? 
        AND Status IN ('In Progress', 'New')
    ");
    $checkReport->bind_param("ss", $location, $category);
    $checkReport->execute();
    $checkResult = $checkReport->get_result();
    $hasDuplicateReport = $checkResult->num_rows > 0;
    $checkReport->close();

    // If duplicate media OR duplicate report exists, mark as Critical
    $finalUrgency = ($hasDuplicateMedia || $hasDuplicateReport) ? "Critical" : $urgencyLevel;

    $conn->begin_transaction();

    try {
        // Insert report
        $stmt = $conn->prepare("
            INSERT INTO Maintenance_report 
            (User_id, Title, Description, Location, Date, Category, Urgency_level) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssss", $userId, $title, $description, $location, $date, $category, $finalUrgency);
       
        if ($stmt->execute()) {
            $reportId = $stmt->insert_id;
            $updatedRelatedCount = 0;

            // If this is a critical report, update related reports to Critical
            if ($finalUrgency === 'Critical') {
                $updateRelated = $conn->prepare("
                    UPDATE Maintenance_report 
                    SET Urgency_level = 'Critical' 
                    WHERE Location = ? 
                    AND Category = ? 
                    AND Status IN ('In Progress', 'New')
                    AND Report_id != ?
                    AND Urgency_level != 'Critical' 
                ");
                $updateRelated->bind_param("ssi", $location, $category, $reportId);
                $updateRelated->execute();
                $updatedRelatedCount = $updateRelated->affected_rows;
                $updateRelated->close();
            }

            // Handle file uploads
            if (isset($_FILES['Proof'])) {
                foreach ($_FILES['Proof']['tmp_name'] as $key => $tmpName) {
                    $fileTmpPath = $_FILES['Proof']['tmp_name'][$key];
                    $fileName = $_FILES['Proof']['name'][$key];
                    $fileSize = $_FILES['Proof']['size'][$key];
                    $fileType = $_FILES['Proof']['type'][$key];
                    $fileHash = md5_file($fileTmpPath);

                    $uploadFileDir = '../../uploads/';
                    $dest_path = $uploadFileDir . basename($fileName);

                    $allowedFileTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/x-msvideo'];
                    
                    if (in_array($fileType, $allowedFileTypes)) {
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $stmt = $conn->prepare("
                                INSERT INTO Multimedia 
                                (Report_id, File_size, File_type, File_path, file_hash) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->bind_param("iisss", $reportId, $fileSize, $fileType, $dest_path, $fileHash);
                            $stmt->execute();
                        }
                    }
                }
            }

            // Log the action
            $actionType = 'Submit';
            $actionDetail = $updatedRelatedCount > 0 
                ? "Created Critical report (updated $updatedRelatedCount related reports)" 
                : "Created report";
                
            $stmt = $conn->prepare("
                INSERT INTO Systemlog 
                (Report_id, User_id, Action_type, Action_detail) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $reportId, $userId, $actionType, $actionDetail);
            $stmt->execute();

            $conn->commit();
            $_SESSION['status'] = 'Report submitted successfully!' . 
                                ($updatedRelatedCount > 0 ? " Updated $updatedRelatedCount related reports to Critical." : "");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['EmailMessage'] = 'Error: ' . $e->getMessage();
    }

    $stmt->close();
    $conn->close();
    header("Location: ../report.php");
    exit();
}
?>
