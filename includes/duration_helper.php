<?php
// includes/duration_helper.php

function formatDuration($seconds) {
    if ($seconds === null || $seconds < 0) {
        return '';
    }

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    $parts = [];

    // Only include non-zero values unless all are zero
    if ($hours > 0 || ($minutes == 0 && $secs == 0)) {
        $parts[] = $hours . ($hours == 1 ? " hr" : " hrs");
    }
    
    if ($minutes > 0 || ($hours == 0 && $secs == 0)) {
        $parts[] = $minutes . ($minutes == 1 ? " min" : " mins");
    }
    
    if ($secs > 0 || empty($parts)) {
        $parts[] = $secs . ($secs == 1 ? " sec" : " secs");
    }

    return implode(", ", $parts);
}

// Function to get verbose duration for display
function getVerboseDuration($seconds) {
    if ($seconds === null || $seconds < 0) {
        return '';
    }

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    return sprintf("%d hrs, %d mins, %d secs", $hours, $minutes, $secs);
}