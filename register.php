<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fullName)) $errors[] = 'Nama lengkap wajib diisi.';
    if (empty($username)) $errors[] = 'Username wajib diisi.';
    if (strlen($username) < 3) $errors[] = 'Username minimal 3 karakter.';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = 'Username hanya boleh huruf, angka, dan underscore.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
    if ($password !== $confirmPassword) $errors[] = 'Konfirmasi password tidak cocok.';

    // Check duplicates
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Username atau email sudah terdaftar.';
        }
    }

    if (empty($errors)) {
        $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#06b6d4'];
        $avatarColor = $colors[array_rand($colors)];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, avatar_color) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword, $fullName, $avatarColor]);
        $userId = $pdo->lastInsertId();

        // Create default categories
        $defaultCategories = [
            ['Pekerjaan', '#6366f1', '💼'],
            ['Pribadi', '#ec4899', '🏠'],
            ['Belajar', '#f59e0b', '📚'],
            ['Kesehatan', '#10b981', '💪'],
            ['Belanja', '#8b5cf6', '🛒']
        ];

        $catStmt = $pdo->prepare("INSERT INTO categories (user_id, name, color, icon) VALUES (?, ?, ?, ?)");
        foreach ($defaultCategories as $cat) {
            $catStmt->execute([$userId, $cat[0], $cat[1], $cat[2]]);
        }

        // Log activity
        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'register', 'Akun berhasil dibuat')");
        $logStmt->execute([$userId]);

        $success = 'Registrasi berhasil! Silakan login.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - moonday</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="logo-icon">✦</div>
            <h1>Buat Akun Baru</h1>
            <p>Mulai kelola tugas kamu dengan moonday</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                ⚠️ <?= implode('<br>', $errors) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <div class="input-icon-wrapper">
                    <span class="icon">👤</span>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" value="<?= htmlspecialchars($fullName ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Username</label>
                <div class="input-icon-wrapper">
                    <span class="icon">@</span>
                    <input type="text" name="username" class="form-control" placeholder="johndoe" value="<?= htmlspecialchars($username ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <div class="input-icon-wrapper">
                    <span class="icon">✉️</span>
                    <input type="email" name="email" class="form-control" placeholder="john@example.com" value="<?= htmlspecialchars($email ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-icon-wrapper">
                    <span class="icon">🔒</span>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                </div>
            </div>

            <div class="form-group">
                <label>Konfirmasi Password</label>
                <div class="input-icon-wrapper">
                    <span class="icon">🔒</span>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 8px;">
                🚀 Daftar Sekarang
            </button>
        </form>

        <div class="divider-text">atau</div>

        <p style="text-align: center; font-size: 14px; color: var(--gray-500);">
            Sudah punya akun? <a href="login.php" class="link">Masuk di sini</a>
        </p>
    </div>
</div>
</body>
</html>