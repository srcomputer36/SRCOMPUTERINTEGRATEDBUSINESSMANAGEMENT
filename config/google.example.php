<?php
// Google integration config template
// Copy to google.php if you use Drive API. Never commit credentials.
// Place service-account.json at storage/credentials/service-account.json (outside public)

return [
    'drive_folder_id' => 'YOUR_DRIVE_FOLDER_ID_HERE',
    'sheets_id' => 'YOUR_SPREADSHEET_ID_HERE',
    'credentials_path' => dirname(__DIR__) . '/storage/credentials/service-account.json',
    // Scopes: https://www.googleapis.com/auth/drive.file (least privilege)
    'scopes' => ['https://www.googleapis.com/auth/drive.file'],
    'mode' => 'local', // local | drive
];
