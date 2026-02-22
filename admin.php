<?php
require 'config.php';
session_start();

/** Helpers: links storage */
function load_links()
{
    global $LINKS_FILE;

    if (!file_exists($LINKS_FILE)) {
        return [];
    }

    return json_decode(file_get_contents($LINKS_FILE), true) ?: [];
}

function save_links($arr)
{
    global $LINKS_FILE;

    file_put_contents(
        $LINKS_FILE,
        json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

/** Settings */
function load_settings()
{
    global $SETTINGS_FILE;

    $def = ['admin_avatar' => ''];

    if (!file_exists($SETTINGS_FILE)) {
        file_put_contents($SETTINGS_FILE, json_encode($def, JSON_PRETTY_PRINT));
        return $def;
    }

    $s = json_decode(file_get_contents($SETTINGS_FILE), true);
    return $s ?: $def;
}

function save_settings($arr)
{
    global $SETTINGS_FILE;

    file_put_contents(
        $SETTINGS_FILE,
        json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

/** Versioning helpers */
function get_version()
{
    $s = load_settings();
    return isset($s['version']) && is_string($s['version']) ? $s['version'] : '1.00.00';
}

function format_version($major, $minor, $patch)
{
    return sprintf('%d.%02d.%02d', (int)$major, (int)$minor, (int)$patch);
}

function bump_version($level = 'patch')
{
    $s = load_settings();
    $v = isset($s['version']) ? $s['version'] : '1.00.00';

    $parts = explode('.', $v);
    $major = isset($parts[0]) ? intval($parts[0]) : 1;
    $minor = isset($parts[1]) ? intval($parts[1]) : 0;
    $patch = isset($parts[2]) ? intval($parts[2]) : 0;

    switch ($level) {
        case 'major':
            $major++;
            $minor = 0;
            $patch = 0;
            break;
        case 'minor':
            $minor++;
            $patch = 0;
            break;
        case 'patch':
        default:
            $patch++;
            break;
    }

    $nv = format_version($major, $minor, $patch);
    $s['version'] = $nv;
    save_settings($s);

    return $nv;
}

/** Categories helpers */
function load_categories()
{
    global $CATEGORIES_FILE, $DEFAULT_CATEGORY_ID, $DEFAULT_CATEGORY_TITLE;

    $def = [[
        'id'    => $DEFAULT_CATEGORY_ID,
        'title' => $DEFAULT_CATEGORY_TITLE,
        'order' => 0,
    ]];

    if (!file_exists($CATEGORIES_FILE)) {
        file_put_contents($CATEGORIES_FILE, json_encode($def, JSON_PRETTY_PRINT));
        return $def;
    }

    $s = json_decode(file_get_contents($CATEGORIES_FILE), true);
    return $s ?: $def;
}

function save_categories($arr)
{
    global $CATEGORIES_FILE;
    file_put_contents(
        $CATEGORIES_FILE,
        json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

/** Migration: ensure links have category_id, order, id and created */
function migrate_links_to_categories()
{
    global $LINKS_FILE, $DEFAULT_CATEGORY_ID;

    $links   = load_links();
    $changed = false;

    foreach ($links as $i => $l) {
        if (!isset($l['category_id'])) {
            $links[$i]['category_id'] = $DEFAULT_CATEGORY_ID;
            $changed = true;
        }

        if (!isset($l['order'])) {
            $links[$i]['order'] = $i;
            $changed = true;
        }

        if (!isset($l['id'])) {
            $links[$i]['id'] = uniqid('l_');
            $changed = true;
        }

        if (!isset($l['created'])) {
            $links[$i]['created'] = date('d-m-Y');
            $changed = true;
        }
    }

    if ($changed) {
        save_links($links);
    }
}

/* execute migration on load */
migrate_links_to_categories();

/** Trash helpers */
function load_trash()
{
    global $TRASH_FILE;
    return file_exists($TRASH_FILE) ? json_decode(file_get_contents($TRASH_FILE), true) : [];
}

function save_trash($arr)
{
    global $TRASH_FILE;
    file_put_contents(
        $TRASH_FILE,
        json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function purge_trash($ttl)
{
    global $TRASH_FILE, $TRASH_RETENTION;

    $trash = load_trash();
    $now   = time();
    $keep  = [];

    foreach ($trash as $t) {
        $expired = ($now - ($t['deleted_at'] ?? 0)) > ($ttl ?? $TRASH_RETENTION);

        if ($expired) {
            // permanent remove moved asset if present
            if (!empty($t['moved_asset']) && strpos($t['moved_asset'], 'assets/') === 0) {
                @unlink(__DIR__ . '/' . $t['moved_asset']);
                $dir = dirname(__DIR__ . '/' . $t['moved_asset']);
                @rmdir($dir);
            }

            // also try to remove original icon path if it still exists
            if (!empty($t['item']['icon']) && strpos($t['item']['icon'], 'assets/') === 0) {
                @unlink(__DIR__ . '/' . $t['item']['icon']);
            }
        } else {
            $keep[] = $t;
        }
    }

    save_trash($keep);
}

// Eenvoudige escape-helper (wordt ook in index.php gebruikt)
function e($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
} 

// Datum-hulpfunctie
function validate_ddmmyyyy($s){
    if (!preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) return false;
    $d = intval($m[1]); $mo = intval($m[2]); $y = intval($m[3]);
    return checkdate($mo, $d, $y);
} 

function sanitize_date($s)
{
    return trim($s);
}

function is_safe_url($url)
{
    if (empty($url)) return true;
    $allowed_protocols = ['http', 'https', 'mailto', 'tel'];
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === false || $scheme === null) return true;
    return in_array(strtolower($scheme), $allowed_protocols);
}

// Upload-hulpfuncties
function upload_error_message($code){
    $map = [
        UPLOAD_ERR_OK => 'OK',
        UPLOAD_ERR_INI_SIZE => 'Bestand groter dan upload_max_filesize (php.ini)',
        UPLOAD_ERR_FORM_SIZE => 'Bestand groter dan formulier limiet',
        UPLOAD_ERR_PARTIAL => 'Bestand slechts gedeeltelijk geüpload',
        UPLOAD_ERR_NO_FILE => 'Geen bestand geüpload',
        UPLOAD_ERR_NO_TMP_DIR => 'Geen tijdelijke map op server',
        UPLOAD_ERR_CANT_WRITE => 'Schrijven naar schijf mislukt',
        UPLOAD_ERR_EXTENSION => 'Upload gestopt door extensie',
    ];
    return $map[$code] ?? 'Onbekende uploadfout (' . intval($code) . ')';
} 

function is_valid_image_file($tmpPath){
    if (!is_uploaded_file($tmpPath)) return [false, 'Bestand is geen upload: mogelijke beveiligingsrisico'];
    $info = @getimagesize($tmpPath);
    if ($info === false) return [false, 'Geen geldige afbeelding'];
    // Eenvoudige mime-whitelist controle
    $mime = $info['mime'] ?? '';
    $allowedMimes = ['image/png','image/jpeg','image/gif','image/webp','image/svg+xml'];
    if (!in_array($mime, $allowedMimes)) return [false, 'Ongewenst mime-type: ' . $mime];
    // Gebruik finfo als extra controle
    if (function_exists('finfo_open')){
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $real = finfo_file($f, $tmpPath);
        finfo_close($f);
        if (!in_array($real, $allowedMimes)) return [false, 'Mime-detectie mismatch: ' . $real];
    }
    return [true, $mime];
}

function log_upload_error($msg){
    global $DATA_DIR;
    $logFile = rtrim($DATA_DIR, '/') . '/upload-debug.log';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $line = date('Y-m-d H:i:s') . " | $ip | $msg\n";
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// CSRF token
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$err = '';
$msg = '';
$settings = load_settings();

// show bump notice when bumped via ?bumped=VERSION
if (!empty($_GET['bumped'])) {
    $msg = 'Versie bijgewerkt naar ' . htmlspecialchars($_GET['bumped']);
}

// Inloggen
if (isset($_POST['login'])) {
    $pw = $_POST['pass'] ?? '';
    if ((!empty($GLOBALS['ADMIN_PASS_PLAIN']) && $pw === $GLOBALS['ADMIN_PASS_PLAIN']) || password_verify($pw, $GLOBALS['ADMIN_PASS_HASH'])) {
        $_SESSION['logged'] = true; 
    } else {
        $err = 'Verkeerd wachtwoord';
    }
} 

// Uitloggen
if (isset($_GET['logout'])) {
    session_destroy(); header('Location: admin.php'); exit;
} 

// Link toevoegen
if (isset($_POST['add']) && !empty($_SESSION['logged'])) {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; }
    else {
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $desc = trim($_POST['desc'] ?? '');
        $icon = trim($_POST['icon_url'] ?? '');

        if (!is_safe_url($url) || !is_safe_url($icon)) {
            $err = 'Ongeldige URL gedetecteerd (onveilig protocol)';
        }

        // Verwerk upload met server-side afbeeldingscontroles
        if ($GLOBALS['ALLOW_UPLOADS'] && isset($_FILES['icon_file'])) {
            $file = $_FILES['icon_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $err = upload_error_message($file['error']);
                log_upload_error('Icon upload failed (pre-check): ' . $err . ' / name:' . ($file['name'] ?? '')); 
            } else if ($file['size'] > $GLOBALS['MAX_UPLOAD_BYTES']) {
                $err = 'Bestand is te groot';
                log_upload_error('Icon upload too large: ' . $file['size']);
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $GLOBALS['ALLOWED_EXT'])) { $err = 'Niet toegestaan bestandstype'; log_upload_error('Icon extension not allowed: ' . $ext); }
                else {
                    list($ok, $info) = is_valid_image_file($file['tmp_name']);
                    if (!$ok) { $err = 'Afbeelding validatie mislukt: ' . $info; log_upload_error('Icon image validation failed: ' . $info); }
                    else {
                        $name = uniqid('i_') . '.' . $ext;
                        $dest = $GLOBALS['UPLOAD_DIR'] . $name;
                        if (!is_dir($GLOBALS['UPLOAD_DIR']) || !is_writable($GLOBALS['UPLOAD_DIR'])) { $err = 'Upload map niet schrijfbaar op server'; log_upload_error('Upload dir not writable: ' . $GLOBALS['UPLOAD_DIR']); }
                        else if (move_uploaded_file($file['tmp_name'], $dest)) {
                            $icon = 'assets/uploads/' . $name;
                        } else { $err = 'Upload mislukt bij verplaatsen'; log_upload_error('move_uploaded_file failed for ' . ($file['name'] ?? 'unknown')); }
                    }
                }
            }
        }

        if (empty($err)) {
            $links = load_links();
            $category_id = trim($_POST['category_id'] ?? $GLOBALS['DEFAULT_CATEGORY_ID']);
            // bereken volgorde als huidig aantal in die categorie
            $order = 0; foreach($links as $ln){ if (($ln['category_id'] ?? $GLOBALS['DEFAULT_CATEGORY_ID']) === $category_id) $order++; }
            $id = uniqid('l_');
            $created = sanitize_date($_POST['created'] ?? '');
            if ($created === '' || !validate_ddmmyyyy($created)) { $created = date('d-m-Y'); }
            $newLink = ['id'=>$id,'title'=>$title, 'url'=>$url, 'icon'=>$icon, 'desc'=>$desc, 'category_id'=>$category_id, 'order'=>$order, 'created'=>$created];
            $links[] = $newLink;
            save_links($links);

            // bump patch version (link toegevoegd)
            $newVer = bump_version('patch');

            // Als AJAX (fetch), retourneer JSON voor client-side invoeging
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'link' => $newLink, 'version' => $newVer]);
                exit;
            }

            header('Location: admin.php');
            exit;
        }
    }
}

// Verwijder link (POST) - soft delete naar prullenbak (accepteert link id of numerieke index)
if (isset($_POST['del']) && !empty($_SESSION['logged'])) {
    $del = $_POST['del'];
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; }
    else {
        $links = load_links();
        $index = null;
        if (is_numeric($del) && isset($links[(int)$del])) { $index = (int)$del; }
        else { foreach($links as $k=>$v){ if (isset($v['id']) && $v['id']===$del){ $index = $k; break; } } }
        if ($index !== null) {
            $item = $links[$index];
            // maak prullenbak-item aan
            $trash = load_trash();
            $token = bin2hex(random_bytes(8));
            // als het icoon een lokale asset is, verplaats het naar een veilige prullenbak-map zodat ongedaan maken het kan herstellen
            $moved_asset = '';
            if (!empty($item['icon']) && strpos($item['icon'], 'assets/') === 0) {
                $origPath = __DIR__ . '/' . $item['icon'];
                if (is_file($origPath)) {
                    $trashDir = __DIR__ . '/assets/trash/' . $token;
                    if (!is_dir($trashDir)) @mkdir($trashDir, 0755, true);
                    $base = basename($origPath);
                    $newRel = 'assets/trash/' . $token . '/' . $base;
                    if (@rename($origPath, __DIR__ . '/' . $newRel)) {
                        $moved_asset = $newRel;
                    } else {
                        // Als verplaatsen faalt, probeer te verwijderen om dangling assets te voorkomen
                        @unlink($origPath);
                    }
                }
            }
            $entry = ['token'=>$token,'deleted_at'=>time(),'index'=>$index,'item'=>$item];
            if ($moved_asset) { $entry['moved_asset'] = $moved_asset; }
            $trash[] = $entry;
            save_trash($trash);
            // verwijder uit links (verwijder nog geen bestanden — laat ongedaan maken toe)
            array_splice($links, $index, 1);
            save_links($links);

            // bump patch version for deletion
            bump_version('patch');

            // redirect terug met token en titel om de undo-snackbar te tonen
            $titleParam = urlencode($item['title'] ?? '');
            header('Location: admin.php?deleted=' . $token . '&title=' . $titleParam);
            exit;
        }
    }
}

// Verwijder link (GET) - fallback voor oudere clients (accepteert id of index)
if (isset($_GET['del']) && !empty($_SESSION['logged'])) {
    $del = $_GET['del'];
    if (empty($_GET['csrf']) || $_GET['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; }
    else {
        $links = load_links();
        $index = null;
        if (is_numeric($del) && isset($links[(int)$del])) { $index = (int)$del; }
        else { foreach($links as $k=>$v){ if (isset($v['id']) && $v['id']===$del){ $index = $k; break; } } }
        if ($index !== null) {
            if (!empty($links[$index]['icon']) && strpos($links[$index]['icon'], 'assets/uploads/') === 0) {
                @unlink(__DIR__ . '/' . $links[$index]['icon']);
            }
            array_splice($links, $index, 1);
            save_links($links);

            // bump patch version for deletion
            bump_version('patch');

            header('Location: admin.php');
            exit;
        }
    }
}

// verwijder oude prullenbak-items (opschonen)
purge_trash(null);

// Bewerkmodus - toon bewerkingsformulier
$editing = null;
$edit_item = null;
if (isset($_GET['edit']) && !empty($_SESSION['logged'])) {
    $editParam = $_GET['edit'];
    $links = load_links();
    $foundIndex = null;
    if (is_numeric($editParam) && isset($links[(int)$editParam])) { $foundIndex = (int)$editParam; }
    else { foreach($links as $k=>$v){ if (isset($v['id']) && $v['id'] === $editParam){ $foundIndex = $k; break; } } }
    if ($foundIndex !== null) { $editing = $foundIndex; $edit_item = $links[$foundIndex]; }
}

// Wijzigingen opslaan
if (isset($_POST['save']) && !empty($_SESSION['logged'])) {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; }
    else {
        $i = (int)($_POST['index'] ?? -1);
        $links = load_links();
        if (!isset($links[$i])) { $err = 'Ongeldige link'; }
        else {
            $title = trim($_POST['title'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $desc = trim($_POST['desc'] ?? '');
            $icon = trim($_POST['icon_url'] ?? '');
            $oldIcon = $links[$i]['icon'] ?? '';

            if (!is_safe_url($url) || !is_safe_url($icon)) {
                $err = 'Ongeldige URL gedetecteerd (onveilig protocol)';
            }

            // Optie om icoon te verwijderen
            if (isset($_POST['remove_icon']) && $_POST['remove_icon'] == '1') {
                if (!empty($oldIcon) && strpos($oldIcon, 'assets/uploads/') === 0) { @unlink(__DIR__ . '/' . $oldIcon); }
                $oldIcon = '';
                $icon = '';
            }

            // Verwerk upload met server-side afbeeldingscontroles
            if ($GLOBALS['ALLOW_UPLOADS'] && isset($_FILES['icon_file'])) {
                $file = $_FILES['icon_file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    // als geen bestand opgegeven, behoud het oude icoon
                    if ($file['error'] === UPLOAD_ERR_NO_FILE) { /* geen bestand opgegeven, behoud oud icoon */ }
                    else { $err = upload_error_message($file['error']); log_upload_error('Edit icon upload pre-check: ' . $err . ' / name:' . ($file['name'] ?? '')); }
                } else if ($file['size'] > $GLOBALS['MAX_UPLOAD_BYTES']) {
                    $err = 'Bestand is te groot'; log_upload_error('Edit icon too large: ' . $file['size']);
                } else {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $GLOBALS['ALLOWED_EXT'])) { $err = 'Niet toegestaan bestandstype'; log_upload_error('Edit icon extension not allowed: ' . $ext); }
                    else {
                        list($ok, $info) = is_valid_image_file($file['tmp_name']);
                        if (!$ok) { $err = 'Afbeelding validatie mislukt: ' . $info; log_upload_error('Edit icon image validation failed: ' . $info); }
                        else {
                            $name = uniqid('i_') . '.' . $ext;
                            $dest = $GLOBALS['UPLOAD_DIR'] . $name;
                            if (!is_dir($GLOBALS['UPLOAD_DIR']) || !is_writable($GLOBALS['UPLOAD_DIR'])) { $err = 'Upload map niet schrijfbaar op server'; log_upload_error('Edit upload dir not writable: ' . $GLOBALS['UPLOAD_DIR']); }
                            else if (move_uploaded_file($file['tmp_name'], $dest)) {
                                // verwijder oud lokaal icoon
                                if (!empty($oldIcon) && strpos($oldIcon, 'assets/uploads/') === 0) { @unlink(__DIR__ . '/' . $oldIcon); }
                                $icon = 'assets/uploads/' . $name;
                            } else { $err = 'Upload mislukt bij verplaatsen'; log_upload_error('Edit move_uploaded_file failed for ' . ($file['name'] ?? 'unknown')); }
                        }
                    }
                }
            } else {
                if ($icon === '') { $icon = $oldIcon; }
            }

            if (empty($err)) {
                // handel categorie-wijziging: als categorie verandert, zet volgorde op het einde van de nieuwe categorie
                $new_cat = trim($_POST['category_id'] ?? ($links[$i]['category_id'] ?? $GLOBALS['DEFAULT_CATEGORY_ID']));
                $old_cat = $links[$i]['category_id'] ?? $GLOBALS['DEFAULT_CATEGORY_ID'];
                $new_order = $links[$i]['order'] ?? 0;
                if ($new_cat !== $old_cat) {
                    $count = 0; foreach($links as $l){ if (($l['category_id'] ?? $GLOBALS['DEFAULT_CATEGORY_ID']) === $new_cat) $count++; }
                    $new_order = $count;
                }
                $created = sanitize_date($_POST['created'] ?? ($links[$i]['created'] ?? date('d-m-Y')));
                if ($created === '' || !validate_ddmmyyyy($created)) { $created = $links[$i]['created'] ?? date('d-m-Y'); }
                $links[$i] = [
                    'id' => $links[$i]['id'] ?? uniqid('l_'),
                    'title' => $title,
                    'url' => $url,
                    'icon' => $icon,
                    'desc' => $desc,
                    'category_id' => $new_cat,
                    'order' => $new_order,
                    'created' => $created,
                ];

                save_links($links);

                // bump patch version for edit
                bump_version('patch');

                header('Location: admin.php');
                exit;
            }
        }
    }
}

// Avatar opslaan / bijwerken
if (isset($_POST['avatar_save']) && !empty($_SESSION['logged'])) {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; }
    else {
        $settings = load_settings();
        $old = $settings['admin_avatar'] ?? '';
        $avatar = trim($_POST['avatar_url'] ?? '');

        if (!is_safe_url($avatar)) {
            $err = 'Ongeldige Avatar URL gedetecteerd (onveilig protocol)';
        }

        // Verwijder huidige avatar
        if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] == '1') {
            if (!empty($old) && strpos($old, 'assets/uploads/') === 0) { @unlink(__DIR__ . '/' . $old); }
            $old = '';
            $avatar = '';
        }

        // Handle avatar upload with server-side image checks
        if ($GLOBALS['ALLOW_UPLOADS'] && isset($_FILES['avatar_file'])) {
            $file = $_FILES['avatar_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                if ($file['error'] === UPLOAD_ERR_NO_FILE) { /* geen avatar opgegeven */ }
                else { $err = upload_error_message($file['error']); log_upload_error('Avatar upload failed (pre-check): ' . $err . ' / name:' . ($file['name'] ?? '')); }
            } else if ($file['size'] > $GLOBALS['MAX_UPLOAD_BYTES']) {
                $err = 'Bestand is te groot'; log_upload_error('Avatar upload too large: ' . $file['size']);
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $GLOBALS['ALLOWED_EXT'])) { $err = 'Niet toegestaan bestandstype'; log_upload_error('Avatar extension not allowed: ' . $ext); }
                else {
                    list($ok, $info) = is_valid_image_file($file['tmp_name']);
                    if (!$ok) { $err = 'Afbeelding validatie mislukt: ' . $info; log_upload_error('Avatar image validation failed: ' . $info); }
                    else {
                        $name = uniqid('a_') . '.' . $ext;
                        $dest = $GLOBALS['UPLOAD_DIR'] . $name;
                        if (!is_dir($GLOBALS['UPLOAD_DIR']) || !is_writable($GLOBALS['UPLOAD_DIR'])) { $err = 'Upload map niet schrijfbaar op server'; log_upload_error('Avatar upload dir not writable: ' . $GLOBALS['UPLOAD_DIR']); }
                        else if (move_uploaded_file($file['tmp_name'], $dest)) {
                            if (!empty($old) && strpos($old, 'assets/uploads/') === 0) { @unlink(__DIR__ . '/' . $old); }
                            $avatar = 'assets/uploads/' . $name;
                        } else { $err = 'Upload mislukt bij verplaatsen'; log_upload_error('Avatar move_uploaded_file failed for ' . ($file['name'] ?? 'unknown')); }
                    }
                }
            }
        }

        if (empty($err)) {
            $settings['admin_avatar'] = $avatar;
            save_settings($settings);

            // bump version (avatar updated)
            bump_version('patch');

            header('Location: admin.php');
            exit;
        }
    }
}

