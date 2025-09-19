<?php
session_start();
require 'rfid-api/db.php';

// Initialize variables
$success = false;
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a signup form submission
    if (isset($_POST['firstName']) && isset($_POST['email'])) {
        try {
            // Get and sanitize form data
            $firstName = trim($_POST['firstName']);
            $middleName = trim($_POST['middleName']);
            $lastName = trim($_POST['lastName']);
            $dob = $_POST['dob'];
            $age = (int) $_POST['age'];
            $sex = $_POST['sex'];
            $email = trim(strtolower($_POST['email']));
            $phone = trim($_POST['phone']);
            $signupPassword = $_POST['signupPassword'];
            $confirmPassword = $_POST['confirmPassword'];

            // Validation
            $errors = [];

            // Validate email format
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Please enter a valid email address.";
            }

            // Validate phone number (basic validation for Philippine format)
            if (!empty($phone) && !preg_match('/^(\+63|0)?[0-9]{10,11}$/', str_replace([' ', '-', '(', ')'], '', $phone))) {
                $errors[] = "Please enter a valid phone number.";
            }

            if (!empty($errors)) {
                $error = implode('<br>', $errors);
            } else {
                // Check for duplicate email
                $checkEmailQuery = "SELECT COUNT(*) FROM visitor_details WHERE email_address = ?";
                $checkStmt = $conn->prepare($checkEmailQuery);
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $emailCount = $result->fetch_row()[0];
                $checkStmt->close();

                if ($emailCount > 0) {
                    $error = "An account with this email address already exists. Please use a different email or try logging in.";
                } else {
                    // Generate new visitor_id (VIS-0001, VIS-0002...)
                    $result = $conn->query("SELECT visitor_id FROM visitor_details ORDER BY visitor_id DESC LIMIT 1");
                    if ($result && $row = $result->fetch_assoc()) {
                        $last_id = intval(substr($row['visitor_id'], 4)); // extract numeric part
                        $new_id_number = $last_id + 1;
                    } else {
                        $new_id_number = 1; // first visitor
                    }
                    $visitor_id = 'VIS-' . str_pad($new_id_number, 4, '0', STR_PAD_LEFT);

                    // Hash the password
                    $hashedPassword = password_hash($signupPassword, PASSWORD_DEFAULT);

                    // Insert into database
                    $insertQuery = "INSERT INTO visitor_details (
                        visitor_id,
                        first_name, 
                        middle_name, 
                        last_name, 
                        date_of_birth, 
                        age, 
                        sex, 
                        email_address, 
                        cellphone_number, 
                        password, 
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bind_param(
                        "sssssissss",
                        $visitor_id,
                        $firstName,
                        $middleName,
                        $lastName,
                        $dob,
                        $age,
                        $sex,
                        $email,
                        $phone,
                        $hashedPassword
                    );

                    if ($stmt->execute()) {
                        $success = true;
                        // Start a session and log the user in
                        $_SESSION['visitor_id'] = $visitor_id;
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();
                    } else {
                        $error = "Failed to create account. Please try again.";
                    }
                    $stmt->close();
                }
            }

        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NSSHAI HOA Management</title>
    <link rel="icon" href="images/SitioSeville_Logo.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS & JS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        * {
            font-family: "Montserrat", sans-serif;
        }

        .hero-overlay {
            background-color: rgba(34, 100, 47, 0.6);
        }

        .nav-link:hover {
            color: #198754 !important;
        }

        .hero-section {
            background-image: url('images/gazebo.png');
            background-size: cover;
            background-position: center;
        }

        .amenities-section {
            background-color: #3B724B;
        }

        .text-drop-shadow {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .auth-toggle {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 20px;
        }

        .auth-toggle button {
            border: none;
            background: transparent;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;
        }

        .auth-toggle button.active {
            background: white;
            color: #198754;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .auth-toggle button:not(.active) {
            color: #6c757d;
        }

        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }

        .auth-form {
            transition: all 0.3s ease;
        }

        .auth-form.hidden {
            display: none !important;
        }
    </style>
</head>

