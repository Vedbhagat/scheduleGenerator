<?php
/**
 * generatorv2.php
 * Dynamic Division-Aware Timetable Engine with Atomic Allocation & Gap Elimination
 */

include_once 'dbConnect.php';

$GLOBALS['allocationLog'] = "";

function logStep($message) {
    $GLOBALS['allocationLog'] .= $message . "\n";
}

function generateTimetable($semester = 'ODD', $academicYear = '2026-27') {
    global $conn;
    $GLOBALS['allocationLog'] = "";

    logStep("=== START TIMETABLE GENERATION PROCESS ===");

    // 1. Fetch static lookups
    $timeslots        = getTimeslots($conn);
    $overlappingSlots = getOverlappingSlotMap($timeslots);
    $classrooms       = getClassrooms($conn);
    $rawDivisions     = getDivisions($conn);
    $teacherAvail     = getTeacherAvailability($conn);
    $teachersMap      = getTeachersMap($conn);
    $coursesMap       = getCoursesMap($conn);

    // 2. Fetch & expand workloads
    $rawWorkloads      = getWorkload($conn, $semester);
    $expandedWorkloads = expandWorkload($conn, $rawWorkloads, $semester);

    // Build structured divisions array
    $divisions = [];
    foreach ($rawDivisions as $dId => $divData) {
        $divLectures = array_filter($expandedWorkloads['lectures'], function($unit) use ($dId) {
            return (int)$unit['division_id'] === (int)$dId;
        });
        $divisions[$dId] = $divData;
        $divisions[$dId]['workload'] = array_values($divLectures);
    }

    // 3. Assign practical slots
    $divisionPracticalSlots = fixPracticalSlot($timeslots, $rawDivisions, $expandedWorkloads['practicals'], $classrooms);

    // Initialization of System State Tracking
    $state = [
        'assigned'              => [],
        'teacherOccupied'       => [], // [teacher_id][weekday][slot_id] = true
        'divisionOccupied'      => [], // [division_id][weekday][slot_id] = true
        'roomOccupied'          => [], // [room_id][weekday][slot_id] = true
        'divisionDayRoom'       => [], // [division_id][weekday] = classroom_id
        'courseDayCount'        => [], // [division_id][course_id][weekday] = count
        'divisionDailyLectures' => [], // [division_id][weekday] = total lecture count
        'unallocated'           => [],
        'brokenPreferences'     => []
    ];

    // 4. Allocate practical workloads
    logStep("\n--- ALLOCATING PRACTICAL WORKLOADS ---");
    $state = allocatePracticals(
        $expandedWorkloads['practicals'], 
        $divisionPracticalSlots, 
        $classrooms, 
        $teacherAvail, 
        $overlappingSlots, 
        $state,
        $teachersMap,
        $coursesMap,
        $timeslots
    );

    // 5. Retrieve lecture timeslots
    $lectureSlots = [];
    foreach ($timeslots as $sId => $sData) {
        if (($sData['SLOT_TYPE'] ?? '') === 'LECTURE') {
            $lectureSlots[] = (int)$sId;
        }
    }
    if (empty($lectureSlots)) {
        $lectureSlots = [1, 3, 7, 8, 10, 11, 14, 15]; // Fallback standard slot IDs
    }

    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

    // 6. Allocate lecture workloads with strict mutual exclusivity & gap elimination
    logStep("\n--- ALLOCATING LECTURE WORKLOADS ---");
    allocateCoursesBalanced(
        $divisions,
        $expandedWorkloads['lectures'],
        $lectureSlots,
        $weekdays,
        $classrooms,
        $teacherAvail,
        $overlappingSlots,
        $state,
        $timeslots,
        $teachersMap,
        $coursesMap,
        false
    );

    // 7. Final Validation & Audit
    $validationReport = runFinalValidation(
        $expandedWorkloads['all'], 
        $state, 
        $divisions, 
        $timeslots
    );

    logStep("\n=== GENERATION PROCESS COMPLETE ===");

    // echo htmlspecialchars($GLOBALS['allocationLog']);

    return [
        'validation' => $validationReport,
        'timetable'  => $state['assigned'],
        'log'        => $GLOBALS['allocationLog']
    ];
}

// -------------------------------------------------------------------------
// STATE & AVAILABILITY HELPERS
// -------------------------------------------------------------------------

