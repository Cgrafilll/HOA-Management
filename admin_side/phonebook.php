<?php
// ✅ Set session configuration BEFORE session_start()
ini_set('session.gc_maxlifetime', 7200); // 2 hours
ini_set('session.cookie_lifetime', 7200); // 2 hours

// Set session cookie parameters before starting session
session_set_cookie_params([
    'lifetime' => 7200, // 2 hours
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']), // Use secure cookies on HTTPS
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);

// NOW start the session
session_start();

require '../rfid-api/db.php'; // Adjust path as needed

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

$admin_id = $_SESSION['admin_id'];
$sql = "SELECT * FROM admin_accounts WHERE admin_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    echo "Admin not found.";
    exit;
}

// Initialize user details
$username = $admin['first_name']; // <- Set username directly from household query
$photo = ''; // Initialize photo; your existing profile photo block will set this later
// Only set $photo if profile_pic exists and is not null
if (!empty($admin['profile_picture'])) {
    $photo = 'data:image/jpeg;base64,' . base64_encode($admin['profile_picture']);
} else {
    $photo = ''; // Explicitly empty if no image is saved
}

// Handle AJAX update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_contact') {
    header('Content-Type: application/json');

    $household_id = trim($_POST['household_id']); // Keep as string, don't convert to int
    $landline = trim($_POST['landline']);
    $cellphone = trim($_POST['cellphone']);

    // Validate household_id format (should be HOU-#### where #### are digits)
    if (!preg_match('/^HOU-\d{4}$/', $household_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid household ID format: ' . $household_id]);
        exit;
    }

    // Debug logging
    error_log("Updating household_id: " . $household_id . " with landline: '" . $landline . "' and cellphone: '" . $cellphone . "'");

    try {
        // First verify the household exists
        $check_stmt = $conn->prepare("SELECT household_id FROM household_accounts WHERE household_id = ?");
        $check_stmt->bind_param("s", $household_id); // Use "s" for string instead of "i" for integer
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Household not found with ID: ' . $household_id]);
            exit;
        }
        $check_stmt->close();

        // Update the specific household record
        $stmt = $conn->prepare("UPDATE household_accounts SET landline = ?, cellphone_number = ? WHERE household_id = ?");
        $stmt->bind_param("sss", $landline, $cellphone, $household_id); // All strings: "sss"

        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            error_log("Update executed for " . $household_id . ". Affected rows: " . $affected_rows);

            // Always return success, regardless of whether changes were made
            echo json_encode(['success' => true, 'message' => 'Update completed successfully', 'affected_rows' => $affected_rows, 'household_id' => $household_id]);
        } else {
            error_log("SQL Execute failed for " . $household_id . ": " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Failed to execute update query for ' . $household_id . ': ' . $stmt->error]);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Database error for " . $household_id . ": " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error for ' . $household_id . ': ' . $e->getMessage()]);
    }
    exit;
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
                    <li><a class="dropdown-item" href="admin/view_admin.php?id=<?php echo $admin_id; ?>"><i
                                class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="login/logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
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
                            <li><a href="violation_tracking.php" class="nav-link px-2">Violation Tracking</a></li>
                            <li><a href="entry_logs.php" class="nav-link px-2">Gate Logs</a></li>
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
                            <li><a href="payment.php" class="nav-link px-2">Payments</a></li>
                            <li><a href="billing.php" class="nav-link px-2">Billing</a></li>
                            <li><a href="invoices.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
                <a href="login/logout.php"
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
                                    <p class="mb-3" id="successMessage">Contact information has been updated.</p>
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Confirmation Modal -->
                    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content text-center">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title fw-bold">Confirmation</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <i class="bi bi-question-circle text-success" style="font-size: 64px;"></i>
                                    <p class="mb-2"><b>Save Changes?</b></p>
                                    <p class="mb-3">Are you sure you want to save the contact information changes?</p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-success" id="confirmSave">Save</button>
                                        <button type="button" class="btn btn-secondary btn-cancel"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                        $landline = $row['landline'] ?: '';
                                        $cellphone = $row['cellphone_number'] ?: '';
                                        $street = $row['street_address'];
                                        echo '
                                        <tr data-id="' . $household_id . '">
                                            <td class="name-field">' . htmlspecialchars($fullName) . '</td>
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
            let currentRow = null;
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));

            // Event listeners for dynamically generated buttons
            document.addEventListener('click', function (e) {
                // Handle Edit button click
                if (e.target.closest('.edit-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const row = e.target.closest('tr');
                    enableEditMode(row);
                    return;
                }

                // Handle Save button click
                if (e.target.closest('.save-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const row = e.target.closest('tr');
                    currentRow = row; // Store the specific row that triggered the save
                    console.log('Save clicked for row ID:', row.dataset.id); // Debug log
                    confirmModal.show();
                    return;
                }

                // Handle Cancel button click
                if (e.target.closest('.cancel-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const row = e.target.closest('tr');
                    cancelEdit(row);
                    return;
                }
            });

            // Confirm save button in modal
            document.getElementById('confirmSave').addEventListener('click', function () {
                if (currentRow) {
                    console.log('Confirming save for row ID:', currentRow.dataset.id); // Debug log
                    confirmModal.hide();
                    saveChanges(currentRow);
                    currentRow = null; // Clear the reference after use
                }
            });

            function enableEditMode(row) {
                console.log('Enabling edit mode for row ID:', row.dataset.id); // Debug log

                // Disable all other edit buttons to prevent multiple edits
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    if (btn.closest('tr') !== row) {
                        btn.disabled = true;
                    }
                });

                // Add edit mode styling
                row.classList.add('edit-mode');

                // Get landline and cellphone fields for THIS specific row
                const landlineField = row.querySelector('.landline-field');
                const cellphoneField = row.querySelector('.cellphone-field');

                // Store original values
                const landlineOriginal = landlineField.textContent.trim();
                const cellphoneOriginal = cellphoneField.textContent.trim();

                // Convert to input fields
                landlineField.innerHTML = `<input type="text" class="editable-field" value="${landlineOriginal}" maxlength="20" placeholder="Enter landline">`;
                cellphoneField.innerHTML = `<input type="text" class="editable-field" value="${cellphoneOriginal}" maxlength="20" placeholder="Enter cellphone">`;

                // Toggle buttons for THIS specific row
                const editBtn = row.querySelector('.edit-btn');
                const saveBtn = row.querySelector('.save-btn');
                const cancelBtn = row.querySelector('.cancel-btn');

                editBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';

                // Focus on first input
                landlineField.querySelector('input').focus();
            }

            function saveChanges(row) {
                const householdId = row.dataset.id;
                console.log('Saving changes for row ID:', householdId); // Debug log

                if (!householdId) {
                    console.error('No household ID found for this row');
                    alert('Error: No household ID found for this row');
                    return;
                }

                const landlineInput = row.querySelector('.landline-field input');
                const cellphoneInput = row.querySelector('.cellphone-field input');

                if (!landlineInput || !cellphoneInput) {
                    console.error('Input fields not found in row:', householdId);
                    return;
                }

                const landlineValue = landlineInput.value.trim();
                const cellphoneValue = cellphoneInput.value.trim();

                console.log('Values to save for household ID', householdId + ':', { landlineValue, cellphoneValue }); // Debug log

                // Create form data
                const formData = new FormData();
                formData.append('action', 'update_contact');
                formData.append('household_id', householdId);
                formData.append('landline', landlineValue);
                formData.append('cellphone', cellphoneValue);

                // Show loading state for THIS specific row
                const saveBtn = row.querySelector('.save-btn');
                const originalSaveBtnText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="bi bi-arrow-repeat me-2 spinner-border spinner-border-sm"></i>Saving...';
                saveBtn.disabled = true;

                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Server response for household ID', householdId + ':', data); // Debug log
                        if (data.success) {
                            // Update display with new values for THIS specific row
                            updateDisplay(row, landlineValue, cellphoneValue);

                            // Show success modal
                            document.getElementById('successMessage').textContent = data.message;
                            successModal.show();
                        } else {
                            alert('Error updating household ID ' + householdId + ': ' + data.message);
                            // Reset save button for THIS specific row
                            saveBtn.innerHTML = originalSaveBtnText;
                            saveBtn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error updating household ID', householdId + ':', error);
                        alert('An error occurred while saving changes for household ID ' + householdId);
                        // Reset save button for THIS specific row
                        saveBtn.innerHTML = originalSaveBtnText;
                        saveBtn.disabled = false;
                    });
            }

            function cancelEdit(row) {
                console.log('Canceling edit for row ID:', row.dataset.id); // Debug log

                const landlineField = row.querySelector('.landline-field');
                const cellphoneField = row.querySelector('.cellphone-field');

                // Restore original values for THIS specific row
                const landlineOriginal = landlineField.dataset.original || '';
                const cellphoneOriginal = cellphoneField.dataset.original || '';

                landlineField.innerHTML = landlineOriginal;
                cellphoneField.innerHTML = cellphoneOriginal;

                // Remove edit mode styling from THIS specific row
                row.classList.remove('edit-mode');

                // Toggle buttons for THIS specific row
                const editBtn = row.querySelector('.edit-btn');
                const saveBtn = row.querySelector('.save-btn');
                const cancelBtn = row.querySelector('.cancel-btn');

                editBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                cancelBtn.style.display = 'none';

                // Re-enable all edit buttons
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.disabled = false;
                });
            }

            function updateDisplay(row, landlineValue, cellphoneValue) {
                console.log('Updating display for row ID:', row.dataset.id); // Debug log

                const landlineField = row.querySelector('.landline-field');
                const cellphoneField = row.querySelector('.cellphone-field');

                // Update display values and data attributes for THIS specific row
                landlineField.innerHTML = landlineValue;
                landlineField.dataset.original = landlineValue;

                cellphoneField.innerHTML = cellphoneValue;
                cellphoneField.dataset.original = cellphoneValue;

                // Remove edit mode styling from THIS specific row
                row.classList.remove('edit-mode');

                // Toggle buttons for THIS specific row
                const editBtn = row.querySelector('.edit-btn');
                const saveBtn = row.querySelector('.save-btn');
                const cancelBtn = row.querySelector('.cancel-btn');

                // Reset save button text and state
                saveBtn.innerHTML = '<i class="bi bi-check2 me-2"></i>Save';
                saveBtn.disabled = false;

                editBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                cancelBtn.style.display = 'none';

                // Re-enable all edit buttons
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.disabled = false;
                });
            }
        });
    </script>

</body>

</html>