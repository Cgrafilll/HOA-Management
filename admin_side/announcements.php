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

require '../rfid-api/db.php'; // Adjust path as needed

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
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

// ✅ Generate unique form token
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(16));
}

// ✅ Handle Announcement Insert BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['title'], $_POST['body'], $_POST['form_token'])) {

        // ✅ Check for duplicate submission
        if ($_POST['form_token'] !== $_SESSION['form_token']) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=duplicate");
            exit;
        }

        $title = trim($_POST['title']);
        $body = trim($_POST['body']);         // remove leading/trailing whitespace
        $body = preg_replace('/\s+/', ' ', $body); // collapse multiple spaces/newlines into single space
        $status = "published";

        try {
            $stmt = $conn->prepare("INSERT INTO announcements (admin_id, title, body, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $admin_id, $title, $body, $status);
            $stmt->execute();

            // ✅ Clear the form token after successful insert
            unset($_SESSION['form_token']);

            // ✅ Redirect after insert (prevents duplicates on refresh)
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit;
        } catch (Exception $e) {
            $error_message = "Error adding announcement: " . $e->getMessage();
        }
    }
}

// ✅ Fetch published announcements (after insert/redirect logic)
try {
    // Auto-archive old announcements first
    $archiveDate = date('Y-m-d H:i:s', strtotime('-7 days'));
    $archiveStmt = $conn->prepare("
        UPDATE announcements 
        SET status = 'archived' 
        WHERE status = 'published' 
        AND created_at < ?
    ");
    $archiveStmt->bind_param("s", $archiveDate);
    $archiveStmt->execute();

    // Now fetch published announcements
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.body, a.created_at, ad.first_name, ad.last_name 
        FROM announcements a
        JOIN admin_accounts ad ON a.admin_id = ad.admin_id
        WHERE a.status = 'published'
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    $error_message = "Error fetching announcements: " . $e->getMessage();
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
    <link rel="icon" href="../images/SitioSeville_Logo.png" type="image/x-icon">
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

            .announcement-title,
            .announcement-body,
            .announcment-meta,
            .form-control,
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
        }

        .announcement-card {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            max-width: 100%;
        }

        .announcement-body {
            font-size: 0.95rem;
            margin: 0;
            margin-bottom: 8px;
            line-height: 1.4;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .card-body p,
        .card-body h6 {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: pre-wrap;
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

        textarea {
            min-height: 100px;
            resize: none;
            /* optional: prevent manual drag */
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
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">COMMUNICATION</h1>
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
                    <li><a class="dropdown-item" href="admin/view_admin.php?id=<?php echo $admin_id; ?>"><i
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
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav class="nav flex-column gap-1 sidebar-nav p-3">
                <a href="admin_dashboard.php"
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
                            <li><a href="admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="household_accounts.php" class="nav-link px-2">Household</a></li>
                            <li><a href="visitor_accounts.php" class="nav-link px-2">Visitors</a></li>
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
                            <li><a href="amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="violation_tracking.php" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="entry_logs.php" class="nav-link px-2">Gate Logs</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Communication -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#commCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-chat-left-text me-2"></i> Communication
                        </span>
                    </button>
                    <div class="collapse show" id="commCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="announcements.php" class="nav-link px-2 actived">Announcements</a></li>
                            <li><a href="events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="phonebook.php" class="nav-link px-2">Phone Book</a></li>
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
                            <li><a href="payment.php" class="nav-link px-2">Payment</a></li>
                            <li><a href="billing.php" class="nav-link px-2">List of Billings</a></li>
                            <li><a href="invoices.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="login/logout.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start logout">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-grow-1 p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Announcements</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Announcement Form</span>
                        <div class="d-flex gap-2">
                            <a href="announcements/archive_announcements.php"
                                class="btn btn-outline-secondary btn-sm">Archived Announcements</a>
                        </div>
                    </div>
                    <hr class="mb-3 mt-0">
                    <!-- ✅ Show error message if duplicate submission detected -->
                    <?php if (isset($_GET['error']) && $_GET['error'] == 'duplicate'): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Duplicate submission detected!</strong> This announcement was already posted.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <!-- Announcement Form -->
                    <form method="POST" id="announcementForm">
                        <!-- ✅ Add hidden form token -->
                        <input type="hidden" name="form_token"
                            value="<?php echo htmlspecialchars($_SESSION['form_token']); ?>">
                        <!-- Title -->
                        <span class="fw h5 mb-2">Title</span>
                        <input type="text" id="title" name="title" class="form-control rounded mb-1" maxlength="150"
                            placeholder="Enter announcement title">
                        <!-- Body -->
                        <span class="fw h5 mb-2 mt-3">Body</span>
                        <textarea id="body" name="body" class="form-control rounded mb-1"
                            style="min-height:100px; resize:none;" placeholder="Enter a description"></textarea>
                        <!-- Error message -->
                        <p id="formError" class="text-danger small mt-2" style="display:none;">
                            Please fill in both Title and Body before publishing.
                        </p>
                        <!-- Publish button -->
                        <div class="text-end">
                            <button type="button" class="btn btn-primary mt-3" id="publishBtn">
                                Publish
                            </button>
                        </div>
                    </form>
                    <!-- Confirmation Modal -->
                    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content text-center">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title fw-bold">Confirmation</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-check-circle text-success" style="font-size: 64px;"></i>
                                    <p class="mb-2"><b>Are you sure?</b></p>
                                    <p class="mb-3">Do you really want to publish this announcement?</p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <!-- Submit confirm -->
                                        <button type="button" class="btn btn-success"
                                            id="confirmPublish">Publish</button>
                                        <!-- Cancel -->
                                        <button type="button" class="btn btn-light btn-cancel"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Success Modal -->
                    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content text-center">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title fw-bold">Success</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                                    <p class="mt-3 mb-2"><b>Announcement Published!</b></p>
                                    <p class="mb-3">Your announcement has been successfully published.</p>
                                    <div class="d-flex justify-content-center">
                                        <button type="button" class="btn btn-success"
                                            data-bs-dismiss="modal">OK</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="fw-bold h5">Published Announcements</span>
                        <hr>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                // Calculate days since creation
                                $createdDate = new DateTime($row['created_at']);
                                $now = new DateTime();
                                $daysSince = $now->diff($createdDate)->days;
                                $daysRemaining = 30 - $daysSince;
                                ?>
                                <div class="card mb-3 shadow-sm announcement-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="announcement-title"
                                                style="font-weight: 600;font-size: 1rem;margin-bottom: 6px;">
                                                <?= htmlspecialchars($row['title']); ?>
                                                <?php if ($daysRemaining <= 7 && $daysRemaining > 0): ?>
                                                    <span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem;">
                                                        <i class="bi bi-clock"></i> <?= $daysRemaining ?> days left
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="announcement-actions gap-1">
                                                <!-- Edit button triggers modal -->
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id']; ?>"
                                                    title="Edit" style="padding: 2px 6px; font-size: 0.9rem;">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <!-- Archive button -->
                                                <button type="button" class="btn btn-sm btn-outline-danger archiveBtn"
                                                    data-id="<?= $row['id']; ?>" title="Archive"
                                                    style="padding: 2px 6px; font-size: 0.9rem;">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="announcement-body mt-2">
                                            <?= nl2br(htmlspecialchars($row['body'])); ?>
                                        </div>
                                        <div class="announcement-meta mt-1 text-muted" style="font-size: 0.8rem;">
                                            Posted by <?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?>
                                            on <?= date("M d, Y h:i A", strtotime($row['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $row['id']; ?>" tabindex="-1"
                                    aria-labelledby="editModalLabel<?= $row['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <form class="editForm" data-id="<?= $row['id']; ?>">
                                                <div class="modal-header bg-success text-white">
                                                    <h5 class="modal-title" id="editModalLabel<?= $row['id']; ?>">Edit
                                                        Announcement</h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <label class="form-label">Title</label>
                                                    <input type="text" name="title" class="form-control mb-2"
                                                        value="<?= htmlspecialchars($row['title']); ?>" required>
                                                    <label class="form-label mt-2">Body</label>
                                                    <textarea name="body" class="form-control" rows="8"
                                                        style="resize: vertical; max-height: 300px; overflow-y: auto;"
                                                        required><?= htmlspecialchars($row['body']); ?></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-success confirmEditBtn"
                                                        data-bs-toggle="modal" data-bs-target="#confirmEditModal"
                                                        data-id="<?= $row['id']; ?>">Save Changes</button>
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted">No published announcements yet.</p>
                        <?php endif; ?>
                    </div>
                    <!--Reusable Confirmation modal for Archive button on publish announcements-->
                    <div class="modal fade" id="confirmArchiveModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content text-center">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title fw-bold">Confirm Archive</h5>
                                    <button type="button" class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-question-circle text-danger" style="font-size: 64px;"></i>
                                    <p class="mt-3 mb-2"><b>Are you sure?</b></p>
                                    <p class="mb-3">Do you want to archive this announcement?</p>
                                    <button type="button" class="btn btn-danger" id="confirmArchiveBtn">Archive</button>
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Archive Success Modal -->
                    <div class="modal fade" id="archiveSuccessModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content text-center">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title fw-bold">Archived</h5>
                                    <button type="button" class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-archive-fill text-success" style="font-size: 64px;"></i>
                                    <p class="mt-3 mb-2"><b>Announcement Archived!</b></p>
                                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Confirmation Modal (single, reused for any announcement) -->
                <div class="modal fade" id="confirmEditModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content text-center">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title fw-bold">Confirm Update</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <i class="bi bi-question-circle text-success" style="font-size: 64px;"></i>
                                <p class="mt-3 mb-2"><b>Are you sure?</b></p>
                                <p class="mb-3">Do you want to save the changes to this announcement?</p>
                                <button type="button" class="btn btn-success" id="saveEditBtn">Update</button>
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Success Modal -->
                <div class="modal fade" id="editSuccessModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content text-center">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title fw-bold">Success</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                                <p class="mt-3 mb-2"><b>Announcement Updated!</b></p>
                                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let successModalEl = document.getElementById('successModal');
                if (successModalEl) {
                    let successModal = new bootstrap.Modal(successModalEl);
                    successModal.show();

                    // ✅ Clear the URL parameter after showing modal to prevent showing on refresh
                    successModalEl.addEventListener('hidden.bs.modal', function () {
                        const url = new URL(window.location);
                        url.searchParams.delete('success');
                        window.history.replaceState({}, document.title, url.pathname);
                    });
                }
            });
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="javascripts/mobileSidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // ✅ Validation function
            function validateForm() {
                let title = document.getElementById("title");
                let body = document.getElementById("body");
                let errorMsg = document.getElementById("formError");
                let isValid = true;

                // Reset styles
                title.classList.remove("border-danger");
                body.classList.remove("border-danger");
                errorMsg.style.display = "none";

                // Validate Title
                if (title.value.trim() === "") {
                    title.classList.add("border-danger");
                    isValid = false;
                }

                // Validate Body
                if (body.value.trim() === "") {
                    body.classList.add("border-danger");
                    isValid = false;
                }

                // If invalid, show error
                if (!isValid) {
                    errorMsg.style.display = "block";
                    return false;
                }

                return true;
            }

            // ✅ Publish button click
            document.getElementById("publishBtn").addEventListener("click", function () {
                if (validateForm()) {
                    let modal = new bootstrap.Modal(document.getElementById("confirmModal"));
                    modal.show();
                }
            });

            // ✅ Confirm publish button click
            document.getElementById("confirmPublish").addEventListener("click", function () {
                this.disabled = true;
                this.textContent = "Publishing...";
                document.getElementById("announcementForm").submit();
            });

            // ✅ Prevent form submission on Enter key unless validation passes
            document.getElementById("announcementForm").addEventListener("submit", function (e) {
                e.preventDefault(); // Always prevent default

                if (validateForm()) {
                    let modal = new bootstrap.Modal(document.getElementById("confirmModal"));
                    modal.show();
                }
            });

            // Auto-expand textarea
            document.querySelectorAll("textarea").forEach(function (el) {
                el.addEventListener("input", function () {
                    this.style.height = "auto"; // reset first
                    this.style.height = (this.scrollHeight) + "px"; // then adjust
                });
            });
        });

        // ✅ FIXED EDIT MODAL FUNCTIONALITY
        document.addEventListener('DOMContentLoaded', function () {
            let currentEditForm = null;
            let currentEditModalId = null;

            // When clicking "Save Changes" -> open confirmation modal
            document.querySelectorAll('.confirmEditBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const announcementId = this.dataset.id;
                    currentEditForm = document.querySelector(`.editForm[data-id='${announcementId}']`);
                    currentEditModalId = `editModal${announcementId}`;

                    // Hide the current edit modal first
                    const editModalEl = document.getElementById(currentEditModalId);
                    const editModalInstance = bootstrap.Modal.getInstance(editModalEl);
                    if (editModalInstance) {
                        editModalInstance.hide();
                    }

                    // Show confirmation modal after a small delay
                    setTimeout(() => {
                        const confirmModal = new bootstrap.Modal(document.getElementById('confirmEditModal'));
                        confirmModal.show();
                    }, 300);
                });
            });

            // When confirming update
            document.getElementById('saveEditBtn').addEventListener('click', function () {
                if (!currentEditForm) return;

                const formData = new FormData(currentEditForm);
                formData.append('id', currentEditForm.dataset.id);

                fetch('announcements/update_announcements.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        const confirmModalEl = document.getElementById('confirmEditModal');
                        const confirmModal = bootstrap.Modal.getInstance(confirmModalEl);

                        if (data.success) {
                            // Hide confirmation modal
                            confirmModal.hide();

                            // Show success modal
                            const successModalEl = document.getElementById('editSuccessModal');
                            const successModal = new bootstrap.Modal(successModalEl);
                            successModal.show();

                            // Refresh page after closing success modal
                            successModalEl.addEventListener('hidden.bs.modal', () => location.reload());
                        } else {
                            alert('Failed to update announcement: ' + data.message);
                        }
                    })
                    .catch(err => {
                        alert('Error updating announcement: ' + err.message);
                    });
            });

            // Handle confirmation modal close/cancel - return to edit modal
            const confirmEditModalEl = document.getElementById('confirmEditModal');
            confirmEditModalEl.addEventListener('hidden.bs.modal', function () {
                // Clean up backdrops first
                setTimeout(() => {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(b => b.remove());
                }, 100);

                // If we came from an edit modal and user canceled, show the edit modal again
                if (currentEditModalId && !document.querySelector('.modal.show')) {
                    setTimeout(() => {
                        const editModalEl = document.getElementById(currentEditModalId);
                        if (editModalEl) {
                            const editModal = new bootstrap.Modal(editModalEl);
                            editModal.show();
                        } else {
                            // If edit modal doesn't exist anymore, clean up completely
                            cleanupModalState();
                        }
                    }, 100);
                } else if (!document.querySelector('.modal.show')) {
                    // If no modals should be open, clean up completely
                    cleanupModalState();
                }
            });

            // Clean up function
            function cleanupModalState() {
                // Remove all backdrops
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                    backdrop.remove();
                });

                // Reset body state
                document.body.classList.remove('modal-open');
                document.body.style.paddingRight = '';
                document.body.style.overflow = '';

                // Clear stored data
                currentEditForm = null;
                currentEditModalId = null;
            }

            // Handle edit modal close events - clean up if no other modals are open
            document.querySelectorAll('[id^="editModal"]').forEach(modal => {
                modal.addEventListener('hidden.bs.modal', function () {
                    setTimeout(() => {
                        const openModals = document.querySelectorAll('.modal.show');
                        if (openModals.length === 0) {
                            cleanupModalState();
                        }
                    }, 100);
                });
            });

            // Handle escape key and outside clicks
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    setTimeout(() => {
                        const openModals = document.querySelectorAll('.modal.show');
                        if (openModals.length === 0) {
                            cleanupModalState();
                        }
                    }, 300);
                }
            });

            // Additional cleanup on window focus (helps with tab switching issues)
            window.addEventListener('focus', function () {
                setTimeout(() => {
                    const openModals = document.querySelectorAll('.modal.show');
                    if (openModals.length === 0) {
                        cleanupModalState();
                    }
                }, 100);
            });
        });

        // ✅ ARCHIVE FUNCTIONALITY
        let archiveAnnouncementId = null;

        document.querySelectorAll('.archiveBtn').forEach(btn => {
            btn.addEventListener('click', function () {
                archiveAnnouncementId = this.dataset.id;
                const archiveModal = new bootstrap.Modal(document.getElementById('confirmArchiveModal'));
                archiveModal.show();
            });
        });

        document.getElementById('confirmArchiveBtn').addEventListener('click', function () {
            if (!archiveAnnouncementId) return;

            const formData = new FormData();
            formData.append('id', archiveAnnouncementId);

            fetch('announcements/process_archive.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    const archiveModalEl = document.getElementById('confirmArchiveModal');
                    const archiveModal = bootstrap.Modal.getInstance(archiveModalEl);

                    if (data.success) {
                        archiveModal.hide();

                        const archiveSuccessModalEl = document.getElementById('archiveSuccessModal');
                        const archiveSuccessModal = new bootstrap.Modal(archiveSuccessModalEl);
                        archiveSuccessModal.show();

                        // Reload only after the user closes success modal
                        archiveSuccessModalEl.addEventListener('hidden.bs.modal', () => location.reload());
                    } else {
                        alert('Failed to archive announcement: ' + data.message);
                    }
                })
                .catch(err => {
                    alert('Error archiving announcement: ' + err.message);
                });
        });
    </script>
</body>

</html>