// Manueel versie bijwerken (knop)
if (isset($_POST['bump_version']) && !empty($_SESSION['logged'])) {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $err = 'Ongeldige sessie (CSRF)';
    } else {
        $new = bump_version('patch');
        header('Location: admin.php?bumped=' . urlencode($new));
        exit;
    }
}

// Handlers voor import / export
$msg = ''; 
if (!empty($_SESSION['logged']) && isset($_GET['export'])) {
    if (empty($_GET['csrf']) || $_GET['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; }
    else {
        $exportData = ['links'=>load_links(),'categories'=>load_categories(),'settings'=>load_settings()];
        // Gebruik ZIP (JSON + assets) wanneer ZipArchive beschikbaar is
        if (class_exists('ZipArchive')) {
            $tmp = tempnam(sys_get_temp_dir(), 'start_export_');
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::OVERWRITE) === true) {
                $zip->addFromString('export.json', json_encode($exportData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
                // voeg assets/ map toe als die aanwezig is
                $assetsDir = __DIR__ . '/assets';
                if (is_dir($assetsDir)) {
                    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assetsDir, RecursiveDirectoryIterator::SKIP_DOTS));
                    foreach ($rii as $file) {
                        $filePath = $file->getRealPath();
                        if (!is_file($filePath)) continue;
                        $localPath = 'assets' . substr($filePath, strlen($assetsDir));
                        $zip->addFile($filePath, $localPath);
                    }
                }
                $zip->close();
                // stream als zip
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="startpage-export-' . date('Y-m-d') . '.zip"');
                header('Content-Length: ' . filesize($tmp));
                // flush en lees
                if (ob_get_level()) { ob_end_clean(); }
                readfile($tmp);
                @unlink($tmp);
                exit;
            }
            // als zip mislukt, val terug op JSON
        }
        // terugvallen op JSON export
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="startpage-export-' . date('Y-m-d') . '.json"');
        echo json_encode($exportData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// helper: recursief verwijderen van map
function rrmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getRealPath());
        } else {
            @unlink($item->getRealPath());
        }
    }

    @rmdir($dir);
}