function getDivisionAvailableSlots($dId, $day, $candidateLectureSlots, $overlappingSlots, $state, $timeslots = [], $divisions = []) {
    $availableSlots = [];

    $prefStartId = $divisions[$dId]['START_TIME_ID'] ?? null;
    $prefEndId   = $divisions[$dId]['END_TIME_ID'] ?? null;

    $prefStartTime = ($prefStartId && isset($timeslots[$prefStartId])) 
        ? strtotime($timeslots[$prefStartId]['START_TIME']) : null;
    $prefEndTime   = ($prefEndId && isset($timeslots[$prefEndId])) 
        ? strtotime($timeslots[$prefEndId]['END_TIME']) : null;

    foreach ($candidateLectureSlots as $slotId) {
        if (isset($timeslots[$slotId])) {
            $slotStart = strtotime($timeslots[$slotId]['START_TIME']);
            $slotEnd   = strtotime($timeslots[$slotId]['END_TIME']);

            if ($prefStartTime !== null && $slotStart < $prefStartTime) continue;
            if ($prefEndTime !== null && $slotEnd > $prefEndTime) continue;
        }

        if (!isSlotOccupiedByDivision($dId, $day, $slotId, $overlappingSlots, $state)) {
            $availableSlots[] = (int)$slotId;
        }
    }

    return $availableSlots;
}

function isSlotOccupiedByDivision($dId, $day, $slotId, $overlappingSlots, $state) {
    $relatedSlots = $overlappingSlots[$slotId] ?? [$slotId];
    foreach ($relatedSlots as $s) {
        if (!empty($state['divisionOccupied'][$dId][$day][$s])) {
            return true;
        }
    }
    return false;
}

/**
 * STRICT TEACHER OCCUPANCY CHECK
 * Checks if a teacher is genuinely booked for a specific slot or an active multi-hour block.
 */
function isTeacherOccupied($tId, $day, $slotId, $overlappingSlots, $state) {
    // 1. Direct Slot Match Check
    if (!empty($state['teacherOccupied'][$tId][$day][$slotId])) {
        return true;
    }

    // 2. Overlap Check (Only trigger if an overlapping multi-hour assignment exists)
    $relatedSlots = $overlappingSlots[$slotId] ?? [];
    foreach ($relatedSlots as $s) {
        if ((int)$s !== (int)$slotId && !empty($state['teacherOccupied'][$tId][$day][$s])) {
            // Confirm whether the occupation at slot $s actually overlaps in time
            return true;
        }
    }

    return false;
}

function isRoomOccupied($roomId, $day, $slotId, $overlappingSlots, $state) {
    $relatedSlots = $overlappingSlots[$slotId] ?? [$slotId];
    foreach ($relatedSlots as $s) {
        if (!empty($state['roomOccupied'][$roomId][$day][$s])) {
            return true;
        }
    }
    return false;
}

function markStateOccupied(&$state, $tId, $dId, $roomId, $day, $slotId, $overlappingSlots) {
    $relatedSlots = $overlappingSlots[$slotId] ?? [$slotId];
    foreach ($relatedSlots as $s) {
        $state['teacherOccupied'][$tId][$day][$s]  = true;
        $state['divisionOccupied'][$dId][$day][$s] = true;
        $state['roomOccupied'][$roomId][$day][$s]  = true;
    }
}

function getPendingWorkloadForDivision(&$unassignedWorkload, $dId) {
    return $unassignedWorkload[$dId] ?? [];
}

/**
 * ACCURATE CANDIDATE EVALUATION
 * Evaluates pending workload candidates with clean state isolation.
 */
function getAvailableTeacherFromWorkload(
    $pendingWorkload, 
    $dId, 
    $day, 
    $slotId, 
    $teacherAvail, 
    $overlappingSlots, 
    $state, 
    $teachersMap = [], 
    $coursesMap = [], 
    $timeslots = [], 
    $maxLecturesPerDay = 2
) {
    $workloadCount = count($pendingWorkload);
    $slotLabel = isset($timeslots[$slotId]) 
        ? "{$timeslots[$slotId]['START_TIME']}-{$timeslots[$slotId]['END_TIME']} (Slot ID {$slotId})" 
        : "Slot ID {$slotId}";

    for ($i = 0; $i < $workloadCount; $i++) {
        if (!isset($pendingWorkload[$i])) continue;

        $lec = $pendingWorkload[$i];
        $tId = (int)$lec['teacher_id'];
        $cId = (int)$lec['course_id'];

        $tName = $teachersMap[$tId] ?? "Teacher ID {$tId}";
        $cName = $coursesMap[$cId] ?? "Course ID {$cId}";

        // 1. Daily Course Limit Check
        $currentCourseDayCount = $state['courseDayCount'][$dId][$cId][$day] ?? 0;
        if ($currentCourseDayCount >= $maxLecturesPerDay) {
            logStep("   - Skipping {$tName} ({$cName}): Daily limit reached for {$day}.");
            continue;
        }

        // 2. Genuine Teacher Occupancy Check (Fixed)
        if (isTeacherOccupied($tId, $day, $slotId, $overlappingSlots, $state)) {
            logStep("   - Skipping {$tName} ({$cName}): Teacher occupied elsewhere at {$slotLabel} on {$day}.");
            continue;
        }

        // 3. Teacher Database Availability Matrix Check
        if (isset($teacherAvail[$tId][$day][$slotId]) && !$teacherAvail[$tId][$day][$slotId]) {
            logStep("   - Skipping {$tName} ({$cName}): Teacher marked unavailable in database for {$slotLabel} on {$day}.");
            continue;
        }

        // Candidate passed all checks cleanly
        logStep("   -> Selected {$tName} for {$cName} (Workload ID: {$lec['workload_id']}) for {$day} at {$slotLabel}.");

        return [
            'index'    => $i,
            'workload' => $lec
        ];
    }

    return null;
}

