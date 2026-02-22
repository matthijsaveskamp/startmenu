<?php
ini_set('session.cookie_httponly', 1);
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;");

// Basisconfiguratie — pas aan naar wens
// Standaardwachtwoord: 'veranderdit' (verander dit zo snel mogelijk)
// Gebruik een hash voor de veiligheid in plaats van een plain-text wachtwoord.
$ADMIN_PASS_HASH = '$2y$10$ntTNGUUY06Hi1dmgIB9qlOrnXeXN5ehTgRD3naOx7nJi7O1xc4WGy';

$ALLOW_UPLOADS = true;
$UPLOAD_DIR = __DIR__ . '/assets/uploads/';

$DATA_DIR = __DIR__ . '/data/';
if (!is_dir($DATA_DIR)) {
    @mkdir($DATA_DIR, 0755, true);
    file_put_contents($DATA_DIR . '.htaccess', 'Deny from all');
}

// Migreer bestanden van root naar data map indien nodig
foreach (['links.json', 'settings.json', 'categories.json', 'trash.json'] as $f) {
    if (file_exists(__DIR__ . '/' . $f) && !file_exists($DATA_DIR . $f)) {
        @rename(__DIR__ . '/' . $f, $DATA_DIR . $f);
    }
}

$LINKS_FILE = $DATA_DIR . 'links.json';
$SETTINGS_FILE = $DATA_DIR . 'settings.json';
$CATEGORIES_FILE = $DATA_DIR . 'categories.json';
$TRASH_FILE = $DATA_DIR . 'trash.json';
$TRASH_RETENTION = 60 * 60; // seconden om verwijderde items te bewaren voordat ze permanent worden verwijderd (standaard 1 uur)
$MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 MB
// Standaard categorie id/titel
$DEFAULT_CATEGORY_ID = 'uncategorized';
$DEFAULT_CATEGORY_TITLE = 'Overig';
$ALLOWED_EXT = ['png','jpg','jpeg','gif','webp'];

// Zorg dat upload-map bestaat
if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0755, true);
}
?>
