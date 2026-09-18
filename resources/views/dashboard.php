<?php
$pageTitle = 'Dashboard';
$election = $electionService->getCurrentElection();
$progress = $stats['total_students'] > 0 ? (int) round(($stats['voted_students'] / $stats['total_students']) * 100) : 0;
include __DIR__ . '/partials/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">Election control room</p><h1><?= htmlspecialchars($election['name'] ?? 'No election configured', ENT_QUOTES, 'UTF-8') ?></h1></div>
    <span class="status status-<?= strtolower($election['status'] ?? 'draft') ?>"><?= htmlspecialchars($election['status'] ?? 'DRAFT', ENT_QUOTES, 'UTF-8') ?></span>
</section>
<section class="metric-grid">
    <article class="metric"><span>Registered voters</span><strong><?= $stats['total_students'] ?></strong><small>Eligible students</small></article>
    <article class="metric"><span>Ballots cast</span><strong><?= $stats['voted_students'] ?></strong><small>Voter records marked complete</small></article>
    <article class="metric"><span>Remaining</span><strong><?= $stats['remaining_students'] ?></strong><small>Still eligible to vote</small></article>
    <article class="metric"><span>Progress</span><strong><?= $progress ?>%</strong><small><?= $stats['positions'] ?> positions · <?= $stats['candidates'] ?> candidates</small></article>
</section>
<section class="dashboard-grid">
    <article class="panel progress-panel"><div class="panel-heading"><h2>Voting progress</h2><span><?= $progress ?>%</span></div><div class="progress-track"><div style="width: <?= $progress ?>%"></div></div><p class="muted">Candidate totals stay hidden until the election is closed.</p></article>
    <article class="panel"><div class="panel-heading"><h2>Election setup</h2><a href="/election">Open</a></div><dl class="detail-list"><div><dt>Status</dt><dd><?= htmlspecialchars($election['status'] ?? 'DRAFT', ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Positions</dt><dd><?= $stats['positions'] ?></dd></div><div><dt>Anonymous ballots</dt><dd><?= $stats['ballots'] ?></dd></div></dl></article>
</section>
<section class="quick-actions"><a class="button button-primary" href="/voting">Open voting station</a><a class="button button-secondary" href="/results">View results</a><a class="button button-secondary" href="/students/import">Import students</a><a class="button button-secondary" href="/positions">Manage positions</a></section>
<?php include __DIR__ . '/partials/footer.php'; ?>
