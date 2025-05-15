<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: ../user/auth.php');
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_sql = "SELECT COUNT(*) as total 
              FROM payment p 
              LEFT JOIN delivery d ON p.delivery_id = d.delivery_id 
              LEFT JOIN dropoff do ON p.dropoff_id = do.dropoff_id 
              WHERE (d.status = 4 OR do.status = 1)";
$total_result = $conn->query($total_sql);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Handle payment update
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'], $_POST['payment_id'])) {
    $payment_id = $_POST['payment_id'];
    $date_paid = $_POST['date_paid'];
    $new_status = 1; // Set to 1 (completed)
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get quotation_id and user_id for history
        $get_info_sql = "SELECT p.quotation_id, q.user_id 
                        FROM payment p 
                        JOIN quotation q ON p.quotation_id = q.quotation_id 
                        WHERE p.payment_id = ?";
        $stmt = $conn->prepare($get_info_sql);
        $stmt->bind_param('s', $payment_id);
        $stmt->execute();
        $info_result = $stmt->get_result();
        $info = $info_result->fetch_assoc();
        
        if (!$info) {
            throw new Exception('Payment information not found');
        }
        
        // Find next available history_id
        $history_id = null;
        $max_attempts = 100; // Prevent infinite loop
        $attempt = 1;
        
        while ($history_id === null && $attempt <= $max_attempts) {
            $check_sql = "SELECT COUNT(*) as count FROM history WHERE history_id = ?";
            $stmt = $conn->prepare($check_sql);
            $history_id_candidate = 'H#' . $attempt;
            $stmt->bind_param('s', $history_id_candidate);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] == 0) {
                $history_id = 'H#' . $attempt;
            }
            $attempt++;
        }
        
        if ($history_id === null) {
            throw new Exception('Failed to generate unique history ID');
        }
        
        // Update date and status in payment table
        $update_sql = "UPDATE payment SET date_paid=?, status=? WHERE payment_id=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('sis', $date_paid, $new_status, $payment_id);
        $stmt->execute();
        $stmt->close();
        
        // Handle receipt upload if file is provided
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['receipt'];
            $fileTmp = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileType = mime_content_type($fileTmp);
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('Only PDF, JPG, JPEG, and PNG files are allowed.');
            }
            
            if ($fileSize > 16 * 1024 * 1024) {
                throw new Exception('File size exceeds 16MB limit.');
            }
            
            $receipt = file_get_contents($fileTmp);
            $stmt = $conn->prepare("UPDATE payment SET receipt=? WHERE payment_id=?");
            $stmt->bind_param('bs', $null, $payment_id);
            $null = NULL;
            $stmt->send_long_data(0, $receipt);
            $stmt->execute();
            $stmt->close();
        }
        
        // Insert into history (status always 0)
        $history_sql = "INSERT INTO history (history_id, payment_id, quotation_id, user_id) 
                       VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($history_sql);
        $stmt->bind_param('ssss', $history_id, $payment_id, $info['quotation_id'], $info['user_id']);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        header('Location: payment_admin.php?success=1');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $upload_message = '<span style="color:#C53030;">Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
    }
}

// Serve receipt file inline if requested
if (isset($_GET['view_receipt'])) {
    $pid = $_GET['view_receipt'];
    $stmt = $conn->prepare('SELECT receipt FROM payment WHERE payment_id = ?');
    $stmt->bind_param('s', $pid);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($receipt);
        $stmt->fetch();
        if ($receipt) {
            // Try to detect file type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($receipt);
            if (!$mime) $mime = 'application/octet-stream';
            header('Content-Type: ' . $mime);
            if ($mime === 'application/pdf') {
                header('Content-Disposition: inline; filename="receipt.pdf"');
            } else {
                header('Content-Disposition: inline; filename="receipt"');
            }
            echo $receipt;
            exit();
        }
    }
    // If not found or empty, show error
    echo '<h2 style="color:red;">Receipt not found.</h2>';
    exit();
}

