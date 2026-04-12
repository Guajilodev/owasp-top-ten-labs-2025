<?php
/**
 * Logout del panel admin
 */
session_start();
session_destroy();
header('Location: index.php');
exit;