<body class="bg-light text-dark">

    <!-- Header -->
    <header class="sticky-top">
        <div class="bg-white shadow">
            <div class="container-fluid custom-container px-4 py-3 d-flex justify-content-between align-items-center">
                <img src="images/NSSHAI_crop.png" class="object-fit-cover" alt="NSSHAI" style="height: 70px;">
                <nav class="d-flex align-items-center gap-3" style="font-size: 16px;">
                    <a href="#home" class="nav-link text-success fw-semibold text-decoration-none">Home</a>
                    <a href="#about" class="nav-link text-secondary text-decoration-none">About Us</a>
                    <a href="#amenities" class="nav-link text-secondary text-decoration-none">Amenities</a>
                    <a href="#inquire" class="nav-link text-secondary text-decoration-none">Contact Us</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Home Section -->
    <section id="home"
        class="position-relative text-center min-vh-100 d-flex flex-column justify-content-center align-items-center hero-section">

        <!-- Translucent Dark Green Overlay -->
        <div class="position-absolute top-0 start-0 w-100 h-100 hero-overlay"></div>

        <!-- Content -->
        <div class="position-relative" style="z-index: 10;">
            <h2 class="display-4 fw-bold mb-4 text-white text-drop-shadow">Welcome to Our Community</h2>
            <div class="container">
                <div class="row justify-content-center">
                    <p class="fs-5 mb-4 text-white text-drop-shadow">
                        Explore our amenities and services available to visitors and residents.
                        <br>Book your visit online today!
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about" class=" d-flex flex-column justify-content-center align-items-center text-center py-5">
        <div class="mb-2">
            <span class="mb-2 fw-bold text-success" style="font-size: 60px; letter-spacing: 10px;">NSSHAI</span>
        </div>
        <div class="mb-5">
            <span class="fw-medium" style="font-family: 'Libre Baskerville', serif; font-size: 64px;;">Neopolitan
                Sitio Seville</span>
        </div>
        <div class="mb-5">
            <span style="font-size: 20px;">
                Neopolitan Sitio Seville Homeowners' Association, Inc. (NSSHAI) is a private <br>residential
                subdivision
                located in North Fairview, Quezon City.
            </span>
        </div>
        <div class="mb-5">
            <span class="fw-medium text-success mb-4" style="font-size: 48px;">VISION</span>
        </div>
        <div class="mb-5">
            <span style="font-size: 20px;">
                To be a premier gated subdivision in Quezon City, setting the standard for safety,<br>
                security, cleanliness, and meticulous, while fostering a serene and inviting<br>
                environment that enhances the quality of life for our homeowners and residents.
            </span>
        </div>
        <div class="mb-5">
            <span class="fw-medium text-success mb-4" style="font-size: 48px;">MISSION</span>
        </div>
        <div class="mb-5">
            <span style="font-size: 20px;">
                Dedicated to creating a haven of safety and tranquility, we commit to maintaining<br>
                the highest standards of security, cleanliness, and upkeep in our subdivision. We strive to<br>
                foster a strong sense of community, providing a space where homeowners and residents<br>
                can enjoy a peaceful and enriching living experience. Our mission is to continuously<br>
                enhance the well-being of our community through proactive management, open<br>
                communication, and a commitment to excellence.
            </span>
        </div>
    </section>

    <!-- Amenities Section with Carousel -->
    <section id="amenities" class="p-5 amenities-section text-center">
        <h3 class="display-6 fw-bold mb-4 text-white">What We Offer</h3>
        <!-- Bootstrap Carousel -->
        <div>
            <div id="amenityCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
                <!-- Indicators -->
                <div class="carousel-indicators mb-2">
                    <button type="button" data-bs-target="#amenityCarousel" data-bs-slide-to="0" class="active"
                        aria-current="true"></button>
                    <button type="button" data-bs-target="#amenityCarousel" data-bs-slide-to="1"></button>
                    <button type="button" data-bs-target="#amenityCarousel" data-bs-slide-to="2"></button>
                    <button type="button" data-bs-target="#amenityCarousel" data-bs-slide-to="3"></button>
                </div>

                <!-- Slides -->
                <div class="carousel-inner rounded shadow">
                    <!-- Clubhouse -->
                    <div class="carousel-item active">
                        <img src="images/clubhouse.png" class="d-block w-100 object-fit-cover"
                            style="height: 600px; object-fit: cover;" alt="Clubhouse">
                    </div>

                    <!-- Pool -->
                    <div class="carousel-item">
                        <img src="images/pool.png" class="d-block w-100 object-fit-cover"
                            style="height: 600px; object-fit: cover;" alt="Swimming Pool">
                    </div>

                    <!-- Gazebo -->
                    <div class="carousel-item">
                        <img src="images/gazebo.png" class="d-block w-100 object-fit-cover"
                            style="height: 600px; object-fit: cover;" alt="Gazebo">
                    </div>

                    <!-- Basketball -->
                    <div class="carousel-item">
                        <img src="images/basketball.png" class="d-block w-100 object-fit-cover"
                            style="height: 600px; object-fit: cover;" alt="Basketball Court">
                    </div>
                </div>

                <!-- Arrows -->
                <button class="carousel-control-prev" type="button" data-bs-target="#amenityCarousel"
                    data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#amenityCarousel"
                    data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
        <!-- Amenity Cards -->
        <div class="row g-4 mt-3">
            <!-- Clubhouse -->
            <div class="col-md-6 col-lg-6">
                <div class="bg-white rounded shadow p-4 text-start h-100">
                    <h5 class="h4 fw-bold text-success mb-3">Clubhouse</h5>
                    <p class="text-muted">A spacious venue for community events, private gatherings, and HOA
                        meetings equipped with basic amenities and seating.</p>
                </div>
            </div>
            <!-- Swimming Pool -->
            <div class="col-md-6 col-lg-6">
                <div class="bg-white rounded shadow p-4 text-start h-100">
                    <h5 class="h4 fw-bold text-success mb-3">Swimming Pool</h5>
                    <p class="text-muted">Perfect for residents and guests to cool off, relax, or host
                        poolside activities. Cleaned and maintained regularly.</p>
                </div>
            </div>
            <!-- Gazebo -->
            <div class="col-md-6 col-lg-6">
                <div class="bg-white rounded shadow p-4 text-start h-100">
                    <h5 class="h4 fw-bold text-success mb-3">Gazebo</h5>
                    <p class="text-muted">A peaceful open-air structure ideal for outdoor relaxation, small
                        picnics, or quiet community gatherings.</p>
                </div>
            </div>
            <!-- Basketball Court -->
            <div class="col-md-6 col-lg-6">
                <div class="bg-white rounded shadow p-4 text-start h-100">
                    <h5 class="h4 fw-bold text-success mb-3">Basketball Court</h5>
                    <p class="text-muted">A full-sized court for residents who love sports. Great for casual
                        games, practices, or mini tournaments.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Login/Signup Section with Google Map -->
    <section id="inquire" class="p-5 bg-light">
        <h3 class="text-center fw-bold mb-4">Book Amenity</h3>
        <div class="row justify-content-center">
            <!-- Google Map Column -->
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="rounded shadow overflow-hidden" style="height: 100%; min-height: 550px;">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1654.007002586308!2d121.06013695214578!3d14.718581856770555!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b08b93fdec6b%3A0xf47ba662230f452f!2sSitio%20Seville%20Clubhouse!5e0!3m2!1sen!2sph!4v1751431430949!5m2!1sen!2sph"
                        width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            <!-- Login/Signup Form Column -->
            <div class="col-lg-6">
                <div class="bg-white p-4 rounded shadow d-flex flex-column" style="height: 100%; min-height: 670px;">
                    <!-- Auth Toggle -->
                    <div class="auth-toggle d-flex">
                        <button type="button" id="loginTab" class="active">Log In</button>
                        <button type="button" id="signupTab">Sign Up</button>
                    </div>
                    <!-- Login Form -->
                    <form id="loginForm" class="auth-form mt-3 d-flex flex-column flex-grow-1"
                        action="visitor_side/visitor_login.php" method="POST">
                        <div class="my-auto">
                            <div class="mb-5 ">
                                <div class="mb-3">
                                    <label for="email_address" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email_address" name="email_address"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label mt-2">Password</label>
                                    <div class="input-group">
                                        <input type="password" id="password" name="password" required
                                            class="form-control" minlength="6" />
                                        <button type="button" class="btn btn-outline-secondary" id="togglePassword1"
                                            tabindex="-1">
                                            <i class="bi bi-eye" id="toggleIcon1"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success w-100 my-3">Login</button>
                            <div class="text-end">
                                <a href="#" class="text-success text-decoration-none small">Forgot your
                                    password?</a>
                            </div>
                        </div>
                    </form>
                    <!-- Signup Form -->
                    <form id="signupForm" class="auth-form mt-3 hidden" action="landing.php" method="POST">
                        <div class="row mb-3">
                            <div class="col-4">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" required>
                            </div>
                            <div class="col-4">
                                <label for="middleName" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middleName" name="middleName" required>
                            </div>
                            <div class="col-4">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="dob" class="form-control" id="dobInput" required
                                    max="<?php echo date('Y-m-d'); ?>" />
                            </div>
                            <div class="col-4">
                                <label class="form-label">Age</label>
                                <input type="number" name="age" class="form-control" id="ageInput" readonly />
                            </div>
                            <div class="col-4">
                                <label class="form-label">Sex</label>
                                <select name="sex" class="form-select" required>
                                    <option value="" selected disabled>Select</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label for="signupEmail" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="signupEmail" name="email" required>
                            </div>
                            <div class="col-6">
                                <label for="phoneNumber" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phoneNumber" name="phone" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="form-label mt-2">Password</label>
                            <div class="input-group">
                                <input type="password" id="signupPassword" name="signupPassword" required
                                    class="form-control" minlength="6" />
                                <button type="button" class="btn btn-outline-secondary" id="togglePassword2"
                                    tabindex="-1">
                                    <i class="bi bi-eye" id="toggleIcon2"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Password must be at least 6 characters long</small>
                        </div>
                        <div class="row mb-3">
                            <label class="form-label mt-2">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" id="confirmPassword" name="confirmPassword" required
                                    class="form-control" minlength="6" />
                                <button type="button" class="btn btn-outline-secondary" id="togglePassword3"
                                    tabindex="-1">
                                    <i class="bi bi-eye" id="toggleIcon3"></i>
                                </button>
                            </div>
                            <!-- Password mismatch error div -->
                            <div id="passwordMismatchError" class="text-danger small mt-1" style="display: none;">
                                <i class="bi bi-exclamation-circle"></i> Passwords do not match
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 my-3">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p class="mb-0">&copy; 2025 HOA Community. All rights reserved.</p>
        </div>
    </footer>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-success text-white">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-check2-circle text-success" style="font-size: 64px;"></i>
                    <p class="mb-2"><b>Success</b></p>
                    <p class="mb-3">User details have been successfully saved.</p>
                    <button type="button" class="btn btn-success" id="doneButton">Done</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-danger text-white">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                    <p class="mb-2"><b>Error</b></p>
                    <p class="mb-3" id="errorMessage">
                        <?php echo isset($error) ? htmlspecialchars($error) : 'An error occurred while processing your request.'; ?>
                    </p>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php if (isset($success) && $success): ?>
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();

                const redirect = () => window.location.href = 'visitor_side/dashboard.php';
                document.getElementById('doneButton').addEventListener('click', redirect);
                document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
            });
        </script>
    <?php endif; ?>
    <?php if (isset($error) && $error): ?>
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
            });
        </script>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Function to show error modal with custom message
            function showErrorModal(message) {
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.innerHTML = message;
                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
            }

            // Get form elements
            const signupForm = document.getElementById('signupForm');
            const dobInput = document.getElementById('dobInput');
            const ageInput = document.getElementById('ageInput');
            const passwordInput = document.getElementById('signupPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');

            function showLogin() {
                document.getElementById('loginForm').classList.remove('hidden');
                document.getElementById('signupForm').classList.add('hidden');
                document.getElementById('loginTab').classList.add('active');
                document.getElementById('signupTab').classList.remove('active');

                // Add fade in animation
                document.getElementById('loginForm').classList.add('fade-in');
                setTimeout(() => {
                    document.getElementById('loginForm').classList.remove('fade-in');
                }, 300);
            }

            function showSignup() {
                document.getElementById('loginForm').classList.add('hidden');
                document.getElementById('signupForm').classList.remove('hidden');
                document.getElementById('loginTab').classList.remove('active');
                document.getElementById('signupTab').classList.add('active');

                // Add fade in animation
                document.getElementById('signupForm').classList.add('fade-in');
                setTimeout(() => {
                    document.getElementById('signupForm').classList.remove('fade-in');
                }, 300);
            }

            // Add event listeners for tab buttons
            document.getElementById('loginTab').addEventListener('click', showLogin);
            document.getElementById('signupTab').addEventListener('click', showSignup);

            // Password Toggle Functionality
            function togglePassword(inputId, toggleButtonId, iconId) {
                const input = document.getElementById(inputId);
                const toggleButton = document.getElementById(toggleButtonId);
                const icon = document.getElementById(iconId);

                if (input && toggleButton && icon) {
                    toggleButton.addEventListener('click', function () {
                        if (input.type === 'password') {
                            input.type = 'text';
                            icon.classList.remove('bi-eye');
                            icon.classList.add('bi-eye-slash');
                        } else {
                            input.type = 'password';
                            icon.classList.remove('bi-eye-slash');
                            icon.classList.add('bi-eye');
                        }
                    });
                }
            }

            // Setup password toggle for both password fields
            togglePassword('password', 'togglePassword1', 'toggleIcon1');
            togglePassword('signupPassword', 'togglePassword2', 'toggleIcon2');
            togglePassword('confirmPassword', 'togglePassword3', 'toggleIcon3');

            // Auto-calculate age when date of birth changes
            if (dobInput && ageInput) {
                dobInput.addEventListener('change', function () {
                    if (this.value) {
                        const dob = new Date(this.value);
                        const today = new Date();

                        // Check if date is valid and not in the future
                        if (dob > today) {
                            showErrorModal('Date of birth cannot be in the future.');
                            this.value = '';
                            ageInput.value = '';
                            return;
                        }

                        let age = today.getFullYear() - dob.getFullYear();
                        const monthDiff = today.getMonth() - dob.getMonth();

                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                            age--;
                        }

                        // Ensure age is reasonable (0-120)
                        if (age < 0 || age > 120) {
                            showErrorModal('Please enter a valid date of birth.');
                            this.value = '';
                            ageInput.value = '';
                            return;
                        }

                        ageInput.value = age;
                    } else {
                        ageInput.value = '';
                    }
                });
            }

            // Password validation functions
            function validatePasswordLength(password) {
                return password.length >= 6;
            }

            function validatePasswordMatch(password, confirmPassword) {
                return password === confirmPassword;
            }

            function validateDateOfBirth(dob) {
                if (!dob) return false;

                const dobDate = new Date(dob);
                const today = new Date();

                // Check if date is in the future
                if (dobDate > today) {
                    return { valid: false, message: 'Date of birth cannot be in the future.' };
                }

                // Calculate age
                let age = today.getFullYear() - dobDate.getFullYear();
                const monthDiff = today.getMonth() - dobDate.getMonth();

                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
                    age--;
                }

                // Check reasonable age range
                if (age < 0 || age > 120) {
                    return { valid: false, message: 'Please enter a valid date of birth.' };
                }

                return { valid: true, age: age };
            }

            // Form submission validation
            if (signupForm) {
                signupForm.addEventListener('submit', function (e) {
                    const password = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    const dob = dobInput.value;

                    // Validate password length
                    if (!validatePasswordLength(password)) {
                        e.preventDefault();
                        showErrorModal('Password must be at least 6 characters long.');
                        return false;
                    }

                    // Validate password match
                    if (!validatePasswordMatch(password, confirmPassword)) {
                        e.preventDefault();
                        showErrorModal('Passwords do not match.');
                        return false;
                    }

                    // Validate date of birth
                    const dobValidation = validateDateOfBirth(dob);
                    if (!dobValidation.valid) {
                        e.preventDefault();
                        showErrorModal(dobValidation.message);
                        return false;
                    }

                    // Update age field with calculated age before submission
                    ageInput.value = dobValidation.age;

                    // If all validations pass, form will be submitted normally
                    return true;
                });
            }

            // Real-time password validation with visual feedback
            if (passwordInput && confirmPasswordInput) {
                function updatePasswordValidation() {
                    const password = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    const mismatchError = document.getElementById('passwordMismatchError');

                    // Remove previous validation classes
                    passwordInput.classList.remove('is-invalid', 'is-valid');
                    confirmPasswordInput.classList.remove('is-invalid', 'is-valid');

                    // Validate password length
                    if (password.length > 0) {
                        if (validatePasswordLength(password)) {
                            passwordInput.classList.add('is-valid');
                        } else {
                            passwordInput.classList.add('is-invalid');
                        }
                    }

                    // Validate password match and show/hide error div
                    if (confirmPassword.length > 0) {
                        if (validatePasswordMatch(password, confirmPassword)) {
                            confirmPasswordInput.classList.add('is-valid');
                            // Hide mismatch error
                            if (mismatchError) {
                                mismatchError.style.display = 'none';
                            }
                        } else {
                            confirmPasswordInput.classList.add('is-invalid');
                            // Show mismatch error
                            if (mismatchError) {
                                mismatchError.style.display = 'block';
                            }
                        }
                    } else {
                        // Hide error when confirm password is empty
                        if (mismatchError) {
                            mismatchError.style.display = 'none';
                        }
                    }
                }

                passwordInput.addEventListener('input', updatePasswordValidation);
                confirmPasswordInput.addEventListener('input', updatePasswordValidation);
            }

            // Real-time date validation with visual feedback
            if (dobInput) {
                dobInput.addEventListener('input', function () {
                    const dob = this.value;

                    // Remove previous validation classes
                    this.classList.remove('is-invalid', 'is-valid');

                    if (dob) {
                        const dobValidation = validateDateOfBirth(dob);
                        if (dobValidation.valid) {
                            this.classList.add('is-valid');
                            ageInput.value = dobValidation.age;
                        } else {
                            this.classList.add('is-invalid');
                            ageInput.value = '';
                        }
                    } else {
                        ageInput.value = '';
                    }
                });
            }
        });

    </script>

</body>

</html>