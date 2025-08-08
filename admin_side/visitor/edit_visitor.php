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

// Initialize admin details
$edit_visitor = $_GET['id'] ?? null;
$prof = $first_name = $middle_name = $last_name = $dob = $sex = $age = $cellphone = $employement = $reason = '';

if ($edit_visitor) {
    try {
        $stmt = $conn->prepare("SELECT * FROM visitor_details WHERE visitor_id = ?");
        $stmt->bind_param("s", $edit_visitor);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin) {
            $prof = !empty($admin['profile_picture']) ? 'data:image/jpeg;base64,' . base64_encode($admin['profile_picture']) : '';
            $first_name = $admin['first_name'];
            $middle_name = $admin['middle_name'];
            $last_name = $admin['last_name'];
            $dob = $admin['date_of_birth'];
            $sex = $admin['sex'];
            $age = $admin['age'];
            $cellphone = $admin['cellphone_number'];
            $employement = $admin['employed_in_subdivision'];
            $reason = $admin['reason_for_visit'];
        } else {
            $error_message = "Visitor not found!";
        }
    } catch (Exception $e) {
        $error_message = "Error fetching admin: " . $e->getMessage();
    }
} else {
    $error_message = "Invalid Visitor ID.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize and collect input
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $dob = $_POST['dob'];
    $age = $_POST['age'];
    $sex = $_POST['sex'];
    $cellphone = $_POST['cellphone'];
    $employment_status = $_POST['employment_status']; // Yes/No
    $reason = $_POST['reason'];

    // 2. Check if profile picture was uploaded
    $has_photo = isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK;

    if ($has_photo) {
        $profile_pic = file_get_contents($_FILES['profile_pic']['tmp_name']);

        $sql = "UPDATE visitor_details SET 
            first_name=?, middle_name=?, last_name=?, date_of_birth=?, age=?, sex=?, 
            cellphone_number=?, employed_in_subdivision=?, reason_for_visit=?, profile_picture=?
            WHERE visitor_id=?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssssissssbs",
            $first_name,
            $middle_name,
            $last_name,
            $dob,
            $age,
            $sex,
            $cellphone,
            $employment_status,
            $reason,
            $null_blob, // temporary bind, will overwrite with send_long_data
            $edit_visitor
        );

        $stmt->send_long_data(9, $profile_pic); // 17th param (index 16)
    } else {
        // No photo uploaded, don't update profile_pic
        $sql = "UPDATE visitor_details SET 
            first_name=?, middle_name=?, last_name=?, date_of_birth=?, age=?, sex=?, 
            cellphone_number=?, employed_in_subdivision=?, reason_for_visit=?
            WHERE visitor_id=?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssssisssss", // no 'b' here
            $first_name,
            $middle_name,
            $last_name,
            $dob,
            $age,
            $sex,
            $cellphone,
            $employment_status,
            $reason,
            $edit_visitor
        );
    }

    // 3. Execute and check success
    if ($stmt->execute()) {
        $success = true;
    } else {
        $error = "Update failed: " . $stmt->error;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
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
                            <li><a href="../household_accounts.php" class="nav-link px-2">Household</a></li>
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
                            <li><a href="#" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="#" class="nav-link px-2">Violation Tracking</a></li>
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
                    <span class="small mb-0">Edit User Details</span>
                    <a href="../visitor_accounts.php"
                        class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-arrow-left-short me-1"></i>Back
                    </a>
                </div>
                <hr class="my-0">
                <div class="p-3">
                    <form action="edit_visitor.php?id=<?= $edit_visitor ?>" method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <label for="profile_pic" class="form-label fw-bold">Profile Picture</label>
                            <div class="row">
                                <div class="col-4 mb-3">
                                    <div id="preview"
                                        class="d-flex align-items-center justify-content-center overflow-hidden rounded"
                                        style="height: 120px; width: 120px; border: 2px dashed #ccc; color: #aaa;">
                                        <?php if (!empty($prof)): ?>
                                            <img src="<?php echo htmlspecialchars($prof) ?>"
                                                style="width: 100px; height: 100px; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="bi bi-person-fill" style="font-size: 48px;"></i>
                                        <?php endif; ?>
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
                                <input type="text" name="first_name" class="form-control"
                                    value="<?php echo htmlspecialchars($first_name) ?>" required />
                                <label class="form-label mt-2">First Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="middle_name" class="form-control"
                                    value="<?php echo htmlspecialchars($middle_name) ?>" required />
                                <label class="form-label mt-2">Middle Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="last_name" class="form-control"
                                    value="<?php echo htmlspecialchars($last_name) ?>" required />
                                <label class="form-label mt-2">Last Name</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="date" name="dob" class="form-control"
                                    value="<?php echo htmlspecialchars($dob) ?>" required />
                                <label class="form-label mt-2">Date of Birth</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="number" name="age" class="form-control"
                                    value="<?php echo htmlspecialchars($age) ?>" readonly />
                                <label class="form-label mt-2">Age</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <select name="sex" class="form-select" required>
                                    <option value="">Select</option>
                                    <option value="Male" <?= ($sex == 'Male') ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($sex == 'Female') ? 'selected' : '' ?>>Female</option>
                                </select>
                                <label class="form-label mt-2">Sex</label>
                            </div>
                        </div>
                        <!-- Contact -->
                        <div class="row">
                            <span class="fw-bold mb-3">Contact Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="cellphone" class="form-control"
                                    value="<?php echo htmlspecialchars($cellphone) ?>" />
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
                                        value="No" <?= ($employement == 'No') ? 'checked' : '' ?> required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check">
                                    <label class="form-check-label me-2" for="yesRadio2">Yes</label>
                                    <input class="form-check-input" type="radio" name="employment_status" id="yesRadio2"
                                        value="Yes" <?= ($employement == 'Yes') ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <!-- Dropdowns for Reason -->
                        <div class="row">
                            <!-- No Dropdown -->
                            <div class="col-md-4 mb-3" id="dropdownNo">
                                <select id="reasonNo" name="reason" class="form-select">
                                    <option disabled <?= ($employement == 'No' && $reason == '') ? 'selected' : '' ?>
                                        value="">Select a reason</option>
                                    <option <?= ($employement == 'No' && $reason == 'Personal Visit / Family Gathering') ? 'selected' : '' ?>>Personal Visit / Family Gathering</option>
                                    <option <?= ($employement == 'No' && $reason == 'Delivery or Pickup') ? 'selected' : '' ?>>Delivery or Pickup</option>
                                    <option <?= ($employement == 'No' && $reason == 'Health or Emergency Services') ? 'selected' : '' ?>>Health or Emergency Services</option>
                                    <option <?= ($employement == 'No' && $reason == 'Religious or Community Outreach') ? 'selected' : '' ?>>Religious or Community Outreach</option>
                                    <option <?= ($employement == 'No' && $reason == 'Transport Services') ? 'selected' : '' ?>>Transport Services</option>
                                    <option <?= ($employement == 'No' && $reason == 'Guest Use of Amenities') ? 'selected' : '' ?>>Guest Use of Amenities</option>
                                    <option <?= ($employement == 'No' && $reason == 'Home Maintenance and Repairs') ? 'selected' : '' ?>>Home Maintenance and Repairs</option>
                                    <option <?= ($employement == 'No' && $reason == 'Construction or Renovation') ? 'selected' : '' ?>>Construction or Renovation</option>
                                    <option <?= ($employement == 'No' && $reason == 'Landscaping and Gardening') ? 'selected' : '' ?>>Landscaping and Gardening</option>
                                    <option <?= ($employement == 'No' && $reason == 'Household Help') ? 'selected' : '' ?>>
                                        Household Help</option>
                                    <option <?= ($employement == 'No' && $reason == 'Pest Control / Cleaning Services') ? 'selected' : '' ?>>Pest Control / Cleaning Services</option>
                                    <option <?= ($employement == 'No' && $reason == 'Internet / Cable / Utility Installation') ? 'selected' : '' ?>>Internet / Cable / Utility Installation
                                    </option>
                                    <option <?= ($employement == 'No' && $reason == 'Furniture or Appliance Delivery') ? 'selected' : '' ?>>Furniture or Appliance Delivery</option>
                                    <option <?= ($employement == 'No' && $reason == 'Server Contractors') ? 'selected' : '' ?>>Server Contractors</option>
                                </select>
                            </div>
                            <!-- Yes Dropdown -->
                            <div class="col-md-4 mb-3" id="dropdownYes">
                                <select id="reasonYes" name="reason" class="form-select">
                                    <option disabled <?= ($employement == 'Yes' && $reason == '') ? 'selected' : '' ?>
                                        value="">Select a reason</option>
                                    <option <?= ($employement == 'Yes' && $reason == 'Administrative Work') ? 'selected' : '' ?>>Administrative Work</option>
                                    <option <?= ($employement == 'Yes' && $reason == 'Facilities Management') ? 'selected' : '' ?>>Facilities Management</option>
                                    <option <?= ($employement == 'Yes' && $reason == 'IT and System Maintenance') ? 'selected' : '' ?>>IT and System Maintenance</option>
                                    <option <?= ($employement == 'Yes' && $reason == 'Security Oversight') ? 'selected' : '' ?>>Security Oversight</option>
                                </select>
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
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate age from DOB
        document.querySelector('input[name="dob"]').addEventListener('change', function () {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
            document.querySelector('input[name="age"]').value = age;
        });

        // Image preview for profile picture
        document.getElementById('profile_pic').addEventListener('change', function (e) {
            const file = e.target.files[0];
            const preview = document.getElementById('preview');

            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" />`;
                }
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '<i class="bi bi-person-fill"></i>';
            }
        });

        const form = document.querySelector('form');
        const reasonNo = document.getElementById('reasonNo');
        const reasonYes = document.getElementById('reasonYes');
        const radioNo = document.getElementById('noRadio1');
        const radioYes = document.getElementById('yesRadio2');

        // Clear the unselected dropdown
        function clearUnselectedDropdown() {
            if (radioYes.checked) {
                reasonNo.selectedIndex = 0;
            } else if (radioNo.checked) {
                reasonYes.selectedIndex = 0;
            }
        }

        // When a reason is selected in "No" dropdown
        reasonNo.addEventListener('change', () => {
            radioNo.checked = true;
            reasonYes.selectedIndex = 0; // Clear Yes dropdown
        });

        // When a reason is selected in "Yes" dropdown
        reasonYes.addEventListener('change', () => {
            radioYes.checked = true;
            reasonNo.selectedIndex = 0; // Clear No dropdown
        });

        // When "No" radio is clicked directly
        radioNo.addEventListener('change', () => {
            clearUnselectedDropdown();
        });

        // When "Yes" radio is clicked directly
        radioYes.addEventListener('change', () => {
            clearUnselectedDropdown();
        });

        // On page load, clear whichever is not selected
        window.addEventListener('DOMContentLoaded', () => {
            clearUnselectedDropdown();
        });

        // Final validation on submit
        form.addEventListener('submit', function (e) {
            const isNo = radioNo.checked;
            const isYes = radioYes.checked;
            const reasonNoSelected = reasonNo.value !== '';
            const reasonYesSelected = reasonYes.value !== '';

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

            if (!valid) e.preventDefault(); // Stop submission
        });

    </script>
</body>

</html>