// -------------------------------------------------------------------------
// WORKLOAD & ALLOCATION PIPELINE
// -------------------------------------------------------------------------

function selectClassroom($dId, $day, $slotId, $studentCount, $lectureHalls, $overlappingSlots, $state) {
    $preferredDayRoom = $state['divisionDayRoom'][$dId][$day] ?? null;

    if ($preferredDayRoom && !isRoomOccupied($preferredDayRoom, $day, $slotId, $overlappingSlots, $state)) {
        return $preferredDayRoom;
    }

    foreach ($lectureHalls as $hall) {
        if (($hall['CAPACITY'] ?? 0) >= $studentCount &&
            !isRoomOccupied($hall['CLASSROOM_ID'], $day, $slotId, $overlappingSlots, $state)) {
            return $hall['CLASSROOM_ID'];
        }
    }

    return null;
}

function allocateCoursesBalanced($divisions, $courses, $lectureSlots, $weekdays, $lectureHalls, $teacherAvail, $overlappingSlots, &$state, $timeslots = [], $teachersMap = [], $coursesMap = [], $debug = false) {
    $unassignedWorkload = [];
    $dailyQuota = [];

    foreach ($divisions as $dId => $divData) {
        $unassignedWorkload[$dId] = $divData['workload'] ?? [];
        $totalLectures = count($unassignedWorkload[$dId]);
        $dailyQuota[$dId] = ($totalLectures > 0) ? (int)ceil($totalLectures / count($weekdays)) : 0;
    }

    // GAP-FIRST SLOT ORDERING FUNCTION
    $getOrderedContiguousSlots = function($freeSlots, $dId, $day, $state) {
        if (empty($freeSlots)) return [];

        sort($freeSlots, SORT_NUMERIC);

        $assignedLectureSlots = [];
        foreach ($state['assigned'] as $alloc) {
            if ($alloc['DIVISION_ID'] == $dId && $alloc['DAY'] === $day && ($alloc['TYPE'] ?? '') === 'LECTURE') {
                $assignedLectureSlots[] = (int)$alloc['SLOT_ID'];
            }
        }

        if (empty($assignedLectureSlots)) {
            return $freeSlots;
        }

        sort($assignedLectureSlots, SORT_NUMERIC);
        $minLectureSlot = min($assignedLectureSlots);
        $maxLectureSlot = max($assignedLectureSlots);

        $gaps     = [];
        $nextSlot = [];
        $prevSlot = [];
        $others   = [];

        foreach ($freeSlots as $sId) {
            $sId = (int)$sId;
            if ($sId > $minLectureSlot && $sId < $maxLectureSlot) {
                $gaps[] = $sId; // Force internal gaps to be filled FIRST
            } elseif ($sId === ($maxLectureSlot + 1)) {
                $nextSlot[] = $sId;
            } elseif ($sId === ($minLectureSlot - 1)) {
                $prevSlot[] = $sId;
            } else {
                $others[] = $sId;
            }
        }

        return array_merge($gaps, $nextSlot, $prevSlot, $others);
    };

    // PASS 1: Quota-Based Balanced Allocation
    foreach ($weekdays as $day) {
        foreach ($divisions as $dId => $divData) {
            $divName = $divData['DIVISION_NAME'] ?? "Division ID {$dId}";
            $pendingWorkload = getPendingWorkloadForDivision($unassignedWorkload, $dId);
            if (empty($pendingWorkload)) continue;

            $targetForToday = $dailyQuota[$dId];

            logStep("\n[PASS 1] Evaluating {$divName} on {$day} (Target: {$targetForToday} lectures):");

            $freeSlots = getDivisionAvailableSlots($dId, $day, $lectureSlots, $overlappingSlots, $state, $timeslots, $divisions);
            $orderedSlots = $getOrderedContiguousSlots($freeSlots, $dId, $day, $state);

            foreach ($orderedSlots as $slotId) {
                $pendingWorkload = getPendingWorkloadForDivision($unassignedWorkload, $dId);
                if (empty($pendingWorkload)) break;
                if (($state['divisionDailyLectures'][$dId][$day] ?? 0) >= $targetForToday) break;

                $slotLabel = isset($timeslots[$slotId]) ? "{$timeslots[$slotId]['START_TIME']}-{$timeslots[$slotId]['END_TIME']}" : "Slot {$slotId}";
                logStep(" - Checking slot {$slotLabel} for {$divName}...");

                $candidate = getAvailableTeacherFromWorkload($pendingWorkload, $dId, $day, $slotId, $teacherAvail, $overlappingSlots, $state, $teachersMap, $coursesMap, $timeslots, 1);

                if ($candidate !== null) {
                    $i   = $candidate['index'];
                    $lec = $candidate['workload'];
                    $tId = $lec['teacher_id'];
                    $cId = $lec['course_id'];

                    $tName = $teachersMap[$tId] ?? "Teacher ID {$tId}";
                    $cName = $coursesMap[$cId] ?? "Course ID {$cId}";

                    $selectedRoom = selectClassroom($dId, $day, $slotId, $lec['student_count'] ?? 60, $lectureHalls, $overlappingSlots, $state);

                    if ($selectedRoom) {
                        markStateOccupied($state, $tId, $dId, $selectedRoom, $day, $slotId, $overlappingSlots);
                        $state['divisionDayRoom'][$dId][$day]       = $selectedRoom;
                        $state['courseDayCount'][$dId][$cId][$day]  = ($state['courseDayCount'][$dId][$cId][$day] ?? 0) + 1;
                        $state['divisionDailyLectures'][$dId][$day] = ($state['divisionDailyLectures'][$dId][$day] ?? 0) + 1;

                        $state['assigned'][] = [
                            'DAY'         => $day,
                            'SLOT_ID'     => $slotId,
                            'DIVISION_ID' => $dId,
                            'COURSE_ID'   => $cId,
                            'TYPE'        => 'LECTURE',
                            'TEACHER_ID'  => $tId,
                            'ROOM_ID'     => $selectedRoom
                        ];

                        logStep("   ==> ALLOCATED: Workload {$lec['workload_id']} ({$cName}) - {$tName} to {$divName} on {$day} at slot {$slotLabel} [Room ID: {$selectedRoom}].");

                        array_splice($unassignedWorkload[$dId], $i, 1);

                        // Re-fetch and re-order slots immediately
                        $freeSlots = getDivisionAvailableSlots($dId, $day, $lectureSlots, $overlappingSlots, $state, $timeslots, $divisions);
                        $orderedSlots = $getOrderedContiguousSlots($freeSlots, $dId, $day, $state);
                    } else {
                        logStep("   - Room assignment failed for {$cName} at slot {$slotLabel}.");
                    }
                } else {
                    logStep("   - No available teacher found for {$divName} at slot {$slotLabel}.");
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // FALLBACK / OVERFLOW PASS: Prioritize Days with Minimum Allocated Hours
    // -------------------------------------------------------------------------
    foreach ($divisions as $dId => $divData) {
        $pendingWorkload = getPendingWorkloadForDivision($unassignedWorkload, $dId);
        if (empty($pendingWorkload)) continue;

        $divName = $divData['DIVISION_NAME'] ?? "Division ID {$dId}";
        logStep("\n[FALLBACK PASS] Resolving " . count($pendingWorkload) . " unallocated workloads for {$divName}...");

        // Step 1: Get weekdays sorted dynamically by MINIMUM total allocated hours
        $sortedWeekdays = $weekdays;
        usort($sortedWeekdays, function($dayA, $dayB) use ($dId, $state) {
            $hoursA = getDivisionTotalAllocatedHours($dId, $dayA, $state);
            $hoursB = getDivisionTotalAllocatedHours($dId, $dayB, $state);
            return $hoursA <=> $hoursB; // Ascending order (least occupied day first)
        });

        // Step 2: Iterate through days starting with the least allocated hours
        foreach ($sortedWeekdays as $day) {
            $pendingWorkload = getPendingWorkloadForDivision($unassignedWorkload, $dId);
            if (empty($pendingWorkload)) break;

            $currentHours = getDivisionTotalAllocatedHours($dId, $day, $state);
            logStep(" - Evaluating {$day} (Current Workload: {$currentHours} hrs) for {$divName}...");

            $freeSlots = getDivisionAvailableSlots($dId, $day, $lectureSlots, $overlappingSlots, $state, $timeslots, $divisions);
            $orderedSlots = $getOrderedContiguousSlots($freeSlots, $dId, $day, $state);

            foreach ($orderedSlots as $slotId) {
                $pendingWorkload = getPendingWorkloadForDivision($unassignedWorkload, $dId);
                if (empty($pendingWorkload)) break;

                $slotLabel = isset($timeslots[$slotId]) ? "{$timeslots[$slotId]['START_TIME']}-{$timeslots[$slotId]['END_TIME']}" : "Slot {$slotId}";

                // Evaluate available candidate teacher for the slot
                $candidate = getAvailableTeacherFromWorkload($pendingWorkload, $dId, $day, $slotId, $teacherAvail, $overlappingSlots, $state, $teachersMap, $coursesMap, $timeslots, 1);

                if ($candidate !== null) {
                    $i   = $candidate['index'];
                    $lec = $candidate['workload'];
                    $tId = $lec['teacher_id'];
                    $cId = $lec['course_id'];

                    $tName = $teachersMap[$tId] ?? "Teacher ID {$tId}";
                    $cName = $coursesMap[$cId] ?? "Course ID {$cId}";

                    $selectedRoom = selectClassroom($dId, $day, $slotId, $lec['student_count'] ?? 60, $lectureHalls, $overlappingSlots, $state);

                    if ($selectedRoom) {
                        markStateOccupied($state, $tId, $dId, $selectedRoom, $day, $slotId, $overlappingSlots);
                        $state['divisionDayRoom'][$dId][$day]       = $selectedRoom;
                        $state['courseDayCount'][$dId][$cId][$day]  = ($state['courseDayCount'][$dId][$cId][$day] ?? 0) + 1;
                        $state['divisionDailyLectures'][$dId][$day] = ($state['divisionDailyLectures'][$dId][$day] ?? 0) + 1;

                        $state['assigned'][] = [
                            'DAY'         => $day,
                            'SLOT_ID'     => $slotId,
                            'DIVISION_ID' => $dId,
                            'COURSE_ID'   => $cId,
                            'TYPE'        => 'LECTURE',
                            'TEACHER_ID'  => $tId,
                            'ROOM_ID'     => $selectedRoom
                        ];

                        logStep("   ==> FALLBACK ALLOCATED: Workload {$lec['workload_id']} ({$cName}) - {$tName} to {$divName} on {$day} at slot {$slotLabel} [Total Day Workload: " . ($currentHours + 1) . " hrs].");

                        // Remove allocated workload unit
                        array_splice($unassignedWorkload[$dId], $i, 1);

                        // Re-sort weekdays dynamically after booking an hour to maintain accurate hour ordering
                        usort($sortedWeekdays, function($dayA, $dayB) use ($dId, $state) {
                            return getDivisionTotalAllocatedHours($dId, $dayA, $state) <=> getDivisionTotalAllocatedHours($dId, $dayB, $state);
                        });
                    }
                }
            }
        }
    }
}

// -------------------------------------------------------------------------
// DATABASE & LOOKUP HELPERS
// -------------------------------------------------------------------------

function getTimeslots($conn) {
    $res = $conn->query("SELECT * FROM TIMESLOT WHERE SLOT_TYPE != 'BREAK' ORDER BY START_TIME, END_TIME");
    $slots = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $slots[$row['SLOT_ID']] = $row;
        }
    }
    return $slots;
}

function getOverlappingSlotMap($timeslots) {
    $map = [];
    foreach ($timeslots as $s1_id => $s1) {
        $map[$s1_id] = [];
        $t1_s = strtotime($s1['START_TIME']);
        $t1_e = strtotime($s1['END_TIME']);
        foreach ($timeslots as $s2_id => $s2) {
            $t2_s = strtotime($s2['START_TIME']);
            $t2_e = strtotime($s2['END_TIME']);
            if ($t1_s < $t2_e && $t1_e > $t2_s) {
                $map[$s1_id][] = (int)$s2_id;
            }
        }
    }
    return $map;
}

function getClassrooms($conn) {
    $res = $conn->query("SELECT * FROM CLASSROOM WHERE CAPACITY IS NOT NULL ORDER BY CAPACITY ASC");
    $rooms = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rooms[$row['CLASSROOM_ID']] = $row;
        }
    }
    return $rooms;
}

