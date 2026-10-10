<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
$error = null;
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        if (!$user || ($user['role'] ?? '') === 'admin') {
            throw new RuntimeException('Sign in to review.');
        }
        if (review_limit_hit((int) $user['id'])) {
            throw new RuntimeException('You have sent several reviews. Please try again in an hour.');
        }
        $existing = db()->prepare('SELECT id FROM reviews WHERE product_id IS NULL AND user_id = ? LIMIT 1');
        $existing->execute([(int) $user['id']]);
        if ($existing->fetch()) {
            throw new RuntimeException('You already reviewed the nursery.');
        }
        $body = clean_review_body((string) ($_POST['body'] ?? ''));
        $rating = (int) ($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5 || strlen($body) < 10 || strlen($body) > 600) {
            throw new RuntimeException('Choose a star rating and write between 10 and 600 characters.');
        }
        db()->prepare('INSERT INTO reviews (product_id, user_id, author_name, rating, body, status, verified) VALUES (NULL, ?, ?, ?, ?, ?, 0)')
            ->execute([(int) $user['id'], (string) $user['name'], $rating, $body, 'pending']);
        flash_set('Thanks - your review will appear after we check it.');
        header('Location: /reviews');
        exit;
    } catch (Throwable $err) {
        $error = safe_error($err, 'That review could not be saved.');
    }
}
$rows = db()->query("SELECT author_name, rating, body, reply, created_at FROM reviews WHERE product_id IS NULL AND status = 'approved' ORDER BY id DESC")->fetchAll();
$flash = flash_take();
render_header('Reviews | Precision Agritech', 'Reviews of Precision Agritech seedling trays from Theur.');
?>
<section class="section"><div class="wrap narrow">
  <h1>Reviews of Precision Agritech</h1>
  <?php if ($flash): ?><p class="flash <?= e($flash['kind']) ?>"><?= e($flash['message']) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="flash bad"><?= e($error) ?></p><?php endif; ?>
  <?php foreach ($rows as $review): ?>
    <article class="card card-body">
      <p><?= stars_markup((float) $review['rating']) ?> <?= e(public_reviewer((string) $review['author_name'])) ?></p>
      <p><?= e($review['body']) ?></p>
      <?php if (!empty($review['reply'])): ?><p class="muted">Nursery: <?= e((string) $review['reply']) ?></p><?php endif; ?>
      <p class="muted"><?= e(date('d M Y', strtotime((string) $review['created_at']))) ?></p>
    </article>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="muted">Approved nursery reviews will show here.</p><?php endif; ?>
  <h2>Write a review of Precision Agritech</h2>
  <?php if ($user && ($user['role'] ?? '') !== 'admin'): ?>
  <form method="post">
    <?= csrf_field() ?>
    <fieldset class="star-pick">
      <legend>Rating</legend>
      <?php for ($star = 5; $star >= 1; $star--): ?>
        <input id="nstar-<?= $star ?>" type="radio" name="rating" value="<?= $star ?>" required>
        <label for="nstar-<?= $star ?>"><?= $star ?> stars</label>
      <?php endfor; ?>
    </fieldset>
    <label>Review <textarea name="body" minlength="10" maxlength="600" required></textarea></label>
    <button class="btn" type="submit">Send review</button>
  </form>
  <?php else: ?>
    <p><a href="/login?next=/reviews">Sign in to review</a></p>
  <?php endif; ?>
</div></section>
<?php render_footer(); ?>
