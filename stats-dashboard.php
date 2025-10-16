<?php
// stats-dashboard.php
$file = __DIR__ . '/stats.log';
$data = [
    'total_visits' => 0,
    'daily' => [],
    'referrers' => [],
    'pages' => []
];

if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $visits = array_map(fn($line) => json_decode($line, true), $lines);

    // Total visits
    $data['total_visits'] = count($visits);

    // Daily visits
    foreach ($visits as $v) {
        $day = substr($v['time'], 0, 10); // "YYYY-MM-DD"
        $data['daily'][$day] = ($data['daily'][$day] ?? 0) + 1;
    }

    // Referrers
    foreach ($visits as $v) {
        $ref = $v['referrer'] ?? 'Direct';
        $data['referrers'][$ref] = ($data['referrers'][$ref] ?? 0) + 1;
    }

    // Page views
    foreach ($visits as $v) {
        $page = $v['page'] ?? 'UNKNOWN';
        $data['pages'][$page] = ($data['pages'][$page] ?? 0) + 1;
    }
}

function format_table($title, $arr) {
    $html = "<h2>$title</h2><table border='1' cellpadding='6' cellspacing='0'>";
    $html .= "<tr><th>Key</th><th>Count</th></tr>";
    foreach ($arr as $k => $v) {
        $html .= "<tr><td>" . htmlspecialchars($k) . "</td><td>$v</td></tr>";
    }
    $html .= "</table>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DSL Stats Dashboard</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; padding: 20px; }
        h1, h2 { color: #4CAF50; }
        table { border-collapse: collapse; margin-bottom: 30px; width: 100%; max-width: 600px; }
        th, td { border: 1px solid #666; text-align: left; }
        th { background: #222; }
        td { background: #111; }
    </style>
</head>
<body>
<h1>Digital SEX Life Stats</h1>
<p>Total visits: <?php echo $data['total_visits']; ?></p>

<?php
if (!empty($data['daily'])) echo format_table('Daily Visits', $data['daily']);
if (!empty($data['referrers'])) echo format_table('Referrers', $data['referrers']);
if (!empty($data['pages'])) echo format_table('Page Views', $data['pages']);
?>
</body>
</html>