function getDivisions($conn) {
    $res = $conn->query("SELECT * FROM DIVISION WHERE STUDENT_COUNT > 0");
    $divs = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $divs[$row['DIVISION_ID']] = $row;
        }
    }
    return $divs;
}

function getTeacherAvailability($conn) {
    $res = $conn->query("SELECT TEACHER_ID, SLOT_ID, WEEKDAY FROM AVAILABILITY WHERE STATUS = 'AVAILABLE'");
    $avail = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $avail[$row['TEACHER_ID']][$row['WEEKDAY']][$row['SLOT_ID']] = true;
        }
    }
    return $avail;
}

function getTeachersMap($conn) {
    $res = $conn->query("SELECT TEACHER_ID, FIRST_NAME FROM TEACHER");
    $map = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row['TEACHER_ID']] = $row['FIRST_NAME'];
        }
    }
    return $map;
}

function getCoursesMap($conn) {
    $res = $conn->query("SELECT COURSE_ID, SHORT_NAME FROM COURSE");
    $map = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row['COURSE_ID']] = $row['SHORT_NAME'];
        }
    }
    return $map;
}

function getWorkload($conn, $semester) {
    $semesterEscaped = $conn->real_escape_string($semester);
    $sql = "
        SELECT 
            t.WORKLOAD_ID, 
            t.TEACHER_ID, 
            t.COURSE_ID, 
            t.DIVISION_ID, 
            t.LECTURE_COUNT,
            c.ISPRACTICAL, 
            c.TYPE AS COURSE_TYPE, 
            c.ISOPTIONAL, 
            c.OPTIONAL_ID, 
            c.YEAR_NUMBER, 
            c.PROGRAMME_ID,
            d.STUDENT_COUNT, 
            d.CLASSROOM_ID AS PREFERRED_ROOM_ID, 
            d.START_TIME_ID AS PREF_START_SLOT,
            d.END_TIME_ID AS PREF_END_SLOT
        FROM TEACHES t
        JOIN COURSE c ON t.COURSE_ID = c.COURSE_ID
        JOIN DIVISION d ON t.DIVISION_ID = d.DIVISION_ID
        WHERE c.SEMESTER = '$semesterEscaped' AND t.LECTURE_COUNT > 0
    ";

    $result = $conn->query($sql);
    $workloads = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $workloads[] = [
                'workload_id'   => (int)$row['WORKLOAD_ID'],
                'teacher_id'    => (int)$row['TEACHER_ID'],
                'course_id'     => (int)$row['COURSE_ID'],
                'division_id'   => (int)$row['DIVISION_ID'],
                'lecture_count' => (int)$row['LECTURE_COUNT'],
                'is_practical'  => (bool)$row['ISPRACTICAL'],
                'course_type'   => $row['COURSE_TYPE'],
                'is_optional'   => (bool)$row['ISOPTIONAL'],
                'optional_id'   => $row['OPTIONAL_ID'] ? (int)$row['OPTIONAL_ID'] : null,
                'year_number'   => (int)$row['YEAR_NUMBER'],
                'programme_id'  => (int)$row['PROGRAMME_ID'],
                'student_count' => (int)$row['STUDENT_COUNT'],
                'pref_room_id'  => $row['PREFERRED_ROOM_ID'] ? (int)$row['PREFERRED_ROOM_ID'] : null,
                'pref_start'    => $row['PREF_START_SLOT'] ? (int)$row['PREF_START_SLOT'] : null,
                'pref_end'      => $row['PREF_END_SLOT'] ? (int)$row['PREF_END_SLOT'] : null
            ];
        }
    }

    return $workloads;
}

