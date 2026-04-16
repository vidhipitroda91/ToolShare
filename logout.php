<?php
session_start();
require 'includes/auth.php';
toolshare_sign_out();
header("Location: index.php");
exit;
?>
