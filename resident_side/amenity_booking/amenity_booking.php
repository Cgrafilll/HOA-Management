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
<title>Resident Booking Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body {
    font-family: 'Montserrat', sans-serif;
    margin: 0;
    padding: 0;
}

/* Header */
header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 72px;
    background-color: white;
    color: #1E40AF;
    display: flex;
    align-items: center;
    padding: 0 20px;
    z-index: 1000;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Sidebar */
.sidebar {
    width: 220px;
    position: fixed;
    top: 72px; /* below header */
    left: 0;
    bottom: 0;
    background-color: #1E3A8A;
    padding: 20px;
    display: flex;
    flex-direction: column;
}

/* Sidebar links */
.sidebar a {
    display: block;
    padding: 12px 20px;
    color: #E0E7FF;
    text-decoration: none;
    border-radius: 6px;
    margin-bottom: 5px;
    transition: background 0.3s, color 0.3s;
}

.sidebar a:hover {
    background-color: #2563EB;
    color: #fff;
}

/* Active sidebar link */
.sidebar a.active {
    background-color: #E0E7FF;
    color: #1E3A8A !important;
    font-weight: 600;
}

.sidebar a.active:hover {
    background-color: #c7d2fe;
    color: #1E3A8A !important;
}

/* Logout button */
.sidebar a.logout-link {
    margin-top: auto;
    background-color: #DC2626;
    color: #fff;
    font-weight: 600;
    text-align: center;
}

.sidebar a.logout-link:hover {
    background-color: #B91C1C;
}

/* Main content */
main {
    margin-left: 220px; /* sidebar width */
    margin-top: 72px;   /* header height */
    padding: 20px;
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
    display:flex;
    justify-content: space-between;
    align-items:center;
}

.calendar-nav button {
    background: rgba(255,255,255,0.2);
    border:none;
    color:white;
    padding:5px 10px;
    margin:0 2px;
    border-radius:4px;
    cursor:pointer;
}

.calendar-nav button.active {
    background: rgba(255,255,255,0.4);
}

.calendar-grid {
    display:grid;
    grid-template-columns: repeat(7, 1fr);
    border-left:1px solid #dee2e6;
    border-top:1px solid #dee2e6;
}

.calendar-day-header {
    background:#f8f9fa;
    padding:5px;
    font-weight:600;
    text-align:center;
    border-right:1px solid #dee2e6;
    border-bottom:1px solid #dee2e6;
}

.calendar-day {
    min-height:100px;
    border-right:1px solid #dee2e6;
    border-bottom:1px solid #dee2e6;
    padding:5px;
    position:relative;
    background:white;
}

.calendar-day.other-month {
    background:#f1f5f9;
    color:#6c757d;
}

.calendar-day.today {
    background:#e3f2fd;
}

.booking-item {
    font-size:0.75rem;
    padding:2px 4px;
    margin-bottom:2px;
    border-radius:3px;
    color:white;
    cursor:pointer;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

</style>
</head>
<body>
<header>
    <div class="me-3"><img src="../../images/NSSHAI_crop.png" alt="NSSHAI" style="height: 50px;"></div>
    <h5 class="mb-0 flex-grow-1 text-dark">Resident Dashboard</h5>
    <div class="d-flex align-items-center gap-2">
        <span>Hello, <?= htmlspecialchars($username) ?></span>
        <div style="width:40px;height:40px;">
            <?php if($photo): ?>
                <img src="<?= htmlspecialchars($photo) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:50%;">
            <?php else: ?>
                <i class="bi bi-person-circle" style="font-size:32px;"></i>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="flex min-h-screen">
        <aside class="sidebar d-flex flex-column p-3">
            <h1 class="h5 fw-bold mb-4 text-white">HOA Resident</h1>
            <nav class="nav flex-column gap-2">
                <a href="../dashboard.php" 
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    Dashboard
                </a>

                <a href="account.php" 
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'account.php' ? 'active' : ''; ?>">
                    Statement of Account
                </a>

                <a href="records.php" 
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'records.php' ? 'active' : ''; ?>">
                    Personal Records
                </a>

                <a href="amenity_booking/amenity_booking.php" 
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'amenity_booking.php' ? 'active' : ''; ?>">
                    Amenities Schedule
                </a>
            </nav>
            <hr class="border-light my-3">
            <a href="../logout.php" class="logout-link">Logout</a>
        </aside>
<main>
    <h4>Amenity Booking Management</h4>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="bookingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings" type="button" role="tab">Bookings</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar" type="button" role="tab">Calendar View</button>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <!-- Bookings Table Tab -->
        <div class="tab-pane fade show active" id="bookings" role="tabpanel">
            <a href="choose_booking.php" class="btn btn-primary btn-sm mb-3 float-end">+ Create New Booking</a>
            <table class="table table-bordered table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Date</th>
                        <th>Amenity</th>
                        <th>Reservation Code</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($bookings_result->num_rows > 0): ?>
                        <?php while($row = $bookings_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['reservation_date'] ?></td>
                                <td><?= $row['amenity'] ?></td>
                                <td><?= $row['reservation_code'] ?></td>
                                <td><?= ucfirst($row['status']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted">No bookings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Calendar Tab -->
        <div class="tab-pane fade" id="calendar" role="tabpanel">
            <div class="calendar-container">
                <div class="calendar-header">
                    <div class="calendar-nav">
                        <button onclick="previousMonth()"><i class="bi bi-chevron-left"></i></button>
                        <button onclick="nextMonth()"><i class="bi bi-chevron-right"></i></button>
                    </div>
                    <h5 id="monthYear">August 2025</h5>
                    <div class="calendar-nav">
                        <button id="monthBtn" class="active" onclick="setView('month')">Month</button>
                    </div>
                </div>
                <div class="calendar-grid" id="calendarGrid"></div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const bookings = <?= json_encode($bookings) ?>;
let currentDate = new Date();
function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    const monthYear = document.getElementById('monthYear');
    grid.innerHTML = '';

    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    monthYear.textContent = `${months[currentDate.getMonth()]} ${currentDate.getFullYear()}`;

    const dayHeaders = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    dayHeaders.forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = day;
        grid.appendChild(header);
    });

    const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(),1);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay());

    const today = new Date();
    for(let i=0;i<42;i++){
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + i);
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        if(date.getMonth() !== currentDate.getMonth()) dayElement.classList.add('other-month');
        if(date.toDateString() === today.toDateString()) dayElement.classList.add('today');

        const dayNumber = document.createElement('div');
        dayNumber.textContent = date.getDate();
        dayElement.appendChild(dayNumber);

        const dateStr = date.toISOString().split('T')[0];
        const dayBookings = bookings.filter(b => b.date === dateStr);
        dayBookings.forEach(b => {
            const div = document.createElement('div');
            div.className = 'booking-item ' + (b.paymentStatus==='Paid'?'bg-success':b.paymentStatus==='Partial'?'bg-warning':'bg-secondary');
            div.textContent = `${b.amenity} - ${b.fullName}`;
            dayElement.appendChild(div);
        });
        grid.appendChild(dayElement);
    }
}
function previousMonth(){ currentDate.setMonth(currentDate.getMonth()-1); renderCalendar();}
function nextMonth(){ currentDate.setMonth(currentDate.getMonth()+1); renderCalendar();}
function setView(view){ renderCalendar(); }

renderCalendar();
</script>
</body>
</html>

