<?php
$connect = mysqli_connect("localhost", "root", "", "kanyawitthayakom");
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $connect->prepare("SELECT user_id, role FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($user_id, $role);
        $stmt->fetch();

        // ✅ บันทึกข้อมูลลงใน session
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['user_id'] = $user_id;

        // ✅ ตรวจสอบบทบาท (role) และเปลี่ยนหน้า
        switch (strtolower($role)) {
            case 'admin':
            case 'teacher':
                header("Location: Rms.php");
                break;
            case 'student':
                header("Location: index.php");
                break;
            default:
                echo "<script>alert('ไม่พบบทบาทผู้ใช้งานที่รองรับ');</script>";
                break;
        }
        exit();
    } else {
        echo "<script>alert('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');</script>";
    }

    $stmt->close();
}
?>



<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โรงเรียนกันยาพิทยาคม</title>
    <link rel="stylesheet" href="login.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto%20Sans%20Thai&display=swap" rel="stylesheet">
</head>
<body>
    <form method="POST">
        <div class="login-container" data-aos="zoom-in">
            <div class="tooltip-container">
                <tooltip class="tooltip-btn"></tooltip>
                <span class="tooltip-text">นักเรียนและครู : ใช้ชื่อจริง-รหัสผ่านใช้วันเดือนปีเกิด เช่น 01/01/2006</span>
            </div>
            <h2>โรงเรียนกันยาพิทยาคม</h2>
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">เข้าสู่ระบบ</button>
        </div>
    </form>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1200,
            once: true
        });
    </script>
</body>
</html>
