<?php
// AT-HM Checker — Search Term Report
// Password-protected. Change the password below before uploading.

define('REPORT_PASSWORD', 'sahct2024');  // <-- change this

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === REPORT_PASSWORD) {
        $_SESSION['athm_auth'] = true;
    } else {
        $error = 'Incorrect password.';
    }
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$authed = !empty($_SESSION['athm_auth']);

// --- Load and tally data ---
$totals      = [];  // term => [matched, unmatched]
$totalSearches = 0;

if ($authed) {
    $logFile = __DIR__ . '/searches.json';
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry || !isset($entry['t'])) continue;
            $term    = $entry['t'];
            $matched = !empty($entry['m']);
            if (!isset($totals[$term])) $totals[$term] = [0, 0];
            if ($matched) $totals[$term][0]++;
            else          $totals[$term][1]++;
            $totalSearches++;
        }
    }
    // Sort by total volume desc
    uasort($totals, fn($a, $b) => ($b[0]+$b[1]) - ($a[0]+$a[1]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AT-HM Checker — Search Report</title>
<style>
  :root {
    --blue: #1963A2;
    --blue-light: #e8f0f8;
    --red: #c0392b;
    --red-light: #fdecea;
    --green: #1a7a4a;
    --green-light: #e8f5ee;
    --grey: #f4f6f8;
    --text: #1a1a1a;
    --muted: #666;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
         background: var(--grey); color: var(--text); min-height: 100vh; }
  .header { background: var(--blue); color: white; padding: 18px 24px;
            display: flex; justify-content: space-between; align-items: center; }
  .header h1 { font-size: 1.1rem; font-weight: 600; }
  .header form { display: inline; }
  .header button { background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4);
                   color: white; padding: 6px 14px; border-radius: 6px; cursor: pointer;
                   font-size: 0.85rem; }
  .login-wrap { display: flex; justify-content: center; align-items: center;
                min-height: calc(100vh - 60px); }
  .login-box { background: white; border-radius: 12px; padding: 40px 36px;
               width: 100%; max-width: 360px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
  .login-box h2 { color: var(--blue); margin-bottom: 24px; font-size: 1.15rem; }
  .login-box input { width: 100%; padding: 10px 14px; border: 1.5px solid #ddd;
                     border-radius: 8px; font-size: 1rem; margin-bottom: 14px; }
  .login-box button { width: 100%; background: var(--blue); color: white; border: none;
                      padding: 11px; border-radius: 8px; font-size: 1rem; cursor: pointer; }
  .error { color: var(--red); font-size: 0.88rem; margin-bottom: 12px; }
  .main { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
  .stats-row { display: flex; gap: 14px; margin-bottom: 24px; flex-wrap: wrap; }
  .stat-card { background: white; border-radius: 10px; padding: 18px 22px; flex: 1;
               min-width: 140px; box-shadow: 0 1px 6px rgba(0,0,0,0.06); }
  .stat-card .num { font-size: 2rem; font-weight: 700; color: var(--blue); line-height: 1; }
  .stat-card .lbl { font-size: 0.82rem; color: var(--muted); margin-top: 5px; }
  .section-title { font-size: 0.95rem; font-weight: 600; color: var(--muted);
                   text-transform: uppercase; letter-spacing: 0.05em;
                   margin: 28px 0 12px; }
  table { width: 100%; border-collapse: collapse; background: white;
          border-radius: 10px; overflow: hidden;
          box-shadow: 0 1px 6px rgba(0,0,0,0.06); }
  th { background: var(--blue); color: white; text-align: left;
       padding: 10px 14px; font-size: 0.85rem; font-weight: 600; }
  td { padding: 9px 14px; font-size: 0.92rem; border-bottom: 1px solid #f0f0f0; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: var(--blue-light); }
  .badge { display: inline-block; padding: 2px 9px; border-radius: 12px;
           font-size: 0.78rem; font-weight: 600; }
  .badge-match { background: var(--green-light); color: var(--green); }
  .badge-no { background: var(--red-light); color: var(--red); }
  .bar-wrap { display: flex; align-items: center; gap: 8px; }
  .bar { height: 8px; border-radius: 4px; background: var(--blue); min-width: 2px; }
  .bar-no { background: var(--red); }
  .empty { text-align: center; padding: 60px 20px; color: var(--muted); }
  @media (max-width: 500px) { .stats-row { flex-direction: column; } }
</style>
</head>
<body>

<div class="header">
  <h1>AT-HM Checker — Search Report</h1>
  <?php if ($authed): ?>
  <form method="post"><button type="submit" name="logout" value="1">Sign out</button></form>
  <?php endif; ?>
</div>

<?php if (!$authed): ?>
<div class="login-wrap">
  <div class="login-box">
    <h2>Search Report</h2>
    <?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post">
      <input type="password" name="password" placeholder="Password" autofocus>
      <button type="submit">Sign in</button>
    </form>
  </div>
</div>

<?php else: ?>
<div class="main">

<?php if (empty($totals)): ?>
  <div class="empty">No searches recorded yet. Once therapists start using the tool, results will appear here.</div>
<?php else:
  $uniqueTerms  = count($totals);
  $matchedTerms = array_filter($totals, fn($v) => $v[0] > 0);
  $noMatchTerms = array_filter($totals, fn($v) => $v[1] > 0 && $v[0] === 0);
  $maxVol = max(array_map(fn($v) => $v[0]+$v[1], $totals));
?>

<div class="stats-row">
  <div class="stat-card">
    <div class="num"><?= number_format($totalSearches) ?></div>
    <div class="lbl">Total searches</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= number_format($uniqueTerms) ?></div>
    <div class="lbl">Unique terms</div>
  </div>
  <div class="stat-card">
    <div class="num" style="color:var(--red)"><?= number_format(count($noMatchTerms)) ?></div>
    <div class="lbl">Terms with no match</div>
  </div>
</div>

<?php if (!empty($noMatchTerms)): ?>
<div class="section-title">⚠️ No match found — potential synonym gaps</div>
<table>
  <tr><th>Search term</th><th>Searches</th><th>Volume</th></tr>
  <?php foreach ($noMatchTerms as $term => [$m, $u]):
    $total = $m + $u;
    $pct = round($total / $maxVol * 100);
  ?>
  <tr>
    <td><?= htmlspecialchars($term) ?></td>
    <td><span class="badge badge-no"><?= $total ?> × no match</span></td>
    <td><div class="bar-wrap"><div class="bar bar-no" style="width:<?= max($pct,2) ?>px"></div><span style="font-size:0.8rem;color:var(--muted)"><?= $total ?></span></div></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<div class="section-title">All search terms</div>
<table>
  <tr><th>Search term</th><th>Matched</th><th>No match</th><th>Volume</th></tr>
  <?php foreach ($totals as $term => [$m, $u]):
    $total = $m + $u;
    $pct = round($total / $maxVol * 100);
  ?>
  <tr>
    <td><?= htmlspecialchars($term) ?></td>
    <td><?php if ($m > 0): ?><span class="badge badge-match"><?= $m ?></span><?php else: ?>—<?php endif; ?></td>
    <td><?php if ($u > 0): ?><span class="badge badge-no"><?= $u ?></span><?php else: ?>—<?php endif; ?></td>
    <td><div class="bar-wrap"><div class="bar" style="width:<?= max($pct,2) ?>px"></div><span style="font-size:0.8rem;color:var(--muted)"><?= $total ?></span></div></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php endif; ?>
</div>
<?php endif; ?>
</body>
</html>
