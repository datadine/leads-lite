<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get_extensions') {
    getExtensions();
} elseif ($action === 'get_report') {
    getReport();
} elseif ($action === 'download_csv') {
    downloadCSV();
} else {
    echo json_encode(['error' => 'Invalid action']);
}

function getExtensions() {
    global $pdo;
    
    // If admin, show all extensions
    // If regular user, only show their assigned extension
    if (is_admin()) {
        $stmt = $pdo->query("
            SELECT DISTINCT extension 
            FROM fusionpbx_calls 
            WHERE extension IS NOT NULL 
            ORDER BY extension
        ");
        $extensions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Get current user's extension
        $stmt = $pdo->prepare("SELECT extension FROM users WHERE id = ?");
        $stmt->execute([current_user_id()]);
        $user_ext = $stmt->fetchColumn();
        
        $extensions = $user_ext ? [$user_ext] : [];
    }
    
    echo json_encode(['extensions' => $extensions]);
}

function getReport() {
    global $pdo;
    
    $extension = $_GET['extension'] ?? '';
    $date_from = $_GET['date_from'] ?? date('Y-m-d');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $min_duration = intval($_GET['min_duration'] ?? 0);
    
    // For regular users, force filter to their extension only
    if (!is_admin()) {
        $stmt = $pdo->prepare("SELECT extension FROM users WHERE id = ?");
        $stmt->execute([current_user_id()]);
        $user_ext = $stmt->fetchColumn();
        
        if (!$user_ext) {
            echo json_encode([
                'total_calls' => 0,
                'total_talk_time' => '0h 0m',
                'unique_numbers' => 0,
                'avg_duration' => '0m 0s',
                'first_call' => '--:--',
                'last_call' => '--:--',
                'time_span' => '0h 0m',
                'compliance_message' => 'No extension assigned to your account',
                'compliance_class' => 'warning',
                'hourly_breakdown' => []
            ]);
            return;
        }
        
        $extension = $user_ext; // Override with user's extension
    }
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    // Date range (convert to EST)
    $where[] = "DATE(start_stamp AT TIME ZONE 'America/New_York') >= :date_from";
    $where[] = "DATE(start_stamp AT TIME ZONE 'America/New_York') <= :date_to";
    $params['date_from'] = $date_from;
    $params['date_to'] = $date_to;
    
    // Extension filter
    if ($extension) {
        $where[] = "extension = :extension";
        $params['extension'] = $extension;
    }
    
    // Duration filter
    if ($min_duration > 0) {
        $where[] = "duration >= :min_duration";
        $params['min_duration'] = $min_duration;
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Get call statistics
    $sql = "
        SELECT 
            COUNT(*) as total_calls,
            SUM(duration) as total_duration,
            COUNT(DISTINCT destination_number) as unique_numbers,
            AVG(duration) as avg_duration,
            MIN(start_stamp AT TIME ZONE 'America/New_York') as first_call,
            MAX(start_stamp AT TIME ZONE 'America/New_York') as last_call
        FROM fusionpbx_calls
        WHERE {$where_clause}
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats = $stmt->fetch();
    
    if ($stats['total_calls'] == 0) {
        echo json_encode([
            'total_calls' => 0,
            'total_talk_time' => '0h 0m',
            'unique_numbers' => 0,
            'avg_duration' => '0m 0s',
            'first_call' => '--:--',
            'last_call' => '--:--',
            'time_span' => '0h 0m',
            'compliance_message' => 'No calls found',
            'compliance_class' => 'warning',
            'hourly_breakdown' => []
        ]);
        return;
    }
    
    // Format times
    $total_seconds = intval($stats['total_duration']);
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    $total_talk_time = "{$hours}h {$minutes}m";
    
    $avg_seconds = intval($stats['avg_duration']);
    $avg_min = floor($avg_seconds / 60);
    $avg_sec = $avg_seconds % 60;
    $avg_duration = "{$avg_min}m {$avg_sec}s";
    
    $first_call_time = new DateTime($stats['first_call']);
    $last_call_time = new DateTime($stats['last_call']);
    
    $first_call = $first_call_time->format('g:i A');
    $last_call = $last_call_time->format('g:i A');

    // Calculate time span (first to last call, for display only)
    $diff = $first_call_time->diff($last_call_time);
    $span_hours = $diff->h + ($diff->days * 24);
    $span_minutes = $diff->i;
    $time_span = "{$span_hours}h {$span_minutes}m";

    // Count weekdays in date range
    $weekdays = 0;
    $current = new DateTime($date_from);
    $end_dt  = new DateTime($date_to);
    while ($current <= $end_dt) {
        $dow = (int)$current->format('N'); // 1=Mon, 7=Sun
        if ($dow <= 5) $weekdays++;
        $current->modify('+1 day');
    }

    // Get actual dialing time from dial_sessions for the date range
    $ds_params = [':df' => $date_from, ':dt' => $date_to];
    $ds_where = "WHERE status = 'closed' AND total_seconds > 0 AND DATE(started_at AT TIME ZONE 'America/New_York') BETWEEN :df AND :dt";
    if ($extension) {
        $ds_where .= " AND user_id = (SELECT id FROM public.users WHERE extension = :ext LIMIT 1)";
        $ds_params[':ext'] = $extension;
    }
    $ds_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_seconds), 0) FROM public.dial_sessions $ds_where");
    $ds_stmt->execute($ds_params);
    $dialed_seconds = (int)$ds_stmt->fetchColumn();

    $dialed_hours = floor($dialed_seconds / 3600);
    $dialed_mins  = floor(($dialed_seconds % 3600) / 60);
    $dialed_time  = "{$dialed_hours}h {$dialed_mins}m";

    // Average per weekday
    $avg_seconds   = $weekdays > 0 ? intdiv($dialed_seconds, $weekdays) : 0;
    $avg_hours     = floor($avg_seconds / 3600);
    $avg_mins      = floor(($avg_seconds % 3600) / 60);
    $avg_time      = "{$avg_hours}h {$avg_mins}m";

    // Compliance based on average daily dialing vs 9 hour target
    $target_seconds = 9 * 3600;

    if ($avg_seconds >= $target_seconds) {
        $compliance_message = "✅ Target achieved! Weekdays: {$weekdays} · Total on dialer: {$dialed_time} · Avg/day: {$avg_time}";
        $compliance_class = 'success';
    } elseif ($avg_seconds >= ($target_seconds * 0.8)) {
        $short_secs = $target_seconds - $avg_seconds;
        $short_h = floor($short_secs / 3600);
        $short_m = floor(($short_secs % 3600) / 60);
        $compliance_message = "⚠️ Close! Weekdays: {$weekdays} · Total on dialer: {$dialed_time} · Avg/day: {$avg_time} · Short by {$short_h}h {$short_m}m/day";
        $compliance_class = 'warning';
    } else {
        $short_secs = $target_seconds - $avg_seconds;
        $short_h = floor($short_secs / 3600);
        $short_m = floor(($short_secs % 3600) / 60);
        $compliance_message = "❌ Below target. Weekdays: {$weekdays} · Total on dialer: {$dialed_time} · Avg/day: {$avg_time} · Short by {$short_h}h {$short_m}m/day";
        $compliance_class = 'danger';
    }
    
    // Get hourly breakdown
    $hourly_sql = "
        SELECT 
            EXTRACT(HOUR FROM start_stamp AT TIME ZONE 'America/New_York') as hour,
            COUNT(*) as calls,
            SUM(duration) as talk_seconds
        FROM fusionpbx_calls
        WHERE {$where_clause}
        GROUP BY hour
        ORDER BY hour
    ";
    
    $stmt = $pdo->prepare($hourly_sql);
    $stmt->execute($params);
    $hourly_raw = $stmt->fetchAll();
    
    $hourly_breakdown = [];
    foreach ($hourly_raw as $row) {
        $hour_num = intval($row['hour']);
        $hour_label = ($hour_num == 0) ? '12 AM' : 
                     (($hour_num < 12) ? "{$hour_num} AM" : 
                     (($hour_num == 12) ? '12 PM' : ($hour_num - 12) . ' PM'));
        
        $talk_secs = intval($row['talk_seconds']);
        $talk_mins = floor($talk_secs / 60);
        $talk_time = "{$talk_mins}m";
        
        $hourly_breakdown[] = [
            'hour' => $hour_label,
            'calls' => intval($row['calls']),
            'talk_time' => $talk_time
        ];
    }
    
    echo json_encode([
        'total_calls' => intval($stats['total_calls']),
        'total_talk_time' => $total_talk_time,
        'unique_numbers' => intval($stats['unique_numbers']),
        'avg_duration' => $avg_duration,
        'first_call' => $first_call,
        'last_call' => $last_call,
        'time_span' => $time_span,
        'compliance_message' => $compliance_message,
        'compliance_class' => $compliance_class,
        'hourly_breakdown' => $hourly_breakdown
    ]);
}

