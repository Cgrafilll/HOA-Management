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

// Pagination settings for household accounts
$entriesPerPage = 10;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Ensure page is at least 1

// Calculate offset for SQL query
$offset = ($currentPage - 1) * $entriesPerPage;

// Get total count for pagination (all household accounts)
$countSql = "SELECT COUNT(*) as total FROM household_accounts WHERE status = 'Active'";
$countResult = $conn->query($countSql);
$totalEntries = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalEntries / $entriesPerPage);

// Get data for current page (household accounts)
$sql = "SELECT household_id, first_name, middle_name, last_name, members, status, created_at 
        FROM household_accounts WHERE status = 'Active' 
        ORDER BY created_at DESC
        LIMIT $entriesPerPage OFFSET $offset";
$result = $conn->query($sql);

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

            .table-responsive {
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

            .table-responsive {
                font-size: 0.75rem;
            }

            .pagination {
                font-size: 0.8rem;
            }
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
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#accountsCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-person-lines-fill me-2"></i> Accounts
                        </span>
                    </button>
                    <div class="collapse show" id="accountsCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="household_accounts.php" class="nav-link px-2 actived">Household</a></li>
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
                    <h5 class="mb-0 fw-bold">Household Account Management</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">List of Household Accounts</span>
                        <div class="d-flex gap-2">
                            <a href="household/archive_household.php" class="btn btn-secondary btn-sm">Archived
                                Accounts</a>
                            <a href="household/add_household.php" class="btn btn-primary btn-sm">+ Create New</a>
                        </div>
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
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title fw-bold">Confirmation</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-x-circle text-danger" style="font-size: 64px;"></i>
                                    <p class="mb-2"><b>Are you sure?</b></p>
                                    <p class="mb-3">This process will archive this account.</p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-danger"
                                            id="confirmProceed">Archive</button>
                                        <button type="button" class="btn btn-secondary btn-cancel"
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
                                const redirect = () => window.location.href = 'admin_accounts.php';
                                document.getElementById('doneButton').addEventListener('click', redirect);
                                document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
                            });
                        </script>
                    <?php endif; ?>
                    <!-- Table with fixed minimum height -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-success text-white small">
                                <tr>
                                    <th>#</th>
                                    <th>Date Created</th>
                                    <th>Full Name</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="small align-middle" style="min-height: 520px; display: table-row-group;">
                                <?php
                                $rowCount = 0;
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $household_id = $row['household_id'];
                                        $fullName = $row['first_name'] . ' ' . substr($row['middle_name'], 0, 1) . '. ' . $row['last_name'];
                                        $status = $row['status'] === 'Active' ? 'text-success' : 'text-danger';
                                        $statusText = ucfirst($row['status']);
                                        $created = date('Y-m-d H:i', strtotime($row['created_at']));

                                        echo '
                                            <tr>
                                                <td>' . $household_id . '</td>
                                                <td>' . $created . '</td>
                                                <td>' . $fullName . '</td>
                                                <td class="' . $status . ' text-center fw-bold">' . $statusText . '</td>
                                                <td>
                                                    <div class="text-center gap-1">
                                                        <!-- View button -->
                                                        <a class="btn btn-sm btn-outline-success" href="household/view_household.php?id=' . $household_id . '" title="View" style="padding: 2px 6px; font-size: 0.9rem;">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <!-- Edit button -->
                                                        <a class="btn btn-sm btn-outline-primary" href="household/edit_household.php?id=' . $household_id . '" title="Edit" style="padding: 2px 6px; font-size: 0.9rem;">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                        <!-- Archive button -->
                                                        <a class="btn btn-sm btn-outline-danger archiveBtn delete-account"
                                                            href="household/archive_process.php" data-id="' . $household_id . '" data-bs-toggle="modal" data-bs-target="#confirmModal" title="Archive"
                                                            style="padding: 2px 6px; font-size: 0.9rem;">
                                                            <i class="bi bi-archive"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>';
                                        $rowCount++;
                                    }
                                }
                                // Check if there are no rows and show appropriate message
                                if ($rowCount === 0) {
                                    echo '<tr><td colspan="5" class="text-center text-muted">No household accounts found.</td></tr>';
                                    // Add empty rows after the "no data" message
                                    $minRows = 10;
                                    for ($i = 1; $i < $minRows; $i++) {
                                        echo '<tr style="height: 52px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                    }
                                } else {
                                    // Add empty rows to maintain consistent height (minimum 10 rows)
                                    $minRows = 10;
                                    for ($i = $rowCount; $i < $minRows; $i++) {
                                        echo '<tr style="height: 52px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Pagination info and controls -->
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <?php
                            $start = $totalEntries > 0 ? $offset + 1 : 0;
                            $end = min($offset + $entriesPerPage, $totalEntries);
                            echo "<span class='small'>Showing $start to $end of $totalEntries entries</span>";
                            ?>
                            <nav>
                                <ul class="pagination pagination-sm m-0">
                                    <?php
                                    // Only show pagination if there are entries
                                    if ($totalEntries > 0) {
                                        // Previous button
                                        $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
                                        $prevPage = $currentPage - 1;
                                        echo "<li class='page-item $prevDisabled'>";
                                        if ($currentPage > 1) {
                                            echo "<a class='page-link' href='?page=$prevPage'>Previous</a>";
                                        } else {
                                            echo "<a class='page-link'>Previous</a>";
                                        }
                                        echo "</li>";

                                        // Page numbers
                                        $startPage = max(1, $currentPage - 2);
                                        $endPage = min($totalPages, $currentPage + 2);

                                        // First page and ellipsis
                                        if ($startPage > 1) {
                                            echo "<li class='page-item'><a class='page-link' href='?page=1'>1</a></li>";
                                            if ($startPage > 2) {
                                                echo "<li class='page-item disabled'><a class='page-link'>...</a></li>";
                                            }
                                        }

                                        // Page range
                                        for ($i = $startPage; $i <= $endPage; $i++) {
                                            $activeClass = $i == $currentPage ? 'active' : '';
                                            echo "<li class='page-item $activeClass'><a class='page-link' href='?page=$i'>$i</a></li>";
                                        }

                                        // Last page and ellipsis
                                        if ($endPage < $totalPages) {
                                            if ($endPage < $totalPages - 1) {
                                                echo "<li class='page-item disabled'><a class='page-link'>...</a></li>";
                                            }
                                            echo "<li class='page-item'><a class='page-link' href='?page=$totalPages'>$totalPages</a></li>";
                                        }

                                        // Next button
                                        $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
                                        $nextPage = $currentPage + 1;
                                        echo "<li class='page-item $nextDisabled'>";
                                        if ($currentPage < $totalPages) {
                                            echo "<a class='page-link' href='?page=$nextPage'>Next</a>";
                                        } else {
                                            echo "<a class='page-link'>Next</a>";
                                        }
                                        echo "</li>";
                                    } else {
                                        // Show disabled pagination when no entries
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
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="javascripts/mobileSidebar.js"></script>
    <script>
        let selectedId = null;

        // Capture ID when clicking "Delete Account" button
        document.querySelectorAll('.delete-account').forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.preventDefault(); // Prevent page reload!
                selectedId = btn.getAttribute('data-id');
            });
        });

        // Handle confirmation proceed
        document.getElementById('confirmProceed').addEventListener('click', () => {
            if (selectedId) {
                fetch('household/archive_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'household_id=' + encodeURIComponent(selectedId)
                })
                    .then(res => res.text())  // read as text first
                    .then(text => {
                        let data;
                        try {
                            data = JSON.parse(text); // then parse
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
                alert("Invalid household ID.");
            }
        });
        // Redirect after success
        const redirect = () => window.location.href = 'household_accounts.php';
        document.getElementById('doneButton').addEventListener('click', redirect);
        document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
    </script>
</body>

</html>