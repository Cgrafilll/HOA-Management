<?php
// ✅ Set session configuration BEFORE session_start()
ini_set('session.gc_maxlifetime', 7200); // 2 hours
ini_set('session.cookie_lifetime', 7200); // 2 hours

// Set session cookie parameters before starting session
session_set_cookie_params(lifetime_or_options: [
    'lifetime' => 7200, // 2 hours
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']), // Use secure cookies on HTTPS
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);

// NOW start the session
session_start();

require '../../rfid-api/db.php'; // Adjust path as needed

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: ../login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

$admin_id = $_SESSION['admin_id'];
$sql = "SELECT * FROM admin_accounts WHERE admin_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    echo "Admin not found.";
    exit;
}

// Initialize user details
$username = $admin['first_name']; // <- Set username directly from household query
$photo = ''; // Initialize photo; your existing profile photo block will set this later
// Only set $photo if profile_pic exists and is not null
if (!empty($admin['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($admin['profile_picture']);
} else {
    $photo = ''; // Explicitly empty if no image is saved
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize and collect input
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $dob = $_POST['dob'];
    $age = $_POST['age'];
    $sex = $_POST['sex'];
    $cellphone = $_POST['cellphone'];
    $landline = $_POST['landline'];
    $email = $_POST['email'];
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $street = $_POST['street'];
    $street2 = $_POST['street2'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $barangay = $_POST['barangay'];
    $postal = $_POST['postal'];
    $members = $_POST['members'];
    $rfid = $_POST['rfid'];

    // Validate and format date of birth
    if (empty($dob)) {
        $error = "Date of birth is required.";
    } else {
        // Validate date format and convert if necessary
        $date_obj = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $dob) {
            $error = "Invalid date format for date of birth.";
        } else {
            // Ensure date is not in the future
            $today = new DateTime();
            if ($date_obj > $today) {
                $error = "Date of birth cannot be in the future.";
            } else {
                $dob = $date_obj->format('Y-m-d'); // Ensure proper format
            }
        }
    }

    // 2. Validate password (only if date validation passed)
    if (!isset($error)) {
        if (empty($password)) {
            $error = "Password cannot be empty.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            try {
                // 3. Check if RFID already exists in both household_accounts and visitor_details tables
                $rfid_exists = false;
                $duplicate_source = "";

                // Check household_accounts table
                $rfid_check_stmt = $conn->prepare("SELECT household_id FROM household_accounts WHERE rfid = ?");
                $rfid_check_stmt->bind_param("s", $rfid);
                $rfid_check_stmt->execute();
                $household_rfid_result = $rfid_check_stmt->get_result();

                if ($household_rfid_result->num_rows > 0) {
                    $rfid_exists = true;
                    $duplicate_source = "household";
                }

                // Check visitor_details table if no duplicate found in household_accounts
                if (!$rfid_exists) {
                    $visitor_rfid_check_stmt = $conn->prepare("SELECT visitor_id FROM visitor_details WHERE rfid = ?");
                    $visitor_rfid_check_stmt->bind_param("s", $rfid);
                    $visitor_rfid_check_stmt->execute();
                    $visitor_rfid_result = $visitor_rfid_check_stmt->get_result();

                    if ($visitor_rfid_result->num_rows > 0) {
                        $rfid_exists = true;
                        $duplicate_source = "visitor";
                    }
                }

                if ($rfid_exists) {
                    $error = "RFID card is already registered to another " . $duplicate_source . ". Please use a different RFID card.";
                } else {
                    // 5. Generate new household_id (HOU-0001, HOU-0002...)
                    $result = $conn->query("SELECT household_id FROM household_accounts ORDER BY household_id DESC LIMIT 1");
                    if ($result && $row = $result->fetch_assoc()) {
                        $last_id = intval(substr($row['household_id'], 4)); // extract numeric part
                        $new_id_number = $last_id + 1;
                    } else {
                        $new_id_number = 1; // first household
                    }
                    $household_id = 'HOU-' . str_pad($new_id_number, 4, '0', STR_PAD_LEFT);
                    // 6. Check if profile picture uploaded
                    $profile_pic = null;
                    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['profile_pic']['tmp_name'];
                        $profile_pic = file_get_contents($file_tmp); // Read image as binary data
                    }

                    // 7. Insert into database with password
                    $sql = "INSERT INTO household_accounts (
                        household_id, first_name, middle_name, last_name, date_of_birth, age, sex,
                        cellphone_number, landline, email_address, password, street_address, street_address_2, city,
                        state_province, barangay, postal_zip_code, members, rfid, profile_picture
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $conn->prepare($sql);

                    // Bind parameters including the hashed password
                    $stmt->bind_param(
                        "sssssisssssssssssssb", // Added 's' for password field
                        $household_id,
                        $first_name,
                        $middle_name,
                        $last_name,
                        $dob,
                        $age,
                        $sex,
                        $cellphone,
                        $landline,
                        $email,
                        $hashed_password, // Added hashed password
                        $street,
                        $street2,
                        $city,
                        $state,
                        $barangay,
                        $postal,
                        $members,
                        $rfid,
                        $profile_pic // binary data
                    );

                    // Send long data for profile picture if it exists
                    if ($profile_pic !== null) {
                        $stmt->send_long_data(18, $profile_pic); // index 18 because it's the 19th parameter
                    }

                    if ($stmt->execute()) {
                        $success = true; // Flag to trigger modal in HTML
                    } else {
                        $error_message = "Error creating household account: " . $stmt->error;
                    }
                }
            } catch (Exception $e) {
                $error = "Error checking RFID: " . $e->getMessage();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NSSHAI HOA Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../../images/SitioSeville_Logo.png" type="image/x-icon">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        * {
            font-family: "Montserrat", sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            overflow-x: hidden;
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: 76px;
            background-color: white;
        }

        .sidebar {
            width: 250px;
            height: calc(100vh - 76px);
            position: fixed;
            top: 76px;
            left: 0;
            background-color: #1F2937;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1020;
            transition: transform 0.3s ease;
        }

        main {
            margin-left: 250px;
            margin-top: 76px;
            min-height: calc(100vh - 76px);
            overflow-y: auto;
            transition: margin-left 0.3s ease;
        }

        .sidebar a,
        .sidebar button {
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar a:hover,
        .sidebar button:hover,
        .collapse ul li a:hover,
        .collapse ul li a.actived {
            color: #80ed99;
        }

        .sidebar .nav-link.active,
        .sidebar .btn-toggle:not(.collapsed),
        .sidebar .btn-toggle.active {
            background-color: #198754;
            border-radius: 0.375rem;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: #1F2937;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: #4B5563;
            border-radius: 3px;
        }

        .sidebar .logout {
            flex-shrink: 0;
            border-top: 1px solid #374151;
            padding-top: 12px;
        }

        .sidebar .btn-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            color: #ffffff;
            background: none;
            border: none;
        }

        .sidebar .btn-toggle i {
            margin-right: 8px;
        }

        .sidebar .btn-toggle::after {
            content: "▼";
            font-size: 10px;
            transition: transform 0.3s;
            margin-left: auto;
        }

        .sidebar .btn-toggle.collapsed::after {
            transform: rotate(0deg);
        }

        .sidebar .btn-toggle:not(.collapsed)::after {
            transform: rotate(180deg);
        }

        .mobile-menu-btn {
            display: none;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 76px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1019;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                top: 76px;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            main {
                margin-left: 0;
            }

            header .logo-container {
                width: auto !important;
            }

            .mobile-menu-btn {
                display: inline-block;
            }

            header h1 {
                font-size: 1rem !important;
            }

            .form-control,
            .form-label,
            .form-select,
            .form-select option,
            .toggle,
            .invalid-feedback,
            main span {
                font-size: 0.85rem;
            }

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }

            .sidebar-overlay {
                top: 0;
            }
        }

        @media (max-width: 576px) {
            header {
                height: auto;
                padding: 0.75rem !important;
            }

            header h1 {
                font-size: 1rem !important;
            }

            main {
                margin-top: 76px;
                padding: 0.75rem !important;
            }

            .sidebar {
                top: 76px;
            }

            .sidebar-overlay {
                top: 0;
            }

            .form-control,
            .form-label,
            .form-select,
            .form-select option,
            .toggle,
            .invalid-feedback,
            main span {
                font-size: 0.75rem;
            }
        }

        #preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <button class="btn btn-success mobile-menu-btn me-2" id="mobileMenuBtn" type="button">
            <i class="bi bi-list"></i>
        </button>
        <div class="me-4 logo-container" style="width: 250px;">
            <img src="../../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">ACCOUNTS</h1>
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2 dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown"
                    aria-expanded="false" role="button" style="cursor: pointer;">
                    <span class="d-none d-md-inline">Hello, <?php echo htmlspecialchars($username); ?></span>
                    <div class="d-flex align-items-center justify-content-center overflow-hidden rounded-5"
                        style="height: 40px; width: 40px; color: #aaa;">
                        <?php if (!empty($photo)): ?>
                            <img src="<?php echo htmlspecialchars($photo); ?>"
                                style="width: 40px; height: 40px; object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle" style="font-size: 32px;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="../admin/view_admin.php?id=<?php echo $admin_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="../login/logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav class="nav flex-column gap-1 sidebar-nav p-3">
                <a href="../admin_dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <!-- Accounts -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#accountsCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-person-lines-fill me-2"></i> Accounts
                        </span>
                    </button>
                    <div class="collapse show" id="accountsCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="../household_accounts.php" class="nav-link px-2 actived">Household</a></li>
                            <li><a href="../visitor_accounts.php" class="nav-link px-2">Visitors</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Record Keeping -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#recordCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-book me-2"></i> Record Keeping
                        </span>
                    </button>
                    <div class="collapse" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="../violation_tracking.php" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="../entry_logs.php" class="nav-link px-2">Gate Logs</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Communication -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#commCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-chat-left-text me-2"></i> Communication
                        </span>
                    </button>
                    <div class="collapse" id="commCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../announcements.php" class="nav-link px-2">Announcements</a></li>
                            <li><a href="../events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="../phonebook.php" class="nav-link px-2">Phone Book</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Accounting -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#acctCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="../billing.php" class="nav-link px-2">Billing</a></li>
                            <li><a href="../invoices.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="../login/logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-grow-1 p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Household Account Management</h5>
                </div>
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0">User Details</span>
                    <a href="../household_accounts.php"
                        class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="my-0">
                <div class="p-3">
                    <form action="add_household.php" method="POST" id="householdForm" enctype="multipart/form-data">
                        <label for="profile_pic" class="form-label fw-bold">Profile Picture</label>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div id="preview"
                                    class="d-flex align-items-center justify-content-center overflow-hidden rounded"
                                    style="height: 120px; width: 120px; border: 2px dashed #ccc; color: #aaa;">
                                    <i class="bi bi-person-fill" style="font-size: 48px;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="file" class="form-control" name="profile_pic" id="profile_pic"
                                    accept="image/*" />
                            </div>
                        </div>
                        <!-- Personal Info -->
                        <div class="row mb-1">
                            <span class="fw-bold mb-2">Personal Information</span>
                            <div class="col-md-4">
                                <input type="text" name="first_name" class="form-control" required />
                                <label class="form-label mt-2">First Name</label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="middle_name" class="form-control" required />
                                <label class="form-label mt-2">Middle Name</label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="last_name" class="form-control" required />
                                <label class="form-label mt-2">Last Name</label>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="date" name="dob" class="form-control" required
                                    max="<?php echo date('Y-m-d'); ?>" />
                                <label class="form-label mt-2">Date of Birth</label>
                            </div>
                            <div class="col-md-4">
                                <input type="number" name="age" class="form-control" readonly />
                                <label class="form-label mt-2">Age</label>
                            </div>
                            <div class="col-md-4">
                                <select name="sex" class="form-select" required>
                                    <option value="">Select</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                </select>
                                <label class="form-label mt-2">Sex</label>
                            </div>
                        </div>
                        <!-- Contact -->
                        <div class="row mb-3">
                            <span class="fw-bold mb-2">Contact Information</span>
                            <div class="col-md-4">
                                <input type="text" name="cellphone" class="form-control" />
                                <label class="form-label mt-2">Cellphone Number</label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="landline" class="form-control" />
                                <label class="form-label mt-2">Landline</label>
                            </div>
                            <div class="col-md-4">
                                <input type="email" name="email" class="form-control" placeholder="example@example.com"
                                    required />
                                <label class="form-label mt-2">Email Address</label>
                            </div>
                        </div>
                        <!-- Address -->
                        <div class="row mb-1">
                            <span class="fw-bold mb-2">Address</span>
                            <div class="col-md-12">
                                <input type="text" name="street" class="form-control" required />
                                <label class="form-label mt-2">Street Address</label>
                            </div>
                            <div class="col-md-12">
                                <input type="text" name="street2" class="form-control" />
                                <label class="form-label mt-2">Street Address Line 2</label>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" name="city" class="form-control" required />
                                <label class="form-label mt-2">City</label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="state" class="form-control" required />
                                <label class="form-label mt-2">State/Province</label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="barangay" class="form-control" required />
                                <label class="form-label mt-2">Barangay</label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="postal" class="form-control" required />
                                <label class="form-label mt-2">Postal/Zip Code</label>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <!-- Household Members -->
                            <div class="col-md-4">
                                <label class="form-label mt-2 fw-bold">Household Members</label>
                                <input type="number" name="members" class="form-control" min="1" required />
                                <label class="form-label mt-2">How many members in the household</label>
                            </div>
                            <!-- Resident RFID -->
                            <div class="col-md-4">
                                <label class="form-label mt-2 fw-bold">Resident RFID</label>
                                <input type="text" name="rfid" class="form-control" id="rfidInput" required />
                                <label class="form-label mt-2">Tap your RFID card</label>
                            </div>
                        </div>
                        <!-- Account Password -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label mb-2 fw-bold">Password</label>
                                <div class="input-group">
                                    <input type="password" id="password" name="password" required class="form-control"
                                        minlength="6" />
                                    <button type="button" class="btn btn-outline-secondary toggle" id="togglePassword1"
                                        tabindex="-1">
                                        <i class="bi bi-eye" id="toggleIcon1"></i>
                                    </button>
                                </div>
                                <label class="form-label mt-2">Set a password for this account (min. 6
                                    characters)</label>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-2 fw-bold">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" id="confirmPassword" name="confirmPassword" required
                                        class="form-control" minlength="6" />
                                    <button type="button" class="btn btn-outline-secondary toggle" id="togglePassword2"
                                        tabindex="-1">
                                        <i class="bi bi-eye" id="toggleIcon2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="../household_accounts.php" class="btn btn-danger">Cancel</a>
                        </div>
                    </form>
                    <!-- Success Modal -->
                    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel"
                        aria-hidden="true">
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
                                    <button type="button" class="btn btn-primary" id="doneButton">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Error Modal -->
                    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel"
                        aria-hidden="true">
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

                                const redirect = () => window.location.href = '../household_accounts.php';
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
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../javascripts/mobileSidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rfidInput = document.getElementById('rfidInput');
            const form = document.getElementById('householdForm');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');

            // Password Toggle Functionality
            function setupPasswordToggle(inputId, toggleButtonId, iconId) {
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
            setupPasswordToggle('password', 'togglePassword1', 'toggleIcon1');
            setupPasswordToggle('confirmPassword', 'togglePassword2', 'toggleIcon2');

            // Password Matching Validation
            function validatePasswords() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const passwordError = document.getElementById('passwordError');

                // Remove existing error styling
                passwordInput.classList.remove('is-invalid');
                confirmPasswordInput.classList.remove('is-invalid');
                if (passwordError) {
                    passwordError.remove();
                }

                if (password !== confirmPassword && confirmPassword !== '') {
                    // Add error styling
                    confirmPasswordInput.classList.add('is-invalid');

                    // Add error message
                    const errorDiv = document.createElement('div');
                    errorDiv.id = 'passwordError';
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'Passwords do not match';
                    confirmPasswordInput.parentNode.appendChild(errorDiv);

                    return false;
                }

                return true;
            }

            // Real-time password validation
            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', validatePasswords);
                passwordInput.addEventListener('input', function () {
                    if (confirmPasswordInput.value !== '') {
                        validatePasswords();
                    }
                });
            }

            // Form submission validation
            form.addEventListener('submit', function (event) {
                // Validate date of birth
                const dobInput = document.querySelector('input[name="dob"]');
                if (!dobInput.value) {
                    event.preventDefault();
                    showErrorModal('Please select a date of birth.');
                    dobInput.focus();
                    return false;
                }

                // Check if date is not in the future
                const selectedDate = new Date(dobInput.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0); // Reset time for accurate comparison

                if (selectedDate > today) {
                    event.preventDefault();
                    showErrorModal('Date of birth cannot be in the future.');
                    dobInput.focus();
                    return false;
                }

                // Check RFID field
                if (!rfidInput.value.trim()) {
                    event.preventDefault();
                    showErrorModal('RFID is required. Please tap your RFID card.');
                    return false;
                }

                if (!validatePasswords()) {
                    event.preventDefault();
                    showErrorModal('Passwords do not match. Please ensure both password fields are identical.');
                    return false;
                }

                // Additional validation for password strength
                const password = passwordInput.value;
                if (password.length < 6) {
                    event.preventDefault();
                    showErrorModal('Password must be at least 6 characters long.');
                    return false;
                }

                console.log('Form is being submitted via Save button');
                console.log('RFID Value:', rfidInput.value);
            });

            // RFID Input Handling - SIMPLIFIED VERSION
            if (rfidInput) {
                // Only prevent Enter key from submitting the form prematurely
                // But still allow the RFID value to be captured
                rfidInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.keyCode === 13) {
                        event.preventDefault(); // Prevent form submission
                        this.blur(); // Remove focus from RFID input
                        console.log('RFID captured:', this.value);

                        // Optional: Add visual feedback that RFID was captured
                        this.style.backgroundColor = '#d4edda'; // Light green background
                        setTimeout(() => {
                            this.style.backgroundColor = ''; // Reset after 1 second
                        }, 1000);

                        return false;
                    }
                });

                // Add visual feedback when RFID is entered
                rfidInput.addEventListener('input', function () {
                    if (this.value.length > 0) {
                        this.classList.add('is-valid');
                        this.classList.remove('is-invalid');
                    } else {
                        this.classList.remove('is-valid');
                    }
                });
            }

            // Auto-calculate age when date of birth changes
            const dobInput = document.querySelector('input[name="dob"]');
            const ageInput = document.querySelector('input[name="age"]');

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

            // Profile picture preview - ENHANCED VERSION
            const profilePicInput = document.getElementById('profile_pic');
            const preview = document.getElementById('preview');

            if (profilePicInput && preview) {
                profilePicInput.addEventListener('change', function (event) {
                    const file = event.target.files[0];
                    if (file) {
                        // Validate file size (5MB limit)
                        if (file.size > 5000000) {
                            showErrorModal('File size too large. Please select an image smaller than 5MB.');
                            this.value = ''; // Clear the input
                            preview.innerHTML = '<i class="bi bi-person-fill" style="font-size: 48px;"></i>';
                            return;
                        }

                        // Validate file type
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(file.type)) {
                            showErrorModal('Invalid file type. Please select a JPEG, PNG, or GIF image.');
                            this.value = ''; // Clear the input
                            preview.innerHTML = '<i class="bi bi-person-fill" style="font-size: 48px;"></i>';
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function (e) {
                            preview.innerHTML = `<img src="${e.target.result}" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px;">`;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        // Reset to default icon if no file selected
                        preview.innerHTML = '<i class="bi bi-person-fill" style="font-size: 48px;"></i>';
                    }
                });
            }
            // Function to show error modal
            function showErrorModal(message) {
                document.getElementById('errorMessage').textContent = message;
                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
            }
        });

    </script>

</body>

</html>