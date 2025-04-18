<?php
require("connect.php");
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
date_default_timezone_set("Asia/Bangkok");

$connect = mysqli_connect("localhost", "root", "", "kanyawitthayakom");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $code = $_POST['confirm_code'];

  $currentHour = (int)date("H");
  $currentMinute = (int)date("i");

  // คำนวณเป็นนาทีทั้งหมดเพื่อเปรียบเทียบง่าย
  $totalMinutes = $currentHour * 60 + $currentMinute;

  if ($totalMinutes >= 0 && $totalMinutes <= 480) { // 00:00 - 08:00
      $action_type = 'เข้างาน';
  } elseif ($totalMinutes >= 481 && $totalMinutes <= 1019) { // 08:01 - 16:59
      $action_type = 'เข้างานสาย';
  } else { // 17:00 - 23:59
      $action_type = 'ออกงาน';
  }

  // บันทึกลงฐานข้อมูล
  $stmt = $connect->prepare("INSERT INTO worktime (workcheck_code, action_type, scanned_time, user_id) VALUES (?, ?, NOW(), ?)");
  $stmt->bind_param("ssi", $code, $action_type, $user_id);

  if ($stmt->execute()) {
      $scanned_time = date("H:i:s");
      echo "<script>
          alert('$action_type เวลา $scanned_time น.');
          setTimeout(function() {
              window.location.href = 'Rms.php';
          }, 3000);
      </script>";
  } else {
      echo "<script>alert('เกิดข้อผิดพลาดในการบันทึก');</script>";
  }

  $stmt->close();
}

?>


<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>QR เข้างาน/ออกงาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="rms_main_M.css">
</head>

<body>
      <!-- Navbar -->
      <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="logo.png" alt="Logo">
                <span class="ms-2">โรงเรียนกันยาพิทยาคม</span>
            </a>
            
            <div class="ms-auto d-flex align-items-center">
                <div class="d-flex align-items-center me-3">
                    <img src="unkown.jpg" alt="Profile" class="profile-img me-2">
                    <span class="text-white profile-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                <form action="logout.php" method="POST">
                    <button type="submit" class="btn btn-logout">ล็อกเอาท์</button>
                </form>
            </div>
        </div>
    </nav>

  <div class="container py-5">
    <div class="card shadow text-center mx-auto" style="max-width: 450px;">
      <div class="card-body">
        <h3 class="mb-4 text-primary">QR Code เข้างาน/ออกงาน</h3>
        
        <img id="qr-code" src="" width="220" height="220" alt="QR Code">
        <div id="qr-message" class="fw-bold mb-2"></div>
        <div id="timer" class="text-muted mb-4">QR จะเปลี่ยนในอีก 60 วินาที</div>

        <form method="POST" class="mb-3">
          <label for="confirm_code" class="form-label">กรอกรหัส 6 หลักจาก QR</label>
          <input type="text" name="confirm_code" id="confirm_code" class="form-control text-center" pattern="\d{6}" required maxlength="6" minlength="6" placeholder="ใส่รหัสยืนยันที่นี่">
          <button type="submit" class="btn btn-success mt-3 w-100">ยืนยันการเข้า/ออกงาน</button>
        </form>
        
      </div>
    </div>
  </div>

  <script>
    let timeLeft = 60;

    function generateRandomCode() {
      return Math.floor(100000 + Math.random() * 900000).toString();
    }

    function updateQRCode() {
      const code = generateRandomCode();
      const userMessage = `รหัส: ${code} - เข้างาน/ออกงาน`;
      const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(userMessage)}&size=1000x1000`;

      document.getElementById("qr-code").src = qrUrl;
      timeLeft = 60;
    }

    function startTimer() {
      setInterval(() => {
        timeLeft--;
        document.getElementById("timer").textContent = `QR จะเปลี่ยนในอีก ${timeLeft} วินาที`;
        if (timeLeft <= 0) {
          updateQRCode();
        }
      }, 1000);
    }

    updateQRCode();
    startTimer();
</script>
</body>
</html>
