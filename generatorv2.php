<?php
/**
 * generatorv2.php
 * Algorithmic Timetable Generator - B.Sc. IT & General Programmes
 * Pipeline: Data Gathering -> Practical Cartesian Allocation -> Constraint Propagation -> Theory Allocation -> Room Mapping
 */

// 1. MAIN PIPELINE EXECUTION
function generateTimetable($semester, $academicYear) {
    global $conn;
    
    // Step 1: Gather all required state data[cite: 2, 3]
    $state = gatherData($conn, $semester);
    
    // Step 2: Prepare & Sort entities
    $state = prepareForAllotment($state);
    
    // Step 3 & 4: Fix and Allocate Practical Slots
    $state = allocatePracticals($state);
    
    // Step 5 & 6: Preprocess Theory Slots and Classrooms
    $state = preprocessTheorySlots($state);
    
    // Step 7 & 8: Allocate Theory (including Fallback)
    $state = allocateTheory($state);
    
    // Step 9: Allocate Classrooms for Theory
    $timetable = allocateClassrooms($state);
    
    // Final verification tracking
    $validation = [
        'pending_workloads' => array_filter($state['workloads'], fn($w) => $w['PENDING_LECTURES'] > 0),
        'unassigned_slots' => [] 
    ];

    return ['timetable' => $timetable, 'validation' => $validation];
}

