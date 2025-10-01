<?php
// ✅ FIX: Set session configuration BEFORE session_start()
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

require '../rfid-api/db.php';

// Check if user is logged in
if (!isset($_SESSION['visitor_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

$visitor_id = $_SESSION['visitor_id'];
$sql = "SELECT * FROM visitor_details WHERE visitor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $visitor_id);
$stmt->execute();
$result = $stmt->get_result();
$visitor = $result->fetch_assoc();

if (!$visitor) {
    echo "Visitor not found.";
    exit;
}

// Initialize user details
$username = $visitor['first_name']; // <- Set username directly from household query
$photo = ''; // Initialize photo; your existing profile photo block will set this later
// Only set $photo if profile_pic exists and is not null
if (!empty($visitor['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($visitor['profile_picture']);
} else {
    $photo = ''; // Explicitly empty if no image is saved
}

// ✅ SIMPLIFIED BOOKING PAGINATION - Only for this household
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total records count for this homeowner
$totalQuery = "SELECT COUNT(*) AS total FROM amenity_bookings WHERE visitor_id = ?";
$totalStmt = $conn->prepare($totalQuery);
$totalStmt->bind_param("s", $visitor_id);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $limit);

// ✅ SIMPLIFIED BOOKING QUERY - Only fetch what we need for this homeowner
$booking_sql = "SELECT 
    ab.id,
    ab.reservation_code,
    ab.amenity,
    ab.user_type,
    ab.reservation_date,
    ab.status,
    ab.created_at,
    CASE 
        WHEN ab.user_type = 'homeowner' THEN ha.first_name
        WHEN ab.user_type = 'visitor' THEN vd.first_name
        ELSE NULL
    END as first_name,
    CASE 
        WHEN ab.user_type = 'homeowner' THEN ha.middle_name
        WHEN ab.user_type = 'visitor' THEN vd.middle_name
        ELSE NULL
    END as middle_name,
    CASE 
        WHEN ab.user_type = 'homeowner' THEN ha.last_name
        WHEN ab.user_type = 'visitor' THEN vd.last_name
        ELSE NULL
    END as last_name
FROM amenity_bookings ab
LEFT JOIN household_accounts ha ON ab.homeowner_id = ha.household_id AND ab.user_type = 'homeowner'
LEFT JOIN visitor_details vd ON ab.visitor_id = vd.visitor_id AND ab.user_type = 'visitor'
WHERE ab.homeowner_id = ? 
ORDER BY ab.reservation_date DESC 
LIMIT ? OFFSET ?";

$bookings_stmt = $conn->prepare($booking_sql);
$bookings_stmt->bind_param("sii", $visitor_id, $limit, $offset);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

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
            min-height: 100vh;
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
        .sidebar .btn-toggle:not(.collapsed),
        .sidebar .logout:hover {
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
            <h1 class="h5 mb-0 fw-bold">VISITOR DASHBOARD</h1>
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
                    <li><a class="dropdown-item"
                            href="visitor_details/view_visitor.php?id=<?php echo $visitor_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav d-flex flex-column gap-1">
                <a href="dashboard.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <a href="amenity_booking/amenity_booking.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-book me-2"></i> Amenity Booking
                </a>
                <!-- Accounting -->
                <div>
                    <button
                        class="btn btn-toggle collapsed px-3 rounded py-2 d-flex align-items-center justify-content-start"
                        data-bs-toggle="collapse" data-bs-target="#acctCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="visitor_payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="#" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout"
                    style="position: fixed; bottom: 0; width: 220px;">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!--Main Content-->
        <main class="flex-fill p-4">
            <!-- Amenity Schedule -->
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white fw-semibold">Amenity Schedule</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">List of Amenity Bookings</span>
                        <a href="amenity_booking/choose_booking.php" class="btn btn-primary btn-sm">+ Create New
                            Booking</a>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-success text-white small">
                                <tr>
                                    <th>Booking Date</th>
                                    <th>Full Name</th>
                                    <th>Amenity</th>
                                    <th>Reservation Code</th>
                                    <th>Payment Status</th>
                                </tr>
                            </thead>
                            <tbody class="small align-middle">
                                <?php
                                if ($bookings_result && $bookings_result->num_rows > 0) {
                                    while ($row = $bookings_result->fetch_assoc()) {
                                        $fullName = trim(ucwords($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']));
                                        $amenity = htmlspecialchars($row['amenity']);
                                        $bookingDate = date('F d, Y', strtotime($row['reservation_date']));
                                        $resCode = htmlspecialchars($row['reservation_code']);
                                        // Status styling
                                        $status = ucfirst($row['status']);
                                        $statusClass = '';
                                        switch (strtolower($row['status'])) {
                                            case 'paid':
                                                $statusClass = 'text-success';
                                                break;
                                            case 'partial':
                                                $statusClass = 'text-warning';
                                                break;
                                            case 'pending':
                                                $statusClass = 'text-secondary';
                                                break;
                                            default:
                                                $statusClass = 'text-muted';
                                        }
                                        echo "<tr>
                                    <td>{$bookingDate}</td>
                                    <td>{$fullName}</td>
                                    <td>{$amenity}</td>
                                    <td>{$resCode}</td>
                                    <td class='{$statusClass} fw-bold'>{$status}</td>
                                  </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center text-muted'>No bookings found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalRecords > 0): ?>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="small">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $totalRecords); ?>
                                of <?php echo $totalRecords; ?> entries
                            </span>
                            <?php if ($totalPages > 1): ?>
                                <nav>
                                    <ul class="pagination pagination-sm justify-content-center mb-0">
                                        <!-- Previous button -->
                                        <li class="page-item <?php if ($page <= 1)
                                            echo 'disabled'; ?>">
                                            <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>">Previous</a>
                                        </li>
                                        <!-- Page numbers -->
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($totalPages, $page + 2);

                                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?php if ($page == $i)
                                                echo 'active'; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <!-- Next button -->
                                        <li class="page-item <?php if ($page >= $totalPages)
                                            echo 'disabled'; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo min($totalPages, $page + 1); ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>