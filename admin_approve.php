<?php
session_start();
include 'db.php';

// --- Simple access control ---
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
  header("Location: login.php");
  exit();
}

// --- Handle Approve / Reject actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
  $id = (int)$_POST['id'];
  $action = $_POST['action'];
  $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
  $other_reason = isset($_POST['other_reason']) ? trim($_POST['other_reason']) : '';

if ($action === 'approve') {
  $status = 'approved';
  $visibility = 'public';
} elseif ($action === 'decline' || $action === 'reject') {
  $status = 'declined';
  $visibility = 'private';
} elseif ($action === 'pending') {
  $status = 'pending';
  $visibility = 'private';
} else {
  header("Location: admin_approve.php?msg=invalid_action");
  exit();
}

  // Final reason (use "other" if selected)
  if ($reason === 'Other' && !empty($other_reason)) {
    $reason = $other_reason;
  }

  // --- Update deck ---
  $stmt = $conn->prepare("UPDATE decks SET status=?, visibility=? WHERE id=?");
  $stmt->bind_param("ssi", $status, $visibility, $id);
  $stmt->execute();
  $stmt->close();

  // --- Fetch deck info and owner ---
  $info = $conn->prepare("SELECT d.title, u.id AS user_id FROM decks d JOIN users u ON d.user_id = u.id WHERE d.id=?");
  $info->bind_param("i", $id);
  $info->execute();
  $info->bind_result($deck_title, $user_id);
  $info->fetch();
  $info->close();

  // --- Create message for notification ---
  if ($status === 'approved') {
    $msg = "Your deck '{$deck_title}' has been approved and is now public!";
  } else {
    $msg = "Your deck '{$deck_title}' has been declined by the admin.";
    if (!empty($reason)) $msg .= " Reason: " . $reason;
  }

  // --- Insert into notifications table ---
  $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
  $notif->bind_param("is", $user_id, $msg);
  $notif->execute();
  $notif->close();

  header("Location: admin_approve.php?msg=$status");
  exit();
}

