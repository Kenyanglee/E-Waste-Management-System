<?php
session_start();
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate unique feedback ID (gap-filling, prefix 'F#')
function generateFeedbackId($conn) {
    $prefix = 'F#';
    $sql = "SELECT feedback_id FROM feedback WHERE feedback_id LIKE 'F#%' ORDER BY feedback_id";
    $result = $conn->query($sql);
    $existingNumbers = array();
    while ($row = $result->fetch_assoc()) {
        $number = intval(substr($row['feedback_id'], 2));
        $existingNumbers[] = $number;
    }
    $nextNumber = 1;
    foreach ($existingNumbers as $number) {
        if ($number != $nextNumber) {
            break;
        }
        $nextNumber++;
    }
    $feedback_id = $prefix . $nextNumber;
    // Ensure uniqueness
    while (true) {
        $check = $conn->query("SELECT 1 FROM feedback WHERE feedback_id = '$feedback_id'");
        if (!$check->fetch_assoc()) break;
        $nextNumber++;
        $feedback_id = $prefix . $nextNumber;
    }
    return $feedback_id;
}

$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        header('Location: auth.php');
        exit();
    }
    $user_id = $_SESSION['user']['user_id'];
    $user_name = $_SESSION['user']['user_name'];
    $email = $_SESSION['user']['email'];
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    $quotation_id = isset($_POST['quotation_id']) ? trim($_POST['quotation_id']) : (isset($_GET['quotation_id']) ? trim($_GET['quotation_id']) : null);
    if ($rating < 1 || $rating > 5 || empty($comment)) {
        $error_message = 'Please select a rating and enter your feedback.';
    } else {
        $feedback_id = generateFeedbackId($conn);
        if ($quotation_id) {
            $stmt = $conn->prepare("INSERT INTO feedback (feedback_id, user_id, quotation_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $feedback_id, $user_id, $quotation_id, $rating, $comment);
        } else {
            $stmt = $conn->prepare("INSERT INTO feedback (feedback_id, user_id, quotation_id, rating, comment) VALUES (?, ?, NULL, ?, ?)");
            $stmt->bind_param("ssis", $feedback_id, $user_id, $rating, $comment);
        }
        if ($stmt->execute()) {
            $success_message = 'Thank you for your feedback!';
        } else {
            $error_message = 'Failed to submit feedback. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Waste Feedback Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            background: radial-gradient(ellipse at center, #1e3c34 0%, #0f1917 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }
        .header-bg {
            width: 100vw;
            height: 100px;
            background: #1e3c34;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .header-title {
            color: #fff;
            font-size: 2.0rem;
            font-weight: 600;
            text-shadow: none;
            margin: 0 auto;
            letter-spacing: 1px;
        }
        .back-btn {
            position: absolute;
            left: 32px;
            top: 50%;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 122px;
            height: 44px;
            background-color: #000000d6;
            border-radius: 50px;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.3s ease-in-out;
            z-index: 10;
        }
        .back-btn .back {
            color: white;
            font-family: "Arial Rounded MT Bold", sans-serif;
            font-size: 20px;
            user-select: none;
        }
        .back-btn:hover {
            transform: translateY(-50%) scale(1.05);
            animation: pulse-back 2s infinite;
        }
        @keyframes pulse-back {
            0%, 100% {
                box-shadow: 0 0 5px 0 rgba(255, 255, 255, 0.7);
            }
            50% {
                box-shadow: 0 0 15px 5px rgba(255, 255, 255, 1);
            }
        }
        .background-under-header {
            width: 100vw;
            min-height: 0;
            background: none;
            display: none;
        }
        .center-container {
            width: 100vw;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: unset;
            position: relative;
            z-index: 1;
        }
        .feedback-card {
            background: rgba(0, 0, 0, 0.8);
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.13);
            width: 600px;
            max-width: 98vw;
            margin: 0 auto;
            margin-top: 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-bottom: 64px;
            padding-top: 0;
            min-height: 480px;
        }
        .card-header-img {
            display: none;
        }
        .feedback-title {
            font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 40px 0 8px 0;
            text-align: center;
        }
        .feedback-desc {
            font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif;
            font-size: 0.95rem;
            color: #fff;
            text-align: center;
            margin: 24px 0 18px 0;
        }
        .star-rating {
            display: flex;
            flex-direction: row;
            justify-content: center;
            margin-top: 0;
            margin-bottom: 16px;
        }
        .star {
            font-size: 2rem;
            color: #e0e0e0;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star.selected {
            color: #F7B801;
        }
        .feedback-textarea {
            font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif;
            width: 90%;
            min-height: 80px;
            max-height: 180px;
            border: none;
            border-radius: 6px;
            padding: 10px;
            font-size: 1rem;
            margin-bottom: 12px;
            resize: none;
            overflow-y: auto;
            background-color: rgba(80, 184, 142, 0.3);
            color: white;
        }
        .feedback-textarea::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .input-row {
            display: flex;
            gap: 8px;
            width: 90%;
            margin-bottom: 16px;
        }
        .input-row input {
            font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif;
            flex: 1;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        .submit-btn {
            font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif;
            background: #E94E89;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 10px 32px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s;
        }
        .submit-btn:hover {
            background: #c93c6e;
        }
        @media (max-width: 700px) {
            .feedback-card { width: 98vw; }
        }
        .form-header-img {
            width: 100%;
            max-width: 100%;
            height: 350px;
            object-fit: cover;
            border-radius: 12px 12px 0 0;
            display: block;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="center-container" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;">
        <div style="position: absolute; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 0; pointer-events: none;">
            <div style="width: 100vw; height: 100vh; background: url('assets/account/recycle.jpg') center top/cover no-repeat;"></div>
            <div style="position: absolute; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.7);"></div>
        </div>
        <a class="back-btn" href="account.php" style="position: absolute; left: 32px; top: 32px; display: inline-flex; align-items: center; justify-content: center; width: 122px; height: 44px; background-color: #000000d6; border-radius: 50px; text-decoration: none; cursor: pointer; transition: transform 0.3s ease-in-out; z-index: 10;">
            <div class="back" style="color: white; font-family: 'Arial Rounded MT Bold', sans-serif; font-size: 20px; user-select: none;">&lt; Back</div>
        </a>
        <div style="font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif; font-size: 2rem; font-weight: bold; color: #59b8a0; text-align: center;margin-top: 20px; margin-bottom: 16px; z-index: 2; position: relative;">Feedback Form</div>
        <form class="feedback-card" method="post" action="" style="position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center;">
            <div style="font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif; font-size: 2rem; font-weight: bold; color: #59b8a0; text-align: center; margin-top: 48px; margin-bottom: 8px;">Your Experience Matters!</div>
            <div style="font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif; font-size: 0.8rem; color: #b5b5b5; text-align: center; margin-bottom: 18px;">We value your feedback to help us improve our service. Please rate your experience and leave a comment below.</div>
            <img src="assets/account/thanks.png" alt="Thank you" style="width: 220px; height: 220px; object-fit: contain; margin-bottom: 18px; margin-top: -8px; display: block;" />
            <div style="display: flex; align-items: center; justify-content: flex-start; width: 90%; margin-bottom: 8px;">
                <span style="font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif; font-size: 1.1rem; color: #fff; margin-right: 12px; min-width: 70px;">Ratings:</span>
                <div class="star-rating" id="starRating" style="margin: 0;">
                    <i class="fa-regular fa-star star" data-value="1"></i>
                    <i class="fa-regular fa-star star" data-value="2"></i>
                    <i class="fa-regular fa-star star" data-value="3"></i>
                    <i class="fa-regular fa-star star" data-value="4"></i>
                    <i class="fa-regular fa-star star" data-value="5"></i>
                </div>
            </div>
            <input type="hidden" name="rating" id="ratingInput" value="0">
            <textarea class="feedback-textarea" name="comment" placeholder="Type here........" style="min-height: 140px; font-size: 1.1rem;"></textarea>
            <?php if (isset($_GET['quotation_id'])): ?>
                <input type="hidden" name="quotation_id" value="<?php echo htmlspecialchars($_GET['quotation_id']); ?>">
            <?php endif; ?>
            <button type="submit" class="submit-btn">SUBMIT FEEDBACK</button>
            <?php if (!empty($error_message)): ?>
                <div style="color: #c90000; font-weight: bold; margin-top: 16px; text-align: center; font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
        </form>
    </div>
    <?php if (!empty($success_message)): ?>
        <div id="successModal" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div style="background: #fff; border-radius: 24px; padding: 40px 32px 32px 32px; box-shadow: 0 8px 32px rgba(0,0,0,0.25); display: flex; flex-direction: column; align-items: center; max-width: 90vw; min-width: 320px;">
                <img src="assets/homepage/success.png" alt="Success" style="width: 120px; height: 120px; object-fit: contain; margin-bottom: 24px;" />
                <div style="font-family: 'Arial Rounded MT Bold', Arial, Helvetica, sans-serif; color: #00bb0c; font-size: 1.3rem; font-weight: bold; margin-bottom: 12px; text-align: center;">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            </div>
        </div>
        <script>
            // Hide the feedback form when modal is shown
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelector('.feedback-card').style.display = 'none';
                setTimeout(function() {
                    window.location.href = 'account.php';
                }, 1000);
            });
        </script>
    <?php endif; ?>
    <script>
        // Star rating logic
        const stars = document.querySelectorAll('.star');
        let selectedRating = 0;
        stars.forEach(star => {
            star.addEventListener('mouseover', function() {
                const val = parseInt(this.getAttribute('data-value'));
                highlightStars(val);
            });
            star.addEventListener('mouseout', function() {
                highlightStars(selectedRating);
            });
            star.addEventListener('click', function() {
                selectedRating = parseInt(this.getAttribute('data-value'));
                highlightStars(selectedRating);
                document.getElementById('ratingInput').value = selectedRating;
            });
        });
        function highlightStars(rating) {
            stars.forEach(star => {
                if (parseInt(star.getAttribute('data-value')) <= rating) {
                    star.classList.add('selected');
                } else {
                    star.classList.remove('selected');
                }
            });
        }
        // Ensure rating is set on submit
        const feedbackForm = document.querySelector('.feedback-card');
        feedbackForm.addEventListener('submit', function(e) {
            if (selectedRating === 0) {
                alert('Please select a rating.');
                e.preventDefault();
                return false;
            }
            document.getElementById('ratingInput').value = selectedRating;
        });
    </script>
</body>
</html>
