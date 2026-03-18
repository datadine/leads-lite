<?php
/**
 * Sync FusionPBX Call Records to Leads Lite - V2
 * Uses last synced xml_cdr_uuid instead of timestamp
 */

require_once __DIR__ . '/../db.php';
require_once 'fusionpbx_config.php';

echo "Checking for new calls...\n";

try {
    // Get the last 5 synced xml_cdr_uuid values
    $stmt = $pdo->query("
        SELECT xml_cdr_uuid 
        FROM fusionpbx_calls 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $last_synced_uuids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($last_synced_uuids)) {
        echo "No previous sync found. Importing all calls...\n";
        $where_clause = "1=1"; // Import everything
        $params = [];
    } else {
        echo "Last 5 synced UUIDs found. Checking for newer calls...\n";
        // Get calls NOT in the last synced batch
        $placeholders = implode(',', array_fill(0, count($last_synced_uuids), '?'));
        $where_clause = "c.xml_cdr_uuid NOT IN ($placeholders)";
        $params = $last_synced_uuids;
    }
    
    // Fetch new call records from FusionPBX
    $sql = "
        SELECT 
            c.xml_cdr_uuid,
            e.extension,
            c.extension_uuid,
            c.caller_id_number,
            c.destination_number,
            c.start_stamp,
            c.answer_stamp,
            c.end_stamp,
            c.duration,
            c.billsec,
            c.hangup_cause,
            c.direction
        FROM v_xml_cdr c
        LEFT JOIN v_extensions e ON c.extension_uuid = e.extension_uuid
        WHERE $where_clause
        ORDER BY c.start_stamp DESC
        LIMIT 1000
    ";
    
    $stmt_fusion = $pdo_fusion->prepare($sql);
    $stmt_fusion->execute($params);
    $new_calls = $stmt_fusion->fetchAll();
    
    $synced_count = 0;
    
    // Insert new calls into Leads Lite database
    $insert_sql = "
        INSERT INTO fusionpbx_calls (
            xml_cdr_uuid, extension, extension_uuid, caller_id_number,
            destination_number, start_stamp, answer_stamp, end_stamp,
            duration, billsec, hangup_cause, direction
        ) VALUES (
            :xml_cdr_uuid, :extension, :extension_uuid, :caller_id_number,
            :destination_number, :start_stamp, :answer_stamp, :end_stamp,
            :duration, :billsec, :hangup_cause, :direction
        )
        ON CONFLICT (xml_cdr_uuid) DO NOTHING
    ";
    
    $stmt_insert = $pdo->prepare($insert_sql);
    
    foreach ($new_calls as $call) {
        try {
            $result = $stmt_insert->execute([
                'xml_cdr_uuid' => $call['xml_cdr_uuid'],
                'extension' => $call['extension'],
                'extension_uuid' => $call['extension_uuid'],
                'caller_id_number' => $call['caller_id_number'],
                'destination_number' => $call['destination_number'],
                'start_stamp' => $call['start_stamp'],
                'answer_stamp' => $call['answer_stamp'],
                'end_stamp' => $call['end_stamp'],
                'duration' => $call['duration'],
                'billsec' => $call['billsec'],
                'hangup_cause' => $call['hangup_cause'],
                'direction' => $call['direction']
            ]);
            
            if ($stmt_insert->rowCount() > 0) {
                $synced_count++;
            }
        } catch (PDOException $e) {
            // Skip duplicates silently
            if (strpos($e->getMessage(), 'duplicate key') === false) {
                error_log("Error inserting call {$call['xml_cdr_uuid']}: " . $e->getMessage());
            }
        }
    }
    
    // Log sync result (keep for monitoring)
    $log_sql = "
        INSERT INTO fusionpbx_sync_log (last_sync_time, records_synced, sync_status)
        VALUES (NOW(), :count, 'success')
    ";
    $pdo->prepare($log_sql)->execute(['count' => $synced_count]);
    
    echo "✅ Synced {$synced_count} new call records\n";
    
} catch (PDOException $e) {
    // Log error
    $error_sql = "
        INSERT INTO fusionpbx_sync_log (last_sync_time, records_synced, sync_status, error_message)
        VALUES (NOW(), 0, 'error', :error)
    ";
    $pdo->prepare($error_sql)->execute(['error' => $e->getMessage()]);
    
    echo "❌ Sync failed: " . $e->getMessage() . "\n";
    error_log("FusionPBX Sync Error: " . $e->getMessage());
}
