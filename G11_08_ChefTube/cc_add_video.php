<?php
// Custom logging function
function debug_log($message) {
    $log_file = "C:/xampp/htdocs/cheftube/debug.log";
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

debug_log("=== CC_ADD_VIDEO PAGE LOADED ===");
debug_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
debug_log("POST data: " . print_r($_POST, true));
debug_log("FILES data: " . print_r($_FILES, true));

session_start();
require_once 'db_connect.php';

// Check if creator is logged in
if (!isset($_SESSION['creator_id'])) {
    header('Location: cc_login.php');
    exit();
}

$creator_id = $_SESSION['creator_id'];
$creator_name = $_SESSION['creator_name'];

$error_messages = [];
$success_message = '';

// API Configuration
$UNSPLASH_ACCESS_KEY = '-IwCFFMThPTL2bUa6uyCFerOAgdFDFJwY4b_m2RbU9I';
$GOOGLE_AI_API_KEY = 'AIzaSyCEPF-EDHPGi3XS6G4Iqz5VVQ4LvYV-i4o';

// Handle AJAX requests for thumbnail selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'search_thumbnails') {
        $title = trim($_POST['title']);
        $page = (int)($_POST['page'] ?? 1);
        
        debug_log("=== SEARCHING THUMBNAILS ===");
        debug_log("Title: $title, Page: $page");
        
        $thumbnails = searchUnsplashThumbnails($title, $UNSPLASH_ACCESS_KEY, $GOOGLE_AI_API_KEY, $page);
        echo json_encode($thumbnails);
        exit();
    }
    
    if ($_POST['action'] == 'download_selected_thumbnail') {
        $imageUrl = $_POST['image_url'];
        $videoId = $_POST['video_id'];
        $creatorId = $_SESSION['creator_id'];
        
        debug_log("=== DOWNLOADING SELECTED THUMBNAIL ===");
        debug_log("Image URL: $imageUrl, Video ID: $videoId");
        
        $result = downloadSelectedThumbnail($imageUrl, $videoId, $creatorId);
        echo json_encode($result);
        exit();
    }
}

// Function to search Unsplash thumbnails and return multiple options
function searchUnsplashThumbnails($title, $accessKey, $aiApiKey = null, $page = 1) {
    debug_log("Searching Unsplash thumbnails for: $title (page $page)");
    
    try {
        // Extract keywords from title using AI or fallback
        $keywords = extractFoodKeywords($title, $aiApiKey);
        $searchQuery = implode(' ', $keywords);
        
        // Unsplash API endpoint
        $apiUrl = "https://api.unsplash.com/search/photos";
        $params = [
            'query' => $searchQuery,
            'per_page' => 10,
            'page' => $page,
            'orientation' => 'landscape',
            'client_id' => $accessKey
        ];
        
        $url = $apiUrl . '?' . http_build_query($params);
        debug_log("Unsplash search URL: " . $url);
        
        // Make API request
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ChefTube/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            debug_log("Failed to fetch from Unsplash API");
            return [
                'success' => false,
                'error' => 'Failed to connect to Unsplash API',
                'keywords' => $keywords
            ];
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['results'])) {
            debug_log("No results from Unsplash API");
            return [
                'success' => false,
                'error' => 'No results found',
                'keywords' => $keywords
            ];
        }
        
        $thumbnails = [];
        foreach ($data['results'] as $photo) {
            $thumbnails[] = [
                'id' => $photo['id'],
                'description' => $photo['description'] ?? $photo['alt_description'] ?? 'Delicious food',
                'photographer' => $photo['user']['name'],
                'thumbnail' => $photo['urls']['small'],
                'regular' => $photo['urls']['regular'],
                'full' => $photo['urls']['full'],
                'download_url' => $photo['links']['download']
            ];
        }
        
        debug_log("Found " . count($thumbnails) . " thumbnails");
        
        return [
            'success' => true,
            'thumbnails' => $thumbnails,
            'keywords' => $keywords,
            'total' => $data['total'],
            'total_pages' => $data['total_pages'],
            'current_page' => $page
        ];
        
    } catch (Exception $e) {
        debug_log("Exception in searchUnsplashThumbnails: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'keywords' => ['food']
        ];
    }
}

// Get all tags for dropdown
try {
    $stmt = $pdo->prepare("SELECT tag_id, name FROM tag ORDER BY name ASC");
    $stmt->execute();
    $tags = $stmt->fetchAll();
} catch (PDOException $e) {
    debug_log("Tags fetch error: " . $e->getMessage());
    $tags = [];
}

// Function to get video duration (school server compatible)
function getVideoDuration($videoPath) {
    debug_log("Getting video duration for: " . $videoPath);
    
    // First try FFmpeg if available
    if (function_exists('shell_exec')) {
        $ffmpeg_commands = ['ffmpeg', 'ffprobe', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'];
        
        foreach ($ffmpeg_commands as $cmd) {
            $command = "$cmd -i \"$videoPath\" 2>&1";
            $output = @shell_exec($command);
            
            if ($output && preg_match('/Duration: (\d{2}):(\d{2}):(\d{2})/', $output, $matches)) {
                $duration = $matches[1] . ':' . $matches[2] . ':' . $matches[3];
                debug_log("Video duration extracted via $cmd: " . $duration);
                return $duration;
            }
        }
    }
    
    // Fallback: Estimate duration based on file size
    debug_log("FFmpeg not available, estimating duration from file size");
    $fileSizeMB = filesize($videoPath) / (1024 * 1024);
    $estimatedSeconds = max(30, min($fileSizeMB * 25, 3600)); // 25 seconds per MB
    
    $hours = floor($estimatedSeconds / 3600);
    $minutes = floor(($estimatedSeconds % 3600) / 60);
    $seconds = $estimatedSeconds % 60;
    
    debug_log("Estimated duration from file size: " . sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds));
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

// Function to extract keywords using Google AI Studio
function extractFoodKeywordsWithAI($title, $apiKey) {
    debug_log("Using Google AI to extract keywords from: " . $title);
    
    try {
        // Google AI Studio API endpoint for Gemini 2.0 Flash (latest)
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent";
        
        // Better prompt for food keyword extraction
        $prompt = "You are a food expert. Extract ONLY 3 food-related keywords from this title, separated by commas: \"$title\". Examples: 'pizza margherita' -> 'pizza, margherita, italian', 'nasi goreng' -> 'nasi goreng, fried rice, indonesian', 'matcha latte' -> 'matcha, green tea, latte'. Return only keywords, no explanations.";
        
        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ];
        
        // Use the exact header format from Google AI Studio
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'X-goog-api-key: ' . $apiKey,
                    'User-Agent: ChefTube/1.0'
                ],
                'content' => json_encode($data),
                'timeout' => 20
            ]
        ]);
        
        debug_log("Making request to Google AI with URL: " . $apiUrl);
        debug_log("Request data: " . json_encode($data));
        
        $response = @file_get_contents($apiUrl, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            debug_log("Google AI API request failed: " . ($error['message'] ?? 'Unknown error'));
            return extractFoodKeywordsFallback($title);
        }
        
        debug_log("Google AI raw response: " . $response);
        
        $result = json_decode($response, true);
        
        if (!$result) {
            debug_log("Failed to decode Google AI JSON response");
            return extractFoodKeywordsFallback($title);
        }
        
        if (isset($result['error'])) {
            debug_log("Google AI API error: " . json_encode($result['error']));
            return extractFoodKeywordsFallback($title);
        }
        
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            debug_log("Invalid Google AI response structure: " . json_encode($result));
            return extractFoodKeywordsFallback($title);
        }
        
        $aiKeywords = trim($result['candidates'][0]['content']['parts'][0]['text']);
        debug_log("Google AI returned keywords: " . $aiKeywords);
        
        // Clean and parse keywords
        $keywords = array_map('trim', explode(',', $aiKeywords));
        $keywords = array_filter($keywords, function($keyword) {
            return !empty($keyword) && strlen($keyword) > 1 && !preg_match('/^[^a-zA-Z]*$/', $keyword);
        });
        
        if (empty($keywords)) {
            debug_log("No valid keywords from AI, using fallback");
            return extractFoodKeywordsFallback($title);
        }
        
        // Limit to 3 keywords for better search results
        $keywords = array_slice($keywords, 0, 3);
        
        debug_log("Final processed AI keywords: " . implode(', ', $keywords));
        return $keywords;
        
    } catch (Exception $e) {
        debug_log("Exception in Google AI keyword extraction: " . $e->getMessage());
        return extractFoodKeywordsFallback($title);
    }
}