function getDivisionTotalAllocatedHours($dId, $day, $state) {
    $totalHours = 0;
    foreach ($state['assigned'] as $alloc) {
        if ((int)$alloc['DIVISION_ID'] === (int)$dId && $alloc['DAY'] === $day) {
            // Practicals count as 2 hours, Lectures as 1 hour
            $totalHours += (($alloc['TYPE'] ?? '') === 'PRACTICAL') ? 2 : 1;
        }
    }
    return $totalHours;
}

function expandWorkload($conn, $rawWorkloads, $semester) {
    $expandedLectures = [];
    $expandedPracticals = [];
    $allUnits = [];

    $divRes = $conn->query("SELECT DIVISION_ID, YEAR_NUMBER, PROGRAMME_ID, STUDENT_COUNT FROM DIVISION WHERE STUDENT_COUNT > 0");
    $yearProgrammeDivisions = [];
    if ($divRes) {
        while ($d = $divRes->fetch_assoc()) {
            $y = (int)$d['YEAR_NUMBER'];
            $p = (int)$d['PROGRAMME_ID'];
            $yearProgrammeDivisions[$y][$p][] = [
                'division_id'   => (int)$d['DIVISION_ID'],
                'student_count' => (int)$d['STUDENT_COUNT']
            ];
        }
    }

    foreach ($rawWorkloads as $wl) {
        $isPractical = $wl['is_practical'];
        $unitsCount  = (int)$wl['lecture_count'];

        $targetDivisions = [];
        if (!$wl['is_optional'] && isset($yearProgrammeDivisions[$wl['year_number']][$wl['programme_id']])) {
            $targetDivisions = $yearProgrammeDivisions[$wl['year_number']][$wl['programme_id']];
        } else {
            $targetDivisions[] = [
                'division_id'   => $wl['division_id'],
                'student_count' => $wl['student_count']
            ];
        }

        foreach ($targetDivisions as $target) {
            for ($i = 0; $i < $unitsCount; $i++) {
                $unit = [
                    'instance_id'   => $wl['workload_id'] . '_D' . $target['division_id'] . '_' . $i,
                    'workload_id'   => $wl['workload_id'],
                    'course_id'     => $wl['course_id'],
                    'division_id'   => $target['division_id'],
                    'teacher_id'    => $wl['teacher_id'],
                    'is_practical'  => $isPractical,
                    'course_type'   => $wl['course_type'],
                    'is_optional'   => $wl['is_optional'],
                    'optional_id'   => $wl['optional_id'],
                    'student_count' => $target['student_count'],
                    'pref_room_id'  => $wl['pref_room_id'],
                    'pref_start'    => $wl['pref_start'],
                    'pref_end'      => $wl['pref_end'],
                    'required_hrs'  => $isPractical ? 2 : 1
                ];

                $allUnits[] = $unit;
                if ($isPractical) {
                    $expandedPracticals[] = $unit;
                } else {
                    $expandedLectures[] = $unit;
                }
            }
        }
    }

    return [
        'all'        => $allUnits,
        'lectures'   => $expandedLectures,
        'practicals' => $expandedPracticals
    ];
}

