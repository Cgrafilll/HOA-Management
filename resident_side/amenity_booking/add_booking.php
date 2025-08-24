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
try {
    $stmt = $conn->prepare("SELECT * FROM household_accounts WHERE household_id = ?");
    $stmt->bind_param("s", $homeowner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $username = $user['first_name'];
        if (!empty($user['profile_picture'])) {
            $photo = 'data:image/jpeg;base64,' . base64_encode($user['profile_picture']);
        } else {
            $photo = '';
        }
    } else {
        $error_message = "Failed to fetch user details.";
    }
} catch (Exception $e) {
    $error_message = "Error fetching user details: " . $e->getMessage();
}

// Initialize amenity details
$amenity = isset($_GET['amenity']) ? urldecode($_GET['amenity']) : null;

$amenities = [
    "Clubhouse" => [
        "image" => "../../images/clubhouse.png",
        "file" => "details/clubhouse.php"
    ],
    "Gazebo" => [
        "image" => "../../images/gazebo.png",
        "file" => "details/gazebo.php"
    ],
    "Swimming Pool" => [
        "image" => "../../images/pool.png",
        "file" => "details/swimming_pool.php"
    ],
    "Basketball Court" => [
        "image" => "../../images/basketball.png",
        "file" => "details/basketball_court.php"
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resident Amenity Booking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" href="../../images/SitioSeville_Logo.png" type="image/x-icon">
<style>
* { font-family: "Montserrat", sans-serif; }
header { position: sticky; top:0; z-index:1030; background:white; padding:0.5rem 1rem; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 4px rgba(0,0,0,0.1);}
.sidebar { width:250px; height:100vh; position:fixed; top:0; left:0; background:#1F2937; overflow-y:auto; padding-top:72px;}
.sidebar a { color:#fff; text-decoration:none; display:flex; align-items:center; justify-content:space-between; padding:0.5rem 1rem; border-radius:0.375rem; margin-bottom:5px;}
.sidebar a:hover, .sidebar a.active { color:#80ed99; background:#198754; font-weight:600;}
main { margin-left:250px; padding:2rem 1rem; margin-top:72px;}
.bg-success.text-white.rounded-top.p-3 { display:flex; justify-content:center; align-items:center;}
</style>
</head>
<body class="bg-light">

<header>
    <div class="d-flex align-items-center gap-3">
        <img src="../../images/NSSHAI_crop.png" alt="NSSHAI" style="height:56px;">
        <h5 class="mb-0 fw-bold">Resident Dashboard</h5>
    </div>
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
    <a href="../dashboard.php" class="active"><i class="bi bi-house me-2"></i>Dashboard</a>
    <a href="../account.php"><i class="bi bi-file-earmark-text me-2"></i>Statement of Account</a>
    <a href="../records.php"><i class="bi bi-journal-text me-2"></i>Personal Records</a>
    <a href="../amenity_booking/amenity_booking.php"><i class="bi bi-building me-2"></i>Amenities</a>
    <a href="../login.php" class="mt-auto"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
</aside>

<main>
    <div class="bg-white shadow rounded p-3">
        <div class="bg-success text-white rounded-top p-3">
            <h5 class="mb-0 fw-bold w-100 text-center">Amenity Booking Management</h5>
        </div>
        <div class="p-3">
            <?php if($amenity && isset($amenities[$amenity])): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="small"><?= htmlspecialchars($amenity) ?></span>
                    <div>
                        <a href="choose_booking.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="bi bi-arrow-left-short me-1"></i>Back
                        </a>
                        <a href="reserve_booking.php?reserve=<?= htmlspecialchars($amenity) ?>" class="btn btn-primary btn-sm">Book Now</a>
                    </div>
                </div>
                <hr class="my-2" style="border-top:2px solid #7a7a7aff;">
                <div class="mt-4">
                    <?php include __DIR__ . '/' . $amenities[$amenity]['file']; ?>
                </div>
            <?php else: ?>
                <p class="text-danger">Invalid or missing amenity selection.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
