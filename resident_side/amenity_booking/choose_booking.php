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
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if ($user) {
    $username = $user['first_name'];
    if (!empty($user['profile_picture'])) {
        $photo = 'data:image/jpeg;base64,' . base64_encode($user['profile_picture']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resident Amenity Booking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { font-family: 'Montserrat', sans-serif; }
header {
    position: fixed;
    top: 0;
    width: 100%;
    height: 72px;
    background-color: white;
    color: #1E40AF;
    display: flex;
    align-items: center;
    padding: 0 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 1000;
}
.sidebar {
    width: 220px;
    position: fixed;
    top: 72px;
    left: 0;
    bottom: 0;
    background-color: #1E3A8A;
    padding: 20px;
    display: flex;
    flex-direction: column;
}
.sidebar a {
    display: block;
    padding: 12px 20px;
    color: #E0E7FF;
    text-decoration: none;
    border-radius: 6px;
    margin-bottom: 5px;
    transition: background 0.3s, color 0.3s;
}
.sidebar a:hover { background-color: #2563EB; color: #fff; }
.sidebar a.active { background-color: #E0E7FF; color: #1E3A8A; font-weight: 600; }
.sidebar a.logout-link { margin-top: auto; background-color: #DC2626; color: #fff; font-weight: 600; text-align:center; }
.sidebar a.logout-link:hover { background-color: #B91C1C; }
main { margin-left: 220px; margin-top: 72px; padding: 20px; }
.booking-card { border-radius: 1rem; overflow: hidden; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display:flex; flex-direction: column; }
.booking-card img { width: 100%; height: 300px; object-fit: cover; }
.booking-card .card-body { flex: 1 1 auto; display:flex; flex-direction:column; justify-content:flex-end; align-items:center; padding:1rem; }
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

<aside class="sidebar">
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="account.php">Statement of Account</a>
    <a href="records.php">Personal Records</a>
    <a href="amenity_booking/amenity_booking.php">Amenities Schedule</a>
    <a href="login.php" class="logout-link">Logout</a>
</aside>

<main>
    <div class="bg-white shadow rounded p-3">
        <div class="bg-success text-white rounded-top p-3 text-center">
            <h5 class="mb-0 fw-bold">Amenity Booking Management</h5>
        </div>
        <div class="p-3 d-flex justify-content-between align-items-center">
            <span>Select an Amenity</span>
            <a href="amenity_booking.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left-short"></i> Back
            </a>
        </div>
        <hr class="mb-3 mt-0">

        <div class="row g-3">
            <!-- Clubhouse -->
            <div class="col-12 col-md-6 d-flex align-items-stretch">
                <div class="booking-card">
                    <img src="../../images/clubhouse.png" alt="Clubhouse">
                    <div class="card-body w-100">
                        <h6 class="card-title mb-2">Clubhouse</h6>
                        <a href="add_booking.php?amenity=Clubhouse" class="btn btn-primary w-100">Book</a>
                    </div>
                </div>
            </div>
            <!-- Gazebo -->
            <div class="col-12 col-md-6 d-flex align-items-stretch">
                <div class="booking-card">
                    <img src="../../images/gazebo.png" alt="Gazebo">
                    <div class="card-body w-100">
                        <h6 class="card-title mb-2">Gazebo</h6>
                        <a href="add_booking.php?amenity=Gazebo" class="btn btn-primary w-100">Book</a>
                    </div>
                </div>
            </div>
            <!-- Swimming Pool -->
            <div class="col-12 col-md-6 d-flex align-items-stretch">
                <div class="booking-card">
                    <img src="../../images/pool.png" alt="Swimming Pool">
                    <div class="card-body w-100">
                        <h6 class="card-title mb-2">Swimming Pool</h6>
                        <a href="add_booking.php?amenity=Swimming%20Pool" class="btn btn-primary w-100">Book</a>
                    </div>
                </div>
            </div>
            <!-- Basketball Court -->
            <div class="col-12 col-md-6 d-flex align-items-stretch">
                <div class="booking-card">
                    <img src="../../images/basketball.png" alt="Basketball Court">
                    <div class="card-body w-100">
                        <h6 class="card-title mb-2">Basketball Court</h6>
                        <a href="add_booking.php?amenity=Basketball%20Court" class="btn btn-primary w-100">Book</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>