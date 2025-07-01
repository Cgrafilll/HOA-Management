<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RFID Management</title>
    <link href='https://unpkg.com/boxicons@2.1.1/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background-color: #f8f8ff;
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

        .card-button:hover {
            transform: scale(1.05);
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2><i class='bx bx-id-card'></i> RFID System</h2>
        <a href="#" class="nav-link" onclick="showSection('home')">Home / Scanner</a>
        <a href="#" class="nav-link" onclick="showSection('residents')">Manage Residents</a>
        <a href="#" class="nav-link" onclick="showSection('visitors')">Manage Visitors</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Home Section -->
        <div id="home-section">
            <h1 class="mb-3">Scan RFID</h1>
            <div id="scanner-section" style="max-width: 600px;">
                <div id="log" class="border border-black overflow-y-auto"
                    style="max-height: 300px; padding: 10px; border-radius: 8px;">
                    <strong>Scan Log:</strong>
                    <div id="entries" class="mt-2"></div>
                </div>
            </div>
            <div id="gateStatus" class="alert alert-secondary mt-3" role="alert">
                Gate Status: <strong>CLOSED</strong>
            </div>
        </div>

        <!-- Residents Section -->
        <div id="residents-section" style="display: none;">
            <h2>Manage Residents</h2>
            <div class="input-group mb-3">
                <input type="text" id="residentUid" class="form-control" placeholder="Scan or Enter Resident UID">
                <button class="btn btn-primary" onclick="openModal('resident')">Add</button>
            </div>
            <ul id="residentList" class="list-group"></ul>
        </div>

        <!-- Visitors Section -->
        <div id="visitors-section" style="display: none;">
            <h2>Manage Visitors</h2>
            <div class="input-group mb-3">
                <input type="text" id="visitorUid" class="form-control" placeholder="Scan or Enter Visitor UID">
                <button class="btn btn-success" onclick="openModal('visitor')">Add</button>
            </div>
            <ul id="visitorList" class="list-group"></ul>
        </div>
    </div>

    <!-- Modal for Add User -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form onsubmit="submitForm(event)">
                    <div class="modal-header">
                        <h5 class="modal-title">Add User Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="modalType" />
                        <input type="hidden" id="modalUid" />
                        <div class="mb-2">
                            <label class="form-label">First Name</label>
                            <input type="text" id="firstName" class="form-control" required />
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Last Name</label>
                            <input type="text" id="lastName" class="form-control" required />
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Contact Number</label>
                            <input type="text" id="contact" class="form-control" />
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Address</label>
                            <textarea id="address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const rfidUsers = { resident: {}, visitor: {} };
        let currentSection = 'home';
        let buffer = '';

        window.onload = () => {
            fetch('http://localhost/rfid-api/get_users.php')
                .then(res => res.json())
                .then(data => {
                    data.forEach(user => {
                        const name = `${user.firstName} ${user.lastName}`;
                        rfidUsers[user.type][user.uid] = name;
                    });
                    updateUIDList('resident');
                    updateUIDList('visitor');
                });
        };

        function openModal(type) {
            const uid = document.getElementById(type + 'Uid').value.trim();
            if (!uid) return alert("Please enter or scan UID first.");
            if (rfidUsers.resident[uid] || rfidUsers.visitor[uid]) return alert("UID already exists.");

            document.getElementById('modalType').value = type;
            document.getElementById('modalUid').value = uid;
            document.getElementById('firstName').value = '';
            document.getElementById('lastName').value = '';
            document.getElementById('contact').value = '';
            document.getElementById('address').value = '';

            const modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        }

        function submitForm(event) {
            event.preventDefault();

            const uid = document.getElementById('modalUid').value;
            const type = document.getElementById('modalType').value;
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const contact = document.getElementById('contact').value.trim();
            const address = document.getElementById('address').value.trim();

            if (!firstName || !lastName) return alert("Name is required.");

            fetch('http://localhost/rfid-api/add_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ uid, type, firstName, lastName, contact, address })
            })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        const user = response.user;
                        const fullName = `${user.firstName} ${user.lastName}`;
                        rfidUsers[type][uid] = fullName;
                        updateUIDList(type);
                        handleRFID(uid);
                        document.getElementById(type + 'Uid').value = '';
                        bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
                    } else {
                        alert("Failed to save user: " + response.message);
                    }
                });
        }

        function showSection(section) {
            ['home-section', 'residents-section', 'visitors-section'].forEach(id => {
                document.getElementById(id).style.display = 'none';
            });
            document.getElementById(section + '-section').style.display = 'block';
            currentSection = section;
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                if (buffer.length >= 10) {
                    const uid = buffer;
                    if (currentSection === 'home') handleRFID(uid);
                    else if (currentSection === 'residents' || currentSection === 'visitors')
                        document.getElementById(currentSection.slice(0, -1) + 'Uid').value = uid;
                }
                buffer = '';
            } else buffer += e.key;
        });

        function handleRFID(uid) {
            let label;
            let className;
            let badgeHTML;
            const gateStatusEl = document.getElementById('gateStatus');

            let isAuthorized = false;

            if (rfidUsers.resident[uid]) {
                label = `${rfidUsers.resident[uid]} (${uid})`;
                className = 'text-success';
                badgeHTML = '<span class="badge bg-success me-2">Resident</span>';
                gateStatusEl.className = 'alert alert-success mt-3';
                gateStatusEl.innerHTML = 'Gate Status: <strong>OPENED (Resident)</strong>';
                isAuthorized = true;
            } else if (rfidUsers.visitor[uid]) {
                label = `${rfidUsers.visitor[uid]} (${uid})`;
                className = 'text-primary';
                badgeHTML = '<span class="badge bg-primary me-2">Visitor</span>';
                gateStatusEl.className = 'alert alert-primary mt-3';
                gateStatusEl.innerHTML = 'Gate Status: <strong>OPENED (Visitor)</strong>';
                isAuthorized = true;
            } else {
                label = `Unknown UID: ${uid}`;
                className = 'text-danger';
                badgeHTML = '<span class="badge bg-danger me-2">Unknown</span>';
                gateStatusEl.className = 'alert alert-danger mt-3';
                gateStatusEl.innerHTML = 'Gate Status: <strong>CLOSED (Unknown)</strong>';
            }

            const entry = document.createElement('div');
            entry.className = `entry mb-2 ${className}`;
            entry.innerHTML = `${badgeHTML}${label} <span class="text-muted">at ${new Date().toLocaleTimeString()}</span>`;
            document.getElementById('entries').prepend(entry);

            if (isAuthorized) {
                fetch('http://localhost/rfid-api/trigger_servo.php')
                    .then(res => res.text())
                    .then(console.log)
                    .catch(err => console.error('Servo trigger failed:', err));
            }
        }

        function updateUIDList(type) {
            const listId = type + 'List';
            const list = document.getElementById(listId);
            list.innerHTML = '';

            for (const uid in rfidUsers[type]) {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                const nameText = document.createElement('span');
                nameText.textContent = `${rfidUsers[type][uid]} (${uid})`;

                const delBtn = document.createElement('button');
                delBtn.className = 'btn btn-sm btn-danger';
                delBtn.textContent = 'Remove';
                delBtn.onclick = () => {
                    fetch('http://localhost/rfid-api/delete_user.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ uid })
                    }).then(() => {
                        delete rfidUsers[type][uid];
                        updateUIDList(type);
                    });
                };

                const btnGroup = document.createElement('div');
                btnGroup.appendChild(delBtn);

                li.appendChild(nameText);
                li.appendChild(btnGroup);
                list.appendChild(li);
            }
        }
    </script>

</body>

</html>