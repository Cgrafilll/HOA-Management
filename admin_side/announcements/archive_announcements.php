<?php
session_start();

require '../../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: ../login/login.php");
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
// Fetch archived announcements
try {
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE status = 'archived' ORDER BY created_at DESC");
    $stmt->execute();
    $announcementsResult = $stmt->get_result();
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
    <link rel="icon" href="../../images/SitioSeville_Logo.png" type="image/x-icon">
    <style>
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
            <img src="../../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">COMMUNICATION</h1>
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
                            <li><a href="../admin_accounts.php" class="nav-link px-2">Admin</a></li>
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
                            <li><a href="../#" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="../entry_logs.php" class="nav-link px-2">Entry Logs</a></li>
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
                            <li><a href="../#" class="nav-link px-2 actived">Announcements</a></li>
                            <li><a href="../#" class="nav-link px-2">Events</a></li>
                            <li><a href="../#" class="nav-link px-2">Phone Book</a></li>
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
                        </ul>
                    </div>
                </div>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Announcements</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Archived Announcements</span>
                        <div class="d-flex gap-2">
                            <a href="../announcements.php"
                                class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <i class="bi bi-arrow-left-short me-1"></i>Back
                            </a>
                        </div>
                    </div>
                    <hr class="mb-3 mt-0">
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
                                    <p class="mb-3">Announcement has been re-published.</p>
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
                                    <i class="bi bi-key text-success" style="font-size: 64px;"></i>
                                    <p class="mb-2"><b>Are you sure?</b></p>
                                    <p class="mb-3">Do you really want to re-publish this announcement?</p>
                                    <p class="mb-3">This process will re-publish this announcement.</p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-success"
                                            id="confirmActivate">Activate</button>
                                        <button type="button" class="btn btn-light btn-cancel"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-success text-white small">
                                <tr>
                                    <th>Title</th>
                                    <th>Body</th>
                                    <th>Created At</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody class="small align-middle">
                                <?php if ($announcementsResult->num_rows > 0): ?>
                                    <?php while ($row = $announcementsResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($row['body'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-success activateBtn"
                                                    data-id="<?php echo $row['id']; ?>">
                                                    <i class="bi bi-check-circle me-1"></i> Activate
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No archived announcements found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <?php $total = $announcementsResult->num_rows;
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
    </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let currentId = null;

            // When clicking Activate button → open confirmation modal
            document.querySelectorAll('.activateBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    currentId = this.dataset.id;
                    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
                    confirmModal.show();
                });
            });
            document.getElementById('doneButton').addEventListener('click', function () {
                const successModalEl = document.getElementById('successModal');
                const successModal = bootstrap.Modal.getInstance(successModalEl);
                successModal.hide();
            });


            // Confirm Activate
            document.getElementById('confirmActivate').addEventListener('click', function () {
                if (!currentId) return;

                fetch('activate_announcements.php', {
                    method: 'POST',
                    body: new URLSearchParams({ id: currentId })
                })
                    .then(res => res.json())
                    .then(data => {
                        const confirmModalEl = document.getElementById('confirmModal');
                        const confirmModal = bootstrap.Modal.getInstance(confirmModalEl);
                        confirmModal.hide();

                        if (data.success) {
                            const successModalEl = document.getElementById('successModal');
                            const successModal = new bootstrap.Modal(successModalEl);
                            successModal.show();

                            // Reload when success modal closes
                            successModalEl.addEventListener('hidden.bs.modal', () => location.reload());
                        } else {
                            alert('Activation failed: ' + data.message);
                        }
                    })
                    .catch(err => {
                        alert('Error: ' + err.message);
                    });
            });
        });
    </script>
</body>
</div>