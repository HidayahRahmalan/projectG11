<?php
echo "<h3>PHP Upload Settings:</h3>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'On' : 'Off') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "max_input_time: " . ini_get('max_input_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
?>