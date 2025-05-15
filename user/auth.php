<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password
$dbname = "nothing_wasted";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to generate unique user ID
function generateUserId($conn) {
    $prefix = "user";
    
    // Get all existing user IDs
    $sql = "SELECT user_id FROM user WHERE user_id LIKE 'user%' ORDER BY user_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        // If no users exist, start with user1
        return $prefix . "1";
    }
    
    // Convert results to array of numbers
    $existingNumbers = array();
    while ($row = $result->fetch_assoc()) {
        $number = intval(substr($row['user_id'], strlen($prefix)));
        $existingNumbers[] = $number;
    }
    
    // Find the first gap in the sequence
    $nextNumber = 1;
    foreach ($existingNumbers as $number) {
        if ($number != $nextNumber) {
            // Found a gap, use this number
            return $prefix . $nextNumber;
        }
        $nextNumber++;
    }
    
    // If no gaps found, use the next number after the highest
    return $prefix . ($nextNumber);
}

$message = "";
$message_type = ""; // 'success' or 'error'

// Handle login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // First check if it's an admin
    $admin_sql = "SELECT * FROM admin WHERE email = ? AND password = ?";
    $admin_stmt = $conn->prepare($admin_sql);
    $admin_stmt->bind_param("ss", $email, $password);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();

    if ($admin_result->num_rows > 0) {
        $_SESSION['admin'] = $admin_result->fetch_assoc();
        $login_success = true;
        $is_admin = true;
    } else {
        // If not admin, check if it's a regular user
        $sql = "SELECT * FROM user WHERE email = ? AND password = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $_SESSION['user'] = $result->fetch_assoc();
            $login_success = true;
            $is_admin = false;
        } else {
            $message = "Invalid email or password.";
            $message_type = "error";
        }
    }
}

