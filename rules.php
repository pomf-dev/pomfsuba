<?php
session_start();
require __DIR__ . '/config.php';
page_header('Rules');
?>
<div class="alert">
    <h2>Rules</h2>
    <ol>
        <li>Do not post illegal content.</li>
        <li>Do not upload malware or executable files.</li>
        <li>Keep uploads within the 8 MB limit.</li>
        <li>Do not spam or intentionally abuse the board.</li>
        <li>Staff may remove posts that violate the site's rules.</li>
    </ol>
</div>
<?php page_footer(); ?>
