<?php
// Session configuration
ini_set('session.gc_maxlifetime', 7200);
ini_set('session.cookie_lifetime', 7200);

session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();
require '../rfid-api/db.php';

// Authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    header("Location: login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

$_SESSION['last_activity'] = time();

// Get admin data
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE admin_id = ?");
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    exit("Admin not found.");
}

// Initialize user details
$username = $admin['first_name'];
$photo = !empty($admin['profile_picture'])
    ? 'data:image/jpeg;base64,' . base64_encode($admin['profile_picture'])
    : '';

// Define rates
$amenityRates = [
    "Swimming Pool" => [
        "homeowner" => [
            "day" => "₱100.00 / per person",
            "night" => "₱200.00 / per person"
        ],
        "visitor" => [
            "day" => "₱200.00 / per person",
            "night" => "₱300.00 / per person"
        ]
    ],
    "Clubhouse" => [
        "homeowner" => [
            "day" => "₱12,000.00",
            "night" => "₱12,000.00"
        ],
        "visitor" => [
            "day" => "₱15,000.00",
            "night" => "₱15,000.00"
        ]
    ],
    "Basketball Court" => [
        "homeowner" => [
            "day" => "₱200.00 / per person",
            "night" => "₱300.00 / per person"
        ],
        "visitor" => [
            "day" => "₱300.00 / per person",
            "night" => "₱400.00 / per person"
        ]
    ],
    "Gazebo" => [
        "homeowner" => [
            "day" => "₱1,000.00",
            "night" => "₱2,000.00"
        ],
        "visitor" => [
            "day" => "₱2,000.00",
            "night" => "₱3,000.00"
        ]
    ]
];

// Helper function to extract numeric value from rate string
function extractNumericRate($rateString)
{
    // Remove currency symbols and extract numeric value
    $cleaned = preg_replace('/[₱,]/', '', $rateString);
    $cleaned = preg_replace('/\s*\/.*$/', '', $cleaned); // Remove "/ per person" etc.
    return floatval($cleaned);
}

// Helper function to get amenity rate from the rates array
function getAmenityRate($amenity, $userType, $timeOfDay = 'day')
{
    global $amenityRates;

    $bookingUserType = ($userType === 'Homeowner/Resident') ? 'homeowner' : 'visitor';

    if (isset($amenityRates[$amenity][$bookingUserType][$timeOfDay])) {
        return $amenityRates[$amenity][$bookingUserType][$timeOfDay];
    }

    // Fallback to day rate if night not found
    if ($timeOfDay === 'night' && isset($amenityRates[$amenity][$bookingUserType]['day'])) {
        return $amenityRates[$amenity][$bookingUserType]['day'];
    }

    return null; // Default if not found
}

// Helper function to check if amenity is per person
function isPerPersonRate($rateString)
{
    return strpos($rateString, '/ per person') !== false;
}

