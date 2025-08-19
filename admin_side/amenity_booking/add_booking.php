<?php
session_start();
require '../../rfid-api/db.php';

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
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <div class="me-4" style="width: 250px;">
            <img src="../../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
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
                            <li><a href="../admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="../household_accounts.php" class="nav-link px-2">Household</a></li>
                            <li><a href="../visitor_accounts.php" class="nav-link px-2">Visitors</a></li>
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
                            <li><a href="../amenity_booking.php" class="nav-link px-2 actived">Amenity Booking</a></li>
                            <li><a href="#" class="nav-link px-2">Violation Tracking</a></li>
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
                    <h5 class="mb-0 fw-bold w-100">Amenity Booking Management</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="small">Swimming Pool</span>
                    <div>
                        <a href="choose_booking.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="bi bi-arrow-left-short me-1"></i>Back
                        </a>
                        <a href="household/add_household.php" class="btn btn-primary btn-sm">Book Now</a>
                    </div>
                </div>
                <hr class="my-2" style="border-top: 2px solid #7a7a7aff";>
                <!-- Pool Image -->
                <div class="my-3" 
                    style="overflow: hidden; border-radius: 0.5rem; 
                            height: 450px; 
                            background-image: url('../../images/pool.png'); 
                            background-size: 90%;   /* zoom level */
                            background-position: center; 
                            background-repeat: no-repeat;">
                </div>
                <!-- Pool Guidelines -->
                <div class="mt-4">
                    <h5 class="fw-bold">CLUBHOUSE POOL USAGE GUIDELINES</h5>
                    <p>
                        We're happy to announce that our pool is now open to walk-ins! To ensure a safe and enjoyable
                        experience for everyone, please take note of the following guidelines:
                    </p>

                    <h6 class="fw-bold mt-3">General Access of Pool:</h6>
                    <div class="ms-3">
                        <p class="mb-1">Walk-in Homeowners/Guests</p>
                        <p class="mb-1">We now welcome walk-ins at a ₱100 rate per head.</p>
                        <div class="ms-3">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle-fill me-2"></i>No need for reservations</li>
                                <li><i class="bi bi-check-circle-fill me-2"></i>No limit to one family per day</li>
                                <li><i class="bi bi-check-circle-fill me-2"></i>First-come, first-served basis no longer applies</li>
                            </ul>
                        </div>
                    </div>              
                    <h6 class="fw-bold mt-3">Exclusive Use of Pool:</h6>
                    <div class="ms-3">
                        <p class="mb-1">Special Requests for Pool Exclusivity:</p>
                        <p class="mb-1">We continue to accommodate exclusive use of the pool upon special request:</p>
                        <div class="ms-3">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle-fill me-2"></i>Minimum of 10 guests required</li>
                                <li><i class="bi bi-check-circle-fill me-2"></i>₱200 per head</li>
                                <li><i class="bi bi-check-circle-fill me-2"></i>Subject to approval and availability</li>
                                <li><i class="bi bi-check-circle-fill me-2"></i>Prior arrangement is necessary</li>
                            </ul>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-4">POOL RATES</h5>
                    <div class="container my-5">
                        <div class="row">
                             <!-- Homeowner Table -->
                            <div class="col-md-6">
                                <h6 class="fw-bold">Homeowner</h6>
                                <div style="max-width: 100%;">
                                    <table class="table table-sm text-center table-bordered">
                                        <thead>
                                            <tr>
                                                <th class="bg-success text-white">Day</th>
                                                <th class="bg-success text-white">Night</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>9:00 AM - 5:00 PM</td>
                                                <td>5:00 PM - 10:00 PM</td>
                                            </tr>
                                            <tr>
                                                <td>₱100.00 / per person</td>
                                                <td>₱200.00 / per person</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Guest Table -->
                            <div class="col-md-6">
                                <h6 class="fw-bold">Guest</h6>
                                <div style="max-width: 100%;">
                                    <table class="table table-sm text-center table-bordered">
                                        <thead>
                                            <tr>
                                                <th class="bg-success text-white">Day</th>
                                                <th class="bg-success text-white">Night</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>9:00 AM - 5:00 PM</td>
                                                <td>5:00 PM - 10:00 PM</td>
                                            </tr>
                                            <tr>
                                                <td>₱200.00 / per person</td>
                                                <td>₱300.00 / per person</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <!-- Add-Ons Table -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="fw-bold">Add-Ons</h6>
                                <div style="max-width: 635px;">
                                    <table class="table table-sm text-center table-bordered">
                                        <thead>
                                            <tr>
                                                <th class="bg-success text-white">Tables</th>
                                                <th class="bg-success text-white">Chairs</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>₱20.00 / per table</td>
                                                <td>₱12.00 / per chair</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Reminders Section -->
                    <div class="mt-5">
                        <h6 class="fw-bold">REMINDERS FOR ALL GUESTS:</h6>
                        <ul>
                            <li>Please observe proper swimwear at all times.</li>
                            <li>Children must be supervised by adults.</li>
                            <li>No lifeguard on duty — swim at your own risk.</li>
                            <li>Keep the area clean and dispose of trash properly.</li>
                            <li>Alcohol, loud music, and disruptive behavior are not allowed.</li>
                            <li>Pool hours: 9:00 AM - 5:00 PM</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
                