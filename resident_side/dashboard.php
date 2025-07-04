<?php
session_start();
require '../rfid-api/db.php';

if (!isset($_SESSION['resident_id'])) {
    header("Location: login.php");
    exit;
}

$resident_id = $_SESSION['resident_id'];
$sql = "SELECT * FROM residents WHERE resident_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $resident_id);
$stmt->execute();
$result = $stmt->get_result();
$resident = $result->fetch_assoc();

if (!$resident) {
    echo "Resident not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Resident Dashboard | HOA Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

    <!-- Sidebar + Main Content -->
    <div class="flex min-h-screen">

        <!-- Sidebar -->
        <aside class="w-64 bg-blue-800 text-white flex flex-col p-6">
            <h1 class="text-2xl font-bold mb-6">HOA Resident</h1>
            <nav class="flex-1 space-y-4">
                <a href="account.php" class="block hover:bg-blue-700 px-3 py-2 rounded">Statement of Account</a>
                <a href="records.php" class="block hover:bg-blue-700 px-3 py-2 rounded">Personal Records</a>
                <a href="amenities.php" class="block hover:bg-blue-700 px-3 py-2 rounded">Amenities Schedule</a>
            </nav>
            <a href="login.php"
                class="block text-red-300 hover:text-white mt-auto pt-4 border-t border-blue-700">Logout</a>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">

            <!-- Welcome Section -->
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-800">Welcome, <span
                        class="text-blue-700"><?= htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']) ?></span>
                </h2>
                <p class="text-gray-600 mt-1">Here’s an overview of your HOA account and activities.</p>
            </div>

            <!-- Statement of Account -->
            <section id="account" class="bg-white p-6 rounded-xl shadow mb-8">
                <h3 class="text-2xl font-semibold mb-4 text-blue-700">Statement of Account</h3>
                <ul class="space-y-2 text-sm">
                    <li class="flex justify-between border-b pb-2">
                        <span>January 2025 Dues</span><span>₱1,500.00</span>
                    </li>
                    <li class="flex justify-between border-b pb-2">
                        <span>Late Fees</span><span>₱200.00</span>
                    </li>
                    <li class="flex justify-between font-bold">
                        <span>Total Due</span><span>₱1,700.00</span>
                    </li>
                </ul>
                <button class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Pay Now</button>
            </section>

            <!-- Personal Records -->
            <section id="records" class="bg-white p-6 rounded-xl shadow mb-8">
                <h3 class="text-2xl font-semibold mb-4 text-blue-700">Personal Records</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div><strong>Name:</strong>
                        <?= htmlspecialchars($resident['first_name'] . ' ' . $resident['middle_name'] . ' ' . $resident['last_name']) ?>
                    </div>
                    <div><strong>Unit:</strong> Block <?= htmlspecialchars($resident['block_no']) ?>, Lot
                        <?= htmlspecialchars($resident['lot_no']) ?></div>
                    <div><strong>Email:</strong> <?= htmlspecialchars($resident['email']) ?></div>
                    <div><strong>Contact:</strong> <?= htmlspecialchars($resident['contact_number']) ?></div>
                </div>
            </section>

            <!-- Amenities Schedule -->
            <section id="amenities" class="bg-white p-6 rounded-xl shadow">
                <h3 class="text-2xl font-semibold mb-4 text-blue-700">Amenities Schedule</h3>
                <table class="w-full text-sm table-auto border-collapse">
                    <thead class="bg-blue-100">
                        <tr>
                            <th class="text-left p-2">Amenity</th>
                            <th class="text-left p-2">Date</th>
                            <th class="text-left p-2">Time</th>
                            <th class="text-left p-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t">
                            <td class="p-2">Clubhouse</td>
                            <td class="p-2">July 6, 2025</td>
                            <td class="p-2">2:00 PM - 6:00 PM</td>
                            <td class="p-2 text-green-600 font-medium">Approved</td>
                        </tr>
                        <tr class="border-t">
                            <td class="p-2">Swimming Pool</td>
                            <td class="p-2">July 10, 2025</td>
                            <td class="p-2">10:00 AM - 12:00 PM</td>
                            <td class="p-2 text-yellow-600 font-medium">Pending</td>
                        </tr>
                    </tbody>
                </table>
            </section>

        </main>

    </div>

</body>

</html>