<?php
session_start();
session_unset();
session_destroy();

echo "<script>alert('คุณได้ออกจากระบบเรียบร้อยแล้ว'); window.location.href = 'Login.php';</script>";
exit();
?>
