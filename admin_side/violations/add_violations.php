<?php
session_start();
require '../../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: login/login.php");
    exit;
}

// Initialize user details
$email_address = $_SESSION['email_address'];
$admin_id = $_SESSION['admin_id'];
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

// Fetch all household accounts for dropdown
$households = [];
try {
    $stmt = $conn->prepare("SELECT household_id, first_name, middle_name, last_name, cellphone_number FROM household_accounts ORDER BY first_name, last_name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $households[] = $row;
    }
} catch (Exception $e) {
    $error_message = "Error fetching households: " . $e->getMessage();
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

        .file-drop-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background-color: #f9fafb;
            transition: all 0.3s ease;
        }

        .file-drop-area:hover {
            border-color: #6b7280;
            background-color: #f3f4f6;
        }

        .file-drop-area.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .cloud-icon {
            font-size: 48px;
            color: #9ca3af;
            margin-bottom: 16px;
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
            <h1 class="h5 mb-0 fw-bold">RECORD KEEPING</h1>
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2 dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown"
                    aria-expanded="false" role="button" style="cursor: pointer;">
                    <span>Hello, <?php echo htmlspecialchars($username); ?></span>
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
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="../admin/view_admin.php?id=<?php echo $admin_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="../login/logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
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
                            <li><a href="../violation_tracking.php" class="nav-link px-2 actived">Violation Tracking</a>
                            </li>
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
                            <li><a href="../payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="../invoice.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="../login/logout.php"
                    class="nav-link mb-3 px-3 py-2 rounded d-flex align-items-center justify-content-start logout"
                    style="position: fixed; bottom: 0; width: 220px;">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Violation Management</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Violations</span>
                        <div class="d-flex gap-2">
                            <a href="../violation_tracking.php" class="btn btn-outline-secondary btn-sm">Back</a>
                        </div>
                    </div>
                    <hr class="mb-3 mt-0">
                    <!-- Violation Report Form -->
                    <form action="save_violation.php" id="violationForm" method="POST" enctype="multipart/form-data">
                        <!-- Household Selection -->
                        <div class="row">
                            <span class="fw-bold mb-3">Select Household Account</span>
                            <div class="col-md-4 mb-3">
                                <select id="householdSelect" class="form-select" required>
                                    <option value="" selected disabled>Select a Household</option>
                                    <?php foreach ($households as $household): ?>
                                        <option value="<?php echo htmlspecialchars($household['household_id']); ?>"
                                            data-firstname="<?php echo htmlspecialchars($household['first_name']); ?>"
                                            data-middlename="<?php echo htmlspecialchars($household['middle_name']); ?>"
                                            data-lastname="<?php echo htmlspecialchars($household['last_name']); ?>"
                                            data-cellphone="<?php echo htmlspecialchars($household['cellphone_number']); ?>">
                                            <?php echo htmlspecialchars($household['household_id'] . ' - ' . $household['first_name'] . ' ' . $household['middle_name'] . ' ' . $household['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="form-label mt-2">Household<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                        </div>
                        <!-- Personal Info -->
                        <div class="row">
                            <span class="fw-bold mb-3">Reporter Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="text" id="first_name" name="first_name" class="form-control" readonly />
                                <label class="form-label mt-2">First Name<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" id="middle_name" name="middle_name" class="form-control" readonly />
                                <label class="form-label mt-2">Middle Name<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" id="last_name" name="last_name" class="form-control" readonly />
                                <label class="form-label mt-2">Last Name<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                        </div>
                        <!-- Contact -->
                        <div class="row">
                            <span class="fw-bold mb-3">Contact Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="tel" id="cellphone_number" name="cellphone_number" class="form-control"
                                    readonly />
                                <label class="form-label mt-2">Cellphone Number</label>
                            </div>
                        </div>
                        <!-- Hidden field to store household_id -->
                        <input type="hidden" id="household_id" name="household_id" />
                        <!-- Incident Details -->
                        <div class="row">
                            <span class="fw-bold mb-3">Incident Details</span>
                            <div class="col-4 mb-3">
                                <input type="date" name="date_incident" class="form-control" required />
                                <label class="form-label mt-2">Date of Incident<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-4 mb-3">
                                <input type="time" name="time_incident" class="form-control" required />
                                <label class="form-label mt-2">Time of Incident<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-4 mb-3">
                                <input type="text" name="location" class="form-control" required />
                                <label class="form-label mt-2">Location<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-4 mb-3">
                                <select name="violation_type" class="form-select" required>
                                    <option value="" selected disabled>Select Violation Type</option>
                                    <option>Excessive Noise</option>
                                    <option>Parking Violation</option>
                                    <option>Pet-Related Complaint</option>
                                    <option>Unapproved Construction</option>
                                </select>
                                <label class="form-label mt-2">Violation Type<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                        </div>
                        <!-- Parties Involved Info -->
                        <div class="row">
                            <span class="fw-bold mb-3">Parties Involved</span>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="homeowner_involved" class="form-control" />
                                <label class=" form-label mt-2">Name of Resident/Household Involved <i>(if
                                        known)</i></label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="address_lot_number" class="form-control" />
                                <label class=" form-label mt-2">Address/Lot Number <i>(if applicable)</i></label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="other_parties" class="form-control" />
                                <label class=" form-label mt-2">Other Parties/Witnesses <i>(optional)</i></label>
                            </div>
                        </div>
                        <!-- Evidence -->
                        <div class="row">
                            <span class="fw-bold mb-3">Evidence</span>
                            <div class="col-md-4 mb-3">
                                <!-- File Upload -->
                                <div class="file-drop-area" id="fileDropArea" style="height: 250px;">
                                    <div class="cloud-icon">
                                        <i class="bi bi-cloud-upload"></i>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Drag & drop files or <a href="#" id="browseLink">Browse</a></strong>
                                    </div>
                                    <div class="small text-muted">
                                        Supported formats: JPEG, PNG, GIF, PDF, TXT, XLS, AI, Word, PPT
                                    </div>
                                    <input type="file" id="fileInput" name="evidence" class="d-none"
                                        accept=".jpeg,.jpg,.png,.gif,.pdf,.txt,.xls,.xlsx,.ai,.doc,.docx,.ppt,.pptx"
                                        required>
                                </div>
                                <label class="form-label mt-2">Upload your Evidence<small
                                        class="fw-bold text-danger">*</small></label>
                                <div id="filePreview" class="mt-2"></div>
                            </div>
                            <div class="col-4">
                                <textarea name="description_of_incident" class="form-control" required
                                    style="height: 250px; resize: none;"
                                    placeholder="Specifically describe what happened . . ."></textarea>
                                <label class="form-label mt-2">Description of Incident<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                        </div>
                        <hr class="mt-0">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="anonymous" name="anonymous" value="1">
                            <label class="form-check-label" for="anonymous">
                                I want to remain anonymous to the reported party
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="accurate" name="accurate" required>
                            <label class="form-check-label" for="accurate">
                                I confirm that the information provided is accurate to the best of my knowledge
                            </label>
                        </div>
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">Report Violation</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <!-- Confirm Save Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Confirm Save</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-question-circle text-primary" style="font-size: 64px;"></i>
                    <p class="mb-2">Are you sure you want to save this violation?</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-primary" id="confirmSaveBtn">Save</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-success text-white">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Violation report saved successfully.</b></p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal"
                        onclick="window.location.href='../violation_tracking.php'">OK</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                    <p id="errorMessage" class="text-dark">An error occurred while saving the violation.
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

                const redirect = () => window.location.href = '../violation_tracking.php';
                document.getElementById('doneButton').addEventListener('click', redirect);
                document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
            });
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const householdSelect = document.getElementById('householdSelect');
            const firstNameInput = document.getElementById('first_name');
            const middleNameInput = document.getElementById('middle_name');
            const lastNameInput = document.getElementById('last_name');
            const cellphoneInput = document.getElementById('cellphone_number');
            const householdIdInput = document.getElementById('household_id');

            // Handle household selection change
            householdSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];

                if (selectedOption.value) {
                    // Populate the form fields with selected household data
                    firstNameInput.value = selectedOption.dataset.firstname;
                    middleNameInput.value = selectedOption.dataset.middlename;
                    lastNameInput.value = selectedOption.dataset.lastname;
                    cellphoneInput.value = selectedOption.dataset.cellphone;
                    householdIdInput.value = selectedOption.value;
                } else {
                    // Clear the form fields if no household is selected
                    firstNameInput.value = '';
                    middleNameInput.value = '';
                    lastNameInput.value = '';
                    cellphoneInput.value = '';
                    householdIdInput.value = '';
                }
            });

            let confirmBtn = document.getElementById("confirmSaveBtn");
            let violationForm = document.getElementById("violationForm");

            // Add form submission handler to add validation classes
            violationForm.addEventListener("submit", function (event) {
                event.preventDefault(); // Prevent default submission

                // Add Bootstrap validation class
                violationForm.classList.add("was-validated");

                // Check if form is valid
                if (violationForm.checkValidity()) {
                    // Show confirmation modal if form is valid
                    let confirmModal = new bootstrap.Modal(document.getElementById("confirmModal"));
                    confirmModal.show();
                }
                // If form is invalid, the was-validated class will show the validation errors
            });

            confirmBtn.addEventListener("click", function () {
                let formData = new FormData(violationForm);

                fetch("save_violation.php", {
                    method: "POST",
                    body: formData
                })
                    .then(res => res.text())
                    .then(data => {
                        if (data.trim() === "success") {
                            new bootstrap.Modal(document.getElementById("successModal")).show();
                            violationForm.reset();
                            // Remove validation class after successful reset
                            violationForm.classList.remove("was-validated");
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

            // File upload functionality
            const fileDropArea = document.getElementById('fileDropArea');
            const fileInput = document.getElementById('fileInput');
            const browseLink = document.getElementById('browseLink');
            const filePreview = document.getElementById('filePreview');

            // Browse link click handler
            browseLink.addEventListener('click', function (e) {
                e.preventDefault();
                fileInput.click();
            });

            // Drag and drop handlers
            fileDropArea.addEventListener('dragover', function (e) {
                e.preventDefault();
                fileDropArea.classList.add('dragover');
            });

            fileDropArea.addEventListener('dragleave', function (e) {
                e.preventDefault();
                fileDropArea.classList.remove('dragover');
            });

            fileDropArea.addEventListener('drop', function (e) {
                e.preventDefault();
                fileDropArea.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    displayFilePreview(files[0]);
                }
            });

            // File input change handler
            fileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    displayFilePreview(this.files[0]);
                }
            });

            // Function to display file preview
            function displayFilePreview(file) {
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert to MB

                filePreview.innerHTML = `
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="bi bi-file-earmark-check me-2"></i>
                <div>
                    <strong>${fileName}</strong> (${fileSize} MB)
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeFile()">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
            }

            // Function to remove selected file
            window.removeFile = function () {
                fileInput.value = '';
                filePreview.innerHTML = '';
            };
        });
    </script>
</body>

</html>