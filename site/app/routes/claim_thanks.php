<?php
/** After-payment page: /claim/thanks?session_id=... (set as the Stripe Payment Link redirect) */
declare(strict_types=1);

page_start(['title' => 'Thank you | ' . cfg('site_name'), 'robots' => 'noindex', 'cache' => false]);
?>
<div class="container section"><div class="card" style="max-width:720px;margin:0 auto">
<h1 style="color:#fff;font-size:1.5rem;margin-bottom:10px">Payment received &mdash; thank you</h1>
<p>Your listing will be reviewed and activated shortly (usually the same business day). You'll get a confirmation at the email you entered.</p>
<p style="margin-top:12px">Questions? Email <a href="mailto:<?= e((string)cfg('notify_email')) ?>"><?= e((string)cfg('notify_email')) ?></a>.</p>
<p style="margin-top:18px"><a class="btn" href="/carriers/">Back to the directory</a></p>
</div></div>
<?php page_end();
