<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: ../user/auth.php');
    exit();
}
// Handle AJAX request for dashboard data
if (isset($_GET['action']) && $_GET['action'] === 'data') {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nothing_wasted";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Function to get dashboard data
        function getDashboardData($conn) {
            $data = array();

            // 1. Get E-Waste Submissions count
            $stmt = $conn->query("SELECT COUNT(*) as total_submissions FROM submission");
            $data['submissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_submissions'];

            // 2. Get E-Waste Quotations count
            $stmt = $conn->query("SELECT COUNT(*) as total_quotations FROM quotation");
            $data['quotations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_quotations'];

            // 3. Get Inventory Summary
            $stmt = $conn->query("SELECT 
                SUM(laptop_inv + desktop_inv + monitor_inv + printer_inv + phone_inv + 
                    appliance_inv + wearables_inv + cables_inv + accessories_inv) as total_ewaste,
                (SELECT SUM(total) FROM quotation WHERE status = 'Completed') as total_value 
                FROM inventory");
            $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
            $data['total_ewaste'] = $inventory['total_ewaste'] ?? 0;
            $data['total_value'] = $inventory['total_value'] ?? 0;

            // 4. Get Pickup Summary (from delivery table)
            $stmt = $conn->query("SELECT 
                SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as completed_pickups,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as pending_pickups 
                FROM delivery");
            $pickups = $stmt->fetch(PDO::FETCH_ASSOC);
            $data['completed_pickups'] = $pickups['completed_pickups'] ?? 0;
            $data['pending_pickups'] = $pickups['pending_pickups'] ?? 0;

            // 5. Get Dropoff Summary
            $stmt = $conn->query("SELECT 
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as completed_dropoffs,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending_dropoffs 
                FROM dropoff");
            $dropoffs = $stmt->fetch(PDO::FETCH_ASSOC);
            $data['completed_dropoffs'] = $dropoffs['completed_dropoffs'] ?? 0;
            $data['pending_dropoffs'] = $dropoffs['pending_dropoffs'] ?? 0;

            // 6. Get Total Cost (from quotation table where status is completed)
            $stmt = $conn->query("SELECT SUM(total) as total_cost FROM quotation WHERE status = 'Completed'");
            $data['total_cost'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_cost'] ?? 0;

            // 7. Get Chart Data (group by month for both tables)
            $type = isset($_GET['type']) ? $_GET['type'] : 'all';

            switch ($type) {
                case 'submission':
                    $stmt = $conn->query("
                        SELECT DATE_FORMAT(date, '%b %Y') as month, COUNT(*) as total
                        FROM submission
                        GROUP BY YEAR(date), MONTH(date)
                        ORDER BY YEAR(date) DESC, MONTH(date) DESC
                        LIMIT 10
                    ");
                    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'quotation':
                    $stmt = $conn->query("
                        SELECT DATE_FORMAT(validity, '%b %Y') as month, COUNT(*) as total
                        FROM quotation
                        GROUP BY YEAR(validity), MONTH(validity)
                        ORDER BY YEAR(validity) DESC, MONTH(validity) DESC
                        LIMIT 10
                    ");
                    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                default:
                    // Combined query for 'all'
                    $stmt = $conn->query("
                        SELECT m.month,
                            COALESCE(s.submissions, 0) as submissions,
                            COALESCE(q.quotations, 0) as quotations
                        FROM (
                            SELECT DATE_FORMAT(date, '%b') as month, MONTH(date) as month_num, YEAR(date) as year
                            FROM submission
                            UNION
                            SELECT DATE_FORMAT(validity, '%b') as month, MONTH(validity) as month_num, YEAR(validity) as year
                            FROM quotation
                        ) m
                        LEFT JOIN (
                            SELECT DATE_FORMAT(date, '%b') as month, MONTH(date) as month_num, YEAR(date) as year, COUNT(*) as submissions
                            FROM submission
                            GROUP BY year, month_num
                        ) s ON m.month_num = s.month_num AND m.year = s.year
                        LEFT JOIN (
                            SELECT DATE_FORMAT(validity, '%b') as month, MONTH(validity) as month_num, YEAR(validity) as year, COUNT(*) as quotations
                            FROM quotation
                            GROUP BY year, month_num
                        ) q ON m.month_num = q.month_num AND m.year = q.year
                        GROUP BY m.year, m.month_num
                        ORDER BY m.year DESC, m.month_num DESC
                        LIMIT 10
                    ");
                    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
            }

            // Debug: log to file
            file_put_contents('debug.log', print_r($chart_data, true));

            $data['chart_data'] = $chart_data;

            return $data;
        }

        // Get all dashboard data
        $dashboardData = getDashboardData($conn);

        // Return as JSON
        header('Content-Type: application/json');
        echo json_encode($dashboardData);

    } catch(PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(array("error" => "Connection failed: " . $e->getMessage()));
    }

    $conn = null;
    exit;
}
?>
<!-- HTML starts here -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }
        body {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-light);
            color: var(--text-primary);
        }
        /* Keeping all the sidebar styles from submission.html */
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
            z-index: 1001;
        }
        .sidebar.hide {
            transform: translateX(-100%);
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
        /* Dashboard specific styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
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
                box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.8);
            }
            50% {
                box-shadow: 0 0 20px 10px rgba(255, 255, 255, 0.6);
            }
            100% {
                box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.8);
            }
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 12px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
            transition: var(--transition-default);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
        .payment-overview {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        .payment-item {
            background: var(--bg-light);
            padding: 20px;
            border-radius: 10px;
            text-align: left;
            transition: var(--transition-default);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .payment-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow);
        }
        .payment-item h3 {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 12px;
            font-weight: 500;
        }
        .payment-item p {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .chart-container {
            position: relative;
            width: 100%;
            height: 350px;
            min-height: 250px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            padding: 16px 8px 8px 8px;
            box-sizing: border-box;
        }
        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
            background: transparent;
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .chart-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .chart-header select {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #E2E8F0;
            background-color: white;
            color: var(--text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-header select i {
            font-size: 0.8rem;
        }
        .chart-header select:hover {
            border-color: var(--primary-color);
        }
        .summary-card {
            padding: 24px;
            transition: var(--transition-default);
        }
        .summary-info h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .summary-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }
        .icon-container {
            width: 56px;
            height: 56px;
            background-color: var(--primary-color);
            color: white;
            font-size: 1.5rem;
            border-radius: 12px;
            transition: var(--transition-default);
        }
        .summary-card:hover .icon-container {
            transform: rotate(15deg);
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            padding: 16px 0;
        }
        .stat-item {
            text-align: center;
            padding: 16px;
            background: var(--bg-light);
            border-radius: 10px;
            transition: var(--transition-default);
        }
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow);
        }
        .stat-item h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .stat-item p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }
        .card h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--bg-light);
        }
        @media (max-width: 1200px) {
            .payment-overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-item p {
                font-size: 1.25rem;
            }
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
        .card {
            animation: fadeIn 0.5s ease-out;
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
        .summary-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-row .card {
            margin-bottom: 0;
        }
        .chart-filter-group {
            display: flex;
            gap: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            border: 1px solid #E2E8F0;
            background: #F7FAFC;
        }
        .chart-filter-btn {
            background: none;
            border: none;
            outline: none;
            padding: 10px 24px;
            font-size: 1rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            font-weight: 500;
            border-right: 1px solid #E2E8F0;
        }
        .chart-filter-btn:last-child {
            border-right: none;
        }
        .chart-filter-btn:hover,
        .chart-filter-btn:focus {
            background: #E6F4F1;
            color: var(--primary-color);
        }
        .chart-filter-btn.active,
        .chart-filter-btn[aria-pressed="true"] {
            background: var(--primary-color);
            color: #fff;
            font-weight: 600;
        }
        .chart-filter-select {
            padding: 10px 24px;
            font-size: 1rem;
            color: var(--text-secondary);
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            background: #F7FAFC;
            font-weight: 500;
            cursor: pointer;
            transition: border-color 0.2s;
            outline: none;
        }
        .chart-filter-select:focus {
            border-color: var(--primary-color);
        }

        /* Responsive Styles */
        @media (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-row {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 300px;
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
            
            .chart-filter-group {
                width: 100%;
            }
            
            .chart-filter-btn {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
            
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-item h3 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            
            .card {
                padding: 12px;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .chart-filter-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .stat-item {
                padding: 12px;
            }
            
            .stat-item h3 {
                font-size: 1.25rem;
            }
            
            .stat-item p {
                font-size: 0.8rem;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .top-bar,
            .chart-filter-group {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .card {
                break-inside: avoid;
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar from submission.html -->
    <div class="sidebar">
        <div class="logo">
            <img src="../user/assets/homepage/logo.png" alt="Nothing Wasted Logo">
        </div>
        <div class="menu">
            <a href="admin.php" class="menu-item active">
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

        <div class="dashboard-grid">
            <div class="left-column">
                <!-- Submissions & Quotations Summary Row -->
                <div class="summary-row">
                    <!-- E-Waste Submissions Summary -->
                    <div class="card e-waste-submissions">
                        <h2>E-Waste Submissions Summary</h2>
                        <div class="summary-stats">
                            <div class="stat-item">
                                <i class="far fa-user"></i>
                                <h3>0</h3>
                                <p>Number of Total Request</p>
                            </div>
                        </div>
                    </div>

                    <!-- E-Waste Quotations Summary -->
                    <div class="card e-waste-quotations">
                        <h2>E-Waste Quotations Summary</h2>
                        <div class="summary-stats">
                            <div class="stat-item">
                                <i class="far fa-user"></i>
                                <h3>0</h3>
                                <p>Number of Total Quotations Generated</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submissions & Quotations Chart -->
                <div class="card">
                    <div class="chart-header">
                        <h2>Submissions & Quotations</h2>
                        <div class="select-wrapper chart-filter-group">
                            <select id="chartTypeSelect" class="chart-filter-select" aria-label="Select chart type">
                                <option value="submission">Submission</option>
                                <option value="quotation">Quotation</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="submissionChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="right-column">
                <!-- Inventory Summary -->
                <div class="card">
                    <h2>Inventory Summary</h2>
                    <div class="summary-stats" style="grid-template-columns: 1fr; gap: 20px;">
                        <div class="stat-item total-ewaste">
                            <i class="far fa-calendar"></i>
                            <h3>0</h3>
                            <p>Total E-Waste</p>
                        </div>
                    </div>
                </div>

                <!-- Pickup Summary -->
                <div class="card">
                    <h2>Pickup Summary</h2>
                    <div class="summary-stats">
                        <div class="stat-item pickup-completed">
                            <i class="far fa-calendar"></i>
                            <h3>0</h3>
                            <p>Picked Up</p>
                        </div>
                        <div class="stat-item pickup-pending">
                            <i class="far fa-clock"></i>
                            <h3>0</h3>
                            <p>Pending</p>
                        </div>
                    </div>
                </div>

                <!-- Dropoff Summary -->
                <div class="card">
                    <h2>Dropoff Summary</h2>
                    <div class="summary-stats">
                        <div class="stat-item dropoff-completed">
                            <i class="far fa-calendar"></i>
                            <h3>0</h3>
                            <p>Completed</p>
                        </div>
                        <div class="stat-item dropoff-pending">
                            <i class="far fa-clock"></i>
                            <h3>0</h3>
                            <p>Pending</p>
                        </div>
                    </div>
                </div>

                <!-- Payment Overview -->
                <div class="card">
                    <h2>Payment Overview</h2>
                    <div class="summary-stats">
                        <div class="stat-item total-cost">
                            <i class="fas fa-money-bill"></i>
                            <h3>RM 0.00</h3>
                            <p>Total Cost</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let cachedSubmissionData = null;
        let cachedQuotationData = null;
        let submissionChartInstance = null;

        // --- Dashboard summary (cards) ---
        async function fetchDashboardData() {
            try {
                const response = await fetch('admin.php?action=data');
                const data = await response.json();
                // Update summary cards
                document.querySelector('.e-waste-submissions h3').textContent = data.submissions;
                document.querySelector('.e-waste-quotations h3').textContent = data.quotations;
                document.querySelector('.total-ewaste h3').textContent = data.total_ewaste;
                document.querySelector('.pickup-completed h3').textContent = data.completed_pickups;
                document.querySelector('.pickup-pending h3').textContent = data.pending_pickups;
                document.querySelector('.dropoff-completed h3').textContent = data.completed_dropoffs;
                document.querySelector('.dropoff-pending h3').textContent = data.pending_dropoffs;
                document.querySelector('.total-cost h3').textContent = 'RM ' + (data.total_cost || 0).toFixed(2);
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            }
        }

        // --- Chart logic ---
        async function fetchChartData(type) {
            const res = await fetch(`admin.php?action=data&type=${type}`);
            const data = await res.json();
            return data.chart_data || [];
        }

        const chartTypeSelect = document.getElementById('chartTypeSelect');
        chartTypeSelect.disabled = true;
        chartTypeSelect.addEventListener('change', function() {
            showChart(this.value);
        });

        document.addEventListener('DOMContentLoaded', async function() {
            fetchDashboardData();
            // Fetch both datasets
            cachedSubmissionData = await fetchChartData('submission');
            cachedQuotationData = await fetchChartData('quotation');
            chartTypeSelect.disabled = false;
            showChart(chartTypeSelect.value);
        });

        function showChart(type) {
            const submissionChart = document.getElementById('submissionChart');
            const submissionCtx = submissionChart.getContext('2d');
            submissionChart.style.display = 'none';
            if (submissionChartInstance) { submissionChartInstance.destroy(); submissionChartInstance = null; }
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { display: true, color: '#E5E5E5' },
                        ticks: { callback: value => value.toLocaleString() }
                    },
                    x: { grid: { display: false } }
                }
            };

            let labels = [];
            let totals = [];
            let label = '';
            let color = '';

            if (type === 'submission') {
                labels = cachedSubmissionData.map(item => item.month);
                totals = cachedSubmissionData.map(item => item.total || 0);
                label = 'Submission';
                color = '#4A90E2';
            } else if (type === 'quotation') {
                labels = cachedQuotationData.map(item => item.month);
                totals = cachedQuotationData.map(item => item.total || 0);
                label = 'Quotation';
                color = '#50B88E';
            }

            submissionChart.style.display = '';
            submissionChartInstance = new Chart(submissionCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: totals,
                        backgroundColor: color,
                        borderRadius: 5,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7
                    }]
                },
                options: chartOptions
            });
        }

        // --- Responsive sidebar and other UI logic remains unchanged ---

        // Find the menu-item with the shopping cart icon
        const menuItems = document.querySelectorAll('.menu-item');
        let paymentMenuItem = null;
        menuItems.forEach(item => {
            if (item.querySelector('.fa-shopping-cart')) {
                paymentMenuItem = item;
            }
        });
        const paymentSubmenu = paymentMenuItem.nextElementSibling;
        const chevronIcon = paymentMenuItem.querySelector('.fa-chevron-down');

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
    </script>
</body>
</html> 