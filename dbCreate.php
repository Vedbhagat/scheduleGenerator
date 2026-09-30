<?php
include 'dbConnect.php';
echo "Creating Tables";


function runquery($connection, $query, $tablename){
  echo "<br>Creating " . $tablename . " table...<br>";
  try {
    $stmt = $connection->query($query);
    echo "  Succesfully<br>";
  } catch (mysqli_sql_exception $e) {
    echo "  Error creating " . $tablename . " table..." . $e->getMessage() . "<br>";
  }
}
function AddClassrooms($connection,$classroom){
  foreach($classroom as $room){
    $query = "INSERT INTO CLASSROOM (FLOOR_NUMBER,ROOM_NUMBER,CATEGORY) VALUES('{$room[0]}','{$room[1]}','{$room[2]}')";
    $result = $connection -> query($query);
    if($connection->affected_rows==1){
      echo "Added classroom as ". $room[0] .' - ' .$room[1] .'<br>';
    }
    else{
      echo "Couldn't add classroom as ". $room[0] .' - ' .$room[1] .'<br>';
    }
  }
}
function AddWeekdays($connection,$weekdays){
  foreach($weekdays as $weekday){
    $query = "INSERT INTO WEEKDAY (WEEKDAY) VALUES('{$weekday}')";
    $result = $connection -> query($query);
    if($connection->affected_rows==1){
      echo "Added Weekday as ". $weekday .'<br>';
    }
    else{
      echo "Couldn't add Weekday as ". $weekday .'<br>';
    }
  }
}
function AddYears($connection,$years){
  $counter = 1;
  foreach($years as $year){
    $query = "INSERT INTO YEAR (YEAR_NUMBER, YEAR_NAME) VALUES({$counter},'{$year}')";
    $result = $connection -> query($query);
    if($connection->affected_rows==1){
      echo "Added Year as ". $year .'<br>';
    }
    else{
      echo "Couldn't add Year as ". $year .'<br>';
    }
    $counter+=1;
  }
}

