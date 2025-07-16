<?php
echo "PHP is working!<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br>";
echo "POST data: ";
var_dump($_POST);
echo "<br>FILES data: ";
var_dump($_FILES);
?>

<form method="POST" enctype="multipart/form-data">
    <input type="text" name="test_field" placeholder="Test field">
    <input type="file" name="test_file">
    <button type="submit">Test Submit</button>
</form>