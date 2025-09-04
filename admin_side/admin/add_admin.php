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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize input
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
    $role = $_POST['role'];

    $result = $conn->query("SELECT admin_id FROM admin_accounts ORDER BY admin_id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $last_id = intval(substr($row['admin_id'], 4)); // Get numeric part
        $new_id_number = $last_id + 1;
    } else {
        $new_id_number = 1; // Start from 1 if no records
    }

    $admin_id = 'ADM-' . str_pad($new_id_number, 4, '0', STR_PAD_LEFT);

    // 2. Handle profile picture upload
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $profile_pic = file_get_contents($file_tmp); // Read image as binary data
    }

    // 3. Insert into database
    $sql = "INSERT INTO admin_accounts (
        admin_id, first_name, middle_name, last_name, date_of_birth, age, sex,
        cellphone_number, landline, email_address, street_address, street_address_2, city,
        state_province, barangay, postal_zip_code, roles, profile_picture
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->send_long_data(17, $profile_pic); // index 16 because it's the 17th parameter

    $stmt->bind_param(
        "sssssisssssssssssb", // last one is blob
        $admin_id,
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
        $role,
        $profile_pic // binary data
    );

    if ($stmt->execute()) {
        $success = true; // Flag to trigger modal in HTML
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
                    <li><a class="dropdown-item" href="view_admin.php?id=<?php echo $admin_id; ?>"><i
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
                            <li><a href="../admin_accounts.php" class="nav-link px-2 actived">Admin</a></li>
                            <li><a href="../household_accounts.php" class="nav-link px-2">Household</a></li>
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
                    <h5 class="mb-0 fw-bold">Admin Account Management</h5>
                </div>
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0">User Details</span>
                    <a href="../admin_accounts.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="my-0">
                <div class="p-3">
                    <form action="add_admin.php" method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <label for="profile_pic" class="form-label fw-bold">Profile Picture</label>
                            <div class="row">
                                <div class="col-4 mb-3">
                                    <div id="preview"
                                        class="d-flex align-items-center justify-content-center overflow-hidden rounded"
                                        style="height: 120px; width: 120px; border: 2px dashed #ccc; color: #aaa;">
                                        <i class="bi bi-person-fill" style="font-size: 48px;"></i>
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
                                <input type="text" name="first_name" class="form-control" required />
                                <label class="form-label mt-2">First Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="middle_name" class="form-control" required />
                                <label class="form-label mt-2">Middle Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="last_name" class="form-control" required />
                                <label class="form-label mt-2">Last Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="date" name="dob" class="form-control" required />
                                <label class="form-label mt-2">Date of Birth</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="number" name="age" class="form-control" readonly />
                                <label class="form-label mt-2">Age</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <select name="sex" class="form-select" required>
                                    <option value="">Select</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                </select>
                                <label class="form-label mt-2">Sex</label>
                            </div>
                        </div>
                        <!-- Contact -->
                        <div class="row">
                            <span class="fw-bold mb-3">Contact Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="tel" name="cellphone" class="form-control" pattern="[0-9]+" maxlength="15"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                    placeholder="e.g., 09171234567" />
                                <label class="form-label mt-2">Cellphone Number</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="landline" class="form-control" />
                                <label class="form-label mt-2">Landline</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="email" name="email" class="form-control" required />
                                <label class="form-label mt-2">Email Address</label>
                            </div>
                        </div>
                        <!-- Address -->
                        <span class="fw-bold mb-3">Address</span>
                        <div class="my-3">
                            <input type="text" name="street" class="form-control" required />
                            <label class="form-label mt-2">Street Address</label>
                        </div>
                        <div class="mb-3">
                            <input type="text" name="street2" class="form-control" />
                            <label class="form-label mt-2">Street Address Line 2</label>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <input type="text" name="city" class="form-control" required />
                                <label class="form-label mt-2">City</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="state" class="form-control" required />
                                <label class="form-label mt-2">State/Province</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="barangay" class="form-control" required />
                                <label class="form-label mt-2">Barangay</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="postal" class="form-control" required />
                                <label class="form-label mt-2">Postal/Zip Code</label>
                            </div>
                        </div>
                        <!-- Roles -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label mt-2 fw-bold">Roles</label>
                                <select name="role" class="form-select" required>
                                    <option value="">--Select Role--</option>
                                    <option>Board Member</option>
                                    <option>Clubhouse Staff</option>
                                    <option>Security Staff</option>
                                </select>
                            </div>
                        </div>
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="../admin_accounts.php" class="btn btn-danger">Cancel</a>
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
                    <?php if (isset($success) && $success): ?>
                        <script>
                            window.addEventListener('DOMContentLoaded', () => {
                                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                                successModal.show();

                                const redirect = () => window.location.href = '../admin_accounts.php';
                                document.getElementById('doneButton').addEventListener('click', redirect);
                                document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate age from DOB
        document.querySelector('input[name="dob"]').addEventListener('change', function () {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
            document.querySelector('input[name="age"]').value = age;
        });

        // Image preview for profile picture
        document.getElementById('profile_pic').addEventListener('change', function (e) {
            const file = e.target.files[0];
            const preview = document.getElementById('preview');

            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" />`;
                }
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '<i class="bi bi-person-fill"></i>';
            }
        });
    </script>
</body>

</html>