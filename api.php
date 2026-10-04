<?php

use LDAP\Result;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

include_once 'dbConnect.php';
header('Content-Type: application/json');

$formCategory = $_REQUEST['formCategory'] ?? '';
$formtype = $_REQUEST['formType'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// if (!isset($_SESSION['username'])) {
//     header('Location: login.html');
//     exit;
// }

sleep(0);

function sendJsonResponse($httpStatusCode, $httpStatusDescription, $jsonResponseBody = '', $actionToPerform = '') {
    http_response_code($httpStatusCode);
    echo json_encode([
        'statusCode' => $httpStatusCode,
        'statusDescription' => $httpStatusDescription,
        'responseBody' => $jsonResponseBody,
        'perform' => $actionToPerform
    ]);
    exit; // Stops execution right after flushing the output
}

function validateTimeslot($startTime, $endTime, $slotType, $slotId = null) {
    /**
     * RETURNS:
     * 1 if success
     * 2 if outofbound
     * 3 if time not matching with the slotType
     * 4 if inverted timeslot
     * 5 if overlapping timeslot
     */
    global $conn;
    $parsedStartTime = (new DateTime($startTime))->getTimestamp();
    $parsedEndTime = (new DateTime($endTime))->getTimestamp();

    $minTime = (new DateTime('07:00'))->getTimestamp();
    $maxTime = (new DateTime('20:00'))->getTimestamp();
    if($parsedStartTime < $minTime || $parsedEndTime > $maxTime){
        return 2;
    }
    switch($slotType){
        case 'lecture':
            if($parsedEndTime - $parsedStartTime != 60*60){// 1 hour lecture
                return 3;
            }
            break;
        case 'practical':
            if($parsedEndTime - $parsedStartTime != 60*120){// 2 hour practical
                return 3;
            }
            break;
        case 'break':
            if($parsedEndTime - $parsedStartTime != 60*15){// 15 minute break
                return 3;
            }
            break;
        default:
            break;
    }
    if ($parsedStartTime >= $parsedEndTime) {
        return 4; // Inverted timeslot
    }else {
        $query = "SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT WHERE ? < END_TIME AND ? > START_TIME AND SLOT_ID != ? AND SLOT_TYPE = ? LIMIT 1";
        $result = $conn->execute_query($query, [$startTime, $endTime, $slotId ?? 0, $slotType]);
        if ($result->num_rows > 0) {
            return 5; // Overlapping Timeslot
        }
    }
    return 1;
}
function isValidName(string $str){
    $pattern = preg_match("/^[A-Za-z\s.]+$/",trim($str)) === 1;
    return ($pattern);
}
function validateClassroom($floor,$room,$capacity,$startTime,$endTime){
    global $conn;
    if($capacity<20 || $capacity>200) return 2; //too small or too large classroom

    if ( ((new DateTime($startTime))->getTimestamp()) >= ((new DateTime($endTime))->getTimestamp()) ) return 4; //Inverted Availability


    // $query = 'SELECT * FROM CLASSROOM WHERE ROOM_NUMBER = ?';
    // $result = $conn->execute_query($query,[$room]);
    // if($result->num_rows>0) return 4; //room number already exists

    return 1;
}
function validateDepartment($fullname,$shortname){
    /**
     * Returns
     * 1 for Success
     * 2 for contains invalid characters
     */
    global $conn;
    if(isValidName($fullname) && isValidName($shortname)) return 1;
    else return 2; //Numbers not allowed
}
function validateProgramme($fullname, $shortname){
    /**
     * Returns
     * 1 for Success
     * 2 for contains invalid characters
     */
    global $conn;
    if(isValidName($fullname) && isValidName($shortname)) return 1;
    else return 2; //Numbers not allowed
}
function validateCourse($fullname, $shortname){
    /**
     * Returns
     * 1 for Success
     * 2 for contains invalid characters
     */
    global $conn;
    if(isValidName($fullname) && isValidName($shortname)) return 1;
    else return 2; //Numbers not allowed
}
function updateCounts($connection) {
    $query = "
        SELECT
            (SELECT COUNT(*) FROM TIMESLOT) AS timeslotCount,
            (SELECT COUNT(*) FROM CLASSROOM WHERE CAPACITY IS NOT NULL) AS classroomCount,
            (SELECT COUNT(*) FROM DEPARTMENT) AS departmentCount,
            (SELECT COUNT(*) FROM TEACHER) AS teacherCount,
            (SELECT COUNT(*) FROM PROGRAMME) AS programmeCount,
            (SELECT COUNT(*) FROM COURSE) AS courseCount,
            (SELECT COUNT(*) FROM COURSE WHERE ISOPTIONAL = TRUE) AS optionalCourseCount,
            (SELECT COUNT(*) FROM DIVISION) AS divisionCount,
            (SELECT COUNT(*) FROM OPTED_BY) AS mapOptionalCourseCount,
            (SELECT COUNT(*) FROM TEACHES) AS workloadCount
    ";
    $result = $connection->execute_query($query);
    $counts = $result->fetch_assoc();
    $file = "lockData.json";
    $data = json_decode(file_get_contents($file), true);
    $countMap = [
        0  => $counts['timeslotCount'],
        1  => $counts['classroomCount'],
        2  => $counts['departmentCount'],
        3  => $counts['teacherCount'],
        4  => $counts['programmeCount'],
        5  => $counts['courseCount'],
        6  => $counts['optionalCourseCount'],
        7  => $counts['divisionCount'],
        8  => $counts['mapOptionalCourseCount'],
        9  => $counts['workloadCount']
    ];
    foreach ($data as &$module) {
        $sequence = $module['sequence'];
        if (isset($countMap[$sequence])) {
            $module['dataCount'] = (int)$countMap[$sequence];
        }
    }
    unset($module);
    file_put_contents($file,json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),);
    return $data;
}
function manageModuleView($connection, $sequence, $action) {
    // Map sequence number to module view definition
    $views = [
        4 => [
            'name' => 'VIEW_MINIMAL_PROGRAMME',
            'sql'  => "CREATE OR REPLACE VIEW VIEW_MINIMAL_PROGRAMME AS
                       SELECT p.DEPARTMENT_ID, p.PROGRAMME_ID, p.LONG_NAME, p.SHORT_NAME, COUNT(c.PROGRAMME_ID) AS DURATION
                       FROM PROGRAMME p
                       LEFT JOIN CONSISTS c ON p.PROGRAMME_ID = c.PROGRAMME_ID
                       GROUP BY p.DEPARTMENT_ID, p.PROGRAMME_ID, p.LONG_NAME, p.SHORT_NAME
                       ORDER BY p.LONG_NAME"
        ],
        5 => [
            'name' => 'VIEW_MINIMAL_COURSE',
            'sql'  => "CREATE OR REPLACE VIEW VIEW_MINIMAL_COURSE AS
                       SELECT c.COURSE_ID, c.PROGRAMME_ID, p.DEPARTMENT_ID, p.SHORT_NAME AS PROGRAMME_NAME,
                              d.SHORT_NAME AS DEPARTMENT_NAME, c.LONG_NAME, c.SHORT_NAME, c.WEEKLY_LECTURES,
                              c.ISPRACTICAL, c.ISOPTIONAL, c.YEAR_NUMBER, c.SEMESTER, c.OPTIONAL_ID
                       FROM COURSE c
                       LEFT JOIN PROGRAMME p ON c.PROGRAMME_ID = p.PROGRAMME_ID
                       LEFT JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                       ORDER BY p.DEPARTMENT_ID, PROGRAMME_NAME, c.YEAR_NUMBER, c.SEMESTER"
        ],
        6 => [
            'name' => 'VIEW_MINIMAL_OPTIONALCOURSE',
            'sql'  => "CREATE OR REPLACE VIEW VIEW_MINIMAL_OPTIONALCOURSE AS
                       SELECT d.DEPARTMENT_ID, d.SHORT_NAME AS DEPARTMENT_SHORT_NAME, p.PROGRAMME_ID,
                              p.SHORT_NAME AS PROGRAMME_SHORT_NAME, y.YEAR_NUMBER, y.YEAR_NAME, c.COURSE_ID,
                              c.SEMESTER, c.LONG_NAME AS COURSE_FULL_NAME, c.SHORT_NAME AS COURSE_SHORT_NAME,
                              c.ISOPTIONAL, c.OPTIONAL_ID, op.SHORT_NAME AS OPTIONAL_COURSE_NAME, c.ISPRACTICAL, c.WEEKLY_LECTURES
                       FROM COURSE c
                       LEFT JOIN COURSE op ON op.COURSE_ID = c.OPTIONAL_ID
                       LEFT JOIN PROGRAMME p ON c.PROGRAMME_ID = p.PROGRAMME_ID
                       LEFT JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                       LEFT JOIN YEAR y ON c.YEAR_NUMBER = y.YEAR_NUMBER
                       WHERE c.ISOPTIONAL = TRUE AND (c.OPTIONAL_ID IS NULL OR c.COURSE_ID < c.OPTIONAL_ID)
                       ORDER BY d.DEPARTMENT_ID, p.PROGRAMME_ID, y.YEAR_NUMBER, c.SEMESTER"
        ],
        7 => [
            'name' => 'VIEW_MINIMAL_DIVISION',
            'sql'  => "CREATE OR REPLACE VIEW VIEW_MINIMAL_DIVISION AS
                       SELECT d.DEPARTMENT_ID, d.SHORT_NAME AS DEPARTMENT_NAME, p.PROGRAMME_ID,
                              p.SHORT_NAME AS PROGRAMME_NAME, y.YEAR_NUMBER, y.YEAR_NAME, dv.DIVISION_ID,
                              dv.NAME AS DIVISION_NAME, dv.STUDENT_COUNT, dv.CLASSROOM_ID, dv.START_TIME_ID, dv.END_TIME_ID
                       FROM DEPARTMENT AS d
                       JOIN PROGRAMME AS p ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                       JOIN CONSISTS AS c ON c.PROGRAMME_ID = p.PROGRAMME_ID
                       JOIN YEAR AS y ON y.YEAR_NUMBER = c.YEAR_NUMBER
                       LEFT JOIN DIVISION AS dv ON dv.PROGRAMME_ID = c.PROGRAMME_ID AND dv.YEAR_NUMBER = c.YEAR_NUMBER
                       ORDER BY dv.STUDENT_COUNT, d.SHORT_NAME, p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME"
        ],
        8 => [
            'name' => 'VIEW_MINIMAL_OPTIONALCOURSEMAPPER',
            'sql'  => "CREATE OR REPLACE VIEW VIEW_MINIMAL_OPTIONALCOURSEMAPPER AS
                       SELECT d.DEPARTMENT_ID, d.SHORT_NAME AS DEPARTMENT_SHORT_NAME, p.PROGRAMME_ID,
                              p.SHORT_NAME AS PROGRAMME_SHORT_NAME, y.YEAR_NUMBER, y.YEAR_NAME, c.COURSE_ID,
                              c.SEMESTER, c.LONG_NAME AS COURSE_FULL_NAME, c.SHORT_NAME AS COURSE_SHORT_NAME,
                              c.ISOPTIONAL, c.OPTIONAL_ID, op.SHORT_NAME AS OPTIONAL_COURSE_NAME, c.ISPRACTICAL,
                              c.WEEKLY_LECTURES, ob.DIVISION_ID AS MAPPED_DIVISION_ID, dv.NAME AS MAPPED_DIVISION_NAME
                       FROM COURSE c
                       LEFT JOIN COURSE op ON op.COURSE_ID = c.OPTIONAL_ID
                       LEFT JOIN OPTED_BY ob ON ob.COURSE_ID = c.COURSE_ID
                       LEFT JOIN DIVISION dv ON dv.DIVISION_ID = ob.DIVISION_ID
                       JOIN PROGRAMME p ON c.PROGRAMME_ID = p.PROGRAMME_ID
                       JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                       JOIN YEAR y ON c.YEAR_NUMBER = y.YEAR_NUMBER
                       WHERE c.ISOPTIONAL = TRUE AND c.OPTIONAL_ID IS NOT NULL
                       ORDER BY d.DEPARTMENT_ID, p.PROGRAMME_ID, y.YEAR_NUMBER, c.SEMESTER, c.COURSE_ID"
        ],
        9 => [
            'name' => 'VIEW_MINIMAL_WORKLOAD',
            'sql'  => "CREATE OR REPLACE VIEW VIEW_MINIMAL_WORKLOAD AS
                       SELECT t.WORKLOAD_ID, t.TEACHER_ID, t.COURSE_ID, t.DIVISION_ID, d.DEPARTMENT_ID,
                              d.SHORT_NAME AS DEPARTMENT_NAME, p.PROGRAMME_ID, p.SHORT_NAME AS PROGRAMME_NAME,
                              y.YEAR_NUMBER, y.YEAR_NAME, dv.NAME AS DIVISION_NAME, c.SEMESTER, c.LONG_NAME AS COURSE_NAME,
                              c.SHORT_NAME AS COURSE_SHORT_NAME, t.LECTURE_COUNT, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                       FROM TEACHES t
                       INNER JOIN TEACHER tr ON t.TEACHER_ID = tr.TEACHER_ID
                       INNER JOIN COURSE c ON t.COURSE_ID = c.COURSE_ID
                       INNER JOIN DIVISION dv ON t.DIVISION_ID = dv.DIVISION_ID
                       INNER JOIN PROGRAMME p ON c.PROGRAMME_ID = p.PROGRAMME_ID
                       INNER JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                       INNER JOIN YEAR y ON c.YEAR_NUMBER = y.YEAR_NUMBER
                       ORDER BY d.LONG_NAME, p.LONG_NAME, y.YEAR_NUMBER, dv.NAME, c.SEMESTER, c.LONG_NAME, TEACHER_NAME"
        ]
    ];

    if (!isset($views[$sequence])) {
        return;
    }

    $viewName = $views[$sequence]['name'];
    
    if ($action === 'create') {
        $connection->query($views[$sequence]['sql']);
    } elseif ($action === 'drop') {
        $connection->query("DROP VIEW IF EXISTS {$viewName}");
    }
}
function isViewExists($connection, $viewName) {
    $result = $connection->query("SHOW TABLES LIKE '{$viewName}'");
    return ($result && $result->num_rows > 0);
}
function isLockable($connection,$sequence){
    if($sequence == 0){
        $result = $connection->execute_query("SELECT COUNT(*) AS count FROM TIMESLOT");
        return ($result->fetch_assoc()['count'] > 0);
    }
    elseif($sequence == 1){
        $result = $connection->execute_query("SELECT COUNT(*) AS count FROM CLASSROOM WHERE CAPACITY IS NULL");
        return ($result->fetch_assoc()['count'] == 0);
    }
    elseif($sequence == 2){
        $result = $connection->execute_query("SELECT COUNT(*) AS count FROM DEPARTMENT");
        return ($result->fetch_assoc()['count'] > 0);
    }
    elseif($sequence == 3){
        $result = $connection->execute_query("
            SELECT COUNT(*) AS count
            FROM DEPARTMENT d
            LEFT JOIN TEACHER t
                ON d.DEPARTMENT_ID = t.DEPARTMENT_ID
            WHERE t.TEACHER_ID IS NULL;
        ");
        return ($result->fetch_assoc()['count'] == 0);
    }
    elseif($sequence == 4){
        $result = $connection->execute_query("
            SELECT COUNT(*) AS count
            FROM DEPARTMENT d
            LEFT JOIN PROGRAMME p
                ON d.DEPARTMENT_ID = p.DEPARTMENT_ID
            WHERE p.PROGRAMME_ID IS NULL;
        ");
        return ($result->fetch_assoc()['count'] == 0);
    }
    elseif($sequence == 5){
        $result = $connection->execute_query("
            SELECT COUNT(*) AS count
            FROM PROGRAMME p
            LEFT JOIN COURSE c
                ON p.PROGRAMME_ID = c.PROGRAMME_ID
            WHERE c.COURSE_ID IS NULL;
        ");
        return ($result->fetch_assoc()['count'] == 0);
    }
    elseif($sequence == 6){
        $result = $connection->execute_query("
            SELECT COUNT(*) AS count
            FROM COURSE 
            WHERE ISOPTIONAL IS TRUE AND OPTIONAL_ID IS NULL;
        ");
        return ($result->fetch_assoc()['count'] == 0);
    }
    elseif($sequence == 7){
        $result = $connection->execute_query("
            SELECT COUNT(*) AS count
            FROM DIVISION 
            WHERE STUDENT_COUNT IS NULL;            
        ");
        return ($result->fetch_assoc()['count'] == 0);
    }
    elseif($sequence == 8){
        $result = $connection->execute_query("
            SELECT COUNT(*) AS count
            FROM COURSE c
            LEFT JOIN OPTED_BY ob
                ON c.COURSE_ID = ob.COURSE_ID
            WHERE c.ISOPTIONAL = TRUE
            AND ob.COURSE_ID IS NULL;
      
        ");
        return ($result->fetch_assoc()['count'] == 0);
    }
    elseif($sequence == 9){
        $file = "lockData.json";
        $data = json_decode(file_get_contents($file), true) ?? [];
        foreach ($data as $module) {
            if ($module['sequence'] < 9 && empty($module['isLocked'])) {
                return false; // Cannot lock sequence 9 if any prior module is unlocked
            }
        }
        return true;
    }
    return false;
}
function arePrecedingModulesLocked() {
    $file = "lockData.json";
    if (!file_exists($file)) return false;
    $data = json_decode(file_get_contents($file), true) ?? [];
    
    foreach ($data as $module) {
        // Check sequences 0 through 9 (all modules prior to Timetable generation)
        if (isset($module['sequence']) && $module['sequence'] <= 9) {
            if (empty($module['isLocked'])) {
                return false;
            }
        }
    }
    return true;
}


try {
    if($formCategory == 'getMinimalData'){
        global $conn;
        $response = [];
        if($formtype == 'department'){
            $sql = "SELECT DEPARTMENT_ID, LONG_NAME, SHORT_NAME FROM DEPARTMENT ORDER BY LONG_NAME";
            $result = mysqli_query($conn, $sql);
            $response['departments'] = [];
            while($row = mysqli_fetch_assoc($result)) {$response['departments'][] = $row;}
        }
        elseif ($formtype == 'programme') {
            $sql = isViewExists($conn, 'VIEW_MINIMAL_PROGRAMME') 
                ? "SELECT * FROM VIEW_MINIMAL_PROGRAMME" 
                : "SELECT p.DEPARTMENT_ID, p.PROGRAMME_ID, p.LONG_NAME, p.SHORT_NAME, COUNT(c.PROGRAMME_ID) AS DURATION
                FROM PROGRAMME p
                LEFT JOIN CONSISTS c ON p.PROGRAMME_ID = c.PROGRAMME_ID
                GROUP BY p.DEPARTMENT_ID, p.PROGRAMME_ID, p.LONG_NAME, p.SHORT_NAME
                ORDER BY p.LONG_NAME";

            $result = mysqli_query($conn, $sql);
            $response['programmes'] = [];
            while ($row = mysqli_fetch_assoc($result)) { $response['programmes'][] = $row; }
        }
        elseif($formtype == 'classroom'){
            $sql = "SELECT CLASSROOM_ID, ROOM_NUMBER, CAPACITY FROM CLASSROOM ORDER BY ROOM_NUMBER";
            $result = mysqli_query($conn, $sql);
            $response['classrooms'] = [];
            while($row = mysqli_fetch_assoc($result)) {$response['classrooms'][] = $row;}
        }
        elseif($formtype == 'timeslot'){
            $sql = "SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT WHERE SLOT_TYPE = 'lecture' ORDER BY SLOT_TYPE, START_TIME";
            $result = mysqli_query($conn, $sql);
            $response['timeslots'] = [];
            while($row = mysqli_fetch_assoc($result)) {$response['timeslots'][] = $row;}
        }
        elseif($formtype == 'year'){
            $sql = "SELECT YEAR_NUMBER, YEAR_NAME FROM YEAR";
            $result = mysqli_query($conn, $sql);
            $response['years'] = [];
            while($row = mysqli_fetch_assoc($result)) {$response['years'][] = $row;}
        }
        elseif($formtype == 'consists'){
            $sql = "SELECT YEAR_NUMBER, PROGRAMME_ID FROM CONSISTS";
            $result = mysqli_query($conn, $sql);
            $response['consists'] = [];
            while($row = mysqli_fetch_assoc($result)) {$response['consists'][] = $row;}
        }
        elseif ($formtype == 'course') {
            $sql = isViewExists($conn, 'VIEW_MINIMAL_COURSE') 
                ? "SELECT * FROM VIEW_MINIMAL_COURSE" 
                : "SELECT c.COURSE_ID, c.PROGRAMME_ID, p.DEPARTMENT_ID, p.SHORT_NAME AS PROGRAMME_NAME, d.SHORT_NAME AS DEPARTMENT_NAME, c.LONG_NAME, c.SHORT_NAME, c.WEEKLY_LECTURES, c.ISPRACTICAL, c.ISOPTIONAL, c.YEAR_NUMBER, c.SEMESTER, c.OPTIONAL_ID
                FROM COURSE c
                LEFT JOIN PROGRAMME p ON c.PROGRAMME_ID = p.PROGRAMME_ID
                LEFT JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                ORDER BY p.DEPARTMENT_ID, PROGRAMME_NAME, c.YEAR_NUMBER, c.SEMESTER";

            $result = mysqli_query($conn, $sql);
            $response['courses'] = [];
            while ($row = mysqli_fetch_assoc($result)) { $response['courses'][] = $row; }
        }
        elseif ($formtype == 'optionalcourse') {
            $sql = isViewExists($conn, 'VIEW_MINIMAL_OPTIONALCOURSE') 
                ? "SELECT * FROM VIEW_MINIMAL_OPTIONALCOURSE" 
                : "SELECT d.DEPARTMENT_ID, d.SHORT_NAME AS DEPARTMENT_SHORT_NAME, p.PROGRAMME_ID, p.SHORT_NAME AS PROGRAMME_SHORT_NAME, y.YEAR_NUMBER, y.YEAR_NAME, c.COURSE_ID, c.SEMESTER, c.LONG_NAME AS COURSE_FULL_NAME, c.SHORT_NAME AS COURSE_SHORT_NAME, c.ISOPTIONAL, c.OPTIONAL_ID, op.SHORT_NAME AS OPTIONAL_COURSE_NAME, c.ISPRACTICAL, c.WEEKLY_LECTURES
                FROM COURSE c
                LEFT JOIN COURSE op ON op.COURSE_ID = c.OPTIONAL_ID
                LEFT JOIN PROGRAMME p ON c.PROGRAMME_ID = p.PROGRAMME_ID
                LEFT JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                LEFT JOIN YEAR y ON c.YEAR_NUMBER = y.YEAR_NUMBER
                WHERE c.ISOPTIONAL = TRUE AND (c.OPTIONAL_ID IS NULL OR c.COURSE_ID < c.OPTIONAL_ID)
                ORDER BY d.DEPARTMENT_ID, p.PROGRAMME_ID, y.YEAR_NUMBER, c.SEMESTER";

            $result = mysqli_query($conn, $sql);
            $response['optionalcourses'] = [];
            while ($row = mysqli_fetch_assoc($result)) { $response['optionalcourses'][] = $row; }
        }
        elseif ($formtype == 'division') {
            $sql = isViewExists($conn, 'VIEW_MINIMAL_DIVISION') 
                ? "SELECT * FROM VIEW_MINIMAL_DIVISION" 
                : "SELECT d.DEPARTMENT_ID, d.SHORT_NAME AS DEPARTMENT_NAME, p.PROGRAMME_ID, p.SHORT_NAME AS PROGRAMME_NAME, y.YEAR_NUMBER, y.YEAR_NAME, dv.DIVISION_ID, dv.NAME AS DIVISION_NAME, dv.STUDENT_COUNT, dv.CLASSROOM_ID, dv.START_TIME_ID, dv.END_TIME_ID
                FROM DEPARTMENT AS d
                JOIN PROGRAMME AS p ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                JOIN CONSISTS AS c ON c.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN YEAR AS y ON y.YEAR_NUMBER = c.YEAR_NUMBER
                LEFT JOIN DIVISION AS dv ON dv.PROGRAMME_ID = c.PROGRAMME_ID AND dv.YEAR_NUMBER = c.YEAR_NUMBER
                ORDER BY dv.STUDENT_COUNT, d.SHORT_NAME, p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME";

            $result = mysqli_query($conn, $sql);
            $response['divisions'] = [];
            while ($row = mysqli_fetch_assoc($result)) { $response['divisions'][] = $row; }
        }
        elseif ($formtype == 'optionalcoursemapper') {
            $sql = isViewExists($conn, 'VIEW_MINIMAL_OPTIONALCOURSEMAPPER') 
                ? "SELECT * FROM VIEW_MINIMAL_OPTIONALCOURSEMAPPER" 
                : "SELECT d.DEPARTMENT_ID, d.SHORT_NAME AS DEPARTMENT_SHORT_NAME, p.PROGRAMME_ID, p.SHORT_NAME AS PROGRAMME_SHORT_NAME, y.YEAR_NUMBER, y.YEAR_NAME, c.COURSE_ID, c.SEMESTER, c.LONG_NAME AS COURSE_FULL_NAME, c.SHORT_NAME AS COURSE_SHORT_NAME, c.ISOPTIONAL, c.OPTIONAL_ID, op.SHORT_NAME AS OPTIONAL_COURSE_NAME, c.ISPRACTICAL, c.WEEKLY_LECTURES, ob.DIVISION_ID AS MAPPED_DIVISION_ID, dv.NAME AS MAPPED_DIVISION_NAME
                FROM COURSE c
                LEFT JOIN COURSE op ON op.COURSE_ID = c.OPTIONAL_ID
                LEFT JOIN OPTED_BY ob ON ob.COURSE_ID = c.COURSE_ID
                LEFT JOIN DIVISION dv ON dv.DIVISION_ID = ob.DIVISION_ID
                JOIN PROGRAMME p ON c.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                JOIN YEAR y ON c.YEAR_NUMBER = y.YEAR_NUMBER
                WHERE c.ISOPTIONAL = TRUE AND c.OPTIONAL_ID IS NOT NULL
                ORDER BY d.DEPARTMENT_ID, p.PROGRAMME_ID, y.YEAR_NUMBER, c.SEMESTER, c.COURSE_ID";

            $result = mysqli_query($conn, $sql);
            $response['optionalcourses'] = [];
            while ($row = mysqli_fetch_assoc($result)) { $response['optionalcourses'][] = $row; }
        }
        elseif ($formtype == 'workload') {
            $sql = isViewExists($conn, 'VIEW_MINIMAL_WORKLOAD') 
                ? "SELECT * FROM VIEW_MINIMAL_WORKLOAD" 
                : "SELECT t.WORKLOAD_ID, t.TEACHER_ID, t.COURSE_ID, t.DIVISION_ID, d.DEPARTMENT_ID, d.SHORT_NAME AS DEPARTMENT_NAME, p.PROGRAMME_ID, p.SHORT_NAME AS PROGRAMME_NAME, y.YEAR_NUMBER, y.YEAR_NAME, dv.NAME AS DIVISION_NAME, c.SEMESTER, c.LONG_NAME AS COURSE_NAME, c.SHORT_NAME AS COURSE_SHORT_NAME, t.LECTURE_COUNT, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                FROM TEACHES t
                INNER JOIN TEACHER tr ON t.TEACHER_ID = tr.TEACHER_ID
                INNER JOIN COURSE c ON t.COURSE_ID = c.COURSE_ID
                INNER JOIN DIVISION dv ON t.DIVISION_ID = dv.DIVISION_ID
                INNER JOIN PROGRAMME p ON c.PROGRAMME_ID = p.PROGRAMME_ID
                INNER JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                INNER JOIN YEAR y ON c.YEAR_NUMBER = y.YEAR_NUMBER
                ORDER BY d.LONG_NAME, p.LONG_NAME, y.YEAR_NUMBER, dv.NAME, c.SEMESTER, c.LONG_NAME, TEACHER_NAME";

            $result = mysqli_query($conn, $sql);
            $response['teaches'] = [];
            while ($row = mysqli_fetch_assoc($result)) { $response['teaches'][] = $row; }
        }
        
        header('Content-Type: application/json');
        sendJsonResponse(200, "The Minimal JSON generated", $response);
    }
    elseif($formCategory == 'load_existing'){
        $tablename = $_POST['tablename'];
        $query = "SELECT * FROM ". $tablename;
        $result = $conn->execute_query($query);
        $parsedResult = $result->fetch_all();
        sendJsonResponse(200,'Existing Data of '.$tablename.' table',$parsedResult);
    }
    elseif($formCategory == 'dashboard'){
        updateCounts($conn);
        $file = "lockData.json";
        $data = json_decode(file_get_contents($file), true);
        if($formtype == 'get_dashboard'){
            sendJsonResponse(200,"Dashboard fetched",$data);
        }
        elseif($formtype == 'lock_module'){
            $sequence = $_POST['sequence'];
            if (isLockable($conn, $sequence)) {
                foreach ($data as $key => $value) {
                    if ($value['sequence'] == ($sequence)) {
                        if ($data[$key]['isOngoing'] == true && $data[$key]['isLocked'] == false) {
                            $data[$key]['isLocked'] = true;
                            $data[$key]['isReady'] = true;
                            $data[$key]['isOngoing'] = false;
                            $data[$key]['viewReady'] = true; // Update flag in JSON

                            // Create view when locked
                            manageModuleView($conn, $sequence, 'create');
                        }
                    } elseif ($value['sequence'] == ($sequence + 1)) {
                        $data[$key]['isLocked'] = false;
                        $data[$key]['isOngoing'] = true;
                        $data[$key]['isReady'] = false;
                    }
                }
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));      
                sendJsonResponse(200, "Module locked", "The module was LOCKED successfully"); 
            }
            else{
                if($sequence == 0){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Timeslot. There must exist atleast one Timeslot.");
                }
                elseif($sequence == 1){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Classroom. The capcity of all the classrooms must be filled.");
                }
                elseif($sequence == 2){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Department. There must exist atleast one Department.");
                }
                elseif($sequence == 3){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Teacher. There must exist atleast one Teacher in every department.");
                }
                elseif($sequence == 4){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Programme. There must exist atleast one Programme in every department.");
                }
                elseif($sequence == 5){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Course. There must exist atleast one Course in every programme.");
                }
                elseif($sequence == 6){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Optional Course. All the optional courses must be mapped with respective optional course.");
                }
                elseif($sequence == 7){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Division. The student count of all the divisions must be filled.");
                }
                elseif($sequence == 8){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Map Optional Course Group. All the optional courses must be mapped to a division(s).");
                }
                elseif($sequence == 9){
                    sendJsonResponse(422, "Cannot Lock","Cannot Lock Workload. UNDEFINED error.");
                }
                else{
                    sendJsonResponse(400,"?","???");
                }
            }
                
        }
        elseif ($formtype == 'unlock_module') {
            $sequence = $_POST['sequence'];
            foreach ($data as $key => $value) {
                if ($value['sequence'] == ($sequence)) {
                    $data[$key]['isLocked'] = false;
                    $data[$key]['isReady'] = false;
                    $data[$key]['isOngoing'] = true;
                    $data[$key]['viewReady'] = false;

                    manageModuleView($conn, $sequence, 'drop');
                } elseif ($value['sequence'] > ($sequence)) {
                    $data[$key]['isLocked'] = true;
                    $data[$key]['isReady'] = false;
                    $data[$key]['isOngoing'] = false;
                    $data[$key]['isPending'] = true;
                    $data[$key]['viewReady'] = false;

                    manageModuleView($conn, $value['sequence'], 'drop');
                }
            }
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));           
            sendJsonResponse(200, "Module unlocked", "Module UNLOCKED");    
        }
    }
    elseif($formCategory == 'timeslot'){
        if($formtype == 'add_timeslot'){
            $startTime = $_POST['startTime'] ?? '';
            $endTime = $_POST['endTime'] ?? '';
            $slotType = $_POST['slotType'] ?? "lecture";

            $isValid = validateTimeslot($startTime, $endTime, $slotType);

            if ($isValid === 1) {
                $query = "INSERT INTO TIMESLOT(START_TIME, END_TIME, SLOT_TYPE) VALUES(?,?,?)";
                $result = $conn->execute_query($query, [$startTime, $endTime, $slotType]);
                if (mysqli_affected_rows($conn) == 1) {
                    sendJsonResponse(201, 'Created', 'Timeslot created successfully');
                } else {
                    sendJsonResponse(400, 'Try again', "Couldn't create timeslot");
                }
            } elseif ($isValid === 2) {
                sendJsonResponse(422, 'Timeslot OutOfBound', 'The timeslot must be between 07:00 AM to 08:00 PM.');
            } elseif ($isValid === 3) {
                sendJsonResponse(422, 'Mismatched SlotType', 'The timeslot does not match the slot type.');
            } elseif ($isValid === 4) {
                sendJsonResponse(422, 'Inverted Timeslot', 'The Start Time is greater than the End Time.');
            } elseif ($isValid === 5) {
                sendJsonResponse(409, 'Overlapping Timeslot', 'The timeslot overlaps with an existing record.');
            } else {
                sendJsonResponse(400, 'Invalid Input', 'Start Time or End Time is invalid.');
            }
        }
        elseif($formtype == 'get_one_timeslot'){ 
            $slotId = $_POST['slotId'];
            $query = "SELECT * FROM TIMESLOT WHERE slot_id = ?;";
            $result = $conn->execute_query($query,[$slotId]);
            if($result->num_rows>0){
                sendJsonResponse(200,"Timeslot found.",$result->fetch_assoc());
                exit;
            }
            sendJsonResponse(400,"Timeslot NOT found.");
        }
        elseif($formtype == 'update_timeslot'){
            $slotId = $_POST['slotId'] ?? null;
            $startTime = $_POST['startTime'] ?? '';
            $endTime = $_POST['endTime'] ?? '';
            $slotType = $_POST['slotType'] ?? "No";
            $isValid = validateTimeslot($startTime, $endTime, $slotType, $slotId);
            if ($isValid === 1) {
                $query = "UPDATE TIMESLOT SET START_TIME = ?, END_TIME = ?, SLOT_TYPE = ? WHERE SLOT_ID = ?";
                $result = $conn->execute_query($query, [$startTime, $endTime, $slotType, $slotId]);
                if (mysqli_affected_rows($conn) > 0) {
                    sendJsonResponse(200, 'Updated', 'Timeslot updated successfully');
                } else {
                    sendJsonResponse(400, 'Try again', "Couldn't update timeslot");
                }
            } elseif ($isValid === 2) {
                sendJsonResponse(422, 'Timeslot OutOfBound', 'The timeslot must be between 07:00 AM to 08:00 PM.');
            } elseif ($isValid === 3) {
                sendJsonResponse(422, 'Mismatched SlotType', 'The timeslot does not match the slot type.');
            } elseif ($isValid === 4) {
                sendJsonResponse(422, 'Inverted Timeslot', 'The Start Time is greater than the End Time.');
            } elseif ($isValid === 5) {
                sendJsonResponse(409, 'Overlapping Timeslot', 'The timeslot overlaps with an existing record.');
            } else {
                sendJsonResponse(400, 'Invalid Input', 'Start Time or End Time is invalid.');
            }
        }
        elseif($formtype == 'delete_timeslot'){
            $slotId = $_POST['slotId'];
            
            $query = "SELECT * FROM TIMESLOT WHERE SLOT_ID = ?";
            $result = $conn->execute_query($query,[$slotId]);
            if($result->num_rows==0){
                sendJsonResponse(200,'Deleted','Timeslot deleted succesfully');
            }
            $query = "DELETE FROM TIMESLOT WHERE SLOT_ID = ?";
            $result = $conn->execute_query($query,[$slotId]);
            if(mysqli_affected_rows($conn)>0 ){
                sendJsonResponse(200,'Deleted','Timeslot deleted succesfully');
            }else{
                sendJsonResponse(400,'Try again',"Couldn't update timeslot");
            }
        }
    }
    elseif($formCategory == 'classroom'){
        if($formtype == 'add_classroom'){
            $floor = $_POST['floor'] ?? '';
            $room = $_POST['room'] ?? '';
            $capacity = $_POST['capacity'] ?? '';
            $startTime = $_POST['startTime'] ?? '';
            $endTime = $_POST['endTime'] ?? '';
            $roomType = ($_POST['roomType']) ?? '';

            $isValid = validateClassroom($floor, $room, $capacity, $startTime, $endTime);
            if ($isValid === 1) {
                $query = "INSERT INTO CLASSROOM(FLOOR_NUMBER, ROOM_NUMBER, CAPACITY, START_TIME, END_TIME, CATEGORY) VALUES(?, ?, ?, ?, ?, ?)";
                $result = $conn->execute_query($query, [$floor, $room, $capacity, $startTime, $endTime, $roomType]);
                if (mysqli_affected_rows($conn) > 0) {
                    sendJsonResponse(201, 'Created', 'Classroom created successfully');
                } else {
                    sendJsonResponse(500, 'Try again', "Couldn't create Classroom");
                }
            }elseif($isValid === 2){
                sendJsonResponse(422,'Capacity out of range','The classroom capacity out of range');
            }elseif($isValid === 3){
                sendJsonResponse(422,'Too many rooms','There are too many rooms at a single floor');        
            }elseif($isValid === 4){
                sendJsonResponse(422,'Inverted Availability','The entered Availability is Invalid');        
            }else {
                sendJsonResponse(400, 'Invalid Classroom', 'Entered values are not valid');
            }
        }
        elseif($formtype == 'get_one_classroom'){ 
                $classroomId = $_POST['classroomId'];
                $query = "SELECT * FROM CLASSROOM WHERE CLASSROOM_ID = ?;";
                $result = $conn->execute_query($query,[$classroomId]);
                if($result->num_rows>0){
                    sendJsonResponse(200,"Classroom found.",$result->fetch_assoc());
                    exit;
                }
                sendJsonResponse(404,"Classroom not found.");
        }
        elseif($formtype ==  'update_classroom'){
            $classroomId = $_POST['classroomId'] ?? '';
            $floor = $_POST['floor'] ?? '';
            $room = $_POST['room'] ?? '';
            $capacity = $_POST['capacity'] ?? '';
            $startTime = $_POST['startTime'] ?? '';
            $endTime = $_POST['endTime'] ?? '';
            $roomType = ($_POST['roomType']) ?? 'LECTURE_HALL';
            
            $isValid = validateClassroom($floor, $room, $capacity, $startTime, $endTime);
            if ($isValid === 1) {
                $query = "UPDATE CLASSROOM SET FLOOR_NUMBER = ?, ROOM_NUMBER = ?, CAPACITY = ?, START_TIME = ?, END_TIME = ?, CATEGORY = ? WHERE CLASSROOM_ID = ?";
                $result = $conn->execute_query($query, [$floor, $room, $capacity, $startTime, $endTime, $roomType, $classroomId]);
                if (mysqli_affected_rows($conn) > 0) {
                    sendJsonResponse(200, 'Updated', 'Classroom updated successfully');
                } else {
                    sendJsonResponse(400, 'Try again', "Couldn't update classroom");
                }
            } elseif ($isValid === 2) {
                sendJsonResponse(422, 'Too small classroom', 'The classroom capacity is too low');
            } elseif ($isValid === 3) {
                sendJsonResponse(422, 'Too many rooms', 'There are too many rooms at a single floor');
            } elseif ($isValid === 4) {
                sendJsonResponse(422, 'Inverted Availability','The entered Availability is Invalid');
            } else {
                sendJsonResponse(400, 'Invalid Classroom', 'Entered values are not valid');
            }
        }
        elseif($formtype == 'delete_classroom'){
                $classroomId = $_POST['classroomId'];
                $query = "DELETE FROM CLASSROOM WHERE CLASSROOM_ID = ?";
                $result = $conn->execute_query($query,[$classroomId]);
                if (mysqli_affected_rows($conn)>0 ){
                    sendJsonResponse(200,'Deleted','Classroom deleted succesfully');
                }else{
                    sendJsonResponse(400,'Try again',"Couldn't delete classroom");
                }
        }
    }
    elseif($formCategory == 'department'){
        if($formtype == 'add_department'){
            $fullname = strtoupper($_POST['fullname'] ?? '');
            $shortname = strtoupper($_POST['shortname'] ?? '');

            $isValid = validateDepartment($fullname, $shortname);
            if ($isValid === 1) {
                $query = "INSERT INTO DEPARTMENT(LONG_NAME, SHORT_NAME) VALUES(?, ?)";
                $result = $conn->execute_query($query, [$fullname, $shortname]);
                if (mysqli_affected_rows($conn) > 0) {
                    sendJsonResponse(201, 'Created', 'Department created successfully');
                } else {
                    sendJsonResponse(400, 'Try again', "Couldn't create Department");
                }
            }elseif($isValid === 2){
                sendJsonResponse(422,'Contains Special Characters','The department name contains invalid character.');
            }else {
                sendJsonResponse(429, 'Invalid Department', 'Entered values are not valid');
            }
        }
        elseif($formtype ==  'get_one_department'){ 
                $departmentId = $_POST['departmentId'];
                $query = "SELECT * FROM DEPARTMENT WHERE DEPARTMENT_ID = ?;";
                $result = $conn->execute_query($query,[$departmentId]);
                if($result->num_rows>0){
                    sendJsonResponse(200,"Department found.",$result->fetch_assoc());
                    exit;
                }
                sendJsonResponse(404,"Department not found.");
        }
        elseif($formtype == 'update_department'){
                $departmentId = $_POST['departmentId'] ?? '';
                $fullname = strtoupper($_POST['fullname'] ?? '');
                $shortname = strtoupper($_POST['shortname'] ?? '');

                $isValid = validateDepartment($fullname, $shortname);
                if ($isValid === 1) {
                    $query = "UPDATE DEPARTMENT SET LONG_NAME = ?, SHORT_NAME = ? WHERE DEPARTMENT_ID = ?";
                    $result = $conn->execute_query($query, [$fullname, $shortname, $departmentId]);
                    if (mysqli_affected_rows($conn) > 0) {
                        sendJsonResponse(201, 'Created', 'Department updated successfully');
                    } else {
                        sendJsonResponse(400, 'Try again', "Couldn't update Department");
                    }
                }elseif($isValid === 2){
                    sendJsonResponse(422,'Contains Special Characters','The department name contains invalid character.');
                }else {
                    sendJsonResponse(429, 'Invalid Department', 'Entered values are not valid');
                }
        }
        elseif($formtype == 'delete_department'){
            $departmentId = $_POST['departmentId'];
            $query = "DELETE FROM DEPARTMENT WHERE DEPARTMENT_ID = ?";
            $result = $conn->execute_query($query,[$departmentId]);
            if (mysqli_affected_rows($conn)>0 ){
                sendJsonResponse(200,'Deleted','Department deleted succesfully');
            }else{
                sendJsonResponse(400,'Try again',"Couldn't update timeslot");
            }
        }        
    }
    elseif($formCategory == 'teacher'){
        if ($formtype == 'add_teacher') {
            $deptId     = $_POST['departmentDropdown'] ?? '';
            $fname      = trim($_POST['fname'] ?? '');
            $lname      = trim($_POST['lname'] ?? '');
            $isPartTime = isset($_POST['isPartTime']) ? 1 : 0;

            if (empty($deptId) || empty($fname) || empty($lname)) {
                sendJsonResponse(400, "Bad Request", "All required fields are mandatory.");
            }
            if (!isValidName($fname) || !isValidName($lname)) {
                sendJsonResponse(422, "Invalid Input", "Teacher name contains invalid characters.");
            }

            $days = [
                'monday'    => 'MONDAY',    'tuesday'  => 'TUESDAY', 
                'wednesday' => 'WEDNESDAY', 'thursday' => 'THURSDAY', 
                'friday'    => 'FRIDAY',    'saturday' => 'SATURDAY'
            ];

            mysqli_begin_transaction($conn);
            try {
                // Insert Teacher record
                $query = "INSERT INTO TEACHER (DEPARTMENT_ID, FIRST_NAME, LAST_NAME, ISPARTTIME) VALUES (?, ?, ?, ?)";
                $conn->execute_query($query, [$deptId, strtoupper($fname), strtoupper($lname), $isPartTime]);
                $teacherId = mysqli_insert_id($conn);

                if (!$teacherId) { 
                    throw new Exception("Couldn't create Teacher record."); 
                }

                // Handle Part-Time Teacher Availability
                if ($isPartTime) {
                    foreach ($days as $dayKey => $weekday) {
                        if (!isset($_POST[$dayKey])) { continue; }

                        $startSlot = (int)($_POST["punchIn_$dayKey"] ?? 0);
                        $endSlot   = (int)($_POST["punchOut_$dayKey"] ?? 0);

                        if ($startSlot > $endSlot) { 
                            throw new Exception("$weekday: Punch In slot must be before Punch Out slot."); 
                        }

                        $slotResult = $conn->execute_query(
                            "SELECT SLOT_ID FROM TIMESLOT WHERE SLOT_ID BETWEEN ? AND ? ORDER BY SLOT_ID",
                            [$startSlot, $endSlot]
                        );

                        while ($slot = $slotResult->fetch_assoc()) {
                            $conn->execute_query(
                                "INSERT INTO AVAILABILITY (TEACHER_ID, SLOT_ID, WEEKDAY, STATUS) VALUES (?, ?, ?, 'AVAILABLE')",
                                [$teacherId, $slot['SLOT_ID'], $weekday]
                            );
                        }
                    }
                } 
                // Full-Time Teacher Availability (All slots available)
                else {
                    $slotResult = $conn->execute_query("SELECT SLOT_ID FROM TIMESLOT ORDER BY SLOT_ID");
                    $slots = [];
                    while ($row = $slotResult->fetch_assoc()) { $slots[] = $row['SLOT_ID']; }

                    foreach ($days as $weekday) {
                        foreach ($slots as $slotId) {
                            $conn->execute_query(
                                "INSERT INTO AVAILABILITY (TEACHER_ID, SLOT_ID, WEEKDAY, STATUS) VALUES (?, ?, ?, 'AVAILABLE')",
                                [$teacherId, $slotId, $weekday]
                            );
                        }
                    }
                }

                mysqli_commit($conn);
                sendJsonResponse(201, "Created", "Teacher created successfully.");

            } catch (Exception $e) {
                mysqli_rollback($conn);
                sendJsonResponse(500, "Creation Failed", $e->getMessage());
            }
        }

        elseif($formtype == 'get_one_teacher'){ 
            $teacherId = $_POST['teacherId'] ?? null;
            if (!$teacherId) {
                sendJsonResponse(400, "Bad Request", "Teacher ID is required.");
            }

            $query = "SELECT * FROM TEACHER WHERE TEACHER_ID = ?;";
            $result = $conn->execute_query($query, [$teacherId]);

            if ($result && $result->num_rows > 0) {
                $teacher = $result->fetch_assoc();

                // Fetch existing Availability if teacher is Part-Time
                $teacher['availability'] = [];
                if ($teacher['ISPARTTIME']) {
                    $availQuery = "
                        SELECT WEEKDAY, MIN(SLOT_ID) as START_SLOT, MAX(SLOT_ID) as END_SLOT 
                        FROM AVAILABILITY 
                        WHERE TEACHER_ID = ? 
                        GROUP BY WEEKDAY";
                    $availRes = $conn->execute_query($availQuery, [$teacherId]);
                    while ($row = $availRes->fetch_assoc()) {
                        $teacher['availability'][strtolower($row['WEEKDAY'])] = $row;
                    }
                }

                sendJsonResponse(200, "Teacher found.", $teacher);
            }
            sendJsonResponse(404, "Teacher not found.");
        }

        elseif ($formtype == 'update_teacher') {
            $teacherId  = $_POST['teacherId'] ?? '';
            $deptId     = $_POST['departmentDropdown'] ?? '';
            $fname      = trim($_POST['fname'] ?? '');
            $lname      = trim($_POST['lname'] ?? '');
            $isPartTime = isset($_POST['isPartTime']) ? 1 : 0;

            if (empty($teacherId) || empty($deptId) || empty($fname) || empty($lname)) {
                sendJsonResponse(400, "Bad Request", "All required fields are mandatory.");
            }
            if (!isValidName($fname) || !isValidName($lname)) {
                sendJsonResponse(422, "Invalid Input", "Teacher name contains invalid characters.");
            }

            $days = [
                'monday' => 'MONDAY', 'tuesday' => 'TUESDAY', 'wednesday' => 'WEDNESDAY',
                'thursday' => 'THURSDAY', 'friday' => 'FRIDAY', 'saturday' => 'SATURDAY'
            ];

            mysqli_begin_transaction($conn);
            try {
                $conn->execute_query(
                    "UPDATE TEACHER SET DEPARTMENT_ID = ?, FIRST_NAME = ?, LAST_NAME = ?, ISPARTTIME = ? WHERE TEACHER_ID = ?",
                    [$deptId, strtoupper($fname), strtoupper($lname), $isPartTime, $teacherId]
                );

                // Clear previous availability records
                $conn->execute_query("DELETE FROM AVAILABILITY WHERE TEACHER_ID = ?", [$teacherId]);

                if ($isPartTime) {
                    foreach ($days as $key => $weekday) {
                        if (!isset($_POST[$key])) continue;

                        $start = (int)($_POST["punchIn_$key"] ?? 0);
                        $end   = (int)($_POST["punchOut_$key"] ?? 0);

                        if ($start > $end) {
                            throw new Exception("$weekday: Punch In slot must be before Punch Out slot.");
                        }

                        $slots = $conn->execute_query(
                            "SELECT SLOT_ID FROM TIMESLOT WHERE SLOT_ID BETWEEN ? AND ? ORDER BY SLOT_ID",
                            [$start, $end]
                        );

                        while ($row = $slots->fetch_assoc()) {
                            $conn->execute_query(
                                "INSERT INTO AVAILABILITY (TEACHER_ID, SLOT_ID, WEEKDAY, STATUS) VALUES (?, ?, ?, 'AVAILABLE')",
                                [$teacherId, $row['SLOT_ID'], $weekday]
                            );
                        }
                    }
                } else {
                    $slots = $conn->execute_query("SELECT SLOT_ID FROM TIMESLOT ORDER BY SLOT_ID");
                    while ($row = $slots->fetch_assoc()) {
                        foreach ($days as $weekday) {
                            $conn->execute_query(
                                "INSERT INTO AVAILABILITY (TEACHER_ID, SLOT_ID, WEEKDAY, STATUS) VALUES (?, ?, ?, 'AVAILABLE')",
                                [$teacherId, $row['SLOT_ID'], $weekday]
                            );
                        }
                    }
                }

                mysqli_commit($conn);
                sendJsonResponse(200, "Updated", "Teacher updated successfully.");

            } catch (Exception $e) {
                mysqli_rollback($conn);
                sendJsonResponse(500, "Update Error", $e->getMessage());
            }
        }

        elseif($formtype == 'delete_teacher'){
            $teacherId = $_POST['teacherId'] ?? null;
            if (!$teacherId) {
                sendJsonResponse(400, "Bad Request", "Teacher ID is required.");
            }

            try {
                $query = "DELETE FROM TEACHER WHERE TEACHER_ID = ?";
                $result = $conn->execute_query($query, [$teacherId]);
                if (mysqli_affected_rows($conn) > 0) {
                    sendJsonResponse(200, 'Deleted', 'Teacher deleted successfully');
                } else {
                    sendJsonResponse(404, 'Not Found', "Teacher record not found.");
                }
            } catch (mysqli_sql_exception $e) {
                sendJsonResponse(409, 'Delete Failed', 'Cannot delete teacher because they are currently assigned to active workload or timetable allotments.');
            }
        }
    }      
    elseif($formCategory == 'programme') {
        if ($formtype == 'add_programme') {
            $departmentId   = $_POST['departmentId'] ?? '';
            $fullname       = strtoupper(trim($_POST['fullname'] ?? ''));
            $shortname      = strtoupper(trim($_POST['shortname'] ?? ''));
            $duration       = (int)($_POST['duration'] ?? 0);
            
            $divisionCounts = [
                1 => (int)($_POST['division1'] ?? 1),
                2 => (int)($_POST['division2'] ?? 1),
                3 => (int)($_POST['division3'] ?? 1),
                4 => (int)($_POST['division4'] ?? 1),
                5 => (int)($_POST['division5'] ?? 1)
            ];
            $divisionCodes = ['A', 'B', 'C', 'D', 'E'];

            $isValid = validateProgramme($fullname, $shortname);
            if ($isValid === 2) {
                sendJsonResponse(422, 'Invalid Name', 'Programme name contains invalid characters.');
            }
            if ($isValid !== 1) {
                sendJsonResponse(400, 'Invalid Programme', 'Entered values are not valid.');
            }
            if ($duration < 1 || $duration > 5) {
                sendJsonResponse(422, 'Invalid Duration', 'Programme duration must be between 1 and 5 years.');
            }

            for ($i = 1; $i <= $duration; $i++) {
                if ($divisionCounts[$i] < 1 || $divisionCounts[$i] > 5) {
                    sendJsonResponse(422, 'Invalid Division Count', "Invalid division count for Year $i.");
                }
            }

            $totalDivisionCount = array_sum(array_slice($divisionCounts, 0, $duration, true));

            mysqli_begin_transaction($conn);
            try {
                $query = "INSERT INTO PROGRAMME (DEPARTMENT_ID, LONG_NAME, SHORT_NAME, DIVISION_COUNT) VALUES (?, ?, ?, ?)";
                $result = $conn->execute_query($query, [$departmentId, $fullname, $shortname, $totalDivisionCount]);
                
                if (!$result || mysqli_affected_rows($conn) != 1) {
                    throw new Exception("Couldn't create Programme record.");
                }

                $programmeId = mysqli_insert_id($conn);

                for ($year = 1; $year <= $duration; $year++) {
                    $resConsists = $conn->execute_query(
                        "INSERT INTO CONSISTS (YEAR_NUMBER, PROGRAMME_ID) VALUES (?, ?)",
                        [$year, $programmeId]
                    );
                    if (!$resConsists) {
                        throw new Exception("Couldn't assign year duration.");
                    }

                    for ($j = 0; $j < $divisionCounts[$year]; $j++) {
                        $resDiv = $conn->execute_query(
                            "INSERT INTO DIVISION (YEAR_NUMBER, NAME, PROGRAMME_ID) VALUES (?, ?, ?)",
                            [$year, $divisionCodes[$j], $programmeId]
                        );
                        if (!$resDiv) {
                            throw new Exception("Couldn't create divisions.");
                        }
                    }
                }

                mysqli_commit($conn);
                sendJsonResponse(201, 'Created', 'Programme created successfully');

            } catch (Exception $e) {
                mysqli_rollback($conn);
                sendJsonResponse(500, 'Creation Failed', $e->getMessage());
            }
        }

        elseif ($formtype == 'get_one_programme') {
            $programmeId = $_POST['programmeId'] ?? '';
            if (!$programmeId) {
                sendJsonResponse(400, 'Bad Request', 'Programme ID is required.');
            }

            $result = $conn->execute_query(
                "SELECT PROGRAMME_ID, DEPARTMENT_ID, LONG_NAME, SHORT_NAME FROM PROGRAMME WHERE PROGRAMME_ID = ?",
                [$programmeId]
            );

            if (!$result || $result->num_rows == 0) {
                sendJsonResponse(404, 'Not Found', 'Programme not found.');
            }

            $data = $result->fetch_assoc();

            $resDuration = $conn->execute_query(
                "SELECT COUNT(*) AS DURATION FROM CONSISTS WHERE PROGRAMME_ID = ?",
                [$programmeId]
            );
            $data['DURATION'] = (int)($resDuration->fetch_assoc()['DURATION'] ?? 0);

            $resDivs = $conn->execute_query(
                "SELECT YEAR_NUMBER, COUNT(*) AS DIVISION_COUNT FROM DIVISION WHERE PROGRAMME_ID = ? GROUP BY YEAR_NUMBER ORDER BY YEAR_NUMBER",
                [$programmeId]
            );

            $data['DIVISIONS'] = [];
            while ($row = $resDivs->fetch_assoc()) {
                $data['DIVISIONS'][(int)$row['YEAR_NUMBER']] = (int)$row['DIVISION_COUNT'];
            }

            sendJsonResponse(200, 'Programme found.', $data);
        }

        elseif ($formtype == 'update_programme') {
            $programmeId    = $_POST['programmeId'] ?? '';
            $departmentId   = $_POST['departmentId'] ?? '';
            $fullname       = strtoupper(trim($_POST['fullname'] ?? ''));
            $shortname      = strtoupper(trim($_POST['shortname'] ?? ''));
            $duration       = (int)($_POST['duration'] ?? 0);

            if (!$programmeId) {
                sendJsonResponse(400, 'Bad Request', 'Programme ID is required.');
            }

            $divisionCounts = [
                1 => (int)($_POST['division1'] ?? 1),
                2 => (int)($_POST['division2'] ?? 1),
                3 => (int)($_POST['division3'] ?? 1),
                4 => (int)($_POST['division4'] ?? 1),
                5 => (int)($_POST['division5'] ?? 1)
            ];
            $divisionCodes = ['A', 'B', 'C', 'D', 'E'];

            $isValid = validateProgramme($fullname, $shortname);
            if ($isValid === 2) {
                sendJsonResponse(422, 'Invalid Name', 'Programme name contains invalid characters.');
            }
            if ($isValid !== 1) {
                sendJsonResponse(400, 'Invalid Programme', 'Entered values are not valid.');
            }
            if ($duration < 1 || $duration > 5) {
                sendJsonResponse(422, 'Invalid Duration', 'Programme duration must be between 1 and 5 years.');
            }

            for ($i = 1; $i <= $duration; $i++) {
                if ($divisionCounts[$i] < 1 || $divisionCounts[$i] > 5) {
                    sendJsonResponse(422, 'Invalid Division Count', "Invalid division count for Year $i.");
                }
            }

            $totalDivisionCount = array_sum(array_slice($divisionCounts, 0, $duration, true));

            mysqli_begin_transaction($conn);
            try {
                $resProg = $conn->execute_query(
                    "UPDATE PROGRAMME SET DEPARTMENT_ID = ?, LONG_NAME = ?, SHORT_NAME = ?, DIVISION_COUNT = ? WHERE PROGRAMME_ID = ?", 
                    [$departmentId, $fullname, $shortname, $totalDivisionCount, $programmeId]
                );

                if (!$resProg) {
                    throw new Exception("Couldn't update Programme basic details.");
                }

                for ($year = 1; $year <= $duration; $year++) {
                    $checkConsists = $conn->execute_query("SELECT YEAR_NUMBER FROM CONSISTS WHERE YEAR_NUMBER = ? AND PROGRAMME_ID = ?", [$year, $programmeId]);
                    if ($checkConsists->num_rows == 0) {
                        $conn->execute_query("INSERT INTO CONSISTS (YEAR_NUMBER, PROGRAMME_ID) VALUES (?, ?)", [$year, $programmeId]);
                    }

                    for ($j = 0; $j < $divisionCounts[$year]; $j++) {
                        $code = $divisionCodes[$j];
                        $checkDiv = $conn->execute_query("SELECT DIVISION_ID FROM DIVISION WHERE YEAR_NUMBER = ? AND PROGRAMME_ID = ? AND NAME = ?", [$year, $programmeId, $code]);
                        if ($checkDiv->num_rows == 0) {
                            $conn->execute_query("INSERT INTO DIVISION (YEAR_NUMBER, NAME, PROGRAMME_ID) VALUES (?, ?, ?)", [$year, $code, $programmeId]);
                        }
                    }

                    // Trim extra divisions if count reduced
                    if ($divisionCounts[$year] < 5) {
                        $deleteFrom = $divisionCodes[$divisionCounts[$year]];
                        $conn->execute_query("DELETE FROM DIVISION WHERE PROGRAMME_ID = ? AND YEAR_NUMBER = ? AND NAME >= ?", [$programmeId, $year, $deleteFrom]);
                    }
                }

                // Delete trimmed years
                $conn->execute_query("DELETE FROM DIVISION WHERE PROGRAMME_ID = ? AND YEAR_NUMBER > ?", [$programmeId, $duration]);
                $conn->execute_query("DELETE FROM CONSISTS WHERE PROGRAMME_ID = ? AND YEAR_NUMBER > ?", [$programmeId, $duration]);

                mysqli_commit($conn);
                sendJsonResponse(200, 'Updated', 'Programme updated successfully');

            } catch (Exception $e) {
                mysqli_rollback($conn);
                sendJsonResponse(500, 'Update Failed', $e->getMessage());
            }
        }

        elseif ($formtype == 'delete_programme') {
            $programmeId = $_POST['programmeId'] ?? '';
            if (!$programmeId) {
                sendJsonResponse(400, 'Bad Request', 'Programme ID is required.');
            }

            try {
                $result = $conn->execute_query("DELETE FROM PROGRAMME WHERE PROGRAMME_ID = ?", [$programmeId]);
                if ($result && mysqli_affected_rows($conn) > 0) {
                    sendJsonResponse(200, 'Deleted', 'Programme deleted successfully');
                } else {
                    sendJsonResponse(404, 'Not Found', 'Programme record not found or already deleted.');
                }
            } catch (mysqli_sql_exception $e) {
                sendJsonResponse(409, 'Delete Failed', 'Cannot delete programme because courses or active timetable entries are assigned to it.');
            }
        }
    }
    elseif($formCategory == 'course'){
        if($formtype == 'add_course'){
            $departmentId = $_POST['departmentId'] ?? '';
            $programmeId = $_POST['programmeId'] ?? '';
            $yearId = $_POST['yearId'] ?? '';
            $fullname = strtoupper($_POST['fullname'] ?? '');
            $shortname = strtoupper($_POST['shortname'] ?? '');
            $weeklyLectures = (int)($_POST['lectureCount'] ?? 0);
            $semester = ($_POST['isEven'] === 'true') ? "EVEN" : "ODD";
            $courseType = $_POST['type'] ?? 'LECTURE';
            $isPractical = (strpos($courseType, 'PRACTICAL') !== false) ? 1 : 0;
            $isOptional = (($_POST['isOptional'] ?? 'false') === 'true') ? 1 : 0;
            $optionalCourseId = !empty($_POST['optionalCourseId']) ? $_POST['optionalCourseId'] : null;

            $isValid = validateCourse($fullname, $shortname);
            if ($isValid === 1) {
                $query = "INSERT INTO COURSE(PROGRAMME_ID, YEAR_NUMBER, TYPE, SEMESTER, LONG_NAME, SHORT_NAME, WEEKLY_LECTURES, ISPRACTICAL, ISOPTIONAL, OPTIONAL_ID) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $result = $conn->execute_query($query, [$programmeId, $yearId, $courseType, $semester, $fullname, $shortname, $weeklyLectures, $isPractical, $isOptional, $optionalCourseId]);
                if (mysqli_affected_rows($conn) > 0) {
                    sendJsonResponse(201, 'Created', 'Course created successfully');
                } else {
                    sendJsonResponse(400, 'Try again', "Couldn't create course");
                }
            }elseif($isValid === 2){
                sendJsonResponse(422,'Contains Special Characters','The course name contains invalid character.');
            }else {
                sendJsonResponse(429, 'Invalid course', 'Entered values are not valid');
            }
        }
        elseif($formtype == 'get_one_course') {
            $courseId = $_POST['courseId'] ?? null;
            if(!$courseId || !is_numeric($courseId)){
                sendJsonResponse(400, "INVALID COURSE ID.");
                exit;
            }
            $query = "
                SELECT
                    C.COURSE_ID,
                    P.DEPARTMENT_ID,
                    C.PROGRAMME_ID,
                    C.YEAR_NUMBER,
                    C.SEMESTER,
                    C.LONG_NAME,
                    C.SHORT_NAME,
                    C.WEEKLY_LECTURES,
                    C.ISPRACTICAL,
                    C.ISOPTIONAL,
                    C.OPTIONAL_ID
                FROM COURSE C
                INNER JOIN PROGRAMME P ON P.PROGRAMME_ID = C.PROGRAMME_ID
                WHERE C.COURSE_ID = ?
                LIMIT 1
            ";
            $result = $conn->execute_query($query, [(int)$courseId]);
            if($result->num_rows > 0){sendJsonResponse(200, "COURSE FOUND.", $result->fetch_assoc());}
            sendJsonResponse(404, "COURSE NOT FOUND.");
            exit;
        }
        elseif($formtype == 'update_course') {
            $courseId = $_POST['courseId'] ?? '';
            $programmeId = $_POST['programmeId'] ?? '';
            $yearId = $_POST['yearId'] ?? '';
            $fullname = strtoupper($_POST['fullname'] ?? '');
            $shortname = strtoupper($_POST['shortname'] ?? '');
            $semester = ($_POST['isEven'] === 'true') ? "EVEN" : "ODD";
            $weeklyLectures = (int)($_POST['lectureCount'] ?? 0);
            $courseType = $_POST['type'] ?? 'LECTURE';
            $isPractical = (strpos($courseType, 'PRACTICAL') !== false) ? 1 : 0;
            $isOptional = (($_POST['isOptional'] ?? 'false') === 'true') ? 1 : 0;
            $optionalCourseId = !empty($_POST['optionalCourseId']) ? $_POST['optionalCourseId'] : null;

            $isValid = validateCourse($fullname, $shortname);
            
            if ($isValid === 1) {
                $query = "UPDATE COURSE SET PROGRAMME_ID = ?, YEAR_NUMBER = ?, TYPE = ?, SEMESTER = ?, LONG_NAME = ?, SHORT_NAME = ?, WEEKLY_LECTURES = ?, ISPRACTICAL = ?, ISOPTIONAL = ?, OPTIONAL_ID = ? WHERE COURSE_ID = ?";
                $result = $conn->execute_query($query, [$programmeId, $yearId, $courseType, $semester, $fullname, $shortname, $weeklyLectures, $isPractical, $isOptional, $optionalCourseId, $courseId]);
                if ($result) {
                    sendJsonResponse(200, 'Updated', 'Course updated successfully');
                } else {
                    sendJsonResponse(400, 'Try again', "Couldn't update course");
                }
            }elseif ($isValid === 2) {
                sendJsonResponse(422, 'Contains Special Characters', 'The course name contains invalid character.');
            } else {
                sendJsonResponse(429, 'Invalid course', 'Entered values are not valid');
            }
        }
        elseif($formtype == 'delete_course'){
            $courseId = $_POST['courseId'];
            $query = "DELETE FROM COURSE WHERE COURSE_ID = ?";
            $result = $conn->execute_query($query,[$courseId]);
            if (mysqli_affected_rows($conn)>0 ){
                sendJsonResponse(200,'Deleted','Course deleted succesfully');
            }else{
                sendJsonResponse(400,'Try again',"Couldn't update timeslot");
            }
        }        
    }
    elseif($formCategory == 'optionalcourse') {
        if ($formtype == 'map_course') {
            $course1 = (int) $_POST['course1'];
            $course2 = (int) $_POST['course2'];

            $conn->begin_transaction();
            try {
                $query = "UPDATE COURSE SET OPTIONAL_ID = ? WHERE COURSE_ID = ?";
                $conn->execute_query($query, [$course2, $course1]);
                $conn->execute_query($query, [$course1, $course2]);

                $conn->commit();
                sendJsonResponse(200, 'Mapped', 'Courses mapped successfully');
            } catch (Exception $e) {
                $conn->rollback();
                sendJsonResponse(500, 'Mapping Failed', 'Could not map optional courses.');
            }
        } elseif($formtype == 'unmap_course') {
            $mainCourse = (int) $_POST['mainCourse'];

            // Find the paired optional course ID
            $query = "SELECT OPTIONAL_ID FROM COURSE WHERE COURSE_ID = ?";
            $result = $conn->execute_query($query, [$mainCourse]);
            $row = $result->fetch_assoc();

            if ($row && !empty($row['OPTIONAL_ID'])) {
                $sideCourse = (int) $row['OPTIONAL_ID'];
                $updateQuery = "UPDATE COURSE SET OPTIONAL_ID = NULL WHERE COURSE_ID IN (?, ?)";
                $conn->execute_query($updateQuery, [$mainCourse, $sideCourse]);
                sendJsonResponse(200, 'Unmapped', 'Course unmapped successfully');
            } else {
                // If single mapping entry exists without reciprocal reference
                $query = "UPDATE COURSE SET OPTIONAL_ID = NULL WHERE COURSE_ID = ?";
                $conn->execute_query($query, [$mainCourse]);
                sendJsonResponse(200, 'Unmapped', 'Course unmapped successfully');
            }
        }
    }
    elseif($formCategory == 'division'){
        if($formtype == 'add_divisionDetails'){
            $divisionId = $_POST['divisionId'] ?? '';
            $studCount = $_POST['studentCount'] ?? null;
            $classroomId = !empty($_POST['classroomId']) ? $_POST['classroomId'] : null;
            $hasPrefTime = isset($_POST['prefTime']) && $_POST['prefTime'] === 'true';
            $isStartPref = isset($_POST['isStartPref']) ? (int)$_POST['isStartPref'] : 1;
            $timeBound = !empty($_POST['timeBound']) ? $_POST['timeBound'] : null;

            if($hasPrefTime && $timeBound !== null){
                if($isStartPref === 1){
                    $query = "UPDATE DIVISION SET STUDENT_COUNT = ?, CLASSROOM_ID = ?, START_TIME_ID = ?, END_TIME_ID = NULL WHERE DIVISION_ID = ?";
                    $result = $conn->execute_query($query, [$studCount, $classroomId, $timeBound, $divisionId]);
                } else {
                    $query = "UPDATE DIVISION SET STUDENT_COUNT = ?, CLASSROOM_ID = ?, START_TIME_ID = NULL, END_TIME_ID = ? WHERE DIVISION_ID = ?";
                    $result = $conn->execute_query($query, [$studCount, $classroomId, $timeBound, $divisionId]);
                }
            } else {
                $query = "UPDATE DIVISION SET STUDENT_COUNT = ?, CLASSROOM_ID = ?, START_TIME_ID = NULL, END_TIME_ID = NULL WHERE DIVISION_ID = ?";
                $result = $conn->execute_query($query, [$studCount, $classroomId, $divisionId]);
            }

            if($result){
                sendJsonResponse(200, 'Details saved', "The division details were saved successfully");
            } else {
                sendJsonResponse(500, 'Details were not saved', "Error in saving division details");
            }
        }
        elseif($formtype == 'delete_divisionDetails'){
            $divisionId = $_POST['divisionId'];
            $query = "UPDATE DIVISION SET STUDENT_COUNT = NULL, CLASSROOM_ID = NULL, START_TIME_ID = NULL, END_TIME_ID = NULL WHERE DIVISION_ID = ?";
            $result = $conn->execute_query($query,[$divisionId]);
            if($result){
                sendJsonResponse(200,"Details Deleted","The Details were deleted successfully.","");
            }
            else{
                sendJsonResponse(400,'Delete failed', "The delete details operation was unsuccesfull.");
            }
        }
            
    }
    elseif($formCategory == 'optionalcoursemapper') {
        if ($formtype == 'map_course2division') {
            $course1 = (int) $_POST['course1'];
            $course2 = (int) $_POST['course2'];
            $division1 = (int) $_POST['division1'];
            $division2 = (int) $_POST['division2'];

            $query = "INSERT INTO OPTED_BY (COURSE_ID, DIVISION_ID) VALUES (?, ?) ON DUPLICATE KEY UPDATE DIVISION_ID = VALUES(DIVISION_ID)";
            $conn->begin_transaction();
            try {
                $conn->execute_query($query, [$course1, $division1]);
                $conn->execute_query($query, [$course2, $division2]);
                $conn->commit();
                sendJsonResponse(200, "Course Mapped to Division", "Courses were successfully mapped to divisions");
            } catch (Exception $e) {
                $conn->rollback();
                sendJsonResponse(400, "Error in mapping", "Mapping was unsuccessful");
            }
        } elseif ($formtype == 'unmap_course') {
            $courseId1 = (int) ($_POST['courseId1'] ?? 0);

            if ($courseId1 <= 0) {
                sendJsonResponse(400, 'Invalid Input', 'Valid course ID is required.');
            }

            // Find the paired optional course ID by checking both COURSE_ID and OPTIONAL_ID
            $query = "SELECT COURSE_ID, OPTIONAL_ID 
                    FROM COURSE 
                    WHERE COURSE_ID = ? OR OPTIONAL_ID = ?";
            $result = $conn->execute_query($query, [$courseId1, $courseId1]);

            $coursesToDelete = [$courseId1];

            while ($row = $result->fetch_assoc()) {
                if (!empty($row['COURSE_ID'])) {
                    $coursesToDelete[] = (int) $row['COURSE_ID'];
                }
                if (!empty($row['OPTIONAL_ID'])) {
                    $coursesToDelete[] = (int) $row['OPTIONAL_ID'];
                }
            }

            // Remove duplicates and re-index array
            $coursesToDelete = array_unique(array_filter($coursesToDelete));

            // Prepare placeholders dynamically (?, ?, ...)
            $placeholders = implode(',', array_fill(0, count($coursesToDelete), '?'));
            $deleteQuery = "DELETE FROM OPTED_BY WHERE COURSE_ID IN ($placeholders)";

            $result = $conn->execute_query($deleteQuery, $coursesToDelete);

            if ($result) {
                sendJsonResponse(200, 'Unmapped', 'Course and its pair unmapped successfully');
            } else {
                sendJsonResponse(400, 'Failed', 'Course unmapping failed');
            }
        }
    }
    elseif($formCategory == 'workload'){
        if($formtype == "create_workload"){
            $teacherId = (int) ($_POST['teacherId'] ?? 0);
            $courseId = (int) ($_POST['courseId'] ?? 0);
            $divisionRaw = $_POST['divisionId'] ?? '';
            $lectureCount = (int) ($_POST['lectureCount'] ?? 0);

            if($lectureCount <= 0){
                sendJsonResponse(
                    400,
                    'Invalid Lecture Count',
                    'Lecture count must be greater than zero.'
                );
                exit;
            }

            // Safely parse division array payload
            $divisionIds = json_decode($divisionRaw, true);
            if (!is_array($divisionIds)) {
                $divisionIds = [(int)$divisionRaw];
            }

            $query = "
                INSERT INTO TEACHES (TEACHER_ID, COURSE_ID, DIVISION_ID, LECTURE_COUNT)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE LECTURE_COUNT = VALUES(LECTURE_COUNT)
            ";

            $conn->begin_transaction();
            try {
                foreach($divisionIds as $divId){
                    $divId = (int)$divId;
                    if ($divId > 0) {
                        $conn->execute_query(
                            $query,
                            [$teacherId, $courseId, $divId, $lectureCount]
                        );
                    }
                }
                $conn->commit();
                sendJsonResponse(
                    200,
                    'Workload Created',
                    'Workload assigned successfully.'
                );
            } catch(mysqli_sql_exception $e) {
                $conn->rollback();
                sendJsonResponse(
                    500,
                    'Creation Failed',
                    'Unable to create workload assignment.'
                );
            }
        }
        elseif($formtype == 'delete_workload'){
            $workloadId = (int) ($_POST['workloadId'] ?? 0);
            if($workloadId <= 0){
                sendJsonResponse(400, 'Invalid Data', 'Invalid workload ID.');
                exit;
            }
            $query = "DELETE FROM TEACHES WHERE WORKLOAD_ID = ?";
            $result = $conn->execute_query($query, [$workloadId]);

            if($result && $conn->affected_rows >= 1){
                sendJsonResponse(200, 'Deleted', 'Workload deleted successfully.');
            }else{
                sendJsonResponse(500, 'Delete Failed', 'Unable to delete workload.');
            }
        }
    }
    elseif($formCategory == "timetable"){
        if (!arePrecedingModulesLocked()) {
            header("Refresh:0");
            sendJsonResponse(403, "Access Denied", "Timetable generation is locked until all preceding modules are locked.");
        }
        if ($formtype == 'check_status') {
            $result = $conn->execute_query("SELECT COUNT(*) AS total, ACADEMIC_YEAR, SEMESTER FROM TIMETABLE GROUP BY ACADEMIC_YEAR, SEMESTER LIMIT 1");
            if ($result && $row = $result->fetch_assoc()) {
                sendJsonResponse(200, "Timetable exists", [
                    'exists' => true,
                    'academic_year' => $row['ACADEMIC_YEAR'],
                    'semester' => $row['SEMESTER']
                ]);
            } else {
                sendJsonResponse(200, "No timetable generated", ['exists' => false]);
            }
        }
        elseif ($formtype == 'generate_and_save') {
            require_once 'generatorv2.php'; // Ensure generator script is loaded

            $semester = strtoupper($_POST['semester'] ?? 'ODD');
            $academicYear = trim($_POST['academicYear'] ?? '2026-27');

            // 1. Run the core allocation pipeline
            $allocationResult = generateTimetable($semester, $academicYear);
            
            // FIX 1: Access 'timetable' instead of 'schedule'
            $schedule = $allocationResult['timetable'] ?? [];

            if (empty($schedule)) {
                sendJsonResponse(422, "Generation Failed", "Could not allocate slots. Check resource limits.");
            }

            // 2. Persist allocations into TIMETABLE table
            $conn->begin_transaction();
            try {
                // Clear existing timetable entries
                $deleteStmt = $conn->prepare("DELETE FROM TIMETABLE WHERE ACADEMIC_YEAR = ? AND SEMESTER = ?");
                $deleteStmt->bind_param("ss", $academicYear, $semester);
                $deleteStmt->execute();
                $deleteStmt->close();

                // Prepare insert statement
                $insertStmt = $conn->prepare("
                    INSERT INTO TIMETABLE (COURSE_ID, DIVISION_ID, CLASSROOM_ID, SLOT_ID, WEEKDAY, TEACHER_ID, ACADEMIC_YEAR, SEMESTER) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($schedule as $row) {
                    $courseId    = $row['COURSE_ID'];
                    $divisionId  = $row['DIVISION_ID'];
                    $classroomId = $row['ROOM_ID'];
                    $slotId      = $row['SLOT_ID'];
                    $weekday     = $row['DAY'];
                    $teacherId   = $row['TEACHER_ID'];

                    // Fixed type string: "iiiisiss" (8 items)
                    $insertStmt->bind_param(
                        "iiiisiss", 
                        $courseId, 
                        $divisionId, 
                        $classroomId, 
                        $slotId, 
                        $weekday, 
                        $teacherId, 
                        $academicYear, 
                        $semester
                    );
                    $insertStmt->execute();
                }

                $insertStmt->close();
                $conn->commit();

                sendJsonResponse(201, "Timetable Saved", "Timetable generated and persisted successfully.", [
                    'validation' => $allocationResult['validation'] ?? []
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                sendJsonResponse(500, "Database Error", $e->getMessage());
            }
        }
        elseif ($formtype == 'get_division_view') {
            // Fetch academic year & semester context
            $metaQuery = $conn->execute_query("SELECT ACADEMIC_YEAR, SEMESTER FROM TIMETABLE LIMIT 1");
            if ($metaQuery->num_rows == 0) {
                sendJsonResponse(404, "No Timetable Found");
            }
            $meta = $metaQuery->fetch_assoc();

            // Fetch timeslots ordered by start time
            $slotQuery = $conn->execute_query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME ASC");
            $timeslots = [];
            while ($s = $slotQuery->fetch_assoc()) {
                $timeslots[] = $s;
            }

            // Fetch all division details linked with programme, dept, year
            $sqlDivisions = "
                SELECT 
                    dv.DIVISION_ID,
                    dv.NAME AS DIVISION_NAME,
                    y.YEAR_NAME,
                    p.SHORT_NAME AS PROGRAMME_NAME,
                    p.LONG_NAME AS PROGRAMME_FULL_NAME,
                    d.SHORT_NAME AS DEPARTMENT_NAME
                FROM DIVISION dv
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                ORDER BY p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME
            ";
            $divRes = $conn->execute_query($sqlDivisions);
            $divisions = [];
            while ($d = $divRes->fetch_assoc()) {
                $divisions[$d['DIVISION_ID']] = [
                    'DIVISION_ID' => $d['DIVISION_ID'],
                    'DIVISION_NAME' => $d['DIVISION_NAME'],
                    'YEAR_NAME' => $d['YEAR_NAME'],
                    'PROGRAMME_NAME' => $d['PROGRAMME_NAME'],
                    'PROGRAMME_FULL_NAME' => $d['PROGRAMME_FULL_NAME'],
                    'DEPARTMENT_NAME' => $d['DEPARTMENT_NAME'],
                    'schedule' => []
                ];
            }

            // Fetch scheduled allocations
            $sqlSchedule = "
                SELECT 
                    tt.DIVISION_ID,
                    tt.SLOT_ID,
                    tt.WEEKDAY,
                    c.SHORT_NAME AS COURSE_SHORT_NAME,
                    c.LONG_NAME AS COURSE_NAME,
                    cr.ROOM_NUMBER,
                    CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ";
            $schedRes = $conn->execute_query($sqlSchedule, [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            while ($r = $schedRes->fetch_assoc()) {
                $divId = $r['DIVISION_ID'];
                $w = $r['WEEKDAY'];
                $slotId = $r['SLOT_ID'];

                if (isset($divisions[$divId])) {
                    if (!isset($divisions[$divId]['schedule'][$w])) {
                        $divisions[$divId]['schedule'][$w] = [];
                    }
                    $divisions[$divId]['schedule'][$w][$slotId] = $r;
                }
            }

            sendJsonResponse(200, "Timetable Data Retrieved", [
                'metadata' => [
                    'ACADEMIC_YEAR' => $meta['ACADEMIC_YEAR'],
                    'SEMESTER' => $meta['SEMESTER'],
                    'timeslots' => $timeslots
                ],
                'divisions' => $divisions
            ]);
        }
        elseif ($formtype == 'clear_timetable') {
            $conn->execute_query("TRUNCATE TABLE TIMETABLE");
            sendJsonResponse(200, "Timetable cleared");
        }
        elseif ($formtype == 'export_validation_pdf') {
            $metaQuery = $conn->execute_query("SELECT ACADEMIC_YEAR, SEMESTER FROM TIMETABLE LIMIT 1");
            if ($metaQuery->num_rows == 0) {
                echo "<h3 style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px;'>No timetable generated yet to export validation report.</h3>";
                exit;
            }
            $meta = $metaQuery->fetch_assoc();

            $sql = "
                SELECT 
                    d.DEPARTMENT_ID,
                    d.LONG_NAME AS DEPARTMENT_NAME,
                    tr.TEACHER_ID,
                    CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME,
                    c.SHORT_NAME AS COURSE_SHORT_NAME,
                    c.LONG_NAME AS COURSE_NAME,
                    c.ISPRACTICAL,
                    p.SHORT_NAME AS PROGRAMME_NAME,
                    y.YEAR_NAME,
                    dv.NAME AS DIVISION_NAME,
                    t.LECTURE_COUNT AS REQUIRED_LECTURES,
                    COALESCE(tt_count.allocated, 0) AS ALLOCATED_LECTURES,
                    (t.LECTURE_COUNT - COALESCE(tt_count.allocated, 0)) AS UNALLOCATED_LECTURES
                FROM TEACHES t
                JOIN TEACHER tr ON t.TEACHER_ID = tr.TEACHER_ID
                JOIN DEPARTMENT d ON tr.DEPARTMENT_ID = d.DEPARTMENT_ID
                JOIN COURSE c ON t.COURSE_ID = c.COURSE_ID
                JOIN DIVISION dv ON t.DIVISION_ID = dv.DIVISION_ID
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                LEFT JOIN (
                    SELECT TEACHER_ID, COURSE_ID, DIVISION_ID, COUNT(*) AS allocated
                    FROM TIMETABLE
                    WHERE ACADEMIC_YEAR = ? AND SEMESTER = ?
                    GROUP BY TEACHER_ID, COURSE_ID, DIVISION_ID
                ) tt_count ON tt_count.TEACHER_ID = t.TEACHER_ID 
                           AND tt_count.COURSE_ID = t.COURSE_ID 
                           AND tt_count.DIVISION_ID = t.DIVISION_ID
                WHERE c.SEMESTER = ?
                HAVING UNALLOCATED_LECTURES > 0
                ORDER BY d.LONG_NAME, TEACHER_NAME, p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME, c.SHORT_NAME
            ";

            $res = $conn->execute_query($sql, [$meta['ACADEMIC_YEAR'], $meta['SEMESTER'], $meta['SEMESTER']]);
            $unallocatedList = [];
            $totalUnallocatedHours = 0;

            while ($row = $res->fetch_assoc()) {
                $dept = $row['DEPARTMENT_NAME'];
                $unallocatedList[$dept][] = $row;
                $totalUnallocatedHours += (int)$row['UNALLOCATED_LECTURES'];
            }

            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>Unallocated Workload Validation Report</title>
                <style>
                    @page { size: A4 portrait; margin: 12mm; }
                    body { font-family: Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 0; padding: 0; line-height: 1.4; }
                    .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
                    .header h1 { font-size: 15px; margin: 0; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; }
                    .header h2 { font-size: 12px; margin: 4px 0 0 0; color: #374151; font-weight: bold; text-transform: uppercase; }
                    .meta-bar { display: flex; justify-content: space-between; font-size: 10px; font-weight: bold; color: #4b5563; margin-top: 6px; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; }
                    .summary-card { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 8px 12px; font-size: 11px; font-weight: bold; margin-bottom: 12px; border-radius: 4px; }
                    .dept-title { font-size: 12px; font-weight: bold; color: #1e3a8a; background: #eff6ff; padding: 6px 10px; border-left: 4px solid #2563eb; margin-top: 14px; margin-bottom: 8px; text-transform: uppercase; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; }
                    th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; font-size: 10px; word-wrap: break-word; }
                    th { background-color: #f3f4f6; color: #111827; font-weight: bold; text-transform: uppercase; font-size: 9px; letter-spacing: 0.5px; }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .badge-pending { color: #dc2626; font-weight: bold; }
                    .success-banner { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 20px; text-align: center; font-weight: bold; font-size: 13px; border-radius: 6px; margin-top: 30px; }
                </style>
            </head>
            <body onload="window.print();">
                <div class="header">
                    <h1>KET's V. G. Vaze College of Arts, Science and Commerce (Autonomous)</h1>
                    <h2>Unallocated Workload Validation Report</h2>
                    <div class="meta-bar">
                        <span>Academic Year: <?= htmlspecialchars($meta['ACADEMIC_YEAR']) ?></span>
                        <span>Semester: <?= htmlspecialchars($meta['SEMESTER']) ?></span>
                        <span>Total Unallocated Hours: <?= $totalUnallocatedHours ?></span>
                        <span>Generated: <?= date('d-M-Y H:i') ?></span>
                    </div>
                </div>

                <?php if (empty($unallocatedList)): ?>
                    <div class="success-banner">
                        ✓ All workloads have been 100% allocated successfully!<br>
                        <span style="font-size: 11px; font-weight: normal; color: #15803d;">There are zero unallocated lectures or practicals for Semester <?= htmlspecialchars($meta['SEMESTER']) ?> (A.Y: <?= htmlspecialchars($meta['ACADEMIC_YEAR']) ?>).</span>
                    </div>
                <?php else: ?>
                    <div class="summary-card">
                        ⚠️ Attention: A total of <?= $totalUnallocatedHours ?> workload hour(s) could not be scheduled in the timetable grid due to constraints.
                    </div>

                    <?php foreach ($unallocatedList as $deptName => $items): ?>
                        <div class="dept-title">Department: <?= htmlspecialchars($deptName) ?></div>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">#</th>
                                    <th style="width: 22%;">Teacher Name</th>
                                    <th style="width: 23%;">Programme & Division</th>
                                    <th style="width: 22%;">Course / Subject</th>
                                    <th style="width: 10%;" class="text-center">Type</th>
                                    <th style="width: 6%;" class="text-center">Req.</th>
                                    <th style="width: 6%;" class="text-center">Alloc.</th>
                                    <th style="width: 6%;" class="text-center">Pending</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $srNo = 1;
                                foreach ($items as $row): 
                                    $classInfo = strtoupper(($row['YEAR_NAME'] == "FIRST YEAR") ? "FY" : (($row['YEAR_NAME'] == "SECOND YEAR") ? "SY" : (($row['YEAR_NAME'] == "THIRD YEAR") ? "TY" : ""))) 
                                                 . " " . $row['PROGRAMME_NAME'] . " - Div " . $row['DIVISION_NAME'];
                                    $type = !empty($row['ISPRACTICAL']) ? 'Practical' : 'Lecture';
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $srNo++ ?></td>
                                        <td><b><?= htmlspecialchars($row['TEACHER_NAME']) ?></b></td>
                                        <td><?= htmlspecialchars($classInfo) ?></td>
                                        <td><?= htmlspecialchars($row['COURSE_NAME']) ?> (<?= htmlspecialchars($row['COURSE_SHORT_NAME']) ?>)</td>
                                        <td class="text-center"><?= $type ?></td>
                                        <td class="text-center"><?= $row['REQUIRED_LECTURES'] ?></td>
                                        <td class="text-center"><?= $row['ALLOCATED_LECTURES'] ?></td>
                                        <td class="text-center badge-pending"><?= $row['UNALLOCATED_LECTURES'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            </body>
            </html>
            <?php
            exit;
        }
        elseif ($formtype == 'export_pdf_old') {
            $metaQuery = $conn->execute_query("SELECT ACADEMIC_YEAR, SEMESTER FROM TIMETABLE LIMIT 1");
            if ($metaQuery->num_rows == 0) {
                echo "<h3>No timetable generated yet to export.</h3>";
                exit;
            }
            $meta = $metaQuery->fetch_assoc();

            $slotQuery = $conn->execute_query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME ASC");
            $allTimeslots = [];
            while ($s = $slotQuery->fetch_assoc()) { $allTimeslots[] = $s; }

            // Fetch Division Timetables
            $divRes = $conn->execute_query("
                SELECT dv.DIVISION_ID, dv.NAME AS DIVISION_NAME, y.YEAR_NAME, p.SHORT_NAME AS PROGRAMME_NAME, 
                       p.LONG_NAME AS PROGRAMME_FULL_NAME, d.SHORT_NAME AS DEPARTMENT_NAME, cr.ROOM_NUMBER
                FROM DIVISION dv
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                LEFT JOIN CLASSROOM cr ON dv.CLASSROOM_ID = cr.CLASSROOM_ID
                ORDER BY d.SHORT_NAME, p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME
            ");
            $divisions = [];
            while ($d = $divRes->fetch_assoc()) {
                $divisions[$d['DIVISION_ID']] = $d;
                $divisions[$d['DIVISION_ID']]['schedule'] = [];
            }

            $schedRes = $conn->execute_query("
                SELECT tt.DIVISION_ID, tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, 
                       cr.ROOM_NUMBER, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            while ($r = $schedRes->fetch_assoc()) {
                $divisions[$r['DIVISION_ID']]['schedule'][$r['WEEKDAY']][$r['SLOT_ID']] = $r;
            }

            // Fetch Teacher Timetables
            $tchrRes = $conn->execute_query("
                SELECT tr.TEACHER_ID, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME, d.SHORT_NAME AS DEPARTMENT_NAME, d.LONG_NAME AS DEPARTMENT_LONG_NAME
                FROM TEACHER tr
                JOIN DEPARTMENT d ON tr.DEPARTMENT_ID = d.DEPARTMENT_ID
                ORDER BY d.SHORT_NAME, tr.FIRST_NAME
            ");
            $teachers = [];
            while ($t = $tchrRes->fetch_assoc()) {
                $teachers[$t['TEACHER_ID']] = $t;
                $teachers[$t['TEACHER_ID']]['schedule'] = [];
            }

            $tchrSchedRes = $conn->execute_query("
                SELECT tt.TEACHER_ID, tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, 
                       dv.NAME AS DIVISION_NAME, p.SHORT_NAME AS PROGRAMME_NAME, cr.ROOM_NUMBER
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            while ($r = $tchrSchedRes->fetch_assoc()) {
                $teachers[$r['TEACHER_ID']]['schedule'][$r['WEEKDAY']][$r['SLOT_ID']] = $r;
            }

            $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

            /**
             * Helper function: Compresses slots, removes edge breaks, and merges consecutive breaks
             */
            function processCompressedSlots($allTimeslots, $schedule, $weekdays) {
                // 1. Filter out completely empty non-break slots
                $filtered = [];
                foreach ($allTimeslots as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        $filtered[] = $slot;
                    } else {
                        $hasData = false;
                        foreach ($weekdays as $day) {
                            if (isset($schedule[$day][$slot['SLOT_ID']])) {
                                $hasData = true;
                                break;
                            }
                        }
                        if ($hasData) {
                            $filtered[] = $slot;
                        }
                    }
                }

                // 2. Trim leading breaks (breaks at the start before any lecture)
                while (!empty($filtered) && reset($filtered)['SLOT_TYPE'] === 'BREAK') {
                    array_shift($filtered);
                }

                // 3. Trim trailing breaks (breaks at the end after all lectures)
                while (!empty($filtered) && end($filtered)['SLOT_TYPE'] === 'BREAK') {
                    array_pop($filtered);
                }

                if (empty($filtered)) {
                    return [];
                }

                // 4. Merge consecutive breaks into single continuous break slots
                $merged = [];
                $tempBreak = null;

                foreach ($filtered as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        if ($tempBreak === null) {
                            $tempBreak = $slot;
                        } else {
                            // Merge end time into existing break
                            $tempBreak['END_TIME'] = $slot['END_TIME'];
                        }
                    } else {
                        if ($tempBreak !== null) {
                            $merged[] = $tempBreak;
                            $tempBreak = null;
                        }
                        $merged[] = $slot;
                    }
                }

                if ($tempBreak !== null) {
                    $merged[] = $tempBreak;
                }

                return $merged;
            }

            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>Timetable</title>
                <style>
                    @page { size: A4 landscape; margin: 10mm; }
                    body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; }
                    .page { page-break-after: always; padding: 10px; }
                    .page:last-child { page-break-after: avoid; }
                    .header { text-align: center; margin-bottom: 8px; }
                    .header h2 { margin: 0; font-size: 14px; text-transform: uppercase; }
                    .header h3 { margin: 3px 0; font-size: 12px; }
                    .header p { margin: 2px 0; font-weight: bold; font-size: 10px; }
                    .room-no { text-align: right; font-weight: bold; margin-bottom: 4px; }
                    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                    th, td { border: 1px solid #000; padding: 6px; text-align: center; font-size: 10px; word-wrap: break-word; }
                    th { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; }
                    .break { background-color: #e0e0e0; font-weight: bold; letter-spacing: 2px; }
                    .timeWeek{font-size: 13px;}
                    .course{font-size: 13px;}
                </style>
            </head>
            <body onload="window.print();">

                <?php foreach ($divisions as $div): 
                    $processedSlots = processCompressedSlots($allTimeslots, $div['schedule'], $weekdays);
                ?>
                    <div class="page">
                        <div class="header">
                            <h2>KET's V. G. Vaze College of Arts, Science and Commerce (Autonomous)</h2><br>
                            <h3>Class Time Table <?= htmlspecialchars($meta['ACADEMIC_YEAR']) ?></h3><br>
                            <h3><?= htmlspecialchars($div['PROGRAMME_FULL_NAME']) ?></h3>
                            <h3><?= ucwords(strtolower(htmlspecialchars($div['YEAR_NAME']))) ?> - <?= ucwords(strtolower(htmlspecialchars($meta['SEMESTER']))) ?> Semester</h3>
                    </div>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 15%;" class="timeWeek">Time</th>
                                    <?php foreach ($weekdays as $day): ?>
                                        <th class="timeWeek"><?= $day ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processedSlots as $slot): ?>
                                    <tr>
                                        <td>
                                            <b class="timeWeek"><?= date("h:i a", strtotime($slot['START_TIME'])) ?>  -  <?= date("h:i a", strtotime($slot['END_TIME'])) ?><br><?= ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) ? "(Practical- 2hrs )" : "" ?></b>
                                        </td>
                                        <?php if ($slot['SLOT_TYPE'] === 'BREAK'): ?>
                                            <td colspan="6" class="break">BREAK</td>
                                        <?php else: ?>
                                            <?php foreach ($weekdays as $day): 
                                                $entry = $div['schedule'][$day][$slot['SLOT_ID']] ?? null;
                                            ?>
                                                <td  class="slotBox"> 
                                                    <?php if ($entry): ?>
                                                        <b  class="course"><?= htmlspecialchars($entry['COURSE_SHORT_NAME']) ?></b><br>
                                                        <span><?= htmlspecialchars($entry['TEACHER_NAME']) ?><br></span>
                                                        (Room: <?= htmlspecialchars($entry['ROOM_NUMBER']) ?>)
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($teachers as $tchr): 
                    $processedSlots = processCompressedSlots($allTimeslots, $tchr['schedule'], $weekdays);
                ?>
                    <div class="page">
                        <div class="header">
                            <h2>KET's V. G. Vaze College of Arts, Science and Commerce (Autonomous)</h2><br>
                            <h3>TEACHER ALLOCATION TIMETABLE  - A.Y: <?= htmlspecialchars($meta['ACADEMIC_YEAR']) ?></h3><br>   
                            <h3><?= htmlspecialchars($tchr['TEACHER_NAME']) ?> <br> (<?= htmlspecialchars($tchr['DEPARTMENT_LONG_NAME']) ?>)</h3>
                            <br>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 12%;" class="timeWeek">Time</th>
                                    <?php foreach ($weekdays as $day): ?>
                                        <th class="timeWeek"><?= $day ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processedSlots as $slot): ?>
                                    <tr>
                                        <td>
                                        <b class="timeWeek"><?= date("h:i a", strtotime($slot['START_TIME'])) ?>  -  <?= date("h:i a", strtotime($slot['END_TIME'])) ?><br><?= ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) ? "(Practical- 2hrs )" : "" ?></b>
                                        </td>
                                        <?php if ($slot['SLOT_TYPE'] === 'BREAK'): ?>
                                            <td colspan="6" class="break">BREAK</td>
                                        <?php else: ?>
                                            <?php foreach ($weekdays as $day): 
                                                $entry = $tchr['schedule'][$day][$slot['SLOT_ID']] ?? null;
                                            ?>
                                                <td class="slotBox">
                                                    <?php if ($entry): ?>
                                                        <b class="course"><?= htmlspecialchars($entry['COURSE_SHORT_NAME']) ?></b><br>
                                                        <b><?= htmlspecialchars($entry['PROGRAMME_NAME']) ?>&nbsp - Div <?= htmlspecialchars($entry['DIVISION_NAME']) ?></b><br>
                                                        (Room: <?= htmlspecialchars($entry['ROOM_NUMBER']) ?>)
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <br><br>
                    </div>
                <?php endforeach; ?>

            </body>
            </html>
            <?php
            exit;
        }
        elseif ($formtype == 'export_pdf') {
            $metaQuery = $conn->execute_query("SELECT ACADEMIC_YEAR, SEMESTER FROM TIMETABLE LIMIT 1");
            if ($metaQuery->num_rows == 0) {
                echo "<h3>No timetable generated yet to export.</h3>";
                exit;
            }
            $meta = $metaQuery->fetch_assoc();

            $slotQuery = $conn->execute_query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME ASC");
            $allTimeslots = [];
            while ($s = $slotQuery->fetch_assoc()) { $allTimeslots[] = $s; }

            // Fetch Division Timetables
            $divRes = $conn->execute_query("
                SELECT dv.DIVISION_ID, dv.NAME AS DIVISION_NAME, y.YEAR_NAME, p.SHORT_NAME AS PROGRAMME_NAME, 
                       p.LONG_NAME AS PROGRAMME_FULL_NAME, d.SHORT_NAME AS DEPARTMENT_NAME, cr.ROOM_NUMBER
                FROM DIVISION dv
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN DEPARTMENT d ON p.DEPARTMENT_ID = d.DEPARTMENT_ID
                JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                LEFT JOIN CLASSROOM cr ON dv.CLASSROOM_ID = cr.CLASSROOM_ID
                ORDER BY d.SHORT_NAME, p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME
            ");
            $divisions = [];
            while ($d = $divRes->fetch_assoc()) {
                $divisions[$d['DIVISION_ID']] = $d;
                $divisions[$d['DIVISION_ID']]['schedule'] = [];
            }

            $schedRes = $conn->execute_query("
                SELECT tt.DIVISION_ID, tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, 
                       cr.ROOM_NUMBER, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            while ($r = $schedRes->fetch_assoc()) {
                $divisions[$r['DIVISION_ID']]['schedule'][$r['WEEKDAY']][$r['SLOT_ID']] = $r;
            }

            // Fetch Teacher Timetables
            $tchrRes = $conn->execute_query("
                SELECT tr.TEACHER_ID, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME, d.SHORT_NAME AS DEPARTMENT_NAME, d.LONG_NAME AS DEPARTMENT_LONG_NAME
                FROM TEACHER tr
                JOIN DEPARTMENT d ON tr.DEPARTMENT_ID = d.DEPARTMENT_ID
                ORDER BY d.SHORT_NAME, tr.FIRST_NAME
            ");
            $teachers = [];
            while ($t = $tchrRes->fetch_assoc()) {
                $teachers[$t['TEACHER_ID']] = $t;
                $teachers[$t['TEACHER_ID']]['schedule'] = [];
            }

            $tchrSchedRes = $conn->execute_query("
                SELECT tt.TEACHER_ID, tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, 
                       dv.NAME AS DIVISION_NAME, p.SHORT_NAME AS PROGRAMME_NAME, cr.ROOM_NUMBER
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            while ($r = $tchrSchedRes->fetch_assoc()) {
                $teachers[$r['TEACHER_ID']]['schedule'][$r['WEEKDAY']][$r['SLOT_ID']] = $r;
            }

            // Fetch Classroom Timetables
            $clsRes = $conn->execute_query("
                SELECT DISTINCT cr.CLASSROOM_ID, cr.ROOM_NUMBER
                FROM TIMETABLE tt
                JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
                ORDER BY cr.ROOM_NUMBER
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);
            $classrooms = [];
            while ($c = $clsRes->fetch_assoc()) {
                $classrooms[$c['CLASSROOM_ID']] = $c;
                $classrooms[$c['CLASSROOM_ID']]['schedule'] = [];
            }

            $clsSchedRes = $conn->execute_query("
                SELECT tt.CLASSROOM_ID, tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, 
                       dv.NAME AS DIVISION_NAME, p.SHORT_NAME AS PROGRAMME_NAME, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            while ($r = $clsSchedRes->fetch_assoc()) {
                $classrooms[$r['CLASSROOM_ID']]['schedule'][$r['WEEKDAY']][$r['SLOT_ID']] = $r;
            }

            $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

            function processCompressedSlots($allTimeslots, $schedule, $weekdays) {
                if (empty($allTimeslots)) {
                    return [];
                }

                // Step 1: Flag which slots actually contain scheduled data on at least one day
                $activeSlotIds = [];
                foreach ($allTimeslots as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        continue;
                    }
                    foreach ($weekdays as $day) {
                        if (isset($schedule[$day][$slot['SLOT_ID']])) {
                            $activeSlotIds[$slot['SLOT_ID']] = true;
                            break;
                        }
                    }
                }

                // Step 2: Filter out empty slots, BUT preserve continuous time flow
                $filteredSlots = [];
                $lastEndTime = null;

                foreach ($allTimeslots as $slot) {
                    $slotId   = $slot['SLOT_ID'];
                    $hasData  = isset($activeSlotIds[$slotId]);
                    $isBreak  = ($slot['SLOT_TYPE'] === 'BREAK');

                    // Always keep BREAKs and slots with actual allocations
                    if ($hasData || $isBreak) {
                        $filteredSlots[] = $slot;
                        $lastEndTime = $slot['END_TIME'];
                        continue;
                    }

                    // If a 2-hour practical slot is empty (like 07:00-09:00 or 11:45-01:45),
                    // check if the same time span is already covered by 1-hour slots.
                    // If covered, SKIP IT completely to eliminate redundant empty rows.
                    $isOverlappingPractical = ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL']));
                    if ($isOverlappingPractical) {
                        // Drop empty overlapping practical slots
                        continue;
                    }

                    // Keep empty 1-hour lecture slots ONLY if they fall between scheduled lectures (gap in time flow)
                    if ($lastEndTime !== null && strtotime($slot['START_TIME']) >= strtotime($lastEndTime)) {
                        // Check if there are future active lectures
                        $hasFutureData = false;
                        foreach ($allTimeslots as $futureSlot) {
                            if (strtotime($futureSlot['START_TIME']) > strtotime($slot['START_TIME']) && isset($activeSlotIds[$futureSlot['SLOT_ID']])) {
                                $hasFutureData = true;
                                break;
                            }
                        }

                        if ($hasFutureData) {
                            $filteredSlots[] = $slot;
                            $lastEndTime = $slot['END_TIME'];
                        }
                    }
                }

                // Step 3: Trim leading and trailing BREAKs
                while (!empty($filteredSlots) && reset($filteredSlots)['SLOT_TYPE'] === 'BREAK') {
                    array_shift($filteredSlots);
                }
                while (!empty($filteredSlots) && end($filteredSlots)['SLOT_TYPE'] === 'BREAK') {
                    array_pop($filteredSlots);
                }

                // Step 4: Merge consecutive BREAK slots into a single continuous break
                $merged = [];
                $tempBreak = null;

                foreach ($filteredSlots as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        if ($tempBreak === null) {
                            $tempBreak = $slot;
                        } else {
                            $tempBreak['END_TIME'] = $slot['END_TIME'];
                        }
                    } else {
                        if ($tempBreak !== null) {
                            $merged[] = $tempBreak;
                            $tempBreak = null;
                        }
                        $merged[] = $slot;
                    }
                }

                if ($tempBreak !== null) {
                    $merged[] = $tempBreak;
                }

                return $merged;
            }

            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>Timetable</title>
                <style>
                    @page { size: A4 landscape; margin: 10mm; }
                    body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; }
                    .page { page-break-after: always; padding: 10px; }
                    .page:last-child { page-break-after: avoid; }
                    .header { text-align: center; margin-bottom: 8px; }
                    .header h2 { margin: 0; font-size: 14px; text-transform: uppercase; }
                    .header h3 { margin: 3px 0; font-size: 12px; }
                    .header p { margin: 2px 0; font-weight: bold; font-size: 10px; }
                    .room-no { text-align: right; font-weight: bold; margin-bottom: 4px; }
                    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                    th, td { border: 1px solid #000; padding: 6px; text-align: center; font-size: 10px; word-wrap: break-word; }
                    th { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; }
                    .break { background-color: #e0e0e0; font-weight: bold; letter-spacing: 2px; }
                    .timeWeek{font-size: 13px;}
                    .course{font-size: 13px;}
                    .slotBox{height: 28px; table-layout: fixed;}
                    .emptySlot{font-size: 9px; font-weight: 500; letter-spacing: 1px; color: gray;}
                </style>
            </head>
            <body onload="window.print();">

                <!-- 1. DIVISION TIMETABLES -->
                <?php foreach ($divisions as $div): 
                    $processedSlots = processCompressedSlots($allTimeslots, $div['schedule'], $weekdays);
                ?>
                    <div class="page">
                        <div class="header">
                            <h2>KET's V. G. Vaze College of Arts, Science and Commerce (Autonomous)</h2><br>
                            <h3>Class Time Table <?= htmlspecialchars($meta['ACADEMIC_YEAR']) ?></h3><br>
                            <h3><?= htmlspecialchars($div['PROGRAMME_FULL_NAME']) ?></h3>
                            <h3><?= ucwords(strtolower(htmlspecialchars($div['YEAR_NAME']))) ?> - <?= ucwords(strtolower(htmlspecialchars($meta['SEMESTER']))) ?> Semester</h3>
                    </div>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 15%;" class="timeWeek">Time</th>
                                    <?php foreach ($weekdays as $day): ?>
                                        <th class="timeWeek"><?= $day ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processedSlots as $slot): ?>
                                    <tr>
                                        <td>
                                            <b class="timeWeek"><?= date("h:i a", strtotime($slot['START_TIME'])) ?>  -  <?= date("h:i a", strtotime($slot['END_TIME'])) ?><br><?= ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) ? "(Practical- 2hrs )" : "" ?></b>
                                        </td>
                                        <?php if ($slot['SLOT_TYPE'] === 'BREAK'): ?>
                                            <td colspan="6" class="break">BREAK</td>
                                        <?php else: ?>
                                            <?php foreach ($weekdays as $day): 
                                                $entry = $div['schedule'][$day][$slot['SLOT_ID']] ?? null;
                                            ?>
                                                <td  class="slotBox"> 
                                                    <?php if ($entry): ?>
                                                        <b  class="course"><?= htmlspecialchars($entry['COURSE_SHORT_NAME']) ?></b><br>
                                                        <span><?= htmlspecialchars($entry['TEACHER_NAME']) ?><br></span>
                                                        (Room: <?= htmlspecialchars($entry['ROOM_NUMBER']) ?>)
                                                    <?php else: ?>
                                                        <div class="emptySlot">( FREE )</div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <!-- 2. TEACHER ALLOCATION TIMETABLES -->
                <?php foreach ($teachers as $tchr): 
                    $processedSlots = processCompressedSlots($allTimeslots, $tchr['schedule'], $weekdays);
                ?>
                    <div class="page">
                        <div class="header">
                            <h2>KET's V. G. Vaze College of Arts, Science and Commerce (Autonomous)</h2><br>
                            <h3>TEACHER ALLOCATION TIMETABLE  - A.Y: <?= htmlspecialchars($meta['ACADEMIC_YEAR']) ?></h3><br>   
                            <h3><?= htmlspecialchars($tchr['TEACHER_NAME']) ?> <br> (<?= htmlspecialchars($tchr['DEPARTMENT_LONG_NAME']) ?>)</h3>
                            <br>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 12%;" class="timeWeek">Time</th>
                                    <?php foreach ($weekdays as $day): ?>
                                        <th class="timeWeek"><?= $day ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processedSlots as $slot): ?>
                                    <tr>
                                        <td>
                                        <b class="timeWeek"><?= date("h:i a", strtotime($slot['START_TIME'])) ?>  -  <?= date("h:i a", strtotime($slot['END_TIME'])) ?><br><?= ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) ? "(Practical- 2hrs )" : "" ?></b>
                                        </td>
                                        <?php if ($slot['SLOT_TYPE'] === 'BREAK'): ?>
                                            <td colspan="6" class="break">BREAK</td>
                                        <?php else: ?>
                                            <?php foreach ($weekdays as $day): 
                                                $entry = $tchr['schedule'][$day][$slot['SLOT_ID']] ?? null;
                                            ?>
                                                <td class="slotBox">
                                                    <?php if ($entry): ?>
                                                        <b class="course"><?= htmlspecialchars($entry['COURSE_SHORT_NAME']) ?></b><br>
                                                        <b>
                                                            <?= strtoupper(htmlspecialchars(($div['YEAR_NAME'] == "FIRST YEAR")? "FY" : (($div['YEAR_NAME'] == "SECOND YEAR")? "SY" : (($div['YEAR_NAME'] == "THIRD YEAR")? "TY" : "")))) ?> 
                                                            <?= htmlspecialchars($entry['PROGRAMME_NAME']) ?>&nbsp - Div 
                                                            <?= htmlspecialchars($entry['DIVISION_NAME']) ?>
                                                        </b>
                                                        <br>
                                                        (Room: <?= htmlspecialchars($entry['ROOM_NUMBER']) ?>)
                                                    <?php else: ?>
                                                        <div class="emptySlot">( FREE )</div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <br><br>
                    </div>
                <?php endforeach; ?>

                <!-- 3. CLASSROOM ALLOCATION TIMETABLES (APPENDED AT THE END) -->
                <?php foreach ($classrooms as $cls): 
                    $processedSlots = processCompressedSlots($allTimeslots, $cls['schedule'], $weekdays);
                ?>
                    <div class="page">
                        <div class="header">
                            <h2>KET's V. G. Vaze College of Arts, Science and Commerce (Autonomous)</h2><br>
                            <h3>CLASSROOM ALLOCATION TIMETABLE - A.Y: <?= htmlspecialchars($meta['ACADEMIC_YEAR']) ?></h3><br>   
                            <h3>Room: <?= htmlspecialchars($cls['ROOM_NUMBER']) ?></h3>
                            <br>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 12%;" class="timeWeek">Time</th>
                                    <?php foreach ($weekdays as $day): ?>
                                        <th class="timeWeek"><?= $day ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processedSlots as $slot): ?>
                                    <tr>
                                        <td>
                                        <b class="timeWeek"><?= date("h:i a", strtotime($slot['START_TIME'])) ?>  -  <?= date("h:i a", strtotime($slot['END_TIME'])) ?><br><?= ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) ? "(Practical- 2hrs )" : "" ?></b>
                                        </td>
                                        <?php if ($slot['SLOT_TYPE'] === 'BREAK'): ?>
                                            <td colspan="6" class="break">BREAK</td>
                                        <?php else: ?>
                                            <?php foreach ($weekdays as $day): 
                                                $entry = $cls['schedule'][$day][$slot['SLOT_ID']] ?? null;
                                            ?>
                                                <td class="slotBox">
                                                    <?php if ($entry): ?>
                                                        <b class="course"><?= htmlspecialchars($entry['COURSE_SHORT_NAME']) ?></b><br>
                                                        <b><?= htmlspecialchars($entry['PROGRAMME_NAME']) ?>&nbsp - Div <?= htmlspecialchars($entry['DIVISION_NAME']) ?></b><br>
                                                        (<?= htmlspecialchars($entry['TEACHER_NAME']) ?>)
                                                    <?php else: ?>
                                                        <div class="emptySlot">( FREE )</div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <br><br>
                    </div>
                <?php endforeach; ?>

            </body>
            </html>
            <?php
            exit;
        }
        elseif ($formtype == 'export_xls_old1') {
            $metaQuery = $conn->execute_query("SELECT ACADEMIC_YEAR, SEMESTER FROM TIMETABLE LIMIT 1");
            if ($metaQuery->num_rows == 0) {
                sendJsonResponse(404, "No timetable found.");
            }
            $meta = $metaQuery->fetch_assoc();

            $slotQuery = $conn->execute_query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME ASC");
            $allTimeslots = [];
            while ($s = $slotQuery->fetch_assoc()) { $allTimeslots[] = $s; }

            $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

            /**
             * Helper function: Compresses slots, removes edge breaks, and merges consecutive breaks
             */
            function processCompressedSlots($allTimeslots, $schedule, $weekdays) {
                $filtered = [];
                foreach ($allTimeslots as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        $filtered[] = $slot;
                    } else {
                        $hasData = false;
                        foreach ($weekdays as $day) {
                            if (isset($schedule[$day][$slot['SLOT_ID']])) {
                                $hasData = true;
                                break;
                            }
                        }
                        if ($hasData) {
                            $filtered[] = $slot;
                        }
                    }
                }

                while (!empty($filtered) && reset($filtered)['SLOT_TYPE'] === 'BREAK') {
                    array_shift($filtered);
                }

                while (!empty($filtered) && end($filtered)['SLOT_TYPE'] === 'BREAK') {
                    array_pop($filtered);
                }

                if (empty($filtered)) {
                    return [];
                }

                $merged = [];
                $tempBreak = null;

                foreach ($filtered as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        if ($tempBreak === null) {
                            $tempBreak = $slot;
                        } else {
                            $tempBreak['END_TIME'] = $slot['END_TIME'];
                        }
                    } else {
                        if ($tempBreak !== null) {
                            $merged[] = $tempBreak;
                            $tempBreak = null;
                        }
                        $merged[] = $slot;
                    }
                }

                if ($tempBreak !== null) {
                    $merged[] = $tempBreak;
                }

                return $merged;
            }

            // Set multi-worksheet Excel headers
            $filename = "Departmental_Timetables_" . $meta['ACADEMIC_YEAR'] . ".xls";
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header("Pragma: no-cache");
            header("Expires: 0");

            // XML Header for Multi-Sheet Workbook
            echo '<?xml version="1.0"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
            echo 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
            echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:html="http://www.w3.org/TR/REC-html40">';

            // Embedded CSS / Styles for Excel
            echo '<Styles>';
            echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/></Style>';
            echo '<Style ss:ID="HeaderTitle"><Font ss:FontName="Arial" ss:Size="13" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
            echo '<Style ss:ID="SubHeader"><Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
            echo '<Style ss:ID="TableHeader"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#F2F2F2" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="TableCell"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="BreakCell"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#E0E0E0" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '</Styles>';

            // Fetch All Departments
            $deptRes = $conn->execute_query("SELECT DEPARTMENT_ID, SHORT_NAME, LONG_NAME FROM DEPARTMENT ORDER BY SHORT_NAME");
            while ($dept = $deptRes->fetch_assoc()) {
                $deptId = $dept['DEPARTMENT_ID'];
                $deptName = htmlspecialchars($dept['SHORT_NAME']);

                echo '<Worksheet ss:Name="' . $deptName . '">';
                echo '<Table>';
                echo '<Column ss:Width="120"/>'; // Time column
                for ($i = 0; $i < 6; $i++) { echo '<Column ss:Width="130"/>'; } // Day columns

                // --- 1. DIVISION TIMETABLES FOR THIS DEPARTMENT ---
                $divRes = $conn->execute_query("
                    SELECT dv.DIVISION_ID, dv.NAME AS DIVISION_NAME, y.YEAR_NAME, p.SHORT_NAME AS PROGRAMME_NAME, 
                           p.LONG_NAME AS PROGRAMME_FULL_NAME, cr.ROOM_NUMBER
                    FROM DIVISION dv
                    JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                    JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                    LEFT JOIN CLASSROOM cr ON dv.CLASSROOM_ID = cr.CLASSROOM_ID
                    WHERE p.DEPARTMENT_ID = ?
                    ORDER BY p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME
                ", [$deptId]);

                while ($div = $divRes->fetch_assoc()) {
                    $schedRes = $conn->execute_query("
                        SELECT tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, 
                               cr.ROOM_NUMBER, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                        FROM TIMETABLE tt
                        JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                        JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                        JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                        WHERE tt.DIVISION_ID = ?
                    ", [$div['DIVISION_ID']]);

                    $divSchedule = [];
                    while ($r = $schedRes->fetch_assoc()) {
                        $divSchedule[$r['WEEKDAY']][$r['SLOT_ID']] = $r;
                    }

                    $processedSlots = processCompressedSlots($allTimeslots, $divSchedule, $weekdays);

                    // PDF Header Format in Excel Sheet
                    echo '<Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">KET\'s V. G. Vaze College of Arts, Science and Commerce (Autonomous)</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">Class Time Table ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($div['PROGRAMME_FULL_NAME']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . ucwords(strtolower(htmlspecialchars($div['YEAR_NAME']))) . ' - ' . ucwords(strtolower(htmlspecialchars($meta['SEMESTER']))) . ' Semester (Div ' . htmlspecialchars($div['DIVISION_NAME']) . ')</Data></Cell></Row>';
                    echo '<Row/>';

                    // Table Headers
                    echo '<Row ss:Height="20">';
                    echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Time</Data></Cell>';
                    foreach ($weekdays as $day) {
                        echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }
                    echo '</Row>';

                    // Table Body
                    foreach ($processedSlots as $slot) {
                        $timeText = date("h:i a", strtotime($slot['START_TIME'])) . " - " . date("h:i a", strtotime($slot['END_TIME']));
                        if ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) {
                            $timeText .= "\n(Practical - 2hrs)";
                        }

                        echo '<Row ss:Height="45">';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $timeText . '</Data></Cell>';

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:MergeAcross="5" ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else {
                            foreach ($weekdays as $day) {
                                if (isset($divSchedule[$day][$slot['SLOT_ID']])) {
                                    $entry = $divSchedule[$day][$slot['SLOT_ID']];
                                    $cellContent = $entry['COURSE_SHORT_NAME'] . "\n" . $entry['TEACHER_NAME'] . "\n(Room: " . $entry['ROOM_NUMBER'] . ")";
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $cellContent . '</Data></Cell>';
                                } else {
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                                }
                            }
                        }
                        echo '</Row>';
                    }
                    echo '<Row/><Row/>'; // Space between division tables
                }

                // --- 2. TEACHER ALLOCATION TIMETABLES FOR THIS DEPARTMENT ---
                $tchrRes = $conn->execute_query("
                    SELECT tr.TEACHER_ID, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME, d.LONG_NAME AS DEPARTMENT_LONG_NAME
                    FROM TEACHER tr
                    JOIN DEPARTMENT d ON tr.DEPARTMENT_ID = d.DEPARTMENT_ID
                    WHERE tr.DEPARTMENT_ID = ?
                    ORDER BY tr.FIRST_NAME
                ", [$deptId]);

                while ($tchr = $tchrRes->fetch_assoc()) {
                    $tchrSchedRes = $conn->execute_query("
                        SELECT tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, dv.NAME AS DIVISION_NAME, p.SHORT_NAME AS PROGRAMME_NAME, cr.ROOM_NUMBER
                        FROM TIMETABLE tt
                        JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                        JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                        JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                        JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                        WHERE tt.TEACHER_ID = ?
                    ", [$tchr['TEACHER_ID']]);

                    $tchrSchedule = [];
                    while ($r = $tchrSchedRes->fetch_assoc()) {
                        $tchrSchedule[$r['WEEKDAY']][$r['SLOT_ID']] = $r;
                    }

                    $processedSlots = processCompressedSlots($allTimeslots, $tchrSchedule, $weekdays);

                    // PDF Header Format for Teachers in Excel
                    echo '<Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">KET\'s V. G. Vaze College of Arts, Science and Commerce (Autonomous)</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">TEACHER ALLOCATION TIMETABLE - A.Y: ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($tchr['TEACHER_NAME']) . ' (' . htmlspecialchars($tchr['DEPARTMENT_LONG_NAME']) . ')</Data></Cell></Row>';
                    echo '<Row/>';

                    // Table Headers
                    echo '<Row ss:Height="20">';
                    echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Time</Data></Cell>';
                    foreach ($weekdays as $day) {
                        echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }
                    echo '</Row>';

                    // Table Body
                    foreach ($processedSlots as $slot) {
                        $timeText = date("h:i a", strtotime($slot['START_TIME'])) . " - " . date("h:i a", strtotime($slot['END_TIME']));
                        if ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) {
                            $timeText .= "\n(Practical - 2hrs)";
                        }

                        echo '<Row ss:Height="45">';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $timeText . '</Data></Cell>';

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:MergeAcross="5" ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else {
                            foreach ($weekdays as $day) {
                                if (isset($tchrSchedule[$day][$slot['SLOT_ID']])) {
                                    $entry = $tchrSchedule[$day][$slot['SLOT_ID']];
                                    $cellContent = $entry['COURSE_SHORT_NAME'] . "\n" . $entry['PROGRAMME_NAME'] . " - Div " . $entry['DIVISION_NAME'] . "\n(Room: " . $entry['ROOM_NUMBER'] . ")";
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . htmlspecialchars($cellContent) . '</Data></Cell>';
                                } else {
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                                }
                            }
                        }
                        echo '</Row>';
                    }
                    echo '<Row/><Row/>';
                }

                echo '</Table>';
                echo '</Worksheet>';
            }

            echo '</Workbook>';
            exit;
        }
        elseif ($formtype == 'export_xls_old2') {
            $metaQuery = $conn->execute_query("SELECT ACADEMIC_YEAR, SEMESTER FROM TIMETABLE LIMIT 1");
            if ($metaQuery->num_rows == 0) {
                sendJsonResponse(404, "No timetable found.");
            }
            $meta = $metaQuery->fetch_assoc();

            $slotQuery = $conn->execute_query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME ASC");
            $allTimeslots = [];
            while ($s = $slotQuery->fetch_assoc()) { $allTimeslots[] = $s; }

            $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

            /**
             * Helper function: Compresses slots, removes edge breaks, and merges consecutive breaks
             */
            function processCompressedSlots($allTimeslots, $schedule, $weekdays) {
                $filtered = [];
                foreach ($allTimeslots as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        $filtered[] = $slot;
                    } else {
                        $hasData = false;
                        foreach ($weekdays as $day) {
                            if (isset($schedule[$day][$slot['SLOT_ID']])) {
                                $hasData = true;
                                break;
                            }
                        }
                        if ($hasData) {
                            $filtered[] = $slot;
                        }
                    }
                }

                while (!empty($filtered) && reset($filtered)['SLOT_TYPE'] === 'BREAK') {
                    array_shift($filtered);
                }

                while (!empty($filtered) && end($filtered)['SLOT_TYPE'] === 'BREAK') {
                    array_pop($filtered);
                }

                if (empty($filtered)) {
                    return [];
                }

                $merged = [];
                $tempBreak = null;

                foreach ($filtered as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        if ($tempBreak === null) {
                            $tempBreak = $slot;
                        } else {
                            $tempBreak['END_TIME'] = $slot['END_TIME'];
                        }
                    } else {
                        if ($tempBreak !== null) {
                            $merged[] = $tempBreak;
                            $tempBreak = null;
                        }
                        $merged[] = $slot;
                    }
                }

                if ($tempBreak !== null) {
                    $merged[] = $tempBreak;
                }

                return $merged;
            }

            // Set multi-worksheet Excel headers
            $filename = "Departmental_Timetables_" . $meta['ACADEMIC_YEAR'] . ".xls";
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header("Pragma: no-cache");
            header("Expires: 0");

            // XML Header for Multi-Sheet Workbook
            echo '<?xml version="1.0"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
            echo 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
            echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:html="http://www.w3.org/TR/REC-html40">';

            // Embedded CSS / Styles for Excel
            echo '<Styles>';
            echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/></Style>';
            echo '<Style ss:ID="HeaderTitle"><Font ss:FontName="Arial" ss:Size="13" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
            echo '<Style ss:ID="SubHeader"><Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
            echo '<Style ss:ID="TableHeader"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#F2F2F2" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="TableCell"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="BreakCell"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#E0E0E0" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '</Styles>';

            // Fetch All Departments
            $deptRes = $conn->execute_query("SELECT DEPARTMENT_ID, SHORT_NAME, LONG_NAME FROM DEPARTMENT ORDER BY SHORT_NAME");
            while ($dept = $deptRes->fetch_assoc()) {
                $deptId = $dept['DEPARTMENT_ID'];
                $deptName = htmlspecialchars($dept['SHORT_NAME']);

                echo '<Worksheet ss:Name="' . $deptName . '">';
                echo '<Table>';
                echo '<Column ss:Width="120"/>'; // Time column
                for ($i = 0; $i < 6; $i++) { echo '<Column ss:Width="130"/>'; } // Day columns

                // --- 1. DIVISION TIMETABLES FOR THIS DEPARTMENT ---
                $divRes = $conn->execute_query("
                    SELECT dv.DIVISION_ID, dv.NAME AS DIVISION_NAME, y.YEAR_NAME, p.SHORT_NAME AS PROGRAMME_NAME, 
                           p.LONG_NAME AS PROGRAMME_FULL_NAME, cr.ROOM_NUMBER
                    FROM DIVISION dv
                    JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                    JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                    LEFT JOIN CLASSROOM cr ON dv.CLASSROOM_ID = cr.CLASSROOM_ID
                    WHERE p.DEPARTMENT_ID = ?
                    ORDER BY p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME
                ", [$deptId]);

                while ($div = $divRes->fetch_assoc()) {
                    $schedRes = $conn->execute_query("
                        SELECT tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, 
                               cr.ROOM_NUMBER, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                        FROM TIMETABLE tt
                        JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                        JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                        JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                        WHERE tt.DIVISION_ID = ?
                    ", [$div['DIVISION_ID']]);

                    $divSchedule = [];
                    while ($r = $schedRes->fetch_assoc()) {
                        $divSchedule[$r['WEEKDAY']][$r['SLOT_ID']] = $r;
                    }

                    $processedSlots = processCompressedSlots($allTimeslots, $divSchedule, $weekdays);

                    // Header Format in Excel Sheet
                    echo '<Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">KET\'s V. G. Vaze College of Arts, Science and Commerce (Autonomous)</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">Class Time Table ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($div['PROGRAMME_FULL_NAME']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . ucwords(strtolower(htmlspecialchars($div['YEAR_NAME']))) . ' - ' . ucwords(strtolower(htmlspecialchars($meta['SEMESTER']))) . ' Semester (Div ' . htmlspecialchars($div['DIVISION_NAME']) . ')</Data></Cell></Row>';
                    echo '<Row/>';

                    // Table Headers
                    echo '<Row ss:Height="20">';
                    echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Time</Data></Cell>';
                    foreach ($weekdays as $day) {
                        echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }
                    echo '</Row>';

                    // Table Body
                    foreach ($processedSlots as $slot) {
                        $timeText = date("h:i a", strtotime($slot['START_TIME'])) . " - " . date("h:i a", strtotime($slot['END_TIME']));
                        if ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) {
                            $timeText .= "\n(Practical - 2hrs)";
                        }

                        echo '<Row ss:Height="45">';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $timeText . '</Data></Cell>';

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:MergeAcross="5" ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else {
                            foreach ($weekdays as $day) {
                                if (isset($divSchedule[$day][$slot['SLOT_ID']])) {
                                    $entry = $divSchedule[$day][$slot['SLOT_ID']];
                                    $cellContent = $entry['COURSE_SHORT_NAME'] . "\n" . $entry['TEACHER_NAME'] . "\n(Room: " . $entry['ROOM_NUMBER'] . ")";
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . htmlspecialchars($cellContent) . '</Data></Cell>';
                                } else {
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                                }
                            }
                        }
                        echo '</Row>';
                    }
                    echo '<Row/><Row/>'; // Space between division tables
                }

                // --- 2. TEACHER ALLOCATION TIMETABLES FOR THIS DEPARTMENT ---
                $tchrRes = $conn->execute_query("
                    SELECT tr.TEACHER_ID, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME, d.LONG_NAME AS DEPARTMENT_LONG_NAME
                    FROM TEACHER tr
                    JOIN DEPARTMENT d ON tr.DEPARTMENT_ID = d.DEPARTMENT_ID
                    WHERE tr.DEPARTMENT_ID = ?
                    ORDER BY tr.FIRST_NAME
                ", [$deptId]);

                while ($tchr = $tchrRes->fetch_assoc()) {
                    $tchrSchedRes = $conn->execute_query("
                        SELECT tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, dv.NAME AS DIVISION_NAME, p.SHORT_NAME AS PROGRAMME_NAME, cr.ROOM_NUMBER
                        FROM TIMETABLE tt
                        JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                        JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                        JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                        JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                        WHERE tt.TEACHER_ID = ?
                    ", [$tchr['TEACHER_ID']]);

                    $tchrSchedule = [];
                    while ($r = $tchrSchedRes->fetch_assoc()) {
                        $tchrSchedule[$r['WEEKDAY']][$r['SLOT_ID']] = $r;
                    }

                    $processedSlots = processCompressedSlots($allTimeslots, $tchrSchedule, $weekdays);

                    // Header Format for Teachers in Excel
                    echo '<Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">KET\'s V. G. Vaze College of Arts, Science and Commerce (Autonomous)</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">TEACHER ALLOCATION TIMETABLE - A.Y: ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($tchr['TEACHER_NAME']) . ' (' . htmlspecialchars($tchr['DEPARTMENT_LONG_NAME']) . ')</Data></Cell></Row>';
                    echo '<Row/>';

                    // Table Headers
                    echo '<Row ss:Height="20">';
                    echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Time</Data></Cell>';
                    foreach ($weekdays as $day) {
                        echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }
                    echo '</Row>';

                    // Table Body
                    foreach ($processedSlots as $slot) {
                        $timeText = date("h:i a", strtotime($slot['START_TIME'])) . " - " . date("h:i a", strtotime($slot['END_TIME']));
                        if ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) {
                            $timeText .= "\n(Practical - 2hrs)";
                        }

                        echo '<Row ss:Height="45">';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $timeText . '</Data></Cell>';

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:MergeAcross="5" ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else {
                            foreach ($weekdays as $day) {
                                if (isset($tchrSchedule[$day][$slot['SLOT_ID']])) {
                                    $entry = $tchrSchedule[$day][$slot['SLOT_ID']];
                                    $cellContent = $entry['COURSE_SHORT_NAME'] . "\n" . $entry['PROGRAMME_NAME'] . " - Div " . $entry['DIVISION_NAME'] . "\n(Room: " . $entry['ROOM_NUMBER'] . ")";
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . htmlspecialchars($cellContent) . '</Data></Cell>';
                                } else {
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                                }
                            }
                        }
                        echo '</Row>';
                    }
                    echo '<Row/><Row/>';
                }

                echo '</Table>';
                echo '</Worksheet>';
            }

            // --- 3. NEW WORKSHEET: CLASSROOM ALLOCATIONS (UNTRIMMED, MERGED ROOM CELLS) ---
            // --- 3. NEW WORKSHEET: CLASSROOM ALLOCATIONS (FIXED EXCEL XML SCHEMA) ---
            echo '<Worksheet ss:Name="Classrooms">';
            echo '<Table>';
            echo '<Column ss:Width="120"/>'; // Room No
            echo '<Column ss:Width="100"/>'; // Weekday

            // Print columns for all timeslots
            foreach ($allTimeslots as $slot) {
                echo '<Column ss:Width="200"/>';
            }

            // Query Classroom Schedules
            $clsRes = $conn->execute_query("
                SELECT cr.CLASSROOM_ID, cr.ROOM_NUMBER
                FROM CLASSROOM cr
                ORDER BY cr.ROOM_NUMBER
            ");

            $clsSchedRes = $conn->execute_query("
                SELECT tt.CLASSROOM_ID, tt.SLOT_ID, tt.WEEKDAY,
                       c.SHORT_NAME AS COURSE_SHORT_NAME, y.YEAR_NAME,
                       p.SHORT_NAME AS PROGRAMME_NAME, dv.NAME AS DIVISION_NAME
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            $roomSchedules = [];
            while ($r = $clsSchedRes->fetch_assoc()) {
                $roomSchedules[$r['CLASSROOM_ID']][$r['WEEKDAY']][$r['SLOT_ID']] = $r;
            }

            // Table Header Row: TimeSlots
            echo '<Row ss:Height="25">';
            echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Room No</Data></Cell>';
            echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Weekday</Data></Cell>';
            foreach ($allTimeslots as $slot) {
                $timeRange = date("g:i", strtotime($slot['START_TIME'])) . " to " . date("g:i", strtotime($slot['END_TIME']));
                if ($slot['SLOT_TYPE'] === 'BREAK') {
                    $timeRange .= " (BREAK)";
                }
                echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . htmlspecialchars($timeRange) . '</Data></Cell>';
            }
            echo '</Row>';

            // Table Rows: Grouped by Classroom
            while ($cr = $clsRes->fetch_assoc()) {
                $classroomId = $cr['CLASSROOM_ID'];
                $roomNumber = htmlspecialchars($cr['ROOM_NUMBER']);
                $totalDays = count($weekdays);

                foreach ($weekdays as $index => $day) {
                    echo '<Row ss:Height="35">';

                    if ($index === 0) {
                        // First weekday row: Output Room Number with vertical merge
                        echo '<Cell ss:MergeDown="' . ($totalDays - 1) . '" ss:StyleID="TableCell"><Data ss:Type="String">' . $roomNumber . '</Data></Cell>';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    } else {
                        // Subsequent weekday rows: Use ss:Index="2" because Column 1 is occupied by the merged cell
                        echo '<Cell ss:Index="2" ss:StyleID="TableCell"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }

                    // Timeslot Cells
                    foreach ($allTimeslots as $slot) {
                        $slotId = $slot['SLOT_ID'];

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else if (isset($roomSchedules[$classroomId][$day][$slotId])) {
                            $entry = $roomSchedules[$classroomId][$day][$slotId];
                            $formattedDetail = htmlspecialchars($entry['COURSE_SHORT_NAME']) . '-' . 
                                               str_replace(' ', '', ucwords(strtolower(htmlspecialchars($entry['YEAR_NAME'])))) . '-' . 
                                               htmlspecialchars($entry['PROGRAMME_NAME']) . '-' . 
                                               htmlspecialchars($entry['DIVISION_NAME']);

                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $formattedDetail . '</Data></Cell>';
                        } else {
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                        }
                    }

                    echo '</Row>';
                }
            }

            echo '</Table>';
            echo '</Worksheet>';

            echo '</Workbook>';
            exit;
        }
        elseif ($formtype == 'export_xls_old3') {
            $metaQuery = $conn->execute_query("SELECT ACADEMIC_YEAR, SEMESTER FROM TIMETABLE LIMIT 1");
            if ($metaQuery->num_rows == 0) {
                sendJsonResponse(404, "No timetable found.");
            }
            $meta = $metaQuery->fetch_assoc();

            // Fetch timeslots, excluding BREAK slots
            $slotQuery = $conn->execute_query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT WHERE SLOT_TYPE != 'BREAK' ORDER BY START_TIME ASC");
            $lectureSlots = [];
            while ($s = $slotQuery->fetch_assoc()) { 
                $lectureSlots[] = $s; 
            }

            // Fetch all timeslots including breaks for existing departmental generators
            $allSlotQuery = $conn->execute_query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME ASC");
            $allTimeslots = [];
            while ($s = $allSlotQuery->fetch_assoc()) { 
                $allTimeslots[] = $s; 
            }

            $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
            $shortDays = ['MONDAY' => 'MON', 'TUESDAY' => 'TUE', 'WEDNESDAY' => 'WED', 'THURSDAY' => 'THU', 'FRIDAY' => 'FRI', 'SATURDAY' => 'SAT'];

            function processCompressedSlots($allTimeslots, $schedule, $weekdays) {
                if (empty($allTimeslots)) {
                    return [];
                }

                // Step 1: Identify which slot_ids have actual scheduled data on at least one day
                $activeSlotIds = [];
                foreach ($allTimeslots as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        continue;
                    }
                    foreach ($weekdays as $day) {
                        if (isset($schedule[$day][$slot['SLOT_ID']])) {
                            $activeSlotIds[$slot['SLOT_ID']] = true;
                            break;
                        }
                    }
                }

                // Step 2: Filter out redundant empty slots while preserving visual time flow
                $filteredSlots = [];
                $lastEndTime = null;

                foreach ($allTimeslots as $slot) {
                    $slotId   = $slot['SLOT_ID'];
                    $hasData  = isset($activeSlotIds[$slotId]);
                    $isBreak  = ($slot['SLOT_TYPE'] === 'BREAK');

                    // Always retain BREAKs and slots that contain actual scheduled classes
                    if ($hasData || $isBreak) {
                        $filteredSlots[] = $slot;
                        $lastEndTime = $slot['END_TIME'];
                        continue;
                    }

                    // Drop empty overlapping/duplicate 2-hour practical slots
                    $isPracticalSlot = ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL']));
                    if ($isPracticalSlot) {
                        continue;
                    }

                    // Keep intermediate empty 1-hour lecture slots IF AND ONLY IF they fall between scheduled lectures
                    if ($lastEndTime !== null && strtotime($slot['START_TIME']) >= strtotime($lastEndTime)) {
                        $hasFutureData = false;
                        foreach ($allTimeslots as $futureSlot) {
                            if (strtotime($futureSlot['START_TIME']) > strtotime($slot['START_TIME']) 
                                && isset($activeSlotIds[$futureSlot['SLOT_ID']])) {
                                $hasFutureData = true;
                                break;
                            }
                        }

                        if ($hasFutureData) {
                            $filteredSlots[] = $slot;
                            $lastEndTime = $slot['END_TIME'];
                        }
                    }
                }

                // Step 3: Trim leading and trailing BREAK slots
                while (!empty($filteredSlots) && reset($filteredSlots)['SLOT_TYPE'] === 'BREAK') {
                    array_shift($filteredSlots);
                }
                while (!empty($filteredSlots) && end($filteredSlots)['SLOT_TYPE'] === 'BREAK') {
                    array_pop($filteredSlots);
                }

                // Step 4: Merge consecutive BREAK slots into a single continuous break block
                $merged = [];
                $tempBreak = null;

                foreach ($filteredSlots as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        if ($tempBreak === null) {
                            $tempBreak = $slot;
                        } else {
                            $tempBreak['END_TIME'] = $slot['END_TIME'];
                        }
                    } else {
                        if ($tempBreak !== null) {
                            $merged[] = $tempBreak;
                            $tempBreak = null;
                        }
                        $merged[] = $slot;
                    }
                }

                if ($tempBreak !== null) {
                    $merged[] = $tempBreak;
                }

                return $merged;
            }

            // Set multi-worksheet Excel headers
            $filename = "Departmental_Timetables_" . $meta['ACADEMIC_YEAR'] . ".xls";
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header("Pragma: no-cache");
            header("Expires: 0");

            // XML Header for Multi-Sheet Workbook
            echo '<?xml version="1.0"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
            echo 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
            echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:html="http://www.w3.org/TR/REC-html40">';

            // Embedded CSS / Styles for Excel
            echo '<Styles>';
            echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/></Style>';
            echo '<Style ss:ID="HeaderTitle"><Font ss:FontName="Arial" ss:Size="13" ss:Bold="1" ss:Color="#FFFFFF"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#595959" ss:Pattern="Solid"/></Style>';
            echo '<Style ss:ID="MasterTitle"><Font ss:FontName="Arial" ss:Size="12" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#E0E0E0" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="SubHeader"><Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
            echo '<Style ss:ID="TableHeader"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#F2F2F2" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="TableCell"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="BreakCell"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#E0E0E0" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '</Styles>';

            // =========================================================================
            // --- 1. NEW WORKSHEET: MASTER TIMETABLE ---
            // =========================================================================
            echo '<Worksheet ss:Name="Master Timetable">';
            echo '<Table>';
            echo '<Column ss:Width="50"/>';  // Sr. No.
            echo '<Column ss:Width="65"/>';  // Capacity
            echo '<Column ss:Width="90"/>';  // Room Number
            echo '<Column ss:Width="55"/>';  // Day

            // Each lecture timeslot takes 3 sub-columns: Class, Subject Name, Teacher Name
            $totalLectureSlots = count($lectureSlots);
            for ($i = 0; $i < $totalLectureSlots * 3; $i++) {
                echo '<Column ss:Width="100"/>';
            }

            $totalColumnsAcross = 4 + ($totalLectureSlots * 3);
            $mergeAcrossTitle = $totalColumnsAcross - 5; // Start at col 5

            // Row 1: Header Title
            echo '<Row ss:Height="25">';
            // echo '<Cell ss:StyleID="HeaderTitle"><Data ss:Type="String">N</Data></Cell>';
            // echo '<Cell ss:MergeAcross="' . ($totalColumnsAcross - 2) . '" ss:StyleID="HeaderTitle"><Data ss:Type="String">MASTER TIME TABLE ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell>';
            echo '<Cell ss:MergeAcross="' . 3 . '" ss:StyleID="HeaderTitle"></Cell>';
            echo '<Cell ss:MergeAcross="' . ($totalColumnsAcross - 1 - 4) . '" ss:StyleID="HeaderTitle"><Data ss:Type="String">MASTER TIME TABLE ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell>';
            echo '</Row>';

            // Row 2: Top Header Labels (MergeDown="1" spans across Row 2 and Row 3)
            echo '<Row ss:Height="20">';
            echo '<Cell ss:MergeDown="1" ss:StyleID="TableHeader"><Data ss:Type="String">Sr. No.</Data></Cell>';
            echo '<Cell ss:MergeDown="1" ss:StyleID="TableHeader"><Data ss:Type="String">Capacity</Data></Cell>';
            echo '<Cell ss:MergeDown="1" ss:StyleID="TableHeader"><Data ss:Type="String">ROOM NUMBER</Data></Cell>';
            echo '<Cell ss:MergeDown="1" ss:StyleID="TableHeader"><Data ss:Type="String">DAY</Data></Cell>';
            echo '<Cell ss:MergeAcross="' . (($totalLectureSlots * 3) - 1) . '" ss:StyleID="MasterTitle"><Data ss:Type="String">TIME</Data></Cell>';
            echo '</Row>';

            // Row 3: Time Slot Headers (Requires ss:Index="5" to skip merged columns 1-4)
            echo '<Row ss:Height="22">';
            foreach ($lectureSlots as $idx => $slot) {
                $timeFormatted = date("g:i A", strtotime($slot['START_TIME'])) . " - " . date("g:i A", strtotime($slot['END_TIME']));
                $indexAttr = ($idx === 0) ? ' ss:Index="5"' : '';
                echo '<Cell' . $indexAttr . ' ss:MergeAcross="2" ss:StyleID="TableHeader"><Data ss:Type="String">' . htmlspecialchars($timeFormatted) . '</Data></Cell>';
            }
            echo '</Row>';

            // Row 4: Sub-headers (Requires ss:Index="5" to start sub-headers at column 5)
            echo '<Row ss:Height="20">';
            for ($s = 0; $s < $totalLectureSlots; $s++) {
                $indexAttr = ($s === 0) ? ' ss:Index="5"' : '';
                echo '<Cell' . $indexAttr . ' ss:StyleID="TableHeader"><Data ss:Type="String">Class</Data></Cell>';
                echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Subject Name</Data></Cell>';
                echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Teacher Name</Data></Cell>';
            }
            echo '</Row>';

            // Fetch Classrooms & Allocations
            $classroomsRes = $conn->execute_query("SELECT CLASSROOM_ID, ROOM_NUMBER, CAPACITY FROM CLASSROOM ORDER BY ROOM_NUMBER ASC");
            $masterClassrooms = [];
            while ($c = $classroomsRes->fetch_assoc()) {
                $masterClassrooms[] = $c;
            }

            $masterSchedRes = $conn->execute_query("
                SELECT tt.CLASSROOM_ID, tt.SLOT_ID, tt.WEEKDAY,
                       c.SHORT_NAME AS COURSE_SHORT_NAME, c.ISPRACTICAL,
                       y.YEAR_NAME, p.SHORT_NAME AS PROGRAMME_NAME, dv.NAME AS DIVISION_NAME,
                       CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            $masterData = [];
            while ($r = $masterSchedRes->fetch_assoc()) {
                $masterData[$r['CLASSROOM_ID']][$r['WEEKDAY']][$r['SLOT_ID']] = $r;
            }

            // Populate Master Timetable Rows
            $srNo = 1;
            foreach ($masterClassrooms as $cr) {
                $cId = $cr['CLASSROOM_ID'];
                $rNum = htmlspecialchars($cr['ROOM_NUMBER']);
                $cap = htmlspecialchars($cr['CAPACITY'] ?? '-');

                foreach ($weekdays as $dayIndex => $day) {
                    echo '<Row ss:Height="30">';

                    if ($dayIndex === 0) {
                        echo '<Cell ss:MergeDown="5" ss:StyleID="TableCell"><Data ss:Type="Number">' . $srNo . '</Data></Cell>';
                        echo '<Cell ss:MergeDown="5" ss:StyleID="TableCell"><Data ss:Type="String">' . $cap . '</Data></Cell>';
                        echo '<Cell ss:MergeDown="5" ss:StyleID="TableCell"><Data ss:Type="String">' . $rNum . '</Data></Cell>';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $shortDays[$day] . '</Data></Cell>';
                    } else {
                        echo '<Cell ss:Index="4" ss:StyleID="TableCell"><Data ss:Type="String">' . $shortDays[$day] . '</Data></Cell>';
                    }

                    // Render allotments per slot
                    $slotCount = count($lectureSlots);
                    for ($sIdx = 0; $sIdx < $slotCount; $sIdx++) {
                        $sId = $lectureSlots[$sIdx]['SLOT_ID'];
                        $entry = $masterData[$cId][$day][$sId] ?? null;

                        // Check if previous slot was a practical that spans into this slot
                        $prevSlotId = ($sIdx > 0) ? $lectureSlots[$sIdx - 1]['SLOT_ID'] : null;
                        $prevEntry = ($prevSlotId && isset($masterData[$cId][$day][$prevSlotId])) ? $masterData[$cId][$day][$prevSlotId] : null;

                        if ($entry) {
                            $className = htmlspecialchars($entry['PROGRAMME_NAME']) . '-' . htmlspecialchars($entry['DIVISION_NAME']);
                            $subjName = htmlspecialchars($entry['COURSE_SHORT_NAME']);
                            if (!empty($entry['ISPRACTICAL'])) {
                                $subjName .= " (Pr)";
                            }
                            $teacherName = htmlspecialchars($entry['TEACHER_NAME']);

                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $className . '</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $subjName . '</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $teacherName . '</Data></Cell>';
                        } elseif ($prevEntry && !empty($prevEntry['ISPRACTICAL'])) {
                            // Render second allotment for a 2-hour practical course
                            $className = htmlspecialchars($prevEntry['PROGRAMME_NAME']) . '-' . htmlspecialchars($prevEntry['DIVISION_NAME']);
                            $subjName = htmlspecialchars($prevEntry['COURSE_SHORT_NAME']) . " (Pr)";
                            $teacherName = htmlspecialchars($prevEntry['TEACHER_NAME']);

                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $className . '</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $subjName . '</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $teacherName . '</Data></Cell>';
                        } else {
                            // Empty allotment
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                        }
                    }

                    echo '</Row>';
                }
                $srNo++;
            }

            echo '</Table>';
            echo '</Worksheet>';

            // =========================================================================
            // --- 2. EXISTING DEPARTMENTAL & TEACHER WORKSHEETS (PRESERVED) ---
            // =========================================================================
            $deptRes = $conn->execute_query("SELECT DEPARTMENT_ID, SHORT_NAME, LONG_NAME FROM DEPARTMENT ORDER BY SHORT_NAME");
            while ($dept = $deptRes->fetch_assoc()) {
                $deptId = $dept['DEPARTMENT_ID'];
                $deptName = htmlspecialchars($dept['SHORT_NAME']);

                echo '<Worksheet ss:Name="' . $deptName . '">';
                echo '<Table>';
                echo '<Column ss:Width="120"/>'; // Time column
                for ($i = 0; $i < 6; $i++) { echo '<Column ss:Width="130"/>'; } // Day columns

                // --- DIVISION TIMETABLES ---
                $divRes = $conn->execute_query("
                    SELECT dv.DIVISION_ID, dv.NAME AS DIVISION_NAME, y.YEAR_NAME, p.SHORT_NAME AS PROGRAMME_NAME, 
                           p.LONG_NAME AS PROGRAMME_FULL_NAME, cr.ROOM_NUMBER
                    FROM DIVISION dv
                    JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                    JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                    LEFT JOIN CLASSROOM cr ON dv.CLASSROOM_ID = cr.CLASSROOM_ID
                    WHERE p.DEPARTMENT_ID = ?
                    ORDER BY p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME
                ", [$deptId]);

                while ($div = $divRes->fetch_assoc()) {
                    $schedRes = $conn->execute_query("
                        SELECT tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, 
                               cr.ROOM_NUMBER, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                        FROM TIMETABLE tt
                        JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                        JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                        JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                        WHERE tt.DIVISION_ID = ?
                    ", [$div['DIVISION_ID']]);

                    $divSchedule = [];
                    while ($r = $schedRes->fetch_assoc()) {
                        $divSchedule[$r['WEEKDAY']][$r['SLOT_ID']] = $r;
                    }

                    $processedSlots = processCompressedSlots($allTimeslots, $divSchedule, $weekdays);

                    // Header Format in Excel Sheet
                    echo '<Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">KET\'s V. G. Vaze College of Arts, Science and Commerce (Autonomous)</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">Class Time Table ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($div['PROGRAMME_FULL_NAME']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . ucwords(strtolower(htmlspecialchars($div['YEAR_NAME']))) . ' - ' . ucwords(strtolower(htmlspecialchars($meta['SEMESTER']))) . ' Semester (Div ' . htmlspecialchars($div['DIVISION_NAME']) . ')</Data></Cell></Row>';
                    echo '<Row/>';

                    // Table Headers
                    echo '<Row ss:Height="20">';
                    echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Time</Data></Cell>';
                    foreach ($weekdays as $day) {
                        echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }
                    echo '</Row>';

                    // Table Body
                    foreach ($processedSlots as $slot) {
                        $timeText = date("h:i a", strtotime($slot['START_TIME'])) . " - " . date("h:i a", strtotime($slot['END_TIME']));
                        if ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) {
                            $timeText .= "\n(Practical - 2hrs)";
                        }

                        echo '<Row ss:Height="45">';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $timeText . '</Data></Cell>';

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:MergeAcross="5" ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else {
                            foreach ($weekdays as $day) {
                                if (isset($divSchedule[$day][$slot['SLOT_ID']])) {
                                    $entry = $divSchedule[$day][$slot['SLOT_ID']];
                                    $cellContent = $entry['COURSE_SHORT_NAME'] . "\n" . $entry['TEACHER_NAME'] . "\n(Room: " . $entry['ROOM_NUMBER'] . ")";
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . htmlspecialchars($cellContent) . '</Data></Cell>';
                                } else {
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                                }
                            }
                        }
                        echo '</Row>';
                    }
                    echo '<Row/><Row/>';
                }

                // --- TEACHER TIMETABLES ---
                $tchrRes = $conn->execute_query("
                    SELECT tr.TEACHER_ID, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME, d.LONG_NAME AS DEPARTMENT_LONG_NAME
                    FROM TEACHER tr
                    JOIN DEPARTMENT d ON tr.DEPARTMENT_ID = d.DEPARTMENT_ID
                    WHERE tr.DEPARTMENT_ID = ?
                    ORDER BY tr.FIRST_NAME
                ", [$deptId]);

                while ($tchr = $tchrRes->fetch_assoc()) {
                    $tchrSchedRes = $conn->execute_query("
                        SELECT tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, dv.NAME AS DIVISION_NAME, p.SHORT_NAME AS PROGRAMME_NAME, cr.ROOM_NUMBER
                        FROM TIMETABLE tt
                        JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                        JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                        JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                        JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                        WHERE tt.TEACHER_ID = ?
                    ", [$tchr['TEACHER_ID']]);

                    $tchrSchedule = [];
                    while ($r = $tchrSchedRes->fetch_assoc()) {
                        $tchrSchedule[$r['WEEKDAY']][$r['SLOT_ID']] = $r;
                    }

                    $processedSlots = processCompressedSlots($allTimeslots, $tchrSchedule, $weekdays);

                    // Header Format for Teachers in Excel
                    echo '<Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">KET\'s V. G. Vaze College of Arts, Science and Commerce (Autonomous)</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">TEACHER ALLOCATION TIMETABLE - A.Y: ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($tchr['TEACHER_NAME']) . ' (' . htmlspecialchars($tchr['DEPARTMENT_LONG_NAME']) . ')</Data></Cell></Row>';
                    echo '<Row/>';

                    // Table Headers
                    echo '<Row ss:Height="20">';
                    echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Time</Data></Cell>';
                    foreach ($weekdays as $day) {
                        echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }
                    echo '</Row>';

                    // Table Body
                    foreach ($processedSlots as $slot) {
                        $timeText = date("h:i a", strtotime($slot['START_TIME'])) . " - " . date("h:i a", strtotime($slot['END_TIME']));
                        if ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) {
                            $timeText .= "\n(Practical - 2hrs)";
                        }

                        echo '<Row ss:Height="45">';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $timeText . '</Data></Cell>';

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:MergeAcross="5" ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else {
                            foreach ($weekdays as $day) {
                                if (isset($tchrSchedule[$day][$slot['SLOT_ID']])) {
                                    $entry = $tchrSchedule[$day][$slot['SLOT_ID']];
                                    $cellContent = $entry['COURSE_SHORT_NAME'] . "\n" . $entry['PROGRAMME_NAME'] . " - Div " . $entry['DIVISION_NAME'] . "\n(Room: " . $entry['ROOM_NUMBER'] . ")";
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . htmlspecialchars($cellContent) . '</Data></Cell>';
                                } else {
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                                }
                            }
                        }
                        echo '</Row>';
                    }
                    echo '<Row/><Row/>';
                }

                echo '</Table>';
                echo '</Worksheet>';
            }
            /*
            // =========================================================================
            // --- 3. EXISTING CLASSROOM ALLOCATIONS WORKSHEET (PRESERVED) ---
            // =========================================================================
            echo '<Worksheet ss:Name="Classrooms">';
            echo '<Table>';
            echo '<Column ss:Width="120"/>'; // Room No
            echo '<Column ss:Width="100"/>'; // Weekday

            foreach ($allTimeslots as $slot) {
                echo '<Column ss:Width="200"/>';
            }

            $clsRes = $conn->execute_query("
                SELECT cr.CLASSROOM_ID, cr.ROOM_NUMBER
                FROM CLASSROOM cr
                ORDER BY cr.ROOM_NUMBER
            ");

            $clsSchedRes = $conn->execute_query("
                SELECT tt.CLASSROOM_ID, tt.SLOT_ID, tt.WEEKDAY,
                       c.SHORT_NAME AS COURSE_SHORT_NAME, y.YEAR_NAME,
                       p.SHORT_NAME AS PROGRAMME_NAME, dv.NAME AS DIVISION_NAME
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            $roomSchedules = [];
            while ($r = $clsSchedRes->fetch_assoc()) {
                $roomSchedules[$r['CLASSROOM_ID']][$r['WEEKDAY']][$r['SLOT_ID']] = $r;
            }

            // Table Header Row: TimeSlots
            echo '<Row ss:Height="25">';
            echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Room No</Data></Cell>';
            echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Weekday</Data></Cell>';
            foreach ($allTimeslots as $slot) {
                $timeRange = date("g:i", strtotime($slot['START_TIME'])) . " to " . date("g:i", strtotime($slot['END_TIME']));
                if ($slot['SLOT_TYPE'] === 'BREAK') {
                    $timeRange .= " (BREAK)";
                }
                echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . htmlspecialchars($timeRange) . '</Data></Cell>';
            }
            echo '</Row>';

            // Table Rows: Grouped by Classroom
            while ($cr = $clsRes->fetch_assoc()) {
                $classroomId = $cr['CLASSROOM_ID'];
                $roomNumber = htmlspecialchars($cr['ROOM_NUMBER']);
                $totalDays = count($weekdays);

                foreach ($weekdays as $index => $day) {
                    echo '<Row ss:Height="35">';

                    if ($index === 0) {
                        echo '<Cell ss:MergeDown="' . ($totalDays - 1) . '" ss:StyleID="TableCell"><Data ss:Type="String">' . $roomNumber . '</Data></Cell>';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    } else {
                        echo '<Cell ss:Index="2" ss:StyleID="TableCell"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }

                    foreach ($allTimeslots as $slot) {
                        $slotId = $slot['SLOT_ID'];

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else if (isset($roomSchedules[$classroomId][$day][$slotId])) {
                            $entry = $roomSchedules[$classroomId][$day][$slotId];
                            $formattedDetail = htmlspecialchars($entry['COURSE_SHORT_NAME']) . '-' . 
                                               str_replace(' ', '', ucwords(strtolower(htmlspecialchars($entry['YEAR_NAME'])))) . '-' . 
                                               htmlspecialchars($entry['PROGRAMME_NAME']) . '-' . 
                                               htmlspecialchars($entry['DIVISION_NAME']);

                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $formattedDetail . '</Data></Cell>';
                        } else {
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                        }
                    }

                    echo '</Row>';
                }
            }

            echo '</Table>';
            echo '</Worksheet>';
            */
            echo '</Workbook>';
            exit;
        }
        elseif ($formtype == 'export_xls') {
            $metaQuery = $conn->execute_query("SELECT ACADEMIC_YEAR, SEMESTER FROM TIMETABLE LIMIT 1");
            if ($metaQuery->num_rows == 0) {
                sendJsonResponse(404, "No timetable found.");
            }
            $meta = $metaQuery->fetch_assoc();

            // MINIMAL CHANGE: Fetch ONLY lecture slots so practicals span across them instead of getting their own column
            $slotQuery = $conn->execute_query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT WHERE SLOT_TYPE = 'LECTURE' ORDER BY START_TIME ASC");
            $lectureSlots = [];
            while ($s = $slotQuery->fetch_assoc()) { 
                $lectureSlots[] = $s; 
            }

            // Fetch all timeslots including breaks for existing departmental generators
            $allSlotQuery = $conn->execute_query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME ASC");
            $allTimeslots = [];
            while ($s = $allSlotQuery->fetch_assoc()) { 
                $allTimeslots[] = $s; 
            }

            $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
            $shortDays = ['MONDAY' => 'MON', 'TUESDAY' => 'TUE', 'WEDNESDAY' => 'WED', 'THURSDAY' => 'THU', 'FRIDAY' => 'FRI', 'SATURDAY' => 'SAT'];

            function processCompressedSlots($allTimeslots, $schedule, $weekdays) {
                if (empty($allTimeslots)) {
                    return [];
                }

                // Step 1: Identify which slot_ids have actual scheduled data on at least one day
                $activeSlotIds = [];
                foreach ($allTimeslots as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        continue;
                    }
                    foreach ($weekdays as $day) {
                        if (isset($schedule[$day][$slot['SLOT_ID']])) {
                            $activeSlotIds[$slot['SLOT_ID']] = true;
                            break;
                        }
                    }
                }

                // Step 2: Filter out redundant empty slots while preserving visual time flow
                $filteredSlots = [];
                $lastEndTime = null;

                foreach ($allTimeslots as $slot) {
                    $slotId   = $slot['SLOT_ID'];
                    $hasData  = isset($activeSlotIds[$slotId]);
                    $isBreak  = ($slot['SLOT_TYPE'] === 'BREAK');

                    // Always retain BREAKs and slots that contain actual scheduled classes
                    if ($hasData || $isBreak) {
                        $filteredSlots[] = $slot;
                        $lastEndTime = $slot['END_TIME'];
                        continue;
                    }

                    // Drop empty overlapping/duplicate 2-hour practical slots
                    $isPracticalSlot = ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL']));
                    if ($isPracticalSlot) {
                        continue;
                    }

                    // Keep intermediate empty 1-hour lecture slots IF AND ONLY IF they fall between scheduled lectures
                    if ($lastEndTime !== null && strtotime($slot['START_TIME']) >= strtotime($lastEndTime)) {
                        $hasFutureData = false;
                        foreach ($allTimeslots as $futureSlot) {
                            if (strtotime($futureSlot['START_TIME']) > strtotime($slot['START_TIME']) 
                                && isset($activeSlotIds[$futureSlot['SLOT_ID']])) {
                                $hasFutureData = true;
                                break;
                            }
                        }

                        if ($hasFutureData) {
                            $filteredSlots[] = $slot;
                            $lastEndTime = $slot['END_TIME'];
                        }
                    }
                }

                // Step 3: Trim leading and trailing BREAK slots
                while (!empty($filteredSlots) && reset($filteredSlots)['SLOT_TYPE'] === 'BREAK') {
                    array_shift($filteredSlots);
                }
                while (!empty($filteredSlots) && end($filteredSlots)['SLOT_TYPE'] === 'BREAK') {
                    array_pop($filteredSlots);
                }

                // Step 4: Merge consecutive BREAK slots into a single continuous break block
                $merged = [];
                $tempBreak = null;

                foreach ($filteredSlots as $slot) {
                    if ($slot['SLOT_TYPE'] === 'BREAK') {
                        if ($tempBreak === null) {
                            $tempBreak = $slot;
                        } else {
                            $tempBreak['END_TIME'] = $slot['END_TIME'];
                        }
                    } else {
                        if ($tempBreak !== null) {
                            $merged[] = $tempBreak;
                            $tempBreak = null;
                        }
                        $merged[] = $slot;
                    }
                }

                if ($tempBreak !== null) {
                    $merged[] = $tempBreak;
                }

                return $merged;
            }

            // Set multi-worksheet Excel headers
            $filename = "Departmental_Timetables_" . $meta['ACADEMIC_YEAR'] . ".xls";
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header("Pragma: no-cache");
            header("Expires: 0");

            // XML Header for Multi-Sheet Workbook
            echo '<?xml version="1.0"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
            echo 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
            echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:html="http://www.w3.org/TR/REC-html40">';

            // Embedded CSS / Styles for Excel
            echo '<Styles>';
            echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/></Style>';
            echo '<Style ss:ID="HeaderTitle"><Font ss:FontName="Arial" ss:Size="13" ss:Bold="1" ss:Color="#FFFFFF"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#595959" ss:Pattern="Solid"/></Style>';
            echo '<Style ss:ID="MasterTitle"><Font ss:FontName="Arial" ss:Size="12" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#E0E0E0" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="SubHeader"><Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
            echo '<Style ss:ID="TableHeader"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#F2F2F2" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="TableCell"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="BreakCell"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#E0E0E0" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '</Styles>';

            // =========================================================================
            // --- 1. NEW WORKSHEET: MASTER TIMETABLE ---
            // =========================================================================
            echo '<Worksheet ss:Name="Master Timetable">';
            echo '<Table>';
            echo '<Column ss:Width="50"/>';  // Sr. No.
            echo '<Column ss:Width="65"/>';  // Capacity
            echo '<Column ss:Width="90"/>';  // Room Number
            echo '<Column ss:Width="55"/>';  // Day

            // Set specific widths for each sub-column in every timeslot block
            $totalLectureSlots = count($lectureSlots);
            for ($s = 0; $s < $totalLectureSlots; $s++) {
                echo '<Column ss:Width="80"/>';  // Width for "Class"
                echo '<Column ss:Width="130"/>'; // Width for "Subject Name"
                echo '<Column ss:Width="130"/>'; // Width for "Teacher Name"
            }

            $totalColumnsAcross = 4 + ($totalLectureSlots * 3);
            $mergeAcrossTitle = $totalColumnsAcross - 5; // Start at col 5

            // Row 1: Header Title
            echo '<Row ss:Height="25">';
            // echo '<Cell ss:StyleID="HeaderTitle"><Data ss:Type="String">N</Data></Cell>';
            // echo '<Cell ss:MergeAcross="' . ($totalColumnsAcross - 2) . '" ss:StyleID="HeaderTitle"><Data ss:Type="String">MASTER TIME TABLE ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell>';
            echo '<Cell ss:MergeAcross="' . 3 . '" ss:StyleID="HeaderTitle"></Cell>';
            echo '<Cell ss:MergeAcross="' . ($totalColumnsAcross - 1 - 4) . '" ss:StyleID="HeaderTitle"><Data ss:Type="String">MASTER TIME TABLE ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell>';
            echo '</Row>';

            // Row 2: Top Header Labels (MergeDown="1" spans across Row 2 and Row 3)
            echo '<Row ss:Height="20">';
            echo '<Cell ss:MergeDown="1" ss:StyleID="TableHeader"><Data ss:Type="String">Sr. No.</Data></Cell>';
            echo '<Cell ss:MergeDown="1" ss:StyleID="TableHeader"><Data ss:Type="String">Capacity</Data></Cell>';
            echo '<Cell ss:MergeDown="1" ss:StyleID="TableHeader"><Data ss:Type="String">ROOM NUMBER</Data></Cell>';
            echo '<Cell ss:MergeDown="1" ss:StyleID="TableHeader"><Data ss:Type="String">DAY</Data></Cell>';
            echo '<Cell ss:MergeAcross="' . (($totalLectureSlots * 3) - 1) . '" ss:StyleID="MasterTitle"><Data ss:Type="String">TIME</Data></Cell>';
            echo '</Row>';

            // Row 3: Time Slot Headers (Requires ss:Index="5" to skip merged columns 1-4)
            echo '<Row ss:Height="22">';
            foreach ($lectureSlots as $idx => $slot) {
                $timeFormatted = date("g:i A", strtotime($slot['START_TIME'])) . " - " . date("g:i A", strtotime($slot['END_TIME']));
                $indexAttr = ($idx === 0) ? ' ss:Index="5"' : '';
                echo '<Cell' . $indexAttr . ' ss:MergeAcross="2" ss:StyleID="TableHeader"><Data ss:Type="String">' . htmlspecialchars($timeFormatted) . '</Data></Cell>';
            }
            echo '</Row>';

            // Row 4: Sub-headers (Requires ss:Index="5" to start sub-headers at column 5)
            echo '<Row ss:Height="20">';
            for ($s = 0; $s < $totalLectureSlots; $s++) {
                $indexAttr = ($s === 0) ? ' ss:Index="5"' : '';
                echo '<Cell' . $indexAttr . ' ss:StyleID="TableHeader"><Data ss:Type="String">Class</Data></Cell>';
                echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Subject Name</Data></Cell>';
                echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Teacher Name</Data></Cell>';
            }
            echo '</Row>';

            // Fetch Classrooms & Allocations
            $classroomsRes = $conn->execute_query("SELECT CLASSROOM_ID, ROOM_NUMBER, CAPACITY FROM CLASSROOM ORDER BY ROOM_NUMBER ASC");
            $masterClassrooms = [];
            while ($c = $classroomsRes->fetch_assoc()) {
                $masterClassrooms[] = $c;
            }

            $masterSchedRes = $conn->execute_query("
                SELECT tt.CLASSROOM_ID, tt.SLOT_ID, tt.WEEKDAY,
                    c.SHORT_NAME AS COURSE_SHORT_NAME, c.ISPRACTICAL,
                    y.YEAR_NAME, p.SHORT_NAME AS PROGRAMME_NAME, dv.NAME AS DIVISION_NAME,
                    CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME,
                    ts.START_TIME, ts.END_TIME, ts.SLOT_TYPE
                FROM TIMETABLE tt
                JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                JOIN TIMESLOT ts ON tt.SLOT_ID = ts.SLOT_ID
                WHERE tt.ACADEMIC_YEAR = ? AND tt.SEMESTER = ?
            ", [$meta['ACADEMIC_YEAR'], $meta['SEMESTER']]);

            $masterData = [];
            while ($r = $masterSchedRes->fetch_assoc()) {
                $cId = $r['CLASSROOM_ID'];
                $day = $r['WEEKDAY'];

                $allocStart = strtotime($r['START_TIME']);
                $allocEnd   = strtotime($r['END_TIME']);

                // Map this allocation to EVERY 1-hour lecture slot that falls within its time range
                foreach ($lectureSlots as $lSlot) {
                    $slotStart = strtotime($lSlot['START_TIME']);
                    $slotEnd   = strtotime($lSlot['END_TIME']);

                    if ($slotStart < $allocEnd && $slotEnd > $allocStart) {
                        $masterData[$cId][$day][$lSlot['SLOT_ID']] = $r;
                    }
                }
            }

            // Populate Master Timetable Rows
            $srNo = 1;
            foreach ($masterClassrooms as $cr) {
                $cId = $cr['CLASSROOM_ID'];
                $rNum = htmlspecialchars($cr['ROOM_NUMBER']);
                $cap = htmlspecialchars($cr['CAPACITY'] ?? '-');

                foreach ($weekdays as $dayIndex => $day) {
                    echo '<Row ss:Height="30">';

                    if ($dayIndex === 0) {
                        echo '<Cell ss:MergeDown="5" ss:StyleID="TableCell"><Data ss:Type="Number">' . $srNo . '</Data></Cell>';
                        echo '<Cell ss:MergeDown="5" ss:StyleID="TableCell"><Data ss:Type="String">' . $cap . '</Data></Cell>';
                        echo '<Cell ss:MergeDown="5" ss:StyleID="TableCell"><Data ss:Type="String">' . $rNum . '</Data></Cell>';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $shortDays[$day] . '</Data></Cell>';
                    } else {
                        echo '<Cell ss:Index="4" ss:StyleID="TableCell"><Data ss:Type="String">' . $shortDays[$day] . '</Data></Cell>';
                    }

                    // Render allotments per slot
                    $slotCount = count($lectureSlots);
                    for ($sIdx = 0; $sIdx < $slotCount; $sIdx++) {
                        $sId = $lectureSlots[$sIdx]['SLOT_ID'];
                        $entry = $masterData[$cId][$day][$sId] ?? null;

                        if ($entry) {

                            $className = 
                                strtoupper(htmlspecialchars(($entry['YEAR_NAME'] == "FIRST YEAR")? "FY " : (($entry['YEAR_NAME'] == "SECOND YEAR")? "SY " : (($entry['YEAR_NAME'] == "THIRD YEAR")? "TY " : "")))) .
                                htmlspecialchars($entry['PROGRAMME_NAME']) .
                                '- ' .
                                htmlspecialchars($entry['DIVISION_NAME']);

                            $subjName = htmlspecialchars($entry['COURSE_SHORT_NAME']);

                            if (!empty($entry['ISPRACTICAL']) || $entry['SLOT_TYPE'] === 'PRACTICAL') {
                                $subjName .= " (Pr)";
                            }
                            $teacherName = htmlspecialchars($entry['TEACHER_NAME']);

                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $className . '</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $subjName . '</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $teacherName . '</Data></Cell>';
                        } else {
                            // Empty allotment
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                            echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                        }
                    }

                    echo '</Row>';
                }
                $srNo++;
            }

            echo '</Table>';
            echo '</Worksheet>';

            // =========================================================================
            // --- 2. EXISTING DEPARTMENTAL & TEACHER WORKSHEETS (PRESERVED) ---
            // =========================================================================
            $deptRes = $conn->execute_query("SELECT DEPARTMENT_ID, SHORT_NAME, LONG_NAME FROM DEPARTMENT ORDER BY SHORT_NAME");
            while ($dept = $deptRes->fetch_assoc()) {
                $deptId = $dept['DEPARTMENT_ID'];
                $deptName = htmlspecialchars($dept['SHORT_NAME']);

                echo '<Worksheet ss:Name="' . $deptName . '">';
                echo '<Table>';
                echo '<Column ss:Width="120"/>'; // Time column
                for ($i = 0; $i < 6; $i++) { echo '<Column ss:Width="130"/>'; } // Day columns

                // --- DIVISION TIMETABLES ---
                $divRes = $conn->execute_query("
                    SELECT dv.DIVISION_ID, dv.NAME AS DIVISION_NAME, y.YEAR_NAME, p.SHORT_NAME AS PROGRAMME_NAME, 
                           p.LONG_NAME AS PROGRAMME_FULL_NAME, cr.ROOM_NUMBER
                    FROM DIVISION dv
                    JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                    JOIN YEAR y ON dv.YEAR_NUMBER = y.YEAR_NUMBER
                    LEFT JOIN CLASSROOM cr ON dv.CLASSROOM_ID = cr.CLASSROOM_ID
                    WHERE p.DEPARTMENT_ID = ?
                    ORDER BY p.SHORT_NAME, y.YEAR_NUMBER, dv.NAME
                ", [$deptId]);

                while ($div = $divRes->fetch_assoc()) {
                    $schedRes = $conn->execute_query("
                        SELECT tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, 
                               cr.ROOM_NUMBER, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME
                        FROM TIMETABLE tt
                        JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                        JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                        JOIN TEACHER tr ON tt.TEACHER_ID = tr.TEACHER_ID
                        WHERE tt.DIVISION_ID = ?
                    ", [$div['DIVISION_ID']]);

                    $divSchedule = [];
                    while ($r = $schedRes->fetch_assoc()) {
                        $divSchedule[$r['WEEKDAY']][$r['SLOT_ID']] = $r;
                    }

                    $processedSlots = processCompressedSlots($allTimeslots, $divSchedule, $weekdays);

                    // Header Format in Excel Sheet
                    echo '<Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">KET\'s V. G. Vaze College of Arts, Science and Commerce (Autonomous)</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">Class Time Table ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($div['PROGRAMME_FULL_NAME']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . ucwords(strtolower(htmlspecialchars($div['YEAR_NAME']))) . ' - ' . ucwords(strtolower(htmlspecialchars($meta['SEMESTER']))) . ' Semester (Div ' . htmlspecialchars($div['DIVISION_NAME']) . ')</Data></Cell></Row>';
                    echo '<Row/>';

                    // Table Headers
                    echo '<Row ss:Height="20">';
                    echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Time</Data></Cell>';
                    foreach ($weekdays as $day) {
                        echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }
                    echo '</Row>';

                    // Table Body
                    foreach ($processedSlots as $slot) {
                        $timeText = date("h:i a", strtotime($slot['START_TIME'])) . " - " . date("h:i a", strtotime($slot['END_TIME']));
                        if ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) {
                            $timeText .= "\n(Practical - 2hrs)";
                        }

                        echo '<Row ss:Height="45">';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $timeText . '</Data></Cell>';

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:MergeAcross="5" ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else {
                            foreach ($weekdays as $day) {
                                if (isset($divSchedule[$day][$slot['SLOT_ID']])) {
                                    $entry = $divSchedule[$day][$slot['SLOT_ID']];
                                    $cellContent = $entry['COURSE_SHORT_NAME'] . "\n" . $entry['TEACHER_NAME'] . "\n(Room: " . $entry['ROOM_NUMBER'] . ")";
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . htmlspecialchars($cellContent) . '</Data></Cell>';
                                } else {
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                                }
                            }
                        }
                        echo '</Row>';
                    }
                    echo '<Row/><Row/>';
                }

                // --- TEACHER TIMETABLES ---
                $tchrRes = $conn->execute_query("
                    SELECT tr.TEACHER_ID, CONCAT(tr.FIRST_NAME, ' ', tr.LAST_NAME) AS TEACHER_NAME, d.LONG_NAME AS DEPARTMENT_LONG_NAME
                    FROM TEACHER tr
                    JOIN DEPARTMENT d ON tr.DEPARTMENT_ID = d.DEPARTMENT_ID
                    WHERE tr.DEPARTMENT_ID = ?
                    ORDER BY tr.FIRST_NAME
                ", [$deptId]);

                while ($tchr = $tchrRes->fetch_assoc()) {
                    $tchrSchedRes = $conn->execute_query("
                        SELECT tt.SLOT_ID, tt.WEEKDAY, c.SHORT_NAME AS COURSE_SHORT_NAME, dv.NAME AS DIVISION_NAME, p.SHORT_NAME AS PROGRAMME_NAME, cr.ROOM_NUMBER
                        FROM TIMETABLE tt
                        JOIN COURSE c ON tt.COURSE_ID = c.COURSE_ID
                        JOIN DIVISION dv ON tt.DIVISION_ID = dv.DIVISION_ID
                        JOIN PROGRAMME p ON dv.PROGRAMME_ID = p.PROGRAMME_ID
                        JOIN CLASSROOM cr ON tt.CLASSROOM_ID = cr.CLASSROOM_ID
                        WHERE tt.TEACHER_ID = ?
                    ", [$tchr['TEACHER_ID']]);

                    $tchrSchedule = [];
                    while ($r = $tchrSchedRes->fetch_assoc()) {
                        $tchrSchedule[$r['WEEKDAY']][$r['SLOT_ID']] = $r;
                    }

                    $processedSlots = processCompressedSlots($allTimeslots, $tchrSchedule, $weekdays);

                    // Header Format for Teachers in Excel
                    echo '<Row ss:Height="22"><Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle"><Data ss:Type="String">KET\'s V. G. Vaze College of Arts, Science and Commerce (Autonomous)</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">TEACHER ALLOCATION TIMETABLE - A.Y: ' . htmlspecialchars($meta['ACADEMIC_YEAR']) . '</Data></Cell></Row>';
                    echo '<Row ss:Height="18"><Cell ss:MergeAcross="6" ss:StyleID="SubHeader"><Data ss:Type="String">' . htmlspecialchars($tchr['TEACHER_NAME']) . ' (' . htmlspecialchars($tchr['DEPARTMENT_LONG_NAME']) . ')</Data></Cell></Row>';
                    echo '<Row/>';

                    // Table Headers
                    echo '<Row ss:Height="20">';
                    echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">Time</Data></Cell>';
                    foreach ($weekdays as $day) {
                        echo '<Cell ss:StyleID="TableHeader"><Data ss:Type="String">' . $day . '</Data></Cell>';
                    }
                    echo '</Row>';

                    // Table Body
                    foreach ($processedSlots as $slot) {
                        $timeText = date("h:i a", strtotime($slot['START_TIME'])) . " - " . date("h:i a", strtotime($slot['END_TIME']));
                        if ($slot['SLOT_TYPE'] === 'PRACTICAL' || !empty($slot['ISPRACTICAL'])) {
                            $timeText .= "\n(Practical - 2hrs)";
                        }

                        echo '<Row ss:Height="45">';
                        echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . $timeText . '</Data></Cell>';

                        if ($slot['SLOT_TYPE'] === 'BREAK') {
                            echo '<Cell ss:MergeAcross="5" ss:StyleID="BreakCell"><Data ss:Type="String">BREAK</Data></Cell>';
                        } else {
                            foreach ($weekdays as $day) {
                                if (isset($tchrSchedule[$day][$slot['SLOT_ID']])) {
                                    $entry = $tchrSchedule[$day][$slot['SLOT_ID']];
                                    $cellContent = $entry['COURSE_SHORT_NAME'] . "\n" . $entry['PROGRAMME_NAME'] . " - Div " . $entry['DIVISION_NAME'] . "\n(Room: " . $entry['ROOM_NUMBER'] . ")";
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">' . htmlspecialchars($cellContent) . '</Data></Cell>';
                                } else {
                                    echo '<Cell ss:StyleID="TableCell"><Data ss:Type="String">-</Data></Cell>';
                                }
                            }
                        }
                        echo '</Row>';
                    }
                    echo '<Row/><Row/>';
                }

                echo '</Table>';
                echo '</Worksheet>';
            }
            echo '</Workbook>';
            exit;
        }
    }
    

} catch (Throwable $e) {
    // Catch ALL exceptions/errors and return them as valid JSON
    sendJsonResponse(500, 'Server Error', "An error occured...Contact system admin.");
    // sendJsonResponse(500, 'Server Error', $e->getMessage());
}

?>