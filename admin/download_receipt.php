<?php
// download_receipt.php
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo 'Missing payment ID.';
    exit;
}
$payment_id = $_GET['id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}
$stmt = $conn->prepare("SELECT receipt FROM payment WHERE payment_id = ?");
$stmt->bind_param('s', $payment_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}
$stmt->bind_result($receipt);
$stmt->fetch();
$stmt->close();
$conn->close();

if (!$receipt) {
    http_response_code(404);
    echo 'No receipt uploaded.';
    exit;
}
// Try to detect file type (PDF or image)
$finfo = finfo_open();
$mime = finfo_buffer($finfo, $receipt, FILEINFO_MIME_TYPE);
finfo_close($finfo);

// Only show HTML wrapper for browser (not XHR) and for viewable types
$is_viewable = in_array($mime, ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']);
$is_xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($is_viewable && !$is_xhr) {
    // Output HTML with back button and embedded file
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>View Receipt</title>';
    echo '<style>body{background:#f7fafc;margin:0;padding:0;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;}';
    echo '.back-btn{position:fixed;top:32px;left:32px;z-index:1001;padding:10px 24px;background:#50B88E;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:500;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.07);transition:background 0.2s;}';
    echo '.back-btn:hover{background:#3d9c7a;}';
    echo '.receipt-view{max-width:90vw;max-height:80vh;box-shadow:0 2px 12px rgba(0,0,0,0.08);border-radius:8px;background:#fff;padding:16px;margin-top:48px;}';
    echo '</style></head><body>';
    echo '<button class="back-btn" onclick="window.history.length > 1 ? window.history.back() : window.close()">&larr; Back</button>';
    echo '<div class="receipt-view">';
    if ($mime === 'application/pdf') {
        $data = base64_encode($receipt);
        echo '<embed src="data:application/pdf;base64,' . $data . '" type="application/pdf" width="800" height="600" style="max-width:90vw;max-height:80vh;">';
        echo '<p>If the PDF does not display, <a href="data:application/pdf;base64,' . $data . '" download="receipt.pdf">download it here</a>.</p>';
    } elseif ($mime === 'image/jpeg' || $mime === 'image/jpg' || $mime === 'image/png') {
        $data = base64_encode($receipt);
        $img_type = ($mime === 'image/png') ? 'png' : 'jpeg';
        echo '<img src="data:image/' . $img_type . ';base64,' . $data . '" alt="Receipt Image" style="max-width:100%;max-height:70vh;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">';
    }
    echo '</div></body></html>';
    exit;
}
// Fallback: direct download or inline for unknown types
if ($mime === 'application/pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="receipt.pdf"');
} elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
    header('Content-Type: image/jpeg');
    header('Content-Disposition: inline; filename="receipt.jpg"');
} elseif ($mime === 'image/png') {
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="receipt.png"');
} else {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="receipt.bin"');
}
header('Content-Length: ' . strlen($receipt));
echo $receipt;
exit; 