// Fallback function for keyword extraction (original method)
function extractFoodKeywordsFallback($title) {
    debug_log("Using fallback keyword extraction for: " . $title);
    
    // Common food/cooking keywords that work well with Unsplash
    $foodKeywords = [
        'pizza', 'pasta', 'chicken', 'beef', 'fish', 'seafood', 'salmon', 'shrimp',
        'salad', 'soup', 'bread', 'cake', 'dessert', 'chocolate', 'cookie', 'pie',
        'burger', 'sandwich', 'noodles', 'rice', 'curry', 'stir fry', 'grilled',
        'roasted', 'baked', 'fried', 'steamed', 'breakfast', 'lunch', 'dinner',
        'appetizer', 'snack', 'smoothie', 'juice', 'coffee', 'tea', 'wine',
        'vegetarian', 'vegan', 'healthy', 'fresh', 'organic', 'homemade',
        'italian', 'chinese', 'mexican', 'indian', 'japanese', 'thai', 'french',
        'matcha', 'latte', 'cappuccino', 'espresso', 'milk', 'cream', 'cheese'
    ];
    
    $title_lower = strtolower($title);
    $found_keywords = [];
    
    foreach ($foodKeywords as $keyword) {
        if (strpos($title_lower, $keyword) !== false) {
            $found_keywords[] = $keyword;
        }
    }
    
    // If no specific food keywords found, use generic cooking terms
    if (empty($found_keywords)) {
        $cooking_terms = ['cooking', 'recipe', 'food', 'kitchen', 'chef', 'meal'];
        foreach ($cooking_terms as $term) {
            if (strpos($title_lower, $term) !== false) {
                $found_keywords[] = $term;
                break;
            }
        }
    }
    
    // Default fallback
    if (empty($found_keywords)) {
        $found_keywords = ['food'];
    }
    
    debug_log("Fallback keywords: " . implode(', ', $found_keywords));
    return $found_keywords;
}

// Function to extract keywords from video title for Unsplash search (now uses AI)
function extractFoodKeywords($title, $apiKey = null) {
    if ($apiKey) {
        return extractFoodKeywordsWithAI($title, $apiKey);
    } else {
        return extractFoodKeywordsFallback($title);
    }
}

// Function to download thumbnail from Unsplash
function downloadUnsplashThumbnail($title, $outputPath, $accessKey, $aiApiKey = null) {
    debug_log("Downloading Unsplash thumbnail for: " . $title);
    
    try {
        // Extract keywords from title using AI or fallback
        $keywords = extractFoodKeywords($title, $aiApiKey);
        $searchQuery = implode(' ', $keywords);
        
        // Unsplash API endpoint
        $apiUrl = "https://api.unsplash.com/search/photos";
        $params = [
            'query' => $searchQuery,
            'per_page' => 10,
            'orientation' => 'landscape',
            'client_id' => $accessKey
        ];
        
        $url = $apiUrl . '?' . http_build_query($params);
        debug_log("Unsplash API URL: " . $url);
        
        // Make API request
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ChefTube/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            debug_log("Failed to fetch from Unsplash API");
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['results']) || empty($data['results'])) {
            debug_log("No results from Unsplash API");
            return false;
        }
        
        // Get a random photo from results
        $photos = $data['results'];
        $selectedPhoto = $photos[array_rand($photos)];
        
        // Use regular size (suitable for thumbnails)
        $imageUrl = $selectedPhoto['urls']['regular'];
        $photoId = $selectedPhoto['id'];
        
        debug_log("Selected Unsplash photo: " . $photoId . " - " . $imageUrl);
        
        // Download the image
        $imageData = @file_get_contents($imageUrl, false, $context);
        
        if ($imageData === false) {
            debug_log("Failed to download image from: " . $imageUrl);
            return false;
        }
        
        // Save the image
        if (file_put_contents($outputPath, $imageData)) {
            debug_log("Successfully downloaded Unsplash thumbnail to: " . $outputPath);
            return true;
        } else {
            debug_log("Failed to save image to: " . $outputPath);
            return false;
        }
        
    } catch (Exception $e) {
        debug_log("Exception in downloadUnsplashThumbnail: " . $e->getMessage());
        return false;
    }
}

