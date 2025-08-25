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

        .booking-card {
            border-radius: 1rem;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
        }

        .booking-card img {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }

        .booking-card .card-body {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            padding: 1rem;
        }
    </style>
</head>

<body>
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
                <a href="amenity_booking.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
                    <i class="bi bi-book me-2"></i> Amenity Booking
                </a>
                <a href="../report.php" class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
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
                    <h5 class="mb-0 fw-bold w-100">Amenity Booking Management</h5>
                </div>
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="mb-0">Select an Amenity</span>
                    <a href="amenity_booking.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="mb-3 mt-0">
                <!-- Responsive Amenity Booking Grid -->
                <div class="container-fluid booking-grid-container">
                    <div class="row g-2 h-100">
                        <!-- Clubhouse -->
                        <div class="col-12 col-md-6 d-flex align-items-stretch">
                            <div class="booking-card w-100">
                                <img src="../../images/clubhouse.png" alt="Clubhouse" class="object-fit-cover"
                                    style="height: 300px;">
                                <div class="card-body w-100">
                                    <h6 class="card-title mb-2">Clubhouse</h6>
                                    <a href="add_booking.php?amenity=Clubhouse" class="btn btn-primary w-100">Book</a>
                                </div>
                            </div>
                        </div>
                        <!-- Gazebo -->
                        <div class="col-12 col-md-6 d-flex align-items-stretch">
                            <div class="booking-card w-100">
                                <img src="../../images/gazebo.png" alt="Gazebo" class="object-fit-cover"
                                    style="height: 300px;">
                                <div class="card-body w-100">
                                    <h6 class="card-title mb-2">Gazebo</h6>
                                    <a href="add_booking.php?amenity=Gazebo" class="btn btn-primary w-100">Book</a>
                                </div>
                            </div>
                        </div>
                        <!-- Swimming Pool -->
                        <div class="col-12 col-md-6 d-flex align-items-stretch">
                            <div class="booking-card w-100">
                                <img src="../../images/pool.png" alt="Swimming Pool" class="object-fit-cover"
                                    style="height: 300px;">
                                <div class="card-body w-100">
                                    <h6 class="card-title mb-2">Swimming Pool</h6>
                                    <a href="add_booking.php?amenity=Swimming%20Pool"
                                        class="btn btn-primary w-100">Book</a>
                                </div>
                            </div>
                        </div>
                        <!-- Basketball Court -->
                        <div class="col-12 col-md-6 d-flex align-items-stretch">
                            <div class="booking-card w-100">
                                <img src="../../images/basketball.png" alt="Basketball Court" class="object-fit-cover"
                                    style="height: 300px;">
                                <div class="card-body w-100">
                                    <h6 class="card-title mb-2">Basketball Court</h6>
                                    <a href="add_booking.php?amenity=Basketball%20Court"
                                        class="btn btn-primary w-100">Book</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
</body>

</html>