<?php
// voice_analytics.php - Voice Commands Analytics Dashboard
session_start();
require_once 'db_connect.php';

// Check if user is logged in (optional - can be viewed by anyone)
$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['creator_id']);

try {
    // Get total voice commands
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_commands FROM voice_commands");
    $stmt->execute();
    $total_commands = $stmt->fetch()['total_commands'];
    
    // Get success rate
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(success) as successful 
        FROM voice_commands");
    $stmt->execute();
    $success_data = $stmt->fetch();
    $success_rate = $success_data['total'] > 0 ? ($success_data['successful'] / $success_data['total']) * 100 : 0;
    
    // Get most popular commands
    $stmt = $pdo->prepare("SELECT 
        command, 
        COUNT(*) as usage_count,
        AVG(success) * 100 as success_rate
        FROM voice_commands 
        WHERE command != 'unknown'
        GROUP BY command 
        ORDER BY usage_count DESC 
        LIMIT 10");
    $stmt->execute();
    $popular_commands = $stmt->fetchAll();
    
    // Get recent commands
    $stmt = $pdo->prepare("SELECT 
        vc.*, 
        v.title as video_title,
        u.username,
        DATE_FORMAT(vc.timestamp, '%Y-%m-%d %H:%i:%s') as formatted_time
        FROM voice_commands vc
        LEFT JOIN video v ON vc.vid_id = v.vid_id
        LEFT JOIN user u ON vc.user_id = u.user_id
        ORDER BY vc.timestamp DESC 
        LIMIT 20");
    $stmt->execute();
    $recent_commands = $stmt->fetchAll();
    
    // Get commands by hour (for usage pattern analysis)
    $stmt = $pdo->prepare("SELECT 
        HOUR(timestamp) as hour,
        COUNT(*) as command_count
        FROM voice_commands 
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(timestamp)
        ORDER BY hour");
    $stmt->execute();
    $hourly_usage = $stmt->fetchAll();
    
    // Get failed commands for improvement
    $stmt = $pdo->prepare("SELECT 
        recognized_text,
        COUNT(*) as failure_count
        FROM voice_commands 
        WHERE success = 0
        GROUP BY recognized_text
        ORDER BY failure_count DESC
        LIMIT 10");
    $stmt->execute();
    $failed_commands = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Voice analytics error: " . $e->getMessage());
    $total_commands = 0;
    $success_rate = 0;
    $popular_commands = [];
    $recent_commands = [];
    $hourly_usage = [];
    $failed_commands = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voice Commands Analytics - ChefTube</title>
    <link rel="icon" type="image/png" href="website/icon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f0f23 100%);
            color: #ffffff;
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 18px;
        }

        .analytics-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 9, 20, 0.5);
        }

        .stat-number {
            font-size: 48px;
            font-weight: bold;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
            font-weight: 500;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
        }

        .chart-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #e50914;
        }

        .command-list {
            list-style: none;
        }

        .command-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .command-item:last-child {
            border-bottom: none;
        }

        .command-name {
            font-weight: 500;
            color: #4caf50;
        }

        .command-stats {
            display: flex;
            gap: 15px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .recent-commands {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
        }

        .recent-command {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .recent-command:last-child {
            border-bottom: none;
        }

        .command-details {
            flex: 1;
        }

        .command-text {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .command-meta {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
        }

        .success-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 10px;
        }

        .success-indicator.success {
            background: #4caf50;
        }

        .success-indicator.failed {
            background: #f44336;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #e50914, #ff6b6b);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>

    <div class="analytics-container">
        <div class="header">
            <h1>🎤 Voice Commands Analytics</h1>
            <p>Real-time analysis of voice control usage in ChefTube</p>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_commands); ?></div>
                <div class="stat-label">Total Commands</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($success_rate, 1); ?>%</div>
                <div class="stat-label">Success Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($popular_commands); ?></div>
                <div class="stat-label">Unique Commands</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($failed_commands); ?></div>
                <div class="stat-label">Failed Patterns</div>
            </div>
        </div>

        <!-- Charts and Analysis -->
        <div class="charts-grid">
            <!-- Most Popular Commands -->
            <div class="chart-card">
                <div class="chart-title">📊 Most Popular Commands</div>
                <ul class="command-list">
                    <?php if (empty($popular_commands)): ?>
                        <li class="command-item">
                            <span style="color: rgba(255,255,255,0.5);">No voice commands recorded yet</span>
                        </li>
                    <?php else: ?>
                        <?php foreach ($popular_commands as $command): ?>
                            <li class="command-item">
                                <span class="command-name"><?php echo htmlspecialchars($command['command']); ?></span>
                                <div class="command-stats">
                                    <span><?php echo $command['usage_count']; ?> uses</span>
                                    <span><?php echo number_format($command['success_rate'], 1); ?>% success</span>
                                </div>
                            </li>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($command['usage_count'] / ($popular_commands[0]['usage_count'] ?? 1)) * 100; ?>%"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Failed Commands Analysis -->
            <div class="chart-card">
                <div class="chart-title">❌ Most Failed Attempts</div>
                <ul class="command-list">
                    <?php if (empty($failed_commands)): ?>
                        <li class="command-item">
                            <span style="color: rgba(255,255,255,0.5);">No failed commands recorded</span>
                        </li>
                    <?php else: ?>
                        <?php foreach ($failed_commands as $failed): ?>
                            <li class="command-item">
                                <span class="command-name" style="color: #f44336;">"<?php echo htmlspecialchars($failed['recognized_text']); ?>"</span>
                                <div class="command-stats">
                                    <span><?php echo $failed['failure_count']; ?> failures</span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Recent Commands -->
        <div class="recent-commands">
            <div class="chart-title">⏰ Recent Voice Commands</div>
            <?php if (empty($recent_commands)): ?>
                <div style="text-align: center; color: rgba(255,255,255,0.5); padding: 40px;">
                    <i class="fas fa-microphone-slash" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <div>No voice commands recorded yet</div>
                    <div style="font-size: 14px; margin-top: 10px;">Try using voice control on a video page!</div>
                </div>
            <?php else: ?>
                <?php foreach ($recent_commands as $command): ?>
                    <div class="recent-command">
                        <div class="command-details">
                            <div class="command-text">
                                Command: <strong><?php echo htmlspecialchars($command['command']); ?></strong>
                            </div>
                            <div class="command-meta">
                                Recognized: "<?php echo htmlspecialchars($command['recognized_text']); ?>" | 
                                Video: <?php echo htmlspecialchars($command['video_title'] ?? 'Unknown'); ?> | 
                                User: <?php echo htmlspecialchars($command['username'] ?? 'Anonymous'); ?> | 
                                Time: <?php echo $command['formatted_time']; ?>
                            </div>
                        </div>
                        <div class="success-indicator <?php echo $command['success'] ? 'success' : 'failed'; ?>"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Demo Instructions -->
        <div class="chart-card" style="margin-top: 30px; text-align: center;">
            <div class="chart-title">🎯 How to Test Voice Control</div>
            <div style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                <p><strong>1.</strong> Go to any video page (e.g., <a href="index.php" style="color: #4caf50;">browse videos</a>)</p>
                <p><strong>2.</strong> Click the microphone button or press 'V' key</p>
                <p><strong>3.</strong> Say commands like:</p>
                <div style="margin: 15px 0; color: #4caf50; font-weight: 600;">
                    "Play video" • "Pause" • "Volume up" • "Full screen" • "Restart video"
                </div>
                <p><strong>4.</strong> Watch real-time analytics update here!</p>
                <p style="margin-top: 20px; font-size: 14px; color: rgba(255,255,255,0.6);">
                    ✨ This demonstrates intelligent multimedia database processing with voice recognition, 
                    command pattern analysis, and user behavior tracking.
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds to show real-time updates
        setTimeout(() => {
            window.location.reload();
        }, 30000);

        // Add some interactive effects
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-5px) scale(1)';
            });
        });

        console.log('🎤 Voice Analytics Dashboard Loaded');
        console.log('📊 Total Commands Processed: <?php echo $total_commands; ?>');
        console.log('✅ Success Rate: <?php echo number_format($success_rate, 1); ?>%');
    </script>
</body>
</html>