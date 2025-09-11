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
            <h1 class="h5 mb-0 fw-bold">ACCOUNTING</h1>
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
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#accountsCollapse" aria-expanded="true">
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
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#acctCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse show" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="../invoice.php" class="nav-link px-2 actived">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="../login/logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout"
                    style="position: fixed; bottom: 0; width: 220px;">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <!-- Header -->
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">New Invoice</h5>
                </div>

                <!-- Back Button -->
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0">Fill out the form below to issue a new invoice</span>
                    <a href="../invoice.php"
                    class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="my-0">

                <!-- Form -->
                <form action="process_add_invoice.php" method="POST" enctype="multipart/form-data" class="p-4">
                    <div class="row g-3">

                        <!-- Household ID (FK) -->
                        <div class="col-md-6">
                            <label for="household_id" class="form-label fw-semibold">Household</label>
                            <select class="form-select" id="household_id" name="household_id" required>
                                <option value="" selected disabled>Select Household</option>
                                <?php
                                // Populate dropdown with household_accounts
                                $households = $conn->query("SELECT household_id, CONCAT(first_name, ' ', last_name) AS name FROM household_accounts");
                                while ($row = $households->fetch_assoc()) {
                                    echo "<option value='{$row['household_id']}'>{$row['household_id']} - {$row['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Billing Month -->
                        <div class="col-md-6">
                            <label for="billing_month" class="form-label fw-semibold">Billing Month</label>
                            <input type="month" class="form-control" id="billing_month" name="billing_month" required>
                        </div>

                        <!-- Balance Remaining -->
                        <div class="col-md-6">
                            <label for="balance_remaining" class="form-label fw-semibold">Balance to be Paid:</label>
                            <input type="number" step="0.01" class="form-control" id="balance_remaining" name="balance_remaining" required>
                        </div>

                        <!-- Payment Date -->
                        <div class="col-md-6">
                            <label for="payment_date" class="form-label fw-semibold">Due Date:</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" readonly>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save me-1"></i> Save Invoice
                        </button>
                    </div>
                    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title fw-bold">Confirm Publish</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-question-circle text-primary" style="font-size: 64px;"></i>
                                        <p class="mb-2"><b>Are you sure?</b></p>
                                        <p class="mb-3">Do you really want to publish this event?</p>
                                        <button type="button" class="btn btn-primary" id="confirmPublish">Yes,
                                            Publish</button>
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
                                        <p class="mt-3 mb-2"><b>Event published successfully.</b></p>
                                        <button type="button" class="btn btn-success"
                                            data-bs-dismiss="modal">OK</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Error Modal -->
                        <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title fw-bold">Error</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-x-circle-fill text-danger" style="font-size: 64px;"></i>
                                        <p class="mt-3 mb-2"><b>Something went wrong.</b></p>
                                        <p id="errorMessage">Please try again later.</p>
                                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
    const form = document.querySelector("form");
    const requiredFields = Array.from(form.querySelectorAll("input:not([type='file']), select"));

    form.addEventListener("submit", function(e) {
        let valid = true;

        requiredFields.forEach(field => {
            if (!field.value) {
                field.classList.add("border", "border-danger");
                valid = false;
            } else {
                field.classList.remove("border", "border-danger");
            }
        });

        if (!valid) {
            e.preventDefault(); // Stop form submission
            alert("Please fill in all required fields.");
        }
    });

    // Remove red border when user types or selects
    requiredFields.forEach(field => {
        field.addEventListener("input", () => field.classList.remove("border", "border-danger"));
        field.addEventListener("change", () => field.classList.remove("border", "border-danger"));
    });
});
    document.addEventListener("DOMContentLoaded", function () {
        <?php if (isset($_SESSION['modal'])): ?>
            var modalId = "<?php echo $_SESSION['modal']; ?>Modal";
            <?php if ($_SESSION['modal'] === 'error' && isset($_SESSION['error_message'])): ?>
                document.getElementById("errorMessage").innerText = "<?php echo addslashes($_SESSION['error_message']); ?>";
            <?php endif; ?>
            var myModal = new bootstrap.Modal(document.getElementById(modalId));
            myModal.show();
            <?php unset($_SESSION['modal']); unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    });
    document.addEventListener("DOMContentLoaded", function() {
    const billingMonth = document.getElementById('billing_month');
    const paymentDate = document.getElementById('due_date');

    // Function to set payment date to 28th of the selected month
    function updatePaymentDate() {
        if (billingMonth.value) {
            // billingMonth.value format: YYYY-MM
            const [year, month] = billingMonth.value.split('-');
            paymentDate.value = `${year}-${month}-28`;
        } else {
            // If no month selected, leave payment_date empty
            paymentDate.value = '';
        }
    }

    // Set default on page load
    updatePaymentDate();

    // Update payment_date whenever billing_month changes
    billingMonth.addEventListener('change', updatePaymentDate);
});
</script>
</body>
</html>