// --- Fetch pending decks ---
$pending = $conn->query("
  SELECT d.id, d.title, d.topic, d.share_code, d.thumbnail, u.username, d.created_at 
  FROM decks d 
  JOIN users u ON d.user_id = u.id
  WHERE d.status='pending'
  ORDER BY d.created_at DESC
");

// --- Fetch all decks ---
$all_decks = $conn->query("
  SELECT d.id, d.title, d.topic, d.thumbnail, d.share_code, d.status, u.username, d.created_at
  FROM decks d
  JOIN users u ON d.user_id = u.id
    WHERE d.status != 'deleted'

  ORDER BY d.created_at DESC
");
$deleted_decks = $conn->query("
  SELECT d.id, d.title, d.topic, d.thumbnail, d.share_code, u.username, d.deleted_at
  FROM decks d
  JOIN users u ON d.user_id = u.id
  WHERE d.status='deleted'
  ORDER BY d.created_at DESC
");



// --- Fetch feedback data ---
$feedback = $conn->query("
  SELECT username, easy, useful, created_at
  FROM feedback
  ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — Flippix</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: slategray;
  min-height: 100vh;
  padding: 20px;
}

.admin-container {
  max-width: 1400px;
  margin: 0 auto;
}

/* Header */
.admin-header {
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  padding: 24px 32px;
  border-radius: 20px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
  margin-bottom: 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.admin-header h1 {
  font-size: 28px;
  font-weight: 700;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.admin-img {
  height: 30px;
  width: auto;
  margin-right: 10px;
}
.admin-title {
  display: flex;
  align-items: center;
}

.admin-badge {
  background: linear-gradient(135deg, #b31217 0%, #e52d27 100%);
  padding: 8px 20px;
  border-radius: 50px;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;

}
.admin-badge a {
  text-decoration: none;
  color: white;
}
.admin-badge:hover {
  transform: translateY(-2px);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
}


/* Alert Messages */
.alert {
  background: rgba(255, 255, 255, 0.98);
  padding: 16px 24px;
  border-radius: 16px;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.alert.success {
  border-left: 4px solid #10b981;
}

.alert.error {
  border-left: 4px solid #ef4444;
}

.alert-icon {
  font-size: 24px;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  background: rgba(255, 255, 255, 0.2);
  padding: 8px;
  border-radius: 16px;
  backdrop-filter: blur(10px);
}

.tab {
  flex: 1;
  padding: 14px 24px;
  cursor: pointer;
  font-weight: 600;
  font-size: 15px;
  color: rgba(255, 255, 255, 0.8);
  background: transparent;
  border: none;
  border-radius: 12px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  text-align: center;
}

.tab:hover {
  color: white;
  background: rgba(255, 255, 255, 0.1);
}

.tab.active {
  background: white;
  color: #667eea;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Content Cards */
.content-card {
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  padding: 32px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #64748b;
}

.empty-state-icon {
  font-size: 64px;
  margin-bottom: 16px;
  opacity: 0.5;
}

.empty-state h3 {
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 8px;
  color: #334155;
}

/* Table */
.table-container {
  overflow-x: auto;
  border-radius: 12px;
}

table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

thead {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

th {
  padding: 16px 20px;
  text-align: left;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: white;
  border: none;
}

th:first-child {
  border-top-left-radius: 12px;
}

th:last-child {
  border-top-right-radius: 12px;
}

tbody tr {
  border-bottom: 1px solid #e2e8f0;
  transition: all 0.2s ease;
}

tbody tr:hover {
  background: #f8fafc;
}

tbody tr:last-child {
  border-bottom: none;
}

td {
  padding: 16px 20px;
  font-size: 14px;
  color: #334155;
}

/* Buttons */

/* Clean, consistent actions column layout */
/* Fix alignment for the Actions column */
table {
  border-collapse: collapse;
  width: 100%;
}

th, td {
  padding: 12px;
  border-bottom: 1px solid #ddd;
  text-align: center;
  vertical-align: middle; /* ✅ keeps text and buttons centered vertically */
}

/* Center the header text too */
th:last-child,
td:last-child {
  text-align: center;
  vertical-align: middle;
}

/* Make action buttons sit inline and centered */
.action-buttons {
  display: flex;
  justify-content: center;
  align-items: center; /* ✅ vertically aligns buttons perfectly */
  gap: 10px;
}

/* Consistent button look */
.action-buttons .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 14px;
  font-size: 13px;
  border-radius: 8px;
  border: none;
  text-decoration: none;
  color: #fff;
  cursor: pointer;
  min-width: 70px;
  transition: 0.2s;
}

.btn-view {
  background: linear-gradient(135deg, #3b82f6, #1e40af);
}

.btn-delete {
  background: linear-gradient(135deg, #ef4444, #991b1b);
}

.btn-view:hover,
.btn-delete:hover {
  opacity: 0.85;
  transform: translateY(-2px);
}

.btn {
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 13px;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn:active {
  transform: translateY(0);
}

.btn-approve {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.btn-decline {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.btn-view {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
}

/* Badges */
.badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}

.badge-id {
  background: #f1f5f9;
  color: #64748b;
}

.rating-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 13px;
}

.rating-easy {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.rating-useful {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

/* Modal */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  animation: fadeIn 0.2s ease;
}

.modal-content {
  background: white;
  margin: 8% auto;
  padding: 0;
  width: 90%;
  max-width: 500px;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.modal-header {
  padding: 24px 32px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
}

.modal-body {
  padding: 24px 32px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-weight: 600;
  font-size: 14px;
  color: #334155;
  margin-bottom: 8px;
}

.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.2s ease;
}

.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group textarea {
  display: none;
  resize: vertical;
  min-height: 100px;
}

.modal-footer {
  padding: 20px 32px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  border-bottom-left-radius: 20px;
  border-bottom-right-radius: 20px;
}

.btn-cancel {
  background: white;
  color: #64748b;
  border: 2px solid #e2e8f0;
}

.btn-cancel:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.btn-submit {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

/* Responsive */
@media (max-width: 768px) {
  body {
    padding: 10px;
  }
  
  .admin-header {
    flex-direction: column;
    gap: 16px;
    text-align: center;
  }
  
  .tabs {
    flex-direction: column;
  }
  
  .content-card {
    padding: 20px;
  }
  
  .action-buttons {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
}
td img {
  transition: transform 0.2s ease;
}

td img:hover {
  transform: scale(1.05);
}
.alert {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

</style>
</head>
<body>

<div class="admin-container">
  <!-- Header -->
  <div class="admin-header">
    <div class="admin-title">
    <img class="admin-img" src="images/labers.png">
    <h1> Flippix Admin Dashboard</h1>
    </div>
    <div class="admin-badge">
      <a href="logout.php">
      <span>Log out</span>
      </a>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
  <?php
    $msg = $_GET['msg'];
    $alertType = 'success';
    $icon = '✅';
    $text = '';

    if ($msg === 'approved') {
      $text = 'Deck approved and published successfully!';
    } elseif ($msg === 'declined') {
      $text = 'Deck has been declined.';
      $alertType = 'error';
      $icon = '❌';
    } elseif ($msg === 'deleted') {
    $text = 'Deck has been deleted successfully.';
    $alertType = 'error'; // red tone for destructive actions
    $icon = '🗑';
    } elseif ($msg === 'invalid_action') {
      $text = 'Invalid action detected.';
      $alertType = 'error';
      $icon = '⚠️';
    }
  ?>
  <div class="alert <?= $alertType ?>">
    <span class="alert-icon"><?= $icon ?></span>
    <span><?= $text ?></span>
  </div>

  <script>
    // 🕒 Auto-hide alert after 3 seconds
    setTimeout(() => {
      const alert = document.querySelector('.alert');
      if (alert) {
        alert.style.transition = 'all 0.3s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
      }
    }, 3000);

    // 🚫 Remove ?msg=... from URL after showing
    const url = new URL(window.location);
    url.searchParams.delete('msg');
    window.history.replaceState({}, document.title, url.pathname);
  </script>
<?php endif; ?>


  <!-- Tabs -->
  <div class="tabs">
  <button class="tab active" data-tab="pending">Pending Decks</button>
  <button class="tab" data-tab="all">List of Decks</button>
  <button class="tab" data-tab="feedback">User Feedback</button>
  <button class="tab" data-tab="deleted">Archived</button>

</div>


  <!-- Pending Decks Tab -->
  <div id="pending" class="tab-content active">
    <div class="content-card">
      <?php if ($pending->num_rows === 0): ?>
        <div class="empty-state">
          <div class="empty-state-icon">📭</div>
          <h3>No Pending Decks</h3>
          <p>All decks have been reviewed!</p>
        </div>
      <?php else: ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Thumbnail</th> <!-- 👈 added -->
                <th>Title</th>
                <th>Topic</th>
                <th>Creator</th>
                <th>Share Code</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $pending->fetch_assoc()): ?>
              <tr>
                <tr>
  <td><span class="badge badge-id">#<?= htmlspecialchars($row['id']) ?></span></td>

  <td>
    <?php if (!empty($row['thumbnail'])): ?>
      <img src="<?= htmlspecialchars($row['thumbnail']) ?>" 
           alt="Thumbnail" 
           style="width: 80px; height: 80px; object-fit: cover; border-radius: 10px; border: 2px solid #e2e8f0;">
    <?php else: ?>
      <div style="width: 80px; height: 80px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 12px;">
        No Image
      </div>
    <?php endif; ?>
  </td>

  <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>

                <td><?= htmlspecialchars($row['topic']) ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><code><?= htmlspecialchars($row['share_code']) ?></code></td>
                <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                <td>
                  <div class="action-buttons">
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="id" value="<?= $row['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" class="btn btn-approve">✓ Approve</button>
                    </form>
                    <button type="button" class="btn btn-decline" onclick="openDeclineModal(<?= $row['id'] ?>)">✗ Reject</button>
                    <a href="admin_view_deck.php?id=<?= $row['id'] ?>" class="btn btn-view">View</a>
                  </div>

                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>


<!-- All Decks Tab -->
<div id="all" class="tab-content">
  <div class="content-card">

    <!-- Filter Bar -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
      <button class="btn filter-btn active" data-status="all">All</button>
      <button class="btn filter-btn" data-status="approved" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">Approved</button>
      <button class="btn filter-btn" data-status="pending" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">Pending</button>
      <button class="btn filter-btn" data-status="declined" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">Declined</button>
    </div>

    <?php if ($all_decks->num_rows === 0): ?>
      <div class="empty-state">
        <div class="empty-state-icon">📭</div>
        <h3>No Decks Found</h3>
        <p>There are currently no decks in the database.</p>
      </div>
    <?php else: ?>
      <div class="table-container">
        <table id="deckTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Thumbnail</th>
              <th>Title</th>
              <th>Topic</th>
              <th>Creator</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $all_decks->fetch_assoc()): ?>
            <tr data-status="<?= htmlspecialchars($row['status']) ?>">
              <td><span class="badge badge-id">#<?= htmlspecialchars($row['id']) ?></span></td>
              <td>
                <?php if (!empty($row['thumbnail'])): ?>
                  <img src="<?= htmlspecialchars($row['thumbnail']) ?>" 
                       alt="Thumbnail" 
                       style="width: 80px; height: 80px; object-fit: cover; border-radius: 10px; border: 2px solid #e2e8f0;">
                <?php else: ?>
                  <div style="width: 80px; height: 80px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 12px;">
                    No Image
                  </div>
                <?php endif; ?>
              </td>
              <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
              <td><?= htmlspecialchars($row['topic']) ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td>
                <?php 
                  $status_color = [
                    'approved' => '#10b981',
                    'pending' => '#f59e0b',
                    'declined' => '#ef4444'
                  ];
                ?>
                <span class="badge" style="background: <?= $status_color[$row['status']] ?? '#94a3b8' ?>; color: white;">
                  <?= ucfirst($row['status']) ?>
                </span>
              </td>
              <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
              <td>
  <div class="action-buttons">
    <a href="admin_view_deck.php?id=<?= $row['id'] ?>" class="btn btn-view">View</a>

    <form method="POST" action="admin_delete_deck.php" onsubmit="return confirm('Are you sure you want to delete this deck?');">
      <input type="hidden" name="id" value="<?= $row['id'] ?>">
      <button type="submit" class="btn btn-delete">🗑</button>
    </form>
  </div>
</td>




              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>



  <!-- Feedback Tab -->
  <div id="feedback" class="tab-content">
    <div class="content-card">
      <?php if ($feedback->num_rows === 0): ?>
        <div class="empty-state">
          <div class="empty-state-icon">💭</div>
          <h3>No Feedback Yet</h3>
          <p>User feedback will appear here.</p>
        </div>
      <?php else: ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>User</th>
                <th>Ease of Use</th>
                <th>Usefulness</th>
                <th>Submitted</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($f = $feedback->fetch_assoc()): ?>
              <tr>
                <td><strong><?= htmlspecialchars($f['username']) ?></strong></td>
                <td>
                  <span class="rating-badge rating-easy">
                    ⭐ <?= htmlspecialchars($f['easy']) ?>/5
                  </span>
                </td>
                <td>
                  <span class="rating-badge rating-useful">
                    💡 <?= htmlspecialchars($f['useful']) ?>/5
                  </span>
                </td>
                <td><?= date('M j, Y', strtotime($f['created_at'])) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<div id="deleted" class="tab-content">
  <div class="content-card">
    <?php if ($deleted_decks->num_rows === 0): ?>
      <div class="empty-state">
        <div class="empty-state-icon">🗑</div>
        <h3>No Deleted Decks</h3>
        <p>Deleted decks will appear here.</p>
      </div>
    <?php else: ?>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Thumbnail</th>
              <th>Title</th>
              <th>Topic</th>
              <th>Creator</th>
              <th>Share Code</th>
              <th>Deleted On</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $deleted_decks->fetch_assoc()): ?>
            <tr>
              <td>#<?= htmlspecialchars($row['id']) ?></td>
              <td>
                <?php if (!empty($row['thumbnail'])): ?>
                  <img src="<?= htmlspecialchars($row['thumbnail']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:10px;">
                <?php else: ?>
                  <div style="width:80px;height:80px;background:#f1f5f9;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;">No Image</div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['title']) ?></td>
              <td><?= htmlspecialchars($row['topic']) ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td><code><?= htmlspecialchars($row['share_code']) ?></code></td>
              <td><?= date('M j, Y', strtotime($row['deleted_at'])) ?></td>
              <td>
                <div class="action-buttons" style="display:flex;gap:5px;">
                  <!-- View button -->
                  <a href="admin_view_deck.php?id=<?= $row['id'] ?>" class="btn btn-view">View</a>

                  <!-- Restore button -->
                  <form method="POST" action="adminrestore_deck.php" style="display:inline;">
                    <input type="hidden" name="deck_id" value="<?= $row['id'] ?>">
                    <button type="submit" class="btn btn-restore" style="color: black;" onclick="return confirm('Are you sure you want to restore this deck?')">Restore</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>


<!-- Decline Modal -->
<div id="declineModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>🚫 Decline Deck Submission</h3>
    </div>
    <form id="declineForm" method="POST">
      <div class="modal-body">
        <input type="hidden" name="id" id="declineDeckId">
        <input type="hidden" name="action" value="decline">

        <div class="form-group">
          <label for="reasonSelect">Reason for declining:</label>
          <select name="reason" id="reasonSelect" required onchange="toggleOtherReason(this.value)">
            <option value="" disabled selected>-- Select a reason --</option>
            <option value="Inappropriate content">Inappropriate content</option>
            <option value="Low quality or incomplete">Low quality or incomplete</option>
            <option value="Duplicated deck">Duplicated deck</option>
            <option value="Incorrect or misleading information">Incorrect or misleading information</option>
            <option value="Violates community guidelines">Violates community guidelines</option>
            <option value="Other">Other (specify below)</option>
          </select>
        </div>

        <div class="form-group">
          <textarea name="other_reason" id="otherReason" placeholder="Please specify your reason..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-cancel" onclick="closeDeclineModal()">Cancel</button>
        <button type="submit" class="btn btn-submit">Decline Deck</button>
      </div>
    </form>
  </div>
</div>

<script>
// Tab switching
const tabs = document.querySelectorAll('.tab');
const contents = document.querySelectorAll('.tab-content');

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    tabs.forEach(t => t.classList.remove('active'));
    contents.forEach(c => c.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById(tab.dataset.tab).classList.add('active');
  });
});

// Modal functions
function openDeclineModal(deckId) {
  document.getElementById('declineDeckId').value = deckId;
  document.getElementById('declineModal').style.display = 'block';
}

function closeDeclineModal() {
  document.getElementById('declineModal').style.display = 'none';
  document.getElementById('declineForm').reset();
  document.getElementById('otherReason').style.display = 'none';
}

function toggleOtherReason(value) {
  const otherBox = document.getElementById('otherReason');
  otherBox.style.display = value === 'Other' ? 'block' : 'none';
  if (value !== 'Other') {
    otherBox.value = '';
  }
}

window.onclick = function(event) {
  const modal = document.getElementById('declineModal');
  if (event.target === modal) {
    closeDeclineModal();
  }
}
</script>

<script>
  // Deck Filter
const filterButtons = document.querySelectorAll('.filter-btn');
const deckRows = document.querySelectorAll('#deckTable tbody tr');

filterButtons.forEach(btn => {
  btn.addEventListener('click', () => {
    // activate button
    filterButtons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const status = btn.dataset.status;
    deckRows.forEach(row => {
      if (status === 'all' || row.dataset.status === status) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  });
});

</script>

</body>
</html>