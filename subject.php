<?php
require("connect.php");
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$connect = mysqli_connect("localhost", "root", "", "kanyawitthayakom");
if (!$connect) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . mysqli_connect_error());
}

$successMessage = "";
$errorMessage = "";

// ตรวจสอบว่าตาราง class_subject มีอยู่หรือไม่
function checkAndCreateClassSubjectTable($connect) {
    $table_exists = mysqli_query($connect, "SHOW TABLES LIKE 'class_subject'");
    
    if (mysqli_num_rows($table_exists) == 0) {
        // สร้างตาราง class_subject หากยังไม่มี
        $create_table_sql = "CREATE TABLE class_subject (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            subject_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY class_subject_unique (class_id, subject_id)
        )";
        
        if (mysqli_query($connect, $create_table_sql)) {
            return "<div class='alert alert-success'>สร้างตาราง class_subject สำเร็จ</div>";
        } else {
            return "<div class='alert alert-danger'>เกิดข้อผิดพลาดในการสร้างตาราง class_subject: " . mysqli_error($connect) . "</div>";
        }
    } else {
        // ตรวจสอบว่าตารางมี index หรือ foreign key ที่ถูกต้องหรือไม่
        $check_indexes = mysqli_query($connect, "SHOW INDEX FROM class_subject WHERE Key_name = 'class_subject_unique'");
        
        if (mysqli_num_rows($check_indexes) == 0) {
            // เพิ่ม unique key เพื่อป้องกันการซ้ำ
            $add_unique_key = "ALTER TABLE class_subject ADD UNIQUE KEY class_subject_unique (class_id, subject_id)";
            
            if (mysqli_query($connect, $add_unique_key)) {
                return "<div class='alert alert-success'>เพิ่ม unique key สำเร็จ</div>";
            } else {
                return "<div class='alert alert-info'>อาจมี unique key อยู่แล้ว หรือเกิดข้อผิดพลาด: " . mysqli_error($connect) . "</div>";
            }
        }
        return "<div class='alert alert-info'>ตาราง class_subject มีอยู่แล้ว</div>";
    }
}

// Helper functions
function getTeachers($connect) {
    $sql = "SELECT user_id, fname, lname FROM teacher";
    $result = mysqli_query($connect, $sql);
    $teachers = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $teachers[] = $row;
        }
    }
    return $teachers;
}

function getSubjects($connect) {
    $sql = "SELECT s.subject_id, s.subject_name, s.subject_code, t.fname, t.lname
            FROM subject s LEFT JOIN teacher t ON s.teacher_id = t.user_id";
    $result = mysqli_query($connect, $sql);
    return $result ? $result : false;
}

function getClassrooms($connect) {
    $result = mysqli_query($connect, "SELECT * FROM classroom");
    return $result ? $result : false;
}

function getConfig($connect, $key) {
    $stmt = mysqli_prepare($connect, "SELECT config_value FROM config WHERE config_key = ?");
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['config_value'];
    }
    return null;
}

// ตรวจสอบโครงสร้างของตาราง classroom และ subject
function createClassSubjectTable($connect) {
    // ตรวจสอบว่าตาราง classroom มี field ชื่อ class_id หรือไม่
    $check_classroom = mysqli_query($connect, "SHOW COLUMNS FROM classroom LIKE 'class_id'");
    if (mysqli_num_rows($check_classroom) == 0) {
        // ถ้าไม่มี field class_id ลองตรวจสอบว่ามี field อื่นที่เป็น primary key หรือไม่
        $check_primary = mysqli_query($connect, "SHOW KEYS FROM classroom WHERE Key_name = 'PRIMARY'");
        $primary_field = '';
        if ($row = mysqli_fetch_assoc($check_primary)) {
            $primary_field = $row['Column_name'];
        }
        
        // ถ้าไม่มี primary key ให้เพิ่ม field class_id
        if (empty($primary_field)) {
            mysqli_query($connect, "ALTER TABLE classroom ADD class_id INT AUTO_INCREMENT PRIMARY KEY");
        } else {
            // ถ้ามี primary key แล้ว ต้องใช้ชื่อ field นั้นในการสร้าง foreign key
            $primary_field = $primary_field;
        }
    } else {
        $primary_field = 'class_id';
    }
    
    // ทำแบบเดียวกันกับตาราง subject
    $check_subject = mysqli_query($connect, "SHOW COLUMNS FROM subject LIKE 'subject_id'");
    if (mysqli_num_rows($check_subject) == 0) {
        $check_primary = mysqli_query($connect, "SHOW KEYS FROM subject WHERE Key_name = 'PRIMARY'");
        $subject_primary_field = '';
        if ($row = mysqli_fetch_assoc($check_primary)) {
            $subject_primary_field = $row['Column_name'];
        }
        
        if (empty($subject_primary_field)) {
            mysqli_query($connect, "ALTER TABLE subject ADD subject_id INT AUTO_INCREMENT PRIMARY KEY");
        } else {
            $subject_primary_field = $subject_primary_field;
        }
    } else {
        $subject_primary_field = 'subject_id';
    }
    
    return ['class_field' => $primary_field, 'subject_field' => $subject_primary_field];
}

