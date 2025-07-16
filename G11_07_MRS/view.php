<?php
session_start();
require_once "config/database.php";
include "includes/header.php";

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div style='text-align:center;margin-top:40px;color:#c00;font-size:1.2em;'>Invalid report ID.</div>";
    exit();
}

$reportId = $_GET['id'];

// Fetch report details
$sql = "
SELECT 
    r.Report_ID, r.title, r.description, r.location, r.report_date,
    u.label AS urgency_label,
    sl.status AS latest_status,
    m.file_path AS media_path
FROM report r
LEFT JOIN (
    SELECT sl1.*
    FROM status_log sl1
    INNER JOIN (
        SELECT Report_ID, MAX(Status_ID) AS max_id
        FROM status_log
        GROUP BY Report_ID
    ) sl2 ON sl1.Report_ID = sl2.Report_ID AND sl1.Status_ID = sl2.max_id
) sl ON r.Report_ID = sl.Report_ID
LEFT JOIN urgency_level u ON r.Urgency_ID = u.Urgency_ID
LEFT JOIN (
    SELECT Report_ID, MIN(Media_ID) AS first_media_id
    FROM media
    GROUP BY Report_ID
) first_media ON r.Report_ID = first_media.Report_ID
LEFT JOIN media m ON m.Media_ID = first_media.first_media_id
WHERE r.Report_ID = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reportId);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    echo "<div style='text-align:center;margin-top:40px;color:#c00;font-size:1.2em;'>Report not found.</div>";
    exit();
}

// Fetch all evidence media
$evidenceQuery = $conn->prepare("SELECT file_path FROM media WHERE Report_ID = ?");
$evidenceQuery->bind_param("s", $reportId);
$evidenceQuery->execute();
$evidenceResult = $evidenceQuery->get_result();

