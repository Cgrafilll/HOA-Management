<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.1/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            background-color: #f8f8ff;
            font-family: 'Montserrat', sans-serif;
        }

        .sidebar {
            width: 250px;
            background-color: #343a40;
            padding: 20px;
            color: white;
        }

        .sidebar h2 {
            font-size: 20px;
            margin-bottom: 20px;
        }

        .sidebar .nav-link {
            color: white;
            margin: 10px 0;
            display: block;
        }

        .sidebar .nav-link:hover {
            background-color: #495057;
            border-radius: 5px;
            padding: 5px 10px;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2><i class='bx bx-id-card'></i> RFID System</h2>
        <span class="nav-link">Home / Scanner</span>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div id="home-section">
            <h1 class="mb-3">Scan RFID</h1>
            <div id="scanner-section" style="max-width: 600px;">
                <div id="log" class="border border-black overflow-y-auto"
                    style="max-height: 600px; padding: 10px; border-radius: 8px;">
                    <strong>Scan Log:</strong>
                    <div id="entries" class="mt-2"></div>
                </div>
            </div>
            <div id="gateStatus" class="alert alert-secondary mt-3" role="alert">
                Gate Status: <strong>CLOSED</strong>
            </div>
        </div>
    </div>

    <script>
        let buffer = "";

        document.addEventListener("keypress", function (e) {
            if (e.key === "Enter") {
                if (buffer.length > 0) {
                    const uid = buffer;
                    buffer = "";

                    // Create entry div
                    const entriesDiv = document.getElementById("entries");
                    const newEntry = document.createElement("div");
                    newEntry.classList.add("p-2", "border-bottom", "d-flex", "justify-content-between", "align-items-center");

                    // UID text
                    const uidText = document.createElement("span");
                    uidText.textContent = "Scanned UID: " + uid;

                    // Status placeholder
                    const statusSpan = document.createElement("span");
                    statusSpan.textContent = "Checking...";
                    statusSpan.classList.add("ms-2", "fw-bold", "text-secondary");

                    newEntry.appendChild(uidText);
                    newEntry.appendChild(statusSpan);
                    entriesDiv.prepend(newEntry);

                    // Send to backend for validation
                    fetch("rfid-api/check_uid.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: "uid=" + encodeURIComponent(uid)
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === "success") {
                                if (data.type === "household") {
                                    statusSpan.textContent = "Household Member";
                                    statusSpan.classList.remove("text-secondary");
                                    statusSpan.classList.add("text-success");
                                } else if (data.type === "visitor") {
                                    statusSpan.textContent = "Visitor";
                                    statusSpan.classList.remove("text-secondary");
                                    statusSpan.classList.add("text-primary");
                                }
                            } else {
                                statusSpan.textContent = "Not Registered";
                                statusSpan.classList.remove("text-secondary");
                                statusSpan.classList.add("text-danger");
                            }
                        })
                        .catch(err => {
                            statusSpan.textContent = "Error";
                            statusSpan.classList.remove("text-secondary");
                            statusSpan.classList.add("text-warning");
                            console.error("Error:", err);
                        });
                }
            } else {
                buffer += e.key;
            }
        });
    </script>

</body>

</html>