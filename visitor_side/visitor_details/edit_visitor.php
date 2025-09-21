<?php
// ✅ FIX: Set session configuration BEFORE session_start()
ini_set('session.gc_maxlifetime', 7200); // 2 hours
ini_set('session.cookie_lifetime', 7200); // 2 hours

// Set session cookie parameters before starting session
session_set_cookie_params([
    'lifetime' => 7200, // 2 hours
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']), // Use secure cookies on HTTPS
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);

// NOW start the session
session_start();

require '../../rfid-api/db.php';

// Check if user is logged in
if (!isset($_SESSION['visitor_id'])) {
    header("Location: ../login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

$visitor_id = $_SESSION['visitor_id'];
$sql = "SELECT * FROM visitor_details WHERE visitor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $visitor_id);
$stmt->execute();
$result = $stmt->get_result();
$visitor = $result->fetch_assoc();

if (!$visitor) {
    echo "Visitor not found.";
    exit;
}

// Initialize user details
$username = $visitor['first_name']; // <- Set username directly from household query
$photo = ''; // Initialize photo; your existing profile photo block will set this later
// Only set $photo if profile_pic exists and is not null
if (!empty($visitor['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($visitor['profile_picture']);
} else {
    $photo = ''; // Explicitly empty if no image is saved
}

// Initialize admin details
$edit_visitor = $_GET['id'] ?? null;
$prof = $first_name = $middle_name = $last_name = $dob = $sex = $age = $cellphone = $email = $employement = $rfid = $reason = '';

if ($edit_visitor) {
    try {
        $stmt = $conn->prepare("SELECT * FROM visitor_details WHERE visitor_id = ?");
        $stmt->bind_param("s", $edit_visitor);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin) {
            $prof = !empty($admin['profile_picture']) ? 'data:image/jpeg;base64,' . base64_encode($admin['profile_picture']) : '';
            $first_name = $admin['first_name'];
            $middle_name = $admin['middle_name'];
            $last_name = $admin['last_name'];
            $dob = $admin['date_of_birth'];
            $sex = $admin['sex'];
            $age = $admin['age'];
            $cellphone = $admin['cellphone_number'];
            $email = $admin['email_address'];
            $employement = $admin['employed_in_subdivision'];
            $reason = $admin['reason_for_visit'];
            $rfid = $admin['rfid'];
        } else {
            $error_message = "Visitor not found!";
        }
    } catch (Exception $e) {
        $error_message = "Error fetching admin: " . $e->getMessage();
    }
} else {
    $error_message = "Invalid Visitor ID.";
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
    $email = $_POST['email'];
    $employment_status = $_POST['employment_status']; // Yes/No
    $reason = $_POST['reason'];
    $rfid = $_POST['rfid'];

    try {
        // 2. Check if RFID already exists in both tables (excluding current visitor)
        $rfid_exists = false;
        $duplicate_source = "";

        // Check visitor_details table (excluding current visitor)
        $visitor_rfid_check_stmt = $conn->prepare("SELECT visitor_id FROM visitor_details WHERE rfid = ? AND visitor_id != ?");
        $visitor_rfid_check_stmt->bind_param("ss", $rfid, $edit_visitor);
        $visitor_rfid_check_stmt->execute();
        $visitor_rfid_result = $visitor_rfid_check_stmt->get_result();

        if ($visitor_rfid_result->num_rows > 0) {
            $rfid_exists = true;
            $duplicate_source = "visitor";
        }

        // Check household_accounts table if no duplicate found in visitor_details
        if (!$rfid_exists) {
            $household_rfid_check_stmt = $conn->prepare("SELECT household_id FROM household_accounts WHERE rfid = ?");
            $household_rfid_check_stmt->bind_param("s", $rfid);
            $household_rfid_check_stmt->execute();
            $household_rfid_result = $household_rfid_check_stmt->get_result();

            if ($household_rfid_result->num_rows > 0) {
                $rfid_exists = true;
                $duplicate_source = "household";
            }
        }

        if ($rfid_exists) {
            $error = "RFID card is already registered to another " . $duplicate_source . ". Please use a different RFID card.";
        } else {
            // 3. Handle password updates
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirmPassword'] ?? '';
            $update_password = false;
            $hashed_password = '';

            // Check if password fields have values
            if (!empty($password) || !empty($confirmPassword)) {
                // If either field has a value, both are required
                if (empty($password)) {
                    $error = "Password cannot be empty when updating password.";
                } elseif (empty($confirmPassword)) {
                    $error = "Please confirm your password.";
                } elseif ($password !== $confirmPassword) {
                    $error = "Passwords do not match.";
                } elseif (strlen($password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } else {
                    // Hash the new password
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $update_password = true;
                }
            }

            // Only proceed with database update if no password errors
            if (!isset($error)) {
                // 4. Check if profile picture was uploaded
                $has_photo = isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK;

                if ($has_photo) {
                    // Validate file size (5MB limit)
                    if ($_FILES['profile_pic']['size'] > 5000000) {
                        $error = "File size too large. Please select an image smaller than 5MB.";
                    } else {
                        // Validate file type
                        $file_type = $_FILES['profile_pic']['type'];
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        if (!in_array($file_type, $allowed_types)) {
                            $error = "Invalid file type. Please select a JPEG, PNG, or GIF image.";
                        } else {
                            $profile_pic = file_get_contents($_FILES['profile_pic']['tmp_name']);
                        }
                    }
                }

                // Proceed with database update if no file errors
                if (!isset($error)) {
                    if ($has_photo && $update_password) {
                        // Update with photo and password
                        $sql = "UPDATE visitor_details SET 
                        first_name=?, middle_name=?, last_name=?, date_of_birth=?, age=?, sex=?, 
                        cellphone_number=?, email_address=?, employed_in_subdivision=?, reason_for_visit=?, rfid=?, password=?, profile_picture=?
                        WHERE visitor_id=?";

                        $stmt = $conn->prepare($sql);
                        $null_blob = null;
                        $stmt->bind_param(
                            "ssssisssssssbs",
                            $first_name,
                            $middle_name,
                            $last_name,
                            $dob,
                            $age,
                            $sex,
                            $cellphone,
                            $email,
                            $employment_status,
                            $reason,
                            $rfid,
                            $hashed_password,
                            $null_blob,
                            $edit_visitor
                        );
                        $stmt->send_long_data(12, $profile_pic); // index 12 = profile_picture

                    } elseif ($has_photo && !$update_password) {
                        // Update with photo only
                        $sql = "UPDATE visitor_details SET 
                        first_name=?, middle_name=?, last_name=?, date_of_birth=?, age=?, sex=?, 
                        cellphone_number=?, email_address=?, employed_in_subdivision=?, reason_for_visit=?, rfid=?, profile_picture=?
                        WHERE visitor_id=?";

                        $stmt = $conn->prepare($sql);
                        $null_blob = null;
                        $stmt->bind_param(
                            "ssssissssssbs",
                            $first_name,
                            $middle_name,
                            $last_name,
                            $dob,
                            $age,
                            $sex,
                            $cellphone,
                            $email,
                            $employment_status,
                            $reason,
                            $rfid,
                            $null_blob,
                            $edit_visitor
                        );
                        $stmt->send_long_data(11, $profile_pic); // index 11 = profile_picture

                    } elseif (!$has_photo && $update_password) {
                        // Update with password only
                        $sql = "UPDATE visitor_details SET 
                        first_name=?, middle_name=?, last_name=?, date_of_birth=?, age=?, sex=?, 
                        cellphone_number=?, email_address=?, employed_in_subdivision=?, reason_for_visit=?, rfid=?, password=?
                        WHERE visitor_id=?";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param(
                            "ssssissssssss",
                            $first_name,
                            $middle_name,
                            $last_name,
                            $dob,
                            $age,
                            $sex,
                            $cellphone,
                            $email,
                            $employment_status,
                            $reason,
                            $rfid,
                            $hashed_password,
                            $edit_visitor
                        );

                    } else {
                        // Update without photo and without password
                        $sql = "UPDATE visitor_details SET 
                        first_name=?, middle_name=?, last_name=?, date_of_birth=?, age=?, sex=?, 
                        cellphone_number=?, email_address=?, employed_in_subdivision=?, reason_for_visit=?, rfid=?
                        WHERE visitor_id=?";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param(
                            "ssssisssssss",
                            $first_name,
                            $middle_name,
                            $last_name,
                            $dob,
                            $age,
                            $sex,
                            $cellphone,
                            $email,
                            $employment_status,
                            $reason,
                            $rfid,
                            $edit_visitor
                        );
                    }

                    // 5. Execute and check success
                    if ($stmt->execute()) {
                        $success = true;
                    } else {
                        $error = "Update failed: " . $stmt->error;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error processing request: " . $e->getMessage();
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
        }

        header {
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 20;
            left: 0;
            background-color: #1F2937;
            overflow-y: auto;
        }

        main {
            margin-left: 250px;
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
        <div class="me-4" style="width: 250px;">
            <img src="../../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">ACCOUNTS</h1>
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2 dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown"
                    aria-expanded="false" role="button" style="cursor: pointer;">
                    <span>Hello, <?php echo htmlspecialchars($username); ?></span>
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
                    <li><a class="dropdown-item" href="view_visitor.php?id=<?php echo $visitor_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="../logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav d-flex flex-column gap-1">
                <a href="../dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <a href="../amenity_booking.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-book me-2"></i> Amenity Booking
                </a>
                <!-- Accounting -->
                <div>
                    <button
                        class="btn btn-toggle collapsed px-3 rounded py-2 d-flex align-items-center justify-content-start"
                        data-bs-toggle="collapse" data-bs-target="#acctCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../#" class="nav-link px-2">Payments</a></li>
                            <li><a href="../#" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="../logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout"
                    style="position: fixed; bottom: 0; width: 220px;">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Visitor Account Management</h5>
                </div>
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0">Edit User Details</span>
                    <button onclick="history.back()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </button>
                </div>
                <hr class="my-0">
                <div class="p-3">
                    <form action="edit_visitor.php?id=<?= $visitor_id ?>" id="visitorForm" method="POST"
                        enctype="multipart/form-data">
                        <div class="row mb-3">
                            <label for="profile_pic" class="form-label fw-bold">Profile Picture</label>
                            <div class="row">
                                <div class="col-4 mb-3">
                                    <div id="preview"
                                        class="d-flex align-items-center justify-content-center overflow-hidden rounded"
                                        style="height: 120px; width: 120px; border: 2px dashed #ccc; color: #aaa;">
                                        <?php if (!empty($prof)): ?>
                                            <img src="<?php echo htmlspecialchars($prof) ?>"
                                                style="width: 100px; height: 100px; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="bi bi-person-fill" style="font-size: 48px;"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4">
                                    <input type="file" class="form-control" name="profile_pic" id="profile_pic"
                                        accept="image/*" />
                                </div>
                            </div>
                        </div>
                        <!-- Personal Info -->
                        <div class="row">
                            <span class="fw-bold mb-3">Personal Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="first_name" class="form-control"
                                    value="<?php echo htmlspecialchars($first_name) ?>" required />
                                <label class="form-label mt-2">First Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="middle_name" class="form-control"
                                    value="<?php echo htmlspecialchars($middle_name) ?>" required />
                                <label class="form-label mt-2">Middle Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="last_name" class="form-control"
                                    value="<?php echo htmlspecialchars($last_name) ?>" required />
                                <label class="form-label mt-2">Last Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="date" name="dob" class="form-control" id="dobInput"
                                    value="<?php echo htmlspecialchars($dob) ?>" required
                                    max="<?php echo date('Y-m-d'); ?>" />
                                <label class="form-label mt-2">Date of Birth</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="number" name="age" class="form-control"
                                    value="<?php echo htmlspecialchars($age) ?>" readonly />
                                <label class="form-label mt-2">Age</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <select name="sex" class="form-select" required>
                                    <option value="">Select</option>
                                    <option value="Male" <?= ($sex == 'Male') ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($sex == 'Female') ? 'selected' : '' ?>>Female</option>
                                </select>
                                <label class="form-label mt-2">Sex</label>
                            </div>
                        </div>
                        <!-- Contact -->
                        <div class="row">
                            <span class="fw-bold mb-3">Contact Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="cellphone" class="form-control"
                                    value="<?php echo htmlspecialchars($cellphone) ?>" />
                                <label class="form-label mt-2">Cellphone Number</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="email" name="email" class="form-control"
                                    value="<?php echo htmlspecialchars($email) ?>" required />
                                <label class="form-label mt-2">Email Address</label>
                            </div>
                        </div>
                        <!-- Reason for Visit -->
                        <span class="fw-bold mb-3">Reason for Visit</span>
                        <div class="my-3">
                            <span>Are you employed by the subdivision?</span>
                        </div>
                        <!-- Radio Buttons -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="form-check">
                                    <label class="form-check-label me-2" for="noRadio1">No</label>
                                    <input class="form-check-input" type="radio" name="employment_status" id="noRadio1"
                                        value="No" <?= ($employement == 'No') ? 'checked' : '' ?> required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check">
                                    <label class="form-check-label me-2" for="yesRadio2">Yes</label>
                                    <input class="form-check-input" type="radio" name="employment_status" id="yesRadio2"
                                        value="Yes" <?= ($employement == 'Yes') ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <!-- Dropdowns for Reason -->
                        <div class="row">
                            <!-- No Dropdown -->
                            <div class="col-md-4 mb-3" id="dropdownNo">
                                <select id="reasonNo" name="reason" class="form-select">
                                    <option disabled <?= ($employement == 'No' && $reason == '') || (empty($employement) || ($employement != 'No' && $employement != 'Yes')) ? 'selected' : '' ?> value="">
                                        Select a reason</option>
                                    <option <?= ($employement == 'No' && $reason == 'Personal Visit / Family Gathering') ? 'selected' : '' ?>>Personal Visit / Family Gathering</option>
                                    <option <?= ($employement == 'No' && $reason == 'Delivery or Pickup') ? 'selected' : '' ?>>Delivery or Pickup</option>
                                    <option <?= ($employement == 'No' && $reason == 'Health or Emergency Services') ? 'selected' : '' ?>>Health or Emergency Services</option>
                                    <option <?= ($employement == 'No' && $reason == 'Religious or Community Outreach') ? 'selected' : '' ?>>Religious or Community Outreach</option>
                                    <option <?= ($employement == 'No' && $reason == 'Transport Services') ? 'selected' : '' ?>>Transport Services</option>
                                    <option <?= ($employement == 'No' && $reason == 'Guest Use of Amenities') ? 'selected' : '' ?>>Guest Use of Amenities</option>
                                    <option <?= ($employement == 'No' && $reason == 'Home Maintenance and Repairs') ? 'selected' : '' ?>>Home Maintenance and Repairs</option>
                                    <option <?= ($employement == 'No' && $reason == 'Construction or Renovation') ? 'selected' : '' ?>>Construction or Renovation</option>
                                    <option <?= ($employement == 'No' && $reason == 'Landscaping and Gardening') ? 'selected' : '' ?>>Landscaping and Gardening</option>
                                    <option <?= ($employement == 'No' && $reason == 'Household Help') ? 'selected' : '' ?>>
                                        Household Help</option>
                                    <option <?= ($employement == 'No' && $reason == 'Pest Control / Cleaning Services') ? 'selected' : '' ?>>Pest Control / Cleaning Services</option>
                                    <option <?= ($employement == 'No' && $reason == 'Internet / Cable / Utility Installation') ? 'selected' : '' ?>>Internet / Cable / Utility Installation
                                    </option>
                                    <option <?= ($employement == 'No' && $reason == 'Furniture or Appliance Delivery') ? 'selected' : '' ?>>Furniture or Appliance Delivery</option>
                                    <option <?= ($employement == 'No' && $reason == 'Server Contractors') ? 'selected' : '' ?>>Server Contractors</option>
                                </select>
                            </div>
                            <!-- Yes Dropdown -->
                            <div class="col-md-4 mb-3" id="dropdownYes">
                                <select id="reasonYes" name="reason" class="form-select">
                                    <option disabled <?= ($employement == 'Yes' && $reason == '') || (empty($employement) || ($employement != 'No' && $employement != 'Yes')) ? 'selected' : '' ?> value="">
                                        Select a reason</option>
                                    <option <?= ($employement == 'Yes' && $reason == 'Administrative Work') ? 'selected' : '' ?>>Administrative Work</option>
                                    <option <?= ($employement == 'Yes' && $reason == 'Facilities Management') ? 'selected' : '' ?>>Facilities Management</option>
                                    <option <?= ($employement == 'Yes' && $reason == 'IT and System Maintenance') ? 'selected' : '' ?>>IT and System Maintenance</option>
                                    <option <?= ($employement == 'Yes' && $reason == 'Security Oversight') ? 'selected' : '' ?>>Security Oversight</option>
                                </select>
                            </div>
                        </div>
                        <!-- Visitor RFID -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label mt-2 fw-bold">Visitor RFID</label>
                                <input type="text" name="rfid" id="rfidInput" class="form-control"
                                    value="<?php echo htmlspecialchars($rfid) ?>" required />
                                <label class="form-label mt-2">Tap your RFID card</label>
                            </div>
                        </div>
                        <!-- Account Password -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label mt-2 fw-bold">New Password</label>
                                <div class="input-group">
                                    <input type="password" id="password" name="password" class="form-control"
                                        minlength="6" />
                                    <button type="button" class="btn btn-outline-secondary" id="togglePassword1"
                                        tabindex="-1">
                                        <i class="bi bi-eye" id="toggleIcon1"></i>
                                    </button>
                                </div>
                                <label class="form-label mt-2">Set a password for this account (min. 6
                                    characters)</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label mt-2 fw-bold invisible">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" id="confirmPassword" name="confirmPassword"
                                        class="form-control" minlength="6" />
                                    <button type="button" class="btn btn-outline-secondary" id="togglePassword2"
                                        tabindex="-1">
                                        <i class="bi bi-eye" id="toggleIcon2"></i>
                                    </button>
                                </div>
                                <label class="form-label mt-2">Confirm password</label>
                                <div id="passwordError" class="invalid-feedback"></div>
                            </div>
                        </div>
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <button onclick="history.back()" class="btn btn-danger">Cancel</button>
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

                                const redirect = () => window.location.href = 'view_visitor.php?id=<?php echo htmlspecialchars($visitor_id) ?>';
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');

            // Function to show error modal
            function showErrorModal(message) {
                const errorMessage = document.getElementById('errorMessage');
                if (errorMessage) {
                    errorMessage.textContent = message;
                }
                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
            }

            // ====== PASSWORD VALIDATION ======
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');

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

            // Password validation function
            function validatePasswords() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const passwordError = document.getElementById('passwordError');

                // Remove existing error styling
                passwordInput.classList.remove('is-invalid');
                confirmPasswordInput.classList.remove('is-invalid');
                if (passwordError) {
                    passwordError.textContent = '';
                }

                // Only validate if both fields have values
                if (password && confirmPassword) {
                    if (password !== confirmPassword) {
                        // Add error styling
                        confirmPasswordInput.classList.add('is-invalid');
                        passwordError.textContent = 'Passwords do not match';
                        passwordError.style.display = 'block';
                        return false;
                    }
                    if (password.length < 6) {
                        passwordInput.classList.add('is-invalid');
                        return false;
                    }
                }
                return true;
            }

            // Real-time password validation
            if (passwordInput && confirmPasswordInput) {
                passwordInput.addEventListener('input', validatePasswords);
                confirmPasswordInput.addEventListener('input', function () {
                    if (passwordInput.value !== '') {
                        validatePasswords();
                    }
                });
            }

            // ====== RFID INPUT HANDLING ======
            const rfidInput = document.getElementById('rfidInput');
            if (rfidInput) {
                // Prevent RFID input from submitting the form
                rfidInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.keyCode === 13) {
                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();

                        // Blur the input to remove focus after RFID scan
                        this.blur();

                        // Confirmation log
                        console.log('RFID captured:', this.value);

                        return false;
                    }
                });

                // Additional prevention using keypress
                rfidInput.addEventListener('keypress', function (event) {
                    if (event.key === 'Enter' || event.keyCode === 13) {
                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();
                        return false;
                    }
                });

                // Prevent any form submission triggered by the RFID input
                rfidInput.addEventListener('input', function () {
                    if (this.value.length > 0) {
                        clearTimeout(window.rfidSubmitTimeout);
                    }
                });
            }

            // ====== AUTO-CALCULATE AGE FROM DOB ======
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

            // ====== PROFILE PICTURE PREVIEW ======
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
                            preview.innerHTML = `<img src="${e.target.result}" style="width: 100px; height: 100px; object-fit: cover;">`;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        preview.innerHTML = '<i class="bi bi-person-fill"></i>';
                    }
                });
            }

            // ====== RADIO + DROPDOWN SYNC ======
            const reasonNo = document.getElementById('reasonNo');
            const reasonYes = document.getElementById('reasonYes');
            const radioNo = document.getElementById('noRadio1');
            const radioYes = document.getElementById('yesRadio2');

            // INITIALIZE DROPDOWNS ON PAGE LOAD BASED ON SELECTED RADIO BUTTON
            function initializeDropdowns() {
                if (radioNo && radioYes && reasonNo && reasonYes) {
                    if (radioNo.checked) {
                        // If "No" is selected, clear the "Yes" dropdown
                        reasonYes.selectedIndex = 0;
                        console.log('Initialized: No radio selected, cleared Yes dropdown');
                    } else if (radioYes.checked) {
                        // If "Yes" is selected, clear the "No" dropdown
                        reasonNo.selectedIndex = 0;
                        console.log('Initialized: Yes radio selected, cleared No dropdown');
                    }
                }
            }

            // Call initialization function on page load
            initializeDropdowns();

            if (reasonNo && reasonYes && radioNo && radioYes) {
                // When user selects from "No" dropdown, check "No" radio and clear "Yes" dropdown
                reasonNo.addEventListener('change', () => {
                    if (reasonNo.value !== '') {
                        radioNo.checked = true;
                        reasonYes.selectedIndex = 0;
                        console.log('No dropdown changed, cleared Yes dropdown');
                    }
                });

                // When user selects from "Yes" dropdown, check "Yes" radio and clear "No" dropdown
                reasonYes.addEventListener('change', () => {
                    if (reasonYes.value !== '') {
                        radioYes.checked = true;
                        reasonNo.selectedIndex = 0;
                        console.log('Yes dropdown changed, cleared No dropdown');
                    }
                });

                // When user clicks "No" radio, clear "Yes" dropdown
                radioNo.addEventListener('change', () => {
                    if (radioNo.checked) {
                        reasonYes.selectedIndex = 0;
                        console.log('No radio selected, cleared Yes dropdown');
                    }
                });

                // When user clicks "Yes" radio, clear "No" dropdown
                radioYes.addEventListener('change', () => {
                    if (radioYes.checked) {
                        reasonNo.selectedIndex = 0;
                        console.log('Yes radio selected, cleared No dropdown');
                    }
                });
            }

            // ====== FINAL VALIDATION ON SUBMIT ======
            if (form) {
                form.addEventListener('submit', function (e) {
                    // Validate date of birth
                    const dobInput = document.querySelector('input[name="dob"]');
                    if (!dobInput.value) {
                        e.preventDefault();
                        showErrorModal('Please select a date of birth.');
                        dobInput.focus();
                        return false;
                    }

                    // Check if date is not in the future
                    const selectedDate = new Date(dobInput.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0); // Reset time for accurate comparison

                    if (selectedDate > today) {
                        e.preventDefault();
                        showErrorModal('Date of birth cannot be in the future.');
                        dobInput.focus();
                        return false;
                    }

                    const isNo = radioNo && radioNo.checked;
                    const isYes = radioYes && radioYes.checked;
                    const reasonNoSelected = reasonNo && reasonNo.value !== '';
                    const reasonYesSelected = reasonYes && reasonYes.value !== '';

                    let valid = true;

                    if (!isNo && !isYes) {
                        showErrorModal("Please select if you're employed by the subdivision.");
                        e.preventDefault();
                        valid = false;
                        return false;
                    } else if (isNo && !reasonNoSelected) {
                        showErrorModal("Please select a reason under 'No'.");
                        e.preventDefault();
                        valid = false;
                        return false;
                    } else if (isYes && !reasonYesSelected) {
                        showErrorModal("Please select a reason under 'Yes'.");
                        e.preventDefault();
                        valid = false;
                        return false;
                    }

                    // Validate passwords only if they're filled
                    const password = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;

                    if (password || confirmPassword) {
                        if (password === '') {
                            showErrorModal('Password cannot be empty when updating password.');
                            e.preventDefault();
                            return false;
                        }
                        if (confirmPassword === '') {
                            showErrorModal('Please confirm your password.');
                            e.preventDefault();
                            return false;
                        }
                        if (!validatePasswords()) {
                            showErrorModal('Passwords do not match. Please ensure both password fields are identical.');
                            e.preventDefault();
                            return false;
                        }
                        if (password.length < 6) {
                            showErrorModal('Password must be at least 6 characters long.');
                            e.preventDefault();
                            return false;
                        }
                    }

                    if (valid) {
                        console.log('Form is being submitted via Save button');
                    }
                });
            }
        });

    </script>

</body>

</html>