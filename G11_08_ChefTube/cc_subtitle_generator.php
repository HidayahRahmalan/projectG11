<?php
session_start();
require_once 'db_connect.php';

// Check if creator is logged in
if (!isset($_SESSION['creator_id'])) {
    header('Location: cc_login.php');
    exit();
}

// Get video ID from URL
if (!isset($_GET['id'])) {
    header('Location: cc_dashboard.php');
    exit();
}

$vid_id = $_GET['id'];
$creator_id = $_SESSION['creator_id'];
$creator_name = $_SESSION['creator_name'];

// Get video details
try {
    $stmt = $pdo->prepare("
        SELECT v.*, c.name as creator_name
        FROM video v
        LEFT JOIN creator c ON v.creator_id = c.creator_id
        WHERE v.vid_id = ? AND v.creator_id = ?
    ");
    $stmt->execute([$vid_id, $creator_id]);
    $video = $stmt->fetch();
    
    if (!$video) {
        header('Location: cc_dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Video fetch error: " . $e->getMessage());
    header('Location: cc_dashboard.php');
    exit();
}

// Handle subtitle operations
$success_message = '';
$error_message = '';

// Save subtitle segment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_subtitle'])) {
    $start_time = floatval($_POST['start_time']);
    $end_time = floatval($_POST['end_time']);
    $text = trim($_POST['text']);
    $sequence = intval($_POST['sequence']);
    
    if (!empty($text) && $end_time > $start_time) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO video_subtitles (vid_id, start_time, end_time, text, sequence_number, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([$vid_id, $start_time, $end_time, $text, $sequence, $creator_id]);
            
            if ($result) {
                $subtitle_id = $pdo->lastInsertId();
                
                // Index words for searching
                $words = explode(' ', strtolower($text));
                foreach ($words as $word) {
                    $word = preg_replace('/[^a-z0-9]/', '', $word);
                    if (strlen($word) > 2) {
                        $stmt = $pdo->prepare("
                            INSERT INTO subtitle_search_index (vid_id, word, timestamp, subtitle_id) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$vid_id, $word, $start_time, $subtitle_id]);
                    }
                }
                
                echo json_encode(['status' => 'success', 'message' => 'Subtitle saved successfully!']);
                exit();
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid subtitle data']);
        exit();
    }
}

// Get existing subtitles
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['get_subtitles'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM video_subtitles 
            WHERE vid_id = ? 
            ORDER BY sequence_number ASC, start_time ASC
        ");
        $stmt->execute([$vid_id]);
        $subtitles = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'subtitles' => $subtitles]);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// Generate SRT file
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_srt'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM video_subtitles 
            WHERE vid_id = ? 
            ORDER BY sequence_number ASC, start_time ASC
        ");
        $stmt->execute([$vid_id]);
        $subtitles = $stmt->fetchAll();
        
        $srt_content = "";
        foreach ($subtitles as $index => $subtitle) {
            $start_time = formatSRTTime($subtitle['start_time']);
            $end_time = formatSRTTime($subtitle['end_time']);
            
            $srt_content .= ($index + 1) . "\n";
            $srt_content .= $start_time . " --> " . $end_time . "\n";
            $srt_content .= $subtitle['text'] . "\n\n";
        }
        
        // Save SRT file
        $srt_filename = $vid_id . '.srt';
        $srt_path = "cc/$creator_id/video/subs/" . $srt_filename;
        
        // Create subs directory if it doesn't exist
        $subs_dir = "cc/$creator_id/video/subs";
        if (!is_dir($subs_dir)) {
            mkdir($subs_dir, 0777, true);
        }
        
        if (file_put_contents($srt_path, $srt_content)) {
            // Update video table with subtitle filename
            $stmt = $pdo->prepare("UPDATE video SET subs = ? WHERE vid_id = ?");
            $stmt->execute([$srt_filename, $vid_id]);
            
            echo json_encode(['status' => 'success', 'message' => 'SRT file generated successfully!', 'filename' => $srt_filename]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create SRT file']);
        }
        exit();
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

function formatSRTTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    $milliseconds = ($secs - floor($secs)) * 1000;
    
    return sprintf("%02d:%02d:%02d,%03d", $hours, $minutes, floor($secs), $milliseconds);
}

// Get creator profile picture path
$pfp_path = "cc/$creator_id/pfp/pfp.png";
if (!file_exists($pfp_path)) {
    $pfp_path = "website/default_avatar.png";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subtitle Generator - <?php echo htmlspecialchars($video['title']); ?></title>
    <link rel="icon" type="image/png" href="website/icon.png">
    <link href="https://vjs.zencdn.net/8.6.1/video-js.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f0f0f;
            color: #fff;
            min-height: 100vh;
        }

        /* Top Navigation */
        .top-nav {
            background: #212121;
            padding: 0 24px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #3a3a3a;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 32px;
            height: 32px;
            border-radius: 4px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: bold;
            color: #e50914;
        }

        .back-btn {
            color: #aaa;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.2s ease;
            font-weight: 500;
            border: 1px solid #3a3a3a;
        }

        .back-btn:hover {
            color: #fff;
            border-color: #e50914;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3a3a3a;
        }

        .profile-name {
            font-weight: 500;
            color: #aaa;
        }

        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
            padding: 24px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .video-section {
            min-width: 0;
        }

        .video-player-wrapper {
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .video-js {
            width: 100%;
            height: 500px;
        }

        .video-info {
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            padding: 24px;
        }

        .video-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .video-meta {
            color: #aaa;
            margin-bottom: 16px;
        }

        /* Subtitle Generator Panel */
        .subtitle-panel {
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            height: fit-content;
            overflow: hidden;
        }

        .panel-header {
            background: #e50914;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-title {
            font-size: 18px;
            font-weight: 600;
            color: #fff;
        }

        .panel-content {
            padding: 20px;
        }

        /* Speech Recognition Controls */
        .speech-controls {
            text-align: center;
            margin-bottom: 24px;
        }

        .speech-button {
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            border: none;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin: 0 auto 16px;
            box-shadow: 0 4px 20px rgba(229, 9, 20, 0.3);
        }

        .speech-button:hover {
            transform: scale(1.05);
        }

        .speech-button.listening {
            background: linear-gradient(45deg, #4caf50, #66bb6a);
            animation: pulse 1.5s infinite;
        }

        .speech-button.processing {
            background: linear-gradient(45deg, #ff9800, #ffb74d);
            animation: spin 1s linear infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .speech-status {
            font-size: 14px;
            color: #aaa;
            margin-bottom: 12px;
        }

        .speech-status.listening {
            color: #4caf50;
            font-weight: 600;
        }

        .speech-status.processing {
            color: #ff9800;
            font-weight: 600;
        }

        /* Current Subtitle */
        .current-subtitle {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .subtitle-time {
            font-size: 12px;
            color: #4caf50;
            margin-bottom: 8px;
            font-family: monospace;
        }

        .subtitle-text {
            background: transparent;
            border: none;
            color: #fff;
            width: 100%;
            min-height: 60px;
            resize: vertical;
            font-size: 14px;
            line-height: 1.4;
            outline: none;
        }

        .subtitle-text::placeholder {
            color: #666;
        }

        .subtitle-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #e50914;
            color: #fff;
        }

        .btn-primary:hover {
            background: #f40612;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid #3a3a3a;
            color: #aaa;
        }

        .btn-secondary:hover {
            border-color: #fff;
            color: #fff;
        }

        /* Subtitle List */
        .subtitle-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .subtitle-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }

        .subtitle-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .subtitle-item-time {
            font-size: 11px;
            color: #4caf50;
            margin-bottom: 4px;
            font-family: monospace;
        }

        .subtitle-item-text {
            font-size: 13px;
            line-height: 1.3;
        }

        /* Progress Stats */
        .progress-stats {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #4caf50;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #aaa;
        }

        /* Action Buttons */
        .panel-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }

        .action-btn {
            width: 100%;
            padding: 12px;
            background: transparent;
            border: 1px solid #3a3a3a;
            color: #aaa;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            border-color: #e50914;
            color: #e50914;
        }

        .action-btn.primary {
            background: #e50914;
            border-color: #e50914;
            color: #fff;
        }

        .action-btn.primary:hover {
            background: #f40612;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert.success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #4caf50;
        }

        .alert.error {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.3);
            color: #f44336;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            
            .video-js {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-section">
                <img src="website/icon.png" alt="ChefTube" class="logo">
                <div class="brand-name">ChefTube</div>
            </div>
            <a href="cc_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        <div class="nav-right">
            <img src="<?php echo htmlspecialchars($pfp_path); ?>" alt="Profile" class="profile-avatar">
            <span class="profile-name"><?php echo htmlspecialchars($creator_name); ?></span>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Video Section -->
        <div class="video-section">
            <div class="video-player-wrapper">
                <video
                    id="videoPlayer"
                    class="video-js vjs-default-skin"
                    controls
                    preload="auto"
                    data-setup="{}">
                    <p class="vjs-no-js">
                        To view this video please enable JavaScript, and consider upgrading to a web browser that
                        <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>.
                    </p>
                </video>
            </div>
            
            <div class="video-info">
                <h1 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h1>
                <div class="video-meta">
                    Creating subtitles for this video • Duration: <?php echo $video['duration'] ?? 'Unknown'; ?>
                </div>
                <p><?php echo nl2br(htmlspecialchars($video['description'])); ?></p>
            </div>
        </div>

        <!-- Subtitle Generator Panel -->
        <div class="subtitle-panel">
            <div class="panel-header">
                <div class="panel-title">🎤 Auto Subtitle Generator</div>
            </div>
            
            <div class="panel-content">
                <!-- Alert Messages -->
                <div id="alertContainer"></div>
                
                <!-- Progress Stats -->
                <div class="progress-stats">
                    <div class="stat-value" id="subtitleCount">0</div>
                    <div class="stat-label">Subtitles Created</div>
                </div>
                
                <!-- Speech Recognition Controls -->
                <div class="speech-controls">
                    <button class="speech-button" id="speechButton" onclick="toggleSpeechRecognition()">
                        <span id="speechIcon">🎤</span>
                    </button>
                    <div class="speech-status" id="speechStatus">Click to start recording</div>
                </div>
                
                <!-- Current Subtitle -->
                <div class="current-subtitle" id="currentSubtitle" style="display: none;">
                    <div class="subtitle-time" id="subtitleTime">00:00:00,000 --> 00:00:00,000</div>
                    <textarea class="subtitle-text" id="subtitleText" placeholder="Speak to generate subtitle text..."></textarea>
                    <div class="subtitle-actions">
                        <button class="btn btn-primary" onclick="saveCurrentSubtitle()">Save</button>
                        <button class="btn btn-secondary" onclick="cancelCurrentSubtitle()">Cancel</button>
                        <button class="btn btn-secondary" onclick="retryRecording()">Re-record</button>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div style="background: rgba(255, 255, 255, 0.05); padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                    <h4 style="margin-bottom: 8px; color: #4caf50;">📝 How to Use:</h4>
                    <ul style="font-size: 14px; color: #aaa; line-height: 1.5; margin-left: 20px;">
                        <li>Play the video and listen to the audio</li>
                        <li>Click the microphone when speech starts</li>
                        <li>Speak what you hear clearly</li>
                        <li>Click again to stop and save the subtitle</li>
                        <li>Repeat for the entire video</li>
                    </ul>
                </div>
                
                <!-- Action Buttons -->
                <div class="panel-actions">
                    <button class="action-btn primary" onclick="generateSRTFile()">
                        📁 Generate SRT File
                    </button>
                    <button class="action-btn" onclick="loadExistingSubtitles()">
                        📋 Load Existing Subtitles
                    </button>
                    <button class="action-btn" onclick="clearAllSubtitles()">
                        🗑️ Clear All Subtitles
                    </button>
                </div>
                
                <!-- Subtitle List -->
                <div style="margin-top: 24px;">
                    <h4 style="margin-bottom: 12px; color: #e50914;">Generated Subtitles:</h4>
                    <div class="subtitle-list" id="subtitleList">
                        <div style="text-align: center; color: #666; padding: 20px;">
                            No subtitles created yet
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Video.js JavaScript -->
    <script src="https://vjs.zencdn.net/8.6.1/video.min.js"></script>
    <script>
        // Subtitle Generator Class
        class SubtitleGenerator {
            constructor() {
                this.recognition = null;
                this.isListening = false;
                this.player = null;
                this.currentStartTime = null;
                this.subtitles = [];
                this.sequenceNumber = 1;
                
                this.initializeSpeechRecognition();
            }
            
            initializeSpeechRecognition() {
                // Check for browser support
                if ('webkitSpeechRecognition' in window) {
                    this.recognition = new webkitSpeechRecognition();
                } else if ('SpeechRecognition' in window) {
                    this.recognition = new SpeechRecognition();
                } else {
                    this.showAlert('Speech recognition not supported in this browser', 'error');
                    return;
                }
                
                // Configure speech recognition
                this.recognition.continuous = false;
                this.recognition.interimResults = false;
                this.recognition.lang = 'en-US';
                this.recognition.maxAlternatives = 1;
                
                // Event listeners
                this.recognition.onstart = () => {
                    this.isListening = true;
                    this.currentStartTime = this.player ? this.player.currentTime() : 0;
                    this.updateUI();
                    this.updateStatus('Listening... Speak clearly', 'listening');
                };
                
                this.recognition.onend = () => {
                    this.isListening = false;
                    this.updateUI();
                };
                
                this.recognition.onresult = (event) => {
                    const result = event.results[0][0];
                    const text = result.transcript.trim();
                    const endTime = this.player ? this.player.currentTime() : 0;
                    
                    if (text && this.currentStartTime !== null) {
                        this.showCurrentSubtitle(this.currentStartTime, endTime, text);
                    }
                };
                
                this.recognition.onerror = (event) => {
                    console.error('Speech recognition error:', event.error);
                    this.isListening = false;
                    this.updateUI();
                    
                    let errorMessage = 'Speech recognition error';
                    switch(event.error) {
                        case 'no-speech':
                            errorMessage = 'No speech detected. Try again.';
                            break;
                        case 'audio-capture':
                            errorMessage = 'Microphone not available';
                            break;
                        case 'not-allowed':
                            errorMessage = 'Microphone permission denied';
                            break;
                        default:
                            errorMessage = `Error: ${event.error}`;
                    }
                    this.updateStatus(errorMessage, 'error');
                };
            }
            
            setPlayer(player) {
                this.player = player;
            }
            
            toggleSpeechRecognition() {
                if (!this.recognition) return;
                
                if (this.isListening) {
                    this.recognition.stop();
                } else {
                    try {
                        this.recognition.start();
                    } catch (error) {
                        console.error('Failed to start recognition:', error);
                        this.updateStatus('Failed to start speech recognition', 'error');
                    }
                }
            }
            
            updateUI() {
                const button = document.getElementById('speechButton');
                const icon = document.getElementById('speechIcon');
                
                if (this.isListening) {
                    button.classList.add('listening');
                    icon.textContent = '⏹️';
                } else {
                    button.classList.remove('listening', 'processing');
                    icon.textContent = '🎤';
                }
            }
            
            updateStatus(message, type = '') {
                const status = document.getElementById('speechStatus');
                status.textContent = message;
                status.className = `speech-status ${type}`;
            }
            
            showCurrentSubtitle(startTime, endTime, text) {
                const container = document.getElementById('currentSubtitle');
                const timeElement = document.getElementById('subtitleTime');
                const textElement = document.getElementById('subtitleText');
                
                const startFormatted = this.formatTime(startTime);
                const endFormatted = this.formatTime(endTime);
                
                timeElement.textContent = `${startFormatted} --> ${endFormatted}`;
                textElement.value = text;
                container.style.display = 'block';
                
                // Store current subtitle data
                this.currentSubtitleData = {
                    startTime: startTime,
                    endTime: endTime,
                    text: text
                };
                
                this.updateStatus('Subtitle ready to save', 'success');
            }
            
            saveCurrentSubtitle() {
                if (!this.currentSubtitleData) return;
                
                const formData = new FormData();
                formData.append('save_subtitle', '1');
                formData.append('start_time', this.currentSubtitleData.startTime);
                formData.append('end_time', this.currentSubtitleData.endTime);
                formData.append('text', this.currentSubtitleData.text);
                formData.append('sequence', this.sequenceNumber);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.showAlert(data.message, 'success');
                        this.addSubtitleToList(this.currentSubtitleData);
                        this.sequenceNumber++;
                        this.updateSubtitleCount();
                        this.cancelCurrentSubtitle();
                    } else {
                        this.showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error saving subtitle:', error);
                    this.showAlert('Failed to save subtitle', 'error');
                });
            }
            
            cancelCurrentSubtitle() {
                document.getElementById('currentSubtitle').style.display = 'none';
                this.currentSubtitleData = null;
                this.updateStatus('Click to record next subtitle', 'ready');
            }
            
            retryRecording() {
                this.cancelCurrentSubtitle();
                this.toggleSpeechRecognition();
            }
            
            addSubtitleToList(subtitle) {
                const list = document.getElementById('subtitleList');
                
                // Clear "no subtitles" message
                if (list.children.length === 1 && list.children[0].textContent.includes('No subtitles')) {
                    list.innerHTML = '';
                }
                
                const item = document.createElement('div');
                item.className = 'subtitle-item';
                item.innerHTML = `
                    <div class="subtitle-item-time">${this.formatTime(subtitle.startTime)} --> ${this.formatTime(subtitle.endTime)}</div>
                    <div class="subtitle-item-text">${subtitle.text}</div>
                `;
                
                list.appendChild(item);
                this.subtitles.push(subtitle);
            }
            
            updateSubtitleCount() {
                document.getElementById('subtitleCount').textContent = this.subtitles.length;
            }
            
            formatTime(seconds) {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = Math.floor(seconds % 60);
                const milliseconds = Math.floor((seconds % 1) * 1000);
                
                return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')},${milliseconds.toString().padStart(3, '0')}`;
            }
            
            showAlert(message, type) {
                const container = document.getElementById('alertContainer');
                const alert = document.createElement('div');
                alert.className = `alert ${type}`;
                alert.textContent = message;
                
                container.appendChild(alert);
                
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            }
            
            generateSRTFile() {
                if (this.subtitles.length === 0) {
                    this.showAlert('No subtitles to generate. Create some subtitles first.', 'error');
                    return;
                }
                
                const formData = new FormData();
                formData.append('generate_srt', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.showAlert(data.message, 'success');
                    } else {
                        this.showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error generating SRT:', error);
                    this.showAlert('Failed to generate SRT file', 'error');
                });
            }
            
            loadExistingSubtitles() {
                const formData = new FormData();
                formData.append('get_subtitles', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.subtitles = data.subtitles;
                        this.renderSubtitleList();
                        this.updateSubtitleCount();
                        this.showAlert('Existing subtitles loaded successfully', 'success');
                    } else {
                        this.showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading subtitles:', error);
                    this.showAlert('Failed to load existing subtitles', 'error');
                });
            }
            
            renderSubtitleList() {
                const list = document.getElementById('subtitleList');
                list.innerHTML = '';
                
                if (this.subtitles.length === 0) {
                    list.innerHTML = '<div style="text-align: center; color: #666; padding: 20px;">No subtitles created yet</div>';
                    return;
                }
                
                this.subtitles.forEach(subtitle => {
                    this.addSubtitleToList({
                        startTime: parseFloat(subtitle.start_time),
                        endTime: parseFloat(subtitle.end_time),
                        text: subtitle.text
                    });
                });
            }
            
            clearAllSubtitles() {
                if (confirm('Are you sure you want to clear all subtitles? This action cannot be undone.')) {
                    // This would need a backend endpoint to delete subtitles
                    this.subtitles = [];
                    this.sequenceNumber = 1;
                    this.renderSubtitleList();
                    this.updateSubtitleCount();
                    this.showAlert('All subtitles cleared', 'success');
                }
            }
        }
        
        // Initialize system
        let subtitleGenerator;
        let player;
        const videoId = '<?php echo $vid_id; ?>';
        const videoPath = 'cc/<?php echo $creator_id; ?>/video/<?php echo $video['video']; ?>';

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            subtitleGenerator = new SubtitleGenerator();
            
            if (typeof videojs !== 'undefined') {
                initializeVideoPlayer();
            } else {
                console.error('Video.js failed to load');
            }
        });

        function initializeVideoPlayer() {
            try {
                player = videojs('videoPlayer', {
                    controls: true,
                    responsive: true,
                    fluid: true,
                    sources: [{
                        src: videoPath,
                        type: 'video/mp4'
                    }]
                });

                player.ready(function() {
                    console.log('Video player ready');
                    subtitleGenerator.setPlayer(player);
                    subtitleGenerator.loadExistingSubtitles();
                });

            } catch (error) {
                console.error('Error initializing video player:', error);
            }
        }
        
        // Global functions
        function toggleSpeechRecognition() {
            subtitleGenerator.toggleSpeechRecognition();
        }
        
        function saveCurrentSubtitle() {
            subtitleGenerator.saveCurrentSubtitle();
        }
        
        function cancelCurrentSubtitle() {
            subtitleGenerator.cancelCurrentSubtitle();
        }
        
        function retryRecording() {
            subtitleGenerator.retryRecording();
        }
        
        function generateSRTFile() {
            subtitleGenerator.generateSRTFile();
        }
        
        function loadExistingSubtitles() {
            subtitleGenerator.loadExistingSubtitles();
        }
        
        function clearAllSubtitles() {
            subtitleGenerator.clearAllSubtitles();
        }
    </script>
</body>
</html>