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

// Get user's profile picture if logged in
$profile_pic = 'assets/homepage/account.png'; // Default image
if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['user_id'];
    $sql = "SELECT profile_pic FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row && $row['profile_pic'] !== null) {
        $profile_pic = 'data:image/jpeg;base64,' . base64_encode($row['profile_pic']);
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="style/globals.css" />
    <link rel="stylesheet" href="style/homepage_user.css" />
    <script src="script/homepage.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Arial+Rounded+MT+Bold&display=swap');

        :root {
            --dark-green: #1B4D3E;
            --medium-green: #2E7D32;
            --light-green: #4CAF50;
            --hover-green: #45a049;
            --text-light: #E8F5E9;
            --text-dark: #1B4D3E;
            --bg-transparent: rgba(27, 77, 62, 0.95);
            --overlay-color: rgba(0, 0, 0, 0.7);
        }

        .text-wrapper-25 a,
        .text-wrapper-26 a {
            color: white;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .text-wrapper-25 a:hover,
        .text-wrapper-26 a:hover {
            color: #888888;
        }

        /* Modal animations */
        .ewaste-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            transition: background 0.3s ease;
        }

        .ewaste-modal.show {
            display: flex;
            animation: fadeIn 0.3s forwards;
        }

        .ewaste-modal-content {
            background: #10241a !important;
            color: #fff !important;
            transform: scale(0.7);
            opacity: 0;
            animation: scaleIn 0.3s forwards;
        }

        .ewaste-modal h2 {
            color: #fff;
            background-color: #59b8a0;
        }

        .ewaste-modal label {
            color: #fff;
        }

        .ewaste-modal input[type="number"] {
            background: #35393e;
            color: #fff;
            border: 1px solid #444851;
        }

        .ewaste-modal input[type="number"]::placeholder {
            color: #bfc7cf;
        }

        .ewaste-modal .upload-box {
            background: #35393e;
            color: #fff;
            border: 1px solid #444851;
        }

        .ewaste-modal .upload-box p,
        .ewaste-modal .file-info,
        .ewaste-modal .note {
            color: #bfc7cf;
        }

        .ewaste-modal.closing {
            animation: fadeOut 0.3s forwards;
        }

        .ewaste-modal.closing .ewaste-modal-content {
            animation: scaleOut 0.3s forwards;
        }

        @keyframes fadeIn {
            from { background: rgba(0, 0, 0, 0); }
            to { background: rgba(0, 0, 0, 0.8); }
        }

        @keyframes fadeOut {
            from { background: rgba(0, 0, 0, 0.8); }
            to { background: rgba(0, 0, 0, 0); }
        }

        @keyframes scaleIn {
            from { 
                transform: scale(0.7);
                opacity: 0;
            }
            to { 
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes scaleOut {
            from { 
                transform: scale(1);
                opacity: 1;
            }
            to { 
                transform: scale(0.7);
                opacity: 0;
            }
        }

        /* Success modal animations */
        .success-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            transition: background 0.3s ease;
        }

        .success-modal.show {
            display: flex;
            animation: fadeIn 0.3s forwards;
        }

        .success-modal-content {
            background: #10241a !important;
            padding: 32px 24px 24px 24px;
            border-radius: 18px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            color: #fff;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
            position: relative;
            transform: scale(0.7);
            opacity: 0;
            animation: scaleIn 0.3s forwards;
        }

        .success-modal.closing {
            animation: fadeOut 0.3s forwards;
        }

        .success-modal.closing .success-modal-content {
            animation: scaleOut 0.3s forwards;
        }

        .success-modal-content h2 {
            color: #fff !important;
            margin-bottom: 18px;
            font-size: 24px;
            font-weight: 700;
        }
        .success-icon {
            margin: 18px 0 10px 0;
        }
        .success-message {
            color: #fff !important;
            font-size: 18px;
            margin: 18px 0 24px 0;
            font-weight: 500;
        }
        .done-btn {
            background: #1B4D3E;
            color: #fff;
            border: none;
            padding: 12px 36px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
        }
        .done-btn:hover {
            background: #2E7D32;
        }

        /* FAQ Styles */
        .faq-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 1998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .faq-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .faq-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1B4D3E, #2E7D32);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 1999;
            transition: all 0.3s ease;
            border: 2px solid #E8F5E9;
        }

        .faq-button:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
            background: linear-gradient(135deg, #2E7D32, #1B4D3E);
        }

        .faq-button i {
            color: #E8F5E9;
            font-size: 28px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        .faq-panel {
            position: fixed;
            bottom: -100%;
            left: 0;
            width: 100%;
            height: 85vh;
            background: rgba(27, 77, 62, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
            z-index: 1999;
            transition: bottom 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 30px;
            overflow-y: auto;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            font-family: "Arial Rounded MT Bold", Arial, sans-serif;
            color: #E8F5E9;
        }

        .faq-panel.active {
            bottom: 0;
        }

        .faq-panel h2 {
            color: #E8F5E9;
            font-size: 28px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
            position: relative;
            padding-bottom: 15px;
        }

        .faq-panel h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(to right, #4CAF50, #2E7D32);
            border-radius: 2px;
        }

        .faq-item {
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .faq-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            background: rgba(255, 255, 255, 0.15);
        }

        .faq-question {
            cursor: pointer;
            padding: 20px;
            background: transparent;
            font-size: 16px;
            font-weight: 500;
            color: #E8F5E9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .faq-question:after {
            content: '+';
            font-size: 24px;
            color: #4CAF50;
            transition: transform 0.3s ease;
        }

        .faq-question.active:after {
            transform: rotate(45deg);
        }

        .faq-question:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.05);
            padding: 0 20px;
            font-size: 15px;
            line-height: 1.6;
            color: #E8F5E9;
        }

        .faq-answer.active {
            max-height: 300px;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Custom Scrollbar */
        .faq-panel::-webkit-scrollbar {
            width: 8px;
        }

        .faq-panel::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .faq-panel::-webkit-scrollbar-thumb {
            background: #2E7D32;
            border-radius: 4px;
        }

        .faq-panel::-webkit-scrollbar-thumb:hover {
            background: #4CAF50;
        }

        .contact-info {
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .contact-info p {
            margin: 10px 0;
            color: #E8F5E9;
            font-size: 16px;
        }

        .contact-info i {
            margin-right: 10px;
            color: #4CAF50;
        }

        #customAlert {
            position: fixed;
            top: 32px;
            left: 50%;
            transform: translateX(-50%) scale(0.95);
            background: linear-gradient(90deg, #2E7D32, #4CAF50);
            color: #fff;
            padding: 14px 36px;
            border-radius: 999px;
            font-size: 18px;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
            box-shadow: 0 4px 24px rgba(44, 62, 80, 0.18);
            opacity: 0;
            pointer-events: none;
            z-index: 3000;
            transition: opacity 0.4s cubic-bezier(0.4,0,0.2,1), transform 0.4s cubic-bezier(0.4,0,0.2,1);
        }
        #customAlert.show {
            opacity: 1;
            pointer-events: auto;
            transform: translateX(-50%) scale(1);
            animation: alertSlideDown 0.4s cubic-bezier(0.4,0,0.2,1);
        }
        @keyframes alertSlideDown {
            from { opacity: 0; transform: translateX(-50%) translateY(-30px) scale(0.95); }
            to { opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
        }

        .custom-confirm-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.7);
            z-index: 4000;
            justify-content: center;
            align-items: center;
            transition: opacity 0.3s;
        }
        .custom-confirm-overlay.show {
            display: flex;
            animation: fadeIn 0.3s;
        }
        .custom-confirm-modal {
            background: #10241a !important;
            color: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            padding: 32px 28px 24px 28px;
            max-width: 350px;
            width: 90%;
            text-align: center;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
            animation: scaleIn 0.3s;
        }
        .custom-confirm-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
            color:rgb(187, 0, 0);
        }
        .custom-confirm-message {
            font-size: 16px;
            margin-bottom: 24px;
        }
        .custom-confirm-actions {
            display: flex;
            justify-content: center;
            gap: 18px;
        }
        .custom-confirm-btn {
            padding: 10px 28px;
            border-radius: 25px;
            border: none;
            font-size: 16px;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
            cursor: pointer;
            transition: background 0.2s;
        }
        .custom-confirm-btn.ok {
            background: #1B4D3E;
            color: #fff;
        }
        .custom-confirm-btn.ok:hover {
            background: #2E7D32;
        }
        .custom-confirm-btn.cancel {
            background: #E8F5E9;
            color: #1B4D3E;
            border: 1px solid #2E7D32;
        }
        .custom-confirm-btn.cancel:hover {
            background: #c8e6c9;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="homepage">
        <div class="div">
            <div class="overlap">
                <div class="rectangle"></div>
                <div class="rectangle-2"></div>
                <img class="earthbg" src="assets/homepage/earth.jpg" />
                <div class="laptoptxt">Laptops</div>
                <div class="servers-computers">Servers &amp; Computers</div>
                <div class="smartphones-tablets">Smartphones &amp; Tablets</div>
                <div class="printers-scanners">Printers &amp; Scanners</div>
                <div class="tvs-monitors">TVs &amp; Monitors</div>
                <div class="peripherals">Peripherals &amp; Accessories</div>
                <div class="electronic-cables">Electronic Cables &amp; Wires</div>
                <div class="wearables">Wearables</div>
                <div class="home-appliances">Home Appliances</div>
                <img class="appliance" src="assets/homepage/appliance.png" />
                <img class="printer" src="assets/homepage/printer.png" />
                <img class="phone" src="assets/homepage/phone2.png" />
                <div class="text-wrapper-5">WHY RECYCLE WITH</div>
                <p class="visit-our">
                    <span class="span">Visit our 24/7 Dropoff Point in </span> <span class="text-wrapper-6">Bukit Jalil.
                    </span>
                </p>
                <div class="text-wrapper-7">?</div>
                <div class="group">
                    <div class="overlap-group">
                        <div class="rectangle-recycle"></div>
                        <a href="auth.php" class="btn-recycle">Recycle Now&nbsp;&nbsp;&gt;</a>
                    </div>
                </div>
                <div class="group-wrapper">
                    <div class="overlap-group-wrapper">
                        <div class="overlap-group-2">
                            <div class="rectangle-4"></div>
                            <a href="account.php" class="account">
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Account&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&gt;
                            </a>
                        </div>
                    </div>
                </div>
                <img class="logo" src="assets/homepage/logo.png" />
                <p class="p">
                    We have 5/5 stars reviews on Google, pay great cash and reward, and have the highest order
                    acceptance rate!
                </p>
                <div class="text-wrapper-8">We Accept</div>
                <img class="cable" src="assets/homepage/cable.png" />
                <img class="map" src="assets/homepage/map.png" />
                <img class="pin" src="assets/homepage/pin.png" />
                <img class="laptop" src="assets/homepage/laptop2.png" />
                <img class="pc" src="assets/homepage/pc2.png" />
                <img class="monitor" src="assets/homepage/monitor2.png" />
                <img class="keyboard" src="assets/homepage/keyboard.png" />
                <img class="watch" src="assets/homepage/watch2.png" />
            </div>
            <div class="overlap-2">
                <div class="rectangle-5"></div>
                <img class="logo2" src="assets/homepage/logo.png" />
                <div class="profile">
                    <a href="account.php">
                        <img class="profileimg" src="<?php echo htmlspecialchars($profile_pic); ?>" />
                    </a>
                </div>
                <p class="get-paid-by-not">
                    <span class="text-wrapper-6">Get Paid</span>
                    <span class="span"> by not<br />Wasting Your Used Electronics.</span>
                </p>
                <p class="we-offer-FREE-pickup">
                    <span class="text-wrapper-9">We offer FREE pickup from your address or Drop off via our<br /></span>
                    <span class="text-wrapper-10">E-Waste Recycling Centre</span>
                    <span class="text-wrapper-9"> and pay cash rewards for your E-Waste.</span>
                </p>
                <img class="recycle" src="assets/homepage/recycle.png" />
                <div class="div-wrapper">
                    <div class="overlap-group">
                        <div class="rectangle-recycle"></div>
                        <a href="auth.php" class="btn-recycle">Recycle Now&nbsp;&nbsp;&gt;</a>
                    </div>
                </div>
                <img class="phonebg" src="assets/homepage/phone.png" />
                <img class="console" src="assets/homepage/ps5.png" />
                <img class="jbl" src="assets/homepage/jbl.png" />
                <img class="beats" src="assets/homepage/beats.png" />
                <img class="watchbg" src="assets/homepage/watch.png" />
                <img class="camera" src="assets/homepage/camera.png" />
                <img class="pcbg" src="assets/homepage/pc.png" />
                <img class="monitorbg" src="assets/homepage/monitor.png" />
                <img class="laptopbg" src="assets/homepage/laptop.png" />
                <div class="rectangle-6"></div>
                <p class="text-wrapper-11">RECYCLE FOR A BETTER PLANET</p>
                <img class="plant1" src="assets/homepage/plant.png" />
                <img class="plant2" src="assets/homepage/plant.png" />
                <div class="rectangle-7"></div>
                <img class="time" src="assets/homepage/time.png" />
                <p class="we-provide">
                    <span class="span">We Provide </span>
                    <span class="text-wrapper-6">Convenient, Fast &amp; Great Value for Used</span>
                    <span class="span"> &amp; Obsolete Electronic Devices</span>
                </p>
                <img class="box" src="assets/homepage/box.png" />
                <div class="rectangle-8"></div>
                <div class="rectangle-9"></div>
                <div class="rectangle-10"></div>
                <p class="our-competitors">
                    <span class="span">Our competitors </span>
                    <span class="text-wrapper-6">CHARGE</span>
                    <span class="span"> pickups, but we provide </span>
                    <span class="text-wrapper-6">FREE</span>
                    <span class="span"> pickup.</span>
                </p>
                <p class="convenient-fast">
                    <span class="text-wrapper-6">Convenient, Fast, Rewarding.</span>
                    <span class="span"> Instant payment by Duit</span>
                    <span class="text-wrapper-10">Now</span>
                    <span class="span">.</span>
                </p>
                <p class="text-wrapper-12">You deserve to be rewarded for doing the right thing.</p>
                <img class="gift" src="assets/homepage/gift.png" />
                <div class="overlap-wrapper">
                    <div class="overlap-group">
                        <div class="rectangle-recycle"></div>
                        <a href="auth.php" class="btn-recycle">Recycle Now&nbsp;&nbsp;&gt;</a>
                    </div>
                </div>
                <div class="rectangle-11"></div>
                <div class="rectangle-11"></div>
                <p class="how-it-works">
                    <span class="span">How</span>
                    <span class="span">It </span>
                    <span class="text-wrapper-6">Works</span>
                </p>
                <div class="rectangle-12"></div>
                <div class="rectangle-13"></div>
                <div class="text-wrapper-14">Step 1</div>
                <p class="create-an-account-or">
                    <span class="text-wrapper-6">Create an account or login</span>
                    <span class="span">, </span>
                    <span class="text-wrapper-6">fill in</span>
                    <span class="span"> and </span>
                    <span class="text-wrapper-6">verify personal information</span>
                    <span class="span"> for verification.</span>
                </p>
                <div class="rectangle-14"></div>
                <div class="rectangle-15"></div>
                <div class="text-wrapper-15">Step 2</div>
                <p class="click-on-the-recycle">
                    <span class="text-wrapper-6">Click</span>
                    <span class="span"> on the </span>
                    <span class="text-wrapper-16">"Recycle Now"</span>
                    <span class="span">&nbsp;</span>
                    <span class="text-wrapper-6">button</span>
                    <span class="span"> and </span>
                    <span class="text-wrapper-6">choose </span>
                    <span class="span">your</span>
                    <span class="text-wrapper-6"> E-Waste category</span>
                    <span class="span">. </span>
                    <span class="text-wrapper-6">Snap a picture</span>
                    <span class="span"> of your E-Waste and fill in the description, and </span>
                    <span class="text-wrapper-6">submit</span>
                    <span class="span">, our representative will generate a quotation for you </span>
                    <span class="text-wrapper-6">shortly after.</span>
                </p>
                <div class="rectangle-16"></div>
                <div class="rectangle-17"></div>
                <div class="text-wrapper-17">Step 3</div>
                <p class="accept-or-reject-the">
                    <span class="text-wrapper-18">Accept</span>
                    <span class="text-wrapper-6">&nbsp;</span>
                    <span class="span">or</span>
                    <span class="text-wrapper-6">&nbsp;</span>
                    <span class="text-wrapper-10">reject</span>
                    <span class="text-wrapper-6">&nbsp;</span>
                    <span class="span">the quotation sent to you by </span>
                    <span class="text-wrapper-6">viewing</span>
                    <span class="span"> it in your </span>
                    <span class="text-wrapper-6">account page</span>
                    <span class="span">. (We do not allow negotiation).</span>
                </p>
                <div class="rectangle-18"></div>
                <div class="rectangle-19"></div>
                <div class="text-wrapper-19">Step 4</div>
                <p class="hand-the-e-waste-to">
                    <span class="text-wrapper-6">Hand the E-Waste</span>
                    <span class="span"> to our pickup rider or</span>
                    <span class="text-wrapper-6"> drop off</span>
                    <span class="span"> at our E-Waste Recycling Centre.</span>
                </p>
                <div class="rectangle-20"></div>
                <div class="rectangle-21"></div>
                <div class="text-wrapper-20">Step 5</div>
                <p class="receive-payment-and">
                    <span class="text-wrapper-6">Receive payment</span>
                    <span class="span"> and </span>
                    <span class="text-wrapper-6">points</span>
                    <span class="span"> to redeem </span>
                    <span class="text-wrapper-6">Rewards!</span>
                </p>
                <div class="ellipse"></div>
                <div class="ellipse-2"></div>
                <div class="ellipse-3"></div>
                <div class="ellipse-4"></div>
                <div class="group-2">
                    <div class="overlap-group">
                        <div class="rectangle-recycle"></div>
                        <a href="auth.php" class="btn-recycle">Recycle Now&nbsp;&nbsp;&gt;</a>
                    </div>
                </div>
            </div>
            <img class="logo3" src="assets/homepage/logo.png" />
            <div class="text-wrapper-21">Contact Us</div>
            <div class="text-wrapper-22">Location</div>
            <div class="text-wrapper-23">Quick Navigation</div>
            <div class="text-wrapper-24">+60123456789</div>
            <p class="nothing-wasted-jalan">
                Nothing Wasted, <br />Jalan Nothing 1/1,Taman Bukit Jalil,<br />48000 Bukit Jalil,<br />Wilayah
                Persekutuan
                Kuala Lumpur,<br />Malaysia.
            </p>
            <div class="text-wrapper-25"><a href="#" onclick="document.getElementById('ewasteModal').classList.add('show'); return false;">Recycle</a></div>
            <div class="text-wrapper-26"><a href="account.php">Account</a></div>
            <div class="text-wrapper-27">nothingwasted@mail.com</div>
        </div>
    </div>

    <!-- Add the E-Waste Submission Form Modal -->
    <div class="ewaste-modal" id="ewasteModal">
        <div class="ewaste-modal-content" style="background:#1B4D3E;color:#E8F5E9;">
            <button class="close-btn" onclick="closeEwasteModal()">
                <img src="assets/edit/close.png" alt="Close" style="width: 20px; height: 20px;">
            </button>
            <h2>E-Waste Submission Form</h2>
            
            <form id="ewasteForm" method="POST" action="submit_ewaste.php" enctype="multipart/form-data">
                <p class="note">*Only fill in the details of the items you have.</p>
                
                <div class="form-group">
                    <label>How many Laptops?</label>
                    <input type="number" name="laptop_qty" min="0">
                </div>

                <div class="form-group">
                    <label>How many Desktops/Servers?</label>
                    <input type="number" name="desktop_qty" min="0">
                </div>

                <div class="form-group">
                    <label>How many Monitors/TVs?</label>
                    <input type="number" name="monitor_qty" min="0">
                </div>

                <div class="form-group">
                    <label>How many Printers/Projectors?</label>
                    <input type="number" name="printer_qty" min="0">
                </div>

                <div class="form-group">
                    <label>How many Smartphones/Tablets?</label>
                    <input type="number" name="phone_qty" min="0">
                </div>

                <div class="form-group">
                    <label>How many Home Appliances (Kettle, Iron Box etc)?</label>
                    <input type="number" name="appliance_qty" min="0">
                </div>

                <div class="form-group">
                    <label>How many Wearables (Smartwatches, Fitness Trackers etc)?</label>
                    <input type="number" name="wearables_qty" min="0">
                </div>

                <div class="form-group">
                    <label>How many Peripherals/Accessories (Keyboards, Mouse, Chargers etc)?</label>
                    <input type="number" name="accessories_qty" min="0">
                </div>

                <div class="form-group">
                    <label>How many Cables/Wires?</label>
                    <input type="number" name="cables_qty" min="0">
                </div>

                <div class="form-group upload-area">
                    <label>Photo or PDF of Items to be recycled.</label>
                    <div class="upload-box" id="uploadBox">
                        <img src="assets/edit/upload.png" alt="Upload" class="upload-icon">
                        <p>Click or drag a photo or PDF here to upload</p>
                        <input type="file" id="fileInput" name="ewaste_image" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <p class="file-info">Only one file is allowed. You may upload a single image (jpg, jpeg, png) or a PDF (max 16MB). If you have multiple images, please combine them into a PDF and upload that.</p>
                    <div id="fileList" class="file-list"></div>
                </div>

                <button type="submit" class="submit-btn">Submit Request !</button>
            </form>
        </div>
    </div>

    <!-- Add Success Modal -->
    <div class="success-modal" id="successModal">
        <div class="success-modal-content">
            <h2>Submission Successful!</h2>
            <div class="success-icon">
                <img src="assets/homepage/success.png" alt="Success" style="width: 80px; height: 80px;">
            </div>
            <p class="success-message">Your e-waste submission has been received successfully!</p>
            <button class="done-btn" onclick="closeSuccessModal()">Done</button>
        </div>
    </div>

    <!-- Add FAQ Section -->
    <div class="faq-overlay" id="faqOverlay" onclick="closeFaqPanel()"></div>
    <div class="faq-button" onclick="toggleFaqPanel()">
        <i class="fas fa-question"></i>
    </div>
    <div class="faq-panel" id="faqPanel">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleAnswer(this)">
                Do I need an account to submit e-waste?
            </div>
            <div class="faq-answer">
                Yes, you must have a registered and logged-in account to submit any e-waste through the system.<br>This helps us keep track of your submissions, assign points correctly, and ensure secure and personalized services.<br>If you do not have an account yet, simply click on the "Account" icon or any buttons and complete the registration form.<br>Once registered, you can log in and start submitting your e-waste.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleAnswer(this)">
                How can I submit my e-waste?
            </div>
            <div class="faq-answer">
                After logging in, click the "Recycle Now" button.<br>You will be required to provide details about the electronic items you wish to recycle, such as the type, image, and quantity.<br>Then, you can select whether you'd like to have the items picked up by our team (delivery) or if you'd prefer to drop them off at a nearby recycling center.<br>Once submitted, your request will be reviewed by the system and processed accordingly.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleAnswer(this)">
                Can I choose the date for delivery or drop-off?
            </div>
            <div class="faq-answer">
                No, users cannot manually schedule the date or time for pickup or drop-off.<br>The system automatically assigns the date based on internal logistics, resource availability, and the queue of active submissions.<br>You will be updated with the scheduled date and further instructions once your submission has been processed by the system.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleAnswer(this)">
                How do I earn points from submitting e-waste?
            </div>
            <div class="faq-answer">
                Each time you submit eligible e-waste and it is successfully collected or dropped off, the system will calculate reward points based on the quantity and type of items submitted.<br>These points are added to your account and can be viewed from your user account.<br>The more you recycle, the more you earn!
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleAnswer(this)">
                What can I redeem with my points?
            </div>
            <div class="faq-answer">
                Points can be redeemed for rewards such as shopping vouchers, gift cards, or cd-keys.<br>Go to the "Rewards" page by clicking Redeem in your account page to browse all available items.<br>Once you have enough points, you can redeem a reward, and the redemption will be recorded for reference in your Redemptions.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleAnswer(this)">
                How do I check my submission status?
            </div>
            <div class="faq-answer">
                You can track the progress of your e-waste submissions in the "Submission History" section after logging in.<br>It will show all the details including dates, approval status, and items submitted.<br>This helps keep you informed about your contributions and their current processing stage.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleAnswer(this)">
                Can I provide feedback after submitting e-waste?
            </div>
            <div class="faq-answer">
                Yes, after the delivery or drop-off process is complete, you'll be able to leave feedback regarding your experience.<br>Your feedback helps us improve the overall service quality and ensures better scheduling, communication, and user satisfaction.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleAnswer(this)">
                Is the delivery service free?
            </div>
            <div class="faq-answer">
                Yes, our system provides free delivery service for e-waste collection.<br>You won't be charged for scheduling a pickup.<br>This service is part of our commitment to making e-waste disposal easy and accessible for everyone.
            </div>
        </div>
        <div class="contact-info">
            <p>Need more help? Contact us at:</p>
            <p><i class="fas fa-phone"></i> +60123456789</p>
            <p><i class="fas fa-envelope"></i> nothingwasted@mail.com</p>
        </div>
    </div>

    <!-- Pill-shaped custom alert -->
    <div id="customAlert"></div>

    <!-- Custom confirmation modal -->
    <div id="customConfirmOverlay" class="custom-confirm-overlay">
        <div class="custom-confirm-modal">
            <div class="custom-confirm-title">Unsaved Input</div>
            <div class="custom-confirm-message">You have unsaved input. Are you sure you want to close the form?</div>
            <div class="custom-confirm-actions">
                <button id="customConfirmOk" class="custom-confirm-btn ok">Yes, Close</button>
                <button id="customConfirmCancel" class="custom-confirm-btn cancel">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Get all Recycle Now buttons
        document.querySelectorAll('.btn-recycle').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const modal = document.getElementById('ewasteModal');
                modal.classList.remove('closing'); // Always remove closing state
                modal.classList.add('show');
                // Scroll window and modal content to top after modal is visible
                setTimeout(() => {
                    window.scrollTo({ top: 0, behavior: 'auto' });
                    const modalContent = modal.querySelector('.ewaste-modal-content');
                    if (modalContent) modalContent.scrollTop = 0;
                    // Also scroll the form inside modal if needed
                    const form = modalContent ? modalContent.querySelector('form') : null;
                    if (form) form.scrollTop = 0;
                }, 10);
            });
        });

        function isFormDirty() {
            const form = document.getElementById('ewasteForm');
            // Check all input fields except submit
            const inputs = form.querySelectorAll('input[type="number"], input[type="file"]');
            for (let input of inputs) {
                if (input.type === 'file') {
                    if (uploadedFile !== null) return true;
                } else {
                    if (input.value && input.value !== '0') return true;
                }
            }
            return false;
        }

        function closeEwasteModal(force = false) {
            if (!force && isFormDirty()) {
                showCustomConfirm('You have unsaved input. Are you sure you want to close the form?', () => closeEwasteModal(true), null);
                return;
            }
            const modal = document.getElementById('ewasteModal');
            modal.classList.add('closing');
            setTimeout(() => {
                modal.classList.remove('show', 'closing');
                // Clear the form
                document.getElementById('ewasteForm').reset();
                uploadedFile = null;
                fileList.innerHTML = '';
                fileInput.value = '';
            }, 300);
        }

        // Close modal when clicking outside
        document.getElementById('ewasteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEwasteModal();
            }
        });

        function showSuccessModal() {
            document.getElementById('successModal').classList.add('show');
        }

        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            modal.classList.add('closing');
            setTimeout(() => {
                modal.classList.remove('show', 'closing');
                document.getElementById('ewasteModal').classList.remove('show');
            }, 300);
        }

        // Pill-shaped custom alert logic
        function showCustomAlert(message) {
            const alertDiv = document.getElementById('customAlert');
            alertDiv.textContent = message;
            alertDiv.classList.add('show');
            // Remove after 3 seconds
            setTimeout(() => {
                alertDiv.classList.remove('show');
            }, 3000);
        }

        // Update form submission handling
        document.getElementById('ewasteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get all quantity inputs
            const quantities = ['laptop_qty', 'desktop_qty', 'monitor_qty', 'printer_qty', 'phone_qty', 'appliance_qty', 'wearables_qty', 'accessories_qty', 'cables_qty'];
            let totalItems = 0;
            
            // Set default value of 0 for empty fields and calculate total
            quantities.forEach(id => {
                const input = document.querySelector(`input[name="${id}"]`);
                if (!input.value || input.value === '') {
                    input.value = '0';
                }
                totalItems += parseInt(input.value);
            });
            
            // Check if at least one item is submitted
            if (totalItems === 0) {
                showCustomAlert('Please enter at least one item');
                return;
            }

            // Check if files are selected
            if (uploadedFile === null) {
                showCustomAlert('Please upload a photo or PDF of your items.');
                return;
            }

            // Create FormData object
            const formData = new FormData(this);

            // Submit form using fetch
            fetch('submit_ewaste.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Add closing animation to form modal
                    const formModal = document.getElementById('ewasteModal');
                    formModal.classList.add('closing');
                    
                    setTimeout(() => {
                        // Show success modal after form closes
                        showSuccessModal();
                        
                        // Clear the form
                        this.reset();
                        uploadedFile = null;
                        fileList.innerHTML = '';
                        fileInput.value = '';
                    }, 300);
                } else {
                    throw new Error(data.message || 'Submission failed');
                }
            })
            .catch(error => {
                showCustomAlert('Error: ' + error.message);
            });
        });

        // File upload handling
        const uploadBox = document.getElementById('uploadBox');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const maxTotalSize = 16 * 1024 * 1024; // 16MB in bytes
        let uploadedFile = null; // Only one file allowed

        uploadBox.addEventListener('click', (e) => {
            if (e.target === uploadBox) {
                fileInput.click();
            }
        });

        uploadBox.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadBox.classList.add('dragover');
        });
        uploadBox.addEventListener('dragleave', () => {
            uploadBox.classList.remove('dragover');
        });
        uploadBox.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadBox.classList.remove('dragover');
            const newFiles = Array.from(e.dataTransfer.files);
            handleFiles(newFiles);
        });

        fileInput.addEventListener('change', (e) => {
            const newFiles = Array.from(e.target.files);
            handleFiles(newFiles);
        });

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            else if (bytes < 1048576) return (bytes / 1024).toFixed(2) + ' KB';
            else return (bytes / 1048576).toFixed(2) + ' MB';
        }

        function handleFiles(newFiles) {
            if (newFiles.length === 0) return;
            const file = newFiles[0];
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                alert('Only PDF, JPG, JPEG, and PNG files are allowed.');
                return;
            }
            if (file.size > maxTotalSize) {
                alert('File size exceeds 16MB limit.');
                return;
            }
            uploadedFile = file;
            updateFileList();
        }

        function updateFileList() {
            fileList.innerHTML = '';
            if (uploadedFile) {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info';
                fileInfo.innerHTML = `
                    <strong>File:</strong> ${uploadedFile.name}<br>
                    <small>Size: ${formatFileSize(uploadedFile.size)} | Type: ${uploadedFile.type.split('/')[1] ? uploadedFile.type.split('/')[1].toUpperCase() : uploadedFile.type.toUpperCase()}</small>
                `;
                const removeButton = document.createElement('button');
                removeButton.className = 'remove-file';
                removeButton.innerHTML = '×';
                removeButton.onclick = () => {
                    uploadedFile = null;
                    updateFileList();
                    fileInput.value = '';
                };
                fileItem.appendChild(fileInfo);
                fileItem.appendChild(removeButton);
                fileList.appendChild(fileItem);
                // Update the file input's files
                const dt = new DataTransfer();
                dt.items.add(uploadedFile);
                fileInput.files = dt.files;
            } else {
                fileInput.value = '';
            }
        }

        // Add FAQ functionality
        function toggleFaqPanel() {
            const panel = document.getElementById('faqPanel');
            const overlay = document.getElementById('faqOverlay');
            panel.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function closeFaqPanel() {
            const panel = document.getElementById('faqPanel');
            const overlay = document.getElementById('faqOverlay');
            panel.classList.remove('active');
            overlay.classList.remove('active');
        }

        function toggleAnswer(element) {
            const answer = element.nextElementSibling;
            const question = element;
            
            // Toggle active class on question
            question.classList.toggle('active');
            
            // Toggle answer visibility
            answer.classList.toggle('active');
            
            // Close other answers
            const allQuestions = document.querySelectorAll('.faq-question');
            const allAnswers = document.querySelectorAll('.faq-answer');
            
            allQuestions.forEach((q, index) => {
                if (q !== question) {
                    q.classList.remove('active');
                    allAnswers[index].classList.remove('active');
                }
            });
        }

        // Custom confirm modal logic
        function showCustomConfirm(message, onConfirm, onCancel) {
            const overlay = document.getElementById('customConfirmOverlay');
            const msgDiv = overlay.querySelector('.custom-confirm-message');
            msgDiv.textContent = message;
            overlay.classList.add('show');
            // Button handlers
            const okBtn = document.getElementById('customConfirmOk');
            const cancelBtn = document.getElementById('customConfirmCancel');
            // Remove previous listeners
            okBtn.onclick = () => {
                overlay.classList.remove('show');
                if (onConfirm) onConfirm();
            };
            cancelBtn.onclick = () => {
                overlay.classList.remove('show');
                if (onCancel) onCancel();
            };
        }
    </script>
</body>

</html> 