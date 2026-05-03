<?php
/**
 * Simple Email Helper
 * Send email notifications when tasks are created
 */

function sendTaskNotification($pdo, $task, $creatorName) {
    $config = include __DIR__ . '/../config/mail.php';
    
    if (!$config['enabled'] || empty($config['smtp_username'])) {
        return false;
    }

    // Get all users to notify
    if ($config['notify_all']) {
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email IS NOT NULL AND email != ''");
        $stmt->execute();
        $users = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND email IS NOT NULL AND email != ''");
        $stmt->execute([$task['user_id']]);
        $users = $stmt->fetchAll();
    }

    if (empty($users)) return false;

    $title = $task['title'];
    $desc = $task['description'] ? mb_substr($task['description'], 0, 100) : '';
    $priority = ucfirst($task['priority']);
    $statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'completed' => 'Selesai', 'archived' => 'Arsip'];
    $status = $statusLabels[$task['status']] ?? $task['status'];
    $category = $task['category_name'] ?? '-';
    $dueDate = $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : '-';
    $siteUrl = getSiteUrl();

    foreach ($users as $user) {
        $subject = "[Moonday] Tugas Baru: {$title}";
        
        $html = getEmailTemplate($title, $desc, $priority, $status, $category, $dueDate, $creatorName, $siteUrl);
        $text = "Tugas baru dibuat oleh {$creatorName}\n\nJudul: {$title}\nDeskripsi: {$desc}\nPrioritas: {$priority}\nStatus: {$status}\nKategori: {$category}\nDeadline: {$dueDate}\n\nLihat di: {$siteUrl}/tasks.php";

        simpleMail($config, $user['email'], $user['full_name'], $subject, $html, $text);
    }

    return true;
}

function simpleMail($config, $to, $toName, $subject, $htmlBody, $textBody) {
    try {
        $boundary = md5(uniqid(time()));
        $headers = "From: {$config['from_name']} <{$config['from_email']}>\r\n";
        $headers .= "Reply-To: {$config['from_email']}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: Moonday Task Manager\r\n";

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($textBody) . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$boundary}--";

        // Use PHPMailer if available, otherwise fallback to mail()
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['smtp_username'];
            $mail->Password   = $config['smtp_password'];
            $mail->SMTPSecure = $config['smtp_secure'];
            $mail->Port       = $config['smtp_port'];
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;
            $mail->send();
        } else {
            // Fallback to native mail() - requires SMTP configured in php.ini
            if (empty($config['smtp_username'])) {
                mail($to, $subject, $body, $headers);
            }
        }
    } catch (Exception $e) {
        error_log("Mail failed: " . $e->getMessage());
    }
}

function getEmailTemplate($title, $desc, $priority, $status, $category, $dueDate, $creator, $siteUrl) {
    $priorityColors = ['Low' => '#10b981', 'Medium' => '#3b82f6', 'High' => '#f59e0b', 'Urgent' => '#ef4444'];
    $pColor = $priorityColors[$priority] ?? '#6b7280';

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;">
<tr><td style="padding:40px 20px;">
    <div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:24px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:24px;">📋 Moonday</h1>
            <p style="color:rgba(255,255,255,0.8);margin:8px 0 0;">Tugas Baru Dibuat</p>
        </div>
        <div style="padding:24px;">
            <p style="color:#374151;font-size:16px;margin:0 0 16px;">Halo! Tugas baru telah dibuat oleh <strong>{$creator}</strong>.</p>
            
            <div style="background:#f8fafc;border-radius:8px;padding:20px;margin-bottom:20px;">
                <h2 style="color:#1e293b;margin:0 0 12px;font-size:20px;">{$title}</h2>
                {$desc ? '<p style="color:#64748b;margin:0 0 16px;font-size:14px;">' . htmlspecialchars($desc) . '</p>' : ''}
                
                <table cellpadding="4" cellspacing="0" style="font-size:14px;">
                    <tr><td style="color:#94a3b8;padding-right:12px;">Prioritas:</td><td style="color:{$pColor};font-weight:600;">{$priority}</td></tr>
                    <tr><td style="color:#94a3b8;padding-right:12px;">Status:</td><td>{$status}</td></tr>
                    <tr><td style="color:#94a3b8;padding-right:12px;">Kategori:</td><td>{$category}</td></tr>
                    <tr><td style="color:#94a3b8;padding-right:12px;">Deadline:</td><td>{$dueDate}</td></tr>
                </table>
            </div>
            
            <div style="text-align:center;">
                <a href="{$siteUrl}/tasks.php" style="display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:12px 32px;text-decoration:none;border-radius:8px;font-weight:600;">Lihat Tugas →</a>
            </div>
        </div>
        <div style="background:#f8fafc;padding:16px;text-align:center;color:#94a3b8;font-size:12px;">
            Moonday Task Manager &copy; 2026
        </div>
    </div>
</td></tr>
</table>
</body>
</html>
HTML;
}

function getSiteUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = dirname($_SERVER['PHP_SELF']);
    // Remove /api or similar subfolder
    $baseDir = str_replace('/api', '', $dir);
    return $protocol . '://' . $host . $baseDir;
}
