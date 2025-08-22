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
        .collapse ul li a:hover {
            color: #80ed99;
        }

        .sidebar .nav-link.active,
        .sidebar .btn-toggle:not(.collapsed) {
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
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <div class="me-4" style="width: 250px;">
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">ADMIN DASHBOARD</h1>
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
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
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
                            <li><a href="#" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="entry_logs.php" class="nav-link px-2">Entry Logs</a></li>
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
                        </ul>
                    </div>
                </div>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-grow-1 p-4">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                                <span class="text-danger fs-4">⚠️</span>
                            </div>
                            <div>
                                <h5 class="mb-0">4</h5>
                                <small class="text-muted">Violations</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <span class="text-primary fs-4">⏳</span>
                            </div>
                            <div>
                                <h5 class="mb-0">12</h5>
                                <small class="text-muted">Bookings</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <span class="text-success fs-4">👤</span>
                            </div>
                            <div>
                                <h5 class="mb-0">256</h5>
                                <small class="text-muted">Members</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Announcements and Events -->
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100 d-flex flex-column">
                        <div class="card-header bg-success text-white fw-semibold">Announcements</div>
                        <div class="card-body flex-grow-1 overflow-auto" style="max-height: 300px;"></div>
                        <div class="card-footer bg-light text-end">
                            <button class="btn btn-success btn-sm">👁 View More</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100 d-flex flex-column">
                        <div class="card-header bg-success text-white fw-semibold">Events</div>
                        <div class="card-body flex-grow-1 overflow-auto" style="max-height: 300px;"></div>
                        <div class="card-footer bg-light text-end d-flex justify-content-end gap-2">
                            <button class="btn btn-success btn-sm">📅 View Calendar</button>
                            <button class="btn btn-success btn-sm">👁 View Events</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>