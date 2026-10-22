<?php
/** Vendor "Get Listed": /get-listed */
declare(strict_types=1);
require __DIR__ . '/../lib/vendor_form.php';
[$fv, $ferrors] = vendor_form_handle('listed');

$total = (int)meta('total', '0');
page_start(['title' => 'Get Listed - Reach ' . nf($total) . ' Trucking Companies | ' . cfg('site_name'), 'description' => 'List your trucking service business in the directory used by carriers in all 50 states.', 'canonical' => '/get-listed', 'cache' => false]);
?>
<div class="hero"><div class="container"><h1>List your <span>trucking service</span> business</h1><p>Brokers, dealers, dispatchers, insurance agencies, factoring companies and every other service carriers buy. Your listing appears on the category pages for every state you serve, next to <?= nf($total) ?> carrier profiles.</p></div></div>
<div class="container section"><div class="grid2"><div class="card"><?php vendor_form_render($fv, $ferrors, '/get-listed'); ?></div>
<aside><div class="card"><h3>Are you a carrier?</h3><p class="small">Trucking companies already have a free profile. <a href="/carriers/search" rel="nofollow">Find yours</a> and claim it instead.</p></div>
<div class="card"><h3>How it works</h3><ol style="margin-left:18px;color:#ccc;font-size:0.9rem;line-height:1.9"><li>Fill in your company details</li><li>Pay securely with Stripe</li><li>We review and publish, usually same day</li></ol></div></aside>
</div></div>
<?php page_end();
