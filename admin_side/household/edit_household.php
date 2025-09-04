<?php
// ✅ Set session configuration BEFORE session_start()
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

// Initialize admin details
$edit_household = $_GET['id'] ?? null;
$prof = $first_name = $middle_name = $last_name = $dob = $sex = $age = $cellphone = $landline = $email = $password = $street = $street2 = $city = $state = $brgy = $postal = $members = $rfid = $status = '';

if ($edit_household) {
    try {
        $stmt = $conn->prepare("SELECT * FROM household_accounts WHERE household_id = ?");
        $stmt->bind_param("s", $edit_household);
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
            $landline = $admin['landline'];
            $email = $admin['email_address'];
            $password = $admin['password'];
            $street = $admin['street_address'];
            $street2 = $admin['street_address_2'];
            $city = $admin['city'];
            $state = $admin['state_province'];
            $brgy = $admin['barangay'];
            $postal = $admin['postal_zip_code'];
            $members = $admin['members'];
            $rfid = $admin['rfid'];
            $status = $admin['status'];
        } else {
            $error_message = "Resident not found!";
        }
    } catch (Exception $e) {
        $error_message = "Error fetching resident: " . $e->getMessage();
    }
} else {
    $error_message = "Invalid resident ID.";
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
    $street = $_POST['street'];
    $street2 = $_POST['street2'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $barangay = $_POST['barangay'];
    $postal = $_POST['postal'];
    $members = $_POST['members'];
    $rfid = $_POST['rfid'];

    // Handle password fields
    $new_password = trim($_POST['passWord'] ?? '');
    $confirm_password = trim($_POST['confirmPassword'] ?? '');

    // 2. Check if RFID already exists (excluding current household)
    try {
        $rfid_check_stmt = $conn->prepare("SELECT household_id FROM household_accounts WHERE rfid = ? AND household_id != ?");
        $rfid_check_stmt->bind_param("ss", $rfid, $edit_household);
        $rfid_check_stmt->execute();
        $rfid_result = $rfid_check_stmt->get_result();

        if ($rfid_result->num_rows > 0) {
            $error = "RFID card is already registered to another household/visitor. Please use a different RFID card.";
        } else {
            // 3. Check if profile picture was uploaded
            $has_photo = isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK;

            // 4. Check password update requirements
            $update_password = false;
            if (!empty($new_password) || !empty($confirm_password)) {
                if ($new_password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } elseif (strlen($new_password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } else {
                    $update_password = true;
                }
            }

            if (!isset($error)) {
                // Build SQL query based on what needs to be updated
                $sql_parts = [
                    "first_name=?",
                    "middle_name=?",
                    "last_name=?",
                    "date_of_birth=?",
                    "age=?",
                    "sex=?",
                    "cellphone_number=?",
                    "landline=?",
                    "email_address=?",
                    "street_address=?",
                    "street_address_2=?",
                    "city=?",
                    "state_province=?",
                    "barangay=?",
                    "postal_zip_code=?",
                    "members=?",
                    "rfid=?"
                ];

                $bind_types = "ssssissssssssssis";
                $bind_values = [
                    $first_name,
                    $middle_name,
                    $last_name,
                    $dob,
                    $age,
                    $sex,
                    $cellphone,
                    $landline,
                    $email,
                    $street,
                    $street2,
                    $city,
                    $state,
                    $barangay,
                    $postal,
                    $members,
                    $rfid
                ];

                // Add password to update if needed
                if ($update_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql_parts[] = "password=?";
                    $bind_types .= "s";
                    $bind_values[] = $hashed_password;
                }

                // Add profile picture to update if needed
                $profile_pic_data = null;
                if ($has_photo) {
                    $sql_parts[] = "profile_picture=?";
                    $bind_types .= "b";
                    $profile_pic_data = file_get_contents($_FILES['profile_pic']['tmp_name']);
                    $bind_values[] = null; // Placeholder for BLOB data
                }

                // Add household_id for WHERE clause
                $bind_types .= "s";
                $bind_values[] = $edit_household;

                $sql = "UPDATE household_accounts SET " . implode(", ", $sql_parts) . " WHERE household_id=?";

                $stmt = $conn->prepare($sql);

                if ($has_photo) {
                    // Handle BLOB data with send_long_data for profile picture
                    $profile_pic_index = count($bind_values) - 2; // -2 because household_id is last

                    // Create refs array for bind_param
                    $bind_refs = [];
                    for ($i = 0; $i < count($bind_values); $i++) {
                        if ($i === $profile_pic_index) {
                            $bind_refs[] = null; // Will be set with send_long_data
                        } else {
                            $bind_refs[] = &$bind_values[$i];
                        }
                    }

                    // Bind parameters
                    $stmt->bind_param($bind_types, ...$bind_refs);

                    // Send BLOB data
                    $stmt->send_long_data($profile_pic_index, $profile_pic_data);
                } else {
                    // Regular bind for all parameters
                    $stmt->bind_param($bind_types, ...$bind_values);
                }

                // 5. Execute and check success
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Update failed: " . $stmt->error;
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error updating record: " . $e->getMessage();
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
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav flex-column gap-1">
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
                            <li><a href="../entry_logs.php" class="nav-link px-2">Entry Logs</a></li>
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
                            <li><a href="../invoice.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="../login/logout.php"
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
                    <h5 class="mb-0 fw-bold">Household Account Management</h5>
                </div>
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0">Edit User Details</span>
                    <a href="../household_accounts.php"
                        class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="my-0">
                <div class="p-3">
                    <form action="edit_household.php?id=<?= $edit_household ?>" id="householdForm" method="POST"
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
                                <input type="date" name="dob" class="form-control"
                                    value="<?php echo htmlspecialchars($dob) ?>" required />
                                <label class="form-label mt-2">Date of Birth</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="age" class="form-control"
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
                                <input type="text" name="landline" class="form-control"
                                    value="<?php echo htmlspecialchars($landline) ?>" />
                                <label class="form-label mt-2">Landline</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="email" name="email" class="form-control"
                                    value="<?php echo htmlspecialchars($email) ?>" required />
                                <label class="form-label mt-2">Email Address</label>
                            </div>
                        </div>
                        <!-- Address -->
                        <span class="fw-bold mb-3">Address</span>
                        <div class="my-3">
                            <input type="text" name="street" class="form-control"
                                value="<?php echo htmlspecialchars($street) ?>" required />
                            <label class="form-label mt-2">Street Address</label>
                        </div>
                        <div class="mb-3">
                            <input type="text" name="street2" class="form-control"
                                value="<?php echo htmlspecialchars($street2) ?>" />
                            <label class="form-label mt-2">Street Address Line 2</label>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <input type="text" name="city" class="form-control"
                                    value="<?php echo htmlspecialchars($city) ?>" required />
                                <label class="form-label mt-2">City</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="state" class="form-control"
                                    value="<?php echo htmlspecialchars($state) ?>" required />
                                <label class="form-label mt-2">State/Province</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="barangay" class="form-control"
                                    value="<?php echo htmlspecialchars($brgy) ?>" required />
                                <label class="form-label mt-2">Barangay</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="postal" class="form-control"
                                    value="<?php echo htmlspecialchars($postal) ?>" required />
                                <label class="form-label mt-2">Postal/Zip Code</label>
                            </div>
                        </div>
                        <div class="row">
                            <!-- Household Members -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label mt-2 fw-bold">Household Members</label>
                                <input type="number" name="members" class="form-control" min="1"
                                    value="<?= number_format($members) ?>" required />
                                <label class="form-label mt-2">How many members in the household</label>
                            </div>
                            <!-- Resident RFID -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label mt-2 fw-bold">Resident RFID</label>
                                <input type="text" name="rfid" class="form-control" id="rfidInput"
                                    value="<?php echo htmlspecialchars($rfid) ?>" required />
                                <label class="form-label mt-2">Tap your RFID card</label>
                            </div>
                        </div>
                        <!-- Account Password -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label mt-2 fw-bold">New Password</label>
                                <div class="input-group">
                                    <input type="password" id="passWord" name="passWord" class="form-control"
                                        minlength="6" />
                                    <button type="button" class="btn btn-outline-secondary" id="togglePassword1"
                                        tabindex="-1">
                                        <i class="bi bi-eye" id="toggleIcon1"></i>
                                    </button>
                                </div>
                                <label class="form-label mt-2">Enter new password to change (min. 6 characters)</label>
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
                                <label class="form-label mt-2">Confirm new password</label>
                                <div id="passwordError" class="invalid-feedback"></div>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rfidInput = document.getElementById('rfidInput');
            const form = document.getElementById('householdForm');
            const passwordInput = document.getElementById('passWord');
            const confirmPasswordInput = document.getElementById('confirmPassword');

            function toggleRequired() {
                if (passwordInput.value.length > 0) {
                    confirmPasswordInput.required = true;
                } else {
                    confirmPasswordInput.required = false;
                }

                if (confirmPasswordInput.value.length > 0) {
                    passwordInput.required = true;
                } else {
                    passwordInput.required = false;
                }
            }

            passwordInput.addEventListener('input', toggleRequired);
            confirmPasswordInput.addEventListener('input', toggleRequired);

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
            setupPasswordToggle('passWord', 'togglePassword1', 'toggleIcon1');
            setupPasswordToggle('confirmPassword', 'togglePassword2', 'toggleIcon2');

            // Password Matching Validation
            function validatePasswords() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                let passwordError = document.getElementById('passwordError');

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
                // Check RFID field
                if (!rfidInput.value.trim()) {
                    event.preventDefault();
                    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    document.getElementById('errorMessage').textContent = 'RFID is required. Please tap your RFID card.';
                    errorModal.show();
                    return false;
                }

                if (!validatePasswords()) {
                    event.preventDefault();
                    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    document.getElementById('errorMessage').textContent = 'Passwords do not match. Please ensure both password fields are identical.';
                    errorModal.show();
                    return false;
                }

                // Additional validation for password strength
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (password !== '' || confirmPassword !== '') {
                    if (password.length < 6) {
                        event.preventDefault();
                        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                        document.getElementById('errorMessage').textContent = 'Password must be at least 6 characters long.';
                        errorModal.show();
                        return false;
                    }
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
                    const dob = new Date(this.value);
                    const today = new Date();
                    let age = today.getFullYear() - dob.getFullYear();
                    const monthDiff = today.getMonth() - dob.getMonth();

                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                        age--;
                    }

                    ageInput.value = age;
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
                            alert('File size too large. Please select an image smaller than 5MB.');
                            this.value = ''; // Clear the input
                            preview.innerHTML = '<i class="bi bi-person-fill" style="font-size: 48px;"></i>';
                            return;
                        }

                        // Validate file type
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Invalid file type. Please select a JPEG, PNG, or GIF image.');
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
        });
    </script>
</body>

</html>