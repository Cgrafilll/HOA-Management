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

// Fetch Amenity Bookings from booking_details table
$entry_sql = "SELECT * FROM entry_logs ORDER BY date_created DESC";
$entry_result = $conn->query($entry_sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        .sidebar {
            width: 250px;
            min-height: 100vh;
            background-color: #1F2937;
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
                        data-bs-target="#recordCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-book me-2"></i> Record Keeping
                        </span>
                    </button>
                    <div class="collapse" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="#" class="nav-link px-2">Violation Tracking</a></li>
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
                            <li><a href="#" class="nav-link px-2">Announcements</a></li>
                            <li><a href="#" class="nav-link px-2">Events</a></li>
                            <li><a href="#" class="nav-link px-2">Phone Book</a></li>
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
                            <li><a href="#" class="nav-link px-2">Payments</a></li>
                            <li><a href="#" class="nav-link px-2">Invoices</a></li>
                            <li><a href="#" class="nav-link px-2">Transactions</a></li>
                            <li><a href="#" class="nav-link px-2">Budgets</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Forms -->
                <a href="#" class="nav-link px-3 py-2 d-flex align-items-center justify-content-start">
                    <i class="bi bi-file-earmark me-2"></i> Forms
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
                                const redirect = () => window.location.href = 'admin_accounts.php';
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
                                    <tbody class="small align-middle">
                                        <?php
                                        $household_count = 0;
                                        if ($entry_result->num_rows > 0) {
                                            $entry_result->data_seek(0); // Reset result pointer
                                            while ($row = $entry_result->fetch_assoc()) {
                                                if ($row['type'] === 'household') {
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
                                        }
                                        if ($household_count === 0) {
                                            echo "<tr><td colspan='5' class='text-center text-muted'>No household entry logs found.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
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
                                    <tbody class="small align-middle">
                                        <?php
                                        $visitor_count = 0;
                                        if ($entry_result->num_rows > 0) {
                                            $entry_result->data_seek(0); // Reset result pointer
                                            while ($row = $entry_result->fetch_assoc()) {
                                                if ($row['type'] === 'visitor') {
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
                                        }
                                        if ($visitor_count === 0) {
                                            echo "<tr><td colspan='5' class='text-center text-muted'>No visitor entry logs found.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <?php $total = $result->num_rows;
                        echo "<span class='small'>Showing 1 to {$total} of {$total} entries</span>";
                        ?>
                        <nav>
                            <ul class="pagination pagination-sm m-0">
                                <li class="page-item disabled"><a class="page-link">Previous</a></li>
                                <li class="page-item active"><a class="page-link">1</a></li>
                                <li class="page-item"><a class="page-link">Next</a></li>
                            </ul>
                        </nav>
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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>