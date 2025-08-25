<?php
session_start();
require '../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: login.php");
    exit;
}

// Initialize user details
$email_address = $_SESSION['email_address'];
$username = $photo = ''; // Initialize user details

// Fetch user details including profile photo
try {
    $stmt = $conn->prepare("SELECT * FROM household_accounts WHERE email_address = ?");
    $stmt->bind_param("s", $email_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $username = $user['first_name'];
        $_SESSION['household_id'] = $user['household_id'];

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
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">REPORT VIOLATION</h1>
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
            <nav class="nav d-flex flex-column gap-1">
                <a href="dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <a href="amenity_booking/amenity_booking.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-book me-2"></i> Amenity Booking
                </a>
                <a href="report.php"
                    class="nav-link px-3 py-2 rounded active d-flex align-items-center justify-content-start">
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
        <!--Main Content-->
        <main class="flex-grow-1 p-4">
            <div class="bg-white shadow rounded p-3">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Report Violation</h5>
                </div>
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <span class="small mb-0">Violation Report Form</span>
                </div>
                <hr class="my-0">
                <div class="p-3">
                    <form action="add_violation.php" id="violationForm" method="POST" enctype="multipart/form-data">
                        <!-- Personal Info -->
                        <div class="row">
                            <span class="fw-bold mb-3">Reporter Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="first_name" class="form-control"
                                    value="<?php echo htmlspecialchars($user['first_name']); ?>" readonly />
                                <label class=" form-label mt-2">First Name<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="middle_name" class="form-control"
                                    value="<?php echo htmlspecialchars($user['middle_name']); ?>" readonly />
                                <label class=" form-label mt-2">Middle Name<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="text" name="last_name" class="form-control"
                                    value="<?php echo htmlspecialchars($user['last_name']); ?>" readonly />
                                <label class=" form-label mt-2">Last Name<small
                                        class="fw-bold text-danger">*</small></label>
                            </div>
                        </div>
                        <!-- Contact -->
                        <div class="row">
                            <span class="fw-bold mb-3">Contact Information</span>
                            <div class="col-md-4 mb-3">
                                <input type="tel" name="cellphone_number" class="form-control"
                                    value="<?php echo htmlspecialchars($user['cellphone_number']); ?>" pattern="[0-9]+"
                                    maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                    placeholder="e.g., 09171234567" readonly/>
                                <label class="form-label mt-2">Cellphone Number</label>
                            </div>
                        </div>
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
                                <label class="form-label mt-2">Street<small
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
                                    style="height: 250px; resize: none;" placeholder="Specifically describe what happened . . ."></textarea>
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
                        <input type="hidden" name="household_id" value="<?php echo htmlspecialchars($user['household_id']); ?>">
                    </form>
                    <!-- Confirm Save Modal -->
                    <div class="modal fade" id="confirmModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content text-center">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Confirm Save</h5>
                                    <button type="button" class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-question-circle text-primary" style="font-size: 64px;"></i>
                                    <p class="mb-2">Are you sure you want to save this violation?</p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-primary" id="confirmSaveBtn">Save</button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
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
                                    <button type="button" class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                                    <p class="mt-3 mb-2">Violation report saved successfully.</p>
                                    <button type="button" class="btn btn-success" data-bs-dismiss="modal"
                                        onclick="window.location.href='report.php'">OK</button>
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
        document.addEventListener("DOMContentLoaded", function () {
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

                fetch("save_report.php", {
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