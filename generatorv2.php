<?php
/**
 * generatorv2.php
 * Algorithmic Timetable Generator - B.Sc. IT & General Programmes
 */

// Helper to find all slot IDs that overlap in time with a target slot
function getOverlappingSlots($sId, $timeslots) {
    $overlap = [];
    $target = $timeslots[$sId];
    $tStart = strtotime($target['START_TIME']);
    $tEnd = strtotime($target['END_TIME']);
    
    foreach ($timeslots as $id => $slot) {
        $start = strtotime($slot['START_TIME']);
        $end = strtotime($slot['END_TIME']);
        // Overlap condition: start of B is before end of A AND end of B is after start of A
        if ($start < $tEnd && $end > $tStart) {
            $overlap[] = $id;
        }
    }
    return $overlap;
}

// 1. MAIN PIPELINE EXECUTION
function generateTimetable($semester, $academicYear) {
    global $conn;
    
    $state = gatherData($conn, $semester);
    $state = prepareForAllotment($state);
    $state = allocatePracticals($state);
    $state = preprocessTheorySlots($state);
    $state = allocateTheory($state);
    $timetable = allocateClassrooms($state);
    
    $validation = [
        'pending_workloads' => array_filter($state['workloads'], fn($w) => $w['PENDING_LECTURES'] > 0),
        'unassigned_slots' => [] 
    ];

    return ['timetable' => $timetable, 'validation' => $validation];
}