$evidences = [];
while ($row = $evidenceResult->fetch_assoc()) {
    $evidences[] = $row['file_path'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Report</title>
    <style>
        body {
            font-family: 'Segoe UI', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f6fa;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 40px 32px 32px 32px;
        }
        .section-title {
            text-align: center;
            color: #1976d2;
            font-size: 2rem;
            margin-bottom: 32px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .details-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
            margin-bottom: 32px;
        }
        .details-table td.label {
            width: 28%;
            background: #e3f2fd;
            font-weight: 600;
            color: #1976d2;
            border-radius: 8px 0 0 8px;
            padding: 14px 18px;
            font-size: 1rem;
        }
        .details-table td {
            background: #f8f9fa;
            color: #333;
            padding: 14px 18px;
            border-radius: 0 8px 8px 0;
            font-size: 1rem;
        }
        .main-image-section {
            text-align: center;
            margin-bottom: 36px;
        }
        .main-image-section img {
            max-width: 320px;
            max-height: 320px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(33,150,243,0.10);
            border: 4px solid #e3f2fd;
            background: #fff;
        }
        .evidence-section {
            margin-top: 40px;
        }
        .evidence-title {
            color: #1976d2;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 18px;
            text-align: left;
        }
        .evidence-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
        }
        .evidence-card {
            background: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(33,150,243,0.07);
            padding: 16px 10px 10px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 120px;
        }
        .evidence-card img, .evidence-card video, .evidence-card audio {
            width: 100%;
            max-width: 150px;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .evidence-card video {
            max-width: 160px;
            height: 120px;
        }
        .evidence-card audio {
            max-width: 160px;
        }
        .evidence-label {
            font-size: 0.95rem;
            color: #1976d2;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .download-link {
            color: #1976d2;
            text-decoration: none;
            font-size: 0.97rem;
            font-weight: 500;
            margin-top: 6px;
        }
        .download-link:hover {
            text-decoration: underline;
        }
        .no-evidence {
            color: #888;
            text-align: center;
            margin-top: 10px;
        }
        .back-btn {
            display: inline-block;
            margin: 36px auto 0 auto;
            background: #1976d2;
            color: #fff;
            font-weight: 600;
            padding: 12px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(33,150,243,0.10);
            transition: background 0.2s;
        }
        .back-btn:hover {
            background: #125ea2;
        }
        @media (max-width: 600px) {
            .container { padding: 16px 2vw; }
            .main-image-section img { max-width: 90vw; }
        }
        .evidence-box {
            background: #f8f9fa;
            border: 1.5px solid #e3f2fd;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(33,150,243,0.07);
            margin: 32px auto 0 auto;
            padding: 28px 18px 18px 18px;
            max-width: 900px;
            width: 100%;
            text-align: center;
        }
        .evidence-box .evidence-label {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1976d2;
            margin-bottom: 18px;
            letter-spacing: 0.5px;
        }
        .evidence-box table.details-table {
            margin: 0 auto 0 auto;
            background: transparent;
            box-shadow: none;
        }
        .main-image-section {
            background: #f8f9fa;
            border: 1.5px solid #e3f2fd;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(33,150,243,0.07);
            margin: 32px auto 0 auto;
            padding: 28px 18px 18px 18px;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="section-title">Report Details</div>
    <table class="details-table">
        <tr><td class="label">Title</td><td><?= htmlspecialchars($report['title']) ?></td></tr>
        <tr><td class="label">Description</td><td><?= nl2br(htmlspecialchars($report['description'])) ?></td></tr>
        <tr><td class="label">Location</td><td><?= htmlspecialchars($report['location']) ?></td></tr>
        <tr><td class="label">Report Date</td><td><?= htmlspecialchars($report['report_date']) ?></td></tr>
        <tr><td class="label">Urgency</td><td><?= htmlspecialchars($report['urgency_label'] ?? 'Not Set') ?></td></tr>
        <tr><td class="label">Status</td><td><?= htmlspecialchars($report['latest_status'] ?? 'Not Updated') ?></td></tr>
    </table>

    <?php
    // Categorize evidence with correct file paths
    $images = [];
    $videos = [];
    $audios = [];
    foreach ($evidences as $evi) {
        $ext = strtolower(pathinfo($evi, PATHINFO_EXTENSION));
        $safePath = (strpos($evi, 'assets/uploads/reports/') === 0) ? htmlspecialchars($evi) : 'assets/uploads/reports/' . htmlspecialchars($evi);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $images[] = $safePath;
        } elseif (in_array($ext, ['mp4', 'mov'])) {
            $videos[] = $safePath;
        } elseif (in_array($ext, ['mp3', 'wav', 'ogg'])) {
            $audios[] = $safePath;
        }
    }
    $hasEvidence = count($images) + count($videos) + count($audios) > 0;
    ?>

    <!-- Evidence Gallery Carousel -->
    <div class="evidence-gallery-section">
        <div class="evidence-title">Evidence</div>
        <?php if ($hasEvidence): ?>
            <?php if (count($images)): ?>
                <div class="evidence-box">
                    <div class="evidence-label">Images</div>
                    <div class="evidence-gallery">
                        <?php foreach ($images as $img): ?>
                            <div class="evidence-card">
                                <img src="<?= $img ?>" alt="Evidence Image" class="carousel-img" onclick="openModal(this.src)">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (count($videos)): ?>
                <div class="evidence-box">
                    <div class="evidence-label">Videos</div>
                    <div class="evidence-gallery">
                        <?php foreach ($videos as $vid): ?>
                            <div class="evidence-card">
                                <video controls class="carousel-video">
                                    <source src="<?= $vid ?>" type="video/<?= strtolower(pathinfo($vid, PATHINFO_EXTENSION)) ?>">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (count($audios)): ?>
                <div class="evidence-box">
                    <div class="evidence-label">Audios</div>
                    <div class="evidence-gallery">
                        <?php foreach ($audios as $aud): ?>
                            <div class="evidence-card">
                                <audio controls class="carousel-audio">
                                    <source src="<?= $aud ?>" type="audio/<?= strtolower(pathinfo($aud, PATHINFO_EXTENSION)) ?>">
                                    Your browser does not support the audio element.
                                </audio>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-evidence">No image, video, or audio evidence was inserted for this report.</div>
        <?php endif; ?>
    </div>
    <div style="text-align:center;">
        <a class="back-btn" href="view_reports.php">&larr; Back to My Reports</a>
    </div>
</div>
<!-- Modal for image preview -->
<div id="imgModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
    <span id="closeModalBtn" style="position:absolute; top:30px; right:50px; color:#fff; font-size:2.5rem; cursor:pointer; font-weight:bold; z-index:10001;">&times;</span>
    <img id="modalImg" src="" style="max-width:90vw; max-height:90vh; border-radius:16px; box-shadow:0 8px 32px rgba(0,0,0,0.25); display:block; margin:auto;">
</div>
<style>
.evidence-gallery-section {
    margin-top: 40px;
    background: #f8f9fa;
    border: 1.5px solid #e3f2fd;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(33,150,243,0.07);
    padding: 28px 18px 18px 18px;
    max-width: 900px;
    width: 100%;
    text-align: center;
}
.carousel-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 18px;
}
.carousel-btn {
    background: #1976d2;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(33,150,243,0.10);
    transition: background 0.2s;
}
.carousel-btn:hover {
    background: #125ea2;
}
.carousel-track {
    display: flex;
    overflow-x: auto;
    scroll-behavior: smooth;
    gap: 24px;
    max-width: 600px;
    min-height: 180px;
    align-items: center;
}
.carousel-item {
    min-width: 180px;
    max-width: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px #e3f2fd;
    padding: 12px 8px;
}
.carousel-img {
    max-width: 180px;
    max-height: 180px;
    border-radius: 8px;
    box-shadow: 0 2px 8px #e3f2fd;
    cursor: pointer;
}
.carousel-video {
    max-width: 200px;
    max-height: 160px;
    border-radius: 8px;
}
.carousel-audio {
    width: 180px;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Carousel scroll logic
    const track = document.getElementById('carouselTrack');
    if (track) {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        if (prevBtn) prevBtn.onclick = () => { track.scrollBy({left: -220, behavior: 'smooth'}); };
        if (nextBtn) nextBtn.onclick = () => { track.scrollBy({left: 220, behavior: 'smooth'}); };
    }
    // Modal logic
    function openModal(src) {
        document.getElementById('imgModal').style.display = 'flex';
        document.getElementById('modalImg').src = src;
    }
    function closeModal() {
        document.getElementById('imgModal').style.display = 'none';
        document.getElementById('modalImg').src = '';
    }
    window.openModal = openModal;
    document.getElementById('imgModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    document.getElementById('closeModalBtn').addEventListener('click', closeModal);
});
</script>
</body>
</html> 