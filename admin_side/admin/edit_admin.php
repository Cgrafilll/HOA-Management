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
$edit_admin = $_GET['id'] ?? null;
$prof = $first_name = $middle_name = $last_name = $dob = $sex = $age = $cellphone = $landline = $email = $password = $street = $street2 = $city = $state = $brgy = $postal = $roles = $status = '';

if ($edit_admin) {
    try {
        $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE admin_id = ?");
        $stmt->bind_param("s", $edit_admin);
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
            $landline = $admin['landline'];
            $email = $admin['email_address'];
            $password = $admin['password'];
            $street = $admin['street_address'];
            $street2 = $admin['street_address_2'];
            $city = $admin['city'];
            $state = $admin['state_province'];
            $brgy = $admin['barangay'];
            $postal = $admin['postal_zip_code'];
            $roles = $admin['roles'];
            $status = $admin['status'];
        } else {
            $error_message = "Admin not found!";
        }
    } catch (Exception $e) {
        $error_message = "Error fetching admin: " . $e->getMessage();
    }
} else {
    $error_message = "Invalid admin ID.";
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
    $landline = $_POST['landline'];
    $email = $_POST['email'];
    $street = $_POST['street'];
    $street2 = $_POST['street2'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $barangay = $_POST['barangay'];
    $postal = $_POST['postal'];
    $role = $_POST['role'];

    // 2. Check if profile picture was uploaded
    $has_photo = isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK;

    if ($has_photo) {
        $profile_pic = file_get_contents($_FILES['profile_pic']['tmp_name']);

        $sql = "UPDATE admin_accounts SET 
            first_name=?, middle_name=?, last_name=?, date_of_birth=?, age=?, sex=?, 
            cellphone_number=?, landline=?, email_address=?, street_address=?, street_address_2=?, 
            city=?, state_province=?, barangay=?, postal_zip_code=?, roles=?, profile_picture=?
            WHERE admin_id=?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssssisssssssssssbs", // 'b' = blob, 'i' = integer (admin_id)
            $first_name,
            $middle_name,
            $last_name,
            $dob,
            $age,
            $sex,
            $cellphone,
            $landline,
            $email,
            $street,
            $street2,
            $city,
            $state,
            $barangay,
            $postal,
            $role,
            $null_blob, // temporary bind, will overwrite with send_long_data
            $edit_admin
        );

        $stmt->send_long_data(16, $profile_pic); // 17th param (index 16)
    } else {
        // No photo uploaded, don't update profile_pic
        $sql = "UPDATE admin_accounts SET 
            first_name=?, middle_name=?, last_name=?, date_of_birth=?, age=?, sex=?, 
            cellphone_number=?, landline=?, email_address=?, street_address=?, street_address_2=?, 
            city=?, state_province=?, barangay=?, postal_zip_code=?, roles=?
            WHERE admin_id=?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssssisssssssssssi", // no 'b' here
            $first_name,
            $middle_name,
            $last_name,
            $dob,
            $age,
            $sex,
            $cellphone,
            $landline,
            $email,
            $street,
            $street2,
            $city,
            $state,
            $barangay,
            $postal,
            $role,
            $edit_admin
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
                    style="height: 40px; width: 40px; border: 2px dashed #ccc; color: #aaa;">
                    <?php if (!empty($photo)): ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>"
                            style="width: 40px; height: 40px; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person-fill" style="font-size: 24px;"></i>
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
                            <li><a href="../admin_accounts.php" class="nav-link px-2 actived">Admin</a></li>
                            <li><a href="../household_accounts.php" class="nav-link px-2">Household</a></li>
                            <li><a href="#" class="nav-link px-2">Visitors</a></li>
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
                    <h5 class="mb-0 fw-bold">Edit Admin Profile</h5>
                </div>
                <div class="p-3">
                    <form action="edit_admin.php?id=<?= $edit_admin ?>" method="POST" enctype="multipart/form-data">
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
                                <input type="text" name="age" class="form-control"
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
                            <div class="col-md-4 mb-3">
                                <input type="text" name="landline" class="form-control"
                                    value="<?php echo htmlspecialchars($landline) ?>" />
                                <label class="form-label mt-2">Landline</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="email" name="email" class="form-control"
                                    value="<?php echo htmlspecialchars($email) ?>" required />
                                <label class="form-label mt-2">Email Address</label>
                            </div>
                        </div>
                        <!-- Address -->
                        <span class="fw-bold mb-3">Address</span>
                        <div class="my-3">
                            <input type="text" name="street" class="form-control"
                                value="<?php echo htmlspecialchars($street) ?>" required />
                            <label class="form-label mt-2">Street Address</label>
                        </div>
                        <div class="mb-3">
                            <input type="text" name="street2" class="form-control"
                                value="<?php echo htmlspecialchars($street2) ?>" />
                            <label class="form-label mt-2">Street Address Line 2</label>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <input type="text" name="city" class="form-control"
                                    value="<?php echo htmlspecialchars($city) ?>" required />
                                <label class="form-label mt-2">City</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="state" class="form-control"
                                    value="<?php echo htmlspecialchars($state) ?>" required />
                                <label class="form-label mt-2">State/Province</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="barangay" class="form-control"
                                    value="<?php echo htmlspecialchars($brgy) ?>" required />
                                <label class="form-label mt-2">Barangay</label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="postal" class="form-control"
                                    value="<?php echo htmlspecialchars($postal) ?>" required />
                                <label class="form-label mt-2">Postal/Zip Code</label>
                            </div>
                        </div>
                        <!-- Roles -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label mt-2 fw-bold">Roles</label>
                                <select name="role" class="form-select" required>
                                    <option value="">--Select Role--</option>
                                    <option value="Board Member" <?= ($roles == 'Board Member') ? 'selected' : '' ?>>Board
                                        Member</option>
                                    <option value="Clubhouse Staff" <?= ($roles == 'Clubhouse Staff') ? 'selected' : '' ?>>
                                        Clubhouse Staff</option>
                                    <option value="Security Staff" <?= ($roles == 'Security Staff') ? 'selected' : '' ?>>
                                        Security Staff</option>
                                </select>
                            </div>
                        </div>
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="../admin_accounts.php" class="btn btn-danger">Cancel</a>
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

                                const redirect = () => window.location.href = '../admin_accounts.php';
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
    </script>
</body>

</html>