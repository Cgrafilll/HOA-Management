<?php
session_start();
require '../../rfid-api/db.php';

if (!isset($_SESSION['household_id'])) {
    header("Location: login.php");
    exit;
}

$homeowner_id = $_SESSION['household_id'];
$email_address = $_SESSION['email_address'];
$username = $photo = '';

// Fetch resident details
$stmt = $conn->prepare("SELECT * FROM household_accounts WHERE household_id = ?");
$stmt->bind_param("s", $homeowner_id);
$stmt->execute();
$userResult = $stmt->get_result();
$user = $userResult->fetch_assoc();
if ($user) {
    $username = $user['first_name'];
    if (!empty($user['profile_picture'])) {
        $photo = 'data:image/jpeg;base64,' . base64_encode($user['profile_picture']);
    }
}

// Fetch bookings
$booking_sql = "SELECT * FROM amenity_bookings WHERE homeowner_id = ? ORDER BY reservation_date DESC";
$stmt = $conn->prepare($booking_sql);
$stmt->bind_param("s", $homeowner_id);
$stmt->execute();
$bookings_result = $stmt->get_result();

// Calendar JSON
$sql = "SELECT id, first_name, middle_name, last_name, amenity, reservation_code, reservation_date, status, total_amount, amount_paid, rate
        FROM amenity_bookings WHERE homeowner_id = ? ORDER BY reservation_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $homeowner_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $timeSlot = $row['rate'] === "day" ? "9:00 AM - 5:00 PM" : "5:00 PM - 10:00 PM";
    $bookings[] = [
        "id" => $row['id'],
        "date" => $row['reservation_date'],
        "fullName" => trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']),
        "amenity" => $row['amenity'],
        "reservationCode" => $row['reservation_code'],
        "paymentStatus" => ucfirst($row['status']),
        "amount" => "$" . number_format($row['amount_paid'], 2) . ($row['status'] === 'partial' ? " / $" . number_format($row['total_amount'], 2) : ""),
        "time" => $timeSlot
    ];
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        /* Calendar styles */
        .calendar-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
        }

        .calendar-header {
            background: #2563EB;
            color: white;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .calendar-nav button {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 4px;
            cursor: pointer;
        }

        .calendar-nav button.active {
            background: rgba(255, 255, 255, 0.4);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border-left: 1px solid #dee2e6;
            border-top: 1px solid #dee2e6;
        }

        .calendar-day-header {
            background: #f8f9fa;
            padding: 5px;
            font-weight: 600;
            text-align: center;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
        }

        .calendar-day {
            min-height: 100px;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            padding: 5px;
            position: relative;
            background: white;
        }

        .calendar-day.other-month {
            background: #f1f5f9;
            color: #6c757d;
        }

        .calendar-day.today {
            background: #e3f2fd;
        }

        .booking-item {
            font-size: 0.75rem;
            padding: 2px 4px;
            margin-bottom: 2px;
            border-radius: 3px;
            color: white;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
            <h1 class="h5 mb-0 fw-bold text-dark">AMENITY BOOKING</h1>
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
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav d-flex flex-column gap-1">
                <a href="../dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <a href="amenity_booking/amenity_booking.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
                    <i class="bi bi-book me-2"></i> Amenity Booking
                </a>
                <a href="#" class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
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
                    <i class="bi bi-box-arrow-left me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Amenity Booking Management</h5>
                </div>
                <!-- Tabs -->
                <ul class="nav nav-tabs my-3" id="dashboardTabs">
                    <li class="nav-item">
                        <a class="nav-link active link-dark" id="bookings-tab" data-bs-toggle="tab" href="#bookings"
                            role="tab">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link link-secondary" id="calendar-tab" data-bs-toggle="tab" href="#calendar"
                            role="tab">Calendar View</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link link-secondary" id="reschedule-tab" data-bs-toggle="tab" href="#reschedule"
                            role="tab">Reschedule Requests</a>
                    </li>
                </ul>
                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Bookings Table -->
                    <div class="tab-pane fade show active" id="bookings" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small">List of Amenity Bookings</span>
                            <a href="choose_booking.php" class="btn btn-primary btn-sm">+ Create New
                                Booking</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-success text-white small">
                                    <tr>
                                        <th>Booking Date</th>
                                        <th>Full Name</th>
                                        <th>Amenity</th>
                                        <th>Reservation Code</th>
                                        <th>Payment Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="small align-middle">
                                    <?php
                                    if ($bookings_result->num_rows > 0) {
                                        while ($row = $bookings_result->fetch_assoc()) {
                                            $id = $row['id'];
                                            $fullName = ucwords($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
                                            $amenity = $row['amenity'];
                                            $bookingDate = $row['reservation_date'];
                                            $resCode = $row['reservation_code'];
                                            $statusClass = $row['status'] === 'Paid' ? 'text-success' : ($row['status'] === 'Partial' ? 'text-warning' : 'text-muted');
                                            echo "<tr>
                                                    <td>{$bookingDate}</td>
                                                    <td>{$fullName}</td>
                                                    <td>{$amenity}</td>
                                                    <td>{$resCode}</td>
                                                    <td class='{$statusClass} fw-bold'>" . ucfirst($row['status']) . "</td>
                                                    <td class='text-center'>
                                                        <div class='dropdown'>
                                                            <button class='btn btn-sm btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>Action</button>
                                                            <ul class='dropdown-menu'>
                                                                <li><a class='dropdown-item' href='#'>View Details</a></li>
                                                            </ul>
                                                        </div>
                                                    </td>
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
                    <!-- Calendar View -->
                    <div class="tab-pane fade" id="calendar" role="tabpanel">
                        <!-- Calendar Controls -->
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-4">
                                <div class="legend">
                                    <div class="legend-item">
                                        <div class="legend-color paid bg-success"></div>
                                        <span>Paid</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color partial bg-warning"></div>
                                        <span>Partial Payment</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color pending bg-secondary"></div>
                                        <span>Pending Payment</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8 text-end">
                                <button class="btn btn-primary btn-sm" onclick="goToToday()">
                                    <i class="bi bi-calendar-date me-1"></i>Today
                                </button>
                            </div>
                        </div>
                        <!-- Calendar -->
                        <div class="calendar-container shadow-sm">
                            <div
                                class="calendar-header bg-success text-white p-3 d-flex justify-content-between align-items-center">
                                <div class="calendar-nav d-flex gap-2">
                                    <button onclick="previousMonth()">
                                        <i class="bi bi-chevron-left"></i>
                                    </button>
                                    <button onclick="nextMonth()">
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                                <h4 class="mb-0" id="monthYear">August 2025</h4>
                                <div class="calendar-nav d-flex gap-2">
                                    <button id="monthBtn" class="active" onclick="setView('month')">Month</button>
                                    <button id="weekBtn" onclick="setView('week')">Week</button>
                                </div>
                            </div>
                            <div class="calendar-grid" id="calendarGrid">
                                <!-- Calendar will be generated here -->
                            </div>
                        </div>
                        <!-- Booking Details Modal -->
                        <div class="modal fade booking-modal" id="bookingModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Booking Details</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body" id="modalContent">
                                        <!-- Booking details will be populated here -->
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary">Edit Booking</button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reschedule Requests -->
                    <div class="tab-pane fade" id="reschedule" role="tabpanel">
                        <!-- Bookings Table -->
                        <div class="tab-pane fade show active" id="bookings" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="small">List of Amenity Bookings</span>
                                <a href="amenity_booking/choose_booking.php" class="btn btn-primary btn-sm">+ Create New
                                    Booking</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="bg-success text-white small">
                                        <tr>
                                            <th>#</th>
                                            <th>Booking Date</th>
                                            <th>Full Name</th>
                                            <th>Amenity</th>
                                            <th>Reservation Code</th>
                                            <th>Payment Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small align-middle">
                                        <?php
                                        if ($bookings_result->num_rows > 0) {
                                            while ($row = $bookings_result->fetch_assoc()) {
                                                $id = $row['booking_id'];
                                                $fullName = $row['full_name'];
                                                $amenity = $row['amenity'];
                                                $bookingDate = $row['booking_date'];
                                                $resCode = $row['reservation_code'];
                                                $statusClass = $row['payment_status'] === 'Paid' ? 'text-success' : ($row['payment_status'] === 'Partial' ? 'text-warning' : 'text-muted');
                                                echo "<tr>
                                                <td>{$id}</td>
                                                <td>{$bookingDate}</td>
                                                <td>{$fullName}</td>
                                                <td>{$amenity}</td>
                                                <td>{$resCode}</td>
                                                <td class='{$statusClass} fw-bold'>{$row['payment_status']}</td>
                                                <td>
                                                    <div class='dropdown'>
                                                        <button class='btn btn-sm btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>Action</button>
                                                        <ul class='dropdown-menu'>
                                                            <li><a class='dropdown-item' href='#'>View Details</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='7' class='text-center text-muted'>No bookings found.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="small">Showing 1 to <?php echo $bookings_result->num_rows; ?>
                                    entries</span>
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
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const bookings = <?= json_encode($bookings) ?>;
        let currentDate = new Date();
        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            const monthYear = document.getElementById('monthYear');
            grid.innerHTML = '';

            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            monthYear.textContent = `${months[currentDate.getMonth()]} ${currentDate.getFullYear()}`;

            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                const header = document.createElement('div');
                header.className = 'calendar-day-header';
                header.textContent = day;
                grid.appendChild(header);
            });

            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());

            const today = new Date();
            for (let i = 0; i < 42; i++) {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                if (date.getMonth() !== currentDate.getMonth()) dayElement.classList.add('other-month');
                if (date.toDateString() === today.toDateString()) dayElement.classList.add('today');

                const dayNumber = document.createElement('div');
                dayNumber.textContent = date.getDate();
                dayElement.appendChild(dayNumber);

                const dateStr = date.toISOString().split('T')[0];
                const dayBookings = bookings.filter(b => b.date === dateStr);
                dayBookings.forEach(b => {
                    const div = document.createElement('div');
                    div.className = 'booking-item ' + (b.paymentStatus === 'Paid' ? 'bg-success' : b.paymentStatus === 'Partial' ? 'bg-warning' : 'bg-secondary');
                    div.textContent = `${b.amenity} - ${b.fullName}`;
                    dayElement.appendChild(div);
                });
                grid.appendChild(dayElement);
            }
        }
        function previousMonth() { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); }
        function nextMonth() { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); }
        function setView(view) { renderCalendar(); }

        renderCalendar();
    </script>
</body>

</html>