// Helper function to handle file upload
function handleFileUpload($file, $uploadDir = '../uploads/payments/')
{
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'payment_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }

    return null;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {
            case 'get_households':
                $stmt = $conn->prepare("
                    SELECT household_id, first_name, middle_name, last_name 
                    FROM household_accounts 
                    WHERE status = 'active' 
                    ORDER BY household_id
                ");
                $stmt->execute();
                $result = $stmt->get_result();

                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'household_id' => $row['household_id'],
                        'name' => trim("{$row['first_name']} {$row['middle_name']} {$row['last_name']}")
                    ];
                }

                echo json_encode(['success' => true, 'data' => $data]);
                break;

            case 'get_visitors':
                $stmt = $conn->prepare("
                    SELECT visitor_id, first_name, middle_name, last_name 
                    FROM visitor_details 
                    WHERE status = 'active' 
                    ORDER BY visitor_id
                ");
                $stmt->execute();
                $result = $stmt->get_result();

                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'visitor_id' => $row['visitor_id'],
                        'name' => trim("{$row['first_name']} {$row['middle_name']} {$row['last_name']}")
                    ];
                }

                echo json_encode(['success' => true, 'data' => $data]);
                break;

            case 'get_amenity_booking_by_invoice':
                $invoice_number = $_GET['invoice_number'] ?? '';
                $user_id = $_GET['user_id'] ?? '';
                $user_type = $_GET['user_type'] ?? '';

                if (empty($invoice_number) || empty($user_id) || empty($user_type)) {
                    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
                    exit;
                }

                // Get user details and booking based on user type
                $user_table = ($user_type === 'Homeowner/Resident') ? 'household_accounts' : 'visitor_details';
                $user_id_field = ($user_type === 'Homeowner/Resident') ? 'household_id' : 'visitor_id';
                $booking_id_field = ($user_type === 'Homeowner/Resident') ? 'homeowner_id' : 'visitor_id';

                // Get user details
                $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM $user_table WHERE $user_id_field = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $user_data = $stmt->get_result()->fetch_assoc();

                // Get complete booking details including amenity info
                $stmt = $conn->prepare("
                    SELECT 
                        id,
                        reference_number, 
                        created_at,
                        amenity,
                        reservation_date,
                        guests,
                        rate,
                        exclusive_booking,
                        chairs,
                        tables,
                        total_amount,
                        amount_paid,
                        status
                    FROM amenity_bookings 
                    WHERE $booking_id_field = ? AND invoice_number = ?
                    LIMIT 1
                ");
                $stmt->bind_param("ss", $user_id, $invoice_number);
                $stmt->execute();
                $booking = $stmt->get_result()->fetch_assoc();

                if ($booking && $user_data) {
                    // CHECK IF ALREADY PAID
                    if (strtolower($booking['status']) === 'paid') {
                        echo json_encode(['success' => false, 'error' => 'This invoice has already been paid in full']);
                        exit;
                    }
                    
                    // Calculate balance
                    $balance_due = $booking['total_amount'] - $booking['amount_paid'];
                    
                    // Additional check: if balance is 0 or negative
                    if ($balance_due <= 0) {
                        echo json_encode(['success' => false, 'error' => 'This invoice has no remaining balance']);
                        exit;
                    }

                    // Build items array for table population
                    $items = [];

                    // Use the rate field from database (contains "day" or "night")
                    $timeOfDay = strtolower($booking['rate']); // Convert to lowercase for consistency

                    // Get proper amenity rate from rates array using database rate field
                    $rateString = getAmenityRate($booking['amenity'], $user_type, $timeOfDay);

                    // If rate not found in array, fallback to a default or error handling
                    if ($rateString === null) {
                        // Fallback: try to construct a basic rate or use a default
                        $rateString = "Rate not found for {$booking['amenity']} - {$timeOfDay}";
                        $numericRate = 0;
                    } else {
                        $numericRate = extractNumericRate($rateString);
                    }

                    // Check if this is a per-person rate
                    $isPerPerson = isPerPersonRate($rateString);

                    // Determine quantity and rate display
                    if ($isPerPerson) {
                        // For per-person rates, use guests count directly from database
                        $quantity = $booking['guests'];
                        $rateDisplay = number_format($numericRate, 2) . ' / per head';
                        $amount = $numericRate * $quantity;
                    } else {
                        // For flat rates, quantity is 1
                        $quantity = 1;
                        $rateDisplay = number_format($numericRate, 2);
                        $amount = $numericRate;
                    }

                    // Main amenity item
                    $items[] = [
                        'category' => 'Amenity',
                        'item' => $booking['amenity'],
                        'rate' => $rateDisplay,
                        'qty' => $quantity,
                        'amount' => number_format($amount, 2)
                    ];

                    // Add chairs if any exist
                    if ($booking['chairs'] > 0) {
                        $chairRate = 12.00; // Fixed rate: ₱12 per chair
                        $items[] = [
                            'category' => 'Amenity',
                            'item' => 'Chairs',
                            'rate' => number_format($chairRate, 2),
                            'qty' => $booking['chairs'],
                            'amount' => number_format($booking['chairs'] * $chairRate, 2)
                        ];
                    }

                    // Add tables if any
                    if ($booking['tables'] > 0) {
                        $tableRate = 20.00; // Fixed rate: ₱20 per table
                        $items[] = [
                            'category' => 'Amenity',
                            'item' => 'Tables',
                            'rate' => number_format($tableRate, 2),
                            'qty' => $booking['tables'],
                            'amount' => number_format($booking['tables'] * $tableRate, 2)
                        ];
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'booking_id' => $booking['id'],
                            'reference_number' => $booking['reference_number'],
                            'first_name' => $user_data['first_name'],
                            'middle_name' => $user_data['middle_name'],
                            'last_name' => $user_data['last_name'],
                            'created_at' => $booking['created_at'],
                            'reservation_date' => $booking['reservation_date'],
                            'items' => $items,
                            'subtotal' => number_format($booking['total_amount'], 2),
                            'amount_paid' => number_format($booking['amount_paid'], 2),
                            'balance_due' => number_format($balance_due, 2),
                            'status' => $booking['status']
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No booking found']);
                }
                break;

            // Add this case to the switch statement in payment.php (around line 150)

            case 'get_billing_by_invoice':
                $invoice_number = $_GET['invoice_number'] ?? '';
                $user_id = $_GET['user_id'] ?? '';
                $user_type = $_GET['user_type'] ?? '';
                $category = $_GET['category'] ?? '';

                if (empty($invoice_number) || empty($user_id) || empty($user_type) || empty($category)) {
                    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
                    exit;
                }

                // Only homeowners/residents can have these types of fees
                if ($user_type !== 'Homeowner/Resident') {
                    echo json_encode(['success' => false, 'error' => $category . ' only apply to homeowners/residents']);
                    exit;
                }

                // Get user details
                $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM household_accounts WHERE household_id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $user_data = $stmt->get_result()->fetch_assoc();

                // Map category to database value
                $db_category = '';
                if ($category === 'Monthly Dues') {
                    $db_category = 'monthly_dues';
                } elseif ($category === 'Penalty Fees') {
                    $db_category = 'penalty_fees';
                } elseif ($category === 'Other Fees') {
                    $db_category = 'other_fees';
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid category']);
                    exit;
                }

                // Get billing details from monthly_dues table
                $stmt = $conn->prepare("
                    SELECT 
                        id,
                        invoice_number,
                        category,
                        billing_month,
                        description,
                        amount_paid,
                        balance_remaining,
                        due_date,
                        status,
                        created_at
                    FROM monthly_dues 
                    WHERE household_id = ? AND invoice_number = ? AND category = ?
                    LIMIT 1
                ");
                $stmt->bind_param("sss", $user_id, $invoice_number, $db_category);
                $stmt->execute();
                $billing = $stmt->get_result()->fetch_assoc();

                if ($billing && $user_data) {
                    // CHECK IF ALREADY PAID
                    $status_lower = strtolower(trim($billing['status']));
                    if ($status_lower === 'paid' || $status_lower === 'completed') {
                        echo json_encode(['success' => false, 'error' => 'This invoice has already been paid in full']);
                        exit;
                    }
                    
                    // Additional check: if balance remaining is 0 or negative
                    if ($billing['balance_remaining'] <= 0) {
                        echo json_encode(['success' => false, 'error' => 'This invoice has no remaining balance']);
                        exit;
                    }

                    // Calculate total amount
                    $total_amount = $billing['amount_paid'] + $billing['balance_remaining'];

                    // Build items array for table population
                    $items = [];

                    // Create display name based on category
                    $itemName = '';
                    if ($db_category === 'monthly_dues') {
                        $billing_month = date('F Y', strtotime($billing['billing_month']));
                        $itemName = "Association Dues - {$billing_month}";
                    } elseif ($db_category === 'penalty_fees') {
                        $itemName = "Penalty Fee";
                    } elseif ($db_category === 'other_fees') {
                        $itemName = "Other Fee";
                    }

                    // Add description if exists
                    if (!empty($billing['description'])) {
                        $itemName .= " - " . $billing['description'];
                    }

                    // Main billing item
                    $items[] = [
                        'category' => $category,
                        'item' => $itemName,
                        'rate' => number_format($total_amount, 2),
                        'qty' => 1,
                        'amount' => number_format($total_amount, 2)
                    ];

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'billing_id' => $billing['id'],
                            'reference_number' => $invoice_number,
                            'first_name' => $user_data['first_name'],
                            'middle_name' => $user_data['middle_name'],
                            'last_name' => $user_data['last_name'],
                            'created_at' => $billing['created_at'] ?? $billing['due_date'],
                            'billing_month' => $billing['billing_month'] ?? null,
                            'description' => $billing['description'] ?? null,
                            'items' => $items,
                            'subtotal' => number_format($total_amount, 2),
                            'amount_paid' => number_format($billing['amount_paid'], 2),
                            'balance_due' => number_format($billing['balance_remaining'], 2),
                            'status' => $billing['status']
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No billing record found']);
                }
                break;

            case 'process_payment':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
                    exit;
                }

                $category = $_POST['category'] ?? '';
                $user_type = $_POST['user_type'] ?? '';
                $user_id = $_POST['user_id'] ?? '';
                $invoice_number = $_POST['invoice_number'] ?? '';
                $amount = floatval($_POST['amount'] ?? 0);
                $payment_method = ($_POST['payment_method'] === 'Bank Transfer') ? 'bank' : 'cash';
                $reference_number = $_POST['reference_number'] ?? '';

                // Validate required fields
                if (empty($category) || empty($user_type) || empty($user_id) || empty($invoice_number) || $amount <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                    exit;
                }

                // Handle file upload
                $proof_filename = null;
                if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
                    $proof_filename = handleFileUpload($_FILES['proof_of_payment']);
                    if (!$proof_filename) {
                        echo json_encode(['success' => false, 'error' => 'Failed to upload proof of payment']);
                        exit;
                    }
                }

                $conn->begin_transaction();

                try {
                    $reference_id = null;
                    $db_user_type = ($user_type === 'Homeowner/Resident') ? 'homeowner' : 'visitor';
                    $household_id = ($user_type === 'Homeowner/Resident') ? $user_id : null;
                    $visitor_id = ($user_type === 'Visitor') ? $user_id : null;

                    if ($category === 'Amenity Fee') {
                        // Get amenity booking details
                        $booking_id_field = ($user_type === 'Homeowner/Resident') ? 'homeowner_id' : 'visitor_id';
                        $stmt = $conn->prepare("SELECT id, amount_paid, total_amount FROM amenity_bookings WHERE $booking_id_field = ? AND invoice_number = ?");
                        $stmt->bind_param("ss", $user_id, $invoice_number);
                        $stmt->execute();
                        $booking = $stmt->get_result()->fetch_assoc();

                        if (!$booking) {
                            throw new Exception('Amenity booking not found');
                        }

                        $reference_id = $booking['id'];
                        $new_amount_paid = $booking['amount_paid'] + $amount;
                        $balance = $booking['total_amount'] - $new_amount_paid;

                        // Determine new status
                        if ($balance <= 0) {
                            $new_status = 'paid';
                        } elseif ($new_amount_paid > 0) {
                            $new_status = 'partial';
                        } else {
                            $new_status = 'pending';
                        }

                        // Update amenity booking
                        $stmt = $conn->prepare("UPDATE amenity_bookings SET amount_paid = ?, status = ? WHERE id = ?");
                        $stmt->bind_param("dsi", $new_amount_paid, $new_status, $reference_id);
                        $stmt->execute();

                        $db_category = 'amenity';

                    } elseif (in_array($category, ['Monthly Dues', 'Penalty Fees', 'Other Fees'])) {
                        if ($user_type !== 'Homeowner/Resident') {
                            throw new Exception($category . ' only apply to homeowners/residents');
                        }

                        // Map category to database value
                        $db_billing_category = '';
                        if ($category === 'Monthly Dues') {
                            $db_billing_category = 'monthly_dues';
                        } elseif ($category === 'Penalty Fees') {
                            $db_billing_category = 'penalty_fees';
                        } elseif ($category === 'Other Fees') {
                            $db_billing_category = 'other_fees';
                        }

                        // Get billing details
                        $stmt = $conn->prepare("SELECT id, amount_paid, balance_remaining FROM monthly_dues WHERE household_id = ? AND invoice_number = ? AND category = ?");
                        $stmt->bind_param("sss", $user_id, $invoice_number, $db_billing_category);
                        $stmt->execute();
                        $billing = $stmt->get_result()->fetch_assoc();

                        if (!$billing) {
                            throw new Exception($category . ' record not found');
                        }

                        $reference_id = $billing['id'];
                        $new_amount_paid = $billing['amount_paid'] + $amount;
                        $new_balance = $billing['balance_remaining'] - $amount;

                        // Ensure balance doesn't go negative
                        if ($new_balance < 0) {
                            $new_balance = 0;
                        }

                        // Determine new status
                        if ($new_balance <= 0) {
                            $new_status = 'Completed';
                        } elseif ($new_amount_paid > 0) {
                            $new_status = 'Partial';
                        } else {
                            $new_status = 'Pending';
                        }

                        // Update billing record
                        $stmt = $conn->prepare("UPDATE monthly_dues SET amount_paid = ?, balance_remaining = ?, status = ? WHERE id = ?");
                        $stmt->bind_param("ddsi", $new_amount_paid, $new_balance, $new_status, $reference_id);
                        $stmt->execute();

                        $db_category = 'monthly_dues';
                    } else {
                        throw new Exception('Invalid category');
                    }

                    // Insert payment record
                    $stmt = $conn->prepare("
                        INSERT INTO payments (category, reference_id, invoice_number, user_type, household_id, visitor_id, amount, payment_method, reference_number, proof_of_payment) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("sisssdssss", $db_category, $reference_id, $invoice_number, $db_user_type, $household_id, $visitor_id, $amount, $payment_method, $reference_number, $proof_filename);
                    $stmt->execute();

                    $payment_id = $conn->insert_id;

                    $conn->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Payment processed successfully',
                        'payment_id' => $payment_id
                    ]);

                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit; // THIS IS CRUCIAL - prevents HTML from being sent with AJAX responses
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NSSHAI HOA Management - Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../images/SitioSeville_Logo.png" type="image/x-icon">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap');

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

        .loading {
            color: #6c757d;
            font-size: 14px;
            margin-top: 5px;
        }

        .form-select:disabled {
            background-color: #f8f9fa;
            opacity: 0.8;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="bg-light">
    <!-- Header -->
    <header class="bg-white shadow-sm py-3 px-4 d-flex align-items-center">
        <div class="me-4" style="width: 250px;">
            <img src="../images/NSSHAI_crop.png" alt="NSSHAI" class="img-fluid" style="height: 56px;">
        </div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <h1 class="h5 mb-0 fw-bold">ACCOUNTING</h1>
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2 dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown"
                    aria-expanded="false" role="button" style="cursor: pointer;">
                    <span>Hello, <?= htmlspecialchars($username) ?></span>
                    <div class="d-flex align-items-center justify-content-center overflow-hidden rounded-5"
                        style="height: 40px; width: 40px; color: #aaa;">
                        <?php if (!empty($photo)): ?>
                            <img src="<?= htmlspecialchars($photo) ?>"
                                style="width: 40px; height: 40px; object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle" style="font-size: 32px;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="admin/view_admin.php?id=<?= $admin_id ?>">
                            <i class="bi bi-person me-2"></i>Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="login/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar p-3">
            <nav class="nav d-flex flex-column gap-1">
                <a href="admin_dashboard.php"
                    class="nav-link px-3 py-2 rounded d-flex align-items-center justify-content-start">
                    <i class="bi bi-house me-2"></i> Home
                </a>
                <!-- Accounts -->
                <div>
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#accountsCollapse">
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
                        data-bs-target="#recordCollapse">
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
                    <button class="btn btn-toggle collapsed px-3 py-2" data-bs-toggle="collapse"
                        data-bs-target="#commCollapse">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-chat-left-text me-2"></i> Communication
                        </span>
                    </button>
                    <div class="collapse" id="commCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="announcements.php" class="nav-link px-2">Announcements</a></li>
                            <li><a href="events.php" class="nav-link px-2">Events</a></li>
                            <li><a href="phonebook.php" class="nav-link px-2">Phone Book</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Accounting (Active) -->
                <div>
                    <button class="btn btn-toggle px-3 py-2 active" data-bs-toggle="collapse"
                        data-bs-target="#acctCollapse">
                        <span class="d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2"></i> Accounting
                        </span>
                    </button>
                    <div class="collapse show" id="acctCollapse">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="payment.php" class="nav-link px-2 actived">Payment</a></li>
                            <li><a href="billing.php" class="nav-link px-2">List of Billings</a></li>
                            <li><a href="invoices.php" class="nav-link px-2">Invoices</a></li>
                        </ul>
                    </div>
                </div>

                <a href="login/logout.php" class="nav-link mb-3 px-3 py-2 rounded logout"
                    style="position: fixed; bottom: 0; width: 220px;">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-fill p-4">
            <div class="bg-white shadow rounded p-4">
                <div class="bg-success text-white rounded-top p-3">
                    <h5 class="mb-0 fw-bold">Payment</h5>
                </div>

                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Payment Management</span>
                    </div>
                    <hr class="mb-3 mt-0">

                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-8">
                            <!-- Payment Method Selection -->
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

                            <!-- Payment Form -->
                            <form id="paymentForm">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">User Type<small
                                                class="fw-bold text-danger">*</small></label>
                                        <select class="form-select" id="userTypeSelect" required>
                                            <option value="">Select User Type</option>
                                            <option value="Homeowner/Resident">Homeowner/Resident</option>
                                            <option value="Visitor">Visitor</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" id="idLabel">Select ID<small
                                                class="fw-bold text-danger">*</small></label>
                                        <select class="form-select" id="userIdSelect" disabled required>
                                            <option value="">First select user type</option>
                                        </select>
                                        <div class="loading d-none" id="loadingIndicator">
                                            <i class="bi bi-arrow-clockwise"></i> Loading available IDs...
                                        </div>
                                    </div>
                                </div>

                                

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Category<small class="fw-bold text-danger">*</small></label>
                                        <select class="form-select" id="categorySelect" required>
                                            <option value="">Select Category</option>
                                            <option value="Monthly Dues">Monthly Dues</option>
                                            <option value="Penalty Fees">Penalty Fees</option>
                                            <option value="Other Fees">Other Fees</option>
                                            <option value="Amenity Fee">Amenity Fee</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Billing Number<small class="fw-bold text-danger">*</small></label>
                                        <input type="text" class="form-control" id="invoiceInput"
                                            placeholder="Enter Billing Number" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Amount Paid<small
                                            class="fw-bold text-danger">*</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="amountPaid" placeholder="0.00"
                                            min="0" step="0.01" required>
                                    </div>
                                </div>

                                <div class="mb-3" id="referenceNumberGroup" style="display: none;">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="referenceNumber"
                                        placeholder="Bank transfer reference number">
                                </div>

                                <!-- Summary Display -->
                                <div class="bg-light rounded p-3 mb-3">
                                    <p class="mb-1"><strong>Billing No:</strong> <span id="refNo"></span></p>
                                    <p class="mb-1"><strong>Name:</strong> <span id="residentName"></span></p>
                                    <p class="mb-1"><strong>Issue Date:</strong> <span id="issueDate"></span></p>
                                    <p class="mb-1"><strong>Payment Method:</strong> <span id="selectedMethod">Bank
                                            Transfer</span></p>
                                </div>

                                <!-- Invoice Details Table -->
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
                                    <tbody id="invoiceTableBody">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No invoice data loaded</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div class="d-flex justify-content-end mb-3">
                                    <div>
                                        <p class="mb-1"><strong>Subtotal:</strong> <span id="subtotal">₱0.00</span></p>
                                        <p class="mb-1"><strong>Previously Paid:</strong> <span
                                                id="previouslyPaid">₱0.00</span></p>
                                        <p class="fw-bold text-success">Balance Due: <span id="balanceDue">₱0.00</span>
                                        </p>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Make Payment</button>
                            </form>
                        </div>

                        <!-- Right Column -->
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

                            <!-- Upload Section -->
                            <div class="border rounded p-3 text-center">
                                <h6 class="fw-bold">Upload Proof of Payment</h6>
                                <div class="file-drop-area" id="fileDropArea" style="height: 250px;">
                                    <div class="cloud-icon">
                                        <i class="bi bi-cloud-upload"></i>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Drag & drop files or <a href="#" id="browseLink">Browse</a></strong>
                                    </div>
                                    <div class="small text-muted">
                                        Supported formats: JPEG, PNG, GIF, PDF
                                    </div>
                                    <input type="file" id="fileInput" name="evidence" class="d-none"
                                        accept="image/jpeg,image/png,image/gif,application/pdf">
                                </div>
                                <div id="filePreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Payment Confirmation Modal -->
    <div class="modal fade" id="confirmPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Confirm Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-question-circle text-primary" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Are you sure?</b></p>
                    <p class="mb-3">Do you want to process this payment?</p>
                    <div class="bg-light rounded p-3 mb-3 text-start">
                        <div class="row">
                            <div class="col-6"><strong>Name:</strong></div>
                            <div class="col-6" id="confirmName"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Category:</strong></div>
                            <div class="col-6" id="confirmCategory"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Billing No.:</strong></div>
                            <div class="col-6" id="confirmInvoice"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Amount:</strong></div>
                            <div class="col-6" id="confirmAmount"></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Method:</strong></div>
                            <div class="col-6" id="confirmMethod"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="confirmPaymentBtn">Process Payment</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Success Modal -->
    <div class="modal fade" id="successPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Payment Successful</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-check-circle text-success" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Payment Processed Successfully!</b></p>
                    <p class="mb-3">The payment has been recorded in the system.</p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Error Modal -->
    <div class="modal fade" id="errorPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Payment Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Payment Error!</b></p>
                    <p class="mb-3" id="errorMessage">An error occurred while processing your payment.</p>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Error Modal -->
    <div class="modal fade" id="errorPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Payment Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 64px;"></i>
                    <p class="mt-3 mb-2"><b>Payment Error!</b></p>
                    <p class="mb-3" id="errorMessage">An error occurred while processing your payment.</p>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="javascripts/payment.js"></script>
</body>

</html>