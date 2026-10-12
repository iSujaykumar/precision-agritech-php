<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
connect_or_explain();
render_header(calc_label() . ' | Precision Agritech', 'Work out how many trays you need and the price.');
echo '<section class="section"><div class="wrap narrow">';
render_calc_panel('page');
echo '</div></section>';
render_footer();
