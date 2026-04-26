<?php
declare(strict_types=1);

define('_CALIBRE_OPDS', true);
session_start();
require_once __DIR__ . '/bootstrap.php';

use CalibreOpds\Config;
use CalibreOpds\Auth;
use CalibreOpds\Navigation;
use CalibreOpds\Opds;
use CalibreOpds\Queue;

// ── 1. Config check ───────────────────────────────────────────────────────────
if (!Config::load()) {
    header('Location: config.php');
    exit;
}

// ── 2. Logout ─────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    Auth::logout();
    header('Location: index.php');
    exit;
}

// ── 3. Login gate — MUST come before any content output ───────────────────────
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_key'])) {
    if (Auth::attempt($_POST['api_key'])) {
        $dest = $_POST['return'] ?? 'index.php';
        // Prevent open redirect
        if (!preg_match('/^[a-zA-Z0-9_.?=&\/-]+$/', $dest)) $dest = 'index.php';
        header('Location: ' . $dest);
        exit;
    }
    $loginError = 'Invalid key.';
}

if (!Auth::check()) {
    $return = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php');
    include __DIR__ . '/views/login.php';
    exit;
}

// ── 4. Authenticated-only actions ────────────────────────────────────────────
$action = $_GET['action'] ?? '';

if ($action === 'img') {
    Opds::handleAction('img');
    exit;
}

if ($action === 'download') {
    Opds::handleAction('download');
    exit;
}

if ($action === 'kobo') {
    Opds::handleAction('kobo');
    exit;
}

// ── 5. Browse / Search ────────────────────────────────────────────────────────
$searchQ = trim($_GET['q'] ?? '');
$feedUrl = '';

if ($searchQ !== '') {
    $feedUrl = Opds::searchUrl($searchQ);
} else {
    $feedUrl = $_GET['feed'] ?? Config::get('OPDS_BASE');
    $feedUrl = Opds::absoluteUrl($feedUrl);
}

$rawXml  = Opds::fetch($feedUrl);
$feedErr = ($rawXml === false);
$catalog = $feedErr
    ? ['title' => 'Connection error', 'entries' => [], 'nav' => []]
    : Opds::parse($rawXml);

$crumbs    = Navigation::breadcrumb($catalog);
$siteTitle = Config::get('READER_TITLE', 'Calibre Bookshelf');
$queueCount = Queue::count();

// ── 6. Render ─────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="wrap header-inner">
        <a class="site-title" href="index.php"><?= htmlspecialchars($siteTitle) ?></a>
        <nav class="site-nav">
            <a class="nav-link active" href="index.php">📚 Reader</a>
            <a class="nav-link" href="config.php?tab=queue">
                ☰ Queue<?php if ($queueCount > 0): ?> <span class="badge"><?= $queueCount ?></span><?php endif; ?>
            </a>
            <a class="nav-link" href="about.php">ℹ About</a>
            <a class="nav-link" href="config.php">⚙ Config</a>
            <a class="nav-link logout" href="index.php?logout=1" title="Log out">⏻</a>
        </nav>
    </div>
</header>

