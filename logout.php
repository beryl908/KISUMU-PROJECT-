<?php
declare(strict_types=1);
require _DIR_ . '/config.php';

session_destroy();
header('Location: index.php');
exit;
?>