<?php
session_start();
include 'db.php';

// Check admin access
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
  header("Location: login.php");
  exit();
}

if (!isset($_GET['id'])) {
  die("Deck ID missing.");
}

$deck_id = (int)$_GET['id'];

// Fetch deck info
$deck = $conn->query("SELECT d.title, d.topic, u.username, d.created_at
                      FROM decks d 
                      JOIN users u ON d.user_id = u.id 
                      WHERE d.id=$deck_id")->fetch_assoc();

// Fetch questions
$cards = $conn->query("SELECT question, answer, image FROM flashcards WHERE deck_id=$deck_id");
$card_count = $cards->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Deck — <?= htmlspecialchars($deck['title']) ?></title>
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

.container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Back Button */
.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.25);
  backdrop-filter: blur(10px);
  color: white;
  padding: 12px 20px;
  border-radius: 12px;
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 24px;
  transition: all 0.3s ease;
  border: 2px solid rgba(255, 255, 255, 0.2);
}

.back-button:hover {
  background: rgba(255, 255, 255, 0.35);
  transform: translateX(-4px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

/* Header Card */
.deck-header {
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  border-radius: 24px;
  padding: 32px;
  margin-bottom: 30px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
}

.deck-title {
  font-size: 32px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 20px;
  line-height: 1.3;
}

.deck-meta {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.meta-item {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 16px 20px;
  border-radius: 12px;
  border-left: 4px solid #667eea;
}

.meta-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #64748b;
  margin-bottom: 6px;
}

.meta-value {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
}

.card-count-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 8px 16px;
  border-radius: 50px;
  font-size: 14px;
  font-weight: 600;
}

/* Section Header */
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.section-title {
  font-size: 24px;
  font-weight: 700;
  color: white;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Cards Grid */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 24px;
}

.flashcard {
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.flashcard::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.flashcard:hover {
  transform: translateY(-8px);
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.15);
}

.card-number {
  position: absolute;
  top: 16px;
  right: 16px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.qa-section {
  margin-bottom: 20px;
}

.qa-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #64748b;
  margin-bottom: 10px;
  padding: 6px 12px;
  background: #f1f5f9;
  border-radius: 8px;
}

.qa-content {
  font-size: 15px;
  line-height: 1.7;
  color: #334155;
  padding: 12px 0;
}

.question-text {
  font-weight: 600;
  color: #1e293b;
}

.answer-text {
  color: #475569;
}

.divider {
  height: 1px;
  background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
  margin: 16px 0;
}

/* Image Container */
.image-container {
  margin-top: 16px;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-radius: 12px;
  padding: 12px;
  overflow: hidden;
}

.image-wrapper {
  position: relative;
  width: 100%;
  max-height: 240px;
  border-radius: 8px;
  overflow: hidden;
  background: white;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.image-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

/* Empty State */
.empty-state {
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  border-radius: 24px;
  padding: 60px 40px;
  text-align: center;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
}

.empty-icon {
  font-size: 72px;
  margin-bottom: 20px;
  opacity: 0.6;
}

.empty-title {
  font-size: 24px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 12px;
}

.empty-text {
  font-size: 16px;
  color: #64748b;
  line-height: 1.6;
}

/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.flashcard {
  animation: fadeIn 0.4s ease backwards;
}

.flashcard:nth-child(1) { animation-delay: 0.05s; }
.flashcard:nth-child(2) { animation-delay: 0.1s; }
.flashcard:nth-child(3) { animation-delay: 0.15s; }
.flashcard:nth-child(4) { animation-delay: 0.2s; }
.flashcard:nth-child(5) { animation-delay: 0.25s; }
.flashcard:nth-child(6) { animation-delay: 0.3s; }

/* Responsive */
@media (max-width: 768px) {
  body {
    padding: 12px;
  }

  .deck-header {
    padding: 24px;
  }

  .deck-title {
    font-size: 24px;
  }

  .deck-meta {
    grid-template-columns: 1fr;
  }

  .cards-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .flashcard {
    padding: 20px;
  }

  .section-title {
    font-size: 20px;
  }
}

/* Scrollbar Styling */
::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}

::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
}

::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}
</style>
</head>
<body>
<div class="container">

  <a href="admin_approve.php" class="back-button">
    <span>←</span>
    <span>Back to Dashboard</span>
  </a>

  <div class="deck-header">
    <h1 class="deck-title"><?= htmlspecialchars($deck['title']) ?></h1>
    <div class="deck-meta">
      <div class="meta-item">
        <div class="meta-label">📚 Topic</div>
        <div class="meta-value"><?= htmlspecialchars($deck['topic']) ?></div>
      </div>
      <div class="meta-item">
        <div class="meta-label">👤 Created By</div>
        <div class="meta-value"><?= htmlspecialchars($deck['username']) ?></div>
      </div>
      <div class="meta-item">
        <div class="meta-label">📅 Created On</div>
        <div class="meta-value"><?= date('M j, Y', strtotime($deck['created_at'])) ?></div>
      </div>
      <div class="meta-item">
        <div class="meta-label">🎴 Total Cards</div>
        <div class="meta-value">
          <span class="card-count-badge">
            <span><?= $card_count ?></span>
            <span><?= $card_count === 1 ? 'card' : 'cards' ?></span>
          </span>
        </div>
      </div>
    </div>
  </div>

  <?php if ($card_count === 0): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <h2 class="empty-title">No Flashcards Found</h2>
      <p class="empty-text">This deck doesn't contain any flashcards yet.</p>
    </div>
  <?php else: ?>
    <div class="section-header">
      <h2 class="section-title">🎴 Flashcard Preview</h2>
    </div>

    <div class="cards-grid">
      <?php 
      $card_num = 1;
      while ($card = $cards->fetch_assoc()): 
      ?>
        <div class="flashcard">
          <div class="card-number"><?= $card_num ?></div>
          
          <div class="qa-section">
            <div class="qa-label">
              <span>❓</span>
              <span>Question</span>
            </div>
            <div class="qa-content question-text">
              <?= nl2br(htmlspecialchars($card['question'])) ?>
            </div>
          </div>

          <div class="divider"></div>

          <div class="qa-section">
            <div class="qa-label">
              <span>✅</span>
              <span>Answer</span>
            </div>
            <div class="qa-content answer-text">
              <?= nl2br(htmlspecialchars($card['answer'])) ?>
            </div>
          </div>

          <?php if (!empty($card['image'])): ?>
            <div class="divider"></div>
            <?php
              $imgSrc = (strpos($card['image'], '/') !== false || strpos($card['image'], '\\') !== false)
                ? htmlspecialchars($card['image'])
                : 'data:image/jpeg;base64,' . base64_encode($card['image']);
            ?>
            <div class="image-container">
              <div class="image-wrapper">
                <img src="<?= $imgSrc ?>" alt="Flashcard Image" loading="lazy">
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php 
      $card_num++;
      endwhile; 
      ?>
    </div>
  <?php endif; ?>

</div>
</body>
</html>