function fixPracticalSlot($timeslots, $divisions, $practicals, $classrooms) {
    $practicalSlots = array_filter($timeslots, fn($s) => ($s['SLOT_TYPE'] ?? '') === 'PRACTICAL');

    $slotLabMatrix = [];
    foreach ($practicalSlots as $slotId => $slot) {
        foreach ($classrooms as $roomId => $room) {
            if (($room['CATEGORY'] ?? '') === 'LECTURE HALL') continue;

            $slotLabMatrix[] = [
                'slot_id'   => (int)$slotId,
                'room_id'   => (int)$roomId,
                'category'  => $room['CATEGORY'],
                'capacity'  => (int)$room['CAPACITY'],
                'start_time'=> strtotime($slot['START_TIME']),
                'end_time'  => strtotime($slot['END_TIME']),
                'is_alloted'=> false
            ];
        }
    }

    $fixedDivisionPracticalSlots = [];

    foreach ($divisions as $dId => $div) {
        $divPracticals = array_filter($practicals, fn($p) => (int)$p['division_id'] === (int)$dId);
        if (empty($divPracticals)) continue;

        $firstPractical = reset($divPracticals);
        $courseType     = $firstPractical['course_type'] ?? '';

        $matchingType = array_filter($slotLabMatrix, function($item) use ($courseType) {
            if ($item['is_alloted']) return false;
            switch ($courseType) {
                case 'IT PRACTICAL':        return $item['category'] === 'IT LAB';
                case 'PHYSICS PRACTICAL':   return $item['category'] === 'PHYSICS LAB';
                case 'CHEMISTRY PRACTICAL': return $item['category'] === 'CHEMISTRY LAB';
                case 'BIOLOGY PRACTICAL':   return $item['category'] === 'BIOLOGY LAB';
                default:                    return $item['category'] !== 'LECTURE HALL';
            }
        });

        $matchingCapacity = array_filter($matchingType, fn($item) => $item['capacity'] >= ($div['STUDENT_COUNT'] ?? 0));

        $prefStartSlotId = $div['START_TIME_ID'] ?? null;
        $prefEndSlotId   = $div['END_TIME_ID'] ?? null;

        $prefStartTime = ($prefStartSlotId && isset($timeslots[$prefStartSlotId])) 
            ? strtotime($timeslots[$prefStartSlotId]['START_TIME']) : null;
        $prefEndTime   = ($prefEndSlotId && isset($timeslots[$prefEndSlotId])) 
            ? strtotime($timeslots[$prefEndSlotId]['END_TIME']) : null;

        $matchingTime = array_filter($matchingCapacity, function($item) use ($prefStartTime, $prefEndTime) {
            $afterStart = ($prefStartTime === null) || ($item['start_time'] >= $prefStartTime);
            $beforeEnd  = ($prefEndTime === null)   || ($item['end_time'] <= $prefEndTime);
            return $afterStart && $beforeEnd;
        });

        $candidates = !empty($matchingTime) ? $matchingTime : $matchingCapacity;
        if (empty($candidates)) $candidates = $matchingType;
        if (empty($candidates)) continue;

        usort($candidates, fn($a, $b) => $a['capacity'] <=> $b['capacity']);
        $selected = reset($candidates);

        foreach ($slotLabMatrix as &$item) {
            if ($item['slot_id'] === $selected['slot_id'] && $item['room_id'] === $selected['room_id']) {
                $item['is_alloted'] = true;
                break;
            }
        }
        unset($item);

        $fixedDivisionPracticalSlots[$dId] = [
            'slot_id' => $selected['slot_id'],
            'room_id' => $selected['room_id']
        ];
    }

    return $fixedDivisionPracticalSlots;
}

