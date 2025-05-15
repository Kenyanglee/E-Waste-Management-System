<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit();
}

if (!isset($_GET['submission_id'])) {
    http_response_code(400);
    echo 'Missing submission_id';
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT ewaste_image FROM submission WHERE submission_id = ?");
    $stmt->execute([$_GET['submission_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !$row['ewaste_image']) {
        http_response_code(404);
        echo 'File not found';
        exit();
    }

    // Try to get the mime type from the file content (fallback to PDF if not image)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($row['ewaste_image']);
    if (!$mime) {
        $mime = 'application/octet-stream';
    }

    header('Content-Type: ' . $mime);
    // Inline display for images and PDFs
    if ($mime === 'application/pdf' || strpos($mime, 'image/') === 0) {
        header('Content-Disposition: inline; filename="ewaste_file.' . ($mime === 'application/pdf' ? 'pdf' : 'jpg') . '"');
    } else {
        header('Content-Disposition: attachment; filename="ewaste_file.bin"');
    }
    echo $row['ewaste_image'];
    exit();
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
    exit();
} 