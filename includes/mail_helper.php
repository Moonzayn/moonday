<?php
/**
 * Simple Email Helper - SMTP Support Included
 * Custom single-file SMTP client, no external library required.
 */

function sendTaskNotification($pdo, $task, $creatorName) {
    $config = include __DIR__ . '/../config/mail.php';
    
    if (!$config['enabled'] || empty($config['smtp_username'])) {
        return false;
    }

    // Get users to notify
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email IS NOT NULL AND email != ''");
    $stmt->execute();
    $users = $stmt->fetchAll();

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
        $text = "Tugas baru dibuat oleh {$creatorName}\n\nJudul: {$title}\nLihat di: {$siteUrl}/tasks.php";

        sendSmtpMail($config, $user['email'], $user['full_name'], $subject, $html, $text);
    }

    return true;
}

function sendSmtpMail($config, $to, $toName, $subject, $htmlBody, $textBody) {
    try {
        $host = $config['smtp_host'];
        $port = $config['smtp_port'];
        $user = $config['smtp_username'];
        $pass = $config['smtp_password'];
        $from = $config['from_email'] ?: $user;
        $fromName = $config['from_name'];
        
        // Connect
        $ssl = ($port == 465) ? 'ssl://' : '';
        $fp = fsockopen($ssl . $host, $port, $errno, $errstr, 30);
        if (!$fp) throw new Exception("Connection failed: $errstr");

        $send = function($cmd) use ($fp) {
            fwrite($fp, $cmd . "\r\n");
            $reply = '';
            while (substr($reply, 3, 1) != ' ') {
                $line = fgets($fp, 515);
                if($line === false) break;
                $reply .= $line;
            }
            return trim($reply);
        };

        $send(''); // Read greeting
        $send("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        
        // Start TLS if port is not 465
        if ($port != 465) {
            $send('STARTTLS');
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }

        $send('AUTH LOGIN');
        $send(base64_encode($user));
        $send(base64_encode($pass));

        $send("MAIL FROM:<$from>");
        $send("RCPT TO:<$to>");
        $send('DATA');

        $headers = "From: $fromName <$from>\r\n";
        $headers .= "Reply-To: $from\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $boundary = md5(uniqid(time()));
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

        $msg = "This is a multi-part message in MIME format.\r\n\r\n";
        $msg .= "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $textBody . "\r\n\r\n";
        $msg .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $htmlBody . "\r\n\r\n";
        $msg .= "--$boundary--\r\n.\r\n";

        fwrite($fp, $msg);
        $send('QUIT');
        fclose($fp);

    } catch (Exception $e) {
        error_log("Moonday Mail Error: " . $e->getMessage());
    }
}

function getEmailTemplate($title, $desc, $priority, $status, $category, $dueDate, $creator, $siteUrl) {
    $descHtml = $desc ? '<p style="color:#64748b;margin:0 0 16px;font-size:14px;">' . htmlspecialchars($desc) . '</p>' : '';
    return <<<HTML
<div style="font-family:sans-serif;background:#f4f4f4;padding:20px;border-radius:8px;">
    <h2 style="color:#333;">$title</h2>
    $descHtml
    <p>Status: <b>$status</b> | Priority: <b>$priority</b></p>
    <a href="$siteUrl/tasks.php" style="background:#007bff;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;">Lihat Tugas</a>
</div>
HTML;
}

function getSiteUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
}