function allocatePracticals($practicals, $divisionPracticalSlots, $classrooms, $teacherAvail, $overlappingSlots, $state, $teachersMap = [], $coursesMap = [], $timeslots = []) {
    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

    foreach ($practicals as $p) {
        $dId = $p['division_id'];
        $tName = $teachersMap[$p['teacher_id']] ?? "Teacher ID {$p['teacher_id']}";
        $cName = $coursesMap[$p['course_id']] ?? "Course ID {$p['course_id']}";

        $fixedConfig = $divisionPracticalSlots[$dId] ?? null;
        if (!$fixedConfig) {
            logStep(" [Practical Error] No fixed lab room/slot found for Division ID {$dId}.");
            $state['unallocated'][] = [
                'unit'   => $p,
                'reason' => "No fixed practical slot/room available for Division ID {$dId}."
            ];
            continue;
        }

        $slotId = $fixedConfig['slot_id'];
        $roomId = $fixedConfig['room_id'];
        $slotLabel = isset($timeslots[$slotId]) ? "{$timeslots[$slotId]['START_TIME']}-{$timeslots[$slotId]['END_TIME']}" : "Slot {$slotId}";
        $allocated = false;

        foreach ($weekdays as $day) {
            if (empty($teacherAvail[$p['teacher_id']][$day][$slotId])) continue;

            if (isSlotOccupiedByDivision($dId, $day, $slotId, $overlappingSlots, $state)) continue;
            if (isTeacherOccupied($p['teacher_id'], $day, $slotId, $overlappingSlots, $state)) continue;
            if (isRoomOccupied($roomId, $day, $slotId, $overlappingSlots, $state)) continue;

            markStateOccupied($state, $p['teacher_id'], $dId, $roomId, $day, $slotId, $overlappingSlots);

            $state['assigned'][] = [
                'DAY'         => $day,
                'SLOT_ID'     => $slotId,
                'DIVISION_ID' => $dId,
                'COURSE_ID'   => $p['course_id'],
                'TYPE'        => 'PRACTICAL',
                'TEACHER_ID'  => $p['teacher_id'],
                'ROOM_ID'     => $roomId
            ];

            logStep(" ==> PRACTICAL ALLOCATED: Workload {$p['workload_id']} ({$cName}) - {$tName} to Div {$dId} on {$day} at slot {$slotLabel} [Room ID: {$roomId}].");

            $allocated = true;
            break;
        }

        if (!$allocated) {
            logStep(" [Practical Conflict] Could not allocate {$cName} ({$tName}) for Div {$dId} in fixed slot {$slotLabel}.");
            $state['unallocated'][] = [
                'unit'   => $p,
                'reason' => "Teacher conflict or division busy in fixed practical slot (Slot ID: {$slotId})."
            ];
        }
    }

    return $state;
}

