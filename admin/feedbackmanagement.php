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

// Pagination logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Get total count
    $stmt = $conn->query("SELECT COUNT(*) FROM feedback");
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    // Get paginated feedbacks
    $stmt = $conn->prepare("SELECT f.feedback_id, u.user_name, u.email, f.rating, f.comment, f.quotation_id FROM feedback f LEFT JOIN user u ON f.user_id = u.user_id ORDER BY f.feedback_id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}

// Calculate summary counts for ratings
$rating_counts = array_fill(1, 5, 0);
foreach ($feedbacks as $feedback) {
    $r = intval($feedback['rating']);
    if ($r >= 1 && $r <= 5) $rating_counts[$r]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
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
        body { display: flex; min-height: 100vh; background-color: var(--bg-light); color: var(--text-primary); max-width: var(--max-width); margin: 0 auto; overflow-x: hidden; }
        .sidebar { width: var(--sidebar-width); background-color: var(--primary-color); padding: 24px 24px 32px; color: white; position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; display: flex; flex-direction: column; justify-content: space-between; z-index: 10; }
        .logo { padding: 4px 12px; margin-bottom: 32px; }
        .logo img { width: 100%; height: auto; }
        .menu { display: flex; flex-direction: column; gap: 12px; padding: 8px 16px; }
        .menu-item { display: flex; align-items: center; gap: 16px; padding: 16px; color: white; text-decoration: none; border-radius: 8px; transition: background-color 0.3s; }
        .menu-item:hover { background-color: rgba(255, 255, 255, 0.1); }
        .menu-item.active { background-color: rgba(255, 255, 255, 0.2); }
        .main-content { flex: 1; margin-left: var(--sidebar-width); padding: 20px; max-width: var(--content-width); width: 100%; box-sizing: border-box; }
        .top-bar { display: flex; justify-content: flex-end; align-items: center; padding: 10px 20px; background-color: #D2CECE; border-radius: 8px; margin-bottom: 20px; }
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
        .account:hover { transform: scale(1.1); animation: pulse-profile 2.5s infinite; }
        .account img { width: 100%; height: 100%; object-fit: cover; }
        @keyframes pulse-profile { 0% { box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.8); } 50% { box-shadow: 0 0 20px 10px rgba(255, 255, 255, 0.6); } 100% { box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.8); } }
        .card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: var(--card-shadow); transition: var(--transition-default); border: 1px solid rgba(0, 0, 0, 0.05); }
        .card:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); transform: translateY(-2px); }
        .table-container { overflow-x: auto; max-width: 100%; box-sizing: border-box; }
        .search-bar { position: relative; width: 400px; margin-bottom: 16px; }
        .search-bar input { width: 100%; padding: 12px 20px 12px 45px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 0.95rem; color: var(--text-primary); background: white; transition: all 0.3s ease; }
        .search-bar input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(80, 184, 142, 0.2); }
        .search-bar i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #A0AEC0; font-size: 1.1rem; }
        .search-bar input::placeholder { color: #A0AEC0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #E2E8F0; word-wrap: break-word; }
        th { background-color: var(--bg-light); font-weight: 600; color: var(--text-primary); }
        tr:hover { background-color: var(--bg-light); }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .page-button { padding: 8px 16px; border: 1px solid #E2E8F0; border-radius: 4px; background-color: white; cursor: pointer; transition: var(--transition-default); }
        .page-button:hover { background-color: var(--bg-light); }
        .page-button.active { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
        /* Responsive Styles */
        @media (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .feedback-grid {
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

            .feedback-details {
                flex-direction: column;
            }

            .feedback-section {
                width: 100%;
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
            
            .feedback-grid {
                grid-template-columns: 1fr;
            }

            .feedback-card {
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

            .feedback-stats {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .rating-distribution {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            
            .card {
                padding: 12px;
            }
            
            .feedback-card {
                padding: 12px;
            }
            
            .feedback-card h3 {
                font-size: 1rem;
            }
            
            .feedback-card p {
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

            .feedback-meta {
                flex-direction: column;
                gap: 5px;
            }

            .rating-bar {
                height: 15px;
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

            .feedback-details {
                page-break-inside: avoid;
            }

            .btn, .action-button {
                display: none;
            }

            .rating-distribution {
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
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.35); z-index: 1000; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal.show { opacity: 1; }
        .modal-content { background: #fff; color: #2D3748; border-radius: 18px; padding: 32px 24px 24px 24px; width: 90%; max-width: 500px; box-shadow: 0 8px 32px rgba(0,0,0,0.18); transform: scale(0.7); opacity: 0; transition: transform 0.3s cubic-bezier(.4,2,.6,1), opacity 0.3s cubic-bezier(.4,2,.6,1); position: relative; }
        .modal.show .modal-content { transform: scale(1); opacity: 1; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        .modal-header h3 { margin: 0; color: #2D3748; font-size: 1.35rem; font-weight: 700; }
        .close-button { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #aaa; padding: 0; transition: color 0.3s; position: absolute; top: 18px; right: 18px; }
        .close-button:hover { color: #4A90E2; }
        .modal-body { padding: 10px 0 0 0; color: #2D3748; }
        .modal-body p { margin: 10px 0; }
        .modal-body strong { color: #4A90E2; }
        #modalComment { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px; line-height: 1.6; color: #2D3748; }
    </style>
</head>
<body>
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
            <a href="feedbackmanagement.php" class="menu-item active">
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
            <!-- Summary Table -->
            <div class="card" style="margin-bottom: 24px; padding: 24px; border-radius: 12px; box-shadow: var(--card-shadow); background: white;">
                <h3 style="margin-bottom: 12px; font-size: 1.5rem; font-weight: bold;">Feedback Summary</h3>
                <table style="width: 100%; border-collapse: collapse; background: #f9f9f9; border-radius: 8px; overflow: hidden;">
                    <thead>
                        <tr style="background: #e2e8f0;">
                            <th style="padding: 8px 12px;">Rating</th>
                            <th style="padding: 8px 12px;">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <tr>
                            <td style="padding: 8px 12px;">★ <?php echo $i; ?></td>
                            <td style="padding: 8px 12px;"><?php echo $rating_counts[$i]; ?></td>
                        </tr>
                        <?php endfor; ?>
                        <tr style="font-weight: bold; background: #e2e8f0;">
                            <td style="padding: 8px 12px;">Total</td>
                            <td style="padding: 8px 12px;">
                                <?php echo array_sum($rating_counts); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- End Summary Table -->
            <!-- Feedback Management Table -->
            <div class="card" style="padding: 24px; border-radius: 12px; box-shadow: var(--card-shadow); background: white;">
                <div class="card-header" style="display: flex; align-items: center; justify-content: flex-start; gap: 10px; flex-wrap: wrap;">
                    <h2 style="margin: 0; font-size: 1.5rem; font-weight: bold;">Feedback Management</h2>
                    <div class="search-bar" style="margin-bottom: 0;">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by Name, Email, Rating, or Comment...">
                    </div>
                </div>
                <div class="table-container">
                    <table id="feedbackTable">
                        <thead>
                            <tr>
                                <th>Feedback ID</th>
                                <th>Quotation ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Rating</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody id="feedbackTableBody">
                            <?php foreach ($feedbacks as $feedback): ?>
                            <tr>
                                <td><?= htmlspecialchars($feedback['feedback_id']) ?></td>
                                <td><?= htmlspecialchars($feedback['quotation_id']) ?></td>
                                <td><?= htmlspecialchars($feedback['user_name']) ?></td>
                                <td><?= htmlspecialchars($feedback['email']) ?></td>
                                <td><?= htmlspecialchars($feedback['rating']) ?></td>
                                <td><?= htmlspecialchars($feedback['comment']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= max(1, $total_pages); $i++): ?>
                            <button class="page-button <?php echo $i === $page ? 'active' : ''; ?>" 
                                    onclick="window.location.href='?page=<?php echo $i; ?>'">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <!-- End Feedback Management Table -->
        </div>
    </div>
    <!-- Add Modal HTML before closing body tag -->
    <div id="commentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Feedback Details</h3>
                <button class="close-button" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p><strong>Feedback ID:</strong> <span id="modalFeedbackId"></span></p>
                <p><strong>Quotation ID:</strong> <span id="modalQuotationId"></span></p>
                <p><strong>Name:</strong> <span id="modalName"></span></p>
                <p><strong>Email:</strong> <span id="modalEmail"></span></p>
                <p><strong>Rating:</strong> <span id="modalRating"></span></p>
                <p><strong>Comment:</strong></p>
                <div id="modalComment"></div>
            </div>
        </div>
    </div>
    <script>
    // Function to truncate text
    function truncateText(text, maxWords) {
        const words = text.split(' ');
        if (words.length > maxWords) {
            return words.slice(0, maxWords).join(' ') + '...';
        }
        return text;
    }

    // Function to show modal with full comment
    function showCommentModal(feedbackId, quotationId, name, email, rating, comment) {
        const modal = document.getElementById('commentModal');
        document.getElementById('modalFeedbackId').textContent = feedbackId;
        document.getElementById('modalQuotationId').textContent = quotationId;
        document.getElementById('modalName').textContent = name;
        document.getElementById('modalEmail').textContent = email;
        document.getElementById('modalRating').textContent = rating;
        document.getElementById('modalComment').textContent = comment;
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }

    // Close modal when clicking the X
    function closeModal() {
        const modal = document.getElementById('commentModal');
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300);
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('commentModal');
        if (event.target === modal) {
            closeModal();
        }
    });

    // Update table rows to include click handler and truncate comments
    document.querySelectorAll('#feedbackTableBody tr').forEach(row => {
        const cells = row.cells;
        const comment = cells[5].textContent;
        cells[5].textContent = truncateText(comment, 10); // Truncate to 10 words
        
        // Add click handler to the row
        row.style.cursor = 'pointer';
        row.addEventListener('click', function() {
            showCommentModal(
                cells[0].textContent,
                cells[1].textContent,
                cells[2].textContent,
                cells[3].textContent,
                cells[4].textContent,
                comment // Use original comment
            );
        });
    });

    // Search functionality (client-side)
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        document.querySelectorAll('#feedbackTableBody tr').forEach(row => {
            const id = row.cells[0].textContent.toLowerCase();
            const quotationId = row.cells[1].textContent.toLowerCase();
            const name = row.cells[2].textContent.toLowerCase();
            const email = row.cells[3].textContent.toLowerCase();
            const rating = row.cells[4].textContent.toLowerCase();
            const comment = row.cells[5].textContent.toLowerCase();
            row.style.display =
                id.includes(searchTerm) ||
                quotationId.includes(searchTerm) ||
                name.includes(searchTerm) ||
                email.includes(searchTerm) ||
                rating.includes(searchTerm) ||
                comment.includes(searchTerm)
                ? '' : 'none';
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
    </script>
</body>
</html>
