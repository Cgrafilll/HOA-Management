
        <?php
// process_event_booking.php
session_start();
require '../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    header("Location: login/login.php");
    exit;
}

// Initialize user details
$email_address = $_SESSION['email_address'];
$username = $photo = '';

try {
    $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE email_address = ?");
    $stmt->bind_param("s", $email_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $username = $user['first_name'];
        $photo = !empty($user['profile_picture']) ? 'data:image/jpeg;base64,' . base64_encode($user['profile_picture']) : '';
    } else {
        $error_message = "Failed to fetch user details.";
    }

} catch (Exception $e) {
    $error_message = "Error fetching user details: " . $e->getMessage();
}

// ✅ Generate unique form token
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(16));
}

// ✅ Handle Event Insert BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['title'], $_POST['body'], $_POST['event_date'], $_POST['form_token'])) {
        
        // ✅ Check for duplicate submission
        if ($_POST['form_token'] !== $_SESSION['form_token']) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=duplicate");
            exit;
        }
        
        $title = trim($_POST['title']);
        $body = trim($_POST['body']);
        $body = preg_replace('/\s+/', ' ', $body); // collapse multiple spaces/newlines
        $event_date = $_POST['event_date'];
        $status = "published";
        $admin_id = $user['admin_id']; // make sure $user is already fetched

        try {
            $stmt = $conn->prepare("INSERT INTO events (admin_id, title, body, status, event_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $admin_id, $title, $body, $status, $event_date);
            $stmt->execute();

            // ✅ Clear the form token after successful insert
            unset($_SESSION['form_token']);
            
            // ✅ Redirect after insert (prevents duplicates on refresh)
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit;
        } catch (Exception $e) {
            $error_message = "Error adding event: " . $e->getMessage();
        }
    }
}
// Fetch all published events
try {
    $stmt = $conn->prepare("
        SELECT e.id, e.title, e.body, e.event_date, e.created_at, 
               a.first_name, a.last_name
        FROM events e
        JOIN admin_accounts a ON e.admin_id = a.admin_id
        WHERE e.status = 'published'
        ORDER BY e.created_at DESC
    ");
    $stmt->execute();
    $events_result = $stmt->get_result();
    $stmt->close();
} catch (Exception $e) {
    $events_result = null; // prevent undefined variable
    $error_message = "Failed to fetch events: " . $e->getMessage();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NSSHAI HOA Management - Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../images/SitioSeville_Logo.png" type="image/x-icon">
    <style>
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
        /* Same CSS as before, updated only where necessary for event cards */
        .event-card {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            max-width: 100%;
        }
        .event-title { 
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 6px; 
        }
        .event-body { 
            font-size: 0.95rem; 
            margin: 0; margin-bottom: 8px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
         }
        .event-meta {
            font-size: 0.8rem;
            color: #6c757d; 
        }
        .announcement-meta {
            font-size: 0.8rem;
            color: #6c757d; /* Bootstrap muted gray */
        }

        .announcement-actions a {
            font-size: 1rem;  /* adjust icon size */
            text-decoration: none;
        }
        .announcement-actions a:hover {
            opacity: 0.8;
        }
        .announcement-actions .btn {
            padding: 2px 6px; /* smaller buttons */
            font-size: 0.9rem;
        }

        main {
            margin-left: 250px;
            padding-bottom: 100px; /* ✅ give breathing room at bottom */
        }
        .card-body p, 
        .card-body h6 {
            word-wrap: break-word;       /* Old support */
            overflow-wrap: break-word;   /* Modern support */
            white-space: pre-wrap;       /* Keeps newlines */
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
            border: 2px solid #dc3545 !important; /* force red */
        }
        textarea {
            min-height: 100px;
            resize: none; /* optional: prevent manual drag */
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
            <h1 class="h5 mb-0 fw-bold">EVENTS</h1>
            <div class="d-flex align-items-center gap-2">
                <span class="text-secondary">Hello, <?php echo htmlspecialchars($username); ?></span>
                <div class="d-flex align-items-center justify-content-center overflow-hidden rounded-5"
                     style="height: 40px; width: 40px; color: #aaa;">
                    <?php if (!empty($photo)): ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>" style="width: 40px; height: 40px; object-fit: cover;">
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
                        data-bs-target="#commCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-chat-left-text me-2"></i> Communication
                        </span>
                    </button>
                    <div class="collapse show" id="commCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="#" class="nav-link px-2">Announcements</a></li>
                            <li><a href="events.php" class="nav-link px-2 actived">Events</a></li>
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
                    <h5 class="mb-0 fw-bold">Manage Events</h5>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Event Form</span>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-outline-secondary btn-sm">Archived Events</a>
                        </div>
                    </div>
                    <hr class="mb-3 mt-0">

                    <!-- Event Form -->
                    <form method="POST" id="eventForm">
                        <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['form_token']); ?>">

                        <h5 class="fw mb-2">Event Title</h5>
                        <input type="text" id="title" name="title" class="form-control border-dark rounded mb-1" maxlength="150" placeholder="Enter event title" required>

                        <h5 class="fw mb-2">Event Date</h5>
                        <input type="date" id="event_date" name="event_date" class="form-control mb-3" required>

                        <h5 class="fw mb-2 mt-3">Description</h5>
                        <textarea id="body" name="body" class="form-control border-dark rounded mb-1" style="min-height:100px; resize:none;" placeholder="Enter event description" required></textarea>

                        <p id="formError" class="text-danger small mt-2" style="display:none;">
                            Please fill in both Title, Description and Date before publishing.
                        </p>

                        <button type="button" class="btn btn-primary mt-3" id="publishBtn">Publish Event</button>
                    </form>
                    <!-- Published Events -->
                    <div class="mt-4">
                        <h5 class="fw-bold">Published Events</h5>
                        <hr>
                        <?php if (!empty($events_result)): ?>
                            <?php foreach ($events_result as $row): ?>
                                <div class="card mb-3 shadow-sm event-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="event-title"><?= htmlspecialchars($row['title']); ?></div>
                                            <div class="event-actions">
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                        data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id']; ?>" title="Edit">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary archiveBtn"
                                                        data-id="<?= $row['id']; ?>" title="Archive">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="event-body mt-2"><?= nl2br(htmlspecialchars($row['body'])); ?></div>
                                        <div class="event-meta mt-1">
                                            Posted by <?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?> 
                                            on <?= date("M d, Y h:i A", strtotime($row['created_at'])); ?> | Event Date: <?= date("M d, Y", strtotime($row['event_date'])); ?>
                                        </div>
                                    </div>

                                    <!-- Move Edit Modal here for this event -->
                                    <div class="modal fade" id="editModal<?= $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form class="editForm" data-id="<?= $row['id']; ?>">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title">Edit Event</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <label class="form-label">Title</label>
                                                        <input type="text" name="title" class="form-control mb-2" value="<?= htmlspecialchars($row['title']); ?>" required>
                                                        <label class="form-label mt-2">Event Date</label>
                                                        <input type="date" name="event_date" class="form-control mb-2" value="<?= htmlspecialchars($row['event_date']); ?>" required>
                                                        <label class="form-label mt-2">Description</label>
                                                        <textarea name="body" class="form-control" rows="5" required><?= htmlspecialchars($row['body']); ?></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="button" class="btn btn-success confirmEditBtn" data-id="<?= $row['id']; ?>">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No published events yet.</p>
                        <?php endif; ?>
                        <!-- Edit Confirmation Modal -->
                        <div class="modal fade" id="confirmEditModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content text-center">
                                        <div class="modal-header bg-success text-white">
                                            <h5 class="modal-title">Confirm Edit</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to save changes to this event?</p>
                                                <button type="button" class="btn btn-success" id="confirmEditBtn">Yes, Save</button>
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                        </div>
                                    </div>
                            </div>
                        </div>

                        <!-- Edit Success Modal -->
                        <div class="modal fade" id="editSuccessModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Success</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Event updated successfully!</p>
                                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Archive Confirmation Modal -->
                        <div class="modal fade" id="confirmArchiveModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-warning text-white">
                                        <h5 class="modal-title fw-bold">Confirm Archive</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 64px;"></i>
                                        <p class="mb-2"><b>Are you sure?</b></p>
                                        <p class="mb-3">Do you really want to archive this event?</p>
                                        <button type="button" class="btn btn-warning" id="confirmArchiveBtn">Yes, Archive</button>
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Archive Success Modal -->
                        <div class="modal fade" id="archiveSuccessModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title fw-bold">Archived!</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                                        <p class="mt-3 mb-2"><b>Event archived successfully.</b></p>
                                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Default Confirm Publish Modal -->
                        <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title fw-bold">Confirm Publish</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-question-circle text-primary" style="font-size: 64px;"></i>
                                        <p class="mb-2"><b>Are you sure?</b></p>
                                        <p class="mb-3">Do you really want to publish this event?</p>
                                        <button type="button" class="btn btn-primary" id="confirmPublish">Yes, Publish</button>
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Default Publish Success Modal -->
                        <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content text-center">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title fw-bold">Published!</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <i class="bi bi-check-circle-fill text-success" style="font-size: 64px;"></i>
                                        <p class="mt-3 mb-2"><b>Event published successfully.</b></p>
                                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === '1') {
                let successModalEl = document.getElementById('successModal');
                if (successModalEl) {
                    let successModal = new bootstrap.Modal(successModalEl);
                    successModal.show();

                    // Remove the query parameter so it doesn’t show on refresh
                    successModalEl.addEventListener('hidden.bs.modal', function () {
                        const url = new URL(window.location);
                        url.searchParams.delete('success');
                        window.history.replaceState({}, document.title, url.pathname);
                    });
                }
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function validateForm() {
                let title = document.getElementById("title");
                let body = document.getElementById("body");
                let date = document.getElementById("event_date");
                let errorMsg = document.getElementById("formError");
                let isValid = true;

                // Reset styles
                title.classList.remove("border-danger");
                body.classList.remove("border-danger");
                date.classList.remove("border-danger");
                errorMsg.style.display = "none";

                if (title.value.trim() === "") { title.classList.add("border-danger"); isValid = false; }
                if (body.value.trim() === "") { body.classList.add("border-danger"); isValid = false; }
                if (date.value === "") { date.classList.add("border-danger"); isValid = false; }

                if (!isValid) { errorMsg.style.display = "block"; }
                return isValid;
            }

            const publishBtn = document.getElementById("publishBtn");
            const confirmBtn = document.getElementById("confirmPublish");
            const eventForm = document.getElementById("eventForm");

            // Show confirmation modal if validation passes
            publishBtn.addEventListener("click", function () {
                if (validateForm()) {
                    let modal = new bootstrap.Modal(document.getElementById("confirmModal"));
                    modal.show();
                }
            });

            // Submit form on confirm
            confirmBtn.addEventListener("click", function () {
                this.disabled = true;
                this.textContent = "Publishing...";
                eventForm.submit();
            });

            // Auto-expand textarea
            document.querySelectorAll("textarea").forEach(function(el) {
                el.addEventListener("input", function () {
                    this.style.height = "auto";
                    this.style.height = this.scrollHeight + "px";
                });
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            let currentEditForm = null;
            let currentEventId = null;

            // When clicking "Save Changes" -> open confirmation modal
            document.querySelectorAll('.confirmEditBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    currentEventId = this.dataset.id;
                    currentEditForm = document.querySelector(`.editForm[data-id='${currentEventId}']`);

                    const editModalEl = document.getElementById(`editModal${currentEventId}`);
                    const editModalInstance = bootstrap.Modal.getInstance(editModalEl);

                    if (editModalInstance) {
                        editModalEl.addEventListener('hidden.bs.modal', function handler() {
                            // Show confirm modal after edit modal is fully hidden
                            const confirmModal = new bootstrap.Modal(document.getElementById('confirmEditModal'));
                            confirmModal.show();

                            editModalEl.removeEventListener('hidden.bs.modal', handler);
                        });

                        editModalInstance.hide();
                    } else {
                        // fallback if instance not found
                        const confirmModal = new bootstrap.Modal(document.getElementById('confirmEditModal'));
                        confirmModal.show();
                    }
                });
            });

            // When confirming in the modal, send the update
            document.getElementById('confirmEditBtn').addEventListener('click', function() {
                if (!currentEditForm) return;

                const formData = new FormData(currentEditForm);
                formData.append('id', currentEventId);

                fetch('events/update_events.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    const confirmModalEl = document.getElementById('confirmEditModal');
                    const confirmModal = bootstrap.Modal.getInstance(confirmModalEl);

                    if (data.success) {
                        confirmModal.hide();

                        const successModalEl = document.getElementById('editSuccessModal');
                        const successModal = new bootstrap.Modal(successModalEl);
                        successModal.show();

                        successModalEl.addEventListener('hidden.bs.modal', () => location.reload());
                    } else {
                        alert('Failed to update event: ' + data.message);
                    }
                })
                .catch(err => alert('Error updating event: ' + err.message));
            });
        });


    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let archiveEventId = null;

            document.querySelectorAll('.archiveBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    archiveEventId = this.dataset.id;
                    const archiveModal = new bootstrap.Modal(document.getElementById('confirmArchiveModal'));
                    archiveModal.show();
                });
            });

            document.getElementById('confirmArchiveBtn').addEventListener('click', function() {
                if (!archiveEventId) return;

                const formData = new FormData();
                formData.append('id', archiveEventId);

                fetch('events/process_archive.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    const archiveModalEl = document.getElementById('confirmArchiveModal');
                    const archiveModal = bootstrap.Modal.getInstance(archiveModalEl);

                    if (data.success) {
                        archiveModal.hide();

                        const archiveSuccessModalEl = document.getElementById('archiveSuccessModal');
                        const archiveSuccessModal = new bootstrap.Modal(archiveSuccessModalEl);
                        archiveSuccessModal.show();

                        archiveSuccessModalEl.addEventListener('hidden.bs.modal', () => location.reload());
                    } else {
                        alert('Failed to archive event: ' + data.message);
                    }
                })
                .catch(err => {
                    alert('Error archiving event: ' + err.message);
                });
            });
        });

    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>