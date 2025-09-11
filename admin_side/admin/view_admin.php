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

// Get LOGGED-IN admin details (for header display)
$logged_admin_id = $_SESSION['admin_id'];
$sql = "SELECT * FROM admin_accounts WHERE admin_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $logged_admin_id);
$stmt->execute();
$result = $stmt->get_result();
$logged_admin = $result->fetch_assoc();

if (!$logged_admin) {
    echo "Logged-in admin not found.";
    exit;
}

// Initialize logged-in user details for header
$username = $logged_admin['first_name'];
$photo = '';
// Only set $photo if profile_pic exists and is not null
if (!empty($logged_admin['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($logged_admin['profile_picture']);
} else {
    $photo = '';
}

// Get VIEWED admin details
$view_admin_id = $_GET['id'] ?? null;
$error_message = '';
$prof = $first_name = $middle_name = $last_name = $dob = $sex = $age = $cellphone = $landline = $email = $password = $street = $street2 = $city = $state = $brgy = $postal = $roles = $status = '';

if ($view_admin_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE admin_id = ?");
        $stmt->bind_param("s", $view_admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $viewed_admin = $result->fetch_assoc();

        if ($viewed_admin) {
            $prof = !empty($viewed_admin['profile_picture']) ? 'data:image/jpeg;base64,' . base64_encode($viewed_admin['profile_picture']) : '';
            $first_name = $viewed_admin['first_name'];
            $middle_name = $viewed_admin['middle_name'];
            $last_name = $viewed_admin['last_name'];
            $dob = $viewed_admin['date_of_birth'];
            $sex = $viewed_admin['sex'];
            $age = $viewed_admin['age'];
            $cellphone = $viewed_admin['cellphone_number'];
            $landline = $viewed_admin['landline'];
            $email = $viewed_admin['email_address'];
            $password = $viewed_admin['password'];
            $street = $viewed_admin['street_address'];
            $street2 = $viewed_admin['street_address_2'];
            $city = $viewed_admin['city'];
            $state = $viewed_admin['state_province'];
            $brgy = $viewed_admin['barangay'];
            $postal = $viewed_admin['postal_zip_code'];
            $roles = $viewed_admin['roles'];
            $status = $viewed_admin['status'];
        } else {
            $error_message = "Admin not found!";
        }
    } catch (Exception $e) {
        $error_message = "Error fetching admin: " . $e->getMessage();
    }
} else {
    $error_message = "Invalid admin ID.";
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
            <h1 class="h5 mb-0 fw-bold">ADMIN DASHBOARD</h1>
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
                    <li><a class="dropdown-item" href="view_admin.php?id=<?php echo $logged_admin_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="login/logout.php"><i
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
                    <i class="bi bi-box-arrow-left me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php else: ?>
                <div class="bg-white shadow rounded p-3">
                    <!-- Header -->
                    <div class="bg-success text-white rounded-top p-3">
                        <h5 class="mb-0 fw-bold">Admin Account Management</h5>
                    </div>
                    <!-- Subheader + Back -->
                    <div class="p-3 d-flex justify-content-between align-items-center">
                        <span class="small">User Details</span>
                        <div>
                            <button onclick="history.back()" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </button>
                            <a class="btn btn-primary btn-sm" href="edit_admin.php?id=<?php echo $view_admin_id; ?>">Edit
                                Details</a>
                        </div>
                    </div>
                    <hr class="my-0">
                    <!-- Content -->
                    <div class="p-4 text-center">
                        <!-- Profile Picture + Role -->
                        <div class="mb-4">
                            <div class="mx-auto rounded overflow-hidden" style="width: 200px; height: 200px;">
                                <?php if (!empty($prof)): ?>
                                    <img src="<?php echo htmlspecialchars($prof) ?>" class="img-fluid rounded"
                                        style="object-fit: cover; width: 100%; height: 100%;">
                                <?php else: ?>
                                    <div class="d-flex justify-content-center align-items-center border border-2 rounded"
                                        style="width: 200px; height: 200px;">
                                        <i class="bi bi-person-fill" style="font-size: 64px; color: #ccc;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 fw-semibold"><?php echo htmlspecialchars($roles); ?></div>
                        </div>
                        <!-- Centered Grid for Labels + Values -->
                        <div class="d-flex justify-content-center">
                            <div class="w-100" style="max-width: 600px;">
                                <?php
                                $details = [
                                    'Full Name' => htmlspecialchars("$first_name $middle_name $last_name"),
                                    'Date of Birth' => !empty($dob) ? date("F j, Y", strtotime($dob)) : 'N/A',
                                    'Age' => htmlspecialchars($age),
                                    'Sex' => htmlspecialchars($sex),
                                    'Cellphone Number' => !empty($cellphone) ? htmlspecialchars($cellphone) : 'N/A',
                                    'Landline' => !empty($landline) ? htmlspecialchars($landline) : 'N/A',
                                    'Email' => htmlspecialchars($email),
                                    'Address' => htmlspecialchars(
                                        $street .
                                        (!empty($street2) ? ', ' . $street2 : '') .
                                        ', ' . $brgy . ', ' . $city . ', ' . $state . ', ' . $postal
                                    )
                                ];
                                foreach ($details as $label => $value): ?>
                                    <div class="row mb-2">
                                        <div class="col-4 text-start fw-bold"><?php echo $label ?>:</div>
                                        <div class="col-8 text-start"><?php echo $value ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate age from DOB
        document.querySelector('input[name="dob"]')?.addEventListener('change', function () {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
            document.querySelector('input[name="age"]').value = age;
        });

        // Image preview for profile picture
        document.getElementById('profile_pic')?.addEventListener('change', function (e) {
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