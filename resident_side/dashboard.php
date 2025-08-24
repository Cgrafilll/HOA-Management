<?php
session_start();
require '../rfid-api/db.php';

if (!isset($_SESSION['household_id'])) {
    header("Location: login.php?error=Please login first");
    exit;
}

$resident_id = $_SESSION['household_id'];
$sql = "SELECT * FROM household_accounts WHERE household_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $resident_id);
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
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap');

    * {
      font-family: "Montserrat", sans-serif;
    }

    /* Header */
    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 72px; /* adjust if needed */
        background-color: #1E40AF; /* darker blue */
        color: white;
        display: flex;
        align-items: center;
        padding: 0 20px;
        z-index: 1000;
    }

    /* Sidebar */
    .sidebar {
        width: 250px;
        height: calc(100vh - 72px); /* full height minus header */
        position: fixed;
        top: 72px; /* right below header */
        left: 0;
        background-color: #1E3A8A; /* slightly darker than header for contrast */
        color: #fff;
        overflow-y: auto;
        padding: 20px 0;
    }

    /* Sidebar links */
    .sidebar a {
        display: block;
        padding: 12px 20px;
        color: #E0E7FF; /* soft light blue text */
        text-decoration: none;
        transition: background 0.3s, color 0.3s;
    }

    .sidebar a:hover {
        background-color: #2563EB; /* highlight on hover */
        color: #fff;
    }

    /* Main Content */
    main {
        margin-left: 250px; /* space for sidebar */
        margin-top: 72px;   /* space for header */
        padding: 20px;
    }

        .sidebar button {
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        

        /* Active Sidebar Link Inverse Colors */
        .sidebar .nav-link.active {
            background-color: #E0E7FF; /* light blue background */
            color: #1E3A8A !important; /* dark blue text */
            font-weight: 600;
        }
        .sidebar .nav-link.active:hover {
            background-color: #c7d2fe; /* slightly darker light blue on hover */
            color: #1E3A8A !important;
        }
        .sidebar .btn-toggle:not(.collapsed),
        .sidebar .btn-toggle.active {
            background-color: #0d47a1;
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

        /* Make Cancel button slightly darker on hover */
        #confirmModal .btn-cancel:hover {
            background-color: #d6d8db;
            /* slightly darker gray */
            color: #000;
        }

        /* Cancel hover */
        .btn-cancel:hover {
            background-color: #d6d8db;
            color: #000;
        }
        /* Sidebar Logout Link */
        .sidebar a.logout-link {
            display: block;
            padding: 12px 20px;
            border-radius: 6px;
            color: #ffffff; /* white text by default */
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s, color 0.3s;
        }

        /* Hover Effect */
        .sidebar a.logout-link:hover {
            background-color: #DC2626; /* red background */
            color: #ffffff;            /* white text */
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
            <h1 class="h5 mb-0 fw-bold text-dark">HOMEOWNER DASHBOARD</h1>
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
     <!-- Sidebar -->
    <div class="flex min-h-screen">
        <aside class="sidebar d-flex flex-column p-3">
            <h1 class="h5 fw-bold mb-4 text-white">HOA Resident</h1>
            <nav class="nav flex-column gap-2">
                <!-- Dashboard link -->
                <a href="#" 
                class="nav-link px-3 py-2 rounded text-white <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    Dashboard
                </a>

                <a href="account.php" 
                class="nav-link px-3 py-2 rounded text-white <?php echo basename($_SERVER['PHP_SELF']) === 'account.php' ? 'active' : ''; ?>">
                    Statement of Account
                </a>

                <a href="records.php" 
                class="nav-link px-3 py-2 rounded text-white <?php echo basename($_SERVER['PHP_SELF']) === 'records.php' ? 'active' : ''; ?>">
                    Personal Records
                </a>

                <a href="amenity_booking/amenity_booking.php" 
                class="nav-link px-3 py-2 rounded text-white <?php echo basename($_SERVER['PHP_SELF']) === 'amenities.php' ? 'active' : ''; ?>">
                    Amenities Schedule
                </a>
            </nav>
            <hr class="border-light my-3">
            <a href="logout.php" class="nav-link text-white fw-semibold mt-auto logout-btn">Logout</a>
        </aside>


        <!--Main Content-->
        <main class="flex-grow-1 p-4">
            <!-- Top Row: Announcements & Events -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white fw-bold">Announcements</div>
                        <div class="card-body">
                            <p class="text-muted mb-0">No announcements posted.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white fw-bold">Events</div>
                        <div class="card-body">
                            <p class="text-muted mb-0">No upcoming events scheduled.</p>
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
            <section class="card shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 text-primary">Amenity Schedule</h5>
                    <table class="table table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>#</th>
                                <th>Amenity</th>
                                <th>Date</th>
                                <th>Reservation Code</th>
                                <th>Rescheduled</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Clubhouse</td>
                                <td>2025-07-31</td>
                                <td>CLB00001</td>
                                <td>No</td>
                                <td class="text-success fw-semibold">Approved</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Basketball Court</td>
                                <td>2025-07-25</td>
                                <td>BBC00001</td>
                                <td>No</td>
                                <td class="text-success fw-semibold">Approved</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

    </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
