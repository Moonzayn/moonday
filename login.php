<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = 'Semua field wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['avatar_color'] = $user['avatar_color'];

            // Log activity
            $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'login', 'Login berhasil')");
            $logStmt->execute([$user['id']]);

            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Username/email atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - moonday</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="logo-icon">✦</div>
            <h1>Selamat Datang!</h1>
            <p>Masuk ke akun moonday kamu</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">⚠️ <?= $error ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">✅ Registrasi berhasil! Silakan login.</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username atau Email</label>
                <div class="input-icon-wrapper">
                    <span class="icon">👤</span>
                    <input type="text" name="login" class="form-control" placeholder="username atau email" value="<?= htmlspecialchars($login ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-icon-wrapper">
                    <span class="icon">🔒</span>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 8px;">
                🔑 Masuk
            </button>
        </form>

        <div class="divider-text">atau</div>

        <p style="text-align: center; font-size: 14px; color: var(--gray-500);">
            Belum punya akun? <a href="register.php" class="link">Daftar gratis</a>
        </p>
    </div>
</div>
</body>
</html>