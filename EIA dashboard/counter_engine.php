<?php
/**
 * Lightweight Visitor Counter Engine
 * Tracks Total Hits and Live Active Users (5 min window)
 */

function get_visitor_stats() {
    $total_file = __DIR__ . '/total_hits.txt';
    $live_file = __DIR__ . '/active_sessions.json';
    $now = time();
    $window = 300; // 5 minutes in seconds

    // 1. Handle Total Hits
    if (!file_exists($total_file)) {
        file_put_contents($total_file, '100'); // Start at 100 or 0
    }
    $total = (int)file_get_contents($total_file);
    
    // Only increment total once per session to avoid refresh spamming
    if (!isset($_SESSION['counted_visit'])) {
        $total++;
        file_put_contents($total_file, $total);
        $_SESSION['counted_visit'] = true;
    }

    // 2. Handle Live Users
    $active_users = [];
    if (file_exists($live_file)) {
        $active_users = json_decode(file_get_contents($live_file), true) ?: [];
    }

    // Update current user's timestamp (using session ID for accuracy)
    $session_id = session_id();
    $active_users[$session_id] = $now;

    // Cleanup: Remove users older than 5 minutes
    foreach ($active_users as $id => $timestamp) {
        if ($now - $timestamp > $window) {
            unset($active_users[$id]);
        }
    }

    // Save back to file
    file_put_contents($live_file, json_encode($active_users));

    return [
        'total' => $total,
        'live' => count($active_users)
    ];
}

// Get stats
$stats = get_visitor_stats();
$total_visitors = $stats['total'];
$live_visitors = $stats['live'];
?>