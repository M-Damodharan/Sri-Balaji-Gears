<?php
/**
 * Sri Balaji Gears - Admin Enquiry Management Dashboard
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/auth.php';

requireAdminAuth();

$pdo = getDbConnection();
$csrfToken = generateCsrfToken();
$notification = '';

// 1. Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sri_balaji_gears_enquiries_' . date('Y-m-d_His') . '.csv"');
    
    // Excel UTF-8 BOM
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'ID', 'Reference ID', 'Date & Time', 'Contact Person', 'Company / OEM',
        'Email', 'Phone', 'Requirement Category', 'Custom Specification',
        'Estimated Volume', 'Component Specifications', 'Attachment Name',
        'Attachment Size (Bytes)', 'Status', 'IP Address'
    ]);

    $stmt = $pdo->query("SELECT * FROM enquiries ORDER BY id DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['reference_id'],
            $row['created_at'],
            $row['customer_name'],
            $row['company_name'],
            $row['email'],
            $row['phone'],
            $row['requirement_category'],
            $row['custom_specification'] ?? '',
            $row['volume_estimate'] ?? '',
            $row['message'],
            $row['attachment_name'] ?? 'None',
            $row['attachment_size'] ?? 0,
            $row['status'] ?? 'New',
            $row['ip_address'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// 2. Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $enquiryId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $newStatus = trim($_POST['status'] ?? '');
        $allowedStatuses = ['New', 'In Review', 'Contacted', 'Closed'];
        if ($enquiryId && in_array($newStatus, $allowedStatuses)) {
            $update = $pdo->prepare("UPDATE enquiries SET status = :status WHERE id = :id");
            $update->execute([':status' => $newStatus, ':id' => $enquiryId]);
            $notification = "Status updated to {$newStatus} for RFQ #{$enquiryId}.";
        }
    }
}

// 3. Filters & Search Query
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$sql = "SELECT * FROM enquiries WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (reference_id LIKE :s OR customer_name LIKE :s OR company_name LIKE :s OR email LIKE :s OR phone LIKE :s OR message LIKE :s)";
    $params[':s'] = "%{$search}%";
}

if (!empty($statusFilter)) {
    $sql .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

if (!empty($categoryFilter)) {
    $sql .= " AND requirement_category = :cat";
    $params[':cat'] = $categoryFilter;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$enquiries = $stmt->fetchAll();

// 4. Metrics
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM enquiries")->fetchColumn();
$newCount = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status = 'New'")->fetchColumn();
$inReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status = 'In Review'")->fetchColumn();
$attachmentCount = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE attachment_name IS NOT NULL AND attachment_name != ''")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Enquiry Management Desk | Sri Balaji Gears Admin</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/sbg-mark.svg">
  <link rel="stylesheet" href="../assets/css/style.css?v=17">
  <style>
    body { background-color: var(--bg-dark); }
    .admin-navbar {
      background: var(--bg-primary);
      border-bottom: 1px solid var(--border-medium);
      padding: 14px 0;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .admin-navbar .container {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .admin-nav-right {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin: 28px 0;
    }
    .metric-card {
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-sm);
      padding: 20px;
    }
    .metric-card strong {
      display: block;
      font-size: 2rem;
      font-family: var(--font-display);
      color: var(--gold-400);
      line-height: 1;
      margin-bottom: 4px;
    }
    .metric-card span {
      font-size: 0.82rem;
      color: var(--ink-secondary);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .filter-bar {
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-sm);
      padding: 16px;
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
      margin-bottom: 24px;
    }
    .filter-bar input, .filter-bar select {
      flex: 1;
      min-width: 180px;
      padding: 8px 12px;
      background: var(--bg-dark);
      border: 1px solid var(--border-medium);
      color: #ffffff;
      border-radius: 6px;
      font-size: 0.88rem;
    }
    .table-responsive {
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      overflow-x: auto;
      margin-bottom: 40px;
    }
    table.data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.88rem;
    }
    table.data-table th {
      background: rgba(255, 255, 255, 0.03);
      padding: 14px 16px;
      text-align: left;
      font-family: var(--font-mono);
      font-size: 0.76rem;
      color: var(--gold-300);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 1px solid var(--border-medium);
    }
    table.data-table td {
      padding: 14px 16px;
      border-bottom: 1px solid var(--border-subtle);
      vertical-align: top;
      color: var(--ink-secondary);
    }
    table.data-table tr:hover td {
      background: rgba(255, 255, 255, 0.02);
    }
    .badge-status {
      display: inline-block;
      padding: 4px 10px;
      border-radius: var(--radius-pill);
      font-size: 0.72rem;
      font-weight: 700;
      font-family: var(--font-mono);
      text-transform: uppercase;
    }
    .badge-status.New { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4); }
    .badge-status.InReview { background: rgba(239, 173, 45, 0.15); color: #ffd36d; border: 1px solid rgba(239, 173, 45, 0.4); }
    .badge-status.Contacted { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
    .badge-status.Closed { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); }
    .att-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid var(--border-gold);
      border-radius: 4px;
      color: var(--gold-300);
      font-size: 0.76rem;
      text-decoration: none;
    }
    .att-pill:hover { background: rgba(239, 173, 45, 0.2); }
  </style>
</head>
<body>
  <!-- Admin Navbar -->
  <header class="admin-navbar">
    <div class="container">
      <div style="display:flex; align-items:center; gap:12px;">
        <img src="../assets/img/sbg-mark.svg" alt="SBG" width="36" height="36">
        <div>
          <span style="font-weight:800; font-family:var(--font-display); font-size:1.15rem; color:#ffffff;">SRI BALAJI GEARS</span>
          <span style="font-size:0.75rem; color:var(--gold-400); font-family:var(--font-mono); margin-left:8px;">ERP ADMIN DESK</span>
        </div>
      </div>
      <div class="admin-nav-right">
        <a href="?action=export_csv" class="button" style="padding:6px 14px; font-size:0.78rem;">
          📥 EXPORT CSV / EXCEL
        </a>
        <a href="../index.html" target="_blank" class="button ghost" style="padding:6px 14px; font-size:0.78rem;">
          🌐 VIEW LIVE SITE ↗
        </a>
        <a href="logout.php" class="button ghost" style="padding:6px 14px; font-size:0.78rem; border-color:rgba(239,68,68,0.4); color:#fca5a5;">
          LOGOUT
        </a>
      </div>
    </div>
  </header>

  <main class="container" style="padding-top: 24px; padding-bottom: 40px;">
    <?php if (!empty($notification)): ?>
      <div style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.4); color:#34d399; padding:12px 18px; border-radius:6px; margin-bottom:20px; font-size:0.88rem;">
        ✓ <?= htmlspecialchars($notification) ?>
      </div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div class="metric-grid">
      <div class="metric-card">
        <strong><?= $totalCount ?></strong>
        <span>Total Received Enquiries</span>
      </div>
      <div class="metric-card">
        <strong style="color:var(--accent-cyan);"><?= $newCount ?></strong>
        <span>New / Unread Enquiries</span>
      </div>
      <div class="metric-card">
        <strong style="color:#ffd36d;"><?= $inReviewCount ?></strong>
        <span>Under Engineering Review</span>
      </div>
      <div class="metric-card">
        <strong style="color:var(--accent-emerald);"><?= $attachmentCount ?></strong>
        <span>With Technical Drawings</span>
      </div>
    </div>

    <!-- Filters & Search -->
    <form method="GET" class="filter-bar">
      <input type="text" name="search" placeholder="Search by name, company, email, phone, reference..." value="<?= htmlspecialchars($search) ?>">
      
      <select name="status">
        <option value="">All Statuses</option>
        <option value="New" <?= $statusFilter === 'New' ? 'selected' : '' ?>>New</option>
        <option value="In Review" <?= $statusFilter === 'In Review' ? 'selected' : '' ?>>In Review</option>
        <option value="Contacted" <?= $statusFilter === 'Contacted' ? 'selected' : '' ?>>Contacted</option>
        <option value="Closed" <?= $statusFilter === 'Closed' ? 'selected' : '' ?>>Closed</option>
      </select>

      <select name="category">
        <option value="">All Requirements</option>
        <option value="3-Seat Bus Assembly Frame Welding" <?= $categoryFilter === '3-Seat Bus Assembly Frame Welding' ? 'selected' : '' ?>>3-Seat Bus Frame</option>
        <option value="2-Seat Luxury Passenger Frame" <?= $categoryFilter === '2-Seat Luxury Passenger Frame' ? 'selected' : '' ?>>2-Seat Luxury Frame</option>
        <option value="Auto Body Frame Chassis Welding" <?= $categoryFilter === 'Auto Body Frame Chassis Welding' ? 'selected' : '' ?>>Auto Body & Chassis</option>
        <option value="Heavy Pressing & Stamping (50T-300T)" <?= $categoryFilter === 'Heavy Pressing & Stamping (50T-300T)' ? 'selected' : '' ?>>Heavy Pressing & Stamping (50T-300T)</option>
        <option value="Custom Welding & Jigs" <?= $categoryFilter === 'Custom Welding & Jigs' ? 'selected' : '' ?>>Custom Welding & Jigs</option>
        <option value="Plant Audit & Vendor Onboarding" <?= $categoryFilter === 'Plant Audit & Vendor Onboarding' ? 'selected' : '' ?>>Plant Audit</option>
        <option value="Others" <?= $categoryFilter === 'Others' ? 'selected' : '' ?>>Others</option>
      </select>

      <button type="submit" class="button" style="padding:8px 16px;">FILTER</button>
      <?php if (!empty($search) || !empty($statusFilter) || !empty($categoryFilter)): ?>
        <a href="index.php" class="button ghost" style="padding:8px 16px;">RESET</a>
      <?php endif; ?>
    </form>

    <!-- Enquiries Table -->
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Ref ID &amp; Date</th>
            <th>Customer / OEM</th>
            <th>Contact Details</th>
            <th>Requirement &amp; Est. Vol</th>
            <th>Component Specs / Notes</th>
            <th>Attachment</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($enquiries)): ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:32px; color:var(--ink-muted);">
                No customer enquiries found matching your filter criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($enquiries as $row): ?>
              <tr>
                <td>
                  <strong style="color:#ffffff; font-family:var(--font-mono); font-size:0.8rem;"><?= htmlspecialchars($row['reference_id']) ?></strong><br>
                  <small style="color:var(--ink-muted); font-size:0.75rem;"><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></small>
                </td>
                <td>
                  <strong style="color:#ffffff;"><?= htmlspecialchars($row['customer_name']) ?></strong><br>
                  <span style="color:var(--gold-400); font-size:0.82rem;"><?= htmlspecialchars($row['company_name']) ?></span>
                </td>
                <td>
                  <a href="mailto:<?= htmlspecialchars($row['email']) ?>" style="color:var(--accent-cyan); text-decoration:none; display:block;"><?= htmlspecialchars($row['email']) ?></a>
                  <a href="tel:<?= htmlspecialchars($row['phone']) ?>" style="color:var(--ink-muted); text-decoration:none; font-size:0.8rem;"><?= htmlspecialchars($row['phone']) ?></a>
                </td>
                <td>
                  <span style="color:#ffffff; font-weight:600;"><?= htmlspecialchars($row['requirement_category']) ?></span>
                  <?php if (!empty($row['custom_specification'])): ?>
                    <br><small style="color:var(--gold-300); font-style:italic;">(<?= htmlspecialchars($row['custom_specification']) ?>)</small>
                  <?php endif; ?>
                  <br><small style="color:var(--ink-muted);">Vol: <?= htmlspecialchars($row['volume_estimate'] ?? 'N/A') ?></small>
                </td>
                <td style="max-width:280px;">
                  <div style="max-height:80px; overflow-y:auto; line-height:1.4; font-size:0.82rem; background:rgba(0,0,0,0.2); padding:6px 8px; border-radius:4px;">
                    <?= nl2br(htmlspecialchars($row['message'])) ?>
                  </div>
                </td>
                <td>
                  <?php if (!empty($row['attachment_name']) && !empty($row['attachment_path'])): ?>
                    <a href="../api/download.php?id=<?= $row['id'] ?>" class="att-pill" title="Download <?= htmlspecialchars($row['attachment_name']) ?>">
                      📎 <?= htmlspecialchars(substr($row['attachment_name'], 0, 18)) ?><?= strlen($row['attachment_name']) > 18 ? '...' : '' ?>
                      (<?= round(($row['attachment_size'] ?? 0) / 1024, 1) ?>KB)
                    </a>
                  <?php else: ?>
                    <span style="color:var(--ink-dim); font-size:0.78rem;">None</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge-status <?= str_replace(' ', '', $row['status'] ?? 'New') ?>">
                    <?= htmlspecialchars($row['status'] ?? 'New') ?>
                  </span>
                </td>
                <td>
                  <form method="POST" action="index.php" style="display:inline-block; margin-bottom:4px;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <select name="status" onchange="this.form.submit()" style="padding:4px 6px; font-size:0.75rem; background:var(--bg-dark); color:#ffffff; border:1px solid var(--border-medium); border-radius:4px;">
                      <option value="New" <?= ($row['status'] ?? '') === 'New' ? 'selected' : '' ?>>New</option>
                      <option value="In Review" <?= ($row['status'] ?? '') === 'In Review' ? 'selected' : '' ?>>In Review</option>
                      <option value="Contacted" <?= ($row['status'] ?? '') === 'Contacted' ? 'selected' : '' ?>>Contacted</option>
                      <option value="Closed" <?= ($row['status'] ?? '') === 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                  </form>
                  <a href="mailto:<?= htmlspecialchars($row['email']) ?>?subject=RE: RFQ <?= htmlspecialchars($row['reference_id']) ?> - Sri Balaji Gears" class="button ghost" style="padding:3px 8px; font-size:0.72rem; display:block; text-align:center; margin-top:4px;">
                    ✉ Reply
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
