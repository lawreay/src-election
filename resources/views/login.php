<?php $pageTitle = 'Admin login'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | SRC Election</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="login-page">
<section class="login-panel">
    <p class="eyebrow">Technical College · Election Office</p>
    <h1>Run a calm, credible election.</h1>
    <p class="muted">Manage eligibility, protect the secret ballot, and reconcile results from one dependable station.</p>
    <?php if (!empty($messages['error'])): ?><div class="alert alert-error"><?= htmlspecialchars($messages['error'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="/login" class="stack-form">
        <label>Username<input type="text" name="username" autocomplete="username" required autofocus></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="button button-primary" type="submit">Sign in</button>
    </form>
    <p class="login-note">Local election station · Admin access only</p>
</section>
</body>
</html>
