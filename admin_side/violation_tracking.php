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

// Pagination settings for active violations
$entriesPerPage = 5;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Ensure page is at least 1

// Calculate offset for SQL query
$offset = ($currentPage - 1) * $entriesPerPage;

// Get total count for pagination (active violations only)
$countSql = "SELECT COUNT(*) as total FROM violations WHERE status = 'Active'";
$countResult = $conn->query($countSql);
$totalEntries = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalEntries / $entriesPerPage);

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

        .card-body p,
        .card-body h6 {
            word-wrap: break-word;
            /* Old support */
            overflow-wrap: break-word;
            /* Modern support */
            white-space: pre-wrap;
            /* Keeps newlines */
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
                            <li><a href="#" class="nav-link px-2 actived">Violation Tracking</a></li>
                            <li><a href="entry_logs.php" class="nav-link px-2">Gate Logs</a></li>
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
                            <li><a href="payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="billing.php" class="nav-link px-2">Invoices</a></li>
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
                    <h5 class="mb-0 fw-bold">Violation Management</h5>
                </div>
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
                                <p class="mb-3" id="successMessage">Violation has been updated.</p>
                                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Done</button>
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
                                <i class="bi bi-question-circle text-success" style="font-size: 64px;"></i>
                                <p class="mb-2"><b>Save Changes?</b></p>
                                <p class="mb-3">Are you sure you want to save the action taken changes?</p>
                                <div class="d-flex justify-content-center gap-2">
                                    <button type="button" class="btn btn-success" id="confirmSave">Save</button>
                                    <button type="button" class="btn btn-secondary btn-cancel"
                                        data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Archive Confirmation Modal -->
                <div class="modal fade" id="archiveConfirmModal" tabindex="-1"
                    aria-labelledby="archiveConfirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content text-center">
                            <div class="modal-header bg-danger">
                                <h5 class="modal-title text-white fw-bold">Archive Violation</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <i class="bi bi-archive text-danger" style="font-size: 64px;"></i>
                                <p class="mb-2"><b>Archive this violation?</b></p>
                                <p class="mb-3">This violation will be moved to archived violations.</p>
                                <div class="d-flex justify-content-center gap-2">
                                    <button type="button" class="btn btn-danger" id="confirmArchive">Archive</button>
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                </div>
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
                                <h5 class="modal-title fw-bold">Error</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                                <p class="mb-2"><b>Update Failed</b></p>
                                <p class="mb-3" id="errorMessage">An error occurred while updating the violation status.
                                </p>
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Violations</span>
                        <div>
                            <a href="violations/archive_violation.php"
                                class="btn btn-outline-secondary btn-sm me-2">Archived Violation</a>
                            <a href="violations/add_violations.php" class="btn btn-primary btn-sm">+ Create Violation
                                Report</a>
                        </div>
                    </div>
                    <hr class="mb-3 mt-0">
                    <!-- Table with fixed minimum height -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-success text-white small">
                                <tr>
                                    <th>Resident Name</th>
                                    <th>Date and Time of Incident</th>
                                    <th>Violation Type</th>
                                    <th>Description / Notes</th>
                                    <th class="text-center">Evidence</th>
                                    <th class="text-center">Action Taken</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="small align-middle">
                                <?php
                                if ($totalEntries > 0) {
                                    // Query with pagination
                                    $sql = "SELECT v.violation_id, 
                                        CONCAT(h.first_name, ' ', h.last_name) AS resident_name,
                                        v.date_incident,
                                        v.time_incident,
                                        v.violation_type,
                                        v.description_of_incident,
                                        v.action_taken,
                                        v.evidence
                                    FROM violations v
                                    INNER JOIN household_accounts h 
                                        ON v.household_id = h.household_id
                                    WHERE v.status = 'Active'
                                    ORDER BY v.date_incident DESC
                                    LIMIT $entriesPerPage OFFSET $offset";

                                    $violations = $conn->query($sql);
                                    $rowCount = 0;

                                    if ($violations && $violations->num_rows > 0) {
                                        while ($row = $violations->fetch_assoc()) {
                                            // Format date and time
                                            $formatted_date = date('M d, Y', strtotime($row['date_incident']));
                                            $formatted_time = date('h:i A', strtotime($row['time_incident']));
                                            $date_time_display = '<span>' . $formatted_date . '</span><br><small class="text-muted">' . $formatted_time . '</small>';

                                            // Determine badge class for action_taken
                                            $action_taken = strtolower(trim($row['action_taken']));
                                            $badge_class = '';
                                            switch ($action_taken) {
                                                case 'pending':
                                                    $badge_class = 'badge bg-primary';
                                                    break;
                                                case 'under review':
                                                    $badge_class = 'badge bg-warning text-dark';
                                                    break;
                                                case 'resolved':
                                                    $badge_class = 'badge bg-success';
                                                    break;
                                                case 'dismissed':
                                                    $badge_class = 'badge bg-secondary';
                                                    break;
                                                default:
                                                    $badge_class = 'badge bg-light text-dark';
                                            }
                                            echo '
                                                <tr data-violation-id="' . $row['violation_id'] . '" style="height: 80px;">
                                                    <td class="align-middle">' . htmlspecialchars($row['resident_name']) . '</td>
                                                    <td class="align-middle">' . $date_time_display . '</td>
                                                    <td class="align-middle">' . htmlspecialchars($row['violation_type']) . '</td>
                                                    <td class="align-middle">' . htmlspecialchars($row['description_of_incident']) . '</td>
                                                    <td class="text-center align-middle">';

                                            // Handle LONGBLOB evidence data
                                            if (!empty($row['evidence'])) {
                                                echo '<img src="data:image/jpeg;base64,' . base64_encode($row['evidence']) . '" 
                                                                alt="Evidence" 
                                                                style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; cursor: pointer;" 
                                                                class="img-thumbnail" 
                                                                onclick="showImageModal(this.src)">';
                                            } else {
                                                echo '<span class="text-muted small">No evidence</span>';
                                            }
                                            echo '</td>
                                                    <td class="text-center action-taken-cell align-middle" data-current-status="' . htmlspecialchars($row['action_taken']) . '">
                                                        <span class="' . $badge_class . ' status-badge">' . htmlspecialchars($row['action_taken']) . '</span>
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <div class="action-buttons">
                                                            <!-- Edit button -->
                                                            <button class="btn btn-sm btn-outline-primary me-1 edit-btn" title="Edit" style="padding: 2px 6px; font-size: 0.9rem;">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                            <!-- Archive button -->
                                                            <button class="btn btn-sm btn-outline-danger archive-btn" 
                                                                data-violation-id="' . $row['violation_id'] . '" title="Archive"
                                                                style="padding: 2px 6px; font-size: 0.9rem;">
                                                                <i class="bi bi-archive"></i>
                                                            </button>
                                                        </div>
                                                        <div class="edit-buttons d-none">
                                                            <button class="btn btn-sm btn-success me-1 save-btn" title="Save" style="padding: 2px 6px; font-size: 0.9rem;">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-secondary cancel-btn" title="Cancel" style="padding: 2px 6px; font-size: 0.9rem;">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>';
                                            $rowCount++;
                                        }
                                    }

                                    // Fill remaining rows to ensure table always has 5 rows
                                    while ($rowCount < 5) {
                                        echo '<tr style="height: 80px;">
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                        </tr>';
                                        $rowCount++;
                                    }
                                } else {
                                    // No active violations found - show message and fill 5 rows
                                    echo '<tr style="height: 80px;">
                                        <td colspan="7" class="text-center text-muted align-middle">No violation reports found.</td>
                                    </tr>';

                                    // Fill remaining 4 empty rows
                                    for ($i = 1; $i < 5; $i++) {
                                        echo '<tr style="height: 80px;">
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                            <td class="align-middle">&nbsp;</td>
                                        </tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalEntries > 0): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="small text-muted">
                                Showing <?php echo (($currentPage - 1) * $entriesPerPage) + 1; ?> to
                                <?php echo min($currentPage * $entriesPerPage, $totalEntries); ?> of
                                <?php echo $totalEntries; ?> entries
                            </div>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Pagination">
                                    <ul class="pagination pagination-sm mb-0">
                                        <!-- Previous button -->
                                        <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>"
                                                aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>

                                        <?php
                                        // Calculate page range to show
                                        $startPage = max(1, $currentPage - 2);
                                        $endPage = min($totalPages, $currentPage + 2);

                                        // Show first page if not in range
                                        if ($startPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1">1</a>
                                            </li>
                                            <?php if ($startPage > 2): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif;
                                        endif;

                                        // Show page numbers in range
                                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor;

                                        // Show last page if not in range
                                        if ($endPage < $totalPages): ?>
                                            <?php if ($endPage < $totalPages - 1): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link"
                                                    href="?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                                            </li>
                                        <?php endif; ?>

                                        <!-- Next button -->
                                        <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        <!-- Image Modal for Full View -->
        <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success">
                        <h5 class="modal-title text-white" id="imageModalLabel">Evidence Image</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="modalImage" src="" alt="Evidence" class="img-fluid" style="max-height: 70vh;">
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
            imageModal.show();
        }

        // Global variables to store current edit context
        let currentEditRow = null;
        let pendingStatus = null;
        let currentArchiveViolationId = null; // ADD THIS LINE

        // Handle edit functionality
        document.addEventListener('DOMContentLoaded', function () {
            // Edit button click handler
            document.addEventListener('click', function (e) {
                if (e.target.closest('.edit-btn')) {
                    const row = e.target.closest('tr');
                    const actionCell = row.querySelector('.action-taken-cell');
                    const currentStatus = actionCell.dataset.currentStatus;

                    // Create dropdown
                    const dropdown = `
                        <select class="form-select form-select-sm status-dropdown">
                            <option value="Pending" ${currentStatus === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Under Review" ${currentStatus === 'Under Review' ? 'selected' : ''}>Under Review</option>
                            <option value="Resolved" ${currentStatus === 'Resolved' ? 'selected' : ''}>Resolved</option>
                            <option value="Dismissed" ${currentStatus === 'Dismissed' ? 'selected' : ''}>Dismissed</option>
                        </select>
                    `;

                    // Replace badge with dropdown
                    actionCell.innerHTML = dropdown;

                    // Show edit buttons, hide action buttons
                    row.querySelector('.action-buttons').classList.add('d-none');
                    row.querySelector('.edit-buttons').classList.remove('d-none');
                }

                // Archive button click handler - ADD THIS BLOCK
                if (e.target.closest('.archive-btn')) {
                    const violationId = e.target.closest('.archive-btn').dataset.violationId;
                    currentArchiveViolationId = violationId;

                    // Show archive confirmation modal
                    const archiveModal = new bootstrap.Modal(document.getElementById('archiveConfirmModal'));
                    archiveModal.show();
                }

                // Save button click handler - Show confirmation modal
                if (e.target.closest('.save-btn')) {
                    const row = e.target.closest('tr');
                    const newStatus = row.querySelector('.status-dropdown').value;
                    const currentStatus = row.querySelector('.action-taken-cell').dataset.currentStatus;

                    // Check if status actually changed
                    if (newStatus === currentStatus) {
                        // No change, just exit edit mode
                        updateStatusDisplay(row, currentStatus);
                        exitEditMode(row);
                        return;
                    }

                    // Store context for confirmation
                    currentEditRow = row;
                    pendingStatus = newStatus;

                    // Show confirmation modal
                    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
                    confirmModal.show();
                }

                // Cancel button click handler
                if (e.target.closest('.cancel-btn')) {
                    const row = e.target.closest('tr');
                    const actionCell = row.querySelector('.action-taken-cell');
                    const currentStatus = actionCell.dataset.currentStatus;

                    // Restore original badge
                    updateStatusDisplay(row, currentStatus);
                    exitEditMode(row);
                }
            });

            // Confirmation modal save handler
            document.getElementById('confirmSave').addEventListener('click', function () {
                if (currentEditRow && pendingStatus) {
                    const violationId = currentEditRow.dataset.violationId;

                    // Close confirmation modal
                    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
                    confirmModal.hide();

                    // Save to database via AJAX
                    fetch('violations/update_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            violation_id: violationId,
                            action_taken: pendingStatus
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update the display
                                updateStatusDisplay(currentEditRow, pendingStatus);
                                exitEditMode(currentEditRow);

                                // Show success modal
                                document.getElementById('successMessage').textContent = 'Violation status has been updated successfully.';
                                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                                successModal.show();
                            } else {
                                // Show error modal
                                document.getElementById('errorMessage').textContent = data.message || 'An error occurred while updating the violation status.';
                                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                                errorModal.show();

                                // Restore original status on error
                                const currentStatus = currentEditRow.querySelector('.action-taken-cell').dataset.currentStatus;
                                updateStatusDisplay(currentEditRow, currentStatus);
                                exitEditMode(currentEditRow);
                            }

                            // Reset context
                            currentEditRow = null;
                            pendingStatus = null;
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Show error modal
                            document.getElementById('errorMessage').textContent = 'Network error occurred. Please try again.';
                            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                            errorModal.show();

                            // Restore original status on error
                            if (currentEditRow) {
                                const currentStatus = currentEditRow.querySelector('.action-taken-cell').dataset.currentStatus;
                                updateStatusDisplay(currentEditRow, currentStatus);
                                exitEditMode(currentEditRow);
                            }

                            // Reset context
                            currentEditRow = null;
                            pendingStatus = null;
                        });
                }
            });

            // Reset context when confirmation modal is closed without saving
            document.getElementById('confirmModal').addEventListener('hidden.bs.modal', function () {
                if (currentEditRow && pendingStatus) {
                    // User closed modal without saving, restore original status
                    const currentStatus = currentEditRow.querySelector('.action-taken-cell').dataset.currentStatus;
                    updateStatusDisplay(currentEditRow, currentStatus);
                    exitEditMode(currentEditRow);

                    // Reset context
                    currentEditRow = null;
                    pendingStatus = null;
                }
            });

            // Archive confirmation handler
            document.getElementById('confirmArchive').addEventListener('click', function () {
                if (currentArchiveViolationId) {
                    // Close archive modal
                    const archiveModal = bootstrap.Modal.getInstance(document.getElementById('archiveConfirmModal'));
                    archiveModal.hide();

                    // Archive violation via AJAX
                    fetch('violations/update_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            violation_id: currentArchiveViolationId,
                            action: 'archive'
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove the row from the table
                                const row = document.querySelector(`tr[data-violation-id="${currentArchiveViolationId}"]`);
                                if (row) {
                                    row.style.transition = 'opacity 0.3s';
                                    row.style.opacity = '0';
                                    setTimeout(() => {
                                        row.remove();

                                        // Check if there are any actual data rows left (rows with violation-id)
                                        const tbody = document.querySelector('tbody');
                                        const dataRows = tbody.querySelectorAll('tr[data-violation-id]');

                                        if (dataRows.length === 0) {
                                            // No data rows left - rebuild table with empty state
                                            tbody.innerHTML = `
                                    <tr style="height: 80px;">
                                        <td colspan="7" class="text-center text-muted align-middle">No violation reports found.</td>
                                    </tr>
                                    <tr style="height: 80px;">
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                    </tr>
                                    <tr style="height: 80px;">
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                    </tr>
                                    <tr style="height: 80px;">
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                    </tr>
                                    <tr style="height: 80px;">
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                    </tr>
                                `;

                                            // Also hide the pagination if it exists
                                            const paginationDiv = document.querySelector('.d-flex.justify-content-between.align-items-center.mt-3');
                                            if (paginationDiv) {
                                                paginationDiv.style.display = 'none';
                                            }
                                        } else {
                                            // There are still data rows, add an empty row to maintain count
                                            const currentRows = tbody.querySelectorAll('tr');
                                            if (currentRows.length < 5) {
                                                const emptyRow = document.createElement('tr');
                                                emptyRow.style.height = '80px';
                                                emptyRow.innerHTML = `
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                        <td class="align-middle">&nbsp;</td>
                                    `;
                                                tbody.appendChild(emptyRow);
                                            }
                                        }
                                    }, 300);
                                }

                                // Show success modal
                                document.getElementById('successMessage').textContent = 'Violation has been archived successfully.';
                                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                                successModal.show();
                            } else {
                                // Show error modal
                                document.getElementById('errorMessage').textContent = data.message || 'An error occurred while archiving the violation.';
                                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                                errorModal.show();
                            }

                            // Reset context
                            currentArchiveViolationId = null;
                        })
                        .catch(error => {
                            console.error('Error:', error);

                            // Show error modal
                            document.getElementById('errorMessage').textContent = 'Network error occurred. Please try again.';
                            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                            errorModal.show();

                            // Reset context
                            currentArchiveViolationId = null;
                        });
                }
            });
            
            // Reset context when archive modal is closed without archiving - ADD THIS BLOCK
            document.getElementById('archiveConfirmModal').addEventListener('hidden.bs.modal', function () {
                currentArchiveViolationId = null;
            });
        });

        function updateStatusDisplay(row, status) {
            const actionCell = row.querySelector('.action-taken-cell');
            let badgeClass = '';

            switch (status.toLowerCase()) {
                case 'pending':
                    badgeClass = 'badge bg-primary';
                    break;
                case 'under review':
                    badgeClass = 'badge bg-warning text-dark';
                    break;
                case 'resolved':
                    badgeClass = 'badge bg-success';
                    break;
                case 'dismissed':
                    badgeClass = 'badge bg-secondary';
                    break;
                default:
                    badgeClass = 'badge bg-light text-dark';
            }

            actionCell.innerHTML = `<span class="${badgeClass} status-badge">${status}</span>`;
            actionCell.dataset.currentStatus = status;
        }

        function exitEditMode(row) {
            row.querySelector('.action-buttons').classList.remove('d-none');
            row.querySelector('.edit-buttons').classList.add('d-none');
        }
    </script>

</body>

</html>