// 2. DATA GATHERING
function gatherData($conn, $semester) {
    $data = [
        'teachers' => [], 'classrooms' => [], 'courses' => [], 
        'workloads' => [], 'divisions' => [], 'timeslots' => [],
        'room_occupation' => [] // Global tracker to prevent overlap across modules
    ];

    $res = $conn->query("SELECT SLOT_ID, START_TIME, END_TIME, SLOT_TYPE FROM TIMESLOT ORDER BY START_TIME");
    while ($row = $res->fetch_assoc()) $data['timeslots'][$row['SLOT_ID']] = $row;

    $res = $conn->query("SELECT CLASSROOM_ID, CAPACITY, CATEGORY FROM CLASSROOM ORDER BY CAPACITY DESC");
    while ($row = $res->fetch_assoc()) $data['classrooms'][$row['CLASSROOM_ID']] = $row;

    // MINIMAL CHANGE: Added CLASSROOM_ID to fetch Division preferred classrooms
    $res = $conn->query("SELECT DIVISION_ID, STUDENT_COUNT, START_TIME_ID, END_TIME_ID, CLASSROOM_ID FROM DIVISION");
    while ($row = $res->fetch_assoc()) {
        $row['ALLOCATED_PRACTICALS'] = [];
        $data['divisions'][$row['DIVISION_ID']] = $row;
    }

    $stmt = $conn->prepare("SELECT COURSE_ID, WEEKLY_LECTURES, ISOPTIONAL, ISPRACTICAL, OPTIONAL_ID FROM COURSE WHERE SEMESTER = ?");
    $stmt->bind_param("s", $semester);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $data['courses'][$row['COURSE_ID']] = $row;

    $optedBy = [];
    $resOpted = $conn->query("SELECT COURSE_ID, DIVISION_ID FROM OPTED_BY");
    while ($row = $resOpted->fetch_assoc()) {
        $optedBy[$row['COURSE_ID']][$row['DIVISION_ID']] = true;
    }

    $res = $conn->query("
        SELECT t.WORKLOAD_ID, t.TEACHER_ID, t.COURSE_ID, t.DIVISION_ID, t.LECTURE_COUNT, c.ISOPTIONAL 
        FROM TEACHES t
        JOIN COURSE c ON t.COURSE_ID = c.COURSE_ID
        WHERE c.SEMESTER = '$semester'
    ");
    while ($row = $res->fetch_assoc()) {
        $cId = $row['COURSE_ID'];
        $dId = $row['DIVISION_ID'];
        
        if ($row['ISOPTIONAL'] && !isset($optedBy[$cId][$dId])) {
            continue;
        }

        $row['PENDING_LECTURES'] = (int)$row['LECTURE_COUNT'];
        $data['workloads'][$row['WORKLOAD_ID']] = $row;
    }

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
    uasort($state['teachers'], fn($a, $b) => $a['TOTAL_AVAILABILITY'] <=> $b['TOTAL_AVAILABILITY']);

    // MINIMAL CHANGE: Sort divisions by time preference to ensure constraints are respected
    uasort($state['divisions'], function($a, $b) {
        $aScore = !empty($a['START_TIME_ID']) ? 2 : (!empty($a['END_TIME_ID']) ? 1 : 0);
        $bScore = !empty($b['START_TIME_ID']) ? 2 : (!empty($b['END_TIME_ID']) ? 1 : 0);
        return $bScore <=> $aScore;
    });

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
function allocatePracticals($state) {
    $state['timetable'] = [];
    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
    
    $labs = array_filter($state['classrooms'], fn($c) => strpos($c['CATEGORY'], 'LAB') !== false);
    $practicalSlots = array_filter($state['timeslots'], fn($s) => $s['SLOT_TYPE'] === 'PRACTICAL');

    foreach ($state['divisions'] as $divId => &$division) {
        if (empty($division['HAS_PRACTICALS'])) continue;

        $labMatrices = [];
        foreach ($labs as $lId => $lab) {
            $type = $lab['CATEGORY'];
            foreach ($practicalSlots as $sId => $slot) {
                $labMatrices[$type][] = ['SLOT_ID' => $sId, 'ROOM_ID' => $lId];
            }
        }

        $fixedSlotId = null;

        foreach ($state['workloads'] as $wId => &$workload) {
            if ($workload['DIVISION_ID'] != $divId || $workload['PENDING_LECTURES'] <= 0) continue;
            
            $course = $state['courses'][$workload['COURSE_ID']];
            if (!$course['ISPRACTICAL']) continue;
            
            $tId = $workload['TEACHER_ID'];
            $teacher = &$state['teachers'][$tId];
            
            $expectedLabType = str_replace('PRACTICAL', 'LAB', $course['TYPE'] ?? 'IT PRACTICAL');
            $availableCartesianPairs = $labMatrices[$expectedLabType] ?? [];

            if (!empty($division['END_TIME_ID'])) {
                $availableCartesianPairs = array_reverse($availableCartesianPairs);
            }

            while ($workload['PENDING_LECTURES'] > 0) {
                $allocated = false;
                
                foreach ($weekdays as $day) {
                    foreach ($availableCartesianPairs as $pair) {
                        $sId = $pair['SLOT_ID'];
                        
                        if ($fixedSlotId !== null && $sId !== $fixedSlotId) continue;

                        $overlapping = getOverlappingSlots($sId, $state['timeslots']);
                        
                        // Ensure all overlapping time slots are completely free for teacher and classroom
                        $canAllocate = true;
                        foreach ($overlapping as $oId) {
                            if (empty($teacher['MATRIX'][$day][$oId]) || 
                                isset($division['ALLOCATED_PRACTICALS'][$day][$oId]) || 
                                !empty($state['room_occupation'][$day][$oId][$pair['ROOM_ID']])) {
                                $canAllocate = false;
                                break;
                            }
                        }

                        if ($canAllocate) {
                            if ($fixedSlotId === null) $fixedSlotId = $sId;
                            
                            $state['timetable'][] = [
                                'COURSE_ID' => $workload['COURSE_ID'],
                                'DIVISION_ID' => $divId,
                                'ROOM_ID' => $pair['ROOM_ID'],
                                'SLOT_ID' => $sId,
                                'DAY' => $day,
                                'TEACHER_ID' => $tId,
                                'TYPE' => 'PRACTICAL'
                            ];
                            
                            // Consume availability across all overlapping matrices
                            foreach ($overlapping as $oId) {
                                $teacher['MATRIX'][$day][$oId] = false;
                                $division['ALLOCATED_PRACTICALS'][$day][$oId] = true;
                                $state['room_occupation'][$day][$oId][$pair['ROOM_ID']] = true;
                            }

                            $workload['PENDING_LECTURES']--;
                            $allocated = true;
                            break 2; 
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
function preprocessTheorySlots($state) {
    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
    $lectureSlots = array_filter($state['timeslots'], fn($s) => $s['SLOT_TYPE'] === 'LECTURE');

    foreach ($state['divisions'] as $divId => &$division) {
        $division['ALLOCATABLE_SLOTS'] = [];
        $division['FALLBACK_SLOTS'] = [];
        $division['FORCE_SLOTS'] = []; 
        
        $divWorkloads = array_filter($state['workloads'], fn($w) => $w['DIVISION_ID'] == $divId && !$state['courses'][$w['COURSE_ID']]['ISPRACTICAL']);
        $totalTheoryHours = array_sum(array_column($divWorkloads, 'PENDING_LECTURES'));
        $dailyQuota = ceil($totalTheoryHours / 6);

        foreach ($weekdays as $day) {
            $dailySlots = [];
            
            // MINIMAL CHANGE: Simplified check as ALLOCATED_PRACTICALS now reliably holds all overlapping 1-hour Slot IDs
            foreach ($lectureSlots as $sId => $slot) {
                if (empty($division['ALLOCATED_PRACTICALS'][$day][$sId])) {
                    $dailySlots[] = $sId;
                }
            }

            $slotCount = count($dailySlots);
            $quota = min($dailyQuota, $slotCount);

            if (!empty($division['START_TIME_ID'])) {
                $division['ALLOCATABLE_SLOTS'][$day] = array_slice($dailySlots, 0, $quota);
                $division['FALLBACK_SLOTS'][$day] = array_slice($dailySlots, $quota); 
            } else {
                $division['ALLOCATABLE_SLOTS'][$day] = array_slice($dailySlots, -$quota, $quota);
                $division['FALLBACK_SLOTS'][$day] = array_slice($dailySlots, 0, $slotCount - $quota);
            }
            
            // Full non-overlapping daily slots for brute-force assignment
            $division['FORCE_SLOTS'][$day] = $dailySlots;
        }
    }
    return $state;
}

// 7 & 8. ALLOCATE THEORY WITH RANDOMIZATION & FALLBACK
function allocateTheory($state) {
    $weekdays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
    
    // MINIMAL CHANGE: Added FORCE_SLOTS to guarantee all workload is fully assigned
    $passes = ['ALLOCATABLE_SLOTS', 'FALLBACK_SLOTS', 'FORCE_SLOTS'];

    foreach ($passes as $passKey) {
        foreach ($state['divisions'] as $divId => &$division) {
            $pendingDivWorkloads = array_filter($state['workloads'], fn($w) => $w['DIVISION_ID'] == $divId && $w['PENDING_LECTURES'] > 0 && !$state['courses'][$w['COURSE_ID']]['ISPRACTICAL']);
            
            if (empty($pendingDivWorkloads)) continue;

            foreach ($weekdays as $day) {
                if (empty($division[$passKey][$day])) continue;

                foreach ($division[$passKey][$day] as $sId) {
                    $pendingList = array_filter($state['workloads'], fn($w) => $w['DIVISION_ID'] == $divId && $w['PENDING_LECTURES'] > 0 && !$state['courses'][$w['COURSE_ID']]['ISPRACTICAL']);
                    if (empty($pendingList)) break 2;

                    $availableWorkloads = array_filter($pendingList, function($w) use ($state, $day, $sId) {
                        return !empty($state['teachers'][$w['TEACHER_ID']]['MATRIX'][$day][$sId]);
                    });

                    if (!empty($availableWorkloads)) {
                        $selectedWorkloadKey = array_rand($availableWorkloads);
                        $selectedWorkload = &$state['workloads'][$selectedWorkloadKey];
                        $tId = $selectedWorkload['TEACHER_ID'];

                        $state['timetable'][] = [
                            'COURSE_ID' => $selectedWorkload['COURSE_ID'],
                            'DIVISION_ID' => $divId,
                            'ROOM_ID' => null, 
                            'SLOT_ID' => $sId,
                            'DAY' => $day,
                            'TEACHER_ID' => $tId,
                            'TYPE' => 'LECTURE'
                        ];

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
    
    // Resume using the global room occupation map initialized during practical assignment
    $roomOccupation = $state['room_occupation'] ?? [];

    foreach ($timetable as &$entry) {
        // Practicals already have rooms assigned and exist in $roomOccupation
        if ($entry['ROOM_ID'] !== null) {
            continue;
        }

        $divId = $entry['DIVISION_ID'];
        $studentCount = $state['divisions'][$divId]['STUDENT_COUNT'];
        
        // MINIMAL CHANGE: Pull explicitly preferred rooms for the division
        $prefRoomId = $state['divisions'][$divId]['CLASSROOM_ID'] ?? null;
        $prevRoomId = $state['divisions'][$divId]['LAST_ROOM_ID'] ?? null;
        
        $bestRoomId = null;
        $bestDiff = PHP_INT_MAX;

        $overlapping = getOverlappingSlots($entry['SLOT_ID'], $state['timeslots']);
        
        // Helper to guarantee the room is free in all intersecting slot slices
        $isRoomFree = function($rId) use (&$roomOccupation, $entry, $overlapping) {
            foreach ($overlapping as $oId) {
                if (!empty($roomOccupation[$entry['DAY']][$oId][$rId])) return false;
            }
            return true;
        };

        // 1. Try Preferred Room
        if ($prefRoomId && isset($state['classrooms'][$prefRoomId]) && $state['classrooms'][$prefRoomId]['CAPACITY'] >= $studentCount && $isRoomFree($prefRoomId)) {
            $bestRoomId = $prefRoomId;
        } 
        // 2. Try Last Used Room 
        elseif ($prevRoomId && isset($state['classrooms'][$prevRoomId]) && $state['classrooms'][$prevRoomId]['CAPACITY'] >= $studentCount && $isRoomFree($prevRoomId)) {
            $bestRoomId = $prevRoomId;
        } 
        // 3. Fallback to Algorithm Best-Fit
        else {
            foreach ($classrooms as $rId => $room) {
                if ($room['CAPACITY'] >= $studentCount && $isRoomFree($rId)) {
                    $diff = $room['CAPACITY'] - $studentCount;
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestRoomId = $rId;
                    }
                }
            }
        }

        // Commit room and cascade occupation overlaps
        if ($bestRoomId !== null) {
            $entry['ROOM_ID'] = $bestRoomId;
            foreach ($overlapping as $oId) {
                $roomOccupation[$entry['DAY']][$oId][$bestRoomId] = true;
            }
            $state['divisions'][$divId]['LAST_ROOM_ID'] = $bestRoomId;
        }
    }
    
    return $timetable;
}

?>