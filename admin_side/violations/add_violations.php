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

// Fetch all household accounts for dropdown
$households = [];
try {
    $stmt = $conn->prepare("SELECT household_id, first_name, middle_name, last_name, cellphone_number FROM household_accounts ORDER BY first_name, last_name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $households[] = $row;
    }
} catch (Exception $e) {
    $error_message = "Error fetching households: " . $e->getMessage();
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
            .form-check,
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
            .form-check,
            main span {
                font-size: 0.85rem;
            }
        }

        /* Make Cancel button slightly darker on hover */
        #confirmModal .btn-cancel:hover {
            background-color: #d6d8db;
            /* slightly darker gray */
            color: #000;
        }

        .form-control.border-danger {
            border: 2px solid #dc3545 !important;
            /* force red */
        }

        .file-drop-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background-color: #f9fafb;
            transition: all 0.3s ease;
        }

        .file-drop-area:hover {
            border-color: #6b7280;
            background-color: #f3f4f6;
        }

        .file-drop-area.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .file-drop-area.required-highlight {
            border-color: #dc3545;
            background-color: #fff5f5;
        }

        .file-drop-area.required-highlight .cloud-icon {
            color: #dc3545;
        }

        .cloud-icon {
            font-size: 48px;
            color: #9ca3af;
            margin-bottom: 16px;
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
            <h1 class="h5 mb-0 fw-bold">RECORD KEEPING</h1>
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
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#accountsCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-person-lines-fill me-2"></i> Accounts
                        </span>
                    </button>
                    <div class="collapse" id="accountsCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="../household_accounts.php" class="nav-link px-2">Household</a></li>
                            <li><a href="../visitor_accounts.php" class="nav-link px-2">Visitors</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Record Keeping -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#recordCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-book me-2"></i> Record Keeping
                        </span>
                    </button>
                    <div class="collapse show" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="../violation_tracking.php" class="nav-link px-2 actived">Violation Tracking</a>
                            </li>
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
                            <li><a href="../announcements.php" class="nav-link px-2 actived">Announcements</a></li>
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
                            <li><a href="../payment.php" class="nav-link px-2">Payment</a></li>
                            <li><a href="../billing.php" class="nav-link px-2">List of Billings</a></li>
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
                    <h5 class="mb-0 fw-bold">Violation Management</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Violations</span>
                        <div class="d-flex gap-2">
                            <a href="../violation_tracking.php" class="btn btn-outline-secondary btn-sm">Back</a>
                        </div>
                    </div>
                    <hr class="mb-3 mt-0">
                    <!-- Violation Report Form -->
                    <form action="save_violation.php" id="violationForm" method="POST" enctype="multipart/form-data">
                        <!-- Household Selection -->
                        <div class="row mb-3">
                            <span class="fw-bold mb-2">Select Household Account</span>
                            <div class="col-md-4">
                                <select id="householdSelect" class="form-select" required>
                                    <option value="" selected disabled>Select a Household</option>
                                    <?php foreach ($households as $household): ?>
                                        <option value="<?php echo htmlspecialchars($household['household_id']); ?>"
                                            data-firstname="<?php echo htmlspecialchars($household['first_name']); ?>"
                                            data-middlename="<?php echo htmlspecialchars($household['middle_name']); ?>"
                                            data-lastname="<?php echo htmlspecialchars($household['last_name']); ?>"
                                            data-cellphone="<?php echo htmlspecialchars($household['cellphone_number']); ?>">
                                            <?php echo htmlspecialchars($household['household_id'] . ' - ' . $household['first_name'] . ' ' . $household['middle_name'] . ' ' . $household['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="form-label mt-2">Household<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                        </div>
                        <!-- Personal Info -->
                        <div class="row mb-3">
                            <span class="fw-bold mb-2">Reporter Information</span>
                            <div class="col-md-4">
                                <input type="text" id="first_name" name="first_name" class="form-control" readonly />
                                <label class="form-label mt-2">First Name<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="middle_name" name="middle_name" class="form-control" readonly />
                                <label class="form-label mt-2">Middle Name<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="last_name" name="last_name" class="form-control" readonly />
                                <label class="form-label mt-2">Last Name<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                        </div>
                        <!-- Contact -->
                        <div class="row mb-3">
                            <span class="fw-bold mb-2">Contact Information</span>
                            <div class="col-md-4">
                                <input type="tel" id="cellphone_number" name="cellphone_number" class="form-control"
                                    readonly />
                                <label class="form-label mt-2">Cellphone Number</label>
                            </div>
                        </div>
                        <!-- Hidden field to store household_id -->
                        <input type="hidden" id="household_id" name="household_id" />
                        <!-- Incident Details -->
                        <div class="row mb-3">
                            <span class="fw-bold mb-2">Incident Details</span>
                            <div class="col-md-4">
                                <input type="date" name="date_incident" class="form-control" id="dateIncident"
                                    max="<?php echo date('Y-m-d'); ?>" required />
                                <label class="form-label mt-2">Date of Incident<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-md-4">
                                <input type="time" name="time_incident" class="form-control" id="timeIncident"
                                    required />
                                <label class="form-label mt-2">Time of Incident<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="location" class="form-control" required />
                                <label class="form-label mt-2">Location<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-md-4">
                                <select name="violation_type" class="form-select" required>
                                    <option value="" selected disabled>Select Violation Type</option>
                                    <option>Excessive Noise</option>
                                    <option>Parking Violation</option>
                                    <option>Pet-Related Complaint</option>
                                    <option>Unapproved Construction</option>
                                </select>
                                <label class="form-label mt-2">Violation Type<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                        </div>
                        <!-- Parties Involved Info -->
                        <div class="row mb-3">
                            <span class="fw-bold mb-2">Parties Involved</span>
                            <div class="col-md-4">
                                <input type="text" name="homeowner_involved" class="form-control" />
                                <label class=" form-label mt-2">Name of Resident/Household Involved <i>(if
                                        known)</i></label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="address_lot_number" class="form-control" />
                                <label class=" form-label mt-2">Address/Lot Number <i>(if applicable)</i></label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="other_parties" class="form-control" />
                                <label class=" form-label mt-2">Other Parties/Witnesses <i>(optional)</i></label>
                            </div>
                        </div>
                        <!-- Evidence -->
                        <div class="row mb-3">
                            <span class="fw-bold mb-2">Evidence</span>
                            <div class="col-md-4">
                                <!-- File Upload -->
                                <div class="file-drop-area" id="fileDropArea" style="height: 250px;">
                                    <div class="cloud-icon">
                                        <i class="bi bi-cloud-upload"></i>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Drag & drop files or <a href="#" id="browseLink">Browse</a></strong>
                                    </div>
                                    <div class="small text-muted">
                                        Supported formats: JPEG, PNG, GIF
                                    </div>
                                    <input type="file" id="fileInput" name="evidence" class="d-none"
                                        accept="image/jpeg,image/png,image/gif" required>
                                </div>
                                <label class="form-label mt-2">Upload your Evidence<small
                                        class="fw-bold text-danger">*</small></label>
                                <div id="filePreview" class="mt-2"></div>
                            </div>
                            <div class="col-md-4">
                                <textarea name="description_of_incident" class="form-control" required
                                    style="height: 250px; resize: none;"
                                    placeholder="Specifically describe what happened . . ."></textarea>
                                <label class="form-label mt-2">Description of Incident<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                        </div>
                        <hr class="mt-0">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="anonymous" name="anonymous"
                                        value="1">
                                    <label class="form-check-label" for="anonymous">
                                        I want to remain anonymous to the reported party
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="accurate" name="accurate"
                                        required>
                                    <label class="form-check-label" for="accurate">
                                        I confirm that the information provided is accurate to the best of my knowledge
                                    </label>
                                </div>
                            </div>
                        </div>
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">Report Violation</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <!-- Confirm Save Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Confirm Save</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-question-circle text-primary" style="font-size: 64px;"></i>
                    <p class="mb-2">Are you sure you want to save this violation?</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-primary" id="confirmSaveBtn">Save</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-success text-white">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Violation report saved successfully.</b></p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal"
                        onclick="window.location.href='../violation_tracking.php'">OK</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                    <p id="errorMessage" class="text-dark">An error occurred while saving the violation.
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

                const redirect = () => window.location.href = '../violation_tracking.php';
                document.getElementById('doneButton').addEventListener('click', redirect);
                document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
            });
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../javascripts/mobileSidebar.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const householdSelect = document.getElementById('householdSelect');
            const firstNameInput = document.getElementById('first_name');
            const middleNameInput = document.getElementById('middle_name');
            const lastNameInput = document.getElementById('last_name');
            const cellphoneInput = document.getElementById('cellphone_number');
            const householdIdInput = document.getElementById('household_id');

            // Handle household selection change
            householdSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];

                if (selectedOption.value) {
                    // Populate the form fields with selected household data
                    firstNameInput.value = selectedOption.dataset.firstname;
                    middleNameInput.value = selectedOption.dataset.middlename;
                    lastNameInput.value = selectedOption.dataset.lastname;
                    cellphoneInput.value = selectedOption.dataset.cellphone;
                    householdIdInput.value = selectedOption.value;
                } else {
                    // Clear the form fields if no household is selected
                    firstNameInput.value = '';
                    middleNameInput.value = '';
                    lastNameInput.value = '';
                    cellphoneInput.value = '';
                    householdIdInput.value = '';
                }
            });

            let confirmBtn = document.getElementById("confirmSaveBtn");
            let violationForm = document.getElementById("violationForm");

            // Add form submission handler to add validation classes
            violationForm.addEventListener("submit", function (event) {
                event.preventDefault(); // Prevent default submission

                // Add Bootstrap validation class
                violationForm.classList.add("was-validated");

                // Check if form is valid
                if (violationForm.checkValidity()) {
                    // Show confirmation modal if form is valid
                    let confirmModal = new bootstrap.Modal(document.getElementById("confirmModal"));
                    confirmModal.show();
                }
                // If form is invalid, the was-validated class will show the validation errors
            });

            confirmBtn.addEventListener("click", function () {
                let formData = new FormData(violationForm);

                fetch("save_violation.php", {
                    method: "POST",
                    body: formData
                })
                    .then(res => res.text())
                    .then(data => {
                        if (data.trim() === "success") {
                            new bootstrap.Modal(document.getElementById("successModal")).show();
                            violationForm.reset();
                            // Remove validation class after successful reset
                            violationForm.classList.remove("was-validated");
                        } else {
                            document.getElementById("errorMessage").innerText = data;
                            new bootstrap.Modal(document.getElementById("errorModal")).show();
                        }
                    })
                    .catch(err => {
                        document.getElementById("errorMessage").innerText = "Network error: " + err;
                        new bootstrap.Modal(document.getElementById("errorModal")).show();
                    });

                // Close confirm modal after saving
                let confirmModal = bootstrap.Modal.getInstance(document.getElementById("confirmModal"));
                confirmModal.hide();
            });

            // File Upload Functionality
            const fileDropArea = document.getElementById('fileDropArea');
            const fileInput = document.getElementById('fileInput');
            const browseLink = document.getElementById('browseLink');
            const filePreview = document.getElementById('filePreview');

            if (fileDropArea && fileInput && browseLink && filePreview) {
                // Prevent defaults for all drag events
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    fileDropArea.addEventListener(eventName, preventDefaults, false);
                    document.body.addEventListener(eventName, preventDefaults, false);
                });

                // Highlight drop area when dragging over it
                ['dragenter', 'dragover'].forEach(eventName => {
                    fileDropArea.addEventListener(eventName, highlight, false);
                });

                // Remove highlight when dragging away or dropping
                ['dragleave', 'drop'].forEach(eventName => {
                    fileDropArea.addEventListener(eventName, unhighlight, false);
                });

                // Handle file drop
                fileDropArea.addEventListener('drop', handleDrop, false);

                // Handle browse link click
                browseLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    fileInput.click();
                });

                // Handle file input change
                fileInput.addEventListener('change', (e) => {
                    handleFiles(e.target.files);
                });
            }

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight(e) {
                fileDropArea.classList.add('dragover');
            }

            function unhighlight(e) {
                fileDropArea.classList.remove('dragover');
            }

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles(files);
            }

            function handleFiles(files) {
                if (files.length === 0) return;

                const file = files[0]; // Take only the first file

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid file type for evidence (JPEG, PNG, GIF)');
                    return;
                }

                // Validate file size (max 10MB)
                const maxSize = 10 * 1024 * 1024; // 10MB in bytes
                if (file.size > maxSize) {
                    alert('File size must be less than 10MB');
                    return;
                }

                // Update the file input
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;

                // Display file preview
                filePreview.innerHTML = `
        <div class="alert alert-success d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <i class="bi bi-file-earmark-image me-2"></i>
                <div>
                    <strong>${file.name}</strong><br>
                    <small>${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                </div>
            </div>
            <button type="button" class="btn-close" onclick="clearFile()"></button>
        </div>
    `;

                // Remove required highlight if present
                fileDropArea.classList.remove('required-highlight');
            }

            function clearFile() {
                if (fileInput) fileInput.value = '';
                if (filePreview) filePreview.innerHTML = '';
            }
            
            // With this code that handles both date and time validation properly:
            const dateIncident = document.getElementById('dateIncident');
            const timeIncident = document.getElementById('timeIncident');

            dateIncident.max = new Date().toISOString().split('T')[0];

            // Update time max attribute based on selected date
            dateIncident.addEventListener('change', function () {
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                selectedDate.setHours(0, 0, 0, 0);

                if (selectedDate.getTime() === today.getTime()) {
                    // If today, set max time to current time
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    timeIncident.max = `${hours}:${minutes}`;
                } else {
                    // If past date, remove time restriction
                    timeIncident.removeAttribute('max');
                }
            });
        });
    </script>
</body>

</html>