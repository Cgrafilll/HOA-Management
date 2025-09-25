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

// Pagination settings for entry logs
$entriesPerPage = 10;

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get current page for entry and exit logs
$entryPage = isset($_GET['entry_page']) ? (int) $_GET['entry_page'] : 1;
$entryPage = max(1, $entryPage);

$exitPage = isset($_GET['exit_page']) ? (int) $_GET['exit_page'] : 1;
$exitPage = max(1, $exitPage);

// Calculate offsets
$entryOffset = ($entryPage - 1) * $entriesPerPage;
$exitOffset = ($exitPage - 1) * $entriesPerPage;

// Build WHERE clause based on filter
$entryWhereClause = "";
$exitWhereClause = "";

if ($filter == 'household') {
    $entryWhereClause = "WHERE type = 'household'";
    $exitWhereClause = "WHERE type = 'household'";
} elseif ($filter == 'visitor') {
    $entryWhereClause = "WHERE type = 'visitor'";
    $exitWhereClause = "WHERE type = 'visitor'";
}
// For 'all', no WHERE clause needed

// Get total counts for ENTRY LOGS
$entryCountSql = "SELECT COUNT(*) as total FROM entry_logs $entryWhereClause";
$entryCountResult = $conn->query($entryCountSql);
$entryTotalEntries = $entryCountResult->fetch_assoc()['total'];
$entryTotalPages = ceil($entryTotalEntries / $entriesPerPage);

// Get total counts for EXIT LOGS
$exitCountSql = "SELECT COUNT(*) as total FROM exit_logs $exitWhereClause";
$exitCountResult = $conn->query($exitCountSql);
$exitTotalEntries = $exitCountResult->fetch_assoc()['total'];
$exitTotalPages = ceil($exitTotalEntries / $entriesPerPage);

// Get paginated data for ENTRY logs
$entry_sql = "SELECT * FROM entry_logs $entryWhereClause ORDER BY date_created DESC LIMIT $entriesPerPage OFFSET $entryOffset";
$entry_result = $conn->query($entry_sql);

