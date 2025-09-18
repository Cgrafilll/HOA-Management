<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Visitor Login | HOA Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../images/SitioSeville_Logo.png" type="image/x-icon">

</head>

<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

    <!-- Login Card -->
    <div class="card shadow-lg p-4 rounded-4 w-100" style="max-width: 400px;">
        <h2 class="text-center text-primary fw-bold mb-4">Visitor Login</h2>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <form action="visitor_login.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email_address" required class="form-control" />
            </div>

            <div class="mb-3 position-relative">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" id="password" name="password" required class="form-control" />
                    <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-5 mb-3">Login</button>
        </form>
    </div>

    <!-- Bootstrap 5 JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById("togglePassword").addEventListener("click", function () {
            const passwordInput = document.getElementById("password");
            const icon = document.getElementById("toggleIcon");

            const isPassword = passwordInput.type === "password";
            passwordInput.type = isPassword ? "text" : "password";
            icon.classList.toggle("bi-eye");
            icon.classList.toggle("bi-eye-slash");
        });
    </script>

</body>

</html>