<?php
/**
 * 1-Click PHPMailer Installer
 * Upload this file to your server and open it in the browser.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$targetDir = __DIR__ . '/vendor/PHPMailer';

if (is_dir($targetDir)) {
    echo "✅ PHPMailer sudah ter-install di folder <code>vendor/PHPMailer</code>.<br>Silakan hapus file ini setelah selesai.";
    exit;
}

$zipUrl = 'https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip';
$zipFile = __DIR__ . '/phpmailer.zip';

echo "⬇️ Downloading PHPMailer...<br>";
file_put_contents($zipFile, file_get_contents($zipUrl));

echo "📦 Extracting...<br>";
$zip = new ZipArchive();
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo(__DIR__);
    $zip->close();
    rename(__DIR__ . '/PHPMailer-master', $targetDir);
    unlink($zipFile);
    
    echo "✅ <strong>Installasi Berhasil!</strong><br>";
    echo "Folder <code>vendor/PHPMailer</code> sudah siap.<br>";
    echo "Sekarang hapus file <code>install_mailer.php</code> ini.";
} else {
    echo "❌ Gagal ekstrak ZIP.";
}