function runFinalValidation($allUnits, $state, $divisions, $timeslots) {
    $totalRequired  = count($allUnits);
    $totalAllocated = count($state['assigned']);
    $unallocated    = count($state['unallocated']);

    $teacherConflicts  = 0;
    $divisionConflicts = 0;
    $roomConflicts     = 0;
    $seenAuditMap      = [];

    foreach ($state['assigned'] as $alloc) {
        $key = $alloc['DAY'] . '_' . $alloc['SLOT_ID'];

        $tKey = "T_" . $alloc['TEACHER_ID'] . '_' . $key;
        if (isset($seenAuditMap[$tKey])) $teacherConflicts++;
        $seenAuditMap[$tKey] = true;

        $dKey = "D_" . $alloc['DIVISION_ID'] . '_' . $key;
        if (isset($seenAuditMap[$dKey])) $divisionConflicts++;
        $seenAuditMap[$dKey] = true;

        $rKey = "R_" . $alloc['ROOM_ID'] . '_' . $key;
        if (isset($seenAuditMap[$rKey])) $roomConflicts++;
        $seenAuditMap[$rKey] = true;
    }

    return [
        'Required_Workloads'               => $totalRequired,
        'Allocated_Workloads'              => $totalAllocated,
        'Unallocated_Workloads'            => $unallocated,
        'Teacher_Conflicts'                => $teacherConflicts,
        'Division_Conflicts'               => $divisionConflicts,
        'Classroom_Conflicts'              => $roomConflicts,
        'Lab_Conflicts'                    => 0,
        'Course_Frequency_Violations'      => $unallocated,
        'Teacher_Workload_Violations'      => $unallocated,
        'Compulsory_Course_Violations'     => 0,
        'Optional_Course_Conflicts'        => 0,
        'Practical_Slot_Violations'        => 0,
        'Broken_Division_Time_Preferences' => array_sum($state['brokenPreferences'] ?? []),
        'Unallocated_Details'              => $state['unallocated']
    ];
}