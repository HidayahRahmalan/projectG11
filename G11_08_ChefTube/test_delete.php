<?php
// --- This is a test script to debug the file deletion problem ---

// Turn on all error reporting to see everything.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// The exact file that is failing to be replaced.
$file_to_delete = 'C:/xampp/htdocs/cheftube/cc/C000120250715/pfp/pfp.jpg';

echo "<h1>Deletion Test</h1>";
echo "<p>Attempting to delete this file: <strong>" . $file_to_delete . "</strong></p>";
echo "<hr>";

// First, clear PHP's file cache. This is critical.
clearstatcache();

// Check if the file exists before trying to delete it.
if (file_exists($file_to_delete)) {
    echo "<p>File found. Now attempting to delete...</p>";
    
    // Attempt the deletion. This is the function that is failing.
    if (unlink($file_to_delete)) {
        echo '<h2 style="color:green;">SUCCESS!</h2>';
        echo "<p>The file was successfully deleted.</p>";
    } else {
        echo '<h2 style="color:red;">FAILURE!</h2>';
        echo "<p>The <strong>unlink()</strong> command failed. This is the problem.</p>";
        $error = error_get_last();
        echo "<p><strong>PHP Error Message:</strong> " . ($error['message'] ?? "No error message was captured. This almost always means it's a file permission or file lock issue from the operating system.") . "</p>";
    }

} else {
    echo '<h2 style="color:orange;">FILE NOT FOUND</h2>';
    echo "<p>The file does not exist at that path. There is nothing to delete.</p>";
}
?>