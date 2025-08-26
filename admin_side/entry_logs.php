<?php
session_start();
require '../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: login/login.php");
    exit;
}

// Initialize user details
$email_address = $_SESSION['email_address'];
$username = $photo = '';// Initialize user details

// Fetch user details including profile photo
try {
    $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE email_address = ?");
    $stmt->bind_param("s", $email_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $username = $user['first_name'];

        // Only set $photo if profile_pic exists and is not null
        if (!empty($user['profile_picture'])) {
            $photo = 'data:image/jpeg;base64,' . base64_encode($user['profile_picture']);
        } else {
            $photo = ''; // Explicitly empty if no image is saved
        }
    } else {
        $error_message = "Failed to fetch user details.";
    }

} catch (Exception $e) {
    $error_message = "Error fetching user details: " . $e->getMessage();
}

// Pagination settings for entry logs
$entriesPerPage = 10;

// Get current page for homeowner tab
$homeownerPage = isset($_GET['homeowner_page']) ? (int) $_GET['homeowner_page'] : 1;
$homeownerPage = max(1, $homeownerPage);

// Get current page for visitor tab
$visitorPage = isset($_GET['visitor_page']) ? (int) $_GET['visitor_page'] : 1;
$visitorPage = max(1, $visitorPage);

// Calculate offsets
$homeownerOffset = ($homeownerPage - 1) * $entriesPerPage;
$visitorOffset = ($visitorPage - 1) * $entriesPerPage;

// Get total counts for each type
$homeownerCountSql = "SELECT COUNT(*) as total FROM entry_logs WHERE type = 'household'";
$homeownerCountResult = $conn->query($homeownerCountSql);
$homeownerTotalEntries = $homeownerCountResult->fetch_assoc()['total'];
$homeownerTotalPages = ceil($homeownerTotalEntries / $entriesPerPage);

$visitorCountSql = "SELECT COUNT(*) as total FROM entry_logs WHERE type = 'visitor'";
$visitorCountResult = $conn->query($visitorCountSql);
$visitorTotalEntries = $visitorCountResult->fetch_assoc()['total'];
$visitorTotalPages = ceil($visitorTotalEntries / $entriesPerPage);

// Get paginated data for homeowner entries
$homeowner_sql = "SELECT * FROM entry_logs WHERE type = 'household' ORDER BY date_created DESC LIMIT $entriesPerPage OFFSET $homeownerOffset";
$homeowner_result = $conn->query($homeowner_sql);