if (!empty($_SESSION['logged']) && isset($_POST['import'])) {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; }
    else {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) { $err = 'Geen bestand geselecteerd of upload mislukt'; }
        else if ($_FILES['import_file']['size'] > ($GLOBALS['MAX_UPLOAD_BYTES'] * 30)) { $err = 'Importbestand is te groot'; }
        else {
            $fileTmp = $_FILES['import_file']['tmp_name'];
            $isZip = false; $json = null; $assetsRestored = false;
            // detecteer zip op basis van inhoud of extensie
            $finfo = null; if (function_exists('finfo_open')){ $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $fileTmp); finfo_close($finfo); }
            $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
            if ($ext === 'zip' || stripos($mime ?? '', 'zip') !== false) { $isZip = true; }

            if ($isZip && class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($fileTmp) !== true) { $err = 'Kon ZIP niet openen'; }
                else {
                    // vind export.json in de ZIP
                    $exportName = null;
                    for ($i=0;$i<$zip->numFiles;$i++){ $name = $zip->getNameIndex($i); if (basename($name) === 'export.json'){ $exportName = $name; break; } }
                    if ($exportName !== null) {
                        $json = $zip->getFromName($exportName);
                        if ($json === false) { $err = 'Kon export.json niet lezen uit ZIP'; }
                    } else {
                        $err = 'ZIP bevat geen export.json';
                    }
                    // verwerk assets-extractie indien aangevraagd (checkbox 'replace' bepaalt hoe we bestaande bestanden behandelen)
                    if (empty($err)) {
                        $replaceAssets = !empty($_POST['replace']);
                        $assetsDir = __DIR__ . '/assets';
                        if ($replaceAssets && is_dir($assetsDir)) { rrmdir($assetsDir); }
                        // extraheer entries die onder assets/ vallen
                        for ($i=0;$i<$zip->numFiles;$i++){
                            $name = $zip->getNameIndex($i);
                            if (!preg_match('#^assets[\\/].+#i', $name)) continue;
                            // voorkom path traversal
                            $rel = preg_replace('#^assets[\\/]#i','',$name);
                            if (strpos($rel, '..') !== false) continue;
                            $outPath = $assetsDir . '/' . $rel;
                            if (substr($name,-1) === '/' ) { @mkdir($outPath, 0755, true); continue; }
                            $content = $zip->getFromIndex($i);
                            if ($content === false) continue;
                            @mkdir(dirname($outPath), 0755, true);
                            file_put_contents($outPath, $content);
                            $assetsRestored = true;
                        }
                    }
                    $zip->close();
                }
            } else {
                // geen zip — neem aan JSON
                $json = @file_get_contents($fileTmp);
            }

            if (empty($err)) {
                $data = @json_decode($json, true);
                if (!is_array($data)) { $err = 'Ongeldig JSON bestand'; }
                else {
                    $linksImport = $data['links'] ?? null;
                    $catsImport = $data['categories'] ?? null;
                    $settingsImport = $data['settings'] ?? null;
                    if (!is_array($linksImport) || !is_array($catsImport)) { $err = 'JSON mist links of categories'; }
                    else {
                        if (!empty($_POST['replace'])) {
                            save_links($linksImport);
                            save_categories($catsImport);
                            if (is_array($settingsImport)) save_settings($settingsImport);
                        } else {
                            // voeg categorieën samen op basis van id
                            $existingCats = load_categories(); $catMap = [];
                            foreach($existingCats as $c) { $catMap[$c['id']] = $c; }
                            foreach($catsImport as $c) { if (isset($c['id'])) $catMap[$c['id']] = $c; }
                            save_categories(array_values($catMap));
                            // voeg links samen op basis van id
                            $existingLinks = load_links(); $linkMap = [];
                            foreach($existingLinks as $l){ if (isset($l['id'])) $linkMap[$l['id']] = $l; }
                            foreach($linksImport as $l){ if (!isset($l['id'])) $l['id'] = uniqid('l_'); $linkMap[$l['id']] = $l; }
                            save_links(array_values($linkMap));
                            if (is_array($settingsImport)) { $settings = load_settings(); $settings = array_merge($settings, $settingsImport); save_settings($settings); }
                        }
                        $msg = 'Import succesvol' . ($assetsRestored ? ' — assets hersteld' : '');

                        // bump minor version for import
                        $newVer = bump_version('minor');
                        $msg .= ' — versie ' . $newVer;
                    }
                }
            }
        }
    }
}

