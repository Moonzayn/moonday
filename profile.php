<?php
require_once 'includes/header.php';

$userId = getUserId();
$message = '';
$error = '';

// Get user data
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $avatarColor = $_POST['avatar_color'] ?? '#6366f1';

        if (empty($fullName) || empty($email)) {
            $error = 'Nama dan email wajib diisi.';
        } else {
            // Check email uniqueness
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkStmt->execute([$email, $userId]);
            if ($checkStmt->fetch()) {
                $error = 'Email sudah digunakan akun lain.';
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, bio = ?, avatar_color = ? WHERE id = ?");
                $updateStmt->execute([$fullName, $email, $bio, $avatarColor, $userId]);

                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;
                $_SESSION['avatar_color'] = $avatarColor;

                logActivity($pdo, $userId, null, 'updated', 'Memperbarui profil');
                $message = 'Profil berhasil diperbarui!';

                // Refresh user data
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch();
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            $error = 'Password saat ini salah.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $passStmt->execute([$hashedPassword, $userId]);
            logActivity($pdo, $userId, null, 'updated', 'Mengubah password');
            $message = 'Password berhasil diubah!';
        }
    }
}

// User stats
$totalTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?");
$totalTasks->execute([$userId]);
$totalCount = $totalTasks->fetchColumn();

$completedTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'completed'");
$completedTasks->execute([$userId]);
$completedCount = $completedTasks->fetchColumn();

$categoryCount = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE user_id = ?");
$categoryCount->execute([$userId]);
$catCount = $categoryCount->fetchColumn();

$memberSince = date('d M Y', strtotime($user['created_at']));
?>

<div class="page-header">
    <div>
        <h1 class="page-title">👤 Profil</h1>
        <p class="page-subtitle">Kelola akun dan pengaturan kamu</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success">✅ <?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger">⚠️ <?= $error ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Profile Card -->
    <div>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-body profile-card">
                <div class="avatar avatar-lg" style="background: <?= $user['avatar_color'] ?>; margin: 0 auto 16px;">
                    <?= getInitials($user['full_name']) ?>
                </div>
                <h2 style="font-size: 22px; font-weight: 700; color: var(--gray-900);"><?= htmlspecialchars($user['full_name']) ?></h2>
                <p style="color: var(--gray-500); font-size: 14px;">@<?= htmlspecialchars($user['username']) ?></p>
                <?php if ($user['bio']): ?>
                <p style="color: var(--gray-600); font-size: 14px; margin-top: 8px;"><?= htmlspecialchars($user['bio']) ?></p>
                <?php endif; ?>
                <p style="color: var(--gray-400); font-size: 12px; margin-top: 12px;">Member sejak <?= $memberSince ?></p>
                
                <div class="profile-stats">
                    <div class="profile-stat">
                        <div class="value"><?= $totalCount ?></div>
                        <div class="label">Total Tugas</div>
                    </div>
                    <div class="profile-stat">
                        <div class="value"><?= $completedCount ?></div>
                        <div class="label">Selesai</div>
                    </div>
                    <div class="profile-stat">
                        <div class="value"><?= $catCount ?></div>
                        <div class="label">Kategori</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Forms -->
    <div>
        <!-- Update Profile -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h2 class="card-title">✏️ Edit Profil</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" class="form-control" rows="3" placeholder="Ceritakan tentang dirimu..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Warna Avatar</label>
                        <input type="color" name="avatar_color" class="form-control" value="<?= $user['avatar_color'] ?>" style="height: 46px; padding: 6px;">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        💾 Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">🔒 Ubah Password</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Password Saat Ini</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Masukkan password saat ini" required>
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required>
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password baru" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-outline">
                        🔑 Ubah Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>