<div class="wrap">

    <div class="search-bar">
        <form method="get" action="index.php">
            <input type="text" name="q"
                   placeholder="Search books…"
                   value="<?= htmlspecialchars($searchQ) ?>"
                   autocomplete="off">
            <button type="submit">Search</button>
            <?php if ($searchQ): ?>
                <a class="btn btn-secondary" href="index.php">✕</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (count($crumbs) > 1): ?>
        <div class="breadcrumb">
            <?php foreach ($crumbs as $i => $crumb): ?>
                <?php if ($i > 0): ?><span class="sep">›</span><?php endif; ?>
                <?php if ($crumb['href']): ?>
                    <a href="<?= htmlspecialchars($crumb['href']) ?>">
                        <?= isset($crumb['icon']) ? $crumb['icon'] . ' ' : '' ?><?= htmlspecialchars($crumb['label']) ?>
                    </a>
                <?php else: ?>
                    <span><?= htmlspecialchars($crumb['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($feedErr): ?>
        <div class="error-box">⚠ Could not connect to OPDS server. Check your credentials in <a href="config.php">configuration</a>.</div>
    <?php endif; ?>

    <?php if (!$feedErr && empty($catalog['entries'])): ?>
        <p class="empty">— no entries found —</p>
    <?php else: ?>
        <ul class="entry-list">
        <?php foreach ($catalog['entries'] as $entry):
            $acqLinks = array_values(array_filter($entry['links'], fn($l) => $l['rel'] === 'acquisition'));
            $best     = Opds::preferredLink($acqLinks);
            $isBook   = $entry['type'] === 'book' && !empty($acqLinks);
            $navLinks = array_values(array_filter($entry['links'], fn($l) => $l['rel'] !== 'acquisition'));
            $navHref  = $navLinks[0]['href'] ?? $entry['id'] ?? '';
        ?>
            <li class="entry-item">

                <?php if (!$isBook): ?>
                    <a class="entry-nav" href="<?= htmlspecialchars(Navigation::feedUrl($navHref)) ?>">
                        <span class="ico">📂</span>
                        <span class="nav-title"><?= htmlspecialchars($entry['title']) ?></span>
                        <span class="nav-arrow">›</span>
                    </a>

                <?php else: ?>
                    <div class="book-header">
                        <?php if ($entry['cover']): ?>
                            <img class="book-cover"
                                 src="<?= htmlspecialchars(Navigation::imgUrl($entry['cover'])) ?>"
                                 alt=""
                                 loading="lazy"
                                 onerror="this.replaceWith(makePlaceholder())">
                        <?php else: ?>
                            <div class="book-cover-placeholder">📖</div>
                        <?php endif; ?>

                        <div class="book-meta">
                            <div class="book-title"><?= htmlspecialchars($entry['title']) ?></div>
                            <?php if ($entry['author']): ?>
                                <div class="book-author"><?= htmlspecialchars($entry['author']) ?></div>
                            <?php endif; ?>
                            <?php if ($entry['summary']): ?>
                                <div class="book-summary"><?= htmlspecialchars($entry['summary']) ?></div>
                            <?php endif; ?>

                            <div class="btn-row">
                                <?php foreach ($acqLinks as $lk):
                                    $ext    = strtoupper($lk['ext'] ?? '?');
                                    $slug   = mb_substr(preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $entry['title']), 0, 80) ?: 'book';
                                    $dlName = trim($slug) . '.' . strtolower($lk['ext'] ?? 'epub');
                                ?>
                                    <button class="btn btn-secondary dl-btn"
                                            data-href="<?= htmlspecialchars(Navigation::enc($lk['href'])) ?>"
                                            data-name="<?= htmlspecialchars($dlName) ?>"
                                            title="Download <?= htmlspecialchars($ext) ?>">
                                        ↓ <span class="fmt-badge"><?= htmlspecialchars($ext) ?></span>
                                    </button>
                                <?php endforeach; ?>

                                <?php if ($best && Config::get('BOOKS_API_URL') !== ''): ?>
                                    <?php
                                    $slug   = mb_substr(preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $entry['title']), 0, 80) ?: 'book';
                                    $koName = trim($slug) . '.' . ($best['ext'] ?? 'epub');
                                    ?>
                                    <button class="btn btn-kobo kobo-send"
                                            data-href="<?= htmlspecialchars(Navigation::enc($best['href'])) ?>"
                                            data-name="<?= htmlspecialchars($koName) ?>"
                                            data-title="<?= htmlspecialchars($entry['title']) ?>"
                                            data-author="<?= htmlspecialchars($entry['author']) ?>"
                                            title="Send to Kobo (<?= htmlspecialchars(strtoupper($best['ext'] ?? '')) ?>)">
                                        ⇢ Kobo
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($catalog['nav']['next']) || !empty($catalog['nav']['previous'])): ?>
        <div class="pagination">
            <?php if (!empty($catalog['nav']['previous'])): ?>
                <a class="btn btn-secondary" href="<?= htmlspecialchars(Navigation::feedUrl($catalog['nav']['previous'])) ?>">← Previous</a>
            <?php endif; ?>
            <?php if (!empty($catalog['nav']['next'])): ?>
                <a class="btn btn-secondary" href="<?= htmlspecialchars(Navigation::feedUrl($catalog['nav']['next'])) ?>">Next →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<footer>
    <div class="wrap footer-inner">
        <span><?= htmlspecialchars($siteTitle) ?> · OPDS</span>
        <a href="<?= htmlspecialchars(Navigation::feedUrl(Config::get('OPDS_BASE'))) ?>">⌂ root</a>
    </div>
</footer>

<div id="toast"></div>

<script>
function toast(msg, isErr = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show' + (isErr ? ' err' : '');
    clearTimeout(t._tid);
    t._tid = setTimeout(() => t.className = '', 3500);
}
function makePlaceholder() {
    const d = document.createElement('div');
    d.className = 'book-cover-placeholder';
    d.textContent = '📖';
    return d;
}

document.querySelectorAll('.dl-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'index.php?action=download';
        ['href', 'name'].forEach(k => {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = k;
            inp.value = btn.dataset[k];
            form.appendChild(inp);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });
});

document.querySelectorAll('.kobo-send').forEach(btn => {
    btn.addEventListener('click', async () => {
        btn.disabled = true;
        const orig = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span>';
        try {
            const body = new URLSearchParams({
                href:   btn.dataset.href,
                name:   btn.dataset.name,
                title:  btn.dataset.title  || btn.dataset.name,
                author: btn.dataset.author || 'Unknown',
            });
            const res  = await fetch('index.php?action=kobo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            });
            const json = await res.json();
            if (json.error) {
                toast('✗ ' + json.error, true);
            } else {
                toast('✓ Added to Kobo queue');
                btn.innerHTML = '✓ Queued';
                btn.style.opacity = '0.5';
                return;
            }
        } catch (e) {
            toast('✗ ' + e.message, true);
        }
        btn.innerHTML = orig;
        btn.disabled  = false;
    });
});
</script>
</body>
</html>
