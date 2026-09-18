<?php $pageTitle = 'Import students'; include __DIR__ . '/partials/header.php'; ?>
<section class="page-heading"><div><p class="eyebrow">Voter register</p><h1>Import students</h1><p class="muted">Upload a CSV with first_name, last_name, programme. Student IDs are generated automatically.</p></div></section>
<div class="panel narrow-panel"><form method="post" action="/students/import" enctype="multipart/form-data" class="stack-form"><label>CSV file<input type="file" name="csv_file" accept=".csv,text/csv" required></label><div class="quick-actions"><button class="button button-primary" type="submit">Import valid records</button><a class="button button-secondary" href="/students">Cancel</a></div></form></div>
<?php include __DIR__ . '/partials/footer.php'; ?>
