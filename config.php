<?php
declare(strict_types=1);

define('_CALIBRE_OPDS', true);
session_start();
require_once __DIR__ . '/bootstrap.php';

use CalibreOpds\Config;
use CalibreOpds\Auth;
use CalibreOpds\Queue;

$configured = Config::load();
$errors     = [];
$success    = '';
$csrfToken  = Auth::csrfToken();
$tab        = $_GET['tab'] ?? 'settings';

// ── Auth gate ─────────────────────────────────────────────────────────────────
if ($configured && !Auth::check()) {
    $loginError = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_key'])) {
        if (Auth::attempt($_POST['api_key'])) {
            header('Location: config.php');
            exit;
        }
        $loginError = 'Invalid key.';
    }
    $siteTitle = Config::get('READER_TITLE', 'Calibre Bookshelf');
    $return    = 'config.php';
    include __DIR__ . '/views/login.php';
    exit;
}

// ── Queue actions (AJAX) ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['queue_action'])) {
    header('Content-Type: application/json');
    if (!Auth::verifyCsrf()) {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    match ($_POST['queue_action']) {
        'remove' => Queue::remove((int)($_POST['index'] ?? -1)),
        'clear'  => Queue::clear(),
        default  => null,
    };
    echo json_encode(['success' => true]);
    exit;
}

// ── Save config ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!Auth::verifyCsrf()) {
        $errors[] = 'Invalid form submission. Please reload and try again.';
    } else {
        $fields = ['NC_USER', 'NC_PASS', 'API_KEY', 'BOOKS_API_URL', 'OPDS_BASE', 'READER_TITLE'];
        $values = [];
        foreach ($fields as $f) {
            $values[$f] = trim($_POST[$f] ?? '');
        }

        if ($configured && $values['NC_PASS'] === '') {
            $values['NC_PASS'] = Config::get('NC_PASS');
        }

        foreach (['NC_USER', 'NC_PASS', 'API_KEY', 'OPDS_BASE'] as $req) {
            if ($values[$req] === '') {
                $errors[] = "{$req} is required.";
            }
        }

        if (!$errors) {
            if (Config::save($values)) {
                $success    = 'Configuration saved successfully.';
                $configured = Config::load();
                if (!$configured) Auth::attempt($values['API_KEY']);
            } else {
                $errors[] = 'Could not write config file. Ensure the data/ directory is writable by the web server.';
            }
        }
    }
}