function getTablePrimaryKey($connect, $tableName) {
    $result = mysqli_query($connect, "SHOW KEYS FROM $tableName WHERE Key_name = 'PRIMARY'");
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['Column_name'];
    }
    return null;
}

function assignSubjectToClass($connect, $class_id, $subject_id) {
    // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
    $check_stmt = mysqli_prepare($connect, "SELECT id FROM class_subject WHERE class_id = ? AND subject_id = ?");
    mysqli_stmt_bind_param($check_stmt, "ii", $class_id, $subject_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // มีข้อมูลอยู่แล้ว
        return true;
    }
    
    // ถ้ายังไม่มีข้อมูล ให้เพิ่มใหม่
    $stmt = mysqli_prepare($connect, "INSERT INTO class_subject (class_id, subject_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $class_id, $subject_id);
    return mysqli_stmt_execute($stmt);
}

function getClassroomsWithSubjectCounts($connect, $class_id_field) {
    $sql = "SELECT c.*, COUNT(cs.subject_id) as subject_count 
            FROM classroom c
            LEFT JOIN class_subject cs ON c.$class_id_field = cs.class_id
            GROUP BY c.$class_id_field";
    $result = mysqli_query($connect, $sql);
    return $result ? $result : false;
}

function getSubjectsForClassroom($connect, $class_id, $subject_id_field) {
    $sql = "SELECT s.*, t.fname, t.lname 
            FROM class_subject cs
            JOIN subject s ON cs.subject_id = s.$subject_id_field
            LEFT JOIN teacher t ON s.teacher_id = t.user_id
            WHERE cs.class_id = ?";
    
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "i", $class_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return $result ? $result : false;
}

// ตรวจสอบและสร้างตาราง class_subject
$tableStatus = checkAndCreateClassSubjectTable($connect);

// เรียกใช้ฟังก์ชัน createClassSubjectTable เพื่อตรวจสอบและสร้างตาราง
$field_info = createClassSubjectTable($connect);
$class_id_field = $field_info['class_field'];
$subject_id_field = $field_info['subject_field'];

// --- บันทึกข้อมูลเมื่อ POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // เพิ่มวิชา
    if (!empty($_POST['subject_name']) && !empty($_POST['subject_code'])) {
        $stmt = mysqli_prepare($connect, "INSERT INTO subject (subject_name, subject_code, teacher_id) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssi", $_POST['subject_name'], $_POST['subject_code'], $_POST['teacher_id']);
        if (mysqli_stmt_execute($stmt)) {
            $successMessage = "บันทึกข้อมูลวิชา " . htmlspecialchars($_POST['subject_name']) . " เรียบร้อยแล้ว";
        } else {
            $errorMessage = "เกิดข้อผิดพลาดในการบันทึกข้อมูลวิชา: " . mysqli_error($connect);
        }
    }

    // เพิ่มครู
    if (!empty($_POST['fname']) && !empty($_POST['lname'])) {
        $stmt = mysqli_prepare($connect, "INSERT INTO teacher (fname, lname, gender, dep, jobposition, certs) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssssss", $_POST['fname'], $_POST['lname'], $_POST['gender'], $_POST['dep'], $_POST['jobposition'], $_POST['certs']);
        if (mysqli_stmt_execute($stmt)) {
            $successMessage = "บันทึกข้อมูลครู " . htmlspecialchars($_POST['fname']) . " " . htmlspecialchars($_POST['lname']) . " เรียบร้อยแล้ว";
        } else {
            $errorMessage = "เกิดข้อผิดพลาดในการบันทึกข้อมูลครู: " . mysqli_error($connect);
        }
    }

    // เพิ่มห้องเรียน
    if (!empty($_POST['class_name'])) {
        // Check if classroom already exists
        $check_stmt = mysqli_prepare($connect, "SELECT class_name FROM classroom WHERE class_name = ?");
        mysqli_stmt_bind_param($check_stmt, "s", $_POST['class_name']);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $errorMessage = "ห้องเรียน " . htmlspecialchars($_POST['class_name']) . " มีอยู่ในระบบแล้ว";
        } else {
            $stmt = mysqli_prepare($connect, "INSERT INTO classroom (class_name) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $_POST['class_name']);
            if (mysqli_stmt_execute($stmt)) {
                $successMessage = "บันทึกข้อมูลห้องเรียน " . htmlspecialchars($_POST['class_name']) . " เรียบร้อยแล้ว";
            } else {
                $errorMessage = "เกิดข้อผิดพลาดในการบันทึกข้อมูลห้องเรียน: " . mysqli_error($connect);
            }
        }
    }

    // เพิ่มวิชาเรียนให้ห้องเรียน
    if (isset($_POST['assign_subject']) && !empty($_POST['class_id']) && !empty($_POST['subject_id'])) {
        if (assignSubjectToClass($connect, $_POST['class_id'], $_POST['subject_id'])) {
            $successMessage = "เพิ่มวิชาเรียนให้ห้องเรียนเรียบร้อยแล้ว";
        } else {
            $errorMessage = "เกิดข้อผิดพลาดในการเพิ่มวิชาเรียน: " . mysqli_error($connect);
        }
    }
}

