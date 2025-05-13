<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = 'uploads/products/' . basename($_FILES['testfile']['name']);
    if (move_uploaded_file($_FILES['testfile']['tmp_name'], $target)) {
        echo "Upload successful! File saved as: $target";
    } else {
        echo "Upload failed!";
    }
}
?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="testfile">
    <button type="submit">Upload</button>
</form> 