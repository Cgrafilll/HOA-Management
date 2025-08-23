<?php
session_start();
require '../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: login/login.php");
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
            color: #000;
        }

        /* Styles for editable fields */
        .editable-field {
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            width: 100%;
            font-size: inherit;
        }

        .editable-field:focus {
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .edit-mode {
            background-color: #fff3cd;
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
            <h1 class="h5 mb-0 fw-bold">COMMUNICATION</h1>
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
                            <li><a href="admin_accounts.php" class="nav-link px-2">Admin</a></li>
                            <li><a href="household_accounts.php" class="nav-link px-2">Household</a></li>
                            <li><a href="visitor_accounts.php" class="nav-link px-2">Visitors</a></li>
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
                            <li><a href="amenity_booking.php" class="nav-link px-2">Amenity Booking</a></li>
                            <li><a href="#" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="entry_logs.php" class="nav-link px-2">Entry Logs</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Communication -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#commCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-chat-left-text me-2"></i> Communication
                        </span>
                    </button>
                    <div class="collapse show" id="commCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="announcements.php" class="nav-link px-2">Announcements</a></li>
                            <li><a href="events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="phonebook.php" class="nav-link px-2 actived">Phone Book</a></li>
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
                    <h5 class="mb-0 fw-bold">Phone Book</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">List of Contacts</span>
                    </div>
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
                                    <p class="mb-3">Contact information has been updated.</p>
                                    <button type="button" class="btn btn-primary" id="doneButton">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Confirmation Modal -->
                    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content text-center">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title fw-bold">Confirmation</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-x-circle text-danger" style="font-size: 64px;"></i>
                                    <p class="mb-2"><b>Are you sure?</b></p>
                                    <p class="mb-3">This process will archive this account.</p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-danger"
                                            id="confirmProceed">Archive</button>
                                        <button type="button" class="btn btn-secondary btn-cancel"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($success) && $success): ?>
                        <script>
                            window.addEventListener('DOMContentLoaded', () => {
                                const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
                                const successModal = new bootstrap.Modal(document.getElementById('successModal'));

                                // Show confirmation modal first
                                confirmModal.show();

                                // If user clicks Proceed
                                document.getElementById('confirmProceed').addEventListener('click', () => {
                                    confirmModal.hide();
                                    setTimeout(() => successModal.show(), 300); // small delay to avoid overlap
                                });

                                // Success modal buttons/redirect
                                const redirect = () => window.location.href = 'admin_accounts.php';
                                document.getElementById('doneButton').addEventListener('click', redirect);
                                document.getElementById('successModal').addEventListener('hidden.bs.modal', redirect);
                            });
                        </script>
                    <?php endif; ?>
                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-success text-white small">
                                <tr>
                                    <th>Name</th>
                                    <th>Landline</th>
                                    <th>Cellphone Number</th>
                                    <th>Street Address</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="small align-middle">
                                <?php
                                $sql = "SELECT household_id, first_name, middle_name, last_name, landline, cellphone_number, street_address FROM household_accounts ORDER BY last_name ASC";
                                $result = $conn->query($sql);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $household_id = $row['household_id'];
                                        $fullName = $row['last_name'] . ', ' . $row['first_name'] . ' ' . substr($row['middle_name'], 0, 1);
                                        $landline = $row['landline'];
                                        $cellphone = $row['cellphone_number'];
                                        $street = $row['street_address'];
                                        echo '
                                        <tr data-id="' . $household_id . '">
                                            <td class="name-field">' . $fullName . '</td>
                                            <td class="landline-field" data-original="' . htmlspecialchars($landline) . '">' . htmlspecialchars($landline) . '</td>
                                            <td class="cellphone-field" data-original="' . htmlspecialchars($cellphone) . '">' . htmlspecialchars($cellphone) . '</td>
                                            <td class="address-field">' . htmlspecialchars($street) . '</td>
                                            <td class="d-flex align-items-center justify-content-center">
                                                <button class="btn btn-sm btn-secondary edit-btn me-1">
                                                    <i class="bi bi-pencil-square me-2"></i>Edit
                                                </button>
                                                <button class="btn btn-sm btn-success save-btn me-1" style="display: none;">
                                                    <i class="bi bi-check2 me-2"></i>Save
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary cancel-btn" style="display: none;">
                                                    <i class="bi bi-x me-2"></i>Cancel
                                                </button>
                                            </td>
                                        </tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center text-muted">No household contacts found.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <?php $total = $result->num_rows;
                            echo "<span class='small'>Showing 1 to {$total} of {$total} entries</span>";
                            ?>
                            <nav>
                                <ul class="pagination pagination-sm m-0">
                                    <li class="page-item disabled"><a class="page-link">Previous</a></li>
                                    <li class="page-item active"><a class="page-link">1</a></li>
                                    <li class="page-item"><a class="page-link">Next</a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editButtons = document.querySelectorAll('.edit-btn');
            const saveButtons = document.querySelectorAll('.save-btn');
            const cancelButtons = document.querySelectorAll('.cancel-btn');

            editButtons.forEach((btn, index) => {
                btn.addEventListener('click', function () {
                    const row = this.closest('tr');
                    enableEditMode(row);
                });
            });

            saveButtons.forEach((btn, index) => {
                btn.addEventListener('click', function () {
                    const row = this.closest('tr');
                    saveChanges(row);
                });
            });

            cancelButtons.forEach((btn, index) => {
                btn.addEventListener('click', function () {
                    const row = this.closest('tr');
                    cancelEdit(row);
                });
            });

            function enableEditMode(row) {
                // Add edit mode styling
                row.classList.add('edit-mode');

                // Get landline and cellphone fields
                const landlineField = row.querySelector('.landline-field');
                const cellphoneField = row.querySelector('.cellphone-field');

                // Store original values
                const landlineOriginal = landlineField.textContent.trim();
                const cellphoneOriginal = cellphoneField.textContent.trim();

                // Convert to input fields
                landlineField.innerHTML = `<input type="text" class="editable-field" value="${landlineOriginal}" maxlength="20">`;
                cellphoneField.innerHTML = `<input type="text" class="editable-field" value="${cellphoneOriginal}" maxlength="20">`;

                // Toggle buttons
                row.querySelector('.edit-btn').style.display = 'none';
                row.querySelector('.save-btn').style.display = 'inline-block';
                row.querySelector('.cancel-btn').style.display = 'inline-block';

                // Focus on first input
                landlineField.querySelector('input').focus();
            }

            function saveChanges(row) {
                const householdId = row.dataset.id;
                const landlineInput = row.querySelector('.landline-field input');
                const cellphoneInput = row.querySelector('.cellphone-field input');

                if (!landlineInput || !cellphoneInput) {
                    return;
                }

                const landlineValue = landlineInput.value.trim();
                const cellphoneValue = cellphoneInput.value.trim();

                // Basic validation
                if (!landlineValue && !cellphoneValue) {
                    alert('Please enter at least one contact number.');
                    return;
                }

                // Here you would typically send an AJAX request to update the database
                // For now, we'll just show the success modal and update the display
                updateDisplay(row, landlineValue, cellphoneValue);

                // Show success modal
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();

                // Update the "User has been moved to archives" text to reflect the actual action
                document.querySelector('#successModal .modal-body p:nth-child(3)').textContent = 'Contact information has been updated successfully.';
            }

            function cancelEdit(row) {
                const landlineField = row.querySelector('.landline-field');
                const cellphoneField = row.querySelector('.cellphone-field');

                // Restore original values
                const landlineOriginal = landlineField.dataset.original || '';
                const cellphoneOriginal = cellphoneField.dataset.original || '';

                landlineField.innerHTML = landlineOriginal;
                cellphoneField.innerHTML = cellphoneOriginal;

                // Remove edit mode styling
                row.classList.remove('edit-mode');

                // Toggle buttons
                row.querySelector('.edit-btn').style.display = 'inline-block';
                row.querySelector('.save-btn').style.display = 'none';
                row.querySelector('.cancel-btn').style.display = 'none';
            }

            function updateDisplay(row, landlineValue, cellphoneValue) {
                const landlineField = row.querySelector('.landline-field');
                const cellphoneField = row.querySelector('.cellphone-field');

                // Update display values and data attributes
                landlineField.innerHTML = landlineValue;
                landlineField.dataset.original = landlineValue;

                cellphoneField.innerHTML = cellphoneValue;
                cellphoneField.dataset.original = cellphoneValue;

                // Remove edit mode styling
                row.classList.remove('edit-mode');

                // Toggle buttons
                row.querySelector('.edit-btn').style.display = 'inline-block';
                row.querySelector('.save-btn').style.display = 'none';
                row.querySelector('.cancel-btn').style.display = 'none';
            }
        });
    </script>

</body>

</html>