// Get paginated data for visitor entries
$visitor_sql = "SELECT * FROM entry_logs WHERE type = 'visitor' ORDER BY date_created DESC LIMIT $entriesPerPage OFFSET $visitorOffset";
$visitor_result = $conn->query($visitor_sql);

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
            <div class="d-flex align-items-center gap-2">
                <span class="text-secondary">Hello, <?php echo htmlspecialchars($username); ?></span>
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
                            <li><a href="entry_logs.php" class="nav-link px-2 actived">Entry Logs</a></li>
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
                    <i class="bi bi-box-arrow-left me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Entry Logs</h5>
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

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mt-3" id="dashboardTabs">
                        <li class="nav-item">
                            <a class="nav-link active link-dark" id="homeowners-tab" data-bs-toggle="tab"
                                href="#homeowner" role="tab">Homeowner / Resident</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-secondary" id="visitor-tab" data-bs-toggle="tab" href="#visitor"
                                role="tab">Visitor</a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content p-3">
                        <!-- Resident Table -->
                        <div class="tab-pane fade show active" id="homeowner" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="small">Homeowner Entry Logs</span>
                                <div class="d-flex gap-2">
                                    <a href="entry_logs/archive_homeowner.php" class="btn btn-secondary btn-sm">Archived
                                        RFID</a>
                                    <a href="entry_logs/manage_homeowner.php" class="btn btn-primary btn-sm">Manage
                                        Homeowner RFID</a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="bg-success text-white small">
                                        <tr>
                                            <th>#</th>
                                            <th>Resident RFID</th>
                                            <th>Full Name</th>
                                            <th>Date and Time</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small align-middle"
                                        style="min-height: 520px; display: table-row-group;">
                                        <?php
                                        $household_count = 0;
                                        if ($homeowner_result->num_rows > 0) {
                                            while ($row = $homeowner_result->fetch_assoc()) {
                                                $household_count++;
                                                $id = $row['entry_id'];
                                                $uid = $row['uid'];
                                                $fullName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                                $date = date('F j, Y, g:i A', strtotime($row['date_created']));
                                                $location = "Gate 1";
                                                echo "<tr>
                                            <td>{$id}</td>
                                            <td>{$uid}</td>
                                            <td>{$fullName}</td>
                                            <td>{$date}</td>
                                            <td>{$location}</td>
                                        </tr>";
                                            }
                                        }

                                        // Check if there are no rows and show appropriate message
                                        if ($household_count === 0) {
                                            echo '<tr><td colspan="5" class="text-center text-muted">No household entry logs found.</td></tr>';
                                            // Add empty rows after the "no data" message
                                            $minRows = 10;
                                            for ($i = 1; $i < $minRows; $i++) {
                                                echo '<tr style="height: 52px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                            }
                                        } else {
                                            // Add empty rows to maintain consistent height (minimum 10 rows)
                                            $minRows = 10;
                                            for ($i = $household_count; $i < $minRows; $i++) {
                                                echo '<tr style="height: 52px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>

                                <!-- Homeowner Pagination -->
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <?php
                                    $homeownerStart = $homeownerTotalEntries > 0 ? $homeownerOffset + 1 : 0;
                                    $homeownerEnd = min($homeownerOffset + $entriesPerPage, $homeownerTotalEntries);
                                    echo "<span class='small'>Showing $homeownerStart to $homeownerEnd of $homeownerTotalEntries entries</span>";
                                    ?>
                                    <nav>
                                        <ul class="pagination pagination-sm m-0">
                                            <?php
                                            // Only show pagination if there are entries
                                            if ($homeownerTotalEntries > 0) {
                                                // Previous button
                                                $prevDisabled = $homeownerPage <= 1 ? 'disabled' : '';
                                                $prevPage = $homeownerPage - 1;
                                                echo "<li class='page-item $prevDisabled'>";
                                                if ($homeownerPage > 1) {
                                                    echo "<a class='page-link' href='?homeowner_page=$prevPage#homeowner'>Previous</a>";
                                                } else {
                                                    echo "<a class='page-link'>Previous</a>";
                                                }
                                                echo "</li>";

                                                // Page numbers
                                                $startPage = max(1, $homeownerPage - 2);
                                                $endPage = min($homeownerTotalPages, $homeownerPage + 2);

                                                // First page and ellipsis
                                                if ($startPage > 1) {
                                                    echo "<li class='page-item'><a class='page-link' href='?homeowner_page=1#homeowner'>1</a></li>";
                                                    if ($startPage > 2) {
                                                        echo "<li class='page-item disabled'><a class='page-link'>...</a></li>";
                                                    }
                                                }

                                                // Page range
                                                for ($i = $startPage; $i <= $endPage; $i++) {
                                                    $activeClass = $i == $homeownerPage ? 'active' : '';
                                                    echo "<li class='page-item $activeClass'><a class='page-link' href='?homeowner_page=$i#homeowner'>$i</a></li>";
                                                }

                                                // Last page and ellipsis
                                                if ($endPage < $homeownerTotalPages) {
                                                    if ($endPage < $homeownerTotalPages - 1) {
                                                        echo "<li class='page-item disabled'><a class='page-link'>...</a></li>";
                                                    }
                                                    echo "<li class='page-item'><a class='page-link' href='?homeowner_page=$homeownerTotalPages#homeowner'>$homeownerTotalPages</a></li>";
                                                }

                                                // Next button
                                                $nextDisabled = $homeownerPage >= $homeownerTotalPages ? 'disabled' : '';
                                                $nextPage = $homeownerPage + 1;
                                                echo "<li class='page-item $nextDisabled'>";
                                                if ($homeownerPage < $homeownerTotalPages) {
                                                    echo "<a class='page-link' href='?homeowner_page=$nextPage#homeowner'>Next</a>";
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

                        <!-- Visitor Table -->
                        <div class="tab-pane fade" id="visitor" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="small">Visitor Entry Logs</span>
                                <div class="d-flex gap-2">
                                    <a href="entry_logs/archive_visitor.php" class="btn btn-secondary btn-sm">Archived
                                        RFID</a>
                                    <a href="entry_logs/manage_visitor.php" class="btn btn-primary btn-sm">Manage
                                        Visitor RFID</a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="bg-primary text-white small">
                                        <tr>
                                            <th>#</th>
                                            <th>Visitor RFID</th>
                                            <th>Full Name</th>
                                            <th>Date and Time</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small align-middle"
                                        style="min-height: 520px; display: table-row-group;">
                                        <?php
                                        $visitor_count = 0;
                                        if ($visitor_result->num_rows > 0) {
                                            while ($row = $visitor_result->fetch_assoc()) {
                                                $visitor_count++;
                                                $id = $row['entry_id'];
                                                $uid = $row['uid'];
                                                $fullName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                                $date = date('F j, Y, g:i A', strtotime($row['date_created']));
                                                $location = "Gate 1";
                                                echo "<tr>
                                            <td>{$id}</td>
                                            <td>{$uid}</td>
                                            <td>{$fullName}</td>
                                            <td>{$date}</td>
                                            <td>{$location}</td>
                                        </tr>";
                                            }
                                        }

                                        // Check if there are no rows and show appropriate message
                                        if ($visitor_count === 0) {
                                            echo '<tr><td colspan="5" class="text-center text-muted">No visitor entry logs found.</td></tr>';
                                            // Add empty rows after the "no data" message
                                            $minRows = 10;
                                            for ($i = 1; $i < $minRows; $i++) {
                                                echo '<tr style="height: 52px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                            }
                                        } else {
                                            // Add empty rows to maintain consistent height (minimum 10 rows)
                                            $minRows = 10;
                                            for ($i = $visitor_count; $i < $minRows; $i++) {
                                                echo '<tr style="height: 52px; visibility: hidden;"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>

                                <!-- Visitor Pagination -->
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <?php
                                    $visitorStart = $visitorTotalEntries > 0 ? $visitorOffset + 1 : 0;
                                    $visitorEnd = min($visitorOffset + $entriesPerPage, $visitorTotalEntries);
                                    echo "<span class='small'>Showing $visitorStart to $visitorEnd of $visitorTotalEntries entries</span>";
                                    ?>
                                    <nav>
                                        <ul class="pagination pagination-sm m-0">
                                            <?php
                                            // Only show pagination if there are entries
                                            if ($visitorTotalEntries > 0) {
                                                // Previous button
                                                $prevDisabled = $visitorPage <= 1 ? 'disabled' : '';
                                                $prevPage = $visitorPage - 1;
                                                echo "<li class='page-item $prevDisabled'>";
                                                if ($visitorPage > 1) {
                                                    echo "<a class='page-link' href='?visitor_page=$prevPage#visitor'>Previous</a>";
                                                } else {
                                                    echo "<a class='page-link'>Previous</a>";
                                                }
                                                echo "</li>";

                                                // Page numbers
                                                $startPage = max(1, $visitorPage - 2);
                                                $endPage = min($visitorTotalPages, $visitorPage + 2);

                                                // First page and ellipsis
                                                if ($startPage > 1) {
                                                    echo "<li class='page-item'><a class='page-link' href='?visitor_page=1#visitor'>1</a></li>";
                                                    if ($startPage > 2) {
                                                        echo "<li class='page-item disabled'><a class='page-link'>...</a></li>";
                                                    }
                                                }

                                                // Page range
                                                for ($i = $startPage; $i <= $endPage; $i++) {
                                                    $activeClass = $i == $visitorPage ? 'active' : '';
                                                    echo "<li class='page-item $activeClass'><a class='page-link' href='?visitor_page=$i#visitor'>$i</a></li>";
                                                }

                                                // Last page and ellipsis
                                                if ($endPage < $visitorTotalPages) {
                                                    if ($endPage < $visitorTotalPages - 1) {
                                                        echo "<li class='page-item disabled'><a class='page-link'>...</a></li>";
                                                    }
                                                    echo "<li class='page-item'><a class='page-link' href='?visitor_page=$visitorTotalPages#visitor'>$visitorTotalPages</a></li>";
                                                }

                                                // Next button
                                                $nextDisabled = $visitorPage >= $visitorTotalPages ? 'disabled' : '';
                                                $nextPage = $visitorPage + 1;
                                                echo "<li class='page-item $nextDisabled'>";
                                                if ($visitorPage < $visitorTotalPages) {
                                                    echo "<a class='page-link' href='?visitor_page=$nextPage#visitor'>Next</a>";
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
                </div>
            </div>
        </main>
    </div>
    <script>
        let selectedId = null;

        // Handle tab switching and link color changes
        document.addEventListener('DOMContentLoaded', function () {
            const tabLinks = document.querySelectorAll('#dashboardTabs .nav-link');
            const tabPanes = document.querySelectorAll('.tab-pane');

            // Add click event listener to each tab link
            tabLinks.forEach(function (tabLink) {
                tabLink.addEventListener('click', function (event) {
                    event.preventDefault();

                    // Get the target tab pane
                    const targetId = this.getAttribute('href').substring(1);
                    const targetPane = document.getElementById(targetId);

                    // Remove active classes from all tabs and panes
                    tabLinks.forEach(function (link) {
                        link.classList.remove('active', 'link-dark');
                        link.classList.add('link-secondary');
                    });

                    tabPanes.forEach(function (pane) {
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
        });

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
                fetch('admin/archive_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'admin_id=' + encodeURIComponent(selectedId)
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
                alert("Invalid admin ID.");
            }
        });
        // Redirect after success
        const redirect = () => window.location.href = 'admin_accounts.php';
        document.getElementById('doneButton').addEventListener('click', redirect);
        document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);

        // Handle tab switching and maintain pagination state
        document.addEventListener('DOMContentLoaded', function () {
            // Check URL hash to activate correct tab
            const hash = window.location.hash;
            if (hash === '#visitor') {
                document.getElementById('homeowners-tab').classList.remove('active');
                document.getElementById('visitor-tab').classList.add('active');
                document.getElementById('homeowner').classList.remove('show', 'active');
                document.getElementById('visitor').classList.add('show', 'active');
            }

            // Update URL hash when tab is clicked
            document.getElementById('homeowners-tab').addEventListener('click', function () {
                window.location.hash = '#homeowner';
            });

            document.getElementById('visitor-tab').addEventListener('click', function () {
                window.location.hash = '#visitor';
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>