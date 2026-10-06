<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$code = (int) ($_SERVER['REDIRECT_STATUS'] ?? 404);
if ($code < 400) {
    $code = 404;
}
http_response_code($code);
render_header($code === 404 ? 'Page not found' : 'Something went wrong');
?>
<section class="section"><div class="wrap narrow">
  <h1><?= $code === 404 ? 'Page not found' : 'Something went wrong' ?></h1>
  <p><?= $code === 404 ? 'That page is not on this site.' : 'Please try again, or call 9011975959.' ?></p>
  <p><a href="/shop">Shop the trays</a> · <a href="/">Home</a></p>
</div></section>
<?php render_footer(); ?>