// Handle registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = $_POST['phone'];
    $zip = $_POST['zip'];
    $dob = $_POST['dob'];
    $address1 = $_POST['address1'];
    $address2 = $_POST['address2'];
    $city = $_POST['city'];
    $state = $_POST['state'];

    // Validate phone number and zip code
    if (!preg_match('/^[0-9]+$/', $phone)) {
        $message = "Phone number must contain only numbers!";
        $message_type = "error";
    } else if (!preg_match('/^[0-9]+$/', $zip)) {
        $message = "Zip code must contain only numbers!";
        $message_type = "error";
    } else {
        // Check if email is unique
        $sql = "SELECT * FROM user WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "Email already in use!";
            $message_type = "error";
            // Force register tab to stay active
            echo "<script>document.getElementById('registerTab').click();</script>";
        } else if ($password !== $confirm_password) {
            $message = "Passwords do not match!";
            $message_type = "error";
            // Force register tab to stay active
            echo "<script>document.getElementById('registerTab').click();</script>";
        } else {
            // Generate unique user ID
            $user_id = generateUserId($conn);
            
            // Combine first and last name
            $user_name = $first_name . ' ' . $last_name;

            $sql = "INSERT INTO user (user_id, user_name, email, contact_number, password, dob, addressline1_1, addressline2_1, zipcode_1, city_1, state_1) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssiss", $user_id, $user_name, $email, $phone, $password, $dob, $address1, $address2, $zip, $city, $state);

            if ($stmt->execute()) {
                $message = "Registration successful! Please log in with your new credentials.";
                $message_type = "success";
                // Refresh the page to show login tab
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'auth.php';
                    }, 1500);
                </script>";
            } else {
                $message = "Registration failed. Please try again.";
                $message_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="style/globals.css" />
    <style>
        /* --- Begin merged CSS from register.css and login.css, scoped for .auth-page --- */
        html, body {
            width: 100vw;
            height: 100vh;
            min-width: 1920px;
            min-height: 1080px;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        body {
            visibility: hidden;
            opacity: 0;
            transition: opacity 1s ease-in-out, visibility 0s 1s;
            background: linear-gradient(180deg, rgba(10, 32, 18, 1) 0%, rgba(0, 0, 0, 1) 100%);
        }
        body.fade-in {
            visibility: visible;
            opacity: 1;
            transition: opacity 1s ease-in-out, visibility 0s;
        }
        .auth-page {
            width: 1920px;
            height: 1080px;
            min-width: 1920px;
            min-height: 1080px;
            max-width: 1920px;
            max-height: 1080px;
            margin: 0 auto;
            position: relative;
            background: linear-gradient(180deg, rgba(10, 32, 18, 1) 0%, rgba(0, 0, 0, 1) 100%);
            overflow-x: hidden;
        }
        .auth-card-outer {
            position: relative;
            width: 1100px;
            margin: 80px auto 0 auto;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            justify-content: center;
            z-index: 10;
            height: auto;
            min-height: 0;
            box-sizing: border-box;
        }
        .auth-bg-globe {
            position: absolute;
            left: -320px;
            top: 80px;
            width: 707px;
            height: 353px;
            object-fit: cover;
            transform: rotate(-90deg) translateX(-707px);
            transform-origin: top left;
            z-index: 12;
            pointer-events: none;
        }
        .auth-card-relative {
            position: relative;
            width: 1100px;
            z-index: 20;
            margin-top: 0;
            height: auto;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .auth-tabs {
            display: flex;
            justify-content: flex-start;
            align-items: flex-end;
            position: absolute;
            top: 0;
            left: 0;
            width: 500px;
            z-index: 40;
            transform: translateY(-50%);
            gap: 0;
        }
        .auth-div {
            position: relative;
            width: 1100px;
            height: 700px;
            margin: 0;
            background: transparent;
            border-radius: 40px;
            overflow: visible;
            box-shadow: none;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            position: relative;
            width: 100%;
            height: 1000px;
            background: #0e2320;
            border-top-right-radius: 40px;
            border-bottom-right-radius: 0;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            overflow: hidden;
            box-shadow: 0 8px 40px 0 rgba(0,0,0,0.4);
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            margin-top: 32px;
            margin-bottom: -60px; /* Tuck under the footer */
        }
        .auth-logo {
            position: relative;
            display: block;
            width: 200px;
            height: 110px;
            margin: 40px auto 0 auto;
            object-fit: contain;
            z-index: 10;
        }
        .auth-tab {
            width: 235px;
            height: 64px;
            background: #18332c;
            border: none;
            outline: none;
            cursor: pointer;
            font-family: "Arial Rounded MT Bold", Helvetica;
            font-size: 36px;
            color: #fff;
            border-radius: 32px 32px 0 0;
            margin: 0;
            position: relative;
            top: 0;
            transition: color 0.3s, background 0.3s, box-shadow 0.3s;
            transition-property: color, background, box-shadow;
            transition-duration: 0.3s, 0.3s, 0.3s;
            transition-timing-function: cubic-bezier(0.4,0,0.2,1);
            box-shadow: 0 4px 12px 0 rgba(0,0,0,0.18);
            z-index: 41;
        }
        .auth-tab.active {
            color: #4fb78e;
            background: #0e2320;
            font-weight: bold;
            z-index: 42;
            box-shadow: 0 8px 16px -8px #0e2320, 0 4px 12px 0 rgba(0,0,0,0.18);
        }
        .auth-tab:not(.active) {
            background: #18332c;
            color: #fff;
            z-index: 30;
            box-shadow: none;
        }
        .auth-desc {
            width: 100%;
            margin-top: 48px;
            font-family: "Arial Rounded MT Bold-Regular", Helvetica;
            font-weight: 400;
            color: transparent;
            font-size: 32px;
            text-align: center;
            letter-spacing: 0;
            line-height: normal;
            white-space: nowrap;
            z-index: 12;
        }
        .auth-desc .span {
            color: #4fb78e;
        }
        .auth-desc .text-wrapper-2 {
            color: #ffffff;
        }
        .auth-forms {
            width: 100%;
            margin-top: 32px;
            z-index: 12;
            position: relative;
            min-height: 350px;
            transition: opacity 0.4s cubic-bezier(0.4,0,0.2,1), visibility 0.4s cubic-bezier(0.4,0,0.2,1);
            opacity: 1;
        }
        .auth-forms.fade {
            opacity: 0;
            pointer-events: none;
        }
        .auth-form {
            width: 100%;
            display: none;
            pointer-events: none;
        }
        .auth-form.active {
            display: block;
            pointer-events: auto;
        }
        .auth-login-grid {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 32px;
            margin-top: 40px;
        }
        .auth-register-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 40px 1fr 1fr;
            grid-template-areas:
                "fn ln . dob dob"
                "email email email addr1 addr1"
                "pw pw pw addr2 addr2"
                "cpw cpw cpw zip city"
                "phone phone phone state state";
            grid-gap: 18px 24px;
            margin-top: 10px;
            padding: 0 40px;
        }
        .auth-register-grid .fn { grid-area: fn; }
        .auth-register-grid .ln { grid-area: ln; }
        .auth-register-grid .dob { grid-area: dob; }
        .auth-register-grid .email { grid-area: email; }
        .auth-register-grid .addr1 { grid-area: addr1; }
        .auth-register-grid .pw { grid-area: pw; }
        .auth-register-grid .addr2 { grid-area: addr2; }
        .auth-register-grid .cpw { grid-area: cpw; }
        .auth-register-grid .zip { grid-area: zip; }
        .auth-register-grid .city { grid-area: city; }
        .auth-register-grid .phone { grid-area: phone; }
        .auth-register-grid .state { grid-area: state; }
        .auth-register-grid .fn input,
        .auth-register-grid .ln input,
        .auth-register-grid .zip input,
        .auth-register-grid .city input,
        .auth-register-grid .state input {
            width: 100%;
            min-width: 120px;
            max-width: 100%;
        }
        .auth-register-grid .dob input,
        .auth-register-grid .addr1 input,
        .auth-register-grid .addr2 input {
            width: 100%;
            min-width: 220px;
            max-width: 400px;
        }
        .auth-register-grid .email input,
        .auth-register-grid .pw input,
        .auth-register-grid .cpw input,
        .auth-register-grid .phone input {
            width: 100%;
            min-width: 220px;
            max-width: 400px;
        }
        .input-wrapper {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .input-wrapper label {
            color: #fff;
            font-family: "Arial Rounded MT Bold-Regular", Helvetica;
            font-size: 18px;
            margin-bottom: 6px;
        }
        .input-wrapper input {
            background-color: white;
            border: none;
            border-radius: 25px;
            color: #333;
            font-size: 16px;
            padding: 12px 20px;
            outline: none;
            width: 320px;
        }
        .input-wrapper input:focus {
            box-shadow: 0 0 5px rgba(79, 183, 142, 0.6);
        }
        .auth-action-button {
            margin: 40px auto 0 auto;
            width: 237px;
            height: 73px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 100px;
            border: 3px solid #ffffff;
            box-shadow: 0px -2px 0px #ffffff;
            cursor: pointer;
            background: transparent;
            font-family: "Arial Rounded MT Bold-Regular", Helvetica;
            font-size: 32px;
            color: #fff;
            transition: transform 0.3s;
        }
        .auth-action-button:hover {
            transform: scale(1.05);
        }
        .auth-footer-bar {
            position: fixed;
            left: 0;
            bottom: 0;
            width: 100vw;
            height: 67px;
            background-color: #57b7a1;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .auth-footer-text {
            font-family: "Archivo Black-Regular", Helvetica;
            font-weight: bold;
            color: #000000;
            font-size: 32px;
            text-align: center;
            letter-spacing: 0;
            line-height: 42.1px;
            white-space: nowrap;
            margin: 0 24px;
        }
        .auth-footer-bar .auth-plant, .auth-footer-bar .auth-plant2 {
            width: 52px;
            height: 55px;
            object-fit: cover;
        }
        .auth-back-group {
            position: absolute;
            top: 27px;
            left: 45px;
            z-index: 1000;
        }
        .auth-back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 122px;
            height: 57px;
            background-color: #000000d6;
            border-radius: 50px;
            text-decoration: none;
            cursor: pointer;
            transform-origin: center;
            transition: transform 0.3s ease-in-out;
        }
        .auth-back-btn .auth-back {
            color: white;
            font-family: "Arial Rounded MT Bold", Helvetica;
            font-size: 24px;
            user-select: none;
        }
        .auth-back-btn:hover {
            transform: scale(1.05);
            animation: pulse-back 2s infinite;
        }
        @keyframes pulse-back {
            0%, 100% { box-shadow: 0 0 5px 0 rgba(255, 255, 255, 0.7); }
            50% { box-shadow: 0 0 15px 5px rgba(255, 255, 255, 1); }
        }
        .auth-form.login1 .auth-action-button {
            margin-top: 48px;
        }
        .auth-bottom-cover {
            position: absolute;
            left: 0;
            width: 1100px;
            height: 40px;
            background: #0e2320;
            z-index: 50;
            
            bottom: 67px; /* height of footer bar */
            margin-left: calc((1920px - 1100px) / 2);
        }
        /* Add pill popup styles */
        .pill-popup {
            position: fixed;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 320px;
            max-width: 90vw;
            padding: 18px 40px;
            border-radius: 50px;
            font-size: 20px;
            font-family: "Arial Rounded MT Bold", Helvetica;
            color: #fff;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            opacity: 0;
            pointer-events: none;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .pill-popup.show {
            top: 40px;
            opacity: 1;
            pointer-events: auto;
        }
        .pill-popup.error {
            background: #e74c3c;
        }
        .pill-popup.success {
            background: #27ae60;
        }
        .auth-register-grid .state select.state-dropdown {
            width: 100%;
            min-width: 120px;
            max-width: 100%;
            height: 42px;
            border-radius: 25px;
            padding: 8px 20px;
            font-size: 16px;
            background: #fff;
            color: #333;
            border: none;
            outline: none;
        }
        .auth-register-grid .state select.state-dropdown:focus {
            box-shadow: 0 0 5px rgba(79, 183, 142, 0.6);
        }

        /* Responsive CSS */
        @media screen and (max-width: 1920px) {
            .auth-page {
                width: 100%;
                min-width: auto;
                max-width: 1920px;
            }
            
            .auth-card-outer {
                width: 90%;
                max-width: 1100px;
            }
            
            .auth-card-relative {
                width: 100%;
            }
            
            .auth-div {
                width: 100%;
            }
            
            .auth-card {
                width: 100%;
            }
            
            .auth-bottom-cover {
                width: 100%;
                margin-left: 0;
            }
        }

        @media screen and (max-width: 1200px) {
            .auth-register-grid {
                grid-template-columns: 1fr 1fr;
                grid-template-areas:
                    "fn ln"
                    "dob dob"
                    "email email"
                    "addr1 addr1"
                    "pw pw"
                    "addr2 addr2"
                    "cpw cpw"
                    "zip city"
                    "phone phone"
                    "state state";
                padding: 0 20px;
            }

            .auth-register-grid .dob input,
            .auth-register-grid .addr1 input,
            .auth-register-grid .addr2 input,
            .auth-register-grid .email input,
            .auth-register-grid .pw input,
            .auth-register-grid .cpw input,
            .auth-register-grid .phone input {
                max-width: 100%;
            }
        }

        @media screen and (max-width: 768px) {
            .auth-tabs {
                width: 100%;
                justify-content: center;
            }

            .auth-tab {
                width: 180px;
                font-size: 28px;
            }

            .auth-logo {
                width: 150px;
                height: 82px;
            }

            .auth-desc {
                font-size: 24px;
                padding: 0 20px;
            }

            .input-wrapper input,
            .input-wrapper select {
                width: 100%;
                min-width: auto;
            }

            .auth-action-button {
                width: 200px;
                height: 60px;
                font-size: 28px;
            }

            .auth-footer-text {
                font-size: 24px;
            }

            .auth-plant, .auth-plant2 {
                width: 40px;
                height: 43px;
            }

            .auth-back-btn {
                width: 100px;
                height: 45px;
            }

            .auth-back {
                font-size: 20px;
            }
        }

        @media screen and (max-width: 480px) {
            .auth-tab {
                width: 140px;
                font-size: 24px;
                height: 50px;
            }

            .auth-logo {
                width: 120px;
                height: 66px;
            }

            .auth-desc {
                font-size: 20px;
            }

            .input-wrapper label {
                font-size: 16px;
            }

            .input-wrapper input,
            .input-wrapper select {
                font-size: 14px;
                padding: 10px 15px;
            }

            .auth-action-button {
                width: 180px;
                height: 50px;
                font-size: 24px;
            }

            .auth-footer-text {
                font-size: 20px;
            }

            .auth-plant, .auth-plant2 {
                width: 32px;
                height: 34px;
            }

            .auth-back-btn {
                width: 80px;
                height: 40px;
            }

            .auth-back {
                font-size: 18px;
            }

            .pill-popup {
                font-size: 16px;
                padding: 12px 24px;
            }
        }

        /* Fix for mobile viewport */
        @media screen and (max-width: 480px) {
            html, body {
                min-width: auto;
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <?php if ($message): ?>
    <div class="pill-popup <?php echo $message_type; ?> show" id="messagePopup">
        <?php echo $message; ?>
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('messagePopup').classList.remove('show');
        }, 3000);
    </script>
    <?php endif; ?>
    
    <div class="auth-page">
        <!-- Logo above everything -->
        <img class="auth-logo" src="assets/homepage/logo.png" alt="Nothing Wasted Logo" />
        <!-- Centered card+tab container -->
        <div class="auth-card-outer">
            <!-- Globe absolutely positioned to hug the card -->
            <img class="auth-bg-globe" src="assets/login/loginearth.png" />
            <!-- Tab bar and card in a single relative container -->
            <div class="auth-card-relative">
                <div class="auth-tabs">
                    <button class="auth-tab active" id="loginTab">Log In</button>
                    <button class="auth-tab" id="registerTab">Register</button>
                </div>
                <div class="auth-div">
                    <div class="auth-card">
                        <div class="auth-desc" id="authDesc">
                            <span class="span">Log in </span><span class="text-wrapper-2">to begin saving the planet.</span>
                        </div>
                        <div class="auth-forms">
                            <!-- Login Form -->
                            <form class="auth-form login1 active" id="loginForm" method="POST">
                                <input type="hidden" name="login" value="1">
                                <div class="auth-login-grid">
                                    <div class="input-wrapper">
                                        <label>Email</label>
                                        <input type="email" name="email" class="rectangle-2" placeholder="Email" required />
                                    </div>
                                    <div class="input-wrapper">
                                        <label>Password</label>
                                        <input type="password" name="password" class="rectangle-3" placeholder="Password" required />
                                    </div>
                                </div>
                                <button type="submit" class="auth-action-button">Log In</button>
                            </form>
                            <!-- Register Form -->
                            <form class="auth-form register1" id="registerForm" method="POST">
                                <input type="hidden" name="register" value="1">
                                <div class="auth-register-grid">
                                    <div class="input-wrapper fn">
                                        <label>First Name</label>
                                        <input type="text" name="first_name" class="rectangle-4" placeholder="First Name" required />
                                    </div>
                                    <div class="input-wrapper ln">
                                        <label>Last Name</label>
                                        <input type="text" name="last_name" class="rectangle-11" placeholder="Last Name" required />
                                    </div>
                                    <div class="input-wrapper dob">
                                        <label>Date Of Birth</label>
                                        <input type="date" name="dob" class="rectangle-6" placeholder="Date Of Birth" required />
                                    </div>
                                    <div class="input-wrapper email">
                                        <label>Email</label>
                                        <input type="email" name="email" class="rectangle-2" placeholder="Email" required />
                                    </div>
                                    <div class="input-wrapper addr1">
                                        <label>Address Line 1</label>
                                        <input type="text" name="address1" class="rectangle-5" placeholder="Address Line 1" required />
                                    </div>
                                    <div class="input-wrapper pw">
                                        <label>Password</label>
                                        <input type="password" name="password" class="rectangle-3" placeholder="Password" required />
                                    </div>
                                    <div class="input-wrapper addr2">
                                        <label>Address Line 2</label>
                                        <input type="text" name="address2" class="rectangle-7" placeholder="Address Line 2" />
                                    </div>
                                    <div class="input-wrapper cpw">
                                        <label>Confirm Password</label>
                                        <input type="password" name="confirm_password" class="rectangle-12" placeholder="Confirm Password" required />
                                    </div>
                                    <div class="input-wrapper phone">
                                        <label>Phone Number</label>
                                        <input type="tel" name="phone" class="rectangle-13" placeholder="Phone Number" required />
                                    </div>
                                    <div class="input-wrapper zip">
                                        <label>Zip Code</label>
                                        <input type="text" name="zip" class="rectangle-8" placeholder="Zip Code" required />
                                    </div>
                                    <div class="input-wrapper city">
                                        <label>City</label>
                                        <input type="text" name="city" class="rectangle-10" placeholder="City" required />
                                    </div>
                                    <div class="input-wrapper state">
                                        <label>State</label>
                                        <select name="state" class="rectangle-9 state-dropdown" required>
                                            <option value="">Select State</option>
                                            <option value="Johor">Johor</option>
                                            <option value="Kedah">Kedah</option>
                                            <option value="Kelantan">Kelantan</option>
                                            <option value="Melaka">Melaka</option>
                                            <option value="Negeri Sembilan">Negeri Sembilan</option>
                                            <option value="Pahang">Pahang</option>
                                            <option value="Penang">Penang</option>
                                            <option value="Perak">Perak</option>
                                            <option value="Perlis">Perlis</option>
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
                                <button type="submit" class="auth-action-button">Register</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Rectangle to cover any small gap below the card -->
        <div class="auth-bottom-cover"></div>
        <!-- Back button -->
        <div class="auth-back-group">
            <a href="homepage_guest.php" class="auth-back-btn">
                <div class="auth-back">&lt; Back</div>
            </a>
        </div>
        <!-- Footer bar at the bottom, outside the card -->
        <div class="auth-footer-bar">
            <img class="auth-plant" src="assets/homepage/plant.png" />
            <span class="auth-footer-text">RECYCLE FOR A BETTER PLANET</span>
            <img class="auth-plant2" src="assets/homepage/plant.png" />
        </div>
    </div>
    <script>
        // Fade in on load
        window.addEventListener("load", () => {
            document.body.classList.add("fade-in");
        });
        window.addEventListener("DOMContentLoaded", () => {
            document.body.classList.add("fade-in");
        });
        // Tab switching logic
        const loginTab = document.getElementById('loginTab');
        const registerTab = document.getElementById('registerTab');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const authTitle = document.getElementById('authTitle');
        const authDesc = document.getElementById('authDesc');

        let isProcessing = false;

        function switchTab(isLogin) {
            if (isProcessing) return; // Prevent tab switching while processing
            
            const forms = document.querySelector('.auth-forms');
            forms.classList.add('fade');
            setTimeout(() => {
                if (isLogin) {
                    loginTab.classList.add('active');
                    registerTab.classList.remove('active');
                    loginForm.classList.add('active');
                    registerForm.classList.remove('active');
                    authDesc.innerHTML = '<span class="span">Log in </span><span class="text-wrapper-2">to begin saving the planet.</span>';
                } else {
                    registerTab.classList.add('active');
                    loginTab.classList.remove('active');
                    registerForm.classList.add('active');
                    loginForm.classList.remove('active');
                    authDesc.innerHTML = '<span class="span">Register </span><span class="text-wrapper-2">to collect points and redeem Rewards!</span>';
                }
                forms.classList.remove('fade');
            }, 350);
        }

        loginTab.addEventListener('click', function() { 
            if (!isProcessing) switchTab(true); 
        });
        registerTab.addEventListener('click', function() { 
            if (!isProcessing) switchTab(false); 
        });

        // Set initial state
        loginForm.classList.add('active');
        registerForm.classList.remove('active');

        // Modify the registration form submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            isProcessing = true;
            
            const formData = new FormData(this);
            const phone = formData.get('phone');
            const zip = formData.get('zip');
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            const email = formData.get('email');
            
            // Check if phone contains only numbers
            if (!/^\d+$/.test(phone)) {
                showPillPopup('Phone number must contain only numbers!', 'error');
                isProcessing = false;
                return;
            }
            
            // Check if zip contains only numbers
            if (!/^\d+$/.test(zip)) {
                showPillPopup('Zip code must contain only numbers!', 'error');
                isProcessing = false;
                return;
            }
            
            // Check password match
            if (password !== confirmPassword) {
                showPillPopup('Passwords do not match!', 'error');
                isProcessing = false;
                return;
            }

            // Check email availability
            fetch('check_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                if (!data.available) {
                    showPillPopup('Email is already in use!', 'error');
                    isProcessing = false;
                    return;
                }
                
                // If email is available, proceed with registration
                fetch('auth.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Check if registration was successful
                    if (html.includes('Registration successful!')) {
                        showPillPopup('Registration successful! Please log in with your new credentials.', 'success');
                        // Wait for the success message to show, then refresh
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show any error messages from the server
                        const errorMatch = html.match(/<div class="pill-popup error show">(.*?)<\/div>/);
                        if (errorMatch) {
                            showPillPopup(errorMatch[1], 'error');
                        }
                        isProcessing = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showPillPopup('An error occurred during registration', 'error');
                    isProcessing = false;
                });
            })
            .catch(error => {
                console.error('Error checking email:', error);
                showPillPopup('Error checking email availability', 'error');
                isProcessing = false;
            });
        });

        function showPillPopup(message, type) {
            const popup = document.createElement('div');
            popup.className = `pill-popup ${type}`;
            popup.textContent = message;
            document.body.appendChild(popup);
            
            // Force a reflow
            void popup.offsetWidth;
            
            // Add show class
            popup.classList.add('show');
            
            setTimeout(() => {
                popup.classList.remove('show');
                setTimeout(() => popup.remove(), 500);
            }, 3000);
        }

        // Update the PHP message display
        <?php if (isset($login_success) && $login_success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showPillPopup('Login successful!', 'success');
            setTimeout(function() {
                <?php if (isset($is_admin) && $is_admin): ?>
                window.location.href = '../admin/admin.php';
                <?php else: ?>
                window.location.href = 'homepage_user.php';
                <?php endif; ?>
            }, 500);
        });
        <?php endif; ?>
    </script>
</body>
</html> 