runquery($conn, "
      CREATE TABLE IF NOT EXISTS CLASSROOM(
        CLASSROOM_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
        FLOOR_NUMBER VARCHAR(15) NOT NULL,
        ROOM_NUMBER VARCHAR(15) NOT NULL UNIQUE,
        CAPACITY INT,
        START_TIME TIME,
        END_TIME TIME,
        CATEGORY ENUM('LECTURE HALL', 'IT LAB', 'PHYSICS LAB', 'CHEMISTRY LAB', 'BIOLOGY LAB') DEFAULT 'LECTURE HALL',

        CHECK(CAPACITY >= 20 AND CAPACITY <= 200),
        CHECK(START_TIME < END_TIME)
      );
    ", "Classroom");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS WEEKDAY(
        WEEKDAY ENUM('MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY') PRIMARY KEY NOT NULL UNIQUE
      );
    ", "Weekday");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS TIMESLOT(
        SLOT_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
        START_TIME TIME NOT NULL,
        END_TIME TIME NOT NULL,
        SLOT_TYPE ENUM('BREAK', 'LECTURE', 'PRACTICAL') NOT NULL DEFAULT 'LECTURE',
        
        CHECK(START_TIME < END_TIME)
      );
    ", "Timeslot");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS USER(
        USERNAME VARCHAR(31) NOT NULL PRIMARY KEY,
        PASSWORD VARCHAR(255) NOT NULL
      );
    ", "User");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS DEPARTMENT(
        DEPARTMENT_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,  
        LONG_NAME VARCHAR(63) NOT NULL,
        SHORT_NAME VARCHAR(31) NOT NULL
      );
    ", "Department");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS TEACHER(
        TEACHER_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
        DEPARTMENT_ID INT NOT NULL,
        FIRST_NAME VARCHAR(15) NOT NULL,
        LAST_NAME VARCHAR(15) NOT NULL,
        ISPARTTIME BOOLEAN NOT NULL DEFAULT(0),

        CONSTRAINT fk_deptId_tchrTbl
        FOREIGN KEY (DEPARTMENT_ID) 
        REFERENCES DEPARTMENT(DEPARTMENT_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
      );
    ", "Teacher");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS AVAILABILITY(
        TEACHER_ID INT NOT NULL,
        SLOT_ID INT NOT NULL,
        WEEKDAY ENUM('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY') NOT NULL,
        STATUS ENUM('AVAILABLE','ALLOTED') DEFAULT 'AVAILABLE',

        PRIMARY KEY(TEACHER_ID, SLOT_ID, WEEKDAY),
        UNIQUE(TEACHER_ID, SLOT_ID, WEEKDAY),

        CONSTRAINT fk_tchrId_avlbtTbl
        FOREIGN KEY (TEACHER_ID) 
        REFERENCES TEACHER(TEACHER_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_slotId_avlbtTbl
        FOREIGN KEY (SLOT_ID) 
        REFERENCES TIMESLOT(SLOT_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_wkdy_avlbtTbl
        FOREIGN KEY (WEEKDAY) 
        REFERENCES WEEKDAY(WEEKDAY)
        ON DELETE CASCADE
        ON UPDATE CASCADE
      );
    ", "Availability");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS PROGRAMME(
        PROGRAMME_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
        DEPARTMENT_ID INT,
        LONG_NAME VARCHAR(63) NOT NULL,
        SHORT_NAME VARCHAR(15) NOT NULL,
        DIVISION_COUNT INT DEFAULT 1,

        CHECK (DIVISION_COUNT BETWEEN 1 AND 25),

        CONSTRAINT fk_deptId_pgrmTbl 
        FOREIGN KEY (DEPARTMENT_ID) 
        REFERENCES DEPARTMENT(DEPARTMENT_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
      );
    ", "Programme");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS YEAR(
        YEAR_NUMBER INT PRIMARY KEY UNIQUE,
        YEAR_NAME ENUM('FIRST YEAR', 'SECOND YEAR', 'THIRD YEAR', 'FOURTH YEAR', 'FIFTH YEAR') NOT NULL
      );
    ", "Year");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS CONSISTS(
        YEAR_NUMBER INT,
        PROGRAMME_ID INT,

        PRIMARY KEY(YEAR_NUMBER, PROGRAMME_ID),

        CONSTRAINT fk_yearId_cnstTbl 
        FOREIGN KEY (YEAR_NUMBER) 
        REFERENCES YEAR(YEAR_NUMBER)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_pgrmId_cnstTbl 
        FOREIGN KEY (PROGRAMME_ID) 
        REFERENCES PROGRAMME(PROGRAMME_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
      );
    ", "Consists");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS DIVISION(
        DIVISION_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
        YEAR_NUMBER INT,        
        PROGRAMME_ID INT,
        NAME CHAR(1) NOT NULL,
        STUDENT_COUNT INT,
        START_TIME_ID INT,
        END_TIME_ID INT,
        CLASSROOM_ID INT,

        CHECK(STUDENT_COUNT > 0),

        UNIQUE(NAME, YEAR_NUMBER, PROGRAMME_ID),

        CONSTRAINT fk_yrId_dvsnTbl 
        FOREIGN KEY (YEAR_NUMBER) 
        REFERENCES YEAR(YEAR_NUMBER)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_pgrmId_dvsnTbl 
        FOREIGN KEY (PROGRAMME_ID) 
        REFERENCES PROGRAMME(PROGRAMME_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_clsrmId_dvsnTbl 
        FOREIGN KEY (CLASSROOM_ID) 
        REFERENCES CLASSROOM(CLASSROOM_ID)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

        CONSTRAINT fk_sTmeId_dvsnTbl 
        FOREIGN KEY (START_TIME_ID) 
        REFERENCES TIMESLOT(SLOT_ID)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

        CONSTRAINT fk_eTmeId_dvsnTbl 
        FOREIGN KEY (END_TIME_ID) 
        REFERENCES TIMESLOT(SLOT_ID)
        ON DELETE SET NULL
        ON UPDATE CASCADE
      );
    ", "Division");

    
