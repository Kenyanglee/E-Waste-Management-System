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

    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Date filter
    $filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : '';
    $filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
    $where = '';
    $params = [];
    
    // Debug initial state
    error_log("=== Filter Debug Start ===");
    error_log("Initial GET parameters: " . print_r($_GET, true));
    
    // Item type filters
    $filter_items = [];
    $item_types = ['laptop', 'desktop', 'monitor', 'printer', 'phone', 'appliance', 'wearables', 'cables', 'accessories'];
    
    // Check which items are selected
    foreach ($item_types as $type) {
        $filter_key = 'filter_' . $type;
        if (isset($_GET[$filter_key])) {
            $filter_items[] = $type . '_inv > 0';
            error_log("Added filter condition: " . $type . '_inv > 0');
        }
    }
    
    // Build the WHERE clause
    $conditions = [];
    
    // Add item conditions first
    if (!empty($filter_items)) {
        $item_condition = '(' . implode(' OR ', $filter_items) . ')';
        $conditions[] = $item_condition;
        error_log("Added item conditions: " . $item_condition);
    }
    
    // Add date conditions if they exist
    if (!empty($filter_month)) {
        $conditions[] = 'MONTH(date) = :filter_month';
        $params[':filter_month'] = $filter_month;
        error_log("Added month filter: " . $filter_month);
    }
    if (!empty($filter_year)) {
        $conditions[] = 'YEAR(date) = :filter_year';
        $params[':filter_year'] = $filter_year;
        error_log("Added year filter: " . $filter_year);
    }
    
    // Build the final WHERE clause
    if (!empty($conditions)) {
        $where = 'WHERE ' . implode(' AND ', $conditions);
        error_log("Final WHERE clause: " . $where);
        error_log("Parameters: " . print_r($params, true));
    }

    // Debug output
    error_log("SQL WHERE clause: " . $where);
    error_log("Filter items: " . print_r($filter_items, true));
    error_log("GET parameters: " . print_r($_GET, true));

    try {
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM inventory $where";
        error_log("Count SQL: " . $count_sql);
        $stmt = $conn->prepare($count_sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
            error_log("Binding parameter: $key = $value");
        }
        $stmt->execute();
        $total_records = $stmt->fetchColumn();
        error_log("Total records found: " . $total_records);
        $total_pages = ceil($total_records / $limit);

        // Get the actual data
        $sql = "SELECT * FROM inventory $where ORDER BY date DESC, ewaste_id DESC LIMIT :limit OFFSET :offset";
        error_log("Data SQL: " . $sql);
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Records returned: " . count($inventory));
        if (!empty($inventory)) {
            error_log("First record: " . print_r($inventory[0], true));
        }

        // Get summary data
        $summary_month = isset($_GET['summary_month']) ? $_GET['summary_month'] : '';
        $summary_year = isset($_GET['summary_year']) ? $_GET['summary_year'] : '';
        $summary_sql = "SELECT 
            SUM(laptop_inv) as total_laptop,
            SUM(desktop_inv) as total_desktop,
            SUM(monitor_inv) as total_monitor,
            SUM(printer_inv) as total_printer,
            SUM(phone_inv) as total_phone,
            SUM(appliance_inv) as total_appliance,
            SUM(wearables_inv) as total_wearables,
            SUM(cables_inv) as total_cables,
            SUM(accessories_inv) as total_accessories
            FROM inventory";
        
        $summary_where = [];
        $summary_params = [];
        
        if (!empty($summary_month)) {
            $summary_where[] = 'MONTH(date) = :summary_month';
            $summary_params[':summary_month'] = $summary_month;
        }
        if (!empty($summary_year)) {
            $summary_where[] = 'YEAR(date) = :summary_year';
            $summary_params[':summary_year'] = $summary_year;
        }
        
        if (!empty($summary_where)) {
            $summary_sql .= ' WHERE ' . implode(' AND ', $summary_where);
        }
        
        try {
            $stmt = $conn->prepare($summary_sql);
            foreach ($summary_params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            $stmt->execute();
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Ensure all summary values are at least 0
            $summary = array_map(function($value) {
                return $value === null ? 0 : $value;
            }, $summary);
            
        } catch(PDOException $e) {
            error_log("Summary query error: " . $e->getMessage());
            $summary = [
                'total_laptop' => 0,
                'total_desktop' => 0,
                'total_monitor' => 0,
                'total_printer' => 0,
                'total_phone' => 0,
                'total_appliance' => 0,
                'total_wearables' => 0,
                'total_cables' => 0,
                'total_accessories' => 0
            ];
        }
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        echo "Connection failed: " . $e->getMessage();
        exit();
    }
    
    error_log("=== Filter Debug End ===");
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo "Connection failed: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Waste Inventory - Admin Dashboard</title>
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
            max-width: 100%;
            box-sizing: border-box;
        }
        .card:hover {
            /* Keep hover shadow/transform removed to avoid stacking issues */
            box-shadow: var(--card-shadow);
            transform: none !important;
            z-index: auto !important;
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
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .status-approved {
            background-color: #C6F6D5;
            color: #2F855A;
        }
        .status-rejected {
            background-color: #FED7D7;
            color: #C53030;
        }
        .status-accepted {
            background-color: #C6F6D5;
            color: #228B22;
        }
        .status-completed {
            background-color: #E2E8F0;
            color: #6B7280;
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
            width: 100vw;
            height: 100vh;
            background: rgba(44, 62, 80, 0.18);
            backdrop-filter: blur(2px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal.show {
            opacity: 1;
            display: flex;
        }
        .modal-content {
            background: #fff;
            border-radius: 18px;
            padding: 38px 32px 28px 32px;
            box-shadow: 0 12px 40px rgba(44, 62, 80, 0.18);
            min-width: 340px;
            max-width: 95vw;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            position: relative;
            animation: modalFadeIn 0.35s cubic-bezier(0.4,0,0.2,1);
        }
        @keyframes modalFadeIn {
            0% { opacity: 0; transform: scale(0.92) translateY(30px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-content h3 {
            margin: 0 0 18px 0;
            font-size: 1.25rem;
            color: var(--primary-color);
            font-weight: 600;
            text-align: center;
        }
        .modal-content input[type="date"] {
            padding: 12px 14px;
            border-radius: 7px;
            border: 1px solid #E2E8F0;
            font-size: 1rem;
            margin-bottom: 12px;
        }
        .modal-content .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 12px;
        }
        .modal-content .page-button {
            border-radius: 7px;
            padding: 9px 22px;
            font-size: 1rem;
        }
        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #b0b0b0;
            cursor: pointer;
            transition: color 0.2s;
        }
        .modal-close:hover {
            color: var(--primary-color);
        }
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
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
        .approve-button {
            background-color: #48BB78;
            color: white;
        }
        .approve-button:hover {
            background-color: #38A169;
        }
        .reject-button {
            background-color: #F56565;
            color: white;
        }
        .reject-button:hover {
            background-color: #E53E3E;
        }
        .ewaste-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .ewaste-image:hover {
            transform: scale(1.02);
        }
        /* Image Zoom Modal */
        .image-zoom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            padding: 20px;
            box-sizing: border-box;
        }
        .zoomed-image {
            max-width: 90%;
            max-height: 90vh;
            margin: auto;
            display: block;
            object-fit: contain;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .close-zoom {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            z-index: 10001;
        }
        .submission-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .detail-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        .detail-value {
            color: var(--text-primary);
            font-weight: 500;
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
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

            .inventory-grid {
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

            .inventory-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .inventory-item {
                padding: 15px;
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

            .inventory-grid {
                grid-template-columns: 1fr;
            }

            .inventory-item {
                padding: 12px;
            }

            .inventory-item h3 {
                font-size: 1.1rem;
            }

            .inventory-item p {
                font-size: 0.9rem;
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

            .inventory-item {
                padding: 10px;
            }

            .inventory-item h3 {
                font-size: 1rem;
            }

            .inventory-item p {
                font-size: 0.8rem;
            }

            .inventory-actions .btn {
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

            .inventory-grid {
                display: block;
            }

            .inventory-item {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid #ddd;
                margin-bottom: 10px;
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
        /* Ensure filter dropdown/modal always appears above summary */
        .filter-dropdown, .modal {
            z-index: 2000 !important;
            position: absolute;
        }
        .inventory-summary {
            z-index: 0 !important;
            position: relative;
        }
        .main-content:hover {
            box-shadow: none !important;
            transform: none !important;
            z-index: auto !important;
        }
        /* Style the summary month dropdown to match the filter dropdown */
        #summaryMonthFilter {
            background: #fff !important;
            color: var(--text-primary);
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
            <a href="inventory.php" class="menu-item active">
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

        <!-- Summary Section -->
        <div class="card inventory-summary" style="position:relative; z-index:0;">
            <div class="card-header">
                <div class="card-header-title">Inventory Summary</div>
                <div class="card-header-actions" style="display: flex; gap: 10px;">
                    <select id="summaryMonthFilter" class="filter-btn-pro" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #E2E8F0;">
                        <option value="">All Months</option>
                        <option value="1" <?php echo $summary_month == '1' ? 'selected' : ''; ?>>January</option>
                        <option value="2" <?php echo $summary_month == '2' ? 'selected' : ''; ?>>February</option>
                        <option value="3" <?php echo $summary_month == '3' ? 'selected' : ''; ?>>March</option>
                        <option value="4" <?php echo $summary_month == '4' ? 'selected' : ''; ?>>April</option>
                        <option value="5" <?php echo $summary_month == '5' ? 'selected' : ''; ?>>May</option>
                        <option value="6" <?php echo $summary_month == '6' ? 'selected' : ''; ?>>June</option>
                        <option value="7" <?php echo $summary_month == '7' ? 'selected' : ''; ?>>July</option>
                        <option value="8" <?php echo $summary_month == '8' ? 'selected' : ''; ?>>August</option>
                        <option value="9" <?php echo $summary_month == '9' ? 'selected' : ''; ?>>September</option>
                        <option value="10" <?php echo $summary_month == '10' ? 'selected' : ''; ?>>October</option>
                        <option value="11" <?php echo $summary_month == '11' ? 'selected' : ''; ?>>November</option>
                        <option value="12" <?php echo $summary_month == '12' ? 'selected' : ''; ?>>December</option>
                    </select>
                    <select id="summaryYearFilter" class="filter-btn-pro" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #E2E8F0;">
                        <option value="">All Years</option>
                        <?php 
                        $currentYear = date('Y');
                        for($year = $currentYear; $year >= $currentYear - 5; $year--) {
                            echo "<option value='$year' " . ($summary_year == $year ? 'selected' : '') . ">$year</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="summary-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding: 20px;">
                <div class="summary-item" style="background: #F7FAFC; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 10px;">Laptop</h3>
                    <p style="font-size: 24px; font-weight: 600; color: var(--primary-color);"><?php echo number_format($summary['total_laptop']); ?></p>
                </div>
                <div class="summary-item" style="background: #F7FAFC; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 10px;">Desktop/Server</h3>
                    <p style="font-size: 24px; font-weight: 600; color: var(--primary-color);"><?php echo number_format($summary['total_desktop']); ?></p>
                </div>
                <div class="summary-item" style="background: #F7FAFC; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 10px;">Monitor/TV</h3>
                    <p style="font-size: 24px; font-weight: 600; color: var(--primary-color);"><?php echo number_format($summary['total_monitor']); ?></p>
                </div>
                <div class="summary-item" style="background: #F7FAFC; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 10px;">Printer/Projector</h3>
                    <p style="font-size: 24px; font-weight: 600; color: var(--primary-color);"><?php echo number_format($summary['total_printer']); ?></p>
                </div>
                <div class="summary-item" style="background: #F7FAFC; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 10px;">Smartphone/Tablet</h3>
                    <p style="font-size: 24px; font-weight: 600; color: var(--primary-color);"><?php echo number_format($summary['total_phone']); ?></p>
                </div>
                <div class="summary-item" style="background: #F7FAFC; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 10px;">Home Appliance</h3>
                    <p style="font-size: 24px; font-weight: 600; color: var(--primary-color);"><?php echo number_format($summary['total_appliance']); ?></p>
                </div>
                <div class="summary-item" style="background: #F7FAFC; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 10px;">Wearable</h3>
                    <p style="font-size: 24px; font-weight: 600; color: var(--primary-color);"><?php echo number_format($summary['total_wearables']); ?></p>
                </div>
                <div class="summary-item" style="background: #F7FAFC; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 10px;">Cable/Wire</h3>
                    <p style="font-size: 24px; font-weight: 600; color: var(--primary-color);"><?php echo number_format($summary['total_cables']); ?></p>
                </div>
                <div class="summary-item" style="background: #F7FAFC; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 10px;">Accessory/Peripheral</h3>
                    <p style="font-size: 24px; font-weight: 600; color: var(--primary-color);"><?php echo number_format($summary['total_accessories']); ?></p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-header-title">E-Waste Inventory</div>
                <div class="card-header-center">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by E-Waste ID, type, or date...">
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
                            <div style="margin: 10px 0; border-top: 1px solid #E2E8F0; padding-top: 10px;">
                                <label style="font-weight: 500; margin-bottom: 8px; display: block;">Filter by Items:</label>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                                    <label style="display: flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" name="filter_laptop" value="1" <?php echo isset($_GET['filter_laptop']) ? 'checked' : ''; ?>>
                                        Laptop
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" name="filter_desktop" value="1" <?php echo isset($_GET['filter_desktop']) ? 'checked' : ''; ?>>
                                        Desktop/Server
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" name="filter_monitor" value="1" <?php echo isset($_GET['filter_monitor']) ? 'checked' : ''; ?>>
                                        Monitor/TV
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" name="filter_printer" value="1" <?php echo isset($_GET['filter_printer']) ? 'checked' : ''; ?>>
                                        Printer/Projector
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" name="filter_phone" value="1" <?php echo isset($_GET['filter_phone']) ? 'checked' : ''; ?>>
                                        Smartphone/Tablet
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" name="filter_appliance" value="1" <?php echo isset($_GET['filter_appliance']) ? 'checked' : ''; ?>>
                                        Home Appliance
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" name="filter_wearables" value="1" <?php echo isset($_GET['filter_wearables']) ? 'checked' : ''; ?>>
                                        Wearable
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" name="filter_cables" value="1" <?php echo isset($_GET['filter_cables']) ? 'checked' : ''; ?>>
                                        Cable/Wire
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" name="filter_accessories" value="1" <?php echo isset($_GET['filter_accessories']) ? 'checked' : ''; ?>>
                                        Accessory/Peripherals
                                    </label>
                                </div>
                            </div>
                            <div class="dropdown-actions">
                                <button type="button" id="clearDropdownFilterBtn" class="page-button" style="background: #eee; color: #333;">Clear</button>
                                <button type="submit" class="page-button" style="background: var(--primary-color); color: #fff;">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>E-Waste ID</th>
                            <th>Laptop</th>
                            <th>Desktop/<br>Server</th>
                            <th>Monitor/<br>TV</th>
                            <th>Printer/<br>Projector</th>
                            <th>Smartphone/<br>Tablet</th>
                            <th>Home Appliance</th>
                            <th>Wearable</th>
                            <th>Cable/<br>Wire</th>
                            <th>Accessory/<br>Peripheral</th>
                            <th>Date</th>
                            <th>Submission ID</th>
                            <th>Quotation ID</th>
                            <th>Delivery ID</th>
                            <th>Dropoff ID</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['ewaste_id']); ?></td>
                            <td><?php echo htmlspecialchars($item['laptop_inv']); ?></td>
                            <td><?php echo htmlspecialchars($item['desktop_inv']); ?></td>
                            <td><?php echo htmlspecialchars($item['monitor_inv']); ?></td>
                            <td><?php echo htmlspecialchars($item['printer_inv']); ?></td>
                            <td><?php echo htmlspecialchars($item['phone_inv']); ?></td>
                            <td><?php echo htmlspecialchars($item['appliance_inv']); ?></td>
                            <td><?php echo htmlspecialchars($item['wearables_inv']); ?></td>
                            <td><?php echo htmlspecialchars($item['cables_inv']); ?></td>
                            <td><?php echo htmlspecialchars($item['accessories_inv']); ?></td>
                            <td><?php echo htmlspecialchars($item['date']); ?></td>
                            <td><?php echo htmlspecialchars($item['submission_id']); ?></td>
                            <td><?php echo htmlspecialchars($item['quotation_id']); ?></td>
                            <td><?php echo htmlspecialchars($item['delivery_id'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($item['dropoff_id'] ?? '-'); ?></td>
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

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const inventoryTableBody = document.getElementById('inventoryTableBody');
        const rows = inventoryTableBody.getElementsByTagName('tr');

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
            // Clear all checkboxes
            const checkboxes = filterDropdownForm.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
            window.location.href = 'inventory.php';
        });
        filterDropdownForm.addEventListener('submit', function(e) {
            if (!filterDropdownMonthInput.value && !filterDropdownYearInput.value) {
                e.preventDefault();
                filterDropdown.classList.remove('show');
                window.location.href = 'inventory.php';
            } else {
                filterDropdown.classList.remove('show');
            }
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

        // Add summary month and year filter functionality
        document.getElementById('summaryMonthFilter').addEventListener('change', function() {
            updateSummaryFilters();
        });

        document.getElementById('summaryYearFilter').addEventListener('change', function() {
            updateSummaryFilters();
        });

        function updateSummaryFilters() {
            const month = document.getElementById('summaryMonthFilter').value;
            const year = document.getElementById('summaryYearFilter').value;
            const currentUrl = new URL(window.location.href);
            
            // Remove existing summary filters
            currentUrl.searchParams.delete('summary_month');
            currentUrl.searchParams.delete('summary_year');
            
            // Add new filters if they have values
            if (month) {
                currentUrl.searchParams.set('summary_month', month);
            }
            if (year) {
                currentUrl.searchParams.set('summary_year', year);
            }
            
            // Preserve other existing filters
            const existingFilters = ['filter_month', 'filter_year', 'filter_laptop', 'filter_desktop', 
                                  'filter_monitor', 'filter_printer', 'filter_phone', 'filter_appliance', 
                                  'filter_wearables', 'filter_cables', 'filter_accessories', 'page'];
            
            existingFilters.forEach(filter => {
                const value = currentUrl.searchParams.get(filter);
                if (value) {
                    currentUrl.searchParams.set(filter, value);
                }
            });
            
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>
