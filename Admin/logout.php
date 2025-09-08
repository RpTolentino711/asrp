<?php
session_start();
unset($_SESSION['is_admin']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_id']);
session_destroy();
header("Location: login.php");
exit();
?>