runquery($conn, "
      CREATE TABLE IF NOT EXISTS COURSE(
        COURSE_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
        OPTIONAL_ID INT,
        YEAR_NUMBER INT,
        PROGRAMME_ID INT,
        TYPE ENUM('LECTURE','IT PRACTICAL','PHYSICS PRACTICAL','BIOLOGY PRACTICAL','CHEMISTRY PRACTICAL'),
        SEMESTER ENUM('EVEN','ODD') NOT NULL,
        LONG_NAME VARCHAR(100) NOT NULL,
        SHORT_NAME VARCHAR(20) NOT NULL,
        WEEKLY_LECTURES INT NOT NULL DEFAULT 0,
        ISPRACTICAL BOOLEAN DEFAULT FALSE,
        ISOPTIONAL BOOLEAN DEFAULT FALSE,

        CONSTRAINT fk_pgrmId_crseTbl 
        FOREIGN KEY (PROGRAMME_ID) 
        REFERENCES PROGRAMME(PROGRAMME_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_yearId_crseTbl 
        FOREIGN KEY (YEAR_NUMBER) 
        REFERENCES YEAR(YEAR_NUMBER)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_opnlId_crseTbl 
        FOREIGN KEY (OPTIONAL_ID) 
        REFERENCES COURSE(COURSE_ID)
        ON DELETE SET NULL
        ON UPDATE CASCADE
      );
    ", "Course");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS OPTED_BY(
        COURSE_ID INT,
        DIVISION_ID INT,

        PRIMARY KEY(COURSE_ID, DIVISION_ID),

        CONSTRAINT fk_crseId_optdByTbl 
        FOREIGN KEY (COURSE_ID) 
        REFERENCES COURSE(COURSE_ID),
        
        CONSTRAINT fk_dvsnId_optdByTbl
        FOREIGN KEY (DIVISION_ID) 
        REFERENCES DIVISION(DIVISION_ID)
      );
    ", "opted_by");

/*runquery($conn, "
      CREATE TABLE IF NOT EXISTS GIVEN(
        SLOT_ID INT,
        WEEKDAY ENUM('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'),
        CLASSROOM_ID INT,
        DIVISION_ID INT,

        PRIMARY KEY(SLOT_ID, CLASSROOM_ID, DIVISION_ID),

        CONSTRAINT fk_slotId_gvenTbl
        FOREIGN KEY (SLOT_ID) 
        REFERENCES TIMESLOT(SLOT_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_clsrmId_gvenTbl
        FOREIGN KEY (CLASSROOM_ID) 
        REFERENCES CLASSROOM(CLASSROOM_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_dvsnId_gvenTbl
        FOREIGN KEY (DIVISION_ID) 
        REFERENCES DIVISION(DIVISION_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_wkdy_gvenTbl
        FOREIGN KEY (WEEKDAY) 
        REFERENCES WEEKDAY(WEEKDAY)
        ON DELETE CASCADE
        ON UPDATE CASCADE
      );
    ", "Given");*/

runquery($conn, "
      CREATE TABLE IF NOT EXISTS TEACHES(
        WORKLOAD_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
        TEACHER_ID INT,
        COURSE_ID INT,
        DIVISION_ID INT,
        LECTURE_COUNT INT NOT NULL DEFAULT(0),
        
        UNIQUE(TEACHER_ID,COURSE_ID,DIVISION_ID),

        CONSTRAINT fk_tchrId_tchsTbl 
        FOREIGN KEY (TEACHER_ID) 
        REFERENCES TEACHER(TEACHER_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_crseId_tchsTbl 
        FOREIGN KEY (COURSE_ID) 
        REFERENCES COURSE(COURSE_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_dvsn_tchsTbl 
        FOREIGN KEY (DIVISION_ID) 
        REFERENCES DIVISION(DIVISION_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE

      );
    ", "Teaches");

runquery($conn, "
      CREATE TABLE IF NOT EXISTS TIMETABLE(
        ALLOTMENT_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
        COURSE_ID INT,
        DIVISION_ID INT,
        CLASSROOM_ID INT,
        SLOT_ID INT,
        WEEKDAY ENUM('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'),
        TEACHER_ID INT,
        ACADEMIC_YEAR VARCHAR(7) NOT NULL,
        SEMESTER ENUM('EVEN','ODD') NOT NULL,

        UNIQUE (COURSE_ID, DIVISION_ID, CLASSROOM_ID, SLOT_ID, WEEKDAY, TEACHER_ID, ACADEMIC_YEAR, SEMESTER),        

        CONSTRAINT fk_crseId_tTbl 
        FOREIGN KEY (COURSE_ID) 
        REFERENCES COURSE(COURSE_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_dvsnId_tTbl 
        FOREIGN KEY (DIVISION_ID) 
        REFERENCES DIVISION(DIVISION_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_clsrmId_tTbl 
        FOREIGN KEY (CLASSROOM_ID) 
        REFERENCES CLASSROOM(CLASSROOM_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_slotId_tTbl 
        FOREIGN KEY (SLOT_ID) 
        REFERENCES TIMESLOT(SLOT_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_wkdy_tTbl
        FOREIGN KEY (WEEKDAY) 
        REFERENCES WEEKDAY(WEEKDAY)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

        CONSTRAINT fk_tchrId_tTbl 
        FOREIGN KEY (TEACHER_ID) 
        REFERENCES TEACHER(TEACHER_ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
      );
    ", "Timetable");




// $rooms = [
//   ['Ground Floor',  001,  'LECTURE_HALL'],
//   ['Ground Floor',  002,  'LECTURE_HALL'],
//   ['First Floor',   101,  'LECTURE_HALL'],
//   ['First Floor',   102,  'LAB'],
//   ['Second Floor',  201,  'LAB'],
//   ['Second Floor',  202,  'LAB']
// ];
$rooms = [
  ['First Floor',   "IT Lab 02",  'IT LAB'],
  ['First Floor',   "IT Lab 01",  'IT LAB'],
  ['First Floor',   "E-Leaning Lab",  'IT LAB'],
  ['First Floor',   "108",  'LECTURE HALL'],
  ['Second Floor',  "008",  'LECTURE HALL'],
  ['Fourth Floor',  "401",  'LECTURE HALL'],
];
$weekdays = [
  'MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'
];
$years = [
  'FIRST YEAR', 'SECOND YEAR', 'THIRD YEAR', 'FOURTH YEAR', 'FIFTH YEAR'
];

AddClassrooms($conn,$rooms);
AddWeekdays($conn,$weekdays);
AddYears($conn,$years);