// Fetch payment records
$sql = "SELECT p.payment_id, p.bank_acc, p.bank_name, p.name, p.date_paid, p.status, p.receipt, p.quotation_id, q.total 
        FROM payment p 
        LEFT JOIN delivery d ON p.delivery_id = d.delivery_id 
        LEFT JOIN dropoff do ON p.dropoff_id = do.dropoff_id 
        LEFT JOIN quotation q ON p.quotation_id = q.quotation_id 
        WHERE (d.status = 4 OR do.status = 1) 
        ORDER BY p.status ASC, p.date_paid DESC 
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        :root {
            --sidebar-width: 280px;
            --primary-color: #50B88E;
            --secondary-color: #4A90E2;
            --text-primary: #2D3748;
            --text-secondary: #718096;
            --bg-light: #F7FAFC;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition-default: all 0.3s ease;
            --max-width: 1920px;
            --content-width: 1640px;
        }
        body {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-light);
            color: var(--text-primary);
            max-width: var(--max-width);
            margin: 0 auto;
            overflow-x: hidden;
        }
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            padding: 24px 24px 32px;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 100;
        }
        .logo {
            padding: 4px 12px;
            margin-bottom: 32px;
        }
        .logo img {
            width: 100%;
            height: auto;
        }
        .menu {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 8px 16px;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .menu-item.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            max-width: var(--content-width);
            width: 100%;
            box-sizing: border-box;
        }
        .top-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 10px 20px;
            background-color: #D2CECE;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .user-actions {
            display: flex;
            align-items: center;
            gap: 32px;
        }
        .account {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #C4C4C4;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
        .account:hover {
            transform: scale(1.1);
            animation: pulse-profile 2.5s infinite;
        }
        .account img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        @keyframes pulse-profile {
            0% {
                box-shadow: 0 0 5px 2px rgba(255, 255, 255, 0.8);
            }
            50% {
                box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.6);
            }
            100% {
                box-shadow: 0 0 5px 2px rgba(255, 255, 255, 0.8);
            }
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
            transition: var(--transition-default);
            border: 1px solid rgba(0, 0, 0, 0.05);
            max-width: 100%;
            box-sizing: border-box;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--bg-light);
        }
        .card-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--text-primary);
        }
        .search-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            background-color: white;
            padding: 12px 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 100%;
            transition: all 0.3s ease;
            box-sizing: border-box;
            border: 1px solid #E2E8F0;
        }
        .search-bar i {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        .search-bar input {
            border: none;
            outline: none;
            width: 100%;
            color: var(--text-primary);
            font-size: 1rem;
        }
        .search-bar:hover, .search-bar:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }
        .search-bar:focus-within i {
            color: var(--primary-color);
        }
        .table-container {
            overflow-x: auto;
            max-width: 100%;
            box-sizing: border-box;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
        }
        th, td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
            font-size: 1rem;
        }
        th {
            background-color: #F8FAFC;
            font-weight: 700;
            color: #2D3748;
            letter-spacing: 0.02em;
        }
        tr:nth-child(even) td {
            background-color: #FAFAFA;
        }
        tr:hover td {
            background-color: #F1F5F9;
            transition: background 0.2s;
        }
        .status-badge {
            padding: 6px 18px;
            border-radius: 999px;
            font-size: 0.95em;
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.02em;
        }
        .status-pending {
            background-color: #FEF3C7;
            color: #B7791F;
            border: 1px solid #F6E05E;
        }
        .status-completed {
            background-color: #C6F6D5;
            color: #276749;
            border: 1px solid #68D391;
        }
        .clickable-cell {
            cursor: pointer;
            color: #3182CE;
            text-decoration: underline;
            border-radius: 6px;
            padding: 4px 10px;
            transition: background 0.2s, color 0.2s;
            display: inline-block;
        }
        .clickable-cell:hover {
            color: #2B6CB0;
            background: #E6F0FA;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }
        .page-button {
            padding: 8px 16px;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
            transition: var(--transition-default);
        }
        .page-button:hover {
            background-color: var(--bg-light);
        }
        .page-button.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal.show {
            opacity: 1;
        }
        .modal-content {
            background-color: white;
            border-radius: 12px;
            padding: 24px;
            width: 600px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.5rem;
        }
        .close-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s ease;
            padding: 5px;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-button:hover {
            color: var(--text-primary);
            background-color: #f3f4f6;
        }
        .modal-body {
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .modal-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }
        .modal-button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .modal-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .file-upload-container {
            position: relative;
            margin-bottom: 10px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .file-upload-dropzone {
            width: 100%;
            max-width: 340px;
            min-height: 120px;
            background: #e2e8f0;
            border: 2px dashed #50B88E;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color 0.3s, background 0.3s;
            margin: 0 auto;
            padding: 18px 0 12px 0;
        }
        .file-upload-dropzone:hover {
            border-color: #4A90E2;
            background:rgb(170, 170, 170);
        }
        .file-upload-dropzone img {
            width: 48px;
            height: 48px;
            margin-bottom: 10px;
            filter: drop-shadow(0 0 2px #888);
        }
        .file-upload-dropzone span {
            color: #2d3748;
            font-size: 1.1em;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-upload-note {
            color: #b2cfc2;
            font-size: 0.95em;
            margin-top: 6px;
            text-align: center;
        }
        .file-name {
            margin-top: 8px;
            font-size: 0.95em;
            color: #23443b;
            text-align: center;
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000;
        }
        .alert {
            padding: 12px 24px;
            border-radius: 50px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s forwards;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .alert-success {
            background-color: #C6F6D5;
            color: #2F855A;
        }
        .alert-error {
            background-color: #FED7D7;
            color: #C53030;
        }
        .alert i {
            font-size: 1.2rem;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
        /* Responsive Styles */
        @media (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .payment-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
                padding: 24px 12px;
            }
            
            .sidebar .logo {
                display: none;
            }
            
            .sidebar .menu-item span {
                display: none;
            }
            
            .sidebar .menu-item {
                justify-content: center;
                padding: 16px;
            }
            
            .sidebar .menu-item i {
                margin: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .submenu {
                position: absolute;
                left: 80px;
                top: 0;
                background: var(--primary-color);
                width: 200px;
                border-radius: 0 8px 8px 0;
                box-shadow: 4px 4px 10px rgba(0,0,0,0.1);
            }
            
            .submenu .menu-item {
                padding: 12px 16px;
            }
            
            .submenu .menu-item span {
                display: inline;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .table th, .table td {
                min-width: 120px;
            }

            .payment-details {
                flex-direction: column;
            }

            .payment-section {
                width: 100%;
            }

            .transaction-history {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 10px;
            }
            
            .user-actions {
                gap: 16px;
            }
            
            .account {
                width: 32px;
                height: 32px;
            }
            
            .card {
                padding: 16px;
            }
            
            .chart-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .search-bar {
                width: 100%;
            }
            
            .filter-btn-pro {
                width: 100%;
                justify-content: center;
            }
            
            .payment-grid {
                grid-template-columns: 1fr;
            }

            .payment-stats {
                grid-template-columns: 1fr;
            }

            .table th, .table td {
                padding: 8px;
                font-size: 0.9rem;
            }

            .btn {
                padding: 6px 12px;
                font-size: 0.9rem;
            }

            .modal-content {
                width: 95%;
                padding: 15px;
            }

            .payment-form {
                grid-template-columns: 1fr;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-row {
                flex-direction: column;
            }

            .form-actions {
                flex-direction: column;
                gap: 10px;
            }

            .payment-method-options {
                flex-direction: column;
            }

            .payment-method-option {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            
            .card {
                padding: 12px;
            }
            
            .payment-card {
                padding: 12px;
            }
            
            .payment-card h3 {
                font-size: 1rem;
            }
            
            .payment-card p {
                font-size: 0.8rem;
            }

            .table th, .table td {
                padding: 6px;
                font-size: 0.8rem;
            }

            .btn {
                padding: 4px 8px;
                font-size: 0.8rem;
            }

            .modal-content {
                padding: 10px;
            }

            .modal-header h3 {
                font-size: 1.1rem;
            }

            .payment-meta {
                flex-direction: column;
                gap: 5px;
            }

            .status-badge {
                font-size: 0.8rem;
                padding: 4px 8px;
            }

            .form-actions button {
                width: 100%;
            }

            .payment-summary {
                padding: 10px;
            }

            .payment-method-icon {
                width: 24px;
                height: 24px;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .top-bar,
            .filter-btn-pro,
            .search-bar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
            }

            .table th, .table td {
                border: 1px solid #ddd;
            }

            .payment-details {
                page-break-inside: avoid;
            }

            .btn, .action-button {
                display: none;
            }

            .transaction-history {
                page-break-inside: avoid;
            }

            .payment-method-options {
                display: none;
            }
        }
        .submenu {
            display: none;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .submenu .menu-item {
            padding: 12px 16px;
            font-size: 0.95em;
        }
        .submenu.active {
            display: block !important;
        }
        .fa-chevron-down {
            transition: transform 0.3s ease;
        }
        .fa-chevron-down.active {
            transform: rotate(180deg);
        }
    </style>
</head>
<body>
    <!-- Sidebar (copy from submission_admin.php) -->
    <div class="sidebar">
        <div class="logo">
            <img src="../user/assets/homepage/logo.png" alt="Nothing Wasted Logo">
        </div>
        <div class="menu">
            <a href="admin.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="report.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="userlist.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>User List</span>
            </a>
            <a href="quotation.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Quotation</span>
            </a>
            <a href="feedbackmanagement.php" class="menu-item">
                <i class="fas fa-comment-alt"></i>
                <span>Feedback</span>
            </a>
            <a href="deliverymanagement.php" class="menu-item">
                <i class="fas fa-clock"></i>
                <span>Delivery Management</span>
            </a>
            <a href="inventory.php" class="menu-item">
                <i class="fas fa-box"></i>
                <span>Inventory</span>
            </a>
            <a href="submission_admin.php" class="menu-item">
                <i class="fas fa-briefcase"></i>
                <span>E-Waste Submission</span>
            </a>
            <a href="payment_admin.php" class="menu-item active">
                <i class="fas fa-shopping-cart"></i>
                <span>Payment</span>
                <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
            </a>
            <div class="submenu" style="display: none; padding-left: 40px;">
                <a href="reward_admin.php" class="menu-item">
                    <i class="fas fa-gift"></i>
                    <span>Reward</span>
                </a>
            </div>
        </div>
        <div class="bottom-menu">
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>
    <div class="main-content">
        <div class="top-bar">
            <div class="user-actions">
                <div class="account">
                    <a href="settings.php">
                        <img src="../user/assets/homepage/account.png" alt="User Avatar">
                    </a>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2>Payment Records</h2>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by Payment ID, Name, Bank, etc...">
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Bank Account</th>
                            <th>Bank Name</th>
                            <th>Name</th>
                            <th>Total (RM)</th>
                            <th>Date Paid</th>
                            <th>Status</th>
                            <th>Quotation ID</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody id="paymentTableBody">
                        <?php
                        if ($upload_message) {
                            echo '<tr><td colspan="9" style="text-align:center;">' . $upload_message . '</td></tr>';
                        }
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                $pid = htmlspecialchars($row['payment_id']);
                                $pid_url = urlencode($row['payment_id']);
                                echo '<tr data-payment-id="' . $pid . '" class="clickable-row">';
                                echo '<td>' . $pid . '</td>';
                                echo '<td>' . htmlspecialchars($row['bank_acc'] ?? '') . '</td>';
                                echo '<td>' . htmlspecialchars($row['bank_name'] ?? '') . '</td>';
                                echo '<td>' . htmlspecialchars($row['name'] ?? '') . '</td>';
                                // Total Amount
                                $total = number_format($row['total'] ?? 0, 2);
                                echo '<td>' . $total . '</td>';
                                // Date Paid
                                $date_val = htmlspecialchars($row['date_paid'] ?? '');
                                echo '<td>' . ($date_val ? $date_val : '<span style="color:#A0AEC0;">Awaiting Payment</span>') . '</td>';
                                // Status
                                $status_class = ($row['status'] ?? 0) == 1 ? 'status-completed' : 'status-pending';
                                $status_val = ($row['status'] ?? 0) == 1 ? 'Completed' : 'Pending';
                                echo '<td><span class="status-badge ' . $status_class . '">' . $status_val . '</span></td>';
                                // Quotation ID
                                echo '<td>' . htmlspecialchars($row['quotation_id'] ?? '') . '</td>';
                                // Receipt
                                $has_receipt = !empty($row['receipt']);
                                if ($has_receipt) {
                                    echo '<td><a href="payment_admin.php?view_receipt=' . $pid_url . '" target="_blank" style="color:#4A90E2;text-decoration:underline;">View/Upload</a></td>';
                                } else {
                                    echo '<td><span style="color:#A0AEC0;">Awaiting Payment</span></td>';
                                }
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="9" style="padding:20px; text-align:center; color:#A0AEC0;">No payment records found.</td></tr>';
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <button class="page-button" onclick="window.location.href='?page=<?php echo $page-1; ?>'">Previous</button>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <button class="page-button <?php echo $i === $page ? 'active' : ''; ?>" onclick="window.location.href='?page=<?php echo $i; ?>'">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <button class="page-button" onclick="window.location.href='?page=<?php echo $page+1; ?>'">Next</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Modal for editing payment details -->
    <div class="modal" id="editModal">
        <div class="modal-content" id="editModalContent" style="max-width:500px;">
            <div class="modal-header">
                <h3 id="modalTitle">Update Payment Details</h3>
                <button class="close-button" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <form method="POST" enctype="multipart/form-data" id="paymentUpdateForm">
                    <input type="hidden" name="payment_id" id="paymentId">
                    <input type="hidden" name="new_status" value="1">
                    <div style="margin-bottom:20px;">
                        <label style="display:block; margin-bottom:8px; font-weight:500;">Date Paid</label>
                        <input type="date" name="date_paid" id="datePaid" style="width:100%; padding:8px; border-radius:4px; border:1px solid #CBD5E0;" required>
                        <div id="dateError" style="color:#C53030; font-size:0.97em; margin-top:6px; display:none;">Date is required.</div>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="display:block; margin-bottom:8px; font-weight:500;">Receipt</label>
                        <div class="file-upload-container">
                            <label class="file-upload-dropzone">
                                <img src="../user/assets/edit/upload.png" alt="Upload">
                                <span>Upload Your Image or PDF Here</span>
                                <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="file-upload-input" id="receiptInput" required>
                            </label>
                            <div id="receiptError" style="color:#C53030; font-size:0.97em; margin-top:6px; display:none;">Receipt is required.</div>
                            <div class="file-upload-note">Maximum file size: 16MB</div>
                        </div>
                        <div class="file-name" id="fileName"></div>
                        <div id="currentReceipt" style="margin-top:10px;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="update_payment" id="saveChangesBtn" style="padding:8px 16px; background:#50B88E; color:#fff; border:none; border-radius:4px; cursor:pointer;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="alert-container" id="alertContainer"></div>
    <script>
        // Sidebar submenu toggle logic
        const menuItems = document.querySelectorAll('.menu-item');
        let paymentMenuItem = null;
        menuItems.forEach(item => {
            if (item.querySelector('.fa-shopping-cart')) {
                paymentMenuItem = item;
            }
        });
        const paymentSubmenu = paymentMenuItem ? paymentMenuItem.nextElementSibling : null;
        const chevronIcon = paymentMenuItem ? paymentMenuItem.querySelector('.fa-chevron-down') : null;

        if (paymentMenuItem && paymentSubmenu && chevronIcon) {
            paymentMenuItem.addEventListener('click', function(e) {
                // Only prevent default if clicking the chevron icon
                if (e.target.classList.contains('fa-chevron-down')) {
                    e.preventDefault();
                    paymentSubmenu.classList.toggle('active');
                    chevronIcon.classList.toggle('active');
                    // Toggle display style for submenu
                    if (paymentSubmenu.classList.contains('active')) {
                        paymentSubmenu.style.display = 'block';
                    } else {
                        paymentSubmenu.style.display = 'none';
                    }
                }
            });
        }

        // Search functionality for payment table
        const searchInput = document.getElementById('searchInput');
        const paymentTableBody = document.getElementById('paymentTableBody');
        const rows = paymentTableBody.getElementsByTagName('tr');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            Array.from(rows).forEach(row => {
                const cells = row.getElementsByTagName('td');
                let found = false;
                for (let i = 0; i < cells.length; i++) {
                    const cellText = cells[i].textContent.toLowerCase();
                    if (cellText.includes(searchTerm)) {
                        found = true;
                        break;
                    }
                }
                row.style.display = found ? '' : 'none';
            });
        });

        // Modal logic for editing payment details
        const editModal = document.getElementById('editModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        const paymentIdInput = document.getElementById('paymentId');
        const datePaidInput = document.getElementById('datePaid');
        const currentReceiptDiv = document.getElementById('currentReceipt');
        const receiptError = document.getElementById('receiptError');

        function closeEditModal() {
            editModal.classList.remove('show');
            setTimeout(() => { editModal.style.display = 'none'; }, 300);
        }

        // Handle row clicks
        document.querySelectorAll('.clickable-row').forEach(row => {
            row.addEventListener('click', function() {
                const pid = this.getAttribute('data-payment-id');
                const dateCell = this.cells[5].textContent;
                const hasReceipt = this.cells[8].textContent.includes('View');
                // Check status cell (6th index)
                const statusCell = this.cells[6].querySelector('.status-badge');
                const isPending = statusCell && statusCell.classList.contains('status-pending');
                if (!isPending) {
                    // Optionally show info alert:
                    // showAlert('This payment is already completed.', 'error');
                    return;
                }
                // Set form values
                paymentIdInput.value = pid;
                datePaidInput.value = dateCell.includes('Not Set') ? '' : dateCell;
                // Show current receipt if exists
                if (hasReceipt) {
                    currentReceiptDiv.innerHTML = `<a href="download_receipt.php?id=${encodeURIComponent(pid)}" target="_blank" style="color:#4A90E2;text-decoration:underline;">View Current Receipt</a>`;
                } else {
                    currentReceiptDiv.innerHTML = '';
                }
                // Show modal and validate date
                openEditModal();
            });
        });

        // Close modal when clicking outside
        editModal.addEventListener('click', function(event) {
            if (event.target === editModal) {
                closeEditModal();
            }
        });

        // File input handling
        const receiptInput = document.getElementById('receiptInput');
        const fileName = document.getElementById('fileName');
        receiptInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileName.textContent = this.files[0].name;
                validateReceipt();
            } else {
                fileName.textContent = '';
                validateReceipt();
            }
        });

        // Date required validation
        const dateError = document.getElementById('dateError');
        const saveChangesBtn = document.getElementById('saveChangesBtn');
        const paymentUpdateForm = document.getElementById('paymentUpdateForm');

        function isDateValid() {
            return !!datePaidInput.value;
        }

        function isReceiptValid() {
            return !!(receiptInput.files && receiptInput.files[0]);
        }

        function updateSaveButtonState() {
            if (isDateValid() && isReceiptValid()) {
                saveChangesBtn.disabled = false;
                saveChangesBtn.style.opacity = 1;
                saveChangesBtn.style.cursor = 'pointer';
            } else {
                saveChangesBtn.disabled = true;
                saveChangesBtn.style.opacity = 0.6;
                saveChangesBtn.style.cursor = 'not-allowed';
            }
        }

        datePaidInput.addEventListener('input', updateSaveButtonState);
        receiptInput.addEventListener('change', updateSaveButtonState);
        paymentUpdateForm.addEventListener('submit', function(e) {
            if (!isDateValid() || !isReceiptValid()) {
                e.preventDefault();
                if (!isDateValid()) showAlert('Date is required.', 'error');
                if (!isReceiptValid()) showAlert('Receipt is required.', 'error');
            }
        });

        // On modal open, always update button state
        function openEditModal() {
            editModal.style.display = 'flex';
            setTimeout(() => { editModal.classList.add('show'); }, 10);
            updateSaveButtonState();
        }

        // Alert function (copied from userlist.php)
        function showAlert(message, type = 'error') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            alert.innerHTML = `\n                <i class="fas fa-${icon}"></i>\n                <span>${message}</span>\n            `;
            alertContainer.appendChild(alert);
            setTimeout(() => alert.remove(), 3000);
        }

        // Show success alert if redirected with success param
        if (window.location.search.includes('success=1')) {
            showAlert('Payment details updated successfully', 'success');
            // Remove the param from the URL without reloading
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        }
    </script>
</body>
</html>
