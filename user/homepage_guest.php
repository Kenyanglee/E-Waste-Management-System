<?php
session_start();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="style/globals.css" />
    <link rel="stylesheet" href="style/homepage_guest.css" />
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

        /* Add smooth scrolling behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Add scroll progress indicator */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 4px;
            background: linear-gradient(to right, #4fb78e, #e74184);
            z-index: 10000;
            transition: width 0.2s ease;
        }

        /* Add scroll to top button */
        .scroll-to-top {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: auto;
            width: 50px;
            height: 50px;
            background-color: #4fb78e;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 10000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .scroll-to-top.visible {
            opacity: 1;
            visibility: visible;
            animation: bounce 1s ease infinite;
        }

        .scroll-to-top:hover {
            background-color: #e74184;
            transform: scale(1.1);
        }

        .scroll-to-top::before {
            content: "↑";
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        /* Add animation keyframes */
        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        /* Add animation classes */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .animate-on-scroll.animated {
            opacity: 1;
            transform: translateY(0);
        }

        /* Enhance scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #4fb78e;
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #e74184;
        }

        /* Add fade-in animation for content sections */
        .rectangle-12,
        .rectangle-14,
        .rectangle-16,
        .rectangle-18,
        .rectangle-20,
        .text-wrapper-5,
        .text-wrapper-8,
        .how-it-works,
        .get-paid-by-not,
        .we-provide {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .rectangle-12.animated,
        .rectangle-14.animated,
        .rectangle-16.animated,
        .rectangle-18.animated,
        .rectangle-20.animated,
        .text-wrapper-5.animated,
        .text-wrapper-8.animated,
        .how-it-works.animated,
        .get-paid-by-not.animated,
        .we-provide.animated {
            opacity: 1;
            transform: translateY(0);
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

        /* FAQ Styles */
        .faq-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--overlay-color);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 998;
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
            background: linear-gradient(135deg, var(--dark-green), var(--medium-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 1000;
            transition: all 0.3s ease;
            border: 2px solid var(--text-light);
        }

        .faq-button:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
            background: linear-gradient(135deg, var(--medium-green), var(--dark-green));
        }

        .faq-button i {
            color: var(--text-light);
            font-size: 28px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        .faq-panel {
            position: fixed;
            bottom: -100%;
            left: 0;
            width: 100%;
            height: 85vh;
            background: var(--bg-transparent);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
            z-index: 999;
            transition: bottom 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 30px;
            overflow-y: auto;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
            color: var(--text-light);
        }

        .faq-panel.active {
            bottom: 0;
        }

        .faq-panel h2 {
            color: var(--text-light);
            font-size: 28px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
            position: relative;
            padding-bottom: 15px;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
        }

        .faq-panel h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(to right, var(--light-green), var(--medium-green));
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
            color: var(--text-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
        }

        .faq-question:after {
            content: '+';
            font-size: 24px;
            color: var(--light-green);
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
            color: var(--text-light);
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
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
            background: var(--medium-green);
            border-radius: 4px;
        }

        .faq-panel::-webkit-scrollbar-thumb:hover {
            background: var(--light-green);
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
            color: var(--text-light);
            font-size: 16px;
        }

        .contact-info i {
            margin-right: 10px;
            color: var(--light-green);
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Add scroll progress indicator -->
    <div class="scroll-progress"></div>
    
    <!-- Add scroll to top button (only once, bottom left) -->
    <div class="scroll-to-top"></div>
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
                            <a href="auth.php" class="account">
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
                    <a href="auth.php">
                        <img class="profileimg" src="assets/homepage/account.png" />
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
            <div class="text-wrapper-25"><a href="auth.php">Recycle</a></div>
            <div class="text-wrapper-26"><a href="auth.php">Account</a></div>
            <div class="text-wrapper-27">nothingwasted@mail.com</div>
        </div>
    </div>
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

    <script>
        // No dynamic creation of scroll-to-top or scroll-progress
        // Only scroll logic and animation
        window.addEventListener('scroll', () => {
            const windowHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (window.scrollY / windowHeight) * 100;
            document.querySelector('.scroll-progress').style.width = scrolled + '%';
            if (window.scrollY > 300) {
                document.querySelector('.scroll-to-top').classList.add('visible');
            } else {
                document.querySelector('.scroll-to-top').classList.remove('visible');
            }
            animateOnScroll();
        });
        document.querySelector('.scroll-to-top').addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate-on-scroll');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementBottom = element.getBoundingClientRect().bottom;
                const isVisible = (elementTop < window.innerHeight) && (elementBottom >= 0);
                if (isVisible) {
                    element.classList.add('animated');
                }
            });
        }

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
    </script>
</body>

</html> 