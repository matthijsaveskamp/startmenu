<?php
// Basisconfiguratie — pas aan naar wens
// Standaardwachtwoord: 'veranderdit' (verander dit zo snel mogelijk)
$ADMIN_PASS_PLAIN = 'veranderdit'; // eenvoudig voor lokaal gebruik
// Je kunt in plaats daarvan een vaste hash gebruiken en $ADMIN_PASS_PLAIN op null zetten
$ADMIN_PASS_HASH = password_hash($ADMIN_PASS_PLAIN, PASSWORD_DEFAULT);

$ALLOW_UPLOADS = true;
$UPLOAD_DIR = __DIR__ . '/assets/uploads/';
$LINKS_FILE = __DIR__ . '/links.json';
$SETTINGS_FILE = __DIR__ . '/settings.json';
$CATEGORIES_FILE = __DIR__ . '/categories.json';
$TRASH_FILE = __DIR__ . '/trash.json';
$TRASH_RETENTION = 60 * 60; // seconden om verwijderde items te bewaren voordat ze permanent worden verwijderd (standaard 1 uur)
$MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 MB
// Standaard categorie id/titel
$DEFAULT_CATEGORY_ID = 'uncategorized';
$DEFAULT_CATEGORY_TITLE = 'Overig';
$ALLOWED_EXT = ['png','jpg','jpeg','gif','webp','svg'];

// Zorg dat upload-map bestaat
if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0755, true);
}
?>
