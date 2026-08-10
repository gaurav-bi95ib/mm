<?php
$promotionFile = basename($_SERVER['PHP_SELF'] ?? '');
?>
<div class="nav-section-label">Paid Promotions</div>
<a href="recommended-promotion.php" class="nav-link <?=$promotionFile==='recommended-promotion.php'?'active':''?>"><span class="icon">R</span> Recommended Venue</a>
<a href="event-promotion.php" class="nav-link <?=$promotionFile==='event-promotion.php'?'active':''?>"><span class="icon">E</span> Event Promotion</a>
