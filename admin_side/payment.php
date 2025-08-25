<?php
session_start();
require '../rfid-api/db.php';

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
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;" />
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">ACCOUNTING</h1>
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
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#commCollapse" aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-chat-left-text me-2"></i> Communication
                        </span>
                    </button>
                    <div class="collapse" id="commCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="announcements.php" class="nav-link px-2 ">Announcements</a></li>
                            <li><a href="events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="phonebook.php" class="nav-link px-2">Phone Book</a></li>
                        </ul>
                    </div>
                </div>
                <!-- Accounting -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#acctCollapse" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse show" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="#" class="nav-link px-2 actived">Payments</a></li>
                            <li><a href="#" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-4">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Payments</h4>
                </div>
                <p class="text-muted">Payment Management</p>

                <div class="row">
                    <!-- Left Side -->
                    <div class="col-md-8">
                        <!-- Payment Method Toggle -->
                        <div class="d-flex gap-3 mb-3">
                            <div class="card method-card flex-fill text-center p-3 border active" id="bankTransfer">
                                <div><i class="bi bi-bank" style="font-size: 2rem;"></i></div>
                                <h6 class="mt-2">EastWest Bank Transfer</h6>
                            </div>
                            <div class="card method-card flex-fill text-center p-3 border" id="inOffice">
                                <div><i class="bi bi-building" style="font-size: 2rem;"></i></div>
                                <h6 class="mt-2">In-Office Payment</h6>
                            </div>
                        </div>

                        <!-- Form -->
                        <form>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">User Type*</label>
                                    <select class="form-select">
                                        <option value="Homeowner/Resident">Homeowner/Resident</option>
                                        <option value="Visitor">Visitor</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Resident ID*</label>
                                    <input type="text" class="form-control" placeholder="Enter Resident ID">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Category*</label>
                                    <select class="form-select">
                                        <option>Monthly Dues</option>
                                        <option>Amenity Fee</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Reference Number*</label>
                                    <input type="text" class="form-control" placeholder="Enter Reference Number">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Amount Paid*</label>
                                <input type="text" class="form-control" placeholder="Enter Amount">
                            </div>

                            <div class="bg-light rounded p-3 mb-3">
                                <p class="mb-1"><strong>Name:</strong> Abby Sungwon C. Saja</p>
                                <p class="mb-1"><strong>Invoice No.:</strong> 0451</p>
                                <p class="mb-1"><strong>Issue Date:</strong> 2025-08-23</p>
                                <p class="mb-1"><strong>Payment Method:</strong> <span id="selectedMethod">Bank Transfer</span></p>
                            </div>

                            <!-- Table -->
                            <table class="table table-bordered">
                                <thead class="table-success">
                                    <tr>
                                        <th>Category</th>
                                        <th>Item</th>
                                        <th>Rate</th>
                                        <th>Qty</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Amenity</td>
                                        <td>Clubhouse</td>
                                        <td>₱ 12,000.00</td>
                                        <td>1</td>
                                        <td>₱ 12,000.00</td>
                                    </tr>
                                    <tr>
                                        <td>Add-On</td>
                                        <td>Chairs</td>
                                        <td>₱ 12.00</td>
                                        <td>48</td>
                                        <td>₱ 576.00</td>
                                    </tr>
                                    <tr>
                                        <td>Add-On</td>
                                        <td>Tables</td>
                                        <td>₱ 15.00</td>
                                        <td>6</td>
                                        <td>₱ 90.00</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="d-flex justify-content-end">
                                <div>
                                    <p class="mb-1"><strong>Subtotal:</strong> ₱ 12,666.00</p>
                                    <p class="mb-1"><strong>Previously Paid:</strong> ₱ 6,333.00</p>
                                    <p class="fw-bold text-success">Balance Due: ₱ 6,333.00</p>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Make Payment</button>
                        </form>
                    </div>

                    <!-- Right Side -->
                    <div class="col-md-4">
                        <!-- Payment Methods Info -->
                        <div class="card border mb-3">
                            <div class="card-body">
                                <h6 class="fw-bold">PAYMENT METHODS</h6>
                                <p class="mb-1"><strong>Bank Transfer Details</strong></p>
                                <ul class="mb-3">
                                    <li><strong>Bank:</strong> EastWest Bank</li>
                                    <li><strong>Account Name:</strong> Neopolitan Sitio Seville</li>
                                    <li><strong>Account Number:</strong> 200049887271</li>
                                </ul>

                                <p class="mb-1"><strong>In-Office Payment</strong></p>
                                <ul>
                                    <li><strong>Address:</strong> NSSHAI Clubhouse Narra St., Quezon City</li>
                                    <li><strong>Office Hours:</strong> Mon–Fri, 8AM–5PM</li>
                                    <li><strong>Accepted:</strong> Cash</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Upload Proof -->
                        <div class="border rounded p-3 text-center">
                            <h6 class="fw-bold">Upload Proof of Payment</h6>
                            <div class="border rounded p-4 bg-light text-muted">
                                <p class="mb-1">Drag & drop files or <a href="#">Browse</a></p>
                                <small>Supported formats: JPEG, PNG, GIF, MP4, PDF, DOC, DOCX</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <style>
            .method-card {
                cursor: pointer;
                transition: 0.3s;
            }
            .method-card.active {
                border: 2px solid #007bff;
                background-color: #e9f2ff;
            }
            .method-card:hover {
                border-color: #007bff;
            }
        </style>

        <script>
            // Toggle Payment Method
            const bankTransfer = document.getElementById('bankTransfer');
            const inOffice = document.getElementById('inOffice');
            const selectedMethod = document.getElementById('selectedMethod');

            bankTransfer.addEventListener('click', () => {
                bankTransfer.classList.add('active');
                inOffice.classList.remove('active');
                selectedMethod.textContent = "Bank Transfer";
            });

            inOffice.addEventListener('click', () => {
                inOffice.classList.add('active');
                bankTransfer.classList.remove('active');
                selectedMethod.textContent = "In-Office Payment";
            });
        </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