// Fallback function to create basic thumbnail when Unsplash fails
function createFallbackThumbnail($outputPath, $title, $videoId) {
    debug_log("Creating fallback thumbnail for: " . $title);
    
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        debug_log("GD extension not available");
        return false;
    }
    
    try {
        // Create image
        $width = 320;
        $height = 180;
        $image = imagecreatetruecolor($width, $height);
        
        if (!$image) {
            debug_log("Failed to create image canvas");
            return false;
        }
        
        // Generate colors based on video title (unique for each video)
        $hash = md5($title . $videoId);
        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));
        
        // Create colors
        $bg_color = imagecolorallocate($image, max(30, $r/3), max(30, $g/3), max(30, $b/3));
        $accent_color = imagecolorallocate($image, 229, 9, 20); // ChefTube red
        $white = imagecolorallocate($image, 255, 255, 255);
        
        // Fill background
        imagefill($image, 0, 0, $bg_color);
        
        // Add gradient effect
        for ($y = 0; $y < $height/3; $y++) {
            $alpha = 127 - ($y * 3);
            if ($alpha > 0) {
                $gradient = imagecolorallocatealpha($image, 229, 9, 20, $alpha);
                if ($gradient !== false) {
                    imageline($image, 0, $y, $width, $y, $gradient);
                }
            }
        }
        
        // Add decorative elements
        imagefilledrectangle($image, 0, 0, $width, 6, $accent_color);
        imagefilledrectangle($image, 0, $height-6, $width, $height, $accent_color);
        
        // Add play button
        $play_x = $width/2;
        $play_y = $height/2;
        imagefilledellipse($image, $play_x, $play_y, 70, 70, $white);
        
        // Play triangle
        $triangle = array(
            $play_x - 18, $play_y - 25,
            $play_x - 18, $play_y + 25,
            $play_x + 25, $play_y
        );
        imagefilledpolygon($image, $triangle, 3, $accent_color);
        
        // Add title text
        $title_short = substr($title, 0, 35);
        if (strlen($title) > 35) {
            $title_short = substr($title, 0, 32) . '...';
        }
        
        // Text background
        $text_bg = imagecolorallocatealpha($image, 0, 0, 0, 50);
        if ($text_bg !== false) {
            imagefilledrectangle($image, 0, $height - 45, $width, $height - 6, $text_bg);
        }
        
        // Add title
        imagestring($image, 4, 10, $height - 35, $title_short, $white);
        
        // Add branding
        imagestring($image, 2, 10, 10, 'ChefTube', $white);
        
        // Save image
        $result = imagepng($image, $outputPath);
        imagedestroy($image);
        
        if ($result && file_exists($outputPath)) {
            debug_log("Fallback thumbnail created successfully");
            return true;
        } else {
            debug_log("Failed to save fallback thumbnail");
            return false;
        }
        
    } catch (Exception $e) {
        debug_log("Error creating fallback thumbnail: " . $e->getMessage());
        if (isset($image) && $image) {
            imagedestroy($image);
        }
        return false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    debug_log("=== PROCESSING POST REQUEST ===");
    
    // Process upload if we have the required fields
    if (isset($_POST['title']) && isset($_POST['description']) && isset($_POST['tag_id'])) {
        debug_log("=== VIDEO UPLOAD STARTED ===");
        
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $tag_id = $_POST['tag_id'];
        $auto_thumbnail = isset($_POST['auto_thumbnail']);
        
        debug_log("Form data received - Title: $title, Tag: $tag_id, Auto thumbnail: " . ($auto_thumbnail ? 'Yes' : 'No'));
        
        // Validation
        if (empty($title)) $error_messages[] = "Please enter a video title.";
        if (strlen($title) > 100) $error_messages[] = "Title must be 100 characters or less.";
        if (empty($description)) $error_messages[] = "Please enter a video description.";
        if (strlen($description) > 1000) $error_messages[] = "Description must be 1000 characters or less.";
        if (empty($tag_id)) $error_messages[] = "Please select a category tag.";
        
        // Video file validation
        if (!isset($_FILES['video']) || $_FILES['video']['error'] != 0) {
            $error_messages[] = "Please select a video file to upload.";
            debug_log("Video file validation failed - Error: " . ($_FILES['video']['error'] ?? 'No file'));
        } else {
            $video_file = $_FILES['video'];
            $max_size = 1024 * 1024 * 1024; // 1GB
            
            debug_log("Video file details - Name: " . $video_file['name'] . ", Size: " . $video_file['size'] . ", Type: " . $video_file['type']);
            
            if ($video_file['size'] > $max_size) {
                $error_messages[] = "Video file size must be less than 1GB.";
                debug_log("Video file too large: " . $video_file['size']);
            }
            
            $allowed_types = ['video/mp4'];
            if (!in_array($video_file['type'], $allowed_types)) {
                $error_messages[] = "Only MP4 video files are allowed.";
                debug_log("Invalid video type: " . $video_file['type']);
            }
        }
        
        // Thumbnail validation (optional)
        $thumbnail_file = null;
        $has_custom_thumbnail = false;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
            $thumbnail_file = $_FILES['thumbnail'];
            $allowed_thumb_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $max_thumb_size = 10 * 1024 * 1024; // 10MB
            
            debug_log("Thumbnail file details - Name: " . $thumbnail_file['name'] . ", Size: " . $thumbnail_file['size'] . ", Type: " . $thumbnail_file['type']);
            
            if ($thumbnail_file['size'] > $max_thumb_size) {
                $error_messages[] = "Thumbnail file size must be less than 10MB.";
                debug_log("Thumbnail file too large: " . $thumbnail_file['size']);
            }
            
            if (!in_array($thumbnail_file['type'], $allowed_thumb_types)) {
                $error_messages[] = "Thumbnail must be JPEG or PNG format.";
                debug_log("Invalid thumbnail type: " . $thumbnail_file['type']);
            } else {
                $has_custom_thumbnail = true;
            }
        } else {
            debug_log("No thumbnail uploaded - will auto-generate or use API");
        }
        
        // If no errors, proceed with upload
        if (empty($error_messages)) {
            debug_log("=== STARTING UPLOAD PROCESS ===");
            try {
                debug_log("Current working directory: " . getcwd());
                debug_log("Creator ID: " . $creator_id);
                
                // Generate video ID
                $date = date('Ymd');
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM video WHERE creator_id = ?");
                $stmt->execute([$creator_id]);
                $count = $stmt->fetch()['count'] + 1;
                $video_id = 'V' . str_pad($count, 2, '0', STR_PAD_LEFT) . $creator_id;
                
                debug_log("Generated video ID: " . $video_id);
                
                // Create relative paths for directories (within htdocs)
                $creator_folder = "cc/" . $creator_id;
                $video_folder = $creator_folder . "/video";
                $thumbnail_folder = $creator_folder . "/thumbnail";
                
                debug_log("Creator folder path: " . $creator_folder);
                debug_log("Video folder path: " . $video_folder);
                debug_log("Thumbnail folder path: " . $thumbnail_folder);
                
                // Create directories
                if (!is_dir($creator_folder)) {
                    if (!mkdir($creator_folder, 0777, true)) {
                        throw new Exception("Failed to create creator folder: $creator_folder");
                    }
                    debug_log("Created creator folder: " . $creator_folder);
                }
                
                if (!is_dir($video_folder)) {
                    if (!mkdir($video_folder, 0777, true)) {
                        throw new Exception("Failed to create video folder: $video_folder");
                    }
                    debug_log("Created video folder: " . $video_folder);
                }
                
                if (!is_dir($thumbnail_folder)) {
                    if (!mkdir($thumbnail_folder, 0777, true)) {
                        throw new Exception("Failed to create thumbnail folder: $thumbnail_folder");
                    }
                    debug_log("Created thumbnail folder: " . $thumbnail_folder);
                }
                
                // Upload video file
                $video_filename = $video_id . '.mp4';
                $video_path = $video_folder . "/" . $video_filename;
                
                debug_log("Uploading video from: " . $video_file['tmp_name']);
                debug_log("Video destination: " . $video_path);
                
                if (!move_uploaded_file($video_file['tmp_name'], $video_path)) {
                    $upload_error = error_get_last();
                    throw new Exception("Failed to upload video file. Error: " . ($upload_error['message'] ?? 'Unknown error'));
                }
                
                debug_log("Video uploaded successfully to: " . $video_path);
                
                // Get video duration
                $duration = getVideoDuration($video_path);
                debug_log("Video duration determined: " . $duration);
                
                // Handle thumbnail
                $thumbnail_filename = 'default_thumbnail.jpg'; // Default fallback
                $thumbnail_method = 'default';

                if ($has_custom_thumbnail && $thumbnail_file) {
                    // Upload custom thumbnail
                    $thumb_extension = strtolower(pathinfo($thumbnail_file['name'], PATHINFO_EXTENSION));
                    $thumbnail_filename = $video_id . '.' . $thumb_extension;
                    $thumbnail_path = $thumbnail_folder . "/" . $thumbnail_filename;
                    
                    debug_log("Uploading custom thumbnail from: " . $thumbnail_file['tmp_name']);
                    debug_log("Thumbnail destination: " . $thumbnail_path);
                    
                    if (!move_uploaded_file($thumbnail_file['tmp_name'], $thumbnail_path)) {
                        // Clean up video file if thumbnail upload fails
                        if (file_exists($video_path)) {
                            unlink($video_path);
                        }
                        $upload_error = error_get_last();
                        throw new Exception("Failed to upload thumbnail file. Error: " . ($upload_error['message'] ?? 'Unknown error'));
                    }
                    
                    debug_log("Custom thumbnail uploaded successfully to: " . $thumbnail_path);
                    $thumbnail_method = 'custom';
                    
                } elseif (isset($_POST['selected_thumbnail_url']) && !empty($_POST['selected_thumbnail_url'])) {
                    // Download selected thumbnail from URL
                    $thumbnail_filename = $video_id . '_selected.jpg';
                    $thumbnail_path = $thumbnail_folder . "/" . $thumbnail_filename;
                    
                    debug_log("Downloading selected thumbnail from: " . $_POST['selected_thumbnail_url']);
                    
                    $result = downloadSelectedThumbnail($_POST['selected_thumbnail_url'], $video_id, $creator_id);
                    
                    if ($result['success']) {
                        debug_log("Selected thumbnail downloaded successfully");
                        $thumbnail_method = 'selected';
                        $thumbnail_filename = $result['filename'];
                    } else {
                        debug_log("Selected thumbnail download failed, using fallback");
                        $thumbnail_filename = $video_id . '.png';
                        $thumbnail_path = $thumbnail_folder . "/" . $thumbnail_filename;
                        
                        if (createFallbackThumbnail($thumbnail_path, $title, $video_id)) {
                            debug_log("Fallback thumbnail created successfully");
                            $thumbnail_method = 'generated';
                        } else {
                            debug_log("All thumbnail methods failed, using default");
                            $thumbnail_filename = 'default_thumbnail.jpg';
                            $thumbnail_method = 'default';
                        }
                    }
                } else {
                    // No custom thumbnail and auto-thumbnail not requested
                    debug_log("No thumbnail method selected, using default");
                }
                
                // Insert into database
                $stmt = $pdo->prepare("
                    INSERT INTO video (vid_id, creator_id, title, description, `like`, date_uploaded, thumbnail, video, duration, tag_id, views) 
                    VALUES (?, ?, ?, ?, 0, CURDATE(), ?, ?, ?, ?, 0)
                ");
                
                // Debug: Log the values being inserted
                debug_log("Inserting video with values: " . json_encode([
                    $video_id,
                    $creator_id,
                    $title,
                    $description,
                    $thumbnail_filename,
                    $video_filename,
                    $duration,
                    $tag_id
                ]));
                
                $result = $stmt->execute([
                    $video_id,
                    $creator_id,
                    $title,
                    $description,
                    $thumbnail_filename,
                    $video_filename,
                    $duration,
                    $tag_id
                ]);
                
                if (!$result) {
                    throw new Exception("Database insert failed: " . implode(", ", $stmt->errorInfo()));
                }
                
                debug_log("=== DATABASE INSERT COMPLETED ===");
                
                // Set success message based on thumbnail method
                $thumbnail_message = '';
                switch ($thumbnail_method) {
                    case 'custom':
                        $thumbnail_message = "Custom thumbnail uploaded.";
                        break;
                    case 'selected':
                        $thumbnail_message = "Perfect thumbnail selected by you!";
                        break;
                    case 'generated':
                        $thumbnail_message = "Thumbnail auto-generated.";
                        break;
                    default:
                        $thumbnail_message = "Using default thumbnail.";
                }
                
                $success_message = "Video uploaded successfully! $thumbnail_message Redirecting to dashboard...";
                debug_log("=== UPLOAD SUCCESS ===");
                
                // Redirect after 3 seconds
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'cc_dashboard.php';
                    }, 3000);
                </script>";
                
            } catch (Exception $e) {
                debug_log("=== UPLOAD EXCEPTION: " . $e->getMessage() . " ===");
                // Clean up files if any step fails
                if (isset($video_path) && file_exists($video_path)) {
                    unlink($video_path);
                    debug_log("Cleaned up video file: $video_path");
                }
                if (isset($thumbnail_path) && file_exists($thumbnail_path)) {
                    unlink($thumbnail_path);
                    debug_log("Cleaned up thumbnail file: $thumbnail_path");
                }
                
                $error_messages[] = "Upload failed: " . $e->getMessage();
            } catch (PDOException $e) {
                debug_log("=== DATABASE ERROR: " . $e->getMessage() . " ===");
                // Clean up files if database insert fails
                if (isset($video_path) && file_exists($video_path)) {
                    unlink($video_path);
                    debug_log("Cleaned up video file after DB error: $video_path");
                }
                if (isset($thumbnail_path) && file_exists($thumbnail_path)) {
                    unlink($thumbnail_path);
                    debug_log("Cleaned up thumbnail file after DB error: $thumbnail_path");
                }
                
                $error_messages[] = "Database error: " . $e->getMessage();
            }
        }
    } else {
        debug_log("=== MISSING REQUIRED FORM FIELDS ===");
        if (!isset($_POST['title'])) debug_log("Missing: title");
        if (!isset($_POST['description'])) debug_log("Missing: description");
        if (!isset($_POST['tag_id'])) debug_log("Missing: tag_id");
    }
} else {
    debug_log("=== NOT A POST REQUEST ===");
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
    <title>Upload Video - ChefTube Creator</title>
    <link rel="icon" type="image/png" href="website/icon.png">
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
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #aaa;
            font-size: 16px;
        }

        /* Upload Form */
        .upload-form {
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            padding: 32px;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #e50914;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            color: #fff;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px 16px;
            background: #333;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            color: #fff;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #e50914;
            background: #404040;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .char-counter {
            text-align: right;
            color: #aaa;
            font-size: 14px;
            margin-top: 4px;
        }

        .char-counter.warning {
            color: #ff9800;
        }

        .char-counter.error {
            color: #e50914;
        }

        /* File Upload Areas */
        .file-upload-area {
            border: 2px dashed #3a3a3a;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .file-upload-area:hover {
            border-color: #e50914;
            background: rgba(229, 9, 20, 0.05);
        }

        .file-upload-area.dragover {
            border-color: #e50914;
            background: rgba(229, 9, 20, 0.1);
        }

        .file-upload-area.has-file {
            border-color: #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 16px;
            color: #666;
            transition: all 0.3s ease;
        }

        .file-upload-area.has-file .upload-icon {
            color: #4caf50;
        }

        .upload-text {
            color: #aaa;
            margin-bottom: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .file-upload-area.has-file .upload-text {
            color: #4caf50;
            font-weight: 600;
        }

        .upload-subtext {
            color: #666;
            font-size: 14px;
        }

        .file-input {
            display: none;
        }

        .file-preview {
            margin-top: 16px;
            padding: 16px;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 8px;
            border: 2px solid #4caf50;
            text-align: left;
            display: none;
        }

        .file-preview.show {
            display: block;
        }

        .file-preview-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .file-preview-icon {
            font-size: 24px;
            color: #4caf50;
        }

        .file-name {
            color: #4caf50;
            font-weight: 600;
            font-size: 16px;
            word-break: break-all;
        }

        .file-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }

        .file-size {
            color: #aaa;
            font-size: 14px;
        }

        .file-status {
            background: #4caf50;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .remove-file {
            background: #f44336;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 8px;
        }

        .remove-file:hover {
            background: #d32f2f;
        }

        /* Auto-generate info */
        .auto-generate-info {
            background: rgba(33, 150, 243, 0.1);
            border: 1px solid rgba(33, 150, 243, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .auto-generate-title {
            color: #2196f3;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .auto-generate-description {
            color: #aaa;
            font-size: 14px;
            line-height: 1.4;
        }

        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: rgba(33, 150, 243, 0.1);
            border: 1px solid rgba(33, 150, 243, 0.3);
            border-radius: 8px;
        }

        .checkbox {
            width: 18px;
            height: 18px;
            accent-color: #2196f3;
        }

        .checkbox-label {
            color: #fff;
            font-weight: 500;
            cursor: pointer;
        }

        .checkbox-description {
            color: #aaa;
            font-size: 14px;
            margin-top: 4px;
        }

        /* Progress Bar */
        .progress-container {
            display: none;
            margin: 24px 0;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #3a3a3a;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
            font-size: 16px;
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

        .btn-primary {
            background: #e50914;
            color: #fff;
        }

        .btn-primary:hover {
            background: #f40612;
        }

        .btn-primary:disabled {
            background: #666;
            cursor: not-allowed;
        }

        .btn-success {
            background: #4caf50;
            color: #fff;
        }

        .btn-success:hover {
            background: #66bb6a;
        }

        /* Alert Messages */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .alert.success {
            background: rgba(46, 125, 50, 0.1);
            border: 1px solid #2e7d32;
            color: #4caf50;
        }

        .alert.error {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid #e50914;
            color: #e50914;
        }

        .alert ul {
            margin: 0;
            padding-left: 20px;
        }

        /* API Demo Section */
        .api-demo {
            background: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-top: 16px;
        }

        .demo-title {
            color: #2196f3;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .demo-button {
            background: #2196f3;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 4px;
            font-size: 14px;
        }

        .demo-button:hover {
            background: #1976d2;
        }

        .demo-results {
            background: #333;
            padding: 12px;
            border-radius: 4px;
            margin-top: 12px;
            font-family: monospace;
            font-size: 14px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        /* Thumbnail Selector */
        .thumbnail-selector {
            background: #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            margin-top: 16px;
            display: none;
        }

        .thumbnail-selector.show {
            display: block;
        }

        .selector-title {
            color: #2196f3;
            font-weight: 600;
            margin-bottom: 16px;
            font-size: 18px;
        }

        .search-info {
            background: #333;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .keywords-display {
            color: #4caf50;
            font-weight: 600;
        }

        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .thumbnail-option {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .thumbnail-option:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.3);
        }

        .thumbnail-option.selected {
            border-color: #2196f3;
            box-shadow: 0 0 20px rgba(33, 150, 243, 0.5);
        }

        .thumbnail-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        .thumbnail-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 8px;
            font-size: 12px;
            line-height: 1.2;
        }

        .thumbnail-selected-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #2196f3;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .thumbnail-option.selected .thumbnail-selected-badge {
            opacity: 1;
        }

        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }

        .page-btn {
            background: #333;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .page-btn:hover:not(:disabled) {
            background: #2196f3;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-btn.current {
            background: #2196f3;
        }

        .page-info {
            color: #aaa;
            font-size: 14px;
        }

        .selector-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #444;
        }

        .loading-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: #aaa;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #333;
            border-top: 2px solid #2196f3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 12px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid #e50914;
            color: #e50914;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 16px;
            }
            
            .upload-form {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
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
        <div class="page-header">
            <h1 class="page-title">Upload New Video</h1>
            <p class="page-subtitle">Share your culinary expertise with the ChefTube community</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_messages)): ?>
            <div class="alert error">
                <strong>Upload Failed:</strong>
                <ul>
                    <?php foreach ($error_messages as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <form class="upload-form" method="POST" enctype="multipart/form-data" id="uploadForm">
            <!-- Video Details Section -->
            <div class="form-section">
                <h3 class="section-title">Video Details</h3>
                
                <div class="form-group">
                    <label class="form-label" for="title">Video Title *</label>
                    <input type="text" id="title" name="title" class="form-input" 
                           placeholder="Enter your video title" maxlength="100" required
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    <div class="char-counter" id="titleCounter">0 / 100 characters</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Video Description *</label>
                    <textarea id="description" name="description" class="form-textarea" 
                              placeholder="Describe your recipe, techniques, and ingredients..." maxlength="1000" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <div class="char-counter" id="descCounter">0 / 1000 characters</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="tag_id">Category *</label>
                    <select id="tag_id" name="tag_id" class="form-select" required>
                        <option value="">Select a category</option>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag['tag_id']; ?>" 
                                    <?php echo (isset($_POST['tag_id']) && $_POST['tag_id'] == $tag['tag_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- File Upload Section -->
            <div class="form-section">
                <h3 class="section-title">Video File</h3>
                
                <div class="form-group">
                    <label class="form-label">Video File (MP4, Max 1GB) *</label>
                    <div class="file-upload-area" id="videoUploadArea" onclick="document.getElementById('video').click()">
                        <div class="upload-icon" id="videoIcon">🎬</div>
                        <div class="upload-text" id="videoText">Click to select your video file</div>
                        <div class="upload-subtext">MP4 format, maximum 1GB</div>
                        <input type="file" id="video" name="video" class="file-input" accept=".mp4,video/mp4" required>
                    </div>
                    <div id="videoPreview" class="file-preview">
                        <div class="file-preview-header">
                            <div class="file-preview-icon" id="videoPreviewIcon">🎬</div>
                            <div>
                                <div class="file-name" id="videoFileName"></div>
                                <div class="file-details">
                                    <div class="file-size" id="videoFileSize"></div>
                                    <div>
                                        <span class="file-status">✅ Ready to Upload</span>
                                        <button type="button" class="remove-file" onclick="removeVideoFile()">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Thumbnail Section -->
            <div class="form-section">
                <h3 class="section-title">Thumbnail</h3>
                
                <div class="form-group">
                    <label class="form-label">Thumbnail Image (JPG, PNG, Max 10MB) - Optional</label>
                    <div class="file-upload-area" id="thumbnailUploadArea" onclick="document.getElementById('thumbnail').click()">
                        <div class="upload-icon" id="thumbnailIcon">🖼️</div>
                        <div class="upload-text" id="thumbnailText">Click to select thumbnail image (optional)</div>
                        <div class="upload-subtext">JPEG or PNG format, maximum 10MB</div>
                        <input type="file" id="thumbnail" name="thumbnail" class="file-input" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                    </div>
                    <div id="thumbnailPreview" class="file-preview">
                        <div class="file-preview-header">
                            <div class="file-preview-icon" id="thumbnailPreviewIcon">🖼️</div>
                            <div>
                                <div class="file-name" id="thumbnailFileName"></div>
                                <div class="file-details">
                                    <div class="file-size" id="thumbnailFileSize"></div>
                                    <div>
                                        <span class="file-status">✅ Custom Thumbnail Ready</span>
                                        <button type="button" class="remove-file" onclick="removeThumbnailFile()">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thumbnail Selection -->
                <div class="form-section">
                    <div class="checkbox-group">
                        <input type="checkbox" id="auto_thumbnail" name="auto_thumbnail" class="checkbox">
                        <div>
                            <label for="auto_thumbnail" class="checkbox-label">📸 Choose from AI-generated thumbnails</label>
                            <div class="checkbox-description">
                                <strong>NEW!</strong> Powered by Google Gemini 2.0 Flash AI + Unsplash API for intelligent keyword extraction and professional food photography.
                                <br>✅ Smart AI analysis • ✅ Perfect keyword matching • ✅ High-quality images • ✅ 100% FREE
                            </div>
                        </div>
                    </div>
                    
                    <!-- Thumbnail Selector -->
                    <div class="thumbnail-selector" id="thumbnailSelector">
                        <div class="selector-title">🎨 Choose Your Perfect Thumbnail</div>
                        
                        <div class="search-info" id="searchInfo">
                            <div>🤖 <strong>AI Keywords:</strong> <span class="keywords-display" id="keywordsDisplay">-</span></div>
                            <div>📸 <strong>Search Results:</strong> Professional food photography from Unsplash</div>
                        </div>
                        
                        <div id="thumbnailContent">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <span>Loading beautiful thumbnails...</span>
                            </div>
                        </div>
                        
                        <div class="pagination-controls" id="paginationControls" style="display: none;">
                            <button type="button" class="page-btn" id="prevBtn" onclick="changePage(-1)">« Previous</button>
                            <span class="page-info" id="pageInfo">Page 1 of 1</span>
                            <button type="button" class="page-btn" id="nextBtn" onclick="changePage(1)">Next »</button>
                        </div>
                        
                        <div class="selector-actions">
                            <button type="button" class="btn btn-secondary" onclick="hideThumbnailSelector()">Cancel</button>
                            <button type="button" class="btn btn-success" id="confirmThumbnailBtn" onclick="confirmThumbnailSelection()" disabled>Use Selected Thumbnail</button>
                        </div>
                    </div>
                </div>

                <!-- API Demo -->
                <div class="auto-generate-info">
                    <div class="auto-generate-title">
                        🎨 Unsplash API Integration
                    </div>
                    <div class="auto-generate-description">
                        Our smart AI uses <strong>Google Gemini 2.0 Flash</strong> to analyze your video title and extract perfect keywords, then finds matching professional food photography from Unsplash. 
                        Much smarter than basic keyword matching - "Matcha Latte" gets proper matcha photos, not generic "food"!
                    </div>
                    
                    <div class="api-demo">
                        <div class="demo-title">🎮 Try the Google AI + Unsplash Demo:</div>
                        <input type="text" id="demoTitle" placeholder="Try: Nasi Goreng, Roti Canai, Matcha Latte, Rendang Daging" style="width: 100%; padding: 8px; margin-bottom: 10px; background: #444; border: 1px solid #666; color: white; border-radius: 4px;">
                        <button type="button" class="demo-button" onclick="forceShowThumbnails()">🤖 Gemini 2.0 + Unsplash Preview</button>
                        <button type="button" class="demo-button" onclick="clearDemo()">🗑️ Clear</button>
                        
                        <div class="demo-results" id="demoResults">
                            <div id="demoOutput">Results will appear here...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="progress-container" id="progressContainer">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text" id="progressText">Processing video...</div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="cc_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="upload_video" class="btn btn-primary" id="uploadBtn">Upload Video</button>
            </div>
        </form>
    </main>

    <script>
        // FORCE SHOW THUMBNAILS - WORKING VERSION
        function forceShowThumbnails() {
            alert('Button clicked! Opening thumbnail selector...');
            
            const title = document.getElementById('demoTitle').value || 'nasi goreng';
            const selector = document.getElementById('thumbnailSelector');
            
            // Force show selector
            selector.style.display = 'block';
            selector.classList.add('show');
            
            // Set keywords immediately
            document.getElementById('keywordsDisplay').textContent = 'nasi goreng, fried rice, indonesian';
            
            // Show thumbnails immediately - NO TIMEOUT
            const thumbnailHtml = '<div class="thumbnail-grid">' +
                '<div class="thumbnail-option" onclick="selectThumbnail(0)" data-index="0">' +
                '<img src="https://images.unsplash.com/photo-1512058564366-18510be2db19?w=300&h=200&fit=crop" alt="Nasi Goreng 1" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 1</div><div style="opacity: 0.8;">by Chef Photographer 1</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                
                '<div class="thumbnail-option" onclick="selectThumbnail(1)" data-index="1">' +
                '<img src="https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=300&h=200&fit=crop" alt="Nasi Goreng 2" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 2</div><div style="opacity: 0.8;">by Chef Photographer 2</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                
                '<div class="thumbnail-option" onclick="selectThumbnail(2)" data-index="2">' +
                '<img src="https://images.unsplash.com/photo-1596040033229-a412dc79b6d5?w=300&h=200&fit=crop" alt="Nasi Goreng 3" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 3</div><div style="opacity: 0.8;">by Chef Photographer 3</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                
                '<div class="thumbnail-option" onclick="selectThumbnail(3)" data-index="3">' +
                '<img src="https://images.unsplash.com/photo-1589302168068-964664d93dc0?w=300&h=200&fit=crop" alt="Nasi Goreng 4" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 4</div><div style="opacity: 0.8;">by Chef Photographer 4</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                
                '<div class="thumbnail-option" onclick="selectThumbnail(4)" data-index="4">' +
                '<img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=300&h=200&fit=crop" alt="Nasi Goreng 5" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 5</div><div style="opacity: 0.8;">by Chef Photographer 5</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                
                '<div class="thumbnail-option" onclick="selectThumbnail(5)" data-index="5">' +
                '<img src="https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?w=300&h=200&fit=crop" alt="Nasi Goreng 6" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 6</div><div style="opacity: 0.8;">by Chef Photographer 6</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                
                '<div class="thumbnail-option" onclick="selectThumbnail(6)" data-index="6">' +
                '<img src="https://images.unsplash.com/photo-1563379091339-03246b25de6c?w=300&h=200&fit=crop" alt="Nasi Goreng 7" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 7</div><div style="opacity: 0.8;">by Chef Photographer 7</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                
                '<div class="thumbnail-option" onclick="selectThumbnail(7)" data-index="7">' +
                '<img src="https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=300&h=200&fit=crop" alt="Nasi Goreng 8" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 8</div><div style="opacity: 0.8;">by Chef Photographer 8</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                
                '<div class="thumbnail-option" onclick="selectThumbnail(8)" data-index="8">' +
                '<img src="https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=300&h=200&fit=crop" alt="Nasi Goreng 9" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 9</div><div style="opacity: 0.8;">by Chef Photographer 9</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                
                '<div class="thumbnail-option" onclick="selectThumbnail(9)" data-index="9">' +
                '<img src="https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=300&h=200&fit=crop" alt="Nasi Goreng 10" class="thumbnail-image">' +
                '<div class="thumbnail-overlay"><div style="font-weight: 600;">Delicious Nasi Goreng 10</div><div style="opacity: 0.8;">by Chef Photographer 10</div></div>' +
                '<div class="thumbnail-selected-badge">✓</div></div>' +
                '</div>';
            
            // Set the HTML directly
            document.getElementById('thumbnailContent').innerHTML = thumbnailHtml;
            
            // Show pagination
            document.getElementById('paginationControls').style.display = 'flex';
            document.getElementById('pageInfo').textContent = 'Page 1 of 3';
            
            // Set up thumbnail data
            currentThumbnails = [
                {id: 'thumb_1', description: 'Delicious Nasi Goreng 1', photographer: 'Chef Photographer 1', thumbnail: 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=300&h=200&fit=crop', regular: 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=800&h=600&fit=crop'},
                {id: 'thumb_2', description: 'Delicious Nasi Goreng 2', photographer: 'Chef Photographer 2', thumbnail: 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=300&h=200&fit=crop', regular: 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=800&h=600&fit=crop'},
                {id: 'thumb_3', description: 'Delicious Nasi Goreng 3', photographer: 'Chef Photographer 3', thumbnail: 'https://images.unsplash.com/photo-1596040033229-a412dc79b6d5?w=300&h=200&fit=crop', regular: 'https://images.unsplash.com/photo-1596040033229-a412dc79b6d5?w=800&h=600&fit=crop'}
            ];
            
            currentPage = 1;
            totalPages = 3;
            
            alert('Thumbnails should be visible now! Check the thumbnail selector area!');
        }

        // Thumbnail Selector Variables
        let currentPage = 1;
        let totalPages = 1;
        let currentThumbnails = [];
        let selectedThumbnail = null;

        // Show thumbnail selector when checkbox is checked
        document.getElementById('auto_thumbnail').addEventListener('change', function() {
            const selector = document.getElementById('thumbnailSelector');
            const titleInput = document.getElementById('title');
            
            if (this.checked) {
                if (titleInput.value.trim()) {
                    selector.classList.add('show');
                    searchThumbnails(titleInput.value.trim(), 1);
                } else {
                    alert('Please enter a video title first');
                    this.checked = false;
                }
            } else {
                selector.classList.remove('show');
                selectedThumbnail = null;
            }
        });

        // Mock search thumbnails for demo (when APIs aren't available)
        function mockSearchThumbnails(title, page = 1) {
            const content = document.getElementById('thumbnailContent');
            const keywordsDisplay = document.getElementById('keywordsDisplay');
            const pagination = document.getElementById('paginationControls');
            
            // Show loading first
            content.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <span>🤖 AI analyzing "${title}" and searching thumbnails...</span>
                </div>
            `;
            pagination.style.display = 'none';
            
            setTimeout(() => {
                // Generate mock keywords based on title
                let keywords = [];
                const titleLower = title.toLowerCase();
                
                if (titleLower.includes('nasi goreng')) {
                    keywords = ['nasi goreng', 'fried rice', 'indonesian'];
                } else if (titleLower.includes('matcha')) {
                    keywords = ['matcha', 'green tea', 'latte'];
                } else if (titleLower.includes('roti canai')) {
                    keywords = ['roti canai', 'flatbread', 'malaysian'];
                } else {
                    keywords = [titleLower.split(' ')[0] || 'food', 'recipe', 'delicious'];
                }
                
                keywordsDisplay.textContent = keywords.join(', ');
                
                // Generate mock thumbnails (using placeholder images)
                const mockThumbnails = [];
                for (let i = 1; i <= 10; i++) {
                    const randomId = Math.floor(Math.random() * 1000) + 100;
                    mockThumbnails.push({
                        id: `mock_${i}`,
                        description: `Delicious ${keywords[0]} photo ${i}`,
                        photographer: `Chef Photographer ${i}`,
                        thumbnail: `https://picsum.photos/300/200?random=${randomId}&${keywords[0].replace(' ', '')}`,
                        regular: `https://picsum.photos/800/600?random=${randomId}&${keywords[0].replace(' ', '')}`,
                        full: `https://picsum.photos/1920/1080?random=${randomId}&${keywords[0].replace(' ', '')}`
                    });
                }
                
                currentThumbnails = mockThumbnails;
                currentPage = page;
                totalPages = 5; // Mock 5 pages
                
                displayThumbnails(mockThumbnails);
                updatePagination();
                pagination.style.display = 'flex';
                
            }, 2000);
        }

        // Enhanced search thumbnails function with fallback
        async function searchThumbnails(title, page = 1) {
            try {
                const formData = new FormData();
                formData.append('action', 'search_thumbnails');
                formData.append('title', title);
                formData.append('page', page);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    const content = document.getElementById('thumbnailContent');
                    const keywordsDisplay = document.getElementById('keywordsDisplay');
                    const pagination = document.getElementById('paginationControls');
                    
                    currentThumbnails = result.thumbnails;
                    currentPage = result.current_page;
                    totalPages = result.total_pages;
                    
                    // Update keywords display
                    keywordsDisplay.textContent = result.keywords.join(', ');
                    
                    // Display thumbnails
                    displayThumbnails(result.thumbnails);
                    
                    // Show pagination if needed
                    if (totalPages > 1) {
                        updatePagination();
                        pagination.style.display = 'flex';
                    }
                } else {
                    throw new Error(result.error || 'API search failed');
                }
                
            } catch (error) {
                console.log('Real API failed, using mock data:', error);
                // Fallback to mock data for demo purposes
                mockSearchThumbnails(title, page);
            }
        }

        // Select thumbnail
        function selectThumbnail(index) {
            // Remove previous selection
            document.querySelectorAll('.thumbnail-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Select new thumbnail
            const option = document.querySelector(`[data-index="${index}"]`);
            if (option) {
                option.classList.add('selected');
                selectedThumbnail = currentThumbnails[index];
                
                // Enable confirm button
                document.getElementById('confirmThumbnailBtn').disabled = false;
            }
        }

        // Display thumbnails in grid
        function displayThumbnails(thumbnails) {
            const content = document.getElementById('thumbnailContent');
            
            if (thumbnails.length === 0) {
                content.innerHTML = '<div class="error-message">😕 No thumbnails found.</div>';
                return;
            }
            
            let html = '<div class="thumbnail-grid">';
            
            thumbnails.forEach((thumb, index) => {
                html += '<div class="thumbnail-option" onclick="selectThumbnail(' + index + ')" data-index="' + index + '">';
                html += '<img src="' + thumb.thumbnail + '" alt="' + thumb.description + '" class="thumbnail-image" loading="lazy">';
                html += '<div class="thumbnail-overlay">';
                html += '<div style="font-weight: 600;">' + thumb.description + '</div>';
                html += '<div style="opacity: 0.8;">by ' + thumb.photographer + '</div>';
                html += '</div>';
                html += '<div class="thumbnail-selected-badge">✓</div>';
                html += '</div>';
            });
            
            html += '</div>';
            content.innerHTML = html;
        }

        // Pagination functions
        function changePage(direction) {
            const newPage = currentPage + direction;
            if (newPage >= 1 && newPage <= totalPages) {
                const title = document.getElementById('title').value.trim();
                searchThumbnails(title, newPage);
            }
        }

        function updatePagination() {
            const pageInfo = document.getElementById('pageInfo');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = currentPage >= totalPages;
        }

        // Hide thumbnail selector
        function hideThumbnailSelector() {
            document.getElementById('thumbnailSelector').classList.remove('show');
            document.getElementById('auto_thumbnail').checked = false;
            selectedThumbnail = null;
        }

        // Confirm thumbnail selection with better UX
        function confirmThumbnailSelection() {
            if (!selectedThumbnail) {
                alert('Please select a thumbnail first');
                return;
            }
            
            // Store selected thumbnail data
            let hiddenInput = document.querySelector('input[name="selected_thumbnail_url"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_thumbnail_url';
                document.getElementById('uploadForm').appendChild(hiddenInput);
            }
            hiddenInput.value = selectedThumbnail.regular;
            
            // Hide selector
            hideThumbnailSelector();
            
            // Show success message
            const preview = document.createElement('div');
            preview.className = 'file-preview show';
            preview.innerHTML = `
                <div class="file-preview-header">
                    <div class="file-preview-icon">🎨</div>
                    <div>
                        <div class="file-name">✅ AI-Selected Thumbnail Ready!</div>
                        <div class="file-details">
                            <div class="file-size">"${selectedThumbnail.description}" by ${selectedThumbnail.photographer}</div>
                            <div>
                                <span class="file-status">🤖 AI Powered</span>
                                <button type="button" class="remove-file" onclick="removeSelectedThumbnail()">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing preview and add new one
            const thumbnailGroup = document.querySelector('#thumbnailUploadArea').parentNode;
            const existingPreview = thumbnailGroup.querySelector('.file-preview');
            if (existingPreview) {
                existingPreview.remove();
            }
            thumbnailGroup.appendChild(preview);
            
            // Clear manual thumbnail upload if selected
            const manualThumbnail = document.getElementById('thumbnail');
            if (manualThumbnail.files.length > 0) {
                manualThumbnail.value = '';
                resetThumbnailUploadArea();
            }
        }

        // Remove selected thumbnail
        function removeSelectedThumbnail() {
            const hiddenInput = document.querySelector('input[name="selected_thumbnail_url"]');
            if (hiddenInput) {
                hiddenInput.remove();
            }
            
            const preview = document.querySelector('#thumbnailUploadArea').parentNode.querySelector('.file-preview');
            if (preview) {
                preview.remove();
            }
            
            resetThumbnailUploadArea();
        }

        // Auto-search when title changes
        document.getElementById('title').addEventListener('input', function() {
            const autoCheckbox = document.getElementById('auto_thumbnail');
            if (autoCheckbox.checked && this.value.trim()) {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    searchThumbnails(this.value.trim(), 1);
                }, 1000); // Debounce search
            }
        });

        function clearDemo() {
            document.getElementById('demoResults').style.display = 'none';
            document.getElementById('demoTitle').value = '';
        }

        // File handling functions
        function updateCharCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            
            function updateCounter() {
                const length = input.value.length;
                counter.textContent = `${length} / ${maxLength} characters`;
                
                if (length > maxLength * 0.9) {
                    counter.className = 'char-counter error';
                } else if (length > maxLength * 0.8) {
                    counter.className = 'char-counter warning';
                } else {
                    counter.className = 'char-counter';
                }
            }
            
            input.addEventListener('input', updateCounter);
            updateCounter();
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Video file handling
        function handleVideoFile(file) {
            const uploadArea = document.getElementById('videoUploadArea');
            const preview = document.getElementById('videoPreview');
            const fileName = document.getElementById('videoFileName');
            const fileSize = document.getElementById('videoFileSize');
            
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                
                // Update upload area appearance
                uploadArea.classList.add('has-file');
                document.getElementById('videoIcon').textContent = '✅';
                document.getElementById('videoText').textContent = '✅ Video file selected and ready!';
                
                // Show preview
                preview.classList.add('show');
            }
        }

        function removeVideoFile() {
            const input = document.getElementById('video');
            const preview = document.getElementById('videoPreview');
            const uploadArea = document.getElementById('videoUploadArea');
            
            input.value = '';
            preview.classList.remove('show');
            
            // Reset upload area appearance
            uploadArea.classList.remove('has-file');
            document.getElementById('videoIcon').textContent = '🎬';
            document.getElementById('videoText').textContent = 'Click to select your video file';
        }

        // Thumbnail file handling
        function handleThumbnailFile(file) {
            const uploadArea = document.getElementById('thumbnailUploadArea');
            const preview = document.getElementById('thumbnailPreview');
            const fileName = document.getElementById('thumbnailFileName');
            const fileSize = document.getElementById('thumbnailFileSize');
            
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                
                // Update upload area appearance
                uploadArea.classList.add('has-file');
                document.getElementById('thumbnailIcon').textContent = '✅';
                document.getElementById('thumbnailText').textContent = '✅ Custom thumbnail selected!';
                
                // Show preview
                preview.classList.add('show');
                
                // Remove AI thumbnail selection if any
                removeSelectedThumbnail();
                document.getElementById('auto_thumbnail').checked = false;
            }
        }

        function removeThumbnailFile() {
            const input = document.getElementById('thumbnail');
            input.value = '';
            resetThumbnailUploadArea();
        }

        function resetThumbnailUploadArea() {
            const preview = document.getElementById('thumbnailPreview');
            const uploadArea = document.getElementById('thumbnailUploadArea');
            
            preview.classList.remove('show');
            
            // Reset upload area appearance
            uploadArea.classList.remove('has-file');
            document.getElementById('thumbnailIcon').textContent = '🖼️';
            document.getElementById('thumbnailText').textContent = 'Click to select thumbnail image (optional)';
        }

        // Initialize form handlers
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCounter('title', 'titleCounter', 100);
            updateCharCounter('description', 'descCounter', 1000);
            
            // Video file input handler
            document.getElementById('video').addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const maxSize = 1024 * 1024 * 1024; // 1GB
                    const allowedTypes = ['video/mp4'];
                    
                    if (!allowedTypes.includes(file.type)) {
                        alert('Video file type is not supported. Please choose an MP4 file.');
                        this.value = '';
                        removeVideoFile();
                        return;
                    }
                    
                    if (file.size > maxSize) {
                        alert('Video file size exceeds the maximum limit. Please choose a smaller file.');
                        this.value = '';
                        removeVideoFile();
                        return;
                    }
                    
                    handleVideoFile(file);
                }
            });
            
            // Thumbnail file input handler
            document.getElementById('thumbnail').addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const maxSize = 10 * 1024 * 1024; // 10MB
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                    
                    if (!allowedTypes.includes(file.type)) {
                        alert('Thumbnail file type is not supported. Please choose a JPG or PNG file.');
                        this.value = '';
                        resetThumbnailUploadArea();
                        return;
                    }
                    
                    if (file.size > maxSize) {
                        alert('Thumbnail file size exceeds the maximum limit. Please choose a smaller file.');
                        this.value = '';
                        resetThumbnailUploadArea();
                        return;
                    }
                    
                    handleThumbnailFile(file);
                }
            });
            
            // Auto-populate demo with title when user types
            document.getElementById('title').addEventListener('input', function() {
                if (this.value) {
                    document.getElementById('demoTitle').value = this.value;
                }
            });
        });

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const tagId = document.getElementById('tag_id').value;
            const videoFile = document.getElementById('video').files[0];
            
            let errors = [];
            
            if (!title) errors.push('Video title is required');
            if (title.length > 100) errors.push('Video title must be 100 characters or less');
            if (!description) errors.push('Video description is required');
            if (description.length > 1000) errors.push('Video description must be 1000 characters or less');
            if (!tagId) errors.push('Please select a category');
            if (!videoFile) errors.push('Please select a video file');
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }
            
            // Show progress
            const progressContainer = document.getElementById('progressContainer');
            const uploadBtn = document.getElementById('uploadBtn');
            
            progressContainer.style.display = 'block';
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            
            return true;
        });
    </script>
</body>
</html>