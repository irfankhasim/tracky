<?php
session_start();
session_destroy();
header('Location: /tracky/login.php');
exit;
