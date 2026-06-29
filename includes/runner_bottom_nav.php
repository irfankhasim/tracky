<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="runner-bottom-nav bottom-nav">
  <a href="/tracky/runner/runner_orders.php" class="nav-btn <?= $current === 'runner_orders.php' ? 'active' : '' ?>">
    <i class="ti ti-home"></i>
    <span>Order</span>
  </a>
  <a href="/tracky/runner/runner_history.php" class="nav-btn <?= $current === 'runner_history.php' ? 'active' : '' ?>">
    <i class="ti ti-history"></i>
    <span>History</span>
  </a>
  <a href="/tracky/runner/runner_profile.php" class="nav-btn <?= $current === 'runner_profile.php' ? 'active' : '' ?>">
    <i class="ti ti-user"></i>
    <span>Profil</span>
  </a>
</nav>