// Herstel verwijderde item (ongedaan maken)
if (isset($_POST['restore']) && !empty($_SESSION['logged'])) {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; }
    else {
        $token = trim($_POST['token'] ?? '');
        $trash = load_trash();
        $found = null; $keeper = [];
        foreach($trash as $t){ if ($t['token'] === $token) { $found = $t; } else { $keeper[] = $t; } }
        if ($found === null) { $err = 'Ongedaan maken niet mogelijk (te laat of ongeldig token)'; }
        else {
            // herstel naar oorspronkelijke index in links indien mogelijk
            // als we een asset naar de prullenbak verplaatsten, zet deze terug
            $item = $found['item'];
            if (!empty($found['moved_asset']) && is_file(__DIR__ . '/' . $found['moved_asset'])) {
                $target = __DIR__ . '/' . ($item['icon'] ?? '');
                if ($target) {
                    @mkdir(dirname($target), 0755, true);
                    @rename(__DIR__ . '/' . $found['moved_asset'], $target);
                    // probeer de nu-lege prullenbakmap te verwijderen
                    @rmdir(dirname(__DIR__ . '/' . $found['moved_asset']));
                }
            }
            $links = load_links();
            $idx = max(0, min(count($links), (int)$found['index']));
            array_splice($links, $idx, 0, [$item]);
            save_links($links);
            // verwijder uit prullenbak
            save_trash($keeper);
            header('Location: admin.php'); exit;
        }
    }
}

// Permanent verwijderen van een prullenbak-item
if (isset($_POST['perma_delete']) && !empty($_SESSION['logged'])) {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; header('Location: admin.php'); exit; }
    $token = trim($_POST['token'] ?? '');
    $trash = load_trash(); $keeper = [];
    foreach($trash as $t){
        if ($t['token'] === $token) {
            if (!empty($t['moved_asset']) && strpos($t['moved_asset'],'assets/')===0){ @unlink(__DIR__ . '/' . $t['moved_asset']); @rmdir(dirname(__DIR__ . '/' . $t['moved_asset'])); }
            if (!empty($t['item']['icon']) && strpos($t['item']['icon'],'assets/')===0){ @unlink(__DIR__ . '/' . $t['item']['icon']); }
            // sla niet op in keeper (effectief verwijderen)
        } else { $keeper[] = $t; }
    }
    save_trash($keeper);
    header('Location: admin.php'); exit;
}

// Prullenbak volledig legen (permanent)
if (isset($_POST['empty_trash']) && !empty($_SESSION['logged'])) {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $err = 'Ongeldige sessie (CSRF)'; header('Location: admin.php'); exit; }
    $trash = load_trash();
    foreach($trash as $t){ if (!empty($t['moved_asset']) && strpos($t['moved_asset'],'assets/')===0){ @unlink(__DIR__ . '/' . $t['moved_asset']); @rmdir(dirname(__DIR__ . '/' . $t['moved_asset'])); } if (!empty($t['item']['icon']) && strpos($t['item']['icon'],'assets/')===0){ @unlink(__DIR__ . '/' . $t['item']['icon']); } }
    save_trash([]);
    header('Location: admin.php'); exit;
}

