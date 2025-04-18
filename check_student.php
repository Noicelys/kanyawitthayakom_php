<?php
require("connect.php");
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$connect = mysqli_connect("localhost", "root", "", "kanyawitthayakom");

// Get subjects
$subjectsQuery = "SELECT subject_id, subject_code, subject_name FROM subject ORDER BY subject_code";
$subjectsResult = mysqli_query($connect, $subjectsQuery);

// Get classrooms
$classQuery = "SELECT class_id, class_name FROM classroom ORDER BY class_name";
$classResult = mysqli_query($connect, $classQuery);

// Process attendance submission
if (isset($_POST['submit_attendance'])) {
    $subjectId = $_POST['subject_id'];
    $classId = $_POST['class_id'];
    $attendanceDate = $_POST['attendance_date'];
    
    // Check if class_subject relation exists
    $csQuery = "SELECT id FROM class_subject WHERE class_id = '$classId' AND subject_id = '$subjectId'";
    $csResult = mysqli_query($connect, $csQuery);
    
    if (mysqli_num_rows($csResult) > 0) {
        $csData = mysqli_fetch_assoc($csResult);
        $classSubjectId = $csData['id'];
    } else {
        // If relation doesn't exist, create it
        $insertCSQuery = "INSERT INTO class_subject (class_id, subject_id) VALUES ('$classId', '$subjectId')";
        mysqli_query($connect, $insertCSQuery);
        $classSubjectId = mysqli_insert_id($connect);
    }
    
    // Delete existing attendance records for this date and class_subject
    $deleteQuery = "DELETE FROM attendance WHERE class_subject_id = '$classSubjectId' AND attendance_date = '$attendanceDate'";
    mysqli_query($connect, $deleteQuery);
    
    // Insert new attendance records
    if (isset($_POST['status'])) {
        foreach ($_POST['status'] as $studentId => $status) {
            if (!empty($status)) { // ตรวจสอบว่ามีการเลือกสถานะหรือไม่
                $insertQuery = "INSERT INTO attendance (student_id, class_subject_id, attendance_date, status) 
                                VALUES ('$studentId', '$classSubjectId', '$attendanceDate', '$status')";
                mysqli_query($connect, $insertQuery);
            }
        }
        
        $successMessage = "บันทึกข้อมูลการเข้าเรียนเรียบร้อยแล้ว";
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบเช็คชื่อนักเรียน</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="rms_main_M.css">
    <style>
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background-color: #f2f2f2;
        }
        
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
        }
        
        .card-header {
            background-color: #ff7300;
            color: white;
            font-weight: bold;
            border-bottom: 0;
            padding: 15px;
        }
        
        .section-title {
            color: #ff7300;
            border-bottom: 2px solid #ff7300;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .btn-check-in {
            background-color: #ff7300;
            color: white;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        
        .btn-check-in:hover {
            background-color: #e66a00;
            color: white;
        }
        
        .status-present {
            background-color: #66bb6a;
            color: white;
        }
        
        .status-late {
            background-color: #ffa726;
            color: white;
        }
        
        .status-absent {
            background-color: #ef5350;
            color: white;
        }
        
        .status-leave {
            background-color: #42a5f5;
            color: white;
        }
        
        .status-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin: 0 5px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
        }
        
        .status-btn:hover {
            transform: scale(1.1);
        }
        
        .status-btn.active {
            border: 3px solid #333;
        }
    </style>
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

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">ระบบเช็คชื่อนักเรียน</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($successMessage)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $successMessage; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="GET" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="subject" class="form-label">เลือกรายวิชา</label>
                                    <select name="subject_id" id="subject" class="form-select" required>
                                        <option value="">-- เลือกรายวิชา --</option>
                                        <?php while ($subject = mysqli_fetch_assoc($subjectsResult)): ?>
                                            <option value="<?php echo $subject['subject_id']; ?>" <?php echo (isset($_GET['subject_id']) && $_GET['subject_id'] == $subject['subject_id']) ? 'selected' : ''; ?>>
                                                <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="classRoom" class="form-label">เลือกห้องเรียน</label>
                                    <select name="class_id" id="classRoom" class="form-select" required>
                                        <option value="">-- เลือกห้องเรียน --</option>
                                        <?php 
                                        mysqli_data_seek($classResult, 0);
                                        while ($class = mysqli_fetch_assoc($classResult)): 
                                        ?>
                                            <option value="<?php echo $class['class_id']; ?>" <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                                <?php echo $class['class_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="checkDate" class="form-label">วันที่เช็คชื่อ</label>
                                    <input type="date" id="checkDate" name="check_date" class="form-control" value="<?php echo isset($_GET['check_date']) ? $_GET['check_date'] : date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-check-in w-100">แสดงรายชื่อ</button>
                                </div>
                            </div>
                        </form>

                        <?php
                        if (isset($_GET['subject_id']) && isset($_GET['class_id'])) {
                            $subjectId = $_GET['subject_id'];
                            $classId = $_GET['class_id'];
                            $checkDate = isset($_GET['check_date']) ? $_GET['check_date'] : date('Y-m-d');
                            
                            // Get class_subject_id
                            $csQuery = "SELECT id FROM class_subject WHERE class_id = '$classId' AND subject_id = '$subjectId'";
                            $csResult = mysqli_query($connect, $csQuery);
                            
                            $classSubjectId = null;
                            if (mysqli_num_rows($csResult) > 0) {
                                $csData = mysqli_fetch_assoc($csResult);
                                $classSubjectId = $csData['id'];
                            }
                            
                            // Get subject info
                            $subjectInfoQuery = "SELECT subject_code, subject_name FROM subject WHERE subject_id = '$subjectId'";
                            $subjectInfoResult = mysqli_query($connect, $subjectInfoQuery);
                            $subjectInfo = mysqli_fetch_assoc($subjectInfoResult);
                            
                            // Get class info
                            $classInfoQuery = "SELECT class_name FROM classroom WHERE class_id = '$classId'";
                            $classInfoResult = mysqli_query($connect, $classInfoQuery);
                            $classInfo = mysqli_fetch_assoc($classInfoResult);
                            
        
                            $studentsQuery = "SELECT * FROM student ORDER BY stu_id ASC";

                            $studentsResult = mysqli_query($connect, $studentsQuery);
                            
                            // Get class info including department
                            $classInfoQuery = "SELECT class_name, department FROM classroom WHERE class_id = '$classId'";
                            $classInfoResult = mysqli_query($connect, $classInfoQuery);
                            $classInfo = mysqli_fetch_assoc($classInfoResult);

                            // ตรวจสอบว่ามี department และใช้เป็นเงื่อนไขในการกรองนักเรียน
                            if (isset($classInfo['department']) && !empty($classInfo['department'])) {
                                $classDepartment = $classInfo['department'];
                                
                                // ดึงนักเรียนเฉพาะแผนกที่ตรงกับห้องเรียน
                                $studentsQuery = "SELECT * FROM student WHERE dep = '$classDepartment' ORDER BY stu_id ASC";
                            } else {
                                // ถ้าไม่มีค่า department ให้แสดงนักเรียนทั้งหมด
                                $studentsQuery = "SELECT * FROM student ORDER BY stu_id ASC";
                            }

                            $studentsResult = mysqli_query($connect, $studentsQuery);

                            // เพิ่มการตรวจสอบว่า $studentsResult เป็น false หรือไม่
                            if ($studentsResult === false) {
                                echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการดึงข้อมูลนักเรียน: ' . mysqli_error($connect) . '</div>';
                                $studentsResult = false;
                            }
                            
                            // Check if any previous attendance exists
                            $attendanceData = array();
                            if ($classSubjectId) {
                                $checkAttendanceQuery = "SELECT student_id, status FROM attendance 
                                                       WHERE class_subject_id = '$classSubjectId' 
                                                       AND attendance_date = '$checkDate'";
                                $checkAttendanceResult = mysqli_query($connect, $checkAttendanceQuery);
                                
                                if ($checkAttendanceResult) {
                                    while ($attendance = mysqli_fetch_assoc($checkAttendanceResult)) {
                                        $attendanceData[$attendance['student_id']] = $attendance['status'];
                                    }
                                }
                            }

                            // Format date for display (d/m/Y)
                            $formattedDate = date('d/m/Y', strtotime($checkDate));
                        ?>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        วิชา: <?php echo $subjectInfo['subject_code'] . ' - ' . $subjectInfo['subject_name']; ?> | 
                                        ห้อง: <?php echo $classInfo['class_name']; ?>
                                    </h5>
                                    <div>
                                        วันที่: <?php echo $formattedDate; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="attendanceForm">
                                    <input type="hidden" name="subject_id" value="<?php echo $subjectId; ?>">
                                    <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
                                    <input type="hidden" name="attendance_date" value="<?php echo $checkDate; ?>">
                                    
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">สถานะการมาเรียน:</span>
                                            <div class="status-present status-btn me-1" title="มาเรียน">ม</div> = มาเรียน
                                            <div class="status-late status-btn mx-1" title="มาสาย">ส</div> = มาสาย
                                            <div class="status-absent status-btn mx-1" title="ขาดเรียน">ข</div> = ขาดเรียน
                                            <div class="status-leave status-btn mx-1" title="ลา">ล</div> = ลา
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr class="table-light">
                                                    <th width="5%" class="text-center">ลำดับ</th>
                                                    <th width="15%" class="text-center">รหัสนักเรียน</th>
                                                    <th width="40%">ชื่อ-นามสกุล</th>
                                                    <th width="10%" class="text-center">แผนก</th>
                                                    <th width="30%" class="text-center">สถานะการมาเรียน</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $i = 1;
                                                if ($studentsResult && mysqli_num_rows($studentsResult) > 0) {
                                                    while ($student = mysqli_fetch_assoc($studentsResult)):
                                                        $currentStatus = isset($attendanceData[$student['stu_id']]) ? $attendanceData[$student['stu_id']] : '';
                                                ?>
                                                <tr>
                                                    <td class="text-center"><?php echo $i++; ?></td>
                                                    <td class="text-center"><?php echo $student['stu_id']; ?></td>
                                                    <td><?php echo $student['fname'] . ' ' . $student['lname']; ?></td>
                                                    <td class="text-center"><?php echo $student['dep']; ?></td>
                                                    <td class="text-center">
                                                        <input type="hidden" name="status[<?php echo $student['stu_id']; ?>]" id="status_<?php echo $student['stu_id']; ?>" value="<?php echo $currentStatus; ?>">
                                                        
                                                        <div class="d-flex justify-content-center">
                                                            <div class="status-present status-btn <?php echo ($currentStatus == 'present') ? 'active' : ''; ?>" 
                                                                onclick="setStatus('<?php echo $student['stu_id']; ?>', 'present')" title="มาเรียน">
                                                                ม
                                                            </div>
                                                            <div class="status-late status-btn <?php echo ($currentStatus == 'late') ? 'active' : ''; ?>" 
                                                                onclick="setStatus('<?php echo $student['stu_id']; ?>', 'late')" title="มาสาย">
                                                                ส
                                                            </div>
                                                            <div class="status-absent status-btn <?php echo ($currentStatus == 'absent') ? 'active' : ''; ?>" 
                                                                onclick="setStatus('<?php echo $student['stu_id']; ?>', 'absent')" title="ขาดเรียน">
                                                                ข
                                                            </div>
                                                            <div class="status-leave status-btn <?php echo ($currentStatus == 'leave') ? 'active' : ''; ?>" 
                                                                onclick="setStatus('<?php echo $student['stu_id']; ?>', 'leave')" title="ลา">
                                                                ล
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php 
                                                    endwhile; 
                                                } else {
                                                    echo '<tr><td colspan="5" class="text-center">ไม่พบข้อมูลนักเรียนในห้องเรียนนี้</td></tr>';
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-3">
                                        <button type="submit" name="submit_attendance" class="btn btn-check-in">
                                            <i class="fas fa-save me-2"></i>บันทึกข้อมูลการเข้าเรียน
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to set attendance status
        function setStatus(studentId, status) {
            // Update hidden input value
            document.getElementById('status_' + studentId).value = status;
            
            // Update visual status indicators (remove active class from all, add to selected)
            const statusButtons = document.querySelectorAll(`[onclick^="setStatus('${studentId}']`);
            statusButtons.forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Find the clicked button and add active class
            const clickedButton = document.querySelector(`[onclick="setStatus('${studentId}', '${status}')"]`);
            if (clickedButton) {
                clickedButton.classList.add('active');
            }
        }
    </script>
</body>
</html>
