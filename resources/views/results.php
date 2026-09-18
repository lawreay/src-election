<?php
$pageTitle = 'Election results';
include __DIR__ . '/partials/header.php';
$election = $results['election'];
?>
<section class="page-heading"><div><p class="eyebrow">Official count</p><h1><?= htmlspecialchars($election['name'] ?? 'Election results', ENT_QUOTES, 'UTF-8') ?></h1><p class="muted">Candidate totals are released only after the election is closed.</p></div><?php if ($election): ?><span class="status status-<?= strtolower($election['status']) ?>"><?= htmlspecialchars($election['status'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></section>
<?php if (!$election): ?>
    <div class="panel"><h2>No election configured</h2><p class="muted">Create an election before viewing results.</p></div>
<?php elseif (!$results['visible']): ?>
    <div class="panel results-hidden"><div class="results-lock">Results hidden</div><h2>The count is protected while voting is in progress.</h2><p class="muted">Close the election before candidate totals become available.</p></div>
<?php else: ?>
    <?php foreach ($results['positions'] as $position): ?><section class="panel results-position"><div class="panel-heading"><h2><?= htmlspecialchars($position['name'], ENT_QUOTES, 'UTF-8') ?></h2><span class="muted">Votes</span></div><table><thead><tr><th>Candidate</th><th>Votes</th></tr></thead><tbody><?php foreach ($position['candidates'] as $candidate): ?><tr><td><?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name'], ENT_QUOTES, 'UTF-8') ?></td><td><strong><?= (int) $candidate['votes'] ?></strong></td></tr><?php endforeach; ?></tbody></table></section><?php endforeach; ?>
    <section class="panel reconciliation"><div class="panel-heading"><h2>Election reconciliation</h2><span class="status <?= $results['reconciliation']['matches'] ? 'status-open' : 'status-draft' ?>"><?= $results['reconciliation']['matches'] ? 'MATCH' : 'ERROR' ?></span></div><div class="detail-list"><div><dt>Marked as voted</dt><dd><?= (int) $results['reconciliation']['marked_voters'] ?></dd></div><div><dt>Submitted ballots</dt><dd><?= (int) $results['reconciliation']['submitted_ballots'] ?></dd></div></div></section>
<?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>