$cur = $configured ? Config::raw() : [];
$val = fn(string $k, string $d = '') => htmlspecialchars($cur[$k] ?? $d);
$siteTitle = $val('READER_TITLE', 'Calibre Bookshelf');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Config — <?= $siteTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="wrap header-inner">
        <a class="site-title" href="index.php"><?= $siteTitle ?></a>
        <nav class="site-nav">
            <a class="nav-link" href="index.php">📚 Reader</a>
            <a class="nav-link<?= $tab === 'queue' ? ' active' : '' ?>" href="config.php?tab=queue">
                ☰ Queue<?php $qc = Queue::count(); if ($qc > 0): ?> <span class="badge"><?= $qc ?></span><?php endif; ?>
            </a>
            <a class="nav-link" href="about.php">ℹ About</a>
            <a class="nav-link<?= $tab === 'settings' ? ' active' : '' ?>" href="config.php">⚙ Config</a>
            <?php if (Auth::check()): ?>
                <a class="nav-link logout" href="index.php?logout=1">⏻</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div class="wrap">

    <?php if (!$configured && $tab === 'settings'): ?>
        <div class="config-intro">
            <strong>Welcome!</strong> Fill in the details below to connect your Nextcloud Calibre2OPDS instance.
        </div>
    <?php endif; ?>

    <!-- Tab nav -->
    <div class="tab-nav">
        <a href="config.php?tab=settings" class="tab-link<?= $tab === 'settings' ? ' active' : '' ?>">⚙ Settings</a>
        <a href="config.php?tab=queue"    class="tab-link<?= $tab === 'queue'    ? ' active' : '' ?>">☰ Kobo Queue</a>
    </div>

    <?php if ($tab === 'settings'): ?>
        <div class="config-card">

            <?php foreach ($errors as $e): ?>
                <div class="error-box">✗ <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
            <?php if ($success): ?>
                <div class="success-box">✓ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" action="config.php">
                <input type="hidden" name="save"  value="1">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="field-group">
                    <label class="field-label" for="READER_TITLE">Reader title</label>
                    <input class="field-input" type="text" id="READER_TITLE" name="READER_TITLE"
                           value="<?= $val('READER_TITLE', 'My Calibre Bookshelf') ?>"
                           placeholder="My Calibre Bookshelf">
                    <span class="field-hint">Displayed in the header and browser tab.</span>
                </div>

                <div class="field-group">
                    <label class="field-label" for="OPDS_BASE">OPDS base URL <span class="req">*</span></label>
                    <input class="field-input" type="url" id="OPDS_BASE" name="OPDS_BASE"
                           value="<?= $val('OPDS_BASE') ?>"
                           placeholder="https://nextcloud.example.com/index.php/apps/calibre_opds"
                           required>
                    <span class="field-hint">Root URL of the Calibre2OPDS app on your Nextcloud instance.</span>
                </div>

                <div class="field-group">
                    <label class="field-label" for="NC_USER">Nextcloud username <span class="req">*</span></label>
                    <input class="field-input" type="text" id="NC_USER" name="NC_USER"
                           value="<?= $val('NC_USER') ?>"
                           placeholder="your_nc_username"
                           autocomplete="username" required>
                </div>

                <div class="field-group">
                    <label class="field-label" for="NC_PASS">
                        Nextcloud app password <span class="req">*</span>
                        <?php if ($configured): ?><span class="field-hint-inline">(leave blank to keep current)</span><?php endif; ?>
                    </label>
                    <input class="field-input" type="password" id="NC_PASS" name="NC_PASS"
                           placeholder="<?= $configured ? '••••••••••••••••' : 'xxxx-xxxx-xxxx-xxxx-xxxx' ?>"
                           autocomplete="new-password">
                    <span class="field-hint">Generate an app password in Nextcloud → Settings → Security. Never use your account password here.</span>
                </div>

                <div class="field-group">
                    <label class="field-label" for="API_KEY">Access key <span class="req">*</span></label>
                    <div class="field-row">
                        <input class="field-input" type="text" id="API_KEY" name="API_KEY"
                               value="<?= $val('API_KEY') ?>"
                               placeholder="A long random string — keep it secret"
                               required>
                        <button type="button" class="btn btn-secondary" onclick="genKey()">Generate</button>
                    </div>
                    <span class="field-hint">Protects access to the reader and Kobo API. Treat it like a password.</span>
                </div>

                <div class="field-group">
                    <label class="field-label" for="BOOKS_API_URL">This app's URL</label>
                    <input class="field-input" type="url" id="BOOKS_API_URL" name="BOOKS_API_URL"
                           value="<?= $val('BOOKS_API_URL') ?>"
                           placeholder="https://this-server.example.com">
                    <span class="field-hint">URL where this reader is installed. Used for Kobo send-to-device and the download script. Required for Kobo integration.</span>
                </div>

                <div class="btn-row" style="margin-top:1.5rem">
                    <button class="btn btn-primary" type="submit">Save configuration</button>
                    <?php if ($configured): ?>
                        <a class="btn btn-secondary" href="index.php">← Back to reader</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    <?php else: ?>
        <div class="config-card">
            <div class="config-section-title">Kobo Queue</div>
            <p class="field-hint" style="margin-bottom:1rem">
                Books queued for your Kobo device. Your Kobo script polls
                <code><?= htmlspecialchars(rtrim($val('BOOKS_API_URL'), '/') . '/api.php') ?>?key=…&amp;action=list</code>
                and downloads new entries automatically.
            </p>

            <?php $queue = \CalibreOpds\Queue::load(); ?>

            <?php if (empty($queue)): ?>
                <p class="empty" style="padding:2rem 0">Queue is empty — send books from the reader using ⇢ Kobo</p>
            <?php else: ?>
                <div class="queue-toolbar">
                    <span><?= count($queue) ?> book<?= count($queue) !== 1 ? 's' : '' ?> in queue</span>
                    <button class="btn btn-secondary" id="clearAll">Clear all</button>
                </div>
                <ul class="queue-list" id="queueList">
                    <?php foreach ($queue as $i => $item): ?>
                        <li class="queue-item" data-index="<?= $i ?>">
                            <span class="fmt-badge"><?= htmlspecialchars(strtoupper($item['ext'] ?? '?')) ?></span>
                            <span class="queue-title"><?= htmlspecialchars($item['title'] ?? $item['file'] ?? '') ?></span>
                            <span class="queue-author"><?= htmlspecialchars($item['author'] ?? '') ?></span>
                            <span class="queue-date"><?= htmlspecialchars(substr($item['added'] ?? '', 0, 10)) ?></span>
                            
                            <?php if (isset($item['synced_status'])): ?>
                                <?php if ($item['synced_status'] === 'success'): ?>
                                    <span class="status-badge success">✓ Synced <?= htmlspecialchars(substr($item['synced'] ?? '', 0, 10)) ?></span>
                                <?php elseif ($item['synced_status'] === 'failed'): ?>
                                    <span class="status-badge failed">✗ Failed</span>
                                <?php else: ?>
                                    <span class="status-badge pending">⏳ Pending</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="status-badge pending">⏳ Pending</span>
                            <?php endif; ?>
                            
                            <button class="btn btn-secondary remove-btn" data-index="<?= $i ?>" title="Remove">✕</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<footer>
    <div class="wrap footer-inner">
        <span>Calibre OPDS Reader</span>
        <a href="https://github.com/oldnomad/calibre_opds" target="_blank" rel="noopener">Calibre2OPDS ↗</a>
    </div>
</footer>

<div id="toast"></div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function toast(msg, isErr = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show' + (isErr ? ' err' : '');
    clearTimeout(t._tid);
    t._tid = setTimeout(() => t.className = '', 3000);
}

function genKey() {
    const arr = new Uint8Array(24);
    crypto.getRandomValues(arr);
    document.getElementById('API_KEY').value =
        btoa(String.fromCharCode(...arr)).replace(/[+/=]/g, c => ({'+':'-','/':'_','=':''})[c]);
}

async function queueAction(action, index = null) {
    const body = new URLSearchParams({ _csrf: CSRF, queue_action: action });
    if (index !== null) body.set('index', index);
    const res  = await fetch('config.php?tab=queue', { method: 'POST', body });
    return res.json();
}

document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const idx  = btn.dataset.index;
        const json = await queueAction('remove', idx);
        if (json.success) {
            btn.closest('li').remove();
            const list = document.getElementById('queueList');
            if (list && !list.querySelector('li')) {
                list.innerHTML = '<li class="empty" style="padding:2rem 0;text-align:center">Queue is empty</li>';
            }
            toast('✓ Removed from queue');
        }
    });
});

const clearBtn = document.getElementById('clearAll');
if (clearBtn) {
    clearBtn.addEventListener('click', async () => {
        if (!confirm('Clear all queued books?')) return;
        const json = await queueAction('clear');
        if (json.success) {
            location.reload();
        }
    });
}
</script>
</body>
</html>