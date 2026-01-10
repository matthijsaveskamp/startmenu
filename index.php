<?php
require 'config.php';
$links = [];
if (file_exists($LINKS_FILE)) {
    $links = json_decode(file_get_contents($LINKS_FILE), true) ?: [];
}
$settings = [];
if (file_exists($SETTINGS_FILE)) { $settings = json_decode(file_get_contents($SETTINGS_FILE), true) ?: []; }
$admin_avatar = $settings['admin_avatar'] ?? '';
$version = $settings['version'] ?? '1.00.00';

// laad categorieën (weergegeven op de startpagina)
$categories = [];
if (file_exists($CATEGORIES_FILE)) { $categories = json_decode(file_get_contents($CATEGORIES_FILE), true) ?: []; }
// sorteert categorieën op volgorde
usort($categories, function($a,$b){ return ($a['order'] ?? 0) <=> ($b['order'] ?? 0); });

function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Startpagina</title>
<link rel="icon" href="assets/start.ico" type="image/x-icon">
<link rel="shortcut icon" href="assets/start.ico" type="image/x-icon">
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <h1>Matthijs Aveskamp - Startmenu - 2025</h1>
  <div class="header-version">Versie <?= e($version) ?></div>
  <a class="header-avatar" href="admin.php" title="Admin">
    <?php if (!empty($admin_avatar)): ?>
      <img src="<?= e($admin_avatar) ?>" alt="Admin">
    <?php else: ?>
      <span class="placeholder">MA</span>
    <?php endif; ?>
  </a>
</header>

<nav class="categories-bar" id="categories-bar">
  <button class="cat-btn active" data-cat="">Alle</button>
  <?php foreach($categories as $c): ?>
    <button class="cat-btn" data-cat="<?= e($c['id']) ?>"><?= e($c['title']) ?></button>
  <?php endforeach; ?>
</nav>

<main id="links-grid" class="grid">
<?php foreach ($links as $l): $cat = $l['category_id'] ?? '{$DEFAULT_CATEGORY_ID}'; ?>
  <a class="card" href="<?= e($l['url']) ?>" target="_blank" rel="noopener noreferrer" data-cat="<?= e($cat) ?>">
    <div class="thumb">
      <?php if (!empty($l['icon'])): ?>
        <img src="<?= e($l['icon']) ?>" alt="<?= e($l['title']) ?>">
      <?php else: ?>
        <span class="placeholder"><?= strtoupper(substr(e($l['title']), 0, 1)) ?></span>
      <?php endif; ?>
    </div>
    <div class="meta">
      <strong><?= e($l['title']) ?></strong>
      <?php if (!empty($l['desc'])): ?><small><?= e($l['desc']) ?></small><?php endif; ?>
      <?php if (!empty($l['created'])): ?><small class="card-date"><?= e($l['created']) ?></small><?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>
</main>

<!-- Mobile-only version text placed under the cards -->
<div class="mobile-version">Versie <?= e($version) ?></div>

<script>
// categorie-filtering
(function(){
  var buttons = document.querySelectorAll('.cat-btn');
  function setActive(btn){ buttons.forEach(function(b){ b.classList.remove('active'); }); btn.classList.add('active'); }
  function filter(cat){
    document.querySelectorAll('#links-grid .card').forEach(function(card){
      if (!cat || card.getAttribute('data-cat') === cat) card.style.display = 'flex'; else card.style.display = 'none';
    });
  }
  buttons.forEach(function(btn){ btn.addEventListener('click', function(){ setActive(this); filter(this.getAttribute('data-cat')); }); });
})();
</script>
</body>
</html>