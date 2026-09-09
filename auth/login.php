<?php
require_once __DIR__ . '/../Config/auth.php';

if (isLoggedIn()) {
    header('Location: ../dashboard/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (loginUser($username, $password)) {
        header('Location: ../dashboard/index.php');
        exit;
    }

    $error = 'Username atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login - Network Monitor PRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=1">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-card">
            <div class="brand-icon"><i class="bi bi-router-fill"></i></div>
            <div class="brand-title">Network Monitor</div>
            <div class="brand-subtitle">PRO Network Management System</div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2" role="alert">
                    <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input class="form-control" id="username" name="username" type="text" placeholder="Masukkan username" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input class="form-control" id="password" name="password" type="password" placeholder="Masukkan password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button class="btn btn-primary w-100 login-button" type="submit">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                </button>
            </form>

            <div class="login-footer">Network Monitor PRO &copy; <?= date('Y') ?></div>
        </section>
    </main>

    <script>
        const password = document.getElementById('password');
        const toggle = document.getElementById('togglePassword');
        toggle.addEventListener('click', () => {
            const visible = password.type === 'text';
            password.type = visible ? 'password' : 'text';
            toggle.innerHTML = visible ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
        });
    </script>
</body>
</html>
