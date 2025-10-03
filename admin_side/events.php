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

// ✅ Handle Event Insert BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['title'], $_POST['body'], $_POST['event_date'], $_POST['form_token'])) {

        // ✅ Check for duplicate submission
        if ($_POST['form_token'] !== $_SESSION['form_token']) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=duplicate");
            exit;
        }

        $title = trim($_POST['title']);
        $body = trim($_POST['body']);
        $body = preg_replace('/\s+/', ' ', $body); // collapse multiple spaces/newlines
        $event_date = $_POST['event_date'];
        $status = "published";

        try {
            $stmt = $conn->prepare("INSERT INTO events (admin_id, title, body, status, event_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $admin_id, $title, $body, $status, $event_date);
            $stmt->execute();

            // ✅ Clear the form token after successful insert
            unset($_SESSION['form_token']);

            // ✅ Redirect after insert (prevents duplicates on refresh)
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit;
        } catch (Exception $e) {
            $error_message = "Error adding event: " . $e->getMessage();
        }
    }
}
// Fetch all published events
try {
    $stmt = $conn->prepare("
        SELECT e.id, e.title, e.body, e.event_date, e.created_at, 
               a.first_name, a.last_name
        FROM events e
        JOIN admin_accounts a ON e.admin_id = a.admin_id
        WHERE e.status = 'published'
        ORDER BY e.created_at DESC
    ");
    $stmt->execute();
    $events_result = $stmt->get_result();
    $stmt->close();
} catch (Exception $e) {
    $events_result = null; // prevent undefined variable
    $error_message = "Failed to fetch events: " . $e->getMessage();
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

            .d-flex.gap-2 {
                flex-direction: column;
                gap: 0.5rem !important;
            }

            .d-flex.gap-2 .btn {
                width: 100%;
            }
        }

        .event-card {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            max-width: 100%;
        }

        .event-body {
            font-size: 0.95rem;
            margin: 0;
            margin-bottom: 8px;
            line-height: 1.4;
            white-space: pre-wrap;
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
                        data-bs-target="#commCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-chat-left-text me-2"></i> Communication
                        </span>
                    </button>
                    <div class="collapse show" id="commCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="announcements.php" class="nav-link px-2">Announcements</a></li>
                            <li><a href="events.php" class="nav-link px-2 actived">Events</a></li>
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
                            <li><a href="payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="billing.php" class="nav-link px-2">Billing</a></li>
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
                    <h5 class="mb-0 fw-bold">Manage Events</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Event Form</span>
                        <div class="d-flex gap-2">
                            <a href="events/archive_events.php" class="btn btn-outline-secondary btn-sm">Archived
                                Events</a>
                        </div>
                    </div>
                    <hr class="mb-3 mt-0">
                    <!-- Event Form -->
                    <form method="POST" id="eventForm">
                        <input type="hidden" name="form_token"
                            value="<?php echo htmlspecialchars($_SESSION['form_token']); ?>">
                        <div class="row">
                            <div class="col-6">
                                <h5 class="fw mb-2">Event Title</h5>
                                <input type="text" id="title" name="title" class="form-control rounded mb-1"
                                    maxlength="150" placeholder="Enter event title" required>
                            </div>
                            <div class="col-6">
                                <h5 class="fw mb-2">Event Date</h5>
                                <input type="date" id="event_date" name="event_date" class="form-control mb-3" required>
                            </div>
                        </div>
                        <h5 class="fw mb-2 mt-3">Description</h5>
                        <textarea id="body" name="body" class="form-control rounded mb-1"
                            style="min-height:100px; resize:none;" placeholder="Enter event description"
                            required></textarea>
                        <p id="formError" class="text-danger small mt-2" style="display:none;">
                            Please fill in both Title, Description and Date before publishing.
                        </p>
                        <!-- Publish button -->
                        <div class="text-end">
                            <button type="button" class="btn btn-primary mt-3" id="publishBtn">Publish</button>
                        </div>
                    </form>
                    <!-- Published Events -->
                    <div class="mt-4">
                        <h5 class="fw-bold">Published Events</h5>
                        <hr>
                        <?php if ($events_result && $events_result->num_rows > 0): ?>
                            <?php while ($row = $events_result->fetch_assoc()): ?>
                                <div class="card mb-3 shadow-sm event-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="event-title"
                                                style="font-weight: 600; font-size: 1rem; margin-bottom: 6px;">
                                                <?= htmlspecialchars($row['title']); ?>
                                            </div>
                                            <div class="event-actions">
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                    data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id']; ?>"
                                                    title="Edit">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger archiveBtn"
                                                    data-id="<?= $row['id']; ?>" title="Archive">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="event-body mt-2"><?= nl2br(htmlspecialchars($row['body'])); ?></div>
                                        <div class="event-meta text-muted mt-1" style="font-size: 0.8rem;">
                                            Posted by <?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?>
                                            on <?= date("M d, Y h:i A", strtotime($row['created_at'])); ?> | Event Date:
                                            <?= date("M d, Y", strtotime($row['event_date'])); ?>
                                        </div>
                                    </div>
                                    <!-- Move Edit Modal here for this event -->
                                    <div class="modal fade" id="editModal<?= $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content">
                                                <form class="editForm" data-id="<?= $row['id']; ?>">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title">Edit Event</h5>
                                                        <button type="button" class="btn-close btn-close-white"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <!-- Title and Event Date in the same row -->
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <label class="form-label">Title</label>
                                                                <input type="text" name="title" class="form-control mb-2"
                                                                    value="<?= htmlspecialchars($row['title']); ?>" required>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label">Event Date</label>
                                                                <input type="date" name="event_date" class="form-control mb-2"
                                                                    value="<?= htmlspecialchars($row['event_date']); ?>"
                                                                    required>
                                                            </div>
                                                        </div>
                                                        <!-- Description with scrollable textarea -->
                                                        <label class="form-label mt-2">Description</label>
                                                        <textarea name="body" class="form-control" rows="8"
                                                            style="resize: vertical; max-height: 300px; overflow-y: auto;"
                                                            required><?= htmlspecialchars($row['body']); ?></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-success confirmEditBtn"
                                                            data-id="<?= $row['id']; ?>">Save Changes</button>
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted">No published events yet.</p>
                        <?php endif; ?>
                        <!-- Edit Confirmation Modal -->
                        <div class="modal fade" id="confirmEditModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Confirm Edit</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-question-circle text-success" style="font-size: 64px;"></i>
                                        <p class="mt-3 mb-2"><b>Are you sure?</b></p>
                                        <p class="mb-3">Do you want to save the changes to this event?</p>
                                        <button type="button" class="btn btn-success" id="confirmEditBtn">Save</button>
                                        <button type="button" class="btn btn-light"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Edit Success Modal -->
                        <div class="modal fade" id="editSuccessModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Success</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                                        <p>Event updated successfully!</p>
                                        <button type="button" class="btn btn-success"
                                            data-bs-dismiss="modal">OK</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Archive Confirmation Modal -->
                        <div class="modal fade" id="confirmArchiveModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title fw-bold">Confirm Archive</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                                        <p class="mb-2"><b>Are you sure?</b></p>
                                        <p class="mb-3">Do you want to archive this event?</p>
                                        <button type="button" class="btn btn-danger"
                                            id="confirmArchiveBtn">Archive</button>
                                        <button type="button" class="btn btn-light"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Archive Success Modal -->
                        <div class="modal fade" id="archiveSuccessModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title fw-bold">Archived!</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                                        <p class="mt-3 mb-2"><b>Event archived successfully.</b></p>
                                        <button type="button" class="btn btn-success"
                                            data-bs-dismiss="modal">OK</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Default Confirm Publish Modal -->
                        <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title fw-bold">Confirm Publish</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-question-circle text-success" style="font-size: 64px;"></i>
                                        <p class="mb-2"><b>Are you sure?</b></p>
                                        <p class="mb-3">Do you really want to publish this event?</p>
                                        <button type="button" class="btn btn-success"
                                            id="confirmPublish">Publish</button>
                                        <button type="button" class="btn btn-light"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Default Publish Success Modal -->
                        <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title fw-bold">Published!</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                                        <p class="mt-3 mb-2"><b>Event Published!</b></p>
                                        <p class="mb-3">Your event has been successfully published.</p>
                                        <div class="d-flex justify-content-center">
                                            <button type="button" class="btn btn-success"
                                                data-bs-dismiss="modal">OK</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === '1') {
                let successModalEl = document.getElementById('successModal');
                if (successModalEl) {
                    let successModal = new bootstrap.Modal(successModalEl);
                    successModal.show();

                    // Remove the query parameter so it doesn’t show on refresh
                    successModalEl.addEventListener('hidden.bs.modal', function () {
                        const url = new URL(window.location);
                        url.searchParams.delete('success');
                        window.history.replaceState({}, document.title, url.pathname);
                    });
                }
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function validateForm() {
                let title = document.getElementById("title");
                let body = document.getElementById("body");
                let date = document.getElementById("event_date");
                let errorMsg = document.getElementById("formError");
                let isValid = true;

                // Reset styles
                title.classList.remove("border-danger");
                body.classList.remove("border-danger");
                date.classList.remove("border-danger");
                errorMsg.style.display = "none";

                if (title.value.trim() === "") { title.classList.add("border-danger"); isValid = false; }
                if (body.value.trim() === "") { body.classList.add("border-danger"); isValid = false; }
                if (date.value === "") { date.classList.add("border-danger"); isValid = false; }

                if (!isValid) { errorMsg.style.display = "block"; }
                return isValid;
            }

            const publishBtn = document.getElementById("publishBtn");
            const confirmBtn = document.getElementById("confirmPublish");
            const eventForm = document.getElementById("eventForm");

            // Show confirmation modal if validation passes
            publishBtn.addEventListener("click", function () {
                if (validateForm()) {
                    let modal = new bootstrap.Modal(document.getElementById("confirmModal"));
                    modal.show();
                }
            });

            // Submit form on confirm
            confirmBtn.addEventListener("click", function () {
                this.disabled = true;
                this.textContent = "Publishing...";
                eventForm.submit();
            });

            // Auto-expand textarea
            document.querySelectorAll("textarea").forEach(function (el) {
                el.addEventListener("input", function () {
                    this.style.height = "auto";
                    this.style.height = this.scrollHeight + "px";
                });
            });
        });
        document.addEventListener('DOMContentLoaded', function () {
            let currentEditForm = null;
            let currentEventId = null;

            // When clicking "Save Changes" -> open confirmation modal
            document.querySelectorAll('.confirmEditBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    currentEventId = this.dataset.id;
                    currentEditForm = document.querySelector(`.editForm[data-id='${currentEventId}']`);

                    const editModalEl = document.getElementById(`editModal${currentEventId}`);
                    const editModalInstance = bootstrap.Modal.getInstance(editModalEl);

                    if (editModalInstance) {
                        editModalEl.addEventListener('hidden.bs.modal', function handler() {
                            // Show confirm modal after edit modal is fully hidden
                            const confirmModal = new bootstrap.Modal(document.getElementById('confirmEditModal'));
                            confirmModal.show();

                            editModalEl.removeEventListener('hidden.bs.modal', handler);
                        });

                        editModalInstance.hide();
                    } else {
                        // fallback if instance not found
                        const confirmModal = new bootstrap.Modal(document.getElementById('confirmEditModal'));
                        confirmModal.show();
                    }
                });
            });

            // When confirming in the modal, send the update
            document.getElementById('confirmEditBtn').addEventListener('click', function () {
                if (!currentEditForm) return;

                const formData = new FormData(currentEditForm);
                formData.append('id', currentEventId);

                fetch('events/update_events.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        const confirmModalEl = document.getElementById('confirmEditModal');
                        const confirmModal = bootstrap.Modal.getInstance(confirmModalEl);

                        if (data.success) {
                            confirmModal.hide();

                            const successModalEl = document.getElementById('editSuccessModal');
                            const successModal = new bootstrap.Modal(successModalEl);
                            successModal.show();

                            successModalEl.addEventListener('hidden.bs.modal', () => location.reload());
                        } else {
                            alert('Failed to update event: ' + data.message);
                        }
                    })
                    .catch(err => alert('Error updating event: ' + err.message));
            });
        });

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let archiveEventId = null;

            document.querySelectorAll('.archiveBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    archiveEventId = this.dataset.id;
                    const archiveModal = new bootstrap.Modal(document.getElementById('confirmArchiveModal'));
                    archiveModal.show();
                });
            });

            document.getElementById('confirmArchiveBtn').addEventListener('click', function () {
                if (!archiveEventId) return;

                const formData = new FormData();
                formData.append('id', archiveEventId);

                fetch('events/process_archive.php', {
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

                            archiveSuccessModalEl.addEventListener('hidden.bs.modal', () => location.reload());
                        } else {
                            alert('Failed to archive event: ' + data.message);
                        }
                    })
                    .catch(err => {
                        alert('Error archiving event: ' + err.message);
                    });
            });
        });

        // Set minimum date to today
        document.addEventListener("DOMContentLoaded", function () {
            const today = new Date().toISOString().split("T")[0];
            const dateInput = document.getElementById("event_date");
            dateInput.min = today; // disables all past options
        });

    </script>
    <script src="javascripts/mobileSidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>