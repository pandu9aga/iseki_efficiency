<?php
$files = [
    'c:/xampp/htdocs/iseki_efficiency/resources/views/admins/dashboard-fullscreen.blade.php',
    'c:/xampp/htdocs/iseki_efficiency/resources/views/leaders/dashboard-fullscreen.blade.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Normalize all newlines to LF first, then convert all LF to CRLF
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = str_replace("\n", "\r\n", $content);
        file_put_contents($file, $content);
        echo "Successfully converted $file to CRLF\n";
    } else {
        echo "File not found: $file\n";
    }
}