// Get paginated data for EXIT logs
$exit_sql = "SELECT * FROM exit_logs $exitWhereClause ORDER BY date_created DESC LIMIT $entriesPerPage OFFSET $exitOffset";
$exit_result = $conn->query($exit_sql);

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

        /* Make Cancel button slightly darker on hover */
        #confirmModal .btn-cancel:hover {
            background-color: #d6d8db;
            /* slightly darker gray */
            color: #000;
        }
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <div class="me-4" style="width: 250px;">
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">RECORD KEEPING</h1>
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
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav flex-column gap-1">
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
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#recordCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-book me-2"></i> Record Keeping
                        </span>
                    </button>
                    <div class="collapse show" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="violation_tracking.php" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="entry_logs.php" class="nav-link px-2 actived">Gate Logs</a></li>
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
                            <li><a href="announcements.php" class="nav-link px-2">Announcements</a></li>
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
                            <li><a href="payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="invoice.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="login/logout.php"
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
                    <h5 class="mb-0 fw-bold">Gate Logs</h5>
                </div>
                <div class="">
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
                                    <p class="mb-3">User has been moved to archives.</p>
                                    <button type="button" class="btn btn-primary" id="doneButton">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                    <i class="bi bi-x-circle text-danger" style="font-size: 64px;"></i>
                                    <p class="mb-2"><b>Are you sure?</b></p>
                                    <p class="mb-3">Do you really want to delete this account?</p>
                                    <p class="mb-3">This process will archive this account.</p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-danger" id="confirmProceed">Delete</button>
                                        <button type="button" class="btn btn-light btn-cancel"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($success) && $success): ?>
                        <script>
                            window.addEventListener('DOMContentLoaded', () => {
                                const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
                                const successModal = new bootstrap.Modal(document.getElementById('successModal'));

                                // Show confirmation modal first
                                confirmModal.show();

                                // If user clicks Proceed
                                document.getElementById('confirmProceed').addEventListener('click', () => {
                                    confirmModal.hide();
                                    setTimeout(() => successModal.show(), 300); // small delay to avoid overlap
                                });

                                // Success modal buttons/redirect
                                const redirect = () => window.location.href = 'entry_logs.php';
                                document.getElementById('doneButton').addEventListener('click', redirect);
                                document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
                            });
                        </script>
                    <?php endif; ?>
                    <!-- Main Tabs: Entry and Exit -->
                    <ul class="nav nav-tabs mt-3" id="mainTabs">
                        <li class="nav-item">
                            <a class="nav-link active link-dark" id="entry-tab" data-bs-toggle="tab" href="#entry"
                                role="tab">Entry Logs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-secondary" id="exit-tab" data-bs-toggle="tab" href="#exit"
                                role="tab">Exit Logs</a>
                        </li>
                    </ul>
                    <!-- Tab Content -->
                    <div class="tab-content px-2">
                        <!-- Entry Logs Tab -->
                        <div class="tab-pane fade show active" id="entry" role="tabpanel">
                            <!-- Filter Dropdown -->
                            <form method="get" class="mb-3 mt-3">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <label for="filter" class="fw-semibold">Filter by:</label>
                                        <select name="filter" id="filter" class="form-select form-select-sm w-auto"
                                            onchange="this.form.submit()">
                                            <option value="all" <?= ($filter == 'all') ? 'selected' : '' ?>>All</option>
                                            <option value="household" <?= ($filter == 'household') ? 'selected' : '' ?>>
                                                Household</option>
                                            <option value="visitor" <?= ($filter == 'visitor') ? 'selected' : '' ?>>Visitor
                                            </option>
                                        </select>
                                        <input type="hidden" name="tab" value="entry">
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="entry_logs/archive_homeowner.php"
                                            class="btn btn-secondary btn-sm">Archived
                                            RFID</a>
                                        <a href="entry_logs/manage_homeowner.php" class="btn btn-primary btn-sm">Manage
                                            RFID</a>
                                    </div>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="bg-success text-white small">
                                        <tr>
                                            <th>Entry ID</th>
                                            <th>RFID</th>
                                            <th>Full Name</th>
                                            <th>Type</th>
                                            <th>Date and Time</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small align-middle"
                                        style="min-height: 520px; display: table-row-group;">
                                        <?php
                                        $entry_count = 0;
                                        if ($entry_result->num_rows > 0) {
                                            while ($row = $entry_result->fetch_assoc()) {
                                                $entry_count++;
                                                $id = $row['entry_id'];
                                                $uid = $row['uid'];
                                                $fullName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                                $type = ucfirst($row['type']);
                                                $date = date('F j, Y, g:i A', strtotime($row['date_created']));
                                                $location = "Gate 1";
                                                echo "<tr>
                                                    <td>{$id}</td>
                                                    <td>{$uid}</td>
                                                    <td>{$fullName}</td>
                                                    <td>{$type}</td>
                                                    <td>{$date}</td>
                                                    <td>{$location}</td>
                                                </tr>";
                                            }
                                        }

                                        // Check if there are no rows and show appropriate message
                                        if ($entry_count === 0) {
                                            echo '<tr><td colspan="6" class="text-center text-muted">No entry logs found for selected filter.</td></tr>';
                                            // Add empty rows after the "no data" message
                                            $minRows = 10;
                                            for ($i = 1; $i < $minRows; $i++) {
                                                echo '<tr style="height: 38px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                            }
                                        } else {
                                            // Add empty rows to maintain consistent height (minimum 10 rows)
                                            $minRows = 10;
                                            for ($i = $entry_count; $i < $minRows; $i++) {
                                                echo '<tr style="height: 38px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>

                                <!-- Entry Pagination -->
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <?php
                                    $entryStart = $entryTotalEntries > 0 ? $entryOffset + 1 : 0;
                                    $entryEnd = min($entryOffset + $entriesPerPage, $entryTotalEntries);
                                    echo "<span class='small'>Showing $entryStart to $entryEnd of $entryTotalEntries entries</span>";
                                    ?>
                                    <nav>
                                        <ul class="pagination pagination-sm m-0">
                                            <?php
                                            if ($entryTotalEntries > 0) {
                                                // Previous button
                                                $prevDisabled = $entryPage <= 1 ? 'disabled' : '';
                                                $prevPage = $entryPage - 1;
                                                echo "<li class='page-item $prevDisabled'>";
                                                if ($entryPage > 1) {
                                                    echo "<a class='page-link' href='?entry_page=$prevPage&filter=$filter&tab=entry'>Previous</a>";
                                                } else {
                                                    echo "<a class='page-link'>Previous</a>";
                                                }
                                                echo "</li>";

                                                // Page numbers (simplified)
                                                for ($i = 1; $i <= $entryTotalPages; $i++) {
                                                    $activeClass = $i == $entryPage ? 'active' : '';
                                                    echo "<li class='page-item $activeClass'><a class='page-link' href='?entry_page=$i&filter=$filter&tab=entry'>$i</a></li>";
                                                }

                                                // Next button
                                                $nextDisabled = $entryPage >= $entryTotalPages ? 'disabled' : '';
                                                $nextPage = $entryPage + 1;
                                                echo "<li class='page-item $nextDisabled'>";
                                                if ($entryPage < $entryTotalPages) {
                                                    echo "<a class='page-link' href='?entry_page=$nextPage&filter=$filter&tab=entry'>Next</a>";
                                                } else {
                                                    echo "<a class='page-link'>Next</a>";
                                                }
                                                echo "</li>";
                                            } else {
                                                echo "<li class='page-item disabled'><a class='page-link'>Previous</a></li>";
                                                echo "<li class='page-item active'><a class='page-link'>1</a></li>";
                                                echo "<li class='page-item disabled'><a class='page-link'>Next</a></li>";
                                            }
                                            ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <!-- Exit Logs Tab -->
                        <div class="tab-pane fade" id="exit" role="tabpanel">
                            <!-- Filter Dropdown -->
                            <form method="get" class="mb-3 mt-3">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <label for="filter" class="fw-semibold">Filter by:</label>
                                        <select name="filter" id="filter" class="form-select form-select-sm w-auto"
                                            onchange="this.form.submit()">
                                            <option value="all" <?= ($filter == 'all') ? 'selected' : '' ?>>All</option>
                                            <option value="household" <?= ($filter == 'household') ? 'selected' : '' ?>>
                                                Household</option>
                                            <option value="visitor" <?= ($filter == 'visitor') ? 'selected' : '' ?>>Visitor
                                            </option>
                                        </select>
                                        <input type="hidden" name="tab" value="exit">
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="entry_logs/archive_homeowner.php"
                                            class="btn btn-secondary btn-sm">Archived
                                            RFID</a>
                                        <a href="entry_logs/manage_homeowner.php" class="btn btn-primary btn-sm">Manage
                                            RFID</a>
                                    </div>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="bg-danger text-white small">
                                        <tr>
                                            <th>Exit ID</th>
                                            <th>RFID</th>
                                            <th>Full Name</th>
                                            <th>Type</th>
                                            <th>Date and Time</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small align-middle"
                                        style="min-height: 520px; display: table-row-group;">
                                        <?php
                                        $exit_count = 0;
                                        if ($exit_result->num_rows > 0) {
                                            while ($row = $exit_result->fetch_assoc()) {
                                                $exit_count++;
                                                $id = $row['exit_id'];
                                                $uid = $row['uid'];
                                                $fullName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                                $type = ucfirst($row['type']);
                                                $date = date('F j, Y, g:i A', strtotime($row['date_created']));
                                                $location = "Gate 1";
                                                echo "<tr>
                                                    <td>{$id}</td>
                                                    <td>{$uid}</td>
                                                    <td>{$fullName}</td>
                                                    <td>{$type}</td>
                                                    <td>{$date}</td>
                                                    <td>{$location}</td>
                                                </tr>";
                                            }
                                        }
                                        // Check if there are no rows and show appropriate message
                                        if ($exit_count === 0) {
                                            echo '<tr><td colspan="6" class="text-center text-muted">No exit logs found for selected filter.</td></tr>';
                                            // Add empty rows after the "no data" message
                                            $minRows = 10;
                                            for ($i = 1; $i < $minRows; $i++) {
                                                echo '<tr style="height: 38px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                            }
                                        } else {
                                            // Add empty rows to maintain consistent height (minimum 10 rows)
                                            $minRows = 10;
                                            for ($i = $exit_count; $i < $minRows; $i++) {
                                                echo '<tr style="height: 38px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <!-- Exit Pagination -->
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <?php
                                    $exitStart = $exitTotalEntries > 0 ? $exitOffset + 1 : 0;
                                    $exitEnd = min($exitOffset + $entriesPerPage, $exitTotalEntries);
                                    echo "<span class='small'>Showing $exitStart to $exitEnd of $exitTotalEntries entries</span>";
                                    ?>
                                    <nav>
                                        <ul class="pagination pagination-sm m-0">
                                            <?php
                                            if ($exitTotalEntries > 0) {
                                                // Previous button
                                                $prevDisabled = $exitPage <= 1 ? 'disabled' : '';
                                                $prevPage = $exitPage - 1;
                                                echo "<li class='page-item $prevDisabled'>";
                                                if ($exitPage > 1) {
                                                    echo "<a class='page-link' href='?exit_page=$prevPage&filter=$filter&tab=exit'>Previous</a>";
                                                } else {
                                                    echo "<a class='page-link'>Previous</a>";
                                                }
                                                echo "</li>";
                                                // Page numbers (simplified)
                                                for ($i = 1; $i <= $exitTotalPages; $i++) {
                                                    $activeClass = $i == $exitPage ? 'active' : '';
                                                    echo "<li class='page-item $activeClass'><a class='page-link' href='?exit_page=$i&filter=$filter&tab=exit'>$i</a></li>";
                                                }
                                                // Next button
                                                $nextDisabled = $exitPage >= $exitTotalPages ? 'disabled' : '';
                                                $nextPage = $exitPage + 1;
                                                echo "<li class='page-item $nextDisabled'>";
                                                if ($exitPage < $exitTotalPages) {
                                                    echo "<a class='page-link' href='?exit_page=$nextPage&filter=$filter&tab=exit'>Next</a>";
                                                } else {
                                                    echo "<a class='page-link'>Next</a>";
                                                }
                                                echo "</li>";
                                            } else {
                                                echo "<li class='page-item disabled'><a class='page-link'>Previous</a></li>";
                                                echo "<li class='page-item active'><a class='page-link'>1</a></li>";
                                                echo "<li class='page-item disabled'><a class='page-link'>Next</a></li>";
                                            }
                                            ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedId = null;

        // Handle main tab switching (Entry/Exit)
        document.addEventListener('DOMContentLoaded', function () {
            const mainTabLinks = document.querySelectorAll('#mainTabs .nav-link');
            const mainTabPanes = document.querySelectorAll('.tab-content > .tab-pane');

            // Handle main tab switching
            mainTabLinks.forEach(function (tabLink) {
                tabLink.addEventListener('click', function (event) {
                    event.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    const targetPane = document.getElementById(targetId);

                    // Remove active classes from all main tabs and panes
                    mainTabLinks.forEach(function (link) {
                        link.classList.remove('active', 'link-dark');
                        link.classList.add('link-secondary');
                    });
                    mainTabPanes.forEach(function (pane) {
                        pane.classList.remove('show', 'active');
                    });

                    // Add active classes to current tab and pane
                    this.classList.add('active');
                    this.classList.remove('link-secondary');
                    this.classList.add('link-dark');

                    if (targetPane) {
                        targetPane.classList.add('show', 'active');
                    }
                });
            });

            // Handle URL tab parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab === 'exit') {
                document.getElementById('exit-tab').click();
            }
        });

        // Rest of the existing JavaScript for modals...
        document.querySelectorAll('.delete-account').forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                selectedId = btn.getAttribute('data-id');
            });
        });

        document.getElementById('confirmProceed').addEventListener('click', () => {
            if (selectedId) {
                fetch('admin/archive_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'admin_id=' + encodeURIComponent(selectedId)
                })
                    .then(res => res.text())
                    .then(text => {
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            throw new Error("Invalid JSON response");
                        }
                        if (data.success) {
                            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                            setTimeout(() => {
                                new bootstrap.Modal(document.getElementById('successModal')).show();
                            }, 300);
                            document.querySelector(`tr[data-id="${selectedId}"]`)?.remove();
                        } else {
                            alert(data.message || "Failed to archive.");
                        }
                    })
            } else {
                alert("Invalid admin ID.");
            }
        });

        const redirect = () => window.location.href = 'entry_logs.php';
        document.getElementById('doneButton').addEventListener('click', redirect);
        document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
    </script>

</body>

</html>