// 2. DATA GATHERING
function gatherData_Dep($conn, $semester) {
    $data = [
        'teachers' => [], 'classrooms' => [], 'courses' => [], 
        'workloads' => [], 'divisions' => [], 'timeslots' => []
    ];

    // Timeslots[cite: 2]
    $res = $conn->query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME");
    while ($row = $res->fetch_assoc()) $data['timeslots'][$row['SLOT_ID']] = $row;

    // Classrooms[cite: 2]
    $res = $conn->query("SELECT CLASSROOM_ID, CAPACITY, CATEGORY FROM CLASSROOM ORDER BY CAPACITY DESC");
    while ($row = $res->fetch_assoc()) $data['classrooms'][$row['CLASSROOM_ID']] = $row;

    // Divisions[cite: 2]
    $res = $conn->query("SELECT DIVISION_ID, STUDENT_COUNT, START_TIME_ID, END_TIME_ID FROM DIVISION");
    while ($row = $res->fetch_assoc()) {
        $row['ALLOCATED_PRACTICALS'] = [];
        $data['divisions'][$row['DIVISION_ID']] = $row;
    }

    // Courses[cite: 2]
    $stmt = $conn->prepare("SELECT COURSE_ID, WEEKLY_LECTURES, ISOPTIONAL, ISPRACTICAL, OPTIONAL_ID FROM COURSE WHERE SEMESTER = ?");
    $stmt->bind_param("s", $semester);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $data['courses'][$row['COURSE_ID']] = $row;

    // Workload[cite: 2]
    $res = $conn->query("
        SELECT t.WORKLOAD_ID, t.TEACHER_ID, t.COURSE_ID, t.DIVISION_ID, t.LECTURE_COUNT 
        FROM TEACHES t
        JOIN COURSE c ON t.COURSE_ID = c.COURSE_ID
        WHERE c.SEMESTER = '$semester'
    ");
    while ($row = $res->fetch_assoc()) {
        $row['PENDING_LECTURES'] = (int)$row['LECTURE_COUNT'];
        $data['workloads'][$row['WORKLOAD_ID']] = $row;
    }

    // Teachers & Availability Matrix[cite: 2, 3]
    $res = $conn->query("SELECT TEACHER_ID FROM TEACHER");
    while ($row = $res->fetch_assoc()) {
        $tId = $row['TEACHER_ID'];
        $data['teachers'][$tId] = ['TEACHER_ID' => $tId, 'TOTAL_AVAILABILITY' => 0, 'MATRIX' => []];
    }
    
    $res = $conn->query("SELECT TEACHER_ID, SLOT_ID, WEEKDAY FROM AVAILABILITY WHERE STATUS = 'AVAILABLE'");
    while ($row = $res->fetch_assoc()) {
        $tId = $row['TEACHER_ID'];
        $data['teachers'][$tId]['MATRIX'][$row['WEEKDAY']][$row['SLOT_ID']] = true;
        $data['teachers'][$tId]['TOTAL_AVAILABILITY']++;
    }

    return $data;
}
// 2. DATA GATHERING
function gatherData($conn, $semester) {
    $data = [
        'teachers' => [], 'classrooms' => [], 'courses' => [], 
        'workloads' => [], 'divisions' => [], 'timeslots' => []
    ];

    // Timeslots
    $res = $conn->query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME");
    while ($row = $res->fetch_assoc()) $data['timeslots'][$row['SLOT_ID']] = $row;

    // Classrooms
    $res = $conn->query("SELECT CLASSROOM_ID, CAPACITY, CATEGORY FROM CLASSROOM ORDER BY CAPACITY DESC");
    while ($row = $res->fetch_assoc()) $data['classrooms'][$row['CLASSROOM_ID']] = $row;

    // Divisions
    $res = $conn->query("SELECT DIVISION_ID, STUDENT_COUNT, START_TIME_ID, END_TIME_ID FROM DIVISION");
    while ($row = $res->fetch_assoc()) {
        $row['ALLOCATED_PRACTICALS'] = [];
        $data['divisions'][$row['DIVISION_ID']] = $row;
    }

    // Courses
    $stmt = $conn->prepare("SELECT COURSE_ID, WEEKLY_LECTURES, ISOPTIONAL, ISPRACTICAL, OPTIONAL_ID FROM COURSE WHERE SEMESTER = ?");
    $stmt->bind_param("s", $semester);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $data['courses'][$row['COURSE_ID']] = $row;

    // MINIMAL CHANGE 1: Fetch opted_by mappings for optional courses
    $optedBy = [];
    $resOpted = $conn->query("SELECT COURSE_ID, DIVISION_ID FROM OPTED_BY");
    while ($row = $resOpted->fetch_assoc()) {
        $optedBy[$row['COURSE_ID']][$row['DIVISION_ID']] = true;
    }

    // Workload
    $res = $conn->query("
        SELECT t.WORKLOAD_ID, t.TEACHER_ID, t.COURSE_ID, t.DIVISION_ID, t.LECTURE_COUNT, c.ISOPTIONAL 
        FROM TEACHES t
        JOIN COURSE c ON t.COURSE_ID = c.COURSE_ID
        WHERE c.SEMESTER = '$semester'
    ");
    while ($row = $res->fetch_assoc()) {
        $cId = $row['COURSE_ID'];
        $dId = $row['DIVISION_ID'];
        
        // MINIMAL CHANGE 2: Skip workload if it's an optional course but NOT mapped to this division in OPTED_BY
        if ($row['ISOPTIONAL'] && !isset($optedBy[$cId][$dId])) {
            continue;
        }

        $row['PENDING_LECTURES'] = (int)$row['LECTURE_COUNT'];
        $data['workloads'][$row['WORKLOAD_ID']] = $row;
    }

    // Teachers & Availability Matrix
    $res = $conn->query("SELECT TEACHER_ID FROM TEACHER");
    while ($row = $res->fetch_assoc()) {
        $tId = $row['TEACHER_ID'];
        $data['teachers'][$tId] = ['TEACHER_ID' => $tId, 'TOTAL_AVAILABILITY' => 0, 'MATRIX' => []];
    }
    
    $res = $conn->query("SELECT TEACHER_ID, SLOT_ID, WEEKDAY FROM AVAILABILITY WHERE STATUS = 'AVAILABLE'");
    while ($row = $res->fetch_assoc()) {
        $tId = $row['TEACHER_ID'];
        $data['teachers'][$tId]['MATRIX'][$row['WEEKDAY']][$row['SLOT_ID']] = true;
        $data['teachers'][$tId]['TOTAL_AVAILABILITY']++;
    }

    return $data;
}

// 3. PREPARATION & SORTING
function prepareForAllotment($state) {
    // Sort teachers by minimum availability hours to prioritize constrained resources
    uasort($state['teachers'], fn($a, $b) => $a['TOTAL_AVAILABILITY'] <=> $b['TOTAL_AVAILABILITY']);

    // Map practical flags to divisions based on their workload
    foreach ($state['workloads'] as $wId => $workload) {
        $courseId = $workload['COURSE_ID'];
        $divId = $workload['DIVISION_ID'];
        if (isset($state['courses'][$courseId]) && $state['courses'][$courseId]['ISPRACTICAL']) {
            $state['divisions'][$divId]['HAS_PRACTICALS'] = true;
        }
    }
    return $state;
}

// 4. FIX AND ALLOCATE PRACTICAL SLOTS
function allocatePracticals_Dep($state) {
    $state['timetable'] = [];
    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
    
    // Extract labs and practical slots
    $labs = array_filter($state['classrooms'], fn($c) => strpos($c['CATEGORY'], 'LAB') !== false);
    $practicalSlots = array_filter($state['timeslots'], fn($s) => $s['SLOT_TYPE'] === 'PRACTICAL');

    // Process divisions marked for practicals
    foreach ($state['divisions'] as $divId => &$division) {
        if (empty($division['HAS_PRACTICALS'])) continue;

        // Cartesian product mapping: grouping by lab type to prevent mixing
        $labMatrices = [];
        foreach ($labs as $lId => $lab) {
            $type = $lab['CATEGORY'];
            foreach ($practicalSlots as $sId => $slot) {
                $labMatrices[$type][] = ['SLOT_ID' => $sId, 'ROOM_ID' => $lId];
            }
        }

        // MINIMAL CHANGE 1: Initialize a variable to lock the practical slot for this division
        $fixedSlotId = null;

        // Allocate workloads tagged as practical
        foreach ($state['workloads'] as $wId => &$workload) {
            if ($workload['DIVISION_ID'] != $divId || $workload['PENDING_LECTURES'] <= 0) continue;
            
            $course = $state['courses'][$workload['COURSE_ID']];
            if (!$course['ISPRACTICAL']) continue;
            
            $tId = $workload['TEACHER_ID'];
            $teacher = &$state['teachers'][$tId];
            
            // Expected practical type (e.g., IT PRACTICAL maps to IT LAB)
            $expectedLabType = str_replace('PRACTICAL', 'LAB', $course['TYPE'] ?? 'IT PRACTICAL');
            $availableCartesianPairs = $labMatrices[$expectedLabType] ?? [];

            while ($workload['PENDING_LECTURES'] > 0) {
                $allocated = false;
                
                foreach ($weekdays as $day) {
                    foreach ($availableCartesianPairs as $pair) {
                        $sId = $pair['SLOT_ID'];
                        
                        // MINIMAL CHANGE 2: If a slot is already fixed for this division, skip all other slots
                        if ($fixedSlotId !== null && $sId !== $fixedSlotId) {
                            continue;
                        }
                        
                        // Check teacher availability and lack of conflicts
                        if (!empty($teacher['MATRIX'][$day][$sId]) && !isset($division['ALLOCATED_PRACTICALS'][$day][$sId])) {
                            
                            // MINIMAL CHANGE 3: Lock the slot ID on the first successful allocation
                            if ($fixedSlotId === null) {
                                $fixedSlotId = $sId;
                            }
                            
                            // Make practical allocation
                            $state['timetable'][] = [
                                'COURSE_ID' => $workload['COURSE_ID'],
                                'DIVISION_ID' => $divId,
                                'ROOM_ID' => $pair['ROOM_ID'],
                                'SLOT_ID' => $sId,
                                'DAY' => $day,
                                'TEACHER_ID' => $tId,
                                'TYPE' => 'PRACTICAL'
                            ];
                            
                            // Consume availability
                            $teacher['MATRIX'][$day][$sId] = false;
                            $division['ALLOCATED_PRACTICALS'][$day][$sId] = true;
                            $workload['PENDING_LECTURES']--;
                            $allocated = true;
                            break 2; // Break out of day and cartesian loop
                        }
                    }
                }
                if (!$allocated) break; // Avoid infinite loop if constraints are impossible
            }
        }
    }
    return $state;
}
// 4. FIX AND ALLOCATE PRACTICAL SLOTS
function allocatePracticals($state) {
    $state['timetable'] = [];
    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
    
    // Extract labs and practical slots
    $labs = array_filter($state['classrooms'], fn($c) => strpos($c['CATEGORY'], 'LAB') !== false);
    $practicalSlots = array_filter($state['timeslots'], fn($s) => $s['SLOT_TYPE'] === 'PRACTICAL');

    // Process divisions marked for practicals
    foreach ($state['divisions'] as $divId => &$division) {
        if (empty($division['HAS_PRACTICALS'])) continue;

        // Cartesian product mapping: grouping by lab type to prevent mixing
        $labMatrices = [];
        foreach ($labs as $lId => $lab) {
            $type = $lab['CATEGORY'];
            foreach ($practicalSlots as $sId => $slot) {
                $labMatrices[$type][] = ['SLOT_ID' => $sId, 'ROOM_ID' => $lId];
            }
        }

        $fixedSlotId = null;

        // Allocate workloads tagged as practical
        foreach ($state['workloads'] as $wId => &$workload) {
            if ($workload['DIVISION_ID'] != $divId || $workload['PENDING_LECTURES'] <= 0) continue;
            
            $course = $state['courses'][$workload['COURSE_ID']];
            if (!$course['ISPRACTICAL']) continue;
            
            $tId = $workload['TEACHER_ID'];
            $teacher = &$state['teachers'][$tId];
            
            // Expected practical type
            $expectedLabType = str_replace('PRACTICAL', 'LAB', $course['TYPE'] ?? 'IT PRACTICAL');
            $availableCartesianPairs = $labMatrices[$expectedLabType] ?? [];

            // SOFT PREFERENCE: If End Time preference exists, reverse array to check latest slots first
            if (!empty($division['END_TIME_ID'])) {
                $availableCartesianPairs = array_reverse($availableCartesianPairs);
            }

            while ($workload['PENDING_LECTURES'] > 0) {
                $allocated = false;
                
                foreach ($weekdays as $day) {
                    foreach ($availableCartesianPairs as $pair) {
                        $sId = $pair['SLOT_ID'];
                        
                        // If a slot is already fixed for this division, skip all other slots
                        if ($fixedSlotId !== null && $sId !== $fixedSlotId) continue;

                        // Check teacher availability and lack of conflicts
                        if (!empty($teacher['MATRIX'][$day][$sId]) && !isset($division['ALLOCATED_PRACTICALS'][$day][$sId])) {
                            
                            // Lock the slot ID on the first successful allocation
                            if ($fixedSlotId === null) {
                                $fixedSlotId = $sId;
                            }
                            
                            // Make practical allocation
                            $state['timetable'][] = [
                                'COURSE_ID' => $workload['COURSE_ID'],
                                'DIVISION_ID' => $divId,
                                'ROOM_ID' => $pair['ROOM_ID'],
                                'SLOT_ID' => $sId,
                                'DAY' => $day,
                                'TEACHER_ID' => $tId,
                                'TYPE' => 'PRACTICAL'
                            ];
                            
                            // Consume availability
                            $teacher['MATRIX'][$day][$sId] = false;
                            $division['ALLOCATED_PRACTICALS'][$day][$sId] = true;
                            $workload['PENDING_LECTURES']--;
                            $allocated = true;
                            break 2; // Break out of day and cartesian loop
                        }
                    }
                }
                if (!$allocated) break; 
            }
        }
    }
    return $state;
}

// 5. PREPROCESS THEORY SLOTS & CONSTRAINT PROPAGATION
function preprocessTheorySlots_Dep($state) {
    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
    $lectureSlots = array_filter($state['timeslots'], fn($s) => $s['SLOT_TYPE'] === 'LECTURE');

    foreach ($state['divisions'] as $divId => &$division) {
        $division['ALLOCATABLE_SLOTS'] = [];
        $division['FALLBACK_SLOTS'] = [];
        
        // Calculate standard quota 
        $divWorkloads = array_filter($state['workloads'], fn($w) => $w['DIVISION_ID'] == $divId && !$state['courses'][$w['COURSE_ID']]['ISPRACTICAL']);
        $totalTheoryHours = array_sum(array_column($divWorkloads, 'PENDING_LECTURES'));
        $dailyQuota = ceil($totalTheoryHours / 6);

        foreach ($weekdays as $day) {
            $dailySlots = [];
            foreach ($lectureSlots as $sId => $slot) {
                // Constraint propagation: Cancel overlapping lecture slots if a 2-hour practical is assigned
                $overlap = false;
                if (!empty($division['ALLOCATED_PRACTICALS'][$day])) {
                    foreach (array_keys($division['ALLOCATED_PRACTICALS'][$day]) as $pracSlotId) {
                        $pSlot = $state['timeslots'][$pracSlotId];
                        // Time overlap check
                        if (strtotime($slot['START_TIME']) >= strtotime($pSlot['START_TIME']) && 
                            strtotime($slot['END_TIME']) <= strtotime($pSlot['END_TIME'])) {
                            $overlap = true;
                            break;
                        }
                    }
                }
                
                if (!$overlap) {
                    $dailySlots[] = $sId;
                }
            }

            // Directional allocation based on preference (START_TIME_ID / END_TIME_ID)
            if ($division['START_TIME_ID']) {
                $division['ALLOCATABLE_SLOTS'][$day] = array_slice($dailySlots, 0, $dailyQuota);
                $division['FALLBACK_SLOTS'][$day] = array_slice($dailySlots, $dailyQuota, 2);
            } else {
                $division['ALLOCATABLE_SLOTS'][$day] = array_slice($dailySlots, -$dailyQuota, $dailyQuota);
                $division['FALLBACK_SLOTS'][$day] = array_slice($dailySlots, -($dailyQuota + 2), 2);
            }
        }
    }
    return $state;
}
// 5. PREPROCESS THEORY SLOTS & CONSTRAINT PROPAGATION
function preprocessTheorySlots($state) {
    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
    $lectureSlots = array_filter($state['timeslots'], fn($s) => $s['SLOT_TYPE'] === 'LECTURE');

    foreach ($state['divisions'] as $divId => &$division) {
        $division['ALLOCATABLE_SLOTS'] = [];
        $division['FALLBACK_SLOTS'] = [];
        
        // Calculate standard quota 
        $divWorkloads = array_filter($state['workloads'], fn($w) => $w['DIVISION_ID'] == $divId && !$state['courses'][$w['COURSE_ID']]['ISPRACTICAL']);
        $totalTheoryHours = array_sum(array_column($divWorkloads, 'PENDING_LECTURES'));
        $dailyQuota = ceil($totalTheoryHours / 6);

        foreach ($weekdays as $day) {
            $dailySlots = [];
            foreach ($lectureSlots as $sId => $slot) {
                // Constraint propagation: Cancel overlapping lecture slots if a 2-hour practical is assigned
                $overlap = false;
                if (!empty($division['ALLOCATED_PRACTICALS'][$day])) {
                    foreach (array_keys($division['ALLOCATED_PRACTICALS'][$day]) as $pracSlotId) {
                        $pSlot = $state['timeslots'][$pracSlotId];
                        // Time overlap check
                        if (strtotime($slot['START_TIME']) >= strtotime($pSlot['START_TIME']) && 
                            strtotime($slot['END_TIME']) <= strtotime($pSlot['END_TIME'])) {
                            $overlap = true;
                            break;
                        }
                    }
                }
                
                if (!$overlap) {
                    $dailySlots[] = $sId;
                }
            }

            // Safe mathematical slicing to prevent dropping slots
            $slotCount = count($dailySlots);
            $quota = min($dailyQuota, $slotCount);

            // SOFT PREFERENCE: We push preferred slots to the FRONT (ALLOCATABLE) and the rest to FALLBACK.
            if (!empty($division['START_TIME_ID'])) {
                $division['ALLOCATABLE_SLOTS'][$day] = array_slice($dailySlots, 0, $quota);
                // Push ALL remaining valid slots into fallback so we never starve the division
                $division['FALLBACK_SLOTS'][$day] = array_slice($dailySlots, $quota); 
            } else {
                // End time preference or no preference (shift to end)
                $division['ALLOCATABLE_SLOTS'][$day] = array_slice($dailySlots, -$quota, $quota);
                $division['FALLBACK_SLOTS'][$day] = array_slice($dailySlots, 0, $slotCount - $quota);
            }
        }
    }
    return $state;
}

// 7 & 8. ALLOCATE THEORY WITH RANDOMIZATION & FALLBACK
function allocateTheory($state) {
    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
    
    // Two passes: standard matrix, then fallback matrix
    $passes = ['ALLOCATABLE_SLOTS', 'FALLBACK_SLOTS'];

    foreach ($passes as $passKey) {
        foreach ($state['divisions'] as $divId => &$division) {
            $pendingDivWorkloads = array_filter($state['workloads'], fn($w) => $w['DIVISION_ID'] == $divId && $w['PENDING_LECTURES'] > 0 && !$state['courses'][$w['COURSE_ID']]['ISPRACTICAL']);
            
            if (empty($pendingDivWorkloads)) continue;

            foreach ($weekdays as $day) {
                if (empty($division[$passKey][$day])) continue;

                foreach ($division[$passKey][$day] as $sId) {
                    // Refresh pending list
                    $pendingList = array_filter($state['workloads'], fn($w) => $w['DIVISION_ID'] == $divId && $w['PENDING_LECTURES'] > 0 && !$state['courses'][$w['COURSE_ID']]['ISPRACTICAL']);
                    if (empty($pendingList)) break 2;

                    // Filter available teachers for this specific slot
                    $availableWorkloads = array_filter($pendingList, function($w) use ($state, $day, $sId) {
                        return !empty($state['teachers'][$w['TEACHER_ID']]['MATRIX'][$day][$sId]);
                    });

                    if (!empty($availableWorkloads)) {
                        // Random selection to prevent clumping
                        $selectedWorkloadKey = array_rand($availableWorkloads);
                        $selectedWorkload = &$state['workloads'][$selectedWorkloadKey];
                        $tId = $selectedWorkload['TEACHER_ID'];

                        // Commit allocation
                        $state['timetable'][] = [
                            'COURSE_ID' => $selectedWorkload['COURSE_ID'],
                            'DIVISION_ID' => $divId,
                            'ROOM_ID' => null, // Resolved in step 9
                            'SLOT_ID' => $sId,
                            'DAY' => $day,
                            'TEACHER_ID' => $tId,
                            'TYPE' => 'LECTURE'
                        ];

                        // Deduct matrices
                        $state['teachers'][$tId]['MATRIX'][$day][$sId] = false;
                        $selectedWorkload['PENDING_LECTURES']--;
                    }
                }
            }
        }
    }
    return $state;
}

// 9. ASSIGN CLASSROOMS FOR THEORY
function allocateClassrooms($state) {
    $timetable = $state['timetable'];
    $classrooms = array_filter($state['classrooms'], fn($c) => strpos($c['CATEGORY'], 'LECTURE') !== false);
    
    // Matrix to track room state: [DAY][SLOT_ID][ROOM_ID] = true (occupied)
    $roomOccupation = [];

    foreach ($timetable as &$entry) {
        // Practicals already have rooms assigned
        if ($entry['ROOM_ID'] !== null) {
            $roomOccupation[$entry['DAY']][$entry['SLOT_ID']][$entry['ROOM_ID']] = true;
            continue;
        }

        $divId = $entry['DIVISION_ID'];
        $studentCount = $state['divisions'][$divId]['STUDENT_COUNT'];
        
        // Find best fit: >= student count, smallest capacity difference
        $bestRoomId = null;
        $bestDiff = PHP_INT_MAX;

        // Optimization: Attempt to use the previously allocated room for this division if available
        $prevRoomId = $state['divisions'][$divId]['LAST_ROOM_ID'] ?? null;
        
        if ($prevRoomId && empty($roomOccupation[$entry['DAY']][$entry['SLOT_ID']][$prevRoomId])) {
            $bestRoomId = $prevRoomId;
        } else {
            foreach ($classrooms as $rId => $room) {
                if ($room['CAPACITY'] >= $studentCount && empty($roomOccupation[$entry['DAY']][$entry['SLOT_ID']][$rId])) {
                    $diff = $room['CAPACITY'] - $studentCount;
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestRoomId = $rId;
                    }
                }
            }
        }

        // Commit room
        if ($bestRoomId !== null) {
            $entry['ROOM_ID'] = $bestRoomId;
            $roomOccupation[$entry['DAY']][$entry['SLOT_ID']][$bestRoomId] = true;
            $state['divisions'][$divId]['LAST_ROOM_ID'] = $bestRoomId; // Cache for next slot
        }
    }
    
    return $timetable;
}
?>