// Categories / ordering API endpoints
if (!empty($_SESSION['logged']) && isset($_POST['action'])) {
    $action = $_POST['action'];
    // CSRF-controle voor muterende acties
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { echo json_encode(['ok'=>false,'error'=>'Ongeldige sessie (CSRF)']); exit; }
    // Categorie aanmaken
    if ($action === 'create_category') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') { echo json_encode(['ok'=>false,'error'=>'Lege titel']); exit; }
        $cats = load_categories();
        $id = uniqid('c_');
        $cats[] = ['id' => $id, 'title' => $title, 'order' => count($cats)];
        save_categories($cats);

        // bump version (category created)
        bump_version('patch');

        echo json_encode(['ok' => true, 'id' => $id, 'title' => $title]);
        exit;
    }
    // Hernoem categorie
    if ($action === 'rename_category') {
        $id = trim($_POST['id'] ?? ''); $title = trim($_POST['title'] ?? '');
        if ($id === '' || $title === '') { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }
        $cats = load_categories();
        foreach ($cats as &$c) {
            if ($c['id'] === $id) {
                $c['title'] = $title;
            }
        }
        save_categories($cats);

        // bump version (category renamed)
        bump_version('patch');

        echo json_encode(['ok' => true]);
        exit;
    }
    // Verwijder categorie
    if ($action === 'delete_category') {
        $id = trim($_POST['id'] ?? ''); if ($id === '') { echo json_encode(['ok'=>false]); exit; }
        $cats = load_categories();
        $newcats = [];
        foreach ($cats as $c) {
            if ($c['id'] !== $id) {
                $newcats[] = $c;
            }
        }
        save_categories($newcats);

        // move links to default category
        $links = load_links();
        foreach ($links as &$l) {
            if ($l['category_id'] === $id) {
                $l['category_id'] = $GLOBALS['DEFAULT_CATEGORY_ID'];
            }
        }
        save_links($links);

        // bump version (category deleted)
        bump_version('patch');

        echo json_encode(['ok' => true]);
        exit;
    }
    // Save order (categories + links ordering)
    if ($action === 'save_order') {
        $data = json_decode($_POST['order'] ?? '[]', true);
        if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Invalid data']); exit; }
        // update categories
        $cats = [];
        foreach($data as $ci=>$cat){ $cats[]=['id'=>$cat['id'],'title'=>$cat['title'],'order'=>intval($ci)]; }
        save_categories($cats);
        // update links order and category
        $links = load_links();
        // build map by id
        $map = [];
        foreach ($links as $link) {
            if (isset($link['id'])) {
                $map[$link['id']] = $link;
            }
        }
        $updated = [];
        foreach($data as $catIndex=>$cat){ if (!isset($cat['links']) || !is_array($cat['links'])) continue; foreach($cat['links'] as $li=>$linkId){ if (isset($map[$linkId])){ $l = $map[$linkId]; $l['category_id'] = $cat['id']; $l['order'] = intval($li); $updated[] = $l; } } }
        // append any remaining links not included
        foreach ($links as $existing) {
            if (!isset($existing['id'])) {
                continue;
            }
            $found = false;
            foreach ($updated as $u) {
                if ($u['id'] === $existing['id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $existing['category_id'] = $GLOBALS['DEFAULT_CATEGORY_ID'];
                $existing['order'] = count($updated);
                $updated[] = $existing;
            }
        }

        save_links($updated);

        // bump patch version (order saved)
        bump_version('patch');

        echo json_encode(['ok' => true]);
        exit;
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin</title>
<link rel="icon" href="assets/start.ico" type="image/x-icon">
<link rel="shortcut icon" href="assets/start.ico" type="image/x-icon">
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <h1>Matthijs Aveskamp - Startmenu - 2025</h1>
  <a class="header-avatar" href="index.php" title="Terug naar startpagina">
    <?php if (!empty($settings['admin_avatar'])): ?>
      <img src="<?= e($settings['admin_avatar']) ?>" alt="Admin">
    <?php else: ?>
      <span class="placeholder">MA</span>
    <?php endif; ?>
  </a>
</header>
<main class="admin">
<?php if (empty($_SESSION['logged'])): ?>
  <h2>Inloggen</h2>
  <?php if(!empty($err)) echo "<p class='err'>" . htmlspecialchars($err) . "</p>"; ?>
  <form method="post">
    <input type="password" name="pass" placeholder="Wachtwoord" required>
    <button name="login">Login</button>
  </form>
<?php else: ?>
  <?php if(!empty($err)) echo "<p class='err'>" . htmlspecialchars($err) . "</p>"; ?>

  <div class="admin-shell">
    <div class="menu-bar">
      <button id="menu-toggle" type="button" class="menu-toggle" aria-expanded="false" aria-controls="admin-menu" title="Toon/verberg menu">☰</button>
    </div>

    <nav id="admin-menu" class="admin-menu" aria-label="Admin menu">
      <div class="menu-panel">
      <button type="button" class="menu-item" data-target="categories" title="Categorieën & links">
        <span class="menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="7" height="7" stroke-width="1.6"/><rect x="14" y="3" width="7" height="7" stroke-width="1.6"/><rect x="14" y="14" width="7" height="7" stroke-width="1.6"/><rect x="3" y="14" width="7" height="7" stroke-width="1.6"/></svg></span>
        <span class="menu-label">Categorieën / links</span>
      </button>
      <button type="button" class="menu-item" data-target="links" title="Link aanmaken">
        <span class="menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M4 12h12" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 6l6 6-6 6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <span class="menu-label">Link aanmaken</span>
      </button>
      <button type="button" class="menu-item" data-target="avatar" title="Admin avatar">
        <span class="menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="7" r="4" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <span class="menu-label">Admin avatar</span>
      </button>
      <button type="button" class="menu-item" data-target="import" title="Import / Export">
        <span class="menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><polyline points="7 10 12 5 17 10" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="5" x2="12" y2="17" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <span class="menu-label">Import / Export</span>
      </button>
      <button type="button" class="menu-item" data-target="trash" title="Prullenbak">
        <span class="menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11v6M14 11v6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <span class="menu-label">Prullenbak</span>
      </button>
      <hr style="margin: 8px 0; border: none; border-top: 1px solid #e6e9ef;">
      <form method="post" style="margin: 0; display: flex; width: 100%;" onsubmit="return confirm('Versie bijwerken?');">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        <button name="bump_version" type="submit" class="menu-item" title="Versie bijwerken" style="width: 100%; margin: 0;">
          <span class="menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0118.8-4.3M22 12.5a10 10 0 01-18.8 4.2" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <span class="menu-label">Versie bijwerken</span>
        </button>
      </form>
      <a href="index.php" class="menu-item" title="Terug naar startpagina" style="text-decoration:none; color:inherit;">
        <span class="menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M3 12l9-9 9 9M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <span class="menu-label">Startpagina</span>
      </a>
      <form method="get" style="margin: 0; display: flex; width: 100%;">
        <input type="hidden" name="logout" value="1">
        <button type="submit" class="menu-item" title="Uitloggen" style="width: 100%; margin: 0;">
          <span class="menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M10 12h7" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <span class="menu-label">Uitloggen</span>
        </button>
      </form>
      </div>
    </nav>
    <div class="admin-content">
      <section class="panel" data-section="links">


  <?php if (isset($editing) && $editing !== null && isset($edit_item)): ?>

    <h2>Link bewerken</h2>
    <?php $cats = load_categories(); ?>
    <form method="post" enctype="multipart/form-data">
      <input name="title" placeholder="Titel" required value="<?= htmlspecialchars($edit_item['title']) ?>">
      <input name="url" placeholder="https://..." required value="<?= htmlspecialchars($edit_item['url']) ?>">
      <input name="desc" placeholder="Optionele beschrijving" value="<?= htmlspecialchars($edit_item['desc']) ?>">
      <label>Categorie</label>
      <select name="category_id">
        <?php foreach($cats as $c): ?>
          <option value="<?= e($c['id']) ?>" <?= ($c['id'] === ($edit_item['category_id'] ?? $GLOBALS['DEFAULT_CATEGORY_ID'])) ? 'selected' : '' ?>><?= e($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Gemaakt op (dd-mm-jjjj)</label>
      <input name="created" placeholder="dd-mm-jjjj" value="<?= htmlspecialchars($edit_item['created'] ?? date('d-m-Y')) ?>">
      <input name="icon_url" placeholder="Icon URL (bv. https://...)" value="<?= htmlspecialchars($edit_item['icon']) ?>">      <?php if ($ALLOW_UPLOADS): ?>
        <input type="file" name="icon_file" accept="image/*">
        <label class="checkbox-inline"><input type="checkbox" name="remove_icon" value="1"> Verwijder huidig icoon</label>
      <?php endif; ?>
      <input type="hidden" name="index" value="<?= (int)$editing ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
      <button name="save">Opslaan</button>
      <a href="admin.php">Annuleren</a>
    </form>
  <?php else: ?>
    <h2>Link toevoegen</h2>
    <?php $cats = load_categories(); ?>
    <form method="post" enctype="multipart/form-data" id="add-link-form">
      <input name="title" placeholder="Titel" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
      <input name="url" placeholder="https://..." required value="<?= htmlspecialchars($_POST['url'] ?? '') ?>">
      <input name="desc" placeholder="Optionele beschrijving" value="<?= htmlspecialchars($_POST['desc'] ?? '') ?>">
      <label>Categorie</label>
      <select name="category_id">
        <?php foreach($cats as $c): ?>
          <option value="<?= e($c['id']) ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] === $c['id']) ? 'selected' : '' ?>><?= e($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Gemaakt op (dd-mm-jjjj)</label>
      <input name="created" placeholder="dd-mm-jjjj" value="<?= htmlspecialchars($_POST['created'] ?? date('d-m-Y')) ?>">
      <input name="icon_url" placeholder="Icon URL (bv. https://...)" value="<?= htmlspecialchars($_POST['icon_url'] ?? '') ?>">      <?php if ($ALLOW_UPLOADS): ?>
        <input type="file" name="icon_file" accept="image/*">
      <?php endif; ?>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
      <button name="add">Toevoegen</button>
    </form>
  <?php endif; ?>

      </section>

      <section class="panel" data-section="avatar" hidden>
  <h2>Admin avatar</h2>
  <div class="avatar-box">
    <?php if (!empty($settings['admin_avatar'])): ?>
      <div class="avatar-preview"><img src="<?= e($settings['admin_avatar']) ?>" alt="Admin avatar"></div>
    <?php else: ?>
      <div class="avatar-preview placeholder">MA</div>
    <?php endif; ?>
  </div>
  <form method="post" enctype="multipart/form-data">
    <input name="avatar_url" placeholder="Avatar URL (https://...)" value="<?= htmlspecialchars($settings['admin_avatar'] ?? '') ?>">
    <?php if ($ALLOW_UPLOADS): ?>
      <input type="file" name="avatar_file" accept="image/*">
      <label class="checkbox-inline"><input type="checkbox" name="remove_avatar" value="1"> Verwijder huidig avatar</label>
    <?php endif; ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <button name="avatar_save">Opslaan avatar</button>
  </form>
  </section>
  <section class="panel" data-section="import" hidden>
  <h2>Import / Export</h2>
  <?php if (!empty($err)): ?>
    <div class="notice err"><?= e($err) ?></div>
  <?php endif; ?>
  <?php if (!empty($msg)): ?>
    <div class="notice success"><?= e($msg) ?></div>
  <?php endif; ?>

  <p>
    <a class="btn btn-edit" href="?export=1&csrf=<?= htmlspecialchars($_SESSION['csrf']) ?>">Exporteer JSON</a>
  </p>
  <form method="post" enctype="multipart/form-data">
    <label>Importeer JSON of ZIP (export met assets)</label>
    <input type="file" name="import_file" accept=".json,.zip,application/zip,application/json">
    <label class="checkbox-inline"><input type="checkbox" name="replace" value="1"> Vervang bestaande data (links, categorieën en assets)</label>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <button name="import" class="btn btn-edit">Importeren</button>
  </form>
  </section>
  <section class="panel" data-section="trash" hidden>
  <h2>Prullenbak</h2>
  <?php $trash = load_trash(); ?>
  <?php if (empty($trash)): ?>
    <p>Prullenbak is leeg</p>
  <?php else: ?>
    <form method="post" onsubmit="return confirm('Weet je zeker dat je alle items permanent wilt verwijderen? Dit kan niet worden teruggedraaid.');">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
      <button name="empty_trash" class="btn btn-delete">Leeg prullenbak</button>
    </form>
    <ul class="trash-list">
      <?php foreach($trash as $t): $item = $t['item']; ?>
        <li class="trash-item">
          <div class="trash-thumb">
            <?php $img = $t['moved_asset'] ?? ($item['icon'] ?? ''); if ($img && file_exists(__DIR__ . '/' . $img)): ?>
              <img src="<?= e($img) ?>" alt="<?= e($item['title'] ?? '') ?>">
            <?php else: ?>
              <div class="placeholder"><?= strtoupper(substr(e($item['title'] ?? ' '),0,1)) ?></div>
            <?php endif; ?>
          </div>
          <div class="trash-meta">
            <strong><?= e($item['title'] ?? '(zonder titel)') ?></strong><br>
            <small>Verwijderd: <?= date('d-m-Y H:i', $t['deleted_at'] ?? time()) ?></small>
          </div>
          <div class="trash-actions">
            <form method="post" style="display:inline; margin-right:8px;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
              <input type="hidden" name="token" value="<?= e($t['token']) ?>">
              <button name="restore" class="btn btn-edit">Ongedaan maken</button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Permanente verwijdering: weet je het zeker?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
              <input type="hidden" name="token" value="<?= e($t['token']) ?>">
              <button name="perma_delete" class="btn btn-delete">Verwijder permanent</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  </section>
  <section class="panel" data-section="categories" hidden>
  <h2>Categorieën & links (drag & drop)</h2>
  <div class="categories-toolbar">
    <input id="new-category-title" placeholder="Nieuwe categorie titel">
    <button id="create-category" class="btn btn-edit">Nieuwe categorie</button>
    <button id="save-order" class="btn btn-edit">Opslaan volgorde</button>
  </div>
  <div class="categories" id="categories-root">
    <?php $cats = load_categories(); $links = load_links(); // bouw een map van links per categorie en sorteert
      $byCat = []; foreach($cats as $c){ $byCat[$c['id']] = []; }
      foreach($links as $l){ $cid = $l['category_id'] ?? '{$DEFAULT_CATEGORY_ID}'; if (!isset($byCat[$cid])) $byCat[$cid]=[]; $byCat[$cid][] = $l; }
      // sorteert categorieën op volgorde
      usort($cats, function($a,$b){ return ($a['order'] ?? 0) <=> ($b['order'] ?? 0); });
      foreach($cats as $c): ?>
      <div class="category" data-cat-id="<?= e($c['id']) ?>">
        <div class="category-header" draggable="true" data-cat-id="<?= e($c['id']) ?>">
          <div class="cat-handle" title="Sleep om categorie te verplaatsen">☰</div>
          <span class="category-title" contenteditable="true"><?= e($c['title']) ?></span>
          <div class="category-actions">
            <button class="btn btn-edit cat-rename" data-id="<?= e($c['id']) ?>">Hernoem</button>
            <button class="btn btn-delete cat-delete" data-id="<?= e($c['id']) ?>">Verwijder</button>
          </div>
        </div>
        <ul class="links" data-cat-id="<?= e($c['id']) ?>">
          <?php if (isset($byCat[$c['id']])) {
            usort($byCat[$c['id']], function($x,$y){ return ($x['order'] ?? 0) <=> ($y['order'] ?? 0); });
            foreach($byCat[$c['id']] as $l): ?>
              <li class="link-item" draggable="true" data-id="<?= e($l['id']) ?>">
                <div style="display:flex;align-items:center;gap:10px">
                  <span class="link-title"><?= e($l['title']) ?></span>
                  <small class="link-date"><?= e($l['created'] ?? '') ?></small>
                </div>
                <div class="actions">
                  <a class="btn btn-edit" href="?edit=<?= e($l['id']) ?>">Bewerk</a>
                  <button class="btn btn-delete" type="button" data-index="<?= e($l['id']) ?>" data-title="<?= e($l['title']) ?>">Verwijder</button>
                </div>
              </li>
            <?php endforeach;
          } ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
  </section><!-- .panel categories -->
    </div><!-- .admin-content -->
  </div><!-- .admin-shell -->

  <?php $currentVersion = get_version(); ?>
  <div class="footer-version">
    <div class="version-text">Versie <?= e($currentVersion) ?></div>
  </div>

  <!-- Snackbar for undo -->
  <div id="snackbar" class="snackbar" role="status" aria-live="polite">
    <span id="snackbar-text">Item verwijderd</span>
    <button id="snackbar-undo" class="btn btn-edit" type="button">Ongedaan maken</button>
  </div>

  <script>
  (function(){
    // Gedelegeerde handler: bevestig verwijdering via browser confirm() en verstuur POST
    document.addEventListener('click', function(e){
      var btn = e.target.closest('.btn-delete');
      if (!btn) return;
      // Categorie verwijderen (gedelegeerd)
      if (btn.classList.contains('cat-delete')) {
        e.preventDefault();
        if (!confirm('Verwijder categorie en verplaats links naar Overig?')) return;
        var id = btn.getAttribute('data-id');
        var fd = new FormData(); fd.append('action','delete_category'); fd.append('id',id); fd.append('csrf','<?= htmlspecialchars($_SESSION['csrf']) ?>');
        fetch('admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{ if (j && j.ok) location.reload(); else alert('Mislukt'); }).catch(function(){ alert('Network error'); });
        return;
      }
      // Link verwijderen (gedelegeerd)
      if (!btn.closest('.links') && !btn.closest('.link-item')) return;
      e.preventDefault();
      var idx = btn.getAttribute('data-index');
      var title = btn.getAttribute('data-title') || '';
      var ok = confirm('Weet je zeker dat je "' + title + '" wilt verwijderen?');
      if (!ok) return;
      var form = document.createElement('form');
      form.method = 'post'; form.style.display = 'none';
      var inp = document.createElement('input'); inp.name='del'; inp.value=idx; form.appendChild(inp);
      var csrf=document.createElement('input'); csrf.type='hidden'; csrf.name='csrf'; csrf.value='<?= htmlspecialchars($_SESSION['csrf']) ?>'; form.appendChild(csrf);
      document.body.appendChild(form); form.submit();
    });

    // Snackbar (ongedaan maken) afhandeling (ongemodificeerd)
    var snackbar = document.getElementById('snackbar');
    var undoBtn = document.getElementById('snackbar-undo');
    var undoTimer = null;
    function showSnackbar(token, title){
      if (!snackbar) return;
      document.getElementById('snackbar-text').textContent = '"' + title + '" verwijderd';
      snackbar.classList.add('is-visible');
      snackbar.setAttribute('data-token', token);
      undoTimer = setTimeout(hideSnackbar, 8000);
    }

    function hideSnackbar(){
      if (!snackbar) return;
      snackbar.classList.remove('is-visible');
      snackbar.removeAttribute('data-token');
      if (undoTimer) {
        clearTimeout(undoTimer);
        undoTimer = null;
      }
    }

    if (undoBtn){
      undoBtn.addEventListener('click', function(){
        var token = snackbar.getAttribute('data-token');
        if (!token) return;
        var form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';

        var inp = document.createElement('input');
        inp.name = 'token';
        inp.value = token;
        form.appendChild(inp);

        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf';
        csrf.value = '<?= htmlspecialchars($_SESSION['csrf']) ?>';
        form.appendChild(csrf);

        var r = document.createElement('input');
        r.type = 'hidden';
        r.name = 'restore';
        r.value = '1';
        form.appendChild(r);

        document.body.appendChild(form);
        form.submit();
      });
    }

    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('deleted')){
      var t = urlParams.get('deleted');
      var title = urlParams.get('title') || '';
      if (title){ title = decodeURIComponent(title); }
      showSnackbar(t, title);
      history.replaceState(null, '', window.location.pathname);
    }

    // Sleep & plaats afhandeling voor link-items met visuele placeholder
    function enableDragDrop(){
      var dragEl = null;
      var linkPlaceholder = document.createElement('li'); linkPlaceholder.className = 'link-placeholder';

      function getLinkAfter(container, y){
        var children = Array.from(container.querySelectorAll('.link-item:not(.dragging)'));
        for (var i=0;i<children.length;i++){ var box = children[i].getBoundingClientRect(); if (y < box.top + box.height/2) return children[i]; }
        return null;
      }

      document.querySelectorAll('.link-item').forEach(function(item){
        item.addEventListener('dragstart', function(e){ dragEl = this; this.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', this.getAttribute('data-id')); });
        item.addEventListener('dragend', function(e){ if (dragEl) dragEl.classList.remove('dragging'); if (linkPlaceholder.parentNode) linkPlaceholder.parentNode.removeChild(linkPlaceholder); dragEl = null; });
      });

      document.querySelectorAll('.links').forEach(function(list){
        list.addEventListener('dragover', function(e){ e.preventDefault(); var after = getLinkAfter(this, e.clientY); if (after == null) { this.appendChild(linkPlaceholder); } else { this.insertBefore(linkPlaceholder, after); } });
        list.addEventListener('dragleave', function(e){ /* laat placeholder zichtbaar totdat het gedropt is */ });
        list.addEventListener('drop', function(e){ e.preventDefault(); if (!dragEl) return; this.insertBefore(dragEl, linkPlaceholder); if (linkPlaceholder.parentNode) linkPlaceholder.parentNode.removeChild(linkPlaceholder); dragEl.classList.remove('dragging'); dragEl = null; });
      });
    }
    enableDragDrop();

    // Categorie sleepplaatsvervanger + herschikken
    (function(){
      var dragged = null;
      var catPlaceholder = document.createElement('div'); catPlaceholder.className = 'cat-placeholder';
      var container = document.getElementById('categories-root');

      document.querySelectorAll('.category-header').forEach(function(header){
        var handle = header.querySelector('.cat-handle');
        header.addEventListener('dragstart', function(e){
          // allow drag only via handle
          if (e.target !== header && !e.target.closest('.cat-handle')) { e.preventDefault(); return; }
          dragged = header.closest('.category');
          // set placeholder height
          catPlaceholder.style.height = dragged.getBoundingClientRect().height + 'px';
          dragged.classList.add('dragging-cat');
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', dragged.getAttribute('data-cat-id'));
        });
        header.addEventListener('dragend', function(e){ if (dragged) dragged.classList.remove('dragging-cat'); if (catPlaceholder.parentNode) catPlaceholder.parentNode.removeChild(catPlaceholder); dragged = null; });
      });

      function getCategoryAfter(container, y){
        var cats = Array.from(container.querySelectorAll('.category:not(.dragging-cat)'));
        for (var i=0;i<cats.length;i++){ var box = cats[i].getBoundingClientRect(); if (y < box.top + box.height/2) return cats[i]; }
        return null;
      }

      container.addEventListener('dragover', function(e){ e.preventDefault(); var after = getCategoryAfter(this, e.clientY); if (after == null) { this.appendChild(catPlaceholder); } else { this.insertBefore(catPlaceholder, after); } });
      container.addEventListener('drop', function(e){ e.preventDefault(); if (!dragged) return; this.insertBefore(dragged, catPlaceholder); if (catPlaceholder.parentNode) catPlaceholder.parentNode.removeChild(catPlaceholder); dragged.classList.remove('dragging-cat'); dragged = null; });
    })();

    // Volgorde opslaan
    document.getElementById('save-order').addEventListener('click', function(){
      var cats = [];
      document.querySelectorAll('.category').forEach(function(catEl){
        var cid = catEl.getAttribute('data-cat-id');
        var title = catEl.querySelector('.category-title').textContent.trim();
        var links = [];
        catEl.querySelectorAll('.links .link-item').forEach(function(li){ links.push(li.getAttribute('data-id')); });
        cats.push({id:cid,title:title,links:links});
      });
      var fd = new FormData(); fd.append('action','save_order'); fd.append('order', JSON.stringify(cats)); fd.append('csrf','<?= htmlspecialchars($_SESSION['csrf']) ?>');
      fetch('admin.php', {method:'POST',body:fd}).then(function(r){ return r.json(); }).then(function(j){ if (j && j.ok){ location.reload(); } else { alert('Opslaan mislukt: ' + (j.error||'onbekend')); } }).catch(function(){ alert('Opslaan mislukt (network)'); });
    });

    // Nieuwe categorie aanmaken
    document.getElementById('create-category').addEventListener('click', function(){
      var t = document.getElementById('new-category-title').value.trim(); if (!t) { alert('Vul een titel in'); return; }
      var fd = new FormData(); fd.append('action','create_category'); fd.append('title',t); fd.append('csrf','<?= htmlspecialchars($_SESSION['csrf']) ?>');
      fetch('admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{ if (j.ok){ location.reload(); } else { alert('Mislukt: ' + (j.error||'onbekend')); } }).catch(e=>alert('Network error'));
    });

    // AJAX link toevoegen (indienen zonder volledige herlaad en invoegen in categorie)
    var addForm = document.getElementById('add-link-form');
    if (addForm) {
      addForm.addEventListener('submit', function(e){
        // als knop met naam 'add' aanwezig is
        if (!this.querySelector('button[name="add"]')) return; // niet het toevoegformulier
        e.preventDefault();
        var fd = new FormData(this);
        fd.append('add','1');
        fetch('admin.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(function(j){
          if (j && j.ok && j.link) {
            // zoek categorie-lijst
            var cat = j.link.category_id || '';
            var ul = document.querySelector('.links[data-cat-id="'+cat+'"]');
            if (!ul) {
              // als categoriakolom niet aanwezig is (zou niet moeten gebeuren), herlaad
              location.reload(); return;
            }
            // maak <li> element
            var li = document.createElement('li'); li.className='link-item newly-added'; li.setAttribute('draggable','true'); li.setAttribute('data-id', j.link.id);
            var left = document.createElement('div'); left.style.display='flex'; left.style.alignItems='center'; left.style.gap='10px'; var span = document.createElement('span'); span.className='link-title'; span.textContent = j.link.title; left.appendChild(span); var small = document.createElement('small'); small.className='link-date'; small.textContent = j.link.created || ''; left.appendChild(small); li.appendChild(left);
            var actions = document.createElement('div'); actions.className='actions';
            var edit = document.createElement('a'); edit.className='btn btn-edit'; edit.href='?edit='+encodeURIComponent(j.link.id); edit.textContent='Bewerk'; actions.appendChild(edit);
            var del = document.createElement('button'); del.className='btn btn-delete'; del.type='button'; del.setAttribute('data-index', j.link.id); del.setAttribute('data-title', j.link.title); del.textContent='Verwijder'; actions.appendChild(del);
            li.appendChild(actions);
            ul.appendChild(li);
            // koppel gedragingen
            attachLinkBehaviors(li);
            // markeer kort
            setTimeout(function(){ li.classList.remove('newly-added'); }, 2200);
            // eventueel scrollen naar zicht
            li.scrollIntoView({behavior:'smooth',block:'center'});
            // formulier leegmaken
            addForm.reset();
            // toon succes modal
            var modal = document.getElementById('success-modal');
            if (modal) {
              modal.classList.add('show');
              // verdwijn na 2 seconden automatisch
              setTimeout(function() {
                modal.classList.remove('show');
              }, 2000);
            }
          } else {
            alert('Toevoegen mislukt: ' + (j.error || 'onbekend'));
          }
        }).catch(function(){ alert('Netwerkfout bij toevoegen'); });
      });
    }

    function attachLinkBehaviors(li){
      // sleepbehandelaars
      li.addEventListener('dragstart', function(e){ e.dataTransfer.setData('text/plain', this.getAttribute('data-id')); e.dataTransfer.effectAllowed = 'move'; this.classList.add('dragging'); });
      li.addEventListener('dragend', function(e){ this.classList.remove('dragging'); });
      // delete-knop wordt al afgehandeld door de gedelegeerde listener
    }

    // Category drag & drop (reorder categories)
    (function(){
      var dragged = null;
      document.querySelectorAll('.category-header').forEach(function(header){
        // Sta alleen slepen toe via de handle
        var handle = header.querySelector('.cat-handle');
        header.addEventListener('dragstart', function(e){
          // vereist dat de handle de bron is
          if (e.target !== header && !e.target.closest('.cat-handle')) { e.preventDefault(); return; }
          dragged = header.closest('.category');
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', dragged.getAttribute('data-cat-id'));
          dragged.classList.add('dragging-cat');
        });
        header.addEventListener('dragend', function(e){ if (dragged) dragged.classList.remove('dragging-cat'); dragged = null; });
      });
      var container = document.getElementById('categories-root');
      container.addEventListener('dragover', function(e){ e.preventDefault(); var target = e.target.closest('.category'); if (!target || target === dragged) return; var rect = target.getBoundingClientRect(); var after = (e.clientY - rect.top) > (rect.height/2); if (after) target.parentNode.insertBefore(dragged, target.nextSibling); else target.parentNode.insertBefore(dragged, target); });
      container.addEventListener('drop', function(e){ e.preventDefault(); });
    })();

    // Hernoem + verwijder categorie
    document.querySelectorAll('.cat-rename').forEach(function(b){ b.addEventListener('click', function(){ var id = this.getAttribute('data-id'); var parent = this.closest('.category'); var title = parent.querySelector('.category-title').textContent.trim(); var fd = new FormData(); fd.append('action','rename_category'); fd.append('id',id); fd.append('title',title); fd.append('csrf','<?= htmlspecialchars($_SESSION['csrf']) ?>'); fetch('admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{ if (j.ok){ alert('Naam aangepast'); } else alert('Mislukt'); }); }); });
    document.querySelectorAll('.cat-delete').forEach(function(b){ b.addEventListener('click', function(){ if (!confirm('Verwijder categorie en verplaats links naar Overig?')) return; var id = this.getAttribute('data-id'); var fd = new FormData(); fd.append('action','delete_category'); fd.append('id',id); fd.append('csrf','<?= htmlspecialchars($_SESSION['csrf']) ?>'); fetch('admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{ if (j.ok){ location.reload(); } else alert('Mislukt'); }); }); });

  })();
  </script>
  <script>
  (function(){
    var menuButtons = document.querySelectorAll('.admin-menu .menu-item');
    var panels = document.querySelectorAll('.panel');
    var menuToggle = document.getElementById('menu-toggle');
    var shell = document.querySelector('.admin-shell');
    // move the toggle into the nav on desktop for better alignment; keep it outside on mobile
    (function(){
      if (!menuToggle) return;
      var adminMenu = document.getElementById('admin-menu');
      var originalParent = menuToggle.parentNode;
      function updateTogglePlacement(){
        var desktop = window.matchMedia && window.matchMedia('(min-width:721px)').matches;
        try{
          if (desktop){
            if (menuToggle.parentNode !== adminMenu){ adminMenu.insertBefore(menuToggle, adminMenu.firstChild); menuToggle.classList.add('inside-menu'); }
          } else {
            if (menuToggle.parentNode !== originalParent){ originalParent.insertBefore(menuToggle, originalParent.firstChild); menuToggle.classList.remove('inside-menu'); }
          }
        }catch(e){}
      }
      updateTogglePlacement();
      if (window.matchMedia){
        var mq = window.matchMedia('(min-width:721px)');
        if (mq.addEventListener) mq.addEventListener('change', updateTogglePlacement); else mq.addListener(updateTogglePlacement);
      }
    })();

    function setCollapsed(state){
      if (!shell) return;
      if (state) {
        shell.classList.add('collapsed');
        if (menuToggle) menuToggle.setAttribute('aria-expanded','false');
      } else {
        shell.classList.remove('collapsed');
        if (menuToggle) menuToggle.setAttribute('aria-expanded','true');
      }
      try{ localStorage.setItem('admin:menu-collapsed', state ? '1' : '0'); }catch(e){}
    }

    if (menuToggle){
      // improved handler: use touchend and debouncing so one tap toggles reliably
      var lastTouch = 0;
      var lastToggleTime = 0;
      function handleToggle(e){
        var now = Date.now();
        if (now - lastToggleTime < 600) return; // debounce double events
        lastToggleTime = now;
        try{ console.log('menu toggle event', e && e.type); }catch(ex){}
        // ignore synthetic click shortly after touch
        if (e && (e.type === 'click') && (now - lastTouch) < 600) return;
        var isMobile = window.matchMedia && window.matchMedia('(max-width:720px)').matches;
        if (isMobile){
          // on mobile: toggle temporary menu-open class
          var willOpen = !shell.classList.contains('menu-open');
          if (willOpen){ shell.classList.add('menu-open'); document.body.classList.add('no-scroll'); }
          else { shell.classList.remove('menu-open'); document.body.classList.remove('no-scroll'); }
          // set aria-expanded accordingly
          menuToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        } else {
          // desktop: collapse sidebar
          setCollapsed(!shell.classList.contains('collapsed'));
        }
      }
      menuToggle.addEventListener('click', handleToggle);
      menuToggle.addEventListener('touchend', function(ev){ lastTouch = Date.now(); handleToggle(ev); ev.preventDefault(); });
    }

    // close overlay when clicking backdrop (only on mobile overlay)
    var adminMenuEl = document.getElementById('admin-menu');
    if (adminMenuEl){
      adminMenuEl.addEventListener('click', function(e){
        if (e.target === adminMenuEl){
          shell.classList.remove('menu-open');
          menuToggle.setAttribute('aria-expanded','false');
          document.body.classList.remove('no-scroll');
        }
      });
    }
    function showPanel(name){
      panels.forEach(function(p){ var is = p.getAttribute('data-section') === name; p.hidden = !is; p.classList.toggle('active', is); });
      menuButtons.forEach(function(b){ b.classList.toggle('active', b.getAttribute('data-target') === name); });
      try{ localStorage.setItem('admin:panel', name); }catch(e){}
      // if on mobile, close the temporary menu after selection and restore scrolling
      if (window.matchMedia && window.matchMedia('(max-width:720px)').matches){ shell.classList.remove('menu-open'); if (menuToggle) menuToggle.setAttribute('aria-expanded','false'); document.body.classList.remove('no-scroll'); }
    }
    menuButtons.forEach(function(b){ b.addEventListener('click', function(){ showPanel(this.getAttribute('data-target')); }); });

    // Close overlay with ESC key on mobile
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' || e.key === 'Esc'){ if (shell.classList.contains('menu-open')){ shell.classList.remove('menu-open'); if (menuToggle) menuToggle.setAttribute('aria-expanded','false'); document.body.classList.remove('no-scroll'); } } });
    var start = '<?php echo isset($editing) && $editing !== null ? "links" : (isset($_GET["panel"]) ? e($_GET["panel"]) : "") ?>' || localStorage.getItem('admin:panel') || 'links';

    // restore collapsed state preference. If none set and small screen, collapse.
    var savedCollapsed = (function(){ try{ return localStorage.getItem('admin:menu-collapsed'); }catch(e){ return null;} })();
    if (savedCollapsed === '1') setCollapsed(true);
    else if (savedCollapsed === '0') setCollapsed(false);
    else { if (window.matchMedia && window.matchMedia('(max-width:720px)').matches) setCollapsed(true); }

    // watch resize: if no explicit pref saved then auto-collapse/uncollapse
    if (window.matchMedia){
      var mq = window.matchMedia('(max-width:720px)');
      mq.addEventListener ? mq.addEventListener('change', function(ev){
        var saved = (function(){ try{ return localStorage.getItem('admin:menu-collapsed'); }catch(e){ return null;} })();
        if (saved !== null) return;
        setCollapsed(ev.matches);
      }) : mq.addListener(function(ev){
        var saved = (function(){ try{ return localStorage.getItem('admin:menu-collapsed'); }catch(e){ return null;} })();
        if (saved !== null) return;
        setCollapsed(ev.matches);
      });
    }

    showPanel(start);
  })();
  </script>
<?php endif; ?>

<!-- Succes Modal -->
<div id="success-modal" class="success-modal">
  <div class="success-modal-content">
    <svg class="success-check" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    <h3>✓ Link aangemaakt</h3>
    <p>Je nieuwe link is succesvol toegevoegd!</p>
    <button class="btn-ok" onclick="document.getElementById('success-modal').classList.remove('show')">OK</button>
  </div>
</div>

</main>
</body></html>