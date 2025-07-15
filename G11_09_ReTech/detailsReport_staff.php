<?php
session_start();
$success = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Staff Member';

/* ───────────────── FETCH ALL REPORTS ───────────────── */
$sql = "
 SELECT r.report_id, r.title, r.location,
        r.urgency_level, r.status, r.report_date,
        (SELECT file_path  FROM media m WHERE m.report_id = r.report_id ORDER BY m.media_id ASC LIMIT 1) AS evidence,
        (SELECT media_type FROM media m WHERE m.report_id = r.report_id ORDER BY m.media_id ASC LIMIT 1) AS media_type
 FROM reports r
 ORDER BY r.report_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Reports</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include 'styles.php'; ?>
    <style>
        .report-image { max-width:140px; max-height:100px; object-fit:contain; border-radius:6px; }
        .report-video { max-width:140px; max-height:100px; border-radius:6px; }
        .media-type-badge { position:absolute; top:2px; right:2px; background:rgba(0,0,0,.7); color:#fff; font-size:10px; padding:2px 6px; border-radius:3px; }
        .media-container { position:relative; display:inline-block; }
    </style>
</head>
<body class="d-flex">

<?php include 'sidebar.php'; ?>

<div class="content">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-4">📝 All Maintenance Reports</h2>
        <a href="report_form.php" class="btn btn-success">➕ Submit New Report</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($result->num_rows === 0): ?>
        <div class="alert alert-info">No reports submitted yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Location</th>
                        <th>Urgency</th>
                        <th>Evidence</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['report_id'] ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><?= htmlspecialchars($row['urgency_level']) ?></td>
                        <td>
                            <?php if ($row['evidence'] && file_exists($row['evidence'])): ?>
                                <div class="media-container">
                                    <?php if ($row['media_type']==='video'): ?>
                                        <video class="report-video" controls preload="metadata" style="cursor:pointer"
                                               data-bs-toggle="modal" data-bs-target="#mediaModal<?= $row['report_id'] ?>">
                                            <source src="<?= htmlspecialchars($row['evidence']) ?>" type="video/mp4">
                                        </video>
                                        <span class="media-type-badge">VIDEO</span>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars($row['evidence']) ?>" class="report-image"
                                             data-bs-toggle="modal" data-bs-target="#mediaModal<?= $row['report_id'] ?>"
                                             style="cursor:pointer">
                                        <span class="media-type-badge">IMAGE</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Fullscreen Modal -->
                                <div class="modal fade fullscreen-modal" id="mediaModal<?= $row['report_id'] ?>" tabindex="-1">
                                  <div class="modal-dialog modal-dialog-centered modal-xl">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title">Evidence - <?= htmlspecialchars($row['title']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                      </div>
                                      <div class="modal-body text-center">
                                          <?php if ($row['media_type']==='video'): ?>
                                              <video controls class="w-100"><source src="<?= htmlspecialchars($row['evidence']) ?>" type="video/mp4"></video>
                                          <?php else: ?>
                                              <img src="<?= htmlspecialchars($row['evidence']) ?>" class="img-fluid">
                                          <?php endif; ?>
                                      </div>
                                      <div class="modal-footer">
                                          <a href="<?= htmlspecialchars($row['evidence']) ?>" class="btn btn-primary" download>📥 Download</a>
                                          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No media</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                              $badge = ['Pending'=>'warning text-dark','In Progress'=>'info text-dark','Completed'=>'success'][$row['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= $row['status'] ?></span>
                        </td>
                        <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
                        <td>
                            <a href="edit_form.php?id=<?= $row['report_id'] ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
                            <a href="edit_status.php?id=<?= $row['report_id'] ?>" class="btn btn-sm btn-warning">🛠️ Update</a>

                            <!-- Delete trigger button -->
                            <button class="btn btn-sm btn-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteModal"
                                    data-report-id="<?= $row['report_id'] ?>">
                                    🗑️ Delete
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="delete_report.php">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            Are you sure you want to delete this report? This action cannot be undone.
        </div>
        <input type="hidden" name="report_id" id="deleteReportId">
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">🗑️ Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const deleteModal = document.getElementById('deleteModal');
    deleteModal.addEventListener('show.bs.modal', event => {
        const button   = event.relatedTarget;
        const reportId = button.getAttribute('data-report-id');
        deleteModal.querySelector('#deleteReportId').value = reportId;
    });

    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('hidden.bs.modal', () => {
            m.querySelectorAll('video').forEach(v => v.pause());
        });
    });
});
</script>
</body>
</html>
