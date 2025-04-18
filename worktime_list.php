<?php
require("connect.php");
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// นับจำนวนรวม
$on_time_query = mysqli_query($connect, "SELECT COUNT(*) AS total FROM worktime WHERE action_type = 'เข้างาน' AND user_id = $user_id");
$late_query = mysqli_query($connect, "SELECT COUNT(*) AS total FROM worktime WHERE action_type = 'เข้างานสาย' AND user_id = $user_id");
$checkout_query = mysqli_query($connect, "SELECT COUNT(*) AS total FROM worktime WHERE action_type = 'ออกงาน' AND user_id = $user_id");

$on_time = mysqli_fetch_assoc($on_time_query)['total'];
$late = mysqli_fetch_assoc($late_query)['total'];
$checkout = mysqli_fetch_assoc($checkout_query)['total'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Dashboard สรุปการเข้างาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="text-center mb-4">
            <h2 class="text-warning">แดชบอร์ดสรุปการเข้างาน</h2>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-warning shadow text-center">
                    <div class="card-body">
                        <h5 class="text-muted">เข้างานตรงเวลา</h5>
                        <h3 class="text-success"><?= $on_time ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-warning shadow text-center">
                    <div class="card-body">
                        <h5 class="text-muted">เข้างานสาย</h5>
                        <h3 class="text-danger"><?= $late ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-warning shadow text-center">
                    <div class="card-body">
                        <h5 class="text-muted">ออกงาน</h5>
                        <h3 class="text-warning"><?= $checkout ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-5 border-warning shadow">
            <div class="card-body">
                <h5 class="card-title text-warning">กราฟสรุปการเข้างานทั้งหมด</h5>
                <canvas id="summaryChart" height="100"></canvas>
            </div>
        </div>
    </div>

<script>
const ctx = document.getElementById('summaryChart').getContext('2d');

const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['เข้างานตรงเวลา', 'เข้างานสาย', 'ออกงาน'],
        datasets: [{
            label: 'จำนวนครั้ง',
            data: [<?= $on_time ?>, <?= $late ?>, <?= $checkout ?>],
            backgroundColor: ['#66bb6a', '#ef5350', '#ffa726']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'จำนวนครั้ง'
                }
            }
        }
    }
});
</script>
</body>
</html>
