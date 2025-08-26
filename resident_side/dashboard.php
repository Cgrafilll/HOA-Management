<?php
session_start();
require '../rfid-api/db.php';

if (!isset($_SESSION['household_id'])) {
    header("Location: login.php?error=Please login first");
    exit;
}

$household_id = $_SESSION['household_id'];
$sql = "SELECT * FROM household_accounts WHERE household_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $household_id);
$stmt->execute();
$result = $stmt->get_result();
$resident = $result->fetch_assoc();

if (!$resident) {
    echo "Resident not found.";
    exit;
}

// Initialize user details
$username = $resident['first_name']; // <- Set username directly from household query
$photo = ''; // Initialize photo; your existing profile photo block will set this later
$email_address = $resident['email_address']; // ✅ FIX: set email_address before using it

// Fetch user details including profile photo
try {
    $stmt = $conn->prepare("SELECT * FROM household_accounts WHERE email_address = ?");
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

// Fetch announcements
$announcements_sql = "SELECT a.id, a.title, a.body, a.status, a.created_at, 
                             ad.first_name, ad.last_name 
                      FROM announcements a 
                      LEFT JOIN admin_accounts ad ON a.admin_id = ad.admin_id 
                      WHERE a.status = 'published' 
                      ORDER BY a.created_at DESC";
$announcements_result = $conn->query($announcements_sql);

// Fetch events from database
$events_sql = "SELECT e.id, e.title, e.body, e.status, e.event_date, e.created_at, 
                      ad.first_name, ad.last_name 
               FROM events e 
               LEFT JOIN admin_accounts ad ON e.admin_id = ad.admin_id 
               WHERE e.status = 'published' 
               ORDER BY e.event_date ASC, e.created_at DESC";

$events_result = $conn->query($events_sql);

// How many records per page
$limit = 10;
// Current page number (default 1 if not set)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
// Calculate offset for SQL query
$offset = ($page - 1) * $limit;
// Get total number of records from amenity_bookings for this homeowner
if ($household_id) {
    $totalQuery = "SELECT COUNT(*) AS total FROM amenity_bookings WHERE homeowner_id = ?";
    $totalStmt = $conn->prepare($totalQuery);
    $totalStmt->bind_param("i", $household_id);
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalRow = $totalResult->fetch_assoc();
    $totalRecords = $totalRow['total'];
} else {
    // If no homeowner_id, get all records
    $totalQuery = "SELECT COUNT(*) AS total FROM amenity_bookings";
    $totalResult = $conn->query($totalQuery);
    $totalRow = $totalResult->fetch_assoc();
    $totalRecords = $totalRow['total'];
}
// Calculate total pages
$totalPages = ceil($totalRecords / $limit);
// ✅ Fetch only the records for THIS PAGE (table)
if ($household_id) {
    $booking_sql = "SELECT 
        ab.id,
        ab.reservation_code,
        ab.amenity,
        ab.user_type,
        ab.reservation_date,
        ab.rate,
        ab.total_amount,
        ab.amount_paid,
        ab.status,
        ab.created_at,
        ab.homeowner_id,
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
    WHERE ab.homeowner_id = ? ORDER BY ab.reservation_date ASC LIMIT ? OFFSET ?";
    $bookings_stmt = $conn->prepare($booking_sql);
    $bookings_stmt->bind_param("iii", $household_id, $limit, $offset);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
} else {
    // If no homeowner_id, get all records for this page
    $booking_sql = "SELECT 
        ab.id,
        ab.reservation_code,
        ab.amenity,
        ab.user_type,
        ab.reservation_date,
        ab.rate,
        ab.total_amount,
        ab.amount_paid,
        ab.status,
        ab.created_at,
        ab.homeowner_id,
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
    ORDER BY ab.reservation_date ASC LIMIT ? OFFSET ?";
    $bookings_stmt = $conn->prepare($booking_sql);
    $bookings_stmt->bind_param("ii", $limit, $offset);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
}

$bookings = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Determine time slot based on 'rate'
        $timeSlot = "N/A";
        if (isset($row['rate'])) {
            if ($row['rate'] === "day") {
                $timeSlot = "9:00 AM - 5:00 PM";
            } elseif ($row['rate'] === "night") {
                $timeSlot = "5:00 PM - 10:00 PM";
            }
        }
        $bookings[] = [
            "id" => $row['id'],
            "date" => $row['reservation_date'],
            "fullName" => trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']),
            "amenity" => $row['amenity'],
            "reservationCode" => $row['reservation_code'],
            "paymentStatus" => ucfirst($row['status']), // pending → Pending
            "amount" => "₱" . number_format($row['amount_paid'], 2) .
                ($row['status'] === 'partial' ? " / ₱" . number_format($row['total_amount'], 2) : ""),
            "time" => $timeSlot,
            "homeownerId" => $row['homeowner_id']
        ];
    }
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
            <h1 class="h5 mb-0 fw-bold">HOMEOWNER DASHBOARD</h1>
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
                            href="resident_details/view_resident.php?id=<?php echo $household_id; ?>"><i
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
                <a href="report.php" class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-exclamation-triangle me-2"></i> Report Violation
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
                            <li><a href="#" class="nav-link px-2">Payments</a></li>
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
            <!-- Announcements and Events -->
            <div class="row g-4 mb-3">
                <div class="col-6">
                    <div class="card shadow-sm h-100 d-flex flex-column">
                        <div class="card-header bg-success text-white fw-semibold">Announcements</div>
                        <div class="card-body flex-grow-1 overflow-auto" style="max-height: 400px;">
                            <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
                                <?php while ($row = $announcements_result->fetch_assoc()): ?>
                                    <div class="card mb-3 shadow-sm announcement-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="announcement-title"
                                                    style="font-weight: 600;font-size: 1rem;margin-bottom: 6px;">
                                                    <?= htmlspecialchars($row['title']); ?>
                                                </div>
                                            </div>
                                            <div class="announcement-body mt-2">
                                                <?= nl2br(htmlspecialchars($row['body'])); ?>
                                            </div>
                                            <div class="announcement-meta mt-1 text-muted" style="font-size: 0.8rem;">
                                                Posted by <?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?>
                                                on <?= date("M d, Y h:i A", strtotime($row['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-megaphone" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mt-2">No announcements available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100 d-flex flex-column">
                        <div class="card-header bg-success text-white fw-semibold">Events</div>
                        <div class="card-body flex-grow-1 overflow-auto" style="max-height: 400px;">
                            <?php if ($events_result && $events_result->num_rows > 0): ?>
                                <?php while ($event_row = $events_result->fetch_assoc()): ?>
                                    <div class="card mb-3 shadow-sm event-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="event-title"
                                                    style="font-weight: 600;font-size: 1rem;margin-bottom: 6px;">
                                                    <?= htmlspecialchars($event_row['title']); ?>
                                                </div>
                                                <?php if (!empty($event_row['event_date'])): ?>
                                                    <div class="event-date mb-2">
                                                        <small class="badge bg-primary">
                                                            <i class="bi bi-calendar-event me-1"></i>
                                                            <?= date("M d, Y", strtotime($event_row['event_date'])); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="event-body mt-2">
                                                <?= nl2br(htmlspecialchars($event_row['body'])); ?>
                                            </div>
                                            <div class="event-meta mt-1 text-muted" style="font-size: 0.8rem;">
                                                Posted by
                                                <?= htmlspecialchars($event_row['first_name'] . " " . $event_row['last_name']); ?>
                                                on <?= date("M d, Y h:i A", strtotime($event_row['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-calendar-event" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mt-2">No events available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Statement of Account -->
            <section class="card mb-4 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 text-primary">Statement of Account</h5>
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td>August 2025</td>
                                <td class="text-end">₱1,500.00</td>
                            </tr>
                            <tr>
                                <td>Late Fees</td>
                                <td class="text-end">₱0.00</td>
                            </tr>
                            <tr class="fw-bold text-primary">
                                <td>Total Due</td>
                                <td class="text-end">₱1,500.00</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="text-end mt-3">
                        <button class="btn btn-primary">Pay Now</button>
                    </div>
                </div>
            </section>
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
                                if ($bookings_result->num_rows > 0) {
                                    while ($row = $bookings_result->fetch_assoc()) {
                                        $id = $row['id'];
                                        $fullName = ucwords($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                        $amenity = $row['amenity'];
                                        $bookingDate = date('F d, Y', strtotime($row['reservation_date']));
                                        $resCode = $row['reservation_code'];
                                        $statusClass = $row['status'] === 'Paid' ? 'text-success' : ($row['status'] === 'Partial' ? 'text-warning' : 'text-muted');
                                        echo "<tr>
                                                    <td>{$bookingDate}</td>
                                                    <td>{$fullName}</td>
                                                    <td>{$amenity}</td>
                                                    <td>{$resCode}</td>
                                                    <td class='{$statusClass} fw-bold'>" . ucfirst($row['status']) . "</td>
                                                </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center text-muted'>No bookings found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small">Showing 1 to <?php echo $bookings_result->num_rows; ?> entries</span>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <!-- Previous button -->
                                <li class="page-item <?php if ($page <= 1)
                                    echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <!-- Page numbers -->
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php if ($page == $i)
                                        echo 'active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <!-- Next button -->
                                <li class="page-item <?php if ($page >= $totalPages)
                                    echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>