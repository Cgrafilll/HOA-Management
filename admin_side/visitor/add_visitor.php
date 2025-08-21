<?php
session_start();
require '../../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: ../login/login.php");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize inputs
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $dob = $_POST['dob'];
    $age = $_POST['age'];
    $sex = $_POST['sex'];
    $cellphone = $_POST['cellphone'];
    $employment_status = $_POST['employment_status']; // Yes/No
    $reason = $_POST['reason'];
    $rfid = $_POST['rfid'];

    try {
        // 2. Check if RFID already exists
        $rfid_check_stmt = $conn->prepare("SELECT visitor_id FROM visitor_details WHERE rfid = ?");
        $rfid_check_stmt->bind_param("s", $rfid);
        $rfid_check_stmt->execute();
        $rfid_result = $rfid_check_stmt->get_result();

        if ($rfid_result->num_rows > 0) {
            $error = "RFID card is already registered to another household/visitor. Please use a different RFID card.";
        } else {
            // 3. Generate new visitor_id (HOU-0001, HOU-0002...)
            $result = $conn->query("SELECT visitor_id FROM visitor_details ORDER BY visitor_id DESC LIMIT 1");
            if ($result && $row = $result->fetch_assoc()) {
                $last_id = intval(substr($row['visitor_id'], 4)); // extract numeric part
                $new_id_number = $last_id + 1;
            } else {
                $new_id_number = 1; // first household
            }
            $visitor_id = 'VIS-' . str_pad($new_id_number, 4, '0', STR_PAD_LEFT);
            // 4. Handle profile picture
            $profile_pic = null;
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profile_pic']['tmp_name'];
                $profile_pic = file_get_contents($file_tmp); // Read image data as binary
            }
            // 5. Prepare SQL
            $sql = "INSERT INTO visitor_details (
                visitor_id, first_name, middle_name, last_name, date_of_birth, age, sex,
                cellphone_number, employed_in_subdivision, reason_for_visit, rfid, profile_picture
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if ($profile_pic !== null) {
                $stmt->send_long_data(11, $profile_pic); // 12th parameter is index 11 (0-based)
            }

            $stmt->bind_param(
                "ssssissssssb",
                $visitor_id,
                $first_name,
                $middle_name,
                $last_name,
                $dob,
                $age,
                $sex,
                $cellphone,
                $employment_status,
                $reason,
                $rfid,
                $profile_pic
            );

            // 5. Execute insert
            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = "Failed to save visitor account: " . $stmt->error;
            }
        }
    } catch (Exception $e) {
        $error = "Error adding visitor: " . $e->getMessage();
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

        #preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
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
            <h1 class="h5 mb-0 fw-bold">ACCOUNTS</h1>
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
                <a href="../admin_dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <!-- Accounts -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#accountsCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-person-lines-fill me-2"></i> Accounts
                        </span>
                    </button>
                    <div class="collapse" id="accountsCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="../visitor_details.php" class="nav-link px-2">Household</a></li>
                            <li><a href="../visitor_accounts.php" class="nav-link px-2 actived">Visitors</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Record Keeping -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#recordCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-book me-2"></i> Record Keeping
                        </span>
                    </button>
                    <div class="collapse" id="recordCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="../amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
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
                    <h5 class="mb-0 fw-bold">Visitor Account Management</h5>
                </div>
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0">User Details</span>
                    <a href="../visitor_accounts.php"
                        class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="my-0">
                <div class="p-3">
                    <form action="add_visitor.php" method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <label for="profile_pic" class="form-label fw-bold">Profile Picture</label>
                            <div class="row">
                                <div class="col-4 mb-3">
                                    <div id="preview"
                                        class="d-flex align-items-center justify-content-center overflow-hidden rounded"
                                        style="height: 120px; width: 120px; border: 2px dashed #ccc; color: #aaa;">
                                        <i class="bi bi-person-fill" style="font-size: 48px;"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4">
                                    <input type="file" class="form-control" name="profile_pic" id="profile_pic"
                                        accept="image/*" />
                                </div>
                            </div>
                        </div>
                        <!-- Personal Info -->
                        <div class="row">
                            <span class="fw-bold mb-3">Personal Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="first_name" class="form-control" required />
                                <label class="form-label mt-2">First Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="middle_name" class="form-control" required />
                                <label class="form-label mt-2">Middle Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="last_name" class="form-control" required />
                                <label class="form-label mt-2">Last Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="date" name="dob" class="form-control" required />
                                <label class="form-label mt-2">Date of Birth</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="number" name="age" class="form-control" readonly />
                                <label class="form-label mt-2">Age</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <select name="sex" class="form-select" required>
                                    <option value="">Select</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                </select>
                                <label class="form-label mt-2">Sex</label>
                            </div>
                        </div>
                        <!-- Contact -->
                        <div class="row">
                            <span class="fw-bold mb-3">Contact Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="cellphone" class="form-control" />
                                <label class="form-label mt-2">Cellphone Number</label>
                            </div>
                        </div>
                        <!-- Reason for Visit -->
                        <span class="fw-bold mb-3">Reason for Visit</span>
                        <div class="my-3">
                            <span>Are you employed by the subdivision?</span>
                        </div>
                        <!-- Radio Buttons -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="form-check">
                                    <label class="form-check-label me-2" for="noRadio1">No</label>
                                    <input class="form-check-input" type="radio" name="employment_status" id="noRadio1"
                                        value="No" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check">
                                    <label class="form-check-label me-2" for="yesRadio2">Yes</label>
                                    <input class="form-check-input" type="radio" name="employment_status" id="yesRadio2"
                                        value="Yes">
                                </div>
                            </div>
                        </div>
                        <!-- Dropdowns for Reason -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <select id="reasonNo" name="reason" class="form-select">
                                    <option selected disabled value="">Select a reason</option>
                                    <option>Personal Visit / Family Gathering</option>
                                    <option>Delivery or Pickup</option>
                                    <option>Health or Emergency Services</option>
                                    <option>Religious or Community Outreach</option>
                                    <option>Transport Services</option>
                                    <option>Guest Use of Amenities</option>
                                    <option>Home Maintenance and Repairs</option>
                                    <option>Construction or Renovation</option>
                                    <option>Landscaping and Gardening</option>
                                    <option>Household Help</option>
                                    <option>Pest Control / Cleaning Services</option>
                                    <option>Internet / Cable / Utility Installation</option>
                                    <option>Furniture or Appliance Delivery</option>
                                    <option>Server Contractors</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <select id="reasonYes" name="reason" class="form-select">
                                    <option selected disabled value="">Select a reason</option>
                                    <option>Administrative Work</option>
                                    <option>Facilities Management</option>
                                    <option>IT and System Maintenance</option>
                                    <option>Security Oversight</option>
                                </select>
                            </div>
                        </div>
                        <!-- Resident RFID -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label mt-2 fw-bold">Visitor RFID</label>
                                <input type="text" name="rfid" class="form-control" id="rfidInput" required />
                                <label class="form-label mt-2">Tap your RFID card</label>
                            </div>
                        </div>
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="../visitor_accounts.php" class="btn btn-danger">Cancel</a>
                        </div>
                    </form>
                    <!-- Success Modal -->
                    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content text-center">
                                <div class="modal-header bg-success text-white">
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-check2-circle text-success" style="font-size: 64px;"></i>
                                    <p class="mb-2"><b>Success</b></p>
                                    <p class="mb-3">User details have been successfully saved.</p>
                                    <button type="button" class="btn btn-primary" id="doneButton">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Error Modal -->
                    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content text-center">
                                <div class="modal-header bg-danger text-white">
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                                    <p class="mb-2"><b>Error</b></p>
                                    <p class="mb-3" id="errorMessage">
                                        <?php echo isset($error) ? htmlspecialchars($error) : 'An error occurred while processing your request.'; ?>
                                    </p>
                                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($success) && $success): ?>
                        <script>
                            window.addEventListener('DOMContentLoaded', () => {
                                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                                successModal.show();

                                const redirect = () => window.location.href = '../visitor_accounts.php';
                                document.getElementById('doneButton').addEventListener('click', redirect);
                                document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
                            });
                        </script>
                    <?php endif; ?>
                    <?php if (isset($error) && $error): ?>
                        <script>
                            window.addEventListener('DOMContentLoaded', () => {
                                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                                errorModal.show();
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');

            // ====== RFID INPUT HANDLING ======
            const rfidInput = document.getElementById('rfidInput');
            if (rfidInput) {
                // Prevent RFID input from submitting the form
                rfidInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.keyCode === 13) {
                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();

                        // Blur the input to remove focus after RFID scan
                        this.blur();

                        // Confirmation log
                        console.log('RFID captured:', this.value);

                        return false;
                    }
                });

                // Additional prevention using keypress
                rfidInput.addEventListener('keypress', function (event) {
                    if (event.key === 'Enter' || event.keyCode === 13) {
                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();
                        return false;
                    }
                });

                // Prevent any form submission triggered by the RFID input
                rfidInput.addEventListener('input', function () {
                    if (this.value.length > 0) {
                        clearTimeout(window.rfidSubmitTimeout);
                    }
                });
            }

            // ====== AUTO-CALCULATE AGE FROM DOB ======
            const dobInput = document.querySelector('input[name="dob"]');
            const ageInput = document.querySelector('input[name="age"]');

            if (dobInput && ageInput) {
                dobInput.addEventListener('change', function () {
                    const dob = new Date(this.value);
                    const today = new Date();
                    let age = today.getFullYear() - dob.getFullYear();
                    const monthDiff = today.getMonth() - dob.getMonth();

                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                        age--;
                    }

                    ageInput.value = age;
                });
            }

            // ====== PROFILE PICTURE PREVIEW ======
            const profilePicInput = document.getElementById('profile_pic');
            const preview = document.getElementById('preview');

            if (profilePicInput && preview) {
                profilePicInput.addEventListener('change', function (event) {
                    const file = event.target.files[0];
                    if (file && file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            preview.innerHTML = `<img src="${e.target.result}" style="width: 100px; height: 100px; object-fit: cover;">`;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        preview.innerHTML = '<i class="bi bi-person-fill"></i>';
                    }
                });
            }

            // ====== RADIO + DROPDOWN SYNC ======
            const reasonNo = document.getElementById('reasonNo');
            const reasonYes = document.getElementById('reasonYes');
            const radioNo = document.getElementById('noRadio1');
            const radioYes = document.getElementById('yesRadio2');

            if (reasonNo && reasonYes && radioNo && radioYes) {
                reasonNo.addEventListener('change', () => {
                    radioNo.checked = true;
                    reasonYes.selectedIndex = 0;
                });

                reasonYes.addEventListener('change', () => {
                    radioYes.checked = true;
                    reasonNo.selectedIndex = 0;
                });

                radioNo.addEventListener('change', () => {
                    if (radioNo.checked) {
                        reasonYes.selectedIndex = 0;
                    }
                });

                radioYes.addEventListener('change', () => {
                    if (radioYes.checked) {
                        reasonNo.selectedIndex = 0;
                    }
                });
            }

            // ====== FINAL VALIDATION ON SUBMIT ======
            if (form) {
                form.addEventListener('submit', function (e) {
                    const isNo = radioNo && radioNo.checked;
                    const isYes = radioYes && radioYes.checked;
                    const reasonNoSelected = reasonNo && reasonNo.value !== '';
                    const reasonYesSelected = reasonYes && reasonYes.value !== '';

                    let valid = true;

                    if (!isNo && !isYes) {
                        alert("Please select if you're employed by the subdivision.");
                        valid = false;
                    } else if (isNo && !reasonNoSelected) {
                        alert("Please select a reason under 'No'.");
                        valid = false;
                    } else if (isYes && !reasonYesSelected) {
                        alert("Please select a reason under 'Yes'.");
                        valid = false;
                    }

                    if (!valid) e.preventDefault();
                    else console.log('Form is being submitted via Save button');
                });
            }
        });
    </script>

</body>

</html>