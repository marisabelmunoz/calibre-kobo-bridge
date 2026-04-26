<?php

declare(strict_types=1);
use CalibreOpds\Config;
$siteTitle = $siteTitle ?? Config::get('READER_TITLE', 'Calibre Bookshelf');
$loginError = $loginError ?? '';
$return     = $return ?? 'index.php';
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
<div class="login-wrap">
    <div class="login-box">
        <div class="login-title"><?= htmlspecialchars($siteTitle) ?></div>
        <p class="login-sub">Enter your access key to continue</p>
        <?php if ($loginError): ?>
            <div class="error-box"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post" action="index.php">
            <input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>">
            <input class="login-input" type="password" name="api_key"
                   placeholder="Access key" autofocus autocomplete="current-password">
            <button class="login-btn" type="submit">Unlock →</button>
        </form>
        <p class="login-config-link"><a href="config.php">⚙ Setup / Configuration</a></p>
    </div>
</div>
</body>

<!-- with <3 by marisabel.nl -->
</html>
