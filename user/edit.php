<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

$user = $_SESSION['user'];
$user_id = $user['user_id'];

// Fetch user data
$sql = "SELECT * FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $changes_made = false;
    $success_message = '';
    
    // Handle profile picture removal
    if (isset($_POST['remove_profile_pic']) && $_POST['remove_profile_pic'] === '1') {
        $update_sql = "UPDATE user SET profile_pic = NULL WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("s", $user_id);
        
        if ($update_stmt->execute()) {
            $changes_made = true;
            $success_message .= "Profile picture removed successfully. ";
            // Update session data
            $_SESSION['user']['profile_pic'] = null;
            $user['profile_pic'] = null;
        }
    }

    if (isset($_POST['update_profile'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $dob = $_POST['dob'];

        // Update user data
        $update_sql = "UPDATE user SET user_name = ?, email = ?, contact_number = ?, dob = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssss", $name, $email, $phone, $dob, $user_id);
        
        if ($update_stmt->execute()) {
            $changes_made = true;
            $success_message .= "Profile updated successfully!";
            // Update session data
            $_SESSION['user']['user_name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['contact_number'] = $phone;
            $_SESSION['user']['dob'] = $dob;
            $user = $_SESSION['user'];
        }
    }

    if ($changes_made) {
        $success_message = trim($success_message);
    }

    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Verify current password
        if ($current_password === $user['password']) {
            if ($new_password === $confirm_password) {
                $update_sql = "UPDATE user SET password = ? WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $new_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Password updated successfully!";
                    // Update session data
                    $_SESSION['user']['password'] = $new_password;
                    $user = $_SESSION['user'];
                } else {
                    $error_message = "Error updating password. Please try again.";
                }
            } else {
                $error_message = "New passwords do not match!";
            }
        } else {
            $error_message = "Current password is incorrect!";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="style/globals.css" />
    <link rel="stylesheet" href="style/edit.css" />
    <title>Edit Profile</title>
</head>

<body>
    <div class="edit">
        <div class="div">
            <!-- Profile Image Upload Popup -->
            <div id="imageUploadPopup" class="image-upload-popup">
                <div class="image-upload-content">
                    <div class="close-btn">
                        <img class="close1" src="assets/edit/close.png" onclick="closeImageUpload()" />
                    </div>
                    <div class="upload-area">
                        <div class="upload-text">Upload Your Image<br/>Here</div>
                        <form id="uploadForm" enctype="multipart/form-data">
                            <input type="file" id="profileImage" name="profileImage" accept="image/*" style="display: none;" onchange="handleFileSelect(this)"/>
                            <div class="upload-icon" onclick="document.getElementById('profileImage').click()">
                                <img class="upload" src="assets/edit/upload.png" />
                            </div>
                            <div class="file-size-limit">Maximum file size: 16MB</div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="prof-pop" id="passwordPopup">
                <div class="prof-pop1">
                    <div class="pw-text">Change Password</div>
                    <img class="close1" src="assets/edit/close.png" onclick="closePasswordPopup()" />

                    <div class="password-popup-content">
                        <form id="passwordForm" onsubmit="handlePasswordChange(event)">
                            <div class="cp1">
                                <div class="input-label">Current Password</div>
                                <input type="password" name="current_password" class="password-input" required />
                            </div>
                            <div class="cp1">
                                <div class="input-label">New Password</div>
                                <input type="password" name="new_password" class="password-input" required />
                            </div>
                            <div class="cp2">
                                <div class="input-label">Confirm New Password</div>
                                <input type="password" name="confirm_password" class="password-input" required />
                            </div>
                            <div id="passwordAlert" class="password-alert"></div>
                            <button type="submit" class="confirm-button">Save</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="prof-pop" id="emailVerifyPopup">
                <div class="prof-pop1">
                    <div class="pw-text">Verify Password</div>
                    <img class="close1" src="assets/edit/close.png" onclick="closeEmailVerifyPopup()" />

                    <div class="password-popup-content">
                        <form id="emailVerifyForm" onsubmit="handleEmailVerification(event)">
                            <div class="cp1">
                                <div class="input-label">Enter Current Password</div>
                                <input type="password" name="verify_password" class="password-input" required />
                            </div>
                            <div id="emailVerifyAlert" class="password-alert"></div>
                            <button type="submit" class="confirm-button">
                                Confirm
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="backbutton">
                <a href="account.php" class="back-btn">
                    <div class="back">&lt; Back</div>
                </a>
            </div>
            <p class="edit-profile"><span class="text-wrapper">Edit </span> <span class="span">Profile</span></p>
            <div class="overlap">
                <div class="rectangle"></div>
                <div class="group">
                    <div class="profile-edit">
                        <div class="account">
                            <?php if ($user['profile_pic']): ?>
                                <img class="profile" id="profileImage" src="data:image/jpeg;base64,<?php echo base64_encode($user['profile_pic']); ?>" />
                            <?php else: ?>
                                <img class="profile" id="profileImage" src="assets/homepage/account.png" />
                            <?php endif; ?>
                        </div>
                        <div class="edit-wrapper" onclick="openImageUploadPopup()">
                            <img class="editimg" src="assets/edit/edit.png" />
                        </div>
                        <?php if ($user['profile_pic']): ?>
                        <div class="remove-profile-wrapper" onclick="removeProfilePicture()">
                            <img class="removeimg" src="assets/edit/delete.png" />
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST" action="">
                    <div class="name-edit">
                        <div class="overlap-2">
                            <div class="img-wrapper">
                                <img class="editimg" id="nameEditIcon" src="assets/edit/edit.png" onclick="toggleNameEditing(event)" />
                            </div>
                            <div class="text-wrapper-2" id="nameText" onclick="toggleNameEditing(event)"><?php echo htmlspecialchars($user['user_name']); ?></div>
                            <input type="text" id="nameInput" name="name" class="name-input" style="display: none;" value="<?php echo htmlspecialchars($user['user_name']); ?>" onblur="disableEditing()" />
                        </div>
                    </div>
                    <div class="email-edit">
                        <div class="overlap-4">
                            <div class="group-4">
                                <div class="text-wrapper-5">Email</div>
                                <div class="group-5">
                                    <div class="overlap-group-2">
                                        <div class="text-wrapper-6" id="emailText"><?php echo htmlspecialchars($user['email']); ?></div>
                                        <input type="email" id="emailInput" name="email" class="email-input" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                                               oninput="validateEmail(this)" 
                                               style="display:none;" required />
                                    </div>
                                </div>
                            </div>
                            <div class="group-6" id="emailEditIconWrapper">
                                <img class="editimg" id="emailEditIcon" src="assets/edit/edit.png" onclick="toggleEmailEditing(event)" />
                            </div>
                        </div>
                    </div>
                    <div class="password-edit">
                        <div class="overlap-5">
                            <div class="group-8">
                                <div class="text-wrapper-5">Password</div>
                                <div class="group-9">
                                    <div class="overlap-group-2">
                                        <input type="password" id="displayedPassword" class="text-wrapper-7" readonly value="********">
                                    </div>
                                </div>
                            </div>
                            <div class="group-10" onclick="openPasswordPopup()">
                                <img class="editimg" src="assets/edit/edit.png" />
                            </div>
                        </div>
                    </div>
                    <div class="dob-edit">
                        <div class="overlap-6">
                            <div class="group-8">
                                <div class="text-wrapper-8">Date Of Birth</div>
                                <div class="group-9">
                                    <div class="overlap-group-2">
                                        <div class="text-wrapper-7" id="dobText"><?php echo date('d/m/Y', strtotime($user['dob'])); ?></div>
                                        <input type="date" id="dobInput" name="dob" class="dob-input" value="<?php echo $user['dob']; ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="group-12">
                                <img class="editimg" id="dobEditIcon" src="assets/edit/edit.png" onclick="toggleDobEditing(event)" />
                            </div>
                        </div>
                    </div>
                    <div class="phoneno-edit">
                        <div class="overlap-7">
                            <div class="group-8">
                                <div class="text-wrapper-9">Phone Number</div>
                                <div class="group-9">
                                    <div class="overlap-group-2">
                                        <div class="text-wrapper-7" id="phoneText"><?php echo htmlspecialchars($user['contact_number']); ?></div>
                                        <input type="tel" id="phoneInput" name="phone" class="phone-input" value="<?php echo htmlspecialchars($user['contact_number']); ?>" oninput="validatePhoneNumber(this)" />
                                    </div>
                                </div>
                            </div>
                            <div class="group-14">
                                <img class="editimg" id="phoneEditIcon" src="assets/edit/edit.png" onclick="togglePhoneEdit(event)" />
                            </div>
                        </div>
                    </div>
                    <div class="save-button" style="visibility: hidden;">
                        <button type="submit" name="update_profile" class="div-wrapper">
                            <div class="text-wrapper-3">Save</div>
                        </button>
                    </div>
                </form>
                <div class="cancel-button" style="visibility: hidden;">
                    <a href="javascript:history.back()" class="back-btn"></a>
                    <div class="overlap-3">
                        <div class="text-wrapper-4">Cancel</div>
                    </div>
                </div>
            </div>
            <?php if (isset($success_message)): ?>
                <div id="successAlert" class="success-alert"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <!-- Confirmation Popup -->
            <div id="confirmationPopup" class="confirmation-popup">
                <div class="confirmation-content">
                    <div class="confirmation-text">Are you sure you want to remove your profile picture?</div>
                    <div class="confirmation-buttons">
                        <button class="confirm-yes" onclick="confirmRemoveProfile()">Yes</button>
                        <button class="confirm-no" onclick="closeConfirmationPopup()">No</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Profile image upload button
            const profileEditButton = document.querySelector('.profile-edit .edit-wrapper');
            profileEditButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openImageUploadPopup();
            });

            // Close buttons
            const closeButtons = document.querySelectorAll('.close-btn');
            closeButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const popup = this.closest('.image-upload-popup, .prof-pop');
                    if (popup) {
                        closePopup(popup);
                    }
                });
            });
        });

        function enableEditing() {
            var textWrapper = document.getElementById('nameText');
            var inputField = document.getElementById('nameInput');

            textWrapper.style.display = 'none';
            inputField.style.display = 'block';

            inputField.focus();
        }

        function disableEditing() {
            var inputField = document.getElementById('nameInput');
            handleNameUpdate(inputField);
            inputField.style.display = 'none';
            document.getElementById('nameText').style.display = 'block';
        }

        let isEditingEmail = false;
        let emailEditVerified = false;

        function toggleEmailEditing(event) {
            if (event) event.preventDefault();
            if (!emailEditVerified) {
                // Show password verification popup
                const popup = document.getElementById('emailVerifyPopup');
                popup.style.display = 'flex';
                void popup.offsetWidth;
                popup.classList.add('active');
                return;
            }
            const textWrapper = document.getElementById('emailText');
            const inputField = document.getElementById('emailInput');
            const icon = document.getElementById('emailEditIcon');
            if (!isEditingEmail) {
                // Switch to editing mode
                textWrapper.style.display = 'none';
                inputField.style.display = 'block';
                inputField.focus();
                icon.src = 'assets/edit/tick.png';
                icon.onclick = function(event) { saveEmailEdit(event); };
                isEditingEmail = true;
            }
        }

        function handleEmailVerification(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            fetch('verify_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear error message
                    const alertDiv = document.getElementById('emailVerifyAlert');
                    alertDiv.textContent = '';
                    alertDiv.style.display = 'none';
                    // Close verification popup
                    closeEmailVerifyPopup();
                    emailEditVerified = true;
                    toggleEmailEditing();
                    // Reset form
                    form.reset();
                } else {
                    // Show error message
                    const alertDiv = document.getElementById('emailVerifyAlert');
                    alertDiv.textContent = data.message || 'Incorrect password';
                    alertDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const alertDiv = document.getElementById('emailVerifyAlert');
                alertDiv.textContent = 'An error occurred. Please try again.';
                alertDiv.style.display = 'block';
            });
        }

        function saveEmailEdit(event) {
            console.log('Tick clicked');
            if (event) event.preventDefault();
            const textWrapper = document.getElementById('emailText');
            const inputField = document.getElementById('emailInput');
            const icon = document.getElementById('emailEditIcon');
            const email = inputField.value;
            let errorDiv = inputField.parentElement.querySelector('.email-error');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'email-error';
                errorDiv.style.color = '#ff4444';
                errorDiv.style.fontSize = '14px';
                errorDiv.style.marginTop = '5px';
                errorDiv.style.position = 'absolute';
                errorDiv.style.width = '100%';
                errorDiv.style.textAlign = 'left';
                errorDiv.style.paddingLeft = '28px';
                inputField.parentElement.appendChild(errorDiv);
            }
            if (!email) {
                errorDiv.textContent = 'Please enter a valid email address';
                return;
            }
            if (!isValidEmail(email)) {
                errorDiv.textContent = 'Please enter a valid email address';
                return;
            }
            checkEmailAvailability(email).then(isAvailable => {
                if (!isAvailable) {
                    errorDiv.textContent = 'This email is already in use';
                    return;
                } else {
                    errorDiv.textContent = '';
                    // Proceed with update
                    const formData = new FormData();
                    formData.append('email', email);
                    formData.append('update_field', 'email');
                    fetch('update_profile_field.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            textWrapper.textContent = email;
                            showNotification('Email updated successfully!', 'success');
                        } else {
                            showNotification(data.message || 'Failed to update email', 'error');
                        }
                        // Revert to view mode
                        inputField.style.display = 'none';
                        textWrapper.style.display = 'block';
                        icon.src = 'assets/edit/edit.png';
                        icon.onclick = toggleEmailEditing;
                        isEditingEmail = false;
                        emailEditVerified = false;
                    })
                    .catch(error => {
                        showNotification('An error occurred while updating email', 'error');
                        // Revert to view mode
                        inputField.style.display = 'none';
                        textWrapper.style.display = 'block';
                        icon.src = 'assets/edit/edit.png';
                        icon.onclick = toggleEmailEditing;
                        isEditingEmail = false;
                        emailEditVerified = false;
                    });
                }
            });
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function validateEmail(inputField) {
            const email = inputField.value;
            let errorDiv = inputField.parentElement.querySelector('.email-error');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'email-error';
                errorDiv.style.color = '#ff4444';
                errorDiv.style.fontSize = '14px';
                errorDiv.style.marginTop = '5px';
                errorDiv.style.position = 'absolute';
                errorDiv.style.width = '100%';
                errorDiv.style.textAlign = 'left';
                errorDiv.style.paddingLeft = '28px';
                inputField.parentElement.appendChild(errorDiv);
            }
            if (!email) {
                errorDiv.textContent = '';
                return;
            }
            if (!isValidEmail(email)) {
                errorDiv.textContent = 'Please enter a valid email address';
                return;
            }
            checkEmailAvailability(email).then(isAvailable => {
                if (!isAvailable) {
                    errorDiv.textContent = 'This email is already in use';
                } else {
                    errorDiv.textContent = '';
                }
            });
        }

        let isEditingName = false;
        function toggleNameEditing(event) {
            if (event) event.preventDefault();
            const textWrapper = document.getElementById('nameText');
            const inputField = document.getElementById('nameInput');
            const icon = document.getElementById('nameEditIcon');
            if (!isEditingName) {
                textWrapper.style.display = 'none';
                inputField.style.display = 'block';
                inputField.focus();
                icon.src = 'assets/edit/tick.png';
                icon.onclick = function(event) { saveNameEdit(event); };
                isEditingName = true;
            }
        }
        function saveNameEdit(event) {
            if (event) event.preventDefault();
            const textWrapper = document.getElementById('nameText');
            const inputField = document.getElementById('nameInput');
            const icon = document.getElementById('nameEditIcon');
            const name = inputField.value.trim();
            if (!name) {
                showNotification('Name cannot be empty', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('name', name);
            formData.append('update_field', 'name');
            fetch('update_profile_field.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    textWrapper.textContent = name;
                    showNotification('Name updated successfully!', 'success');
                } else {
                    showNotification(data.message || 'Failed to update name', 'error');
                }
                inputField.style.display = 'none';
                textWrapper.style.display = 'block';
                icon.src = 'assets/edit/edit.png';
                icon.onclick = function(event) { toggleNameEditing(event); };
                isEditingName = false;
            })
            .catch(error => {
                showNotification('An error occurred while updating name', 'error');
                inputField.style.display = 'none';
                textWrapper.style.display = 'block';
                icon.src = 'assets/edit/edit.png';
                icon.onclick = function(event) { toggleNameEditing(event); };
                isEditingName = false;
            });
        }

        let isEditingDob = false;
        function toggleDobEditing(event) {
            if (event) event.preventDefault();
            const textWrapper = document.getElementById('dobText');
            const inputField = document.getElementById('dobInput');
            const icon = document.getElementById('dobEditIcon');
            if (!isEditingDob) {
                textWrapper.style.display = 'none';
                inputField.style.display = 'block';
                inputField.focus();
                icon.src = 'assets/edit/tick.png';
                icon.onclick = function(event) { saveDobEdit(event); };
                isEditingDob = true;
            }
        }
        function saveDobEdit(event) {
            if (event) event.preventDefault();
            const textWrapper = document.getElementById('dobText');
            const inputField = document.getElementById('dobInput');
            const icon = document.getElementById('dobEditIcon');
            const dob = inputField.value;
            if (!dob) {
                showNotification('Date of birth cannot be empty', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('dob', dob);
            formData.append('update_field', 'dob');
            fetch('update_profile_field.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const formattedDate = new Date(dob).toLocaleDateString('en-GB');
                    textWrapper.textContent = formattedDate;
                    showNotification('Date of birth updated successfully!', 'success');
                } else {
                    showNotification(data.message || 'Failed to update date of birth', 'error');
                }
                inputField.style.display = 'none';
                textWrapper.style.display = 'block';
                icon.src = 'assets/edit/edit.png';
                icon.onclick = function(event) { toggleDobEditing(event); };
                isEditingDob = false;
            })
            .catch(error => {
                showNotification('An error occurred while updating date of birth', 'error');
                inputField.style.display = 'none';
                textWrapper.style.display = 'block';
                icon.src = 'assets/edit/edit.png';
                icon.onclick = function(event) { toggleDobEditing(event); };
                isEditingDob = false;
            });
        }

        let isEditingPhone = false;
        function togglePhoneEdit(event) {
            if (event) event.preventDefault();
            const textWrapper = document.getElementById('phoneText');
            const inputField = document.getElementById('phoneInput');
            const icon = document.getElementById('phoneEditIcon');
            if (!isEditingPhone) {
                inputField.value = textWrapper.textContent.trim();
                textWrapper.style.display = 'none';
                inputField.style.display = 'block';
                inputField.focus();
                icon.src = 'assets/edit/tick.png';
                icon.onclick = function(event) { savePhoneEdit(event); };
                isEditingPhone = true;
            }
        }
        function savePhoneEdit(event) {
            if (event) event.preventDefault();
            const textWrapper = document.getElementById('phoneText');
            const inputField = document.getElementById('phoneInput');
            const icon = document.getElementById('phoneEditIcon');
            const phone = inputField.value.trim();
            
            if (!phone) {
                showNotification('Phone number cannot be empty', 'error');
                return;
            }
            
            if (!/^\d+$/.test(phone)) {
                showNotification('Phone number can only contain digits', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('phone', phone);
            formData.append('update_field', 'contact_number');
            fetch('update_profile_field.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    textWrapper.textContent = phone;
                    showNotification('Phone number updated successfully!', 'success');
                } else {
                    showNotification(data.message || 'Failed to update phone number', 'error');
                }
                inputField.style.display = 'none';
                textWrapper.style.display = 'block';
                icon.src = 'assets/edit/edit.png';
                icon.onclick = function(event) { togglePhoneEdit(event); };
                isEditingPhone = false;
            })
            .catch(error => {
                showNotification('An error occurred while updating phone number', 'error');
                inputField.style.display = 'none';
                textWrapper.style.display = 'block';
                icon.src = 'assets/edit/edit.png';
                icon.onclick = function(event) { togglePhoneEdit(event); };
                isEditingPhone = false;
            });
        }

        function openImageUploadPopup() {
            const popup = document.getElementById('imageUploadPopup');
            popup.style.display = 'flex';
            // Force a reflow
            void popup.offsetWidth;
            popup.classList.add('active');
        }

        function closeImageUpload() {
            const popup = document.getElementById('imageUploadPopup');
            closePopup(popup);
        }

        function openPasswordPopup() {
            const popup = document.getElementById('passwordPopup');
            popup.style.display = 'flex';
            // Force a reflow
            void popup.offsetWidth;
            popup.classList.add('active');
        }

        function closePasswordPopup() {
            const popup = document.getElementById('passwordPopup');
            closePopup(popup);
        }

        function closePopup(popup) {
            popup.classList.remove('active');
            // Wait for the animation to complete before hiding
            popup.addEventListener('transitionend', function handler() {
                popup.style.display = 'none';
                popup.removeEventListener('transitionend', handler);
            });
        }

        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                const formData = new FormData();
                formData.append('profileImage', input.files[0]);

                fetch('upload_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the profile image on the page
                        const profileImg = document.querySelector('.profile');
                        profileImg.src = URL.createObjectURL(input.files[0]);
                        closeImageUpload();
                        // Show success message
                        showNotification('Profile image updated successfully!', 'success');

                        // Dynamically show or create the remove button
                        let removeBtn = document.querySelector('.remove-profile-wrapper');
                        if (!removeBtn) {
                            // Create the remove button if it doesn't exist
                            removeBtn = document.createElement('div');
                            removeBtn.className = 'remove-profile-wrapper';
                            removeBtn.onclick = removeProfilePicture;
                            const removeImg = document.createElement('img');
                            removeImg.className = 'removeimg';
                            removeImg.src = 'assets/edit/delete.png';
                            removeBtn.appendChild(removeImg);
                            // Insert after the edit-wrapper
                            const editWrapper = document.querySelector('.profile-edit .edit-wrapper');
                            editWrapper.parentNode.appendChild(removeBtn);
                        } else {
                            removeBtn.style.display = '';
                        }
                    } else {
                        showNotification('Failed to upload image: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error uploading image', 'error');
                });
            }
        }

        function removeProfilePicture() {
            const popup = document.getElementById('confirmationPopup');
            popup.style.display = 'flex';
            void popup.offsetWidth;
            popup.classList.add('active');
        }

        function confirmRemoveProfile() {
            closeConfirmationPopup();
            fetch('remove_profile.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the profile image to default
                    const profileImg = document.querySelector('.profile');
                    profileImg.src = 'assets/homepage/account.png';
                    // Hide the remove button
                    const removeBtn = document.querySelector('.remove-profile-wrapper');
                    if (removeBtn) removeBtn.style.display = 'none';
                    // Show success message
                    showNotification('Profile picture removed successfully!', 'success');
                } else {
                    showNotification(data.message || 'Failed to remove profile picture', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error removing profile picture', 'error');
            });
        }

        function closeConfirmationPopup() {
            const popup = document.getElementById('confirmationPopup');
            popup.classList.remove('active');
            // Wait for the animation to complete before hiding
            popup.addEventListener('transitionend', function handler(e) {
                // Only handle the transition of the content
                if (e.target.classList.contains('confirmation-content')) {
                    popup.style.display = 'none';
                    popup.removeEventListener('transitionend', handler);
                }
            });
        }

        function handlePasswordChange(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            fetch('update_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear error message
                    const alertDiv = document.getElementById('passwordAlert');
                    alertDiv.textContent = '';
                    alertDiv.style.display = 'none';
                    // Close popup and show success message
                    closePasswordPopup();
                    showNotification('Password updated successfully!', 'success');
                    // Reset form
                    form.reset();
                } else {
                    // Show error message in the popup and as a pill
                    const alertDiv = document.getElementById('passwordAlert');
                    alertDiv.textContent = data.message || 'Failed to update password';
                    alertDiv.style.display = 'block';
                    alertDiv.style.color = '#ff4444';
                    showNotification(data.message || 'Failed to update password', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const alertDiv = document.getElementById('passwordAlert');
                alertDiv.textContent = 'An error occurred. Please try again.';
                alertDiv.style.display = 'block';
                showNotification('An error occurred. Please try again.', 'error');
            });
        }

        function startEmailEdit() {
            const popup = document.getElementById('emailVerifyPopup');
            popup.style.display = 'flex';
            void popup.offsetWidth;
            popup.classList.add('active');
        }

        function closeEmailVerifyPopup() {
            const popup = document.getElementById('emailVerifyPopup');
            popup.classList.remove('active');
            popup.addEventListener('transitionend', function handler(e) {
                if (e.target.classList.contains('prof-pop1')) {
                    popup.style.display = 'none';
                    popup.removeEventListener('transitionend', handler);
                }
            });
        }

        function handleNameUpdate(inputField) {
            const newName = inputField.value;
            const formData = new FormData();
            formData.append('name', newName);
            formData.append('update_field', 'name');

            fetch('update_profile_field.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('nameText').textContent = newName;
                    showSuccessMessage('Name updated successfully');
                } else {
                    showErrorMessage(data.message || 'Failed to update name');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('An error occurred while updating name');
            });
        }

        function handleDobUpdate(inputField) {
            const newDob = inputField.value;
            const formData = new FormData();
            formData.append('dob', newDob);
            formData.append('update_field', 'dob');

            fetch('update_profile_field.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const formattedDate = new Date(newDob).toLocaleDateString('en-GB');
                    document.getElementById('dobText').textContent = formattedDate;
                    showSuccessMessage('Date of birth updated successfully');
                } else {
                    showErrorMessage(data.message || 'Failed to update date of birth');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('An error occurred while updating date of birth');
            });
        }

        function handlePhoneUpdate(inputField) {
            const newPhone = inputField.value;
            const formData = new FormData();
            formData.append('phone', newPhone);
            formData.append('update_field', 'contact_number');

            fetch('update_profile_field.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('phoneText').textContent = newPhone;
                    showSuccessMessage('Phone number updated successfully');
                } else {
                    showErrorMessage(data.message || 'Failed to update phone number');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('An error occurred while updating phone number');
            });
        }

        function showNotification(message, type) {
            // Remove any existing notifications
            const existingNotifications = document.querySelectorAll('.notification-pill');
            existingNotifications.forEach(notification => notification.remove());

            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification-pill ${type}-pill`;
            notification.textContent = message;
            document.body.appendChild(notification);

            // Trigger animation
            requestAnimationFrame(() => {
                notification.classList.add('animate');
            });

            // Remove notification after animation
            notification.addEventListener('animationend', () => {
                notification.remove();
            });
        }

        function showSuccessMessage(message) {
            showNotification(message, 'success');
        }

        function showErrorMessage(message) {
            showNotification(message, 'error');
        }

        // Auto-hide success message after 3 seconds
        <?php if (isset($success_message)): ?>
        setTimeout(() => {
            document.getElementById('successAlert').style.display = 'none';
        }, 3000);
        <?php endif; ?>

        // Update the form submission handler
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            if (isProfileMarkedForRemoval) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_profile_pic';
                input.value = '1';
                this.appendChild(input);
            }
        });

        // Add cancel button handler
        document.querySelector('.cancel-button').addEventListener('click', function() {
            if (isProfileMarkedForRemoval) {
                // Restore original image
                const profileImg = document.querySelector('.profile');
                profileImg.src = originalProfileSrc;
                profileImg.style.opacity = '1';
                isProfileMarkedForRemoval = false;
                originalProfileSrc = '';
            }
        });

        function checkEmailAvailability(email) {
            return fetch('check_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => data.available)
            .catch(error => {
                console.error('Error checking email:', error);
                return false;
            });
        }

        document.getElementById('emailInput').addEventListener('keydown', function(e) {
            if (isEditingEmail && e.key === 'Enter') {
                e.preventDefault();
                saveEmailEdit(e);
            }
        });

        function validatePhoneNumber(input) {
            // Remove any non-digit characters
            input.value = input.value.replace(/\D/g, '');
        }
    </script>
</body>
</html> 