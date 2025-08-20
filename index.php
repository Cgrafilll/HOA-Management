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
        let autoCloseTimer = null;

        document.addEventListener("keypress", function (e) {
            if (e.key === "Enter") {
                if (buffer.length > 0) {
                    const uid = buffer;
                    buffer = "";

                    const entriesDiv = document.getElementById("entries");
                    const newEntry = document.createElement("div");
                    newEntry.classList.add("p-2", "border-bottom");

                    const topLine = document.createElement("div");
                    topLine.classList.add("d-flex", "justify-content-between", "fw-bold");

                    const uidText = document.createElement("span");
                    uidText.textContent = "Scanned UID: " + uid;

                    const statusSpan = document.createElement("span");
                    statusSpan.textContent = "Checking...";
                    statusSpan.classList.add("text-secondary");

                    topLine.appendChild(uidText);
                    topLine.appendChild(statusSpan);

                    const nameText = document.createElement("div");
                    nameText.textContent = "Name: Checking...";
                    nameText.classList.add("text-muted");

                    newEntry.appendChild(topLine);
                    newEntry.appendChild(nameText);
                    entriesDiv.prepend(newEntry);

                    fetch("rfid-api/check_uid.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: "uid=" + encodeURIComponent(uid)
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === "success") {
                                nameText.textContent = "Name: " + (data.full_name || "Unknown Card");
                                nameText.classList.remove("text-muted");

                                if (data.type === "household") {
                                    statusSpan.textContent = "Household Member";
                                    statusSpan.classList.remove("text-secondary");
                                    statusSpan.classList.add("text-success");
                                    triggerGate("open");

                                } else if (data.type === "visitor") {
                                    statusSpan.textContent = "Visitor";
                                    statusSpan.classList.remove("text-secondary");
                                    statusSpan.classList.add("text-primary");
                                    triggerGate("open");
                                }

                                // ✅ Auto close after 5 seconds
                                resetAutoClose();

                            } else {
                                statusSpan.textContent = "Not Registered";
                                statusSpan.classList.remove("text-secondary");
                                statusSpan.classList.add("text-danger");

                                nameText.textContent = "Name: Unknown Card";
                                nameText.classList.remove("text-muted");

                                // ✅ Immediately close gate if not registered
                                triggerGate("close");
                            }
                        })

                        .catch(err => {
                            statusSpan.textContent = "Error";
                            statusSpan.classList.remove("text-secondary");
                            statusSpan.classList.add("text-warning");

                            nameText.textContent = "Name: Unknown Card";
                            nameText.classList.remove("text-muted");
                            console.error("Error:", err);

                            // Use mock database when API fails
                            const userData = mockDatabase[uid];
                            if (userData) {
                                statusSpan.textContent = userData.type === "household" ? "Household Member" : "Visitor";
                                statusSpan.classList.remove("text-secondary", "text-warning");
                                statusSpan.classList.add(userData.type === "household" ? "text-success" : "text-primary");

                                nameText.textContent = "Name: " + userData.full_name;
                                triggerGate("open");
                                resetAutoClose();
                            } else {
                                // ✅ Close gate on error or unknown card
                                triggerGate("close");
                            }
                        });
                }
            } else {
                buffer += e.key;
            }
        });

        // ✅ Function to call open_gate.php
        function triggerGate(action) {
            fetch("rfid-api/open_gate.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=" + encodeURIComponent(action)
            })
                .then(res => res.json())
                .then(data => {
                    const gateStatus = document.getElementById("gateStatus");
                    if (data.status === "success") {
                        gateStatus.innerHTML = `Gate Status: <strong>${data.gate}</strong>`;
                        gateStatus.className =
                            data.gate === "OPEN"
                                ? "alert alert-success mt-3"
                                : "alert alert-secondary mt-3";
                    } else {
                        gateStatus.innerHTML = `Gate Status: <strong>Error</strong>`;
                        gateStatus.className = "alert alert-danger mt-3";
                    }
                })
                .catch(err => {
                    console.error("Gate trigger error:", err);

                    // Demo gate status update when API fails
                    const gateStatus = document.getElementById("gateStatus");
                    if (action === "open") {
                        updateGateDisplay("OPEN");
                    } else if (action === "close") {
                        updateGateDisplay("CLOSED");
                    }
                });
        }

        // ✅ Close gate automatically after 5 seconds
        function resetAutoClose() {
            if (autoCloseTimer) clearTimeout(autoCloseTimer);
            autoCloseTimer = setTimeout(() => {
                triggerGate("close");
                // Update status display when auto-closing
                updateGateDisplay("CLOSED");
            }, 5000);
        }

        // Update gate status display
        function updateGateDisplay(status) {
            const gateStatus = document.getElementById("gateStatus");
            if (status === "OPEN") {
                gateStatus.innerHTML = `Gate Status: <strong>OPEN</strong>`;
                gateStatus.className = "alert alert-success mt-3";
            } else {
                gateStatus.innerHTML = `Gate Status: <strong>CLOSED</strong>`;
                gateStatus.className = "alert alert-secondary mt-3";
            }
        }
    </script>

</body>

</html>