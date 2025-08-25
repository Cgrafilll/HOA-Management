<?php
session_start();
require '../../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: login/login.php");
    exit;
}

// Initialize user details
$email_address = $_SESSION['email_address'];
$username = $photo = ''; // Initialize user details

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

        .announcement-card {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            max-width: 100%;
        }

        .announcement-body {
            font-size: 0.95rem;
            margin: 0;
            margin-bottom: 8px;
            line-height: 1.4;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        main {
            margin-left: 250px;
            padding-bottom: 100px;
            /* ✅ give breathing room at bottom */
        }

        .card-body p,
        .card-body h6 {
            word-wrap: break-word;
            /* Old support */
            overflow-wrap: break-word;
            /* Modern support */
            white-space: pre-wrap;
            /* Keeps newlines */
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

        /* Make Cancel button slightly darker on hover */
        #confirmModal .btn-cancel:hover {
            background-color: #d6d8db;
            /* slightly darker gray */
            color: #000;
        }

        .form-control.border-danger {
            border: 2px solid #dc3545 !important;
            /* force red */
        }

        textarea {
            min-height: 100px;
            resize: none;
            /* optional: prevent manual drag */
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
            <h1 class="h5 mb-0 fw-bold">VIOLATION TRACKING</h1>
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
                        data-bs-target="#recordCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-book me-2"></i> Record Keeping
                        </span>
                    </button>
                    <div class="collapse show" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="../violation_tracking.php" class="nav-link px-2 actived">Violation Tracking</a></li>
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
                            <li><a href="../announcements.php" class="nav-link px-2 actived">Announcements</a></li>
                            <li><a href="../events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="../phonebook.php" class="nav-link px-2">Phone Book</a></li>
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
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Announcements</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Violations</span>
                        <div class="d-flex gap-2">
                            <a href="../violation_tracking.php"
                                class="btn btn-outline-secondary btn-sm">Back</a>
                        </div>
                    </div>
                    <hr class="mb-3 mt-0">
                    <!-- Violation Report Form -->
                    <form action="save_violation.php" id="violationForm"method="POST" enctype="multipart/form-data" class="row g-3">

                        <!-- Reporter Information -->
                        <div class="col-12">
                            <h6 class="fw-bold">Reporter Information</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name<span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name<span class="text-danger">*</span></label>
                            <input type="text" name="middle_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name<span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>

                        <!-- Contact Information -->
                        <div class="col-12">
                            <h6 class="fw-bold mt-3">Contact Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cellphone Number<span class="text-danger">*</span></label>
                            <input type="text" name="cellphone_number" class="form-control" required>
                        </div>

                        <!-- Incident Details -->
                        <div class="col-12">
                            <h6 class="fw-bold mt-3">Incident Details</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Incident<span class="text-danger">*</span></label>
                            <input type="date" name="date_incident" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Time of Incident<span class="text-danger">*</span></label>
                            <input type="time" name="time_incident" class="form-control" placeholder="HH:MM" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Location<span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Violation Type<span class="text-danger">*</span></label>
                            <select name="violation_type" class="form-select" required>
                                <option value="">-- Select Violation --</option>
                                <option value="Unauthorized Amenity Use">Unauthorized Amenity Use</option>
                                <option value="Noise Disturbance">Noise Disturbance</option>
                                <option value="Property Damage">Property Damage</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description of Incident<span class="text-danger">*</span></label>
                            <textarea name="description_of_incident" class="form-control" required></textarea>
                        </div>

                        <!-- Parties Involved -->
                        <div class="col-12">
                            <h6 class="fw-bold mt-3">Parties Involved</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Name of Resident/Household Involved (if known)</label>
                            <input type="text" name="homeowner_involved" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Address / Lot Number (if applicable)</label>
                            <input type="text" name="address_lot_number" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Other Parties / Witnesses (optional)</label>
                            <input type="text" name="other_parties" class="form-control">
                        </div>

                        <!-- Evidence -->
                        <div class="col-md-6">
                            <label class="form-label">Evidence</label>
                            <input type="file" name="evidence" class="form-control" accept=".jpg,.jpeg,.png,.gif,.mp4,.pdf,.doc,.docx,.ppt,.pptx">
                            <div class="form-text">Supported formats: JPEG, PNG, GIF, MP4, PDF, DOC, PPT</div>
                        </div>

                        <!-- Action Taken -->
                        <div class="col-md-6">
                            <label class="form-label">Immediate Action</label>
                            <input type="text" name="action_taken" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Remarks / Notes</label>
                            <textarea name="remarks" class="form-control"></textarea>
                        </div>

                        <!-- Options -->
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="anonymous" value="1">
                                <label class="form-check-label">I want to remain anonymous to the reported party</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="confirmation" value="1" required>
                                <label class="form-check-label">I confirm that the information provided is accurate to the best of my knowledge</label>
                            </div>
                        </div>
                        <!-- Buttons -->
                        <div class="col-12 d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmModal">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <!-- Confirm Save Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Confirm Save</h5>
        </div>
        <div class="modal-body">
            Are you sure you want to save this violation?
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmSaveBtn">Save</button>
        </div>
        </div>
    </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">Saved!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Violation report saved successfully.</b></p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="window.location.href='../violation_tracking.php'">OK</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="errorMessage" class="text-dark">An error occurred while saving the violation.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
       document.addEventListener("DOMContentLoaded", function () {
            let confirmBtn = document.getElementById("confirmSaveBtn");

            confirmBtn.addEventListener("click", function () {
                let formData = new FormData(document.getElementById("violationForm"));

                fetch("save_violation.php", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.text())
                .then(data => {
                    if (data.trim() === "success") {
                        new bootstrap.Modal(document.getElementById("successModal")).show();
                        document.getElementById("violationForm").reset();
                    } else {
                        document.getElementById("errorMessage").innerText = data;
                        new bootstrap.Modal(document.getElementById("errorModal")).show();
                    }
                })
                .catch(err => {
                    document.getElementById("errorMessage").innerText = "Network error: " + err;
                    new bootstrap.Modal(document.getElementById("errorModal")).show();
                });

                // Close confirm modal after saving
                let confirmModal = bootstrap.Modal.getInstance(document.getElementById("confirmModal"));
                confirmModal.hide();
            });
        });
    </script>
</body>
</html>