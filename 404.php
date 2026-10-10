<?php
declare(strict_types=1);
http_response_code(404);
require __DIR__ . '/includes/bootstrap.php';
render_header('Page not found | Precision Agritech');
?>
<section class="section"><div class="wrap narrow">
  <h1>Page not found</h1>
  <p>That page is not on the nursery site.</p>
  <p><a class="btn" href="/">Back to the nursery</a> <a class="btn light" href="/shop">Shop seedlings</a></p>
</div></section>
<?php render_footer(); ?>
