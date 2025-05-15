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

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get current page number
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10; // Number of records per page
    $offset = ($page - 1) * $limit;

    // Get total number of records
    $stmt = $conn->query("SELECT COUNT(*) FROM user");
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch users with pagination
    $stmt = $conn->prepare("SELECT user_id, user_name, email, contact_number, dob, point FROM user LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User List - Admin Dashboard</title>
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
        .search-bar input::placeholder {
            color: var(--text-secondary);
        }
        .search-bar:hover, .search-bar:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }
        .search-bar:focus-within i {
            color: var(--primary-color);
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
        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
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
        th:nth-child(1), td:nth-child(1) { width: 10%; } /* User ID */
        th:nth-child(2), td:nth-child(2) { width: 15%; } /* Name */
        th:nth-child(3), td:nth-child(3) { width: 20%; } /* Email */
        th:nth-child(4), td:nth-child(4) { width: 15%; } /* Contact Number */
        th:nth-child(5), td:nth-child(5) { width: 15%; } /* Date of Birth */
        th:nth-child(6), td:nth-child(6) { width: 10%; } /* Points */
        th:nth-child(7), td:nth-child(7) { width: 15%; } /* Actions */
        tr:hover {
            background-color: var(--bg-light);
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-active {
            background-color: #C6F6D5;
            color: #2F855A;
        }
        .status-inactive {
            background-color: #FED7D7;
            color: #C53030;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-button {
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: var(--transition-default);
        }
        .edit-button {
            background-color: var(--secondary-color);
            color: white;
        }
        .delete-button {
            background-color: #F56565;
            color: white;
        }
        .action-button:hover {
            opacity: 0.9;
            transform: translateY(-2px);
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
        @media (max-width: 1920px) {
            .main-content {
                padding: 20px;
            }
            .card {
                padding: 20px;
            }
            th, td {
                padding: 10px 12px;
            }
        }
        @media (max-width: 1600px) {
            :root {
                --content-width: 1360px;
            }
            th:nth-child(1), td:nth-child(1) { width: 8%; }
            th:nth-child(2), td:nth-child(2) { width: 12%; }
            th:nth-child(3), td:nth-child(3) { width: 18%; }
            th:nth-child(4), td:nth-child(4) { width: 12%; }
            th:nth-child(5), td:nth-child(5) { width: 12%; }
            th:nth-child(6), td:nth-child(6) { width: 8%; }
            th:nth-child(7), td:nth-child(7) { width: 10%; }
        }
        @media (max-width: 1366px) {
            :root {
                --content-width: 1086px;
            }
            .search-bar {
                width: 300px;
            }
        }

        /* Modal Styles */
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
            width: 400px;
            max-width: 90%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        /* Loading Spinner Styles */
        .loading-spinner {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            justify-content: center;
            align-items: center;
            z-index: 1001;
        }

        .loading-spinner.show {
            display: flex;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #E2E8F0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .modal-button {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition-default);
        }

        .cancel-button {
            background-color: var(--bg-light);
            color: var(--text-primary);
        }

        .confirm-button {
            background-color: var(--primary-color);
            color: white;
        }

        .modal-button:hover {
            transform: translateY(-2px);
        }

        .cancel-button:hover {
            background-color: #E2E8F0;
        }

        .confirm-button:hover {
            background-color: #3d8b6d;
        }

        /* Alert Styles */
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

        /* Tab Styles */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 10px;
        }

        .tab-button {
            padding: 8px 16px;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--text-secondary);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Address Tab Styles */
        .address-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .address-tab-button {
            padding: 8px 16px;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--text-secondary);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .address-tab-button.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .address-content {
            display: none;
        }

        .address-content.active {
            display: block;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Profile Picture Styles */
        .profile-picture-section {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .profile-picture-container {
            text-align: center;
        }

        .profile-picture-container img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 3px solid var(--primary-color);
        }

        .profile-picture-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .upload-btn, .remove-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .upload-btn {
            background-color: var(--primary-color);
            color: white;
        }

        .remove-btn {
            background-color: #F56565;
            color: white;
        }

        .upload-btn:hover, .remove-btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
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

            .table-responsive {
                overflow-x: auto;
            }

            .table th, .table td {
                min-width: 120px;
            }

            .user-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .table th, .table td {
                padding: 8px;
                font-size: 0.9rem;
            }

            .btn {
                padding: 6px 12px;
                font-size: 0.9rem;
            }

            .user-grid {
                grid-template-columns: 1fr;
            }

            .search-bar {
                flex-direction: column;
                gap: 10px;
            }

            .search-bar input,
            .search-bar select {
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

            .table th, .table td {
                padding: 6px;
                font-size: 0.8rem;
            }

            .btn {
                padding: 4px 8px;
                font-size: 0.8rem;
            }

            .user-actions .btn {
                width: 100%;
                margin-bottom: 5px;
            }

            .modal-dialog {
                margin: 10px;
            }

            .modal-content {
                padding: 10px;
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

            .table {
                width: 100%;
                border-collapse: collapse;
            }

            .table th, .table td {
                border: 1px solid #ddd;
            }

            .btn {
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
            <a href="userlist.php" class="menu-item active">
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

        <div class="card">
            <div class="card-header">
                <h2>User Accounts</h2>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by ID, name, email, or phone...">
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                            <th>Date of Birth</th>
                            <th>Points</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php foreach ($users as $user): ?>
                        <tr data-user-id="<?php echo htmlspecialchars($user['user_id']); ?>">
                            <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($user['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['contact_number']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($user['dob'])); ?></td>
                            <td><?php echo number_format($user['point']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-button edit-button" onclick="editUser('<?php echo $user['user_id']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-button delete-button" onclick="deleteUser('<?php echo $user['user_id']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="close-button">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-button cancel-button">Cancel</button>
                <button class="modal-button confirm-button">Delete</button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content" style="width: 600px; max-width: 90%;">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="close-button" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="tabs">
                    <button class="tab-button active" onclick="switchTab('details')">User Details</button>
                    <button class="tab-button" onclick="switchTab('addresses')">Addresses</button>
                </div>
                
                <div id="detailsTab" class="tab-content active">
                    <form id="editUserForm" onsubmit="updateUserDetails(event)">
                        <input type="hidden" id="editUserId" name="user_id">
                        <div class="profile-picture-section">
                            <div class="profile-picture-container">
                                <img id="editUserProfilePic" src="../user/assets/homepage/account.png" alt="Profile Picture">
                                <div class="profile-picture-actions">
                                    <button type="button" class="remove-btn" onclick="removeProfilePicture()">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" id="editUserName" name="user_name" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="editUserEmail" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="tel" id="editUserPhone" name="contact_number" required>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" id="editUserDob" name="dob" required>
                        </div>
                        <div class="form-group">
                            <label>Points</label>
                            <input type="number" id="editUserPoints" name="point" required min="0" value="0">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="modal-button confirm-button">Save Changes</button>
                        </div>
                    </form>
                </div>

                <div id="addressesTab" class="tab-content">
                    <div class="address-tabs">
                        <button class="address-tab-button active" onclick="switchAddressTab(1)">Address 1</button>
                        <button class="address-tab-button" onclick="switchAddressTab(2)">Address 2</button>
                        <button class="address-tab-button" onclick="switchAddressTab(3)">Address 3</button>
                    </div>
                    
                    <form id="editAddressForm" onsubmit="updateAddress(event)">
                        <input type="hidden" id="editAddressUserId" name="user_id">
                        <input type="hidden" id="editAddressNumber" name="address_number" value="1">
                        
                        <div id="address1Content" class="address-content active">
                            <div class="form-group">
                                <label>Address Line 1</label>
                                <input type="text" id="editAddressLine1_1" name="addressline1_1" required>
                            </div>
                            <div class="form-group">
                                <label>Address Line 2</label>
                                <input type="text" id="editAddressLine2_1" name="addressline2_1">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zip Code</label>
                                    <input type="text" id="editZipcode_1" name="zipcode_1" required>
                                </div>
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" id="editCity_1" name="city_1" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <select id="editState_1" name="state_1" required>
                                    <option value="">Select State</option>
                                    <option value="Johor">Johor</option>
                                    <option value="Kedah">Kedah</option>
                                    <option value="Kelantan">Kelantan</option>
                                    <option value="Melaka">Melaka</option>
                                    <option value="Negeri Sembilan">Negeri Sembilan</option>
                                    <option value="Pahang">Pahang</option>
                                    <option value="Perak">Perak</option>
                                    <option value="Perlis">Perlis</option>
                                    <option value="Pulau Pinang">Pulau Pinang</option>
                                    <option value="Sabah">Sabah</option>
                                    <option value="Sarawak">Sarawak</option>
                                    <option value="Selangor">Selangor</option>
                                    <option value="Terengganu">Terengganu</option>
                                    <option value="Wilayah Persekutuan Kuala Lumpur">Wilayah Persekutuan Kuala Lumpur</option>
                                    <option value="Wilayah Persekutuan Labuan">Wilayah Persekutuan Labuan</option>
                                    <option value="Wilayah Persekutuan Putrajaya">Wilayah Persekutuan Putrajaya</option>
                                </select>
                            </div>
                        </div>

                        <div id="address2Content" class="address-content">
                            <div class="form-group">
                                <label>Address Line 1</label>
                                <input type="text" id="editAddressLine1_2" name="addressline1_2">
                            </div>
                            <div class="form-group">
                                <label>Address Line 2</label>
                                <input type="text" id="editAddressLine2_2" name="addressline2_2">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zip Code</label>
                                    <input type="text" id="editZipcode_2" name="zipcode_2">
                                </div>
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" id="editCity_2" name="city_2">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <select id="editState_2" name="state_2">
                                    <option value="">Select State</option>
                                    <option value="Johor">Johor</option>
                                    <option value="Kedah">Kedah</option>
                                    <option value="Kelantan">Kelantan</option>
                                    <option value="Melaka">Melaka</option>
                                    <option value="Negeri Sembilan">Negeri Sembilan</option>
                                    <option value="Pahang">Pahang</option>
                                    <option value="Perak">Perak</option>
                                    <option value="Perlis">Perlis</option>
                                    <option value="Pulau Pinang">Pulau Pinang</option>
                                    <option value="Sabah">Sabah</option>
                                    <option value="Sarawak">Sarawak</option>
                                    <option value="Selangor">Selangor</option>
                                    <option value="Terengganu">Terengganu</option>
                                    <option value="Wilayah Persekutuan Kuala Lumpur">Wilayah Persekutuan Kuala Lumpur</option>
                                    <option value="Wilayah Persekutuan Labuan">Wilayah Persekutuan Labuan</option>
                                    <option value="Wilayah Persekutuan Putrajaya">Wilayah Persekutuan Putrajaya</option>
                                </select>
                            </div>
                        </div>

                        <div id="address3Content" class="address-content">
                            <div class="form-group">
                                <label>Address Line 1</label>
                                <input type="text" id="editAddressLine1_3" name="addressline1_3">
                            </div>
                            <div class="form-group">
                                <label>Address Line 2</label>
                                <input type="text" id="editAddressLine2_3" name="addressline2_3">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zip Code</label>
                                    <input type="text" id="editZipcode_3" name="zipcode_3">
                                </div>
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" id="editCity_3" name="city_3">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <select id="editState_3" name="state_3">
                                    <option value="">Select State</option>
                                    <option value="Johor">Johor</option>
                                    <option value="Kedah">Kedah</option>
                                    <option value="Kelantan">Kelantan</option>
                                    <option value="Melaka">Melaka</option>
                                    <option value="Negeri Sembilan">Negeri Sembilan</option>
                                    <option value="Pahang">Pahang</option>
                                    <option value="Perak">Perak</option>
                                    <option value="Perlis">Perlis</option>
                                    <option value="Pulau Pinang">Pulau Pinang</option>
                                    <option value="Sabah">Sabah</option>
                                    <option value="Sarawak">Sarawak</option>
                                    <option value="Selangor">Selangor</option>
                                    <option value="Terengganu">Terengganu</option>
                                    <option value="Wilayah Persekutuan Kuala Lumpur">Wilayah Persekutuan Kuala Lumpur</option>
                                    <option value="Wilayah Persekutuan Labuan">Wilayah Persekutuan Labuan</option>
                                    <option value="Wilayah Persekutuan Putrajaya">Wilayah Persekutuan Putrajaya</option>
                                </select>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="modal-button confirm-button">Save Address</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Notification -->
    <div class="alert-container" id="alertContainer"></div>

    <script>
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

        // Mobile menu toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Add responsive handlers if needed
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('active');
            }
        });

        function editUser(userId) {
            const modal = document.getElementById('editModal');
            const modalContent = modal.querySelector('.modal-content');
            const loadingSpinner = document.createElement('div');
            loadingSpinner.className = 'loading-spinner';
            loadingSpinner.innerHTML = '<div class="spinner"></div>';
            modalContent.appendChild(loadingSpinner);
            
            // Show modal with loading state
            modal.style.display = 'flex';
            modal.offsetHeight;
            modal.classList.add('show');
            loadingSpinner.classList.add('show');

            // Fetch user details
            fetch(`get_user_details.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        // Set user details
                        document.getElementById('editUserId').value = user.user_id;
                        document.getElementById('editUserName').value = user.user_name;
                        document.getElementById('editUserEmail').value = user.email;
                        document.getElementById('editUserPhone').value = user.contact_number;
                        document.getElementById('editUserDob').value = user.dob;
                        document.getElementById('editUserPoints').value = user.point;

                        // Set profile picture and handle remove button visibility
                        const profilePic = document.getElementById('editUserProfilePic');
                        const removeBtn = document.querySelector('.remove-btn');
                        if (user.profile_pic) {
                            profilePic.src = `data:image/jpeg;base64,${user.profile_pic}`;
                            removeBtn.style.display = 'flex';
                        } else {
                            profilePic.src = '../user/assets/homepage/account.png';
                            removeBtn.style.display = 'none';
                        }

                        // Set address details
                        document.getElementById('editAddressUserId').value = user.user_id;
                        
                        // Address 1
                        document.getElementById('editAddressLine1_1').value = user.addressline1_1;
                        document.getElementById('editAddressLine2_1').value = user.addressline2_1;
                        document.getElementById('editZipcode_1').value = user.zipcode_1;
                        document.getElementById('editCity_1').value = user.city_1;
                        document.getElementById('editState_1').value = user.state_1;

                        // Address 2
                        document.getElementById('editAddressLine1_2').value = user.addressline1_2 || '';
                        document.getElementById('editAddressLine2_2').value = user.addressline2_2 || '';
                        document.getElementById('editZipcode_2').value = user.zipcode_2 || '';
                        document.getElementById('editCity_2').value = user.city_2 || '';
                        document.getElementById('editState_2').value = user.state_2 || '';

                        // Address 3
                        document.getElementById('editAddressLine1_3').value = user.addressline1_3 || '';
                        document.getElementById('editAddressLine2_3').value = user.addressline2_3 || '';
                        document.getElementById('editZipcode_3').value = user.zipcode_3 || '';
                        document.getElementById('editCity_3').value = user.city_3 || '';
                        document.getElementById('editState_3').value = user.state_3 || '';

                        // Hide loading spinner
                        loadingSpinner.classList.remove('show');
                        setTimeout(() => {
                            loadingSpinner.remove();
                        }, 300);
                    } else {
                        showAlert('Error fetching user details', 'error');
                        closeEditModal();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error fetching user details', 'error');
                    closeEditModal();
                });
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('show');
            // Wait for animation to complete before hiding
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            // Show selected tab content
            document.getElementById(tabName + 'Tab').classList.add('active');

            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            document.querySelector(`.tab-button[onclick="switchTab('${tabName}')"]`).classList.add('active');
        }

        function switchAddressTab(addressNumber) {
            // Hide all address contents
            document.querySelectorAll('.address-content').forEach(content => {
                content.classList.remove('active');
            });
            // Show selected address content
            document.getElementById(`address${addressNumber}Content`).classList.add('active');

            // Update address tab buttons
            document.querySelectorAll('.address-tab-button').forEach(button => {
                button.classList.remove('active');
            });
            document.querySelector(`.address-tab-button[onclick="switchAddressTab(${addressNumber})"]`).classList.add('active');

            // Update address number in form
            document.getElementById('editAddressNumber').value = addressNumber;
        }

        function handleProfilePicChange(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('editUserProfilePic').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        function removeProfilePicture() {
            document.getElementById('editUserProfilePic').src = '../user/assets/homepage/account.png';
            // Add a hidden input to indicate profile picture removal
            let removePicInput = document.getElementById('removeProfilePic');
            if (!removePicInput) {
                removePicInput = document.createElement('input');
                removePicInput.type = 'hidden';
                removePicInput.id = 'removeProfilePic';
                removePicInput.name = 'remove_profile_pic';
                removePicInput.value = '1';
                document.getElementById('editUserForm').appendChild(removePicInput);
            }
            // Hide the remove button after clicking
            document.querySelector('.remove-btn').style.display = 'none';
        }

        function updateUserDetails(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            // Check if profile picture should be removed
            const removePicInput = document.getElementById('removeProfilePic');
            if (removePicInput) {
                formData.append('remove_profile_pic', '1');
            }

            // Add loading state
            const submitButton = event.target.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            fetch('update_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('User details updated successfully', 'success');
                    // Refresh the page to show updated data
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message || 'Error updating user details', 'error');
                    // Reset button state
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error updating user details', 'error');
                // Reset button state
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            });
        }

        function updateAddress(event) {
            event.preventDefault();
            const formData = new FormData(event.target);

            fetch('update_address.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Address updated successfully', 'success');
                    // Refresh the page to show updated data
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message || 'Error updating address', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error updating address', 'error');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        let currentUserId = null;
        const modal = document.getElementById('deleteModal');
        const closeButton = modal.querySelector('.close-button');
        const cancelButton = modal.querySelector('.cancel-button');
        const confirmButton = modal.querySelector('.confirm-button');

        function deleteUser(userId) {
            currentUserId = userId;
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'flex';
            // Trigger reflow
            modal.offsetHeight;
            modal.classList.add('show');
        }

        function closeModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('show');
            // Wait for animation to complete before hiding
            setTimeout(() => {
                modal.style.display = 'none';
                currentUserId = null;
            }, 300);
        }

        closeButton.addEventListener('click', closeModal);
        cancelButton.addEventListener('click', closeModal);

        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            alert.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            `;
            
            alertContainer.appendChild(alert);
            
            // Remove alert after animation
            setTimeout(() => {
                alert.remove();
            }, 3000);
        }

        confirmButton.addEventListener('click', function() {
            if (currentUserId) {
                // Create form data
                const formData = new FormData();
                formData.append('user_id', currentUserId);
                formData.append('action', 'delete');

                // Send AJAX request
                fetch('delete_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('User deleted successfully');
                        // Remove the row from the table
                        const row = document.querySelector(`tr[data-user-id="${currentUserId}"]`);
                        if (row) {
                            row.remove();
                        }
                    } else {
                        showAlert(data.message || 'Error deleting user', 'error');
                    }
                })
                .catch(error => {
                    showAlert('Error deleting user', 'error');
                    console.error('Error:', error);
                })
                .finally(() => {
                    closeModal();
                });
            }
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const userTableBody = document.getElementById('userTableBody');
        const rows = userTableBody.getElementsByTagName('tr');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            Array.from(rows).forEach(row => {
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                // Check each cell except the last one (actions)
                for (let i = 0; i < cells.length - 1; i++) {
                    const cellText = cells[i].textContent.toLowerCase();
                    if (cellText.includes(searchTerm)) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            });
        });
    </script>
</body>
</html> 