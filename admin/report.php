<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: ../user/auth.php');
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Date filter
    $filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : '';
    $filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
    $where = '';
    $params = [];
    
    if ($filter_month || $filter_year) {
        $conditions = [];
        if ($filter_month) {
            $conditions[] = 'MONTH(p.date_paid) = :filter_month';
            $params[':filter_month'] = $filter_month;
        }
        if ($filter_year) {
            $conditions[] = 'YEAR(p.date_paid) = :filter_year';
            $params[':filter_year'] = $filter_year;
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);
    }

    // Count total records
    $stmt = $conn->prepare("SELECT COUNT(*) FROM history h LEFT JOIN payment p ON h.payment_id = p.payment_id $where");
    if ($filter_month) {
        $stmt->bindValue(':filter_month', $filter_month, PDO::PARAM_INT);
    }
    if ($filter_year) {
        $stmt->bindValue(':filter_year', $filter_year, PDO::PARAM_INT);
    }
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch all columns from payment, user, and quotation via history
    $sql = "SELECT h.*, 
               p.bank_acc, p.bank_name, p.name AS acc_holder, q.total AS amount, q.point_to_add, p.date_paid, 
               q.quotation_id, q.status AS quotation_status, q.method AS quotation_method, 
               u.user_name, u.email, u.contact_number
        FROM history h
        LEFT JOIN payment p ON h.payment_id = p.payment_id
        LEFT JOIN quotation q ON p.quotation_id = q.quotation_id
        LEFT JOIN user u ON h.user_id = u.user_id
        $where
        ORDER BY COALESCE(p.date_paid, '1970-01-01') DESC, h.history_id DESC
        LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    if ($filter_month) {
        $stmt->bindValue(':filter_month', $filter_month, PDO::PARAM_INT);
    }
    if ($filter_year) {
        $stmt->bindValue(':filter_year', $filter_year, PDO::PARAM_INT);
    }
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Waste Submissions - Admin Dashboard</title>
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
        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--bg-light);
            justify-content: flex-start;
        }
        .card-header-title {
            flex-shrink: 0;
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 600;
        }
        .card-header-center {
            display: flex;
            align-items: center;
        }
        .card-header-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            position: relative;
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
        .filter-btn-pro {
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(80, 184, 142, 0.08);
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
            outline: none;
        }
        .filter-btn-pro i {
            font-size: 1.1rem;
        }
        .filter-btn-pro:hover, .filter-btn-pro:focus {
            background-color: #3d9c7a;
            box-shadow: 0 4px 16px rgba(80, 184, 142, 0.15);
            transform: translateY(-2px) scale(1.03);
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
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
        }
        th {
            background-color: var(--bg-light);
            font-weight: 600;
            color: var(--text-primary);
        }
        tr:hover {
            background-color: var(--bg-light);
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-accepted { background: #C6F6D5; color: #2F855A; }
        .status-completed { background: #C6F6D5; color: #228B22; }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-rejected { background: #FED7D7; color: #C53030; }
        .status-accepted { background: #C6F6D5; color: #2F855A; }
        .modal-details-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #E2E8F0;
        }
        .modal-details-label {
            color: #718096;
            font-size: 0.95em;
        }
        .modal-details-value {
            color: #2D3748;
            font-weight: 600;
        }
        #reportModal .modal-content {
            background: #232a26 !important;
            color: #fff;
        }
        /* Modal table background and text color */
        #reportModal table {
            background: #232a26;
            color: #fff;
        }
        #reportModal th, #reportModal td {
            background: #232a26;
            color: #fff;
            border: 1px solid #b5b5b5;
        }
        #reportModal tr:hover, #reportModal td:hover, #reportModal th:hover {
            background: #232a26 !important;
            color: #fff !important;
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
        .view-button {
            background-color: var(--secondary-color);
            color: white;
        }
        .approve-button {
            background-color: #48BB78;
            color: white;
        }
        .reject-button {
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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal.show {
            display: block;
            opacity: 1;
        }
        .modal-content {
            background: #232a26;
            border-radius: 18px;
            padding: 32px;
            width: 800px;
            max-width: 95%;
            margin: 40px auto;
            position: relative;
            color: #fff;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }
        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: #b0b0b0;
            font-size: 28px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border-radius: 50%;
        }
        .close-modal:hover {
            color: #50B88E;
            background: rgba(80, 184, 142, 0.1);
        }
        .modal-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .modal-logo {
            height: 60px;
            margin-bottom: 15px;
        }
        .modal-header h2 {
            color: #50B88E;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        .modal-body {
            color: #fff;
        }
        .report-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 30px;
        }
        .report-section {
            flex: 1;
        }
        .report-section h3 {
            color: #50B88E;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .report-details {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #b0b0b0;
        }
        .detail-value {
            color: #fff;
            font-weight: 500;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            overflow: hidden;
        }
        .report-table th,
        .report-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .report-table th {
            background: rgba(80, 184, 142, 0.1);
            color: #50B88E;
            font-weight: 600;
        }
        .report-table tr:last-child td {
            border-bottom: none;
        }
        .total-section {
            display: flex;
            justify-content: flex-end;
            gap: 30px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .total-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .total-label {
            color: #b0b0b0;
            font-weight: 600;
        }
        .total-value {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 700;
        }
        .points-value {
            color: #50B88E;
            border: 2px solid #50B88E;
        }
        .amount-value {
            color: #ffb07c;
            border: 2px solid #ffb07c;
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
        }
        @media (max-width: 1366px) {
            :root {
                --content-width: 1086px;
            }
            .search-bar {
                width: 300px;
            }
        }
        /* Quotation Modal Styles */
        .quotation-modal {
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
        .quotation-modal.show {
            opacity: 1;
        }
        .quotation-modal-content {
            background-color: white;
            border-radius: 12px;
            padding: 24px;
            width: 800px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .quotation-modal.show .quotation-modal-content {
            transform: scale(1);
            opacity: 1;
        }
        .price-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .price-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(80, 184, 142, 0.2);
            outline: none;
        }
        .price-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            align-items: center;
            margin-bottom: 12px;
        }
        .price-label {
            font-weight: 500;
            color: var(--text-primary);
        }
        .total-row {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .points-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .points-header {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .points-header i {
            color: var(--primary-color);
        }
        .points-calculation {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        .points-total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            justify-content: space-between;
        }
        .remarks-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            min-height: 100px;
            resize: vertical;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .remarks-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(80, 184, 142, 0.2);
            outline: none;
        }
        .generate-button {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .generate-button:hover {
            background-color: #3d9c7a;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .filter-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            left: auto;
            margin-top: 8px;
            background: #fff;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(44, 62, 80, 0.10);
            padding: 18px 20px 12px 20px;
            min-width: 240px;
            z-index: 2000;
        }
        .filter-dropdown.show {
            display: block;
        }
        .filter-dropdown input[type="date"] {
            width: 100%;
            padding: 10px 12px;
            border-radius: 7px;
            border: 1px solid #E2E8F0;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .filter-dropdown .dropdown-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .filter-dropdown .page-button {
            border-radius: 7px;
            padding: 8px 18px;
            font-size: 1rem;
        }
        .report-table th, .report-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 1rem;
        }
        .report-table th {
            background: #f4f7fa;
            color: #2D3748;
            font-weight: 700;
        }
        .report-table tr:hover {
            background: #f8fafc;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.95em;
            font-weight: 600;
            background: #e2e8f0;
            color: #333;
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

            .table-responsive {
                overflow-x: auto;
            }

            .table th, .table td {
                min-width: 120px;
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
        }
    </style>
</head>
<body>
    <!-- Sidebar from admin.php -->
    <div class="sidebar">
        <div class="logo">
            <img src="../user/assets/homepage/logo.png" alt="Nothing Wasted Logo">
        </div>
        <div class="menu">
            <a href="admin.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="report.php" class="menu-item active">
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

        <div class="card">
            <div class="card-header">
                <div class="card-header-title">Report</div>
                <div class="card-header-center">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by User, Email, Quotation ID, Payment ID...">
                    </div>
                </div>
                <div class="card-header-actions">
                    <button id="openFilterDropdown" class="filter-btn-pro">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <div id="filterDropdown" class="filter-dropdown">
                        <form id="filterDropdownForm" method="get" style="display: flex; flex-direction: column; gap: 8px;">
                            <select name="filter_month" id="filterDropdownMonthInput" style="width: 100%; padding: 10px 12px; border-radius: 7px; border: 1px solid #E2E8F0; font-size: 1rem; margin-bottom: 10px;">
                                <option value="">Select Month</option>
                                <option value="1" <?php echo $filter_month == '1' ? 'selected' : ''; ?>>January</option>
                                <option value="2" <?php echo $filter_month == '2' ? 'selected' : ''; ?>>February</option>
                                <option value="3" <?php echo $filter_month == '3' ? 'selected' : ''; ?>>March</option>
                                <option value="4" <?php echo $filter_month == '4' ? 'selected' : ''; ?>>April</option>
                                <option value="5" <?php echo $filter_month == '5' ? 'selected' : ''; ?>>May</option>
                                <option value="6" <?php echo $filter_month == '6' ? 'selected' : ''; ?>>June</option>
                                <option value="7" <?php echo $filter_month == '7' ? 'selected' : ''; ?>>July</option>
                                <option value="8" <?php echo $filter_month == '8' ? 'selected' : ''; ?>>August</option>
                                <option value="9" <?php echo $filter_month == '9' ? 'selected' : ''; ?>>September</option>
                                <option value="10" <?php echo $filter_month == '10' ? 'selected' : ''; ?>>October</option>
                                <option value="11" <?php echo $filter_month == '11' ? 'selected' : ''; ?>>November</option>
                                <option value="12" <?php echo $filter_month == '12' ? 'selected' : ''; ?>>December</option>
                            </select>
                            <select name="filter_year" id="filterDropdownYearInput" style="width: 100%; padding: 10px 12px; border-radius: 7px; border: 1px solid #E2E8F0; font-size: 1rem; margin-bottom: 10px;">
                                <option value="">Select Year</option>
                                <?php 
                                $currentYear = date('Y');
                                for($year = $currentYear; $year >= $currentYear - 5; $year--) {
                                    echo "<option value='$year' " . ($filter_year == $year ? 'selected' : '') . ">$year</option>";
                                }
                                ?>
                            </select>
                            <div class="dropdown-actions">
                                <button type="button" id="clearDropdownFilterBtn" class="page-button" style="background: #eee; color: #333;">Clear</button>
                                <button type="submit" class="page-button" style="background: var(--primary-color); color: #fff;">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Report ID</th>
                            <th>Date</th>
                            <th>Payment ID</th>
                            <th>User ID</th>
                            <th>Quotation ID</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        <?php foreach ($records as $i => $row): ?>
                            <?php if (!empty($row) && json_encode($row) !== false): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['history_id']); ?></td>
                                <td><?php echo $row['date_paid'] ? date('Y-m-d', strtotime($row['date_paid'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($row['payment_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['quotation_id']); ?></td>
                                <td>
                                    <button class="view-button" data-report='<?php echo htmlspecialchars(json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>' style="background:#50B88E;color:#fff;padding:6px 18px;border-radius:16px;font-weight:600;border:none;cursor:pointer;box-shadow:none;transition:background 0.2s;">
                                        View
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>
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
    <!-- Report Details Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeReportModal()">&times;</button>
            <div class="modal-header">
                <img src="../user/assets/homepage/logo.png" alt="Logo" class="modal-logo">
                <h2>Report Details</h2>
            </div>
            <div id="modalDetails" class="modal-body"></div>
        </div>
    </div>
    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const reportTableBody = document.getElementById('reportTableBody');
        const rows = reportTableBody.getElementsByTagName('tr');

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

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.view-button').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const row = JSON.parse(this.getAttribute('data-report'));
                    const modal = document.getElementById('reportModal');
                    const details = document.getElementById('modalDetails');
                    
                    let html = `
                        <div class="report-info">
                            <div class="report-section">
                                <h3>From</h3>
                                <div class="report-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Company</span>
                                        <span class="detail-value">Nothing Wasted</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Address</span>
                                        <span class="detail-value">Jalan Nothing 1/1,<br>Taman Bukit Jalil,</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Location</span>
                                        <span class="detail-value">48000 Bukit Jalil, Kuala Lumpur</span>
                                    </div>
                                </div>
                            </div>
                            <div class="report-section">
                                <h3>To</h3>
                                <div class="report-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Name</span>
                                        <span class="detail-value">${row.user_name || '-'}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Email</span>
                                        <span class="detail-value">${row.email || '-'}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Contact</span>
                                        <span class="detail-value">${row.contact_number || '-'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="report-section">
                            <h3>Transaction Details</h3>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Points Added</th>
                                        <th>Amount (RM)</th>
                                        <th>Date Paid</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>${row.point_to_add || '-'}</td>
                                        <td>${row.amount ? 'RM ' + row.amount : '-'}</td>
                                        <td>${row.date_paid ? new Date(row.date_paid).toLocaleDateString() : '-'}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="total-section">
                            <div class="total-item">
                                <img src="../user/assets/account/point.png" alt="Points" style="height:24px;">
                                <span class="total-label">TOTAL POINTS</span>
                                <span class="total-value points-value">${row.point_to_add || '0'}</span>
                            </div>
                            <div class="total-item">
                                <span class="total-label">TOTAL AMOUNT</span>
                                <span class="total-value amount-value">${row.amount ? 'RM ' + row.amount : 'RM 0'}</span>
                            </div>
                        </div>

                        <div class="report-section">
                            <h3>Payment Details</h3>
                            <div class="report-details">
                                <div class="detail-row">
                                    <span class="detail-label">Payment ID</span>
                                    <span class="detail-value">${row.payment_id || '-'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Bank Account</span>
                                    <span class="detail-value">${row.bank_acc || '-'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Bank Name</span>
                                    <span class="detail-value">${row.bank_name || '-'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Account Holder</span>
                                    <span class="detail-value">${row.acc_holder || '-'}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    details.innerHTML = html;
                    
                    // First make the modal visible
                    modal.style.display = 'block';
                    
                    // Force a reflow
                    modal.offsetHeight;
                    
                    // Then add the show class to trigger the animation
                    modal.classList.add('show');
                });
            });
        });

        function closeReportModal() {
            const modal = document.getElementById('reportModal');
            const modalContent = modal.querySelector('.modal-content');
            
            // Add closing animation
            modalContent.style.transform = 'scale(0.7)';
            modalContent.style.opacity = '0';
            modal.style.opacity = '0';
            
            // Wait for animation to complete before hiding
            setTimeout(() => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                // Reset transform and opacity for next open
                modalContent.style.transform = '';
                modalContent.style.opacity = '';
                modal.style.opacity = '';
            }, 300);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('reportModal');
            if (event.target === modal) {
                closeReportModal();
            }
        }

        // Dropdown filter functionality
        const openFilterDropdownBtn = document.getElementById('openFilterDropdown');
        const filterDropdown = document.getElementById('filterDropdown');
        const clearDropdownFilterBtn = document.getElementById('clearDropdownFilterBtn');
        const filterDropdownMonthInput = document.getElementById('filterDropdownMonthInput');
        const filterDropdownYearInput = document.getElementById('filterDropdownYearInput');
        const filterDropdownForm = document.getElementById('filterDropdownForm');

        openFilterDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            filterDropdown.classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            if (!filterDropdown.contains(e.target) && e.target !== openFilterDropdownBtn) {
                filterDropdown.classList.remove('show');
            }
        });
        clearDropdownFilterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            filterDropdownMonthInput.value = '';
            filterDropdownYearInput.value = '';
            window.location.href = 'report.php';
        });
        filterDropdownForm.addEventListener('submit', function(e) {
            if (!filterDropdownMonthInput.value && !filterDropdownYearInput.value) {
                e.preventDefault();
                filterDropdown.classList.remove('show');
                window.location.href = 'report.php';
            } else {
                filterDropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>