function downloadCSV() {
    global $pdo;

    $extension    = $_GET['extension']    ?? '';
    $date_from    = $_GET['date_from']    ?? date('Y-m-d');
    $date_to      = $_GET['date_to']      ?? date('Y-m-d');
    $min_duration = (int)($_GET['min_duration'] ?? 0);

    // Non-admins locked to their own extension
    if (!is_admin()) {
        $stmt = $pdo->prepare("SELECT extension FROM users WHERE id = ?");
        $stmt->execute([current_user_id()]);
        $extension = $stmt->fetchColumn();
    }

    $where  = "WHERE DATE(fc.start_stamp AT TIME ZONE 'America/New_York') BETWEEN :df AND :dt";
    $params = [':df' => $date_from, ':dt' => $date_to];

    if ($extension !== '') {
        $where .= " AND fc.extension = :ext";
        $params[':ext'] = $extension;
    }

    if ($min_duration > 0) {
        $where .= " AND fc.billsec >= :md";
        $params[':md'] = $min_duration;
    }

    $stmt = $pdo->prepare("
        SELECT DISTINCT ON (fc.xml_cdr_uuid)
            fc.start_stamp AT TIME ZONE 'America/New_York' AS call_time,
            fc.extension,
            fc.caller_id_number,
            fc.destination_number,
            fc.direction,
            fc.duration,
            fc.billsec,
            COALESCE(cl.outcome, fc.hangup_cause) AS outcome
        FROM fusionpbx_calls fc
        LEFT JOIN public.call_logs cl
            ON cl.lead_id = (
                SELECT l.id FROM public.leads l
                WHERE RIGHT(REGEXP_REPLACE(l.phone, '[^0-9]', '', 'g'), 10) = RIGHT(REGEXP_REPLACE(fc.destination_number, '[^0-9]', '', 'g'), 10)
                LIMIT 1
            )
            AND DATE(cl.call_time AT TIME ZONE 'America/New_York') = DATE(fc.start_stamp AT TIME ZONE 'America/New_York')
        $where
        ORDER BY fc.xml_cdr_uuid, cl.id DESC NULLS LAST, fc.start_stamp DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'call_report_' . $date_from . '_to_' . $date_to . ($extension ? '_ext'.$extension : '') . '.csv';

    // Clear any buffered output (prevents JSON error leaking into CSV)
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date/Time', 'Extension', 'Caller ID', 'Destination', 'Direction', 'Duration', 'Talk Time (sec)', 'Outcome']);

    $outcome_labels = [
        'interested'     => 'Interested',
        'not_interested' => 'Not Interested',
        'no_answer'      => 'No Answer',
        'called'         => 'Called',
        'callback'       => 'Call Back',
    ];

    foreach ($rows as $row) {
        $duration_fmt = sprintf('%d:%02d', intdiv((int)$row['duration'], 60), (int)$row['duration'] % 60);
        $outcome = $outcome_labels[$row['outcome']] ?? $row['outcome'];
        fputcsv($out, [
            $row['call_time'],
            $row['extension'],
            $row['caller_id_number'],
            $row['destination_number'],
            $row['direction'],
            $duration_fmt,
            $row['billsec'],
            $outcome,
        ]);
    }

    fclose($out);
    exit;
}
