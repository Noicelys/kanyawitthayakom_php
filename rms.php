<?php
require("connect.php");
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all events
$stmt = mysqli_query($connect, "SELECT * FROM events ORDER BY event_date ASC");
$events = mysqli_fetch_all($stmt, MYSQLI_ASSOC);

// Handle adding a new event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $title = trim($_POST['event_title']);
    $date = $_POST['event_date'];
    $description = trim($_POST['event_description']);
    
    if (!empty($title) && !empty($date)) {
        try {
            $query = "INSERT INTO events (title, event_date, description, created_by) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($connect, $query);
            mysqli_stmt_bind_param($stmt, "ssss", $title, $date, $description, $_SESSION['username']);
            mysqli_stmt_execute($stmt);
            
            // Redirect to refresh page and avoid form resubmission
            header("Location: rms.php");
            exit();
        } catch (Exception $e) {
            $error = "เกิดข้อผิดพลาดในการเพิ่มกิจกรรม: " . $e->getMessage();
        }
    } else {
        $error = "กรุณากรอกชื่อกิจกรรมและวันที่";
    }
}

//เวลาทำงาน
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - โรงเรียนกันยาพิทยาคม</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <div class="container">
        <!-- Attendance Summary -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="section-title">แดชบอร์ดสรุปการเข้างาน</h2>
            </div>
            
            <!-- Attendance Stats -->
            <div class="col-md-4 mb-3">
                <div class="card h-100 stats-card">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-check-circle text-success fa-3x me-3"></i>
                            <h5 class="card-title mb-0">เข้างานตรงเวลา</h5>
                        </div>
                        <h3 class="text-success"><?= $on_time ?> ครั้ง</h3>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card h-100 stats-card">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-clock text-danger fa-3x me-3"></i>
                            <h5 class="card-title mb-0">เข้างานสาย</h5>
                        </div>
                        <h3 class="text-danger"><?= $late ?> ครั้ง</h3>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card h-100 stats-card">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-sign-out-alt text-warning fa-3x me-3"></i>
                            <h5 class="card-title mb-0">ออกงาน</h5>
                        </div>
                        <h3 class="text-warning"><?= $checkout ?> ครั้ง</h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i>
                        กราฟสรุปการเข้างานทั้งหมด
                    </div>
                    <div class="card-body">
                        <canvas id="summaryChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Check-in Button -->
        <div class="row mb-4 text-center">
            <div class="col-md-3 mb-2">
                <a href="worktime.php" class="btn btn-check-in btn-lg w-100">
                    <i class="fas fa-user-clock me-2"></i>เข้างาน/ออกงาน
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="subject.php" class="btn btn-check-in btn-lg w-100">
                <i class="fas fa-book me-2"></i>จัดการตารางเรียน
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="check_student.php" class="btn btn-check-in btn-lg w-100">
                    <i class="fas fa-user-graduate me-2"></i>จัดการนักเรียน
                </a>
            </div>
        </div>


        <!-- Calendar and Events -->
        <div class="row">
            <!-- Calendar Section -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="far fa-calendar-alt me-2"></i>ปฏิทินกิจกรรม</span>
                        <div class="calendar-nav">
                            <button id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                            <button id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <h5 id="currentMonth" class="mb-0"></h5>
                        </div>
                        <div class="weekdays mb-2">
                            <div>อา</div>
                            <div>จ</div>
                            <div>อ</div>
                            <div>พ</div>
                            <div>พฤ</div>
                            <div>ศ</div>
                            <div>ส</div>
                        </div>
                        <div id="calendar-days" class="days"></div>
                    </div>
                </div>
            </div>
            
            <!-- Events Section -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="far fa-calendar-check me-2"></i>กิจกรรมสำคัญ
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Add Event Form -->
                        <form method="POST" action="" class="bg-light p-3 rounded mb-4">
                            <div class="mb-3">
                                <input type="text" name="event_title" class="form-control" placeholder="ชื่อกิจกรรม" required>
                            </div>
                            <div class="mb-3">
                                <input type="date" name="event_date" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <textarea name="event_description" class="form-control" placeholder="รายละเอียดกิจกรรม" rows="2"></textarea>
                            </div>
                            <button type="submit" name="add_event" class="btn btn-check-in">
                                <i class="fas fa-plus me-2"></i>เพิ่มกิจกรรม
                            </button>
                        </form>
                        
                        <!-- Events List -->
                        <div class="events-list">
                            <?php if (empty($events)): ?>
                                <div class="text-center text-muted p-4">
                                    <i class="far fa-calendar-times fa-3x mb-3"></i>
                                    <p>ยังไม่มีกิจกรรมที่กำหนด</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($events as $event): ?>
                                    <div class="event-item p-3 mb-3 shadow-sm" data-date="<?php echo $event['event_date']; ?>">
                                        <div class="row">
                                            <div class="col-md-3 event-date">
                                                <i class="far fa-calendar me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($event['event_date'])); ?>
                                            </div>
                                            <div class="col-md-9">
                                                <h5 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h5>
                                                <p class="text-muted mb-1"><?php echo htmlspecialchars($event['description']); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($event['created_by']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentDate = new Date();
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();
            
            // Store event dates from PHP for highlighting in calendar
            const eventDates = [
                <?php foreach ($events as $event): ?>
                    "<?php echo $event['event_date']; ?>",
                <?php endforeach; ?>
            ];

            function updateCalendar() {
                const monthNames = ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", 
                                    "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
                
                // Display current month and year (convert to Thai year)
                document.getElementById('currentMonth').textContent = 
                    `${monthNames[currentMonth]} ${currentYear + 543}`;
                
                // Calculate first day of month and total days
                const firstDay = new Date(currentYear, currentMonth, 1).getDay();
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                
                // Create calendar grid
                const calendarDays = document.getElementById('calendar-days');
                calendarDays.innerHTML = '';
                
                // Add empty cells for days before the first day of month
                for (let i = 0; i < firstDay; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.classList.add('day', 'empty');
                    calendarDays.appendChild(emptyDay);
                }
                
                // Add cells for all days in month
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayElement = document.createElement('div');
                    dayElement.classList.add('day');
                    dayElement.textContent = day;
                    
                    // Check if this date has events
                    const dateString = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    if (eventDates.includes(dateString)) {
                        dayElement.classList.add('has-event');
                    }
                    
                    // Highlight today's date
                    if (currentYear === new Date().getFullYear() && 
                        currentMonth === new Date().getMonth() && 
                        day === new Date().getDate()) {
                        dayElement.classList.add('today');
                    }
                    
                    calendarDays.appendChild(dayElement);
                }
            }
            
            // Initialize calendar
            updateCalendar();
            
            // Handle month navigation
            document.getElementById('prevMonth').addEventListener('click', function() {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                updateCalendar();
            });
            
            document.getElementById('nextMonth').addEventListener('click', function() {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                updateCalendar();
            });

            // Initialize chart
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
        });
    </script>
</body>
</html>