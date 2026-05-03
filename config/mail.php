<?php
/**
 * Mail Configuration
 * Email notification settings for task creation alerts
 */
return [
    // SMTP Settings
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_port'     => 587,
    'smtp_secure'   => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    
    // Email settings
    'from_name'     => 'Moonday Task Manager',
    'from_email'    => '',
    
    // Notification toggle
    'enabled'       => false,
    
    // Who gets notified
    'notify_all'    => true,        // If true, all users get notified. If false, only creator.
    'notify_owner'  => false,       // If notify_all is false, set true to also notify the task creator
];
