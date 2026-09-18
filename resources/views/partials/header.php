<?php
$pageTitle = $pageTitle ?? 'SRC Election System';
$messages = $messages ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | SRC Election</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/dashboard">SRC<span>Vote</span></a>
    <?php if (!empty($_SESSION['admin_logged_in'])): ?>
        <nav class="nav-links" aria-label="Main navigation">
            <a href="/dashboard">Dashboard</a>
            <a href="/students">Students</a>
            <a href="/positions">Positions</a>
            <a href="/candidates">Candidates</a>
            <a href="/election">Election</a>
            <a class="nav-action" href="/voting">Voting station</a>
            <a href="/logout">Log out</a>
        </nav>
    <?php endif; ?>
</header>
<main class="page-shell">
    <?php if (!empty($messages['error'])): ?><div class="alert alert-error"><?= htmlspecialchars($messages['error'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if (!empty($messages['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($messages['success'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
