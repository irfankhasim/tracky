<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="runner-bottom-nav bottom-nav">
  <a href="/tracky/runner/my_orders.php" class="nav-btn <?= $current === 'my_orders.php' ? 'active' : '' ?>">
    <i class="ti ti-home"></i>
    <span>Order</span>
  </a>
  <a href="/tracky/runner/history.php" class="nav-btn <?= $current === 'history.php' ? 'active' : '' ?>">
    <i class="ti ti-history"></i>
    <span>History</span>
  </a>
  <a href="/tracky/runner/profile.php" class="nav-btn <?= $current === 'profile.php' ? 'active' : '' ?>">
    <i class="ti ti-user"></i>
    <span>Profil</span>
  </a>
</nav>
