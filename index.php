<?php
session_start();

// Get error messages from session
$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? '',
    'charity' => $_SESSION['charity_error'] ?? '',
];

// Get active form from session
$activeForm = $_SESSION['active_form'] ?? 'login';

// Get success message if any
$successMessage = $_SESSION['success_message'] ?? '';
$registerSuccess = $_SESSION['register_success'] ?? '';

// If we have a register success message but no general success message, use that
if (empty($successMessage) && !empty($registerSuccess)) {
    $successMessage = $registerSuccess;
}

// Clear session variables AFTER retrieving them
unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['charity_error'], 
      $_SESSION['active_form'], $_SESSION['success_message'], $_SESSION['register_success']);

// Helper functions
function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}
?> 

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KEMOS DONATION - Login & Register</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dialog.css">
    <style>
        /* Additional inline styles to ensure horizontal forms */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-row .form-group input,
        .form-row .form-group select,
        .form-row .form-group textarea {
            width: 100%;
            margin-bottom: 0;
        }
        
        /* Keep login form narrow, make registration forms wider */
        #register-form, #charity-form {
            max-width: 900px;
        }
        
        /* Center align login form */
        #login-form {
            max-width: 450px;
            margin: 0 auto;
        }
        
        /* Center the container for login form */
        .container {
            max-width: 900px;
            display: flex;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .form-row .form-group {
                width: 100%;
            }
            
            #register-form, #charity-form, #login-form {
                max-width: 450px;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">KEMOS DONATION</div>
            <ul class="nav-links">
                <li><a href="#" class="form-switch" data-form="login-form">Login</a></li>
                <li><a href="#" class="form-switch" data-form="register-form">Register</a></li>
                <li><a href="#" class="form-switch" data-form="charity-form">Charity Registration</a></li>
                <li><a href="forgot_password.php">Forgot Password</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <!-- Login Form - Kept in original vertical format and centered -->
        <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
            <form action="auth.php" method="post">
                <div class="form-header">
                    <h1>KEMOS DONATION</h1>
                    <h2>Login</h2>
                </div>
                <?= showError($errors['login']); ?>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="hidden" name="redirect_to" value="user_page.php"> <!-- Ensure redirection -->
                <button type="submit" name="login">Login</button>
                <p>Don't Have an Account? <a href="#" class="form-switch" data-form="register-form">Register</a></p>
                <p>Representing a Charity? <a href="#" class="form-switch" data-form="charity-form">Register as Charity</a></p>
            </form>
        </div>

        <!-- Register Form - Now with horizontal layout -->
        <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
            <form action="auth.php" method="post" id="user-register-form">
                <h2>Register</h2>
                <?= showError($errors['register']); ?>
                
                <!-- First row -->
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="first_name" placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="middle_name" placeholder="Middle Name" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="last_name" placeholder="Last Name" required>
                    </div>
                </div>
                
                <!-- Second row -->
                <div class="form-row">
                    <div class="form-group">
                        <input type="email" name="email" id="user-email" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                        <input type="date" id="dob" name="dob" required min="1900-01-01" max="2006-03-29" placeholder="Date of Birth">
                    </div>
                </div>
                
                <!-- Third row -->
                <div class="form-row">
                    <div class="form-group">
                        <input type="password" name="password" id="user-password" placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="confirm_password" id="user-confirm-password" placeholder="Confirm Password" required>
                    </div>
                </div>
                
                <div id="age-error" class="error-message" style="display: none;">You must be at least 18 years old to register.</div>
                
                <input type="hidden" name="role" value="user">
                <button type="submit" name="register" id="register-btn">Register</button>
                <p>Already Have an Account? <a href="#" class="form-switch" data-form="login-form">Log in</a></p>
            </form>
        </div>

        <!-- Charity Registration Form - Also with horizontal layout -->
        <div class="form-box <?= isActiveForm('charity', $activeForm); ?>" id="charity-form">
            <form action="auth.php" method="post" id="charity-register-form">
                <h2>Charity Registration</h2>
                <?= showError($errors['charity']); ?>
                
                <!-- First row -->
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="charity_name" placeholder="Charity Name" required>
                    </div>
                    <div class="form-group">
                        <select name="country" id="country-select" required>
                            <option value="" disabled selected>Select Country</option>
                            <!-- Countries will be populated by JavaScript -->
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="city" id="city-select" required>
                            <option value="" disabled selected>Select City</option>
                            <!-- Cities will be populated by JavaScript based on country selection -->
                        </select>
                    </div>
                </div>
                
                <!-- Second row -->
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="location" placeholder="Location Of Charity" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" id="charity-email" placeholder="Charity Email" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="contact_person" placeholder="Contact Person" required>
                    </div>
                </div>
                
                <!-- Third row -->
                <div class="form-row">
                    <div class="form-group">
                        <input type="email" name="contact_email" id="contact-person-email" placeholder="Contact Person Email" required>
                    </div>
                    <div class="form-group">
                        <select name="country_code" id="country-code-select" required>
                            <option value="" disabled selected>Select Country Code</option>
                            <!-- Country codes will be populated by JavaScript -->
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="tel" name="phone" placeholder="Phone Number" required>
                    </div>
                </div>
                
                <!-- Fourth row -->
                <div class="form-row">
                    <div class="form-group">
                        <input type="password" name="password" id="charity-password" placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="confirm_password" id="charity-confirm-password" placeholder="Confirm Password" required>
                    </div>
                </div>
                
                <!-- Description (full width) -->
                <textarea name="description" placeholder="Charity Description" rows="3" required></textarea>
                
                <input type="hidden" name="role" value="charity">
                <button type="submit" name="charity_register">Register Charity</button>
                <p>Already A Member ? <a href="#" class="form-switch" data-form="login-form">Log in</a></p>
            </form>
        </div>
    </div>

    <!-- JavaScript for form switching and validation -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show the active form on page load
        const activeForm = '<?= $activeForm ?>';
        document.querySelectorAll('.form-box').forEach(form => {
            form.classList.remove('active');
        });
        
        const formToShow = document.getElementById(activeForm + '-form');
        if (formToShow) {
            formToShow.classList.add('active');
        } else {
            document.getElementById('login-form').classList.add('active');
        }
        
        // Add click handlers for form switching
        document.querySelectorAll('.form-switch').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetForm = this.getAttribute('data-form');
                
                // Hide all forms
                document.querySelectorAll('.form-box').forEach(form => {
                    form.classList.remove('active');
                });
                
                // Show target form
                document.getElementById(targetForm).classList.add('active');
            });
        });

        // Age validation for user registration
        const userRegisterForm = document.getElementById('user-register-form');
        const dobInput = document.getElementById('dob');
        const ageError = document.getElementById('age-error');
        const registerBtn = document.getElementById('register-btn');

        if (dobInput) {
            dobInput.addEventListener('change', validateAge);
            userRegisterForm.addEventListener('submit', function(e) {
                if (!validateAge()) {
                    e.preventDefault();
                }
            });
        }

        function validateAge() {
            if (!dobInput) return true;
            
            const birthDate = new Date(dobInput.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if (age < 18) {
                ageError.style.display = 'block';
                return false;
            } else {
                ageError.style.display = 'none';
                return true;
            }
        }

        // Email validation
        const userEmail = document.getElementById('user-email');
        const charityEmail = document.getElementById('charity-email');
        const contactPersonEmail = document.getElementById('contact-person-email');
        
        // Function to validate email format
        function validateEmail(email) {
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return emailRegex.test(email);
        }
        
        // Add event listeners for email validation
        if (userEmail) {
            userEmail.addEventListener('input', function() {
                if (!validateEmail(this.value) && this.value.length > 0) {
                    this.setCustomValidity('Please enter a valid email address');
                } else {
                    this.setCustomValidity('');
                }
            });
        }

        if (charityEmail) {
            charityEmail.addEventListener('input', function() {
                if (!validateEmail(this.value) && this.value.length > 0) {
                    this.setCustomValidity('Please enter a valid email address');
                } else {
                    this.setCustomValidity('');
                }
            });
        }

        if (contactPersonEmail) {
            contactPersonEmail.addEventListener('input', function() {
                if (!validateEmail(this.value) && this.value.length > 0) {
                    this.setCustomValidity('Please enter a valid email address');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
        
        // Password validation
        const userPassword = document.getElementById('user-password');
        const userConfirmPassword = document.getElementById('user-confirm-password');
        const charityPassword = document.getElementById('charity-password');
        const charityConfirmPassword = document.getElementById('charity-confirm-password');
        
        // Function to validate password format
        function validatePassword(password) {
            const passwordRegex = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@#$%^&+=!]).{8,}$/;
            return passwordRegex.test(password);
        }
        
        // Function to validate password match
        function validatePasswordMatch(password, confirmPassword) {
            return password === confirmPassword;
        }
        
        // Add event listeners for password validation
        if (userPassword && userConfirmPassword) {
            userPassword.addEventListener('input', function() {
                if (!validatePassword(this.value) && this.value.length > 0) {
                    this.setCustomValidity('Password must contain at least 8 characters, including uppercase, lowercase, number, and special character');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            userConfirmPassword.addEventListener('input', function() {
                if (!validatePasswordMatch(userPassword.value, this.value) && this.value.length > 0) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }

        if (charityPassword && charityConfirmPassword) {
            charityPassword.addEventListener('input', function() {
                if (!validatePassword(this.value) && this.value.length > 0) {
                    this.setCustomValidity('Password must contain at least 8 characters, including uppercase, lowercase, number, and special character');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            charityConfirmPassword.addEventListener('input', function() {
                if (!validatePasswordMatch(charityPassword.value, this.value) && this.value.length > 0) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
        
        // Country and city data
        const countries = [
            { code: "US", name: "United States", cities: ["New York", "Los Angeles", "Chicago", "Houston", "Phoenix"] },
            { code: "CA", name: "Canada", cities: ["Toronto", "Montreal", "Vancouver", "Calgary", "Ottawa"] },
            { code: "UK", name: "United Kingdom", cities: ["London", "Manchester", "Birmingham", "Glasgow", "Liverpool"] },
            { code: "AU", name: "Australia", cities: ["Sydney", "Melbourne", "Brisbane", "Perth", "Adelaide"] },
            { code: "DE", name: "Germany", cities: ["Berlin", "Munich", "Hamburg", "Frankfurt", "Cologne"] },
            { code: "FR", name: "France", cities: ["Paris", "Marseille", "Lyon", "Toulouse", "Nice"] },
            { code: "JP", name: "Japan", cities: ["Tokyo", "Osaka", "Kyoto", "Yokohama", "Nagoya"] },
            { code: "BR", name: "Brazil", cities: ["São Paulo", "Rio de Janeiro", "Brasília", "Salvador", "Fortaleza"] },
            { code: "IN", name: "India", cities: ["Mumbai", "Delhi", "Bangalore", "Hyderabad", "Chennai"] },
            { code: "CN", name: "China", cities: ["Beijing", "Shanghai", "Guangzhou", "Shenzhen", "Chengdu"] }
        ];
        
        // Country codes for phone
        const countryCodes = [
            { code: "+1", country: "US/Canada" },
            { code: "+44", country: "UK" },
            { code: "+61", country: "Australia" },
            { code: "+49", country: "Germany" },
            { code: "+33", country: "France" },
            { code: "+81", country: "Japan" },
            { code: "+55", country: "Brazil" },
            { code: "+91", country: "India" },
            { code: "+86", country: "China" },
            { code: "+34", country: "Spain" },
            { code: "+39", country: "Italy" },
            { code: "+7", country: "Russia" },
            { code: "+82", country: "South Korea" },
            { code: "+52", country: "Mexico" },
            { code: "+31", country: "Netherlands" }
        ];
        
        // Populate country dropdown
        const countrySelect = document.getElementById('country-select');
        const citySelect = document.getElementById('city-select');
        
        if (countrySelect) {
            countries.forEach(country => {
                const option = document.createElement('option');
                option.value = country.code;
                option.textContent = country.name;
                countrySelect.appendChild(option);
            });
            
            // Update cities when country changes
            countrySelect.addEventListener('change', function() {
                const selectedCountry = countries.find(country => country.code === this.value);
                
                // Clear current cities
                citySelect.innerHTML = '<option value="" disabled selected>Select City</option>';
                
                if (selectedCountry) {
                    selectedCountry.cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city;
                        option.textContent = city;
                        citySelect.appendChild(option);
                    });
                }
            });
        }
        
        // Populate country code dropdown
        const countryCodeSelect = document.getElementById('country-code-select');
        
        if (countryCodeSelect) {
            countryCodes.forEach(countryCode => {
                const option = document.createElement('option');
                option.value = countryCode.code;
                option.textContent = `${countryCode.code} (${countryCode.country})`;
                countryCodeSelect.appendChild(option);
            });
        }

        // Check for success message in cookie
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
        }
        
        const cookieMessage = getCookie('success_message');
        if (cookieMessage) {
            // Store in sessionStorage for the dialog.js to use
            sessionStorage.setItem('success_message', cookieMessage);
            
            // Clear the cookie
            document.cookie = 'success_message=; Max-Age=-99999999; Path=/';
        }
    });
    </script>
    
    <!-- Dialog functionality disabled -->
    <!-- <script src="dialog.js"></script> -->
</body>

</html>

