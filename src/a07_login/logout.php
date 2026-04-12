<?php
session_start();
session_destroy();
setcookie('NEXO_SESS', '', time() - 3600, '/');
header('Location: index.php');
exit;
