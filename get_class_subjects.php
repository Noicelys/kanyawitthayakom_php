<?php
require("connect.php");
session_start();

if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'ไม่ได้รับอนุญาต']);
    exit();
}

$connect = mysqli_connect("localhost", "root", "", "kanyawitthayakom");

if (!isset($_GET['class_id'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสห้องเรียน']);
    exit();
}

$class_id = intval($_GET['class_id']);

$check_subject = mysqli_query($connect, "SHOW KEYS FROM subject WHERE Key_name = 'PRIMARY'");
$subject_id_field = 'subject_id';
if ($row = mysqli_fetch_assoc($check_subject)) {
    $subject_id_field = $row['Column_name'];
}

$sql = "SELECT s.*, t.fname, t.lname 
        FROM class_subject cs
        JOIN subject s ON cs.subject_id = s.$subject_id_field
        LEFT JOIN teacher t ON s.teacher_id = t.user_id
        WHERE cs.class_id = ?";

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, "i", $class_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$subjects = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $subjects[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'subjects' => $subjects
]);
