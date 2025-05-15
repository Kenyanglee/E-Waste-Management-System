<?php
session_start();
if (!isset($_SESSION['admin'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    } else {
        header('Location: ../user/auth.php');
        exit();
    }
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');

        // Get quotation details
        if (isset($_GET['action']) && $_GET['action'] === 'get_details' && isset($_GET['quotation_id'])) {
            try {
                $quotation_id = trim($_GET['quotation_id']);
                
                // Get quotation details with user information
                $stmt = $conn->prepare("
                    SELECT 
                        q.*,
                        u.user_name,
                        u.email,
                        a.admin_id,
                        a.name as admin_name,
                        s.laptop_qty, s.desktop_qty, s.monitor_qty, s.printer_qty,
                        s.phone_qty, s.appliance_qty, s.wearables_qty, s.cables_qty,
                        s.accessories_qty,
                        DATE_FORMAT(q.validity, '%Y-%m-%d') as formatted_validity,
                        p.payment_id,
                        d.delivery_id,
                        do.dropoff_id,
                        q.addressline1, q.addressline2, q.zipcode, q.city, q.state
                    FROM quotation q 
                    JOIN user u ON q.user_id = u.user_id 
                    LEFT JOIN admin a ON q.admin_id = a.admin_id
                    LEFT JOIN submission s ON q.submission_id = s.submission_id
                    LEFT JOIN payment p ON q.quotation_id = p.quotation_id
                    LEFT JOIN delivery d ON q.quotation_id = d.quotation_id
                    LEFT JOIN dropoff do ON q.quotation_id = do.quotation_id
                    WHERE q.quotation_id = :quotation_id
                ");
                $stmt->bindParam(':quotation_id', $quotation_id);
                $stmt->execute();
                $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($quotation) {
                    echo json_encode([
                        'success' => true,
                        'quotation' => $quotation
                    ]);
                } else {
                    throw new Exception('Quotation not found');
                }
            } catch (Exception $e) {
                error_log("Error in quotation.php: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit();
        }

        // Check and update expired quotations
        if (isset($_GET['action']) && $_GET['action'] === 'check_validity') {
            try {
                // Start transaction
                $conn->beginTransaction();

                // Update quotations to Expired
                $stmt = $conn->prepare("
                    UPDATE quotation 
                    SET status = 'Expired' 
                    WHERE status = 'pending' 
                    AND validity < CURDATE()
                ");
                $stmt->execute();
                
                $affected_rows = $stmt->rowCount();

                // Update related submissions to Completed
                $stmt = $conn->prepare("
                    UPDATE submission s
                    JOIN quotation q ON s.submission_id = q.submission_id
                    SET s.status = 'Completed'
                    WHERE q.status = 'Expired'
                ");
                $stmt->execute();

                // Commit transaction
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => $affected_rows . ' quotation(s) marked as Expired',
                    'affected_rows' => $affected_rows
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                error_log("Error in quotation.php: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit();
        }
    }

    // Regular page load - get quotations list with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $stmt = $conn->query("SELECT COUNT(*) FROM quotation");
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    $stmt = $conn->prepare("
        SELECT 
            q.*,
            u.user_name,
            u.email,
            a.admin_id,
            a.name as admin_name,
            DATE_FORMAT(q.validity, '%Y-%m-%d') as formatted_validity
        FROM quotation q 
        JOIN user u ON q.user_id = u.user_id 
        LEFT JOIN admin a ON q.admin_id = a.admin_id
        ORDER BY 
            CASE q.status
                WHEN 'pending' THEN 1
                WHEN 'accepted' THEN 2
                WHEN 'rejected' THEN 3
                WHEN 'completed' THEN 4
                ELSE 5
            END,
            q.validity DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    } else {
        die("Connection failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotations - Admin Dashboard</title>
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
            z-index: 10;
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
        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .card-header h2 {
            margin: 0;
            white-space: nowrap;
        }
        .search-bar {
            position: relative;
            width: 400px;
        }
        .search-bar input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--text-primary);
            background: white;
            transition: all 0.3s ease;
        }
        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(80, 184, 142, 0.2);
        }
        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #A0AEC0;
            font-size: 1.1rem;
        }
        .search-bar input::placeholder {
            color: #A0AEC0;
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
            table-layout: fixed;
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
            word-wrap: break-word;
        }
        th {
            background-color: var(--bg-light);
            font-weight: 600;
            color: var(--text-primary);
        }
        tr:hover {
            background-color: var(--bg-light);
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
            width: 800px;
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
            padding: 20px;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.25rem;
        }
        .close-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0;
        }
        .modal-body {
            padding: 20px;
            color: var(--text-secondary);
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #E2E8F0;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 500;
            color: var(--text-primary);
        }
        .detail-value {
            color: var(--text-secondary);
        }
        .quotation-details {
            margin-bottom: 20px;
        }
        .quotation-details h4 {
            margin: 20px 0 10px;
            color: var(--text-primary);
        }
        tr {
            cursor: pointer;
        }
        tr:hover {
            background-color: #F7FAFC;
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
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        @media (max-width: 1920px) {
            .main-content { padding: 20px; }
            .card { padding: 20px; }
            th, td { padding: 10px 12px; }
        }
        @media (max-width: 1600px) {
            :root { --content-width: 1360px; }
        }
        @media (max-width: 1366px) {
            :root { --content-width: 1086px; }
            .search-bar { width: 300px; }
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            text-align: center;
            display: inline-block;
            min-width: 100px;
        }
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .status-accepted {
            background-color: #10B981;
            color: white;
        }
        .status-rejected {
            background-color: #EF4444;
            color: white;
        }
        .status-completed {
            background-color: #6B7280;
            color: white;
        }
        .status-expired {
            background-color: #800000;
            color: white;
        }
        .validity-button {
            background-color: #718096;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            margin-left: auto;
        }
        .validity-button:hover {
            background-color: #4A5568;
            transform: translateY(-1px);
        }
        .validity-button i {
            font-size: 1rem;
        }
        @keyframes pulse-profile {
            0% {
                box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.8);
            }
            50% {
                box-shadow: 0 0 20px 10px rgba(255, 255, 255, 0.6);
            }
            100% {
                box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.8);
            }
        }
        /* Responsive Styles */
        @media (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .quotation-grid {
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

            .quotation-details {
                flex-direction: column;
            }

            .quotation-section {
                width: 100%;
            }

            .price-calculation {
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
            
            .quotation-grid {
                grid-template-columns: 1fr;
            }

            .quotation-card {
                padding: 15px;
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

            .quotation-form {
                grid-template-columns: 1fr;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .price-input {
                width: 100%;
            }

            .points-calculation {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            
            .card {
                padding: 12px;
            }
            
            .quotation-card {
                padding: 12px;
            }
            
            .quotation-card h3 {
                font-size: 1rem;
            }
            
            .quotation-card p {
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

            .quotation-meta {
                flex-direction: column;
                gap: 5px;
            }

            .status-badge {
                font-size: 0.8rem;
                padding: 4px 8px;
            }

            .form-actions {
                flex-direction: column;
                gap: 10px;
            }

            .form-actions button {
                width: 100%;
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

            .quotation-details {
                page-break-inside: avoid;
            }

            .btn, .action-button {
                display: none;
            }

            .price-calculation {
                page-break-inside: avoid;
            }

            .points-calculation {
                page-break-inside: avoid;
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
    <!-- Sidebar -->
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
            <a href="quotation.php" class="menu-item active">
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
            <a href="payment_admin.php" class="menu-item">
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

    <!-- Main Content -->
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
                <h2>Quotations</h2>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by ID, Name, or Email...">
                </div>
                <button id="checkValidityBtn" class="validity-button">
                    <i class="fas fa-clock"></i> Check Validity
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Quotation ID</th>
                            <th>Submission ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Total (RM)</th>
                            <th>Points</th>
                            <th>Validity</th>
                            <th>Status</th>
                            <th>Generated By</th>
                        </tr>
                    </thead>
                    <tbody id="quotationTableBody">
                        <?php foreach ($quotations as $quotation): ?>
                        <tr data-quotation-id="<?php echo htmlspecialchars($quotation['quotation_id']); ?>">
                            <td><?php echo htmlspecialchars($quotation['quotation_id']); ?></td>
                            <td><?php echo htmlspecialchars($quotation['submission_id']); ?></td>
                            <td><?php echo htmlspecialchars($quotation['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($quotation['email']); ?></td>
                            <td><?php echo number_format($quotation['total'], 2); ?></td>
                            <td><?php echo number_format($quotation['point_to_add']); ?></td>
                            <td><?php echo htmlspecialchars($quotation['formatted_validity']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($quotation['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($quotation['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $quotation['admin_name'] ? htmlspecialchars($quotation['admin_name']) . ' (' . htmlspecialchars($quotation['admin_id']) . ')' : 'Not assigned'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <button class="page-button" onclick="window.location.href='?page=<?php echo $page-1; ?>'">Previous</button>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <button class="page-button <?php echo $i === $page ? 'active' : ''; ?>" 
                            onclick="window.location.href='?page=<?php echo $i; ?>'">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <button class="page-button" onclick="window.location.href='?page=<?php echo $page+1; ?>'">Next</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div class="alert-container" id="alertContainer"></div>

    <!-- View Quotation Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Quotation Details</h3>
                <button class="close-button" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Content will be dynamically populated -->
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const quotationTableBody = document.getElementById('quotationTableBody');
        const rows = quotationTableBody.getElementsByTagName('tr');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            Array.from(rows).forEach(row => {
                const quotationId = row.cells[0].textContent.toLowerCase();
                const userName = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                const generatedBy = row.cells[7].textContent.toLowerCase();
                
                const matches = quotationId.includes(searchTerm) || 
                              userName.includes(searchTerm) || 
                              email.includes(searchTerm) ||
                              generatedBy.includes(searchTerm);
                
                row.style.display = matches ? '' : 'none';
            });
        });

        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            alertContainer.appendChild(alert);
            setTimeout(() => alert.remove(), 3000);
        }

        // Payment dropdown functionality
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

        // Add click event to table rows
        const tableRows = document.querySelectorAll('#quotationTableBody tr');
        tableRows.forEach(row => {
            row.addEventListener('click', function() {
                const quotationId = this.getAttribute('data-quotation-id');
                viewQuotation(quotationId);
            });
        });

        // Check Validity button functionality
        document.getElementById('checkValidityBtn').addEventListener('click', function() {
            this.disabled = true;
            this.style.opacity = '0.7';
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';

            fetch('quotation.php?action=check_validity', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    if (data.affected_rows > 0) {
                        // Reload the page to show updated statuses
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    showAlert(data.message || 'Error checking validity', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error checking validity', 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.style.opacity = '1';
                this.innerHTML = '<i class="fas fa-clock"></i> Check Validity';
            });
        });

        function viewQuotation(quotationId) {
            const modal = document.getElementById('viewModal');
            modal.style.display = 'flex';
            modal.offsetHeight;
            modal.classList.add('show');

            fetch(`quotation.php?action=get_details&quotation_id=${encodeURIComponent(quotationId)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const quotation = data.quotation;
                    const modalBody = document.querySelector('.modal-body');
                    
                    // Format numbers to ensure they're not undefined
                    const formatNumber = (num) => {
                        return num ? parseFloat(num).toFixed(2) : '0.00';
                    };

                    const formatQuantity = (num) => {
                        return num ? parseInt(num) : 0;
                    };
                    
                    modalBody.innerHTML = `
                        <div class="quotation-details">
                            <div class="detail-item">
                                <span class="detail-label">Quotation ID</span>
                                <span class="detail-value">${quotation.quotation_id || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Submission ID</span>
                                <span class="detail-value">${quotation.submission_id || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Name</span>
                                <span class="detail-value">${quotation.user_name || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Email</span>
                                <span class="detail-value">${quotation.email || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Validity</span>
                                <span class="detail-value">${quotation.formatted_validity || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status</span>
                                <span class="detail-value">
                                    <span class="status-badge status-${(quotation.status || '').toLowerCase()}">
                                        ${quotation.status ? quotation.status.charAt(0).toUpperCase() + quotation.status.slice(1) : 'N/A'}
                                    </span>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Points to Add</span>
                                <span class="detail-value">${quotation.point_to_add || '0'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Generated By</span>
                                <span class="detail-value">${quotation.admin_name ? quotation.admin_name + ' (' + quotation.admin_id + ')' : 'Not assigned'}</span>
                            </div>
                        </div>
                        <h4>Item Prices</h4>
                        <div class="quotation-details">
                            ${parseFloat(quotation.laptop_p) > 0 ? `
                                <div class="detail-item">
                                    <span class="detail-label">Laptops (${formatQuantity(quotation.laptop_qty)})</span>
                                    <span class="detail-value">RM ${formatNumber(quotation.laptop_p)}</span>
                                </div>
                            ` : ''}
                            ${parseFloat(quotation.desktop_p) > 0 ? `
                                <div class="detail-item">
                                    <span class="detail-label">Desktops/Servers (${formatQuantity(quotation.desktop_qty)})</span>
                                    <span class="detail-value">RM ${formatNumber(quotation.desktop_p)}</span>
                                </div>
                            ` : ''}
                            ${parseFloat(quotation.monitor_p) > 0 ? `
                                <div class="detail-item">
                                    <span class="detail-label">Monitors/TVs (${formatQuantity(quotation.monitor_qty)})</span>
                                    <span class="detail-value">RM ${formatNumber(quotation.monitor_p)}</span>
                                </div>
                            ` : ''}
                            ${parseFloat(quotation.printer_p) > 0 ? `
                                <div class="detail-item">
                                    <span class="detail-label">Printers/Projectors (${formatQuantity(quotation.printer_qty)})</span>
                                    <span class="detail-value">RM ${formatNumber(quotation.printer_p)}</span>
                                </div>
                            ` : ''}
                            ${parseFloat(quotation.phone_p) > 0 ? `
                                <div class="detail-item">
                                    <span class="detail-label">Smartphones/Tablets (${formatQuantity(quotation.phone_qty)})</span>
                                    <span class="detail-value">RM ${formatNumber(quotation.phone_p)}</span>
                                </div>
                            ` : ''}
                            ${parseFloat(quotation.appliance_p) > 0 ? `
                                <div class="detail-item">
                                    <span class="detail-label">Home Appliances (${formatQuantity(quotation.appliance_qty)})</span>
                                    <span class="detail-value">RM ${formatNumber(quotation.appliance_p)}</span>
                                </div>
                            ` : ''}
                            ${parseFloat(quotation.wearables_p) > 0 ? `
                                <div class="detail-item">
                                    <span class="detail-label">Wearables (${formatQuantity(quotation.wearables_qty)})</span>
                                    <span class="detail-value">RM ${formatNumber(quotation.wearables_p)}</span>
                                </div>
                            ` : ''}
                            ${parseFloat(quotation.cables_p) > 0 ? `
                                <div class="detail-item">
                                    <span class="detail-label">Cables/Wires (${formatQuantity(quotation.cables_qty)})</span>
                                    <span class="detail-value">RM ${formatNumber(quotation.cables_p)}</span>
                                </div>
                            ` : ''}
                            ${parseFloat(quotation.accessories_p) > 0 ? `
                                <div class="detail-item">
                                    <span class="detail-label">Peripherals/Accessories (${formatQuantity(quotation.accessories_qty)})</span>
                                    <span class="detail-value">RM ${formatNumber(quotation.accessories_p)}</span>
                                </div>
                            ` : ''}
                            <div class="detail-item" style="border-top: 1px solid #E2E8F0; margin-top: 10px; padding-top: 10px;">
                                <span class="detail-label">Total</span>
                                <span class="detail-value">RM ${formatNumber(quotation.total)}</span>
                            </div>
                        </div>
                        ${quotation.remarks ? `
                            <h4>Remarks</h4>
                            <p>${quotation.remarks}</p>
                        ` : ''}
                        ${quotation.status && quotation.status.toLowerCase() === 'accepted' ? `
                            <h4 style="margin-top: 24px;">References ID</h4>
                            <div class="quotation-details">
                                <div class="detail-item">
                                    <span class="detail-label">Payment ID</span>
                                    <span class="detail-value"><strong>${quotation.payment_id || 'N/A'}</strong></span>
                                </div>
                                ${quotation.delivery_id ? `
                                    <div class="detail-item">
                                        <span class="detail-label">Delivery ID</span>
                                        <span class="detail-value"><strong>${quotation.delivery_id}</strong></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Delivery Address</span>
                                        <span class="detail-value">
                                            ${quotation.addressline1 ? `
                                                ${quotation.addressline1}<br>
                                                ${quotation.addressline2 || ''}<br>
                                                ${quotation.zipcode} ${quotation.city}<br>
                                                ${quotation.state}
                                            ` : 'N/A'}
                                        </span>
                                    </div>
                                ` : ''}
                                ${quotation.dropoff_id ? `
                                    <div class="detail-item">
                                        <span class="detail-label">Drop-off ID</span>
                                        <span class="detail-value"><strong>${quotation.dropoff_id}</strong></span>
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    `;
                } else {
                    showAlert(data.message || 'Error fetching quotation details', 'error');
                    closeModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error fetching quotation details', 'error');
                closeModal();
            });
        }

        function closeModal() {
            const modal = document.getElementById('viewModal');
            const modalContent = modal.querySelector('.modal-content');
            modalContent.style.transform = 'scale(0.7)';
            modalContent.style.opacity = '0';
            setTimeout(() => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modalContent.style.transform = '';
                modalContent.style.opacity = '';
            }, 300);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        }
    </script>
</body>
</html> 