// ดึงข้อมูลพื้นฐาน
$teachers = getTeachers($connect);
$subjects = getSubjects($connect);
$classrooms = getClassroomsWithSubjectCounts($connect, $class_id_field);

// ตรวจสอบว่ามีการส่ง class_id มาเพื่อดึงข้อมูลวิชาของห้องเรียนหรือไม่
if (isset($_GET['class_id']) && !empty($_GET['class_id'])) {
    $class_id = intval($_GET['class_id']);
    $subject_id_field = getTablePrimaryKey($connect, 'subject') ?: 'subject_id';
    
    $result = getSubjectsForClassroom($connect, $class_id, $subject_id_field);
    
    $class_subjects = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $class_subjects[] = [
                'subject_id' => $row[$subject_id_field],
                'subject_name' => $row['subject_name'],
                'subject_code' => $row['subject_code'],
                'fname' => $row['fname'],
                'lname' => $row['lname']
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'subjects' => $class_subjects]);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . mysqli_error($connect)]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลพื้นฐาน | ระบบจัดการโรงเรียน</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <link rel="stylesheet" href="rms_main_M.css">
    <style>
        :root {
            --primary-color: #ff7300;
            --secondary-color: #ff9940;
            --accent-color: #ffe0c2;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
            --danger-color: #dc3545;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Noto Sans Thai', sans-serif;
        }
             
        fieldset { 
            border: 1px solid #eee; 
            padding: 20px; 
            margin-bottom: 30px; 
            border-radius: 12px; 
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            background-color: white;
            transition: all 0.3s ease;
        }
        
        fieldset:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        legend { 
            font-size: 1.1rem; 
            font-weight: bold; 
            color: var(--primary-color);
            width: auto;
            padding: 0 15px;
            background-color: white;
            border-radius: 20px;
            border: 1px solid #eee;
        }
        
        .content-section {
            margin-bottom: 40px;
        }
          
        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        
        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 115, 0, 0.25);
        }
        
        .breadcrumb {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 15px 20px;
            margin-bottom: 25px;
        }

        .top-spacer {
            margin-top: 20px;
        }
        
        .btn-check-in {
            background: red;
            border: none;
            border-radius: 8px;
            color: white;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 3px 8px rgba(255, 115, 0, 0.25);
        }
        
        .btn-check-in:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 115, 0, 0.35);
            color: white;
        }
        
        .btn-check-in:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(255, 115, 0, 0.2);
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-bottom: 1px solid #f0f0f0;
            border-radius: 12px 12px 0 0 !important;
            padding: 18px 20px;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #f0f0f0;
        }
        
        .nav-tabs .nav-item {
            margin-bottom: -2px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            color: var(--dark-color);
            font-weight: 500;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            border-color: var(--secondary-color);
            background-color: rgba(255, 115, 0, 0.05);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            background-color: transparent;
        }
        
        .tab-content {
            background-color: white;
            border-radius: 0 0 12px 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
            
        .badge-pill {
            padding: 8px 15px;
            font-weight: 500;
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            border-radius: 12px 12px 0 0;
            background: linear-gradient(135deg, var(--light-color), white);
        }
        
        .alert {
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        /* Animation styles */
        .animated-icon {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Improved table styles */
        .table th {
            font-weight: 600;
            color: #555;
            border-top: none;
            background-color: #f8f9fa;
        }
        
        .table td {
            vertical-align: middle;
            padding: 12px 15px;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0,0,0,.01);
        }
        
        /* Form label improvements */
        label {
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }
        
        /* Add smooth loading transition */
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        
        /* Required field indicator */
        .required-field::after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }

        /* ปรับแต่งการแสดงผลบนอุปกรณ์มือถือ */
        @media (max-width: 767.98px) {
            .tab-content {
                padding: 15px;
            }
            
            fieldset {
                padding: 15px;
            }
            
            .icon-circle {
                width: 40px;
                height: 40px;
            }
            
            .section-title h4 {
                font-size: 1.1rem;
            }
        }

        /* เพิ่มแอนิเมชั่นเมื่อโหลดข้อมูล */
        .data-loading {
            position: relative;
        }
        
        .data-loading::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255,255,255,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1;
        }
        
        /* เพิ่มสีไฮไลท์สำหรับคอลัมน์ในตาราง */
        .highlight-column {
            background-color: rgba(255, 115, 0, 0.05);
        }
        
        /* ปรับแต่งไอคอน */
        .fa-plus-circle {
            color: var(--success-color);
        }
        
        .fa-book {
            color: #007bff;
        }
        
        .fa-door-open {
            color: #6f42c1;
        }
        
        /* Empty state styles */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .empty-state h5 {
            color: #777;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">กำลังโหลด...</span>
        </div>
    </div>

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

    <div class="container mt-4">
        <!-- Show success/error messages if any -->
        <?php if(isset($successMessage) && !empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" data-aos="fade-down">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $successMessage; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($errorMessage) && !empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" data-aos="fade-down">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $errorMessage; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" data-aos="fade-right" data-aos-duration="800">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="rms.php"><i class="fas fa-home"></i> หน้าหลัก</a></li>
                <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-cog"></i> จัดการข้อมูลพื้นฐาน</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="card mb-4" data-aos="fade-up" data-aos-duration="1000">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-database animated-icon mr-2"></i> จัดการข้อมูลพื้นฐาน</h4>
                <button class="btn btn-check-in btn-sm" data-toggle="modal" data-target="#helpModal">
                    <i class="fas fa-question-circle mr-1"></i> ช่วยเหลือ
                </button>
            </div>
            <div class="card-body">
                <p class="lead">กรุณากรอกข้อมูลที่จำเป็นเพื่อใช้ในการสร้างตารางเรียน เช่น รายวิชา ครูผู้สอน และห้องเรียน</p>
                <div class="progress mt-3" style="height: 6px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: 
                        <?php 
                            $total = 3; // total number of data types
                            $completed = 0;
                            if ($subjects && mysqli_num_rows($subjects) > 0) $completed++;
                            if (count($teachers) > 0) $completed++;
                            if ($classrooms && mysqli_num_rows($classrooms) > 0) $completed++;
                            echo ($completed / $total) * 100;
                        ?>%;" 
                        aria-valuenow="<?php echo ($completed / $total) * 100; ?>" 
                        aria-valuemin="0" 
                        aria-valuemax="100">
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <small>ข้อมูลที่กรอกแล้ว <?php echo $completed; ?> จาก <?php echo $total; ?> รายการ</small>
                    <small class="text-muted">กรอกข้อมูลเพิ่มเติมเพื่อดำเนินการต่อ</small>
                </div>
            </div>
        </div>

        <!-- Nav tabs -->
        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist" data-aos="fade-up" data-aos-duration="800">
            <li class="nav-item">
                <a class="nav-link active" id="add-tab" data-toggle="tab" href="#add" role="tab">
                    <i class="fas fa-plus-circle mr-2"></i> เพิ่มข้อมูล
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="subjects-tab" data-toggle="tab" href="#subjects" role="tab">
                    <i class="fas fa-book mr-2"></i> รายวิชา
                    <span class="badge badge-pill badge-light ml-1">
                        <?php echo ($subjects) ? mysqli_num_rows($subjects) : '0'; ?>
                    </span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="classrooms-tab" data-toggle="tab" href="#classrooms" role="tab">
                    <i class="fas fa-door-open mr-2"></i> ห้องเรียน
                    <span class="badge badge-pill badge-light ml-1">
                        <?php echo ($classrooms) ? mysqli_num_rows($classrooms) : '0'; ?>
                    </span>
                </a>
            </li>
        </ul>

        <!-- Tab content -->
        <div class="tab-content bg-white rounded shadow-sm">
            <!-- Add Data Tab -->
            <div class="tab-pane fade show active" id="add" role="tabpanel" aria-labelledby="add-tab">
                <form method="post" id="dataForm">

                    <!-- เพิ่มรายวิชา -->
                    <fieldset data-aos="fade-up" data-aos-delay="100">
                        <legend><i class="fas fa-book mr-2"></i> เพิ่มรายวิชา</legend>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="subject_name" class="required-field"><i class="fas fa-edit mr-1"></i> ชื่อวิชา</label>
                                <input name="subject_name" id="subject_name" class="form-control" placeholder="เช่น คณิตศาสตร์ 1" >
                            </div>
                            <div class="form-group col-md-3">
                                <label for="subject_code" class="required-field"><i class="fas fa-hashtag mr-1"></i> รหัสวิชา</label>
                                <input name="subject_code" id="subject_code" class="form-control" placeholder="เช่น ค31101">
                            </div>
                            <div class="form-group col-md-5">
                                <label for="teacher_id"><i class="fas fa-chalkboard-teacher mr-1"></i> ครูผู้สอน</label>
                                <select name="teacher_id" id="teacher_id" class="form-control">
                                    <option value="">เลือกครูผู้สอน</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= $t['user_id'] ?>"><?= htmlspecialchars($t['fname'] . ' ' . $t['lname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="text-right mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm add-subject-btn">
                                <i class="fas fa-plus mr-1"></i> เพิ่มรายวิชานี้
                            </button>
                        </div>
                    </fieldset>

                    <!-- เพิ่มครู -->
                    <fieldset data-aos="fade-up" data-aos-delay="200">
                        <legend><i class="fas fa-user-plus mr-2"></i> เพิ่มครู</legend>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="fname" class="required-field"><i class="fas fa-user mr-1"></i> ชื่อ</label>
                                <input name="fname" id="fname" class="form-control" placeholder="ชื่อ">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="lname" class="required-field"><i class="fas fa-user mr-1"></i> นามสกุล</label>
                                <input name="lname" id="lname" class="form-control" placeholder="นามสกุล">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="gender"><i class="fas fa-venus-mars mr-1"></i> เพศ</label>
                                <select name="gender" id="gender" class="form-control">
                                    <option value="Male">ชาย</option>
                                    <option value="Female">หญิง</option>
                                    <option value="Other">อื่น ๆ</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="dep"><i class="fas fa-building mr-1"></i> แผนก</label>
                                <input name="dep" id="dep" class="form-control" placeholder="เช่น คณิตศาสตร์">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="jobposition"><i class="fas fa-id-badge mr-1"></i> ตำแหน่ง</label>
                                <input name="jobposition" id="jobposition" class="form-control" placeholder="เช่น ครูชำนาญการ">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="certs"><i class="fas fa-certificate mr-1"></i> วุฒิบัตร</label>
                                <input name="certs" id="certs" class="form-control" placeholder="เช่น ศษ.บ (คณิตศาสตร์)">
                            </div>
                        </div>
                        <div class="text-right mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm add-teacher-btn">
                                <i class="fas fa-plus mr-1"></i> เพิ่มครูคนนี้
                            </button>
                        </div>
                    </fieldset>

                    <!-- เพิ่มห้องเรียน -->
                    <fieldset>
                        <legend><i class="fas fa-door-open mr-2"></i> เพิ่มห้องเรียน</legend>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="class_name" class="required-field"><i class="fas fa-school mr-1"></i> ชื่อห้องเรียน</label>
                                <input name="class_name" id="class_name" class="form-control" placeholder="เช่น ม.1/1">
                            </div>
                        </div>
                        <div class="text-right mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm add-classroom-btn">
                                <i class="fas fa-plus mr-1"></i> เพิ่มห้องเรียนนี้
                            </button>
                        </div>
                    </fieldset>
                    
                    <div class="mt-4 p-4 bg-light rounded">
                        <h5><i class="fas fa-plus-circle mr-2"></i> เพิ่มวิชาเรียนให้ห้องเรียน</h5>
                        <form method="post">
                            <input type="hidden" name="assign_subject" value="1">
                            <div class="form-row">
                                <div class="form-group col-md-5">
                                    <label for="class_id">เลือกห้องเรียน</label>
                                    <select name="class_id" id="class_id" class="form-control" required>
                                        <option value="">-- เลือกห้องเรียน --</option>
                                        <?php 
                                        if ($classrooms && mysqli_num_rows($classrooms) > 0) {
                                            mysqli_data_seek($classrooms, 0);
                                            while ($class = mysqli_fetch_assoc($classrooms)): 
                                        ?>
                                            <option value="<?= $class[$class_id_field] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                        <?php 
                                            endwhile;
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-5">
                                    <label for="subject_id">เลือกวิชาเรียน</label>
                                    <select name="subject_id" id="subject_id" class="form-control" required>
                                        <option value="">-- เลือกวิชาเรียน --</option>
                                        <?php 
                                        if ($subjects && mysqli_num_rows($subjects) > 0) {
                                            mysqli_data_seek($subjects, 0);
                                            while ($subject = mysqli_fetch_assoc($subjects)): 
                                        ?>
                                            <option value="<?= $subject[$subject_id_field] ?>"><?= htmlspecialchars($subject['subject_name']) ?> (<?= htmlspecialchars($subject['subject_code']) ?>)</option>
                                        <?php 
                                            endwhile;
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-plus mr-1"></i> เพิ่มวิชา
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                </form>
            </div>

            <!-- Subjects Tab -->
            <div class="tab-pane fade" id="subjects" role="tabpanel" aria-labelledby="subjects-tab">
                <div data-aos="fade-up">
                    <div class="section-title">
                        <div class="icon-circle">
                            <i class="fas fa-book"></i>
                        </div>
                        <h4>รายชื่อวิชา</h4>
                    </div>
                    
                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" class="form-control" id="searchSubjects" placeholder="ค้นหารายวิชา...">
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="subjectsTable">
                                    <thead class="bg-light">
                                        <tr>
                                            <th scope="col" width="5%">#</th>
                                            <th scope="col" width="40%">ชื่อวิชา</th>
                                            <th scope="col" width="20%">รหัสวิชา</th>
                                            <th scope="col" width="35%">ครูผู้สอน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $count = 1;
                                        if ($subjects && mysqli_num_rows($subjects) > 0) {
                                            mysqli_data_seek($subjects, 0);
                                            while ($subject = mysqli_fetch_assoc($subjects)): 
                                        ?>
                                            <tr>
                                                <td><?= $count++ ?></td>
                                                <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                                <td><span class="badge badge-pill badge-light"><?= htmlspecialchars($subject['subject_code']) ?></span></td>
                                                <td>
                                                    <?php if (!empty($subject['fname'])): ?>
                                                        <i class="fas fa-user-tie mr-1 text-muted"></i> <?= htmlspecialchars($subject['fname'] . ' ' . $subject['lname']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted"><i class="fas fa-user-slash mr-1"></i> ยังไม่กำหนด</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php 
                                            endwhile;
                                        } else {
                                        ?>
                                            <tr>
                                                <td colspan="4">
                                                    <div class="empty-state">
                                                        <i class="fas fa-book"></i>
                                                        <h5>ยังไม่มีข้อมูลรายวิชา</h5>
                                                        <p>กรุณาเพิ่มรายวิชาในแท็บ "เพิ่มข้อมูล"</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Classrooms Tab -->
            <div class="tab-pane fade" id="classrooms" role="tabpanel" aria-labelledby="classrooms-tab">
                <div data-aos="fade-up">
                    <div class="section-title">
                        <div class="icon-circle">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <h4>รายชื่อห้องเรียน</h4>
                    </div>
                    
                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" class="form-control" id="searchClassrooms" placeholder="ค้นหาห้องเรียน...">
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                            <table class="table table-hover mb-0" id="classroomsTable">
                                    <thead class="bg-light">
                                        <tr>
                                            <th scope="col" width="10%">#</th>
                                            <th scope="col" width="45%">ชื่อห้อง</th>
                                            <th scope="col" width="25%">จำนวนรายวิชา</th>
                                            <th scope="col" width="20%">การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $count = 1;
                                        if ($classrooms && mysqli_num_rows($classrooms) > 0) {
                                            mysqli_data_seek($classrooms, 0);
                                            while ($class = mysqli_fetch_assoc($classrooms)): 
                                        ?>
                                            <tr>
                                                <td><?= $count++ ?></td>
                                                <td><i class="fas fa-door-open mr-2 text-muted"></i><?= htmlspecialchars($class['class_name']) ?></td>
                                                <td><span class="badge badge-pill badge-info"><?= isset($class['subject_count']) ? $class['subject_count'] : '0' ?> วิชา</span></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary view-subjects" 
                                                            data-class-id="<?= $class[$class_id_field] ?>" 
                                                            data-class-name="<?= htmlspecialchars($class['class_name']) ?>">
                                                        <i class="fas fa-book-open mr-1"></i> ดูรายวิชา
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php 
                                            endwhile;
                                        } else {
                                        ?>
                                            <tr>
                                                <td colspan="4">
                                                    <div class="empty-state">
                                                        <i class="fas fa-door-open"></i>
                                                        <h5>ยังไม่มีข้อมูลห้องเรียน</h5>
                                                        <p>กรุณาเพิ่มห้องเรียนในแท็บ "เพิ่มข้อมูล"</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title"><i class="fas fa-question-circle mr-2"></i> วิธีใช้งาน</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-book text-primary mr-2"></i> การจัดการรายวิชา</h5>
                                    <p>คุณสามารถเพิ่มรายวิชาได้ในแท็บ "เพิ่มข้อมูล" โดยกรอกข้อมูลต่อไปนี้:</p>
                                    <ul>
                                        <li>ชื่อวิชา เช่น คณิตศาสตร์ 1</li>
                                        <li>รหัสวิชา เช่น ค31101</li>
                                        <li>ครูผู้สอน (เลือกจากรายชื่อครูที่มีอยู่ในระบบ)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-user-plus text-success mr-2"></i> การเพิ่มครู</h5>
                                    <p>คุณสามารถเพิ่มครูผู้สอนได้ในแท็บ "เพิ่มข้อมูล" โดยกรอกข้อมูลต่อไปนี้:</p>
                                    <ul>
                                        <li>ชื่อ-นามสกุล</li>
                                        <li>เพศ</li>
                                        <li>แผนก เช่น คณิตศาสตร์, วิทยาศาสตร์</li>
                                        <li>ตำแหน่ง และวุฒิบัตร</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-door-open text-info mr-2"></i> การเพิ่มห้องเรียน</h5>
                                    <p>คุณสามารถเพิ่มห้องเรียนได้โดยกรอกชื่อห้องเรียน เช่น ม.1/1, ม.2/3 เป็นต้น</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-cogs text-warning mr-2"></i> การตั้งค่าระบบ</h5>
                                    <p>คุณสามารถกำหนดค่าต่างๆ ของระบบได้ในแท็บ "ตั้งค่าระบบ" เช่น:</p>
                                    <ul>
                                        <li>จำนวนคาบเรียนต่อวัน</li>
                                        <li>จำนวนวันเรียนต่อสัปดาห์</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> หลังจากกรอกข้อมูลเสร็จแล้ว คลิกที่ปุ่ม "บันทึกข้อมูลทั้งหมด" เพื่อบันทึกข้อมูลลงในระบบ
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Subjects for Classroom Modal -->
<div class="modal fade" id="classSubjectsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="fas fa-book-open mr-2"></i> รายวิชาของห้อง <span id="classNameTitle"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="classSubjectsTable">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="5%">#</th>
                                <th scope="col" width="35%">ชื่อวิชา</th>
                                <th scope="col" width="20%">รหัสวิชา</th>
                                <th scope="col" width="40%">ครูผู้สอน</th>
                            </tr>
                        </thead>
                        <tbody id="classSubjectsTableBody">
                            <!-- จะถูกเติมโดย JavaScript -->
                            <tr>
                                <td colspan="4" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">กำลังโหลด...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="noSubjectsMessage" class="empty-state" style="display: none;">
                    <i class="fas fa-book-open"></i>
                    <h5>ยังไม่มีรายวิชาในห้องเรียนนี้</h5>
                    <p>คุณสามารถเพิ่มวิชาในแท็บ "เพิ่มข้อมูล"</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        once: true
    });

    // Hide preloader when page is loaded
    $(window).on('load', function() {
        $('#preloader').fadeOut(500, function() {
            $(this).remove();
        });
    });

    // Search functionality
    $("#searchSubjects, #searchClassrooms").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        var tableId = $(this).attr('id').replace('search', '') + 'Table';
        $("#" + tableId + " tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // Button click handlers for individual forms
    $(".add-subject-btn, .add-teacher-btn, .add-classroom-btn").on("click", function() {
        var type = $(this).attr('class').includes('subject') ? 'subject' : 
                  ($(this).attr('class').includes('teacher') ? 'teacher' : 'classroom');
        
        var requiredFields = {
            'subject': ['#subject_name', '#subject_code'],
            'teacher': ['#fname', '#lname'],
            'classroom': ['#class_name']
        };
        
        // Validate form
        var isValid = true;
        requiredFields[type].forEach(function(field) {
            if (!$(field).val()) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            alert("กรุณากรอกข้อมูลให้ครบถ้วน");
            return;
        }
        
        // Create a temporary form with just the needed fields
        var tempForm = $("<form>").attr("method", "post");
        
        // Add appropriate fields based on type
        if (type === 'subject') {
            tempForm.append($("#subject_name, #subject_code, #teacher_id").clone());
        } else if (type === 'teacher') {
            tempForm.append($("#fname, #lname, #gender, #dep, #jobposition, #certs").clone());
        } else {
            tempForm.append($("#class_name").clone());
        }
        
        $("body").append(tempForm);
        tempForm.submit();
    });

    // Form validation for main form
    $("#dataForm").on("submit", function(e) {
        var isValid = false;
        
        if ($("#subject_name").val() && $("#subject_code").val() || 
            $("#fname").val() && $("#lname").val() || 
            $("#class_name").val()) {
            isValid = true;
        }

    });
    
    // Initialize tooltips
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
    // Handle view subjects button click
$("body").on("click", ".view-subjects", function() {
    var classId = $(this).data("class-id");
    var className = $(this).data("class-name");
    
    $("#classNameTitle").text(className);
    $("#classSubjectsTableBody").html('<tr><td colspan="4" class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">กำลังโหลด...</span></div></td></tr>');
    $("#noSubjectsMessage").hide();
    
    // โหลดข้อมูลรายวิชาของห้องเรียน
    $.ajax({
        url: "get_class_subjects.php",
        type: "GET",
        data: { class_id: classId },
        dataType: "json",
        success: function(response) {
            if (response.success && response.subjects.length > 0) {
                var html = '';
                $.each(response.subjects, function(i, subject) {
                    html += '<tr>';
                    html += '<td>' + (i + 1) + '</td>';
                    html += '<td>' + subject.subject_name + '</td>';
                    html += '<td><span class="badge badge-pill badge-light">' + subject.subject_code + '</span></td>';
                    html += '<td>';
                    if (subject.fname) {
                        html += '<i class="fas fa-user-tie mr-1 text-muted"></i> ' + subject.fname + ' ' + subject.lname;
                    } else {
                        html += '<span class="text-muted"><i class="fas fa-user-slash mr-1"></i> ยังไม่กำหนด</span>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });
                $("#classSubjectsTableBody").html(html);
            } else {
                $("#classSubjectsTable").hide();
                $("#noSubjectsMessage").show();
            }
        },
        error: function() {
            $("#classSubjectsTableBody").html('<tr><td colspan="4" class="text-center text-danger"><i class="fas fa-exclamation-circle mr-2"></i>เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
        }
    });
    
    $("#classSubjectsModal").modal("show");
});
</script>
</body>
</html>