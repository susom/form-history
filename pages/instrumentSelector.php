<?php
namespace Stanford\FormHistory;
/** @var \Stanford\FormHistory\FormHistory $module */

use \REDCap;
use \DateTime;

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$selected_form_event = isset($_POST['form_event']) && !empty($_POST['form_event']) ? $_POST['form_event'] : null;
$selected_record = isset($_POST['record']) && !empty($_POST['record']) ? $_POST['record'] : null;

global $Proj;
$stylesheet = $module->getUrl('pages/selector.css');

$module->emDebug("**** New request ****");
$module->emDebug("selected forms/events: " . json_encode($selected_form_event) . ", selected record: " . $selected_record);

$selected_form = '';
$selected_event = '';
if (!empty($selected_form_event) && !empty($selected_record)) {

    // Find the log table where the history data is stored
    $log_table_name = findLogTable($pid);

    // Loop over each form/event
    $running_total_fields = array();
    $running_total_data = array();
    foreach ($selected_form_event as $one_form_event) {

        // Split out the form and event
        list($selected_event_name, $selected_form) = splitFormEvent($one_form_event);

        // Find which fields are on this form
        $fields_on_form = $Proj->forms[$selected_form]['fields'];
        $selected_field_names = array_keys($fields_on_form);

        // Find the event id from the event name
        $event_names = REDCap::getEventNames(true, true);
        $selected_event = array_search($selected_event_name, $event_names);

        // Retrieve data from the log table
        list($history_entries, $all_updated_field_names) =
            findHistoryData($pid, $selected_event, $selected_event_name, $selected_record,
                $selected_field_names, $log_table_name);

        // Merge the new data with the old
        if (empty($running_total_fields)) {
            $running_total_fields = $all_updated_field_names;
        } else {
            $running_total_fields = array_merge($running_total_fields, $all_updated_field_names);
        }
        if (empty($running_total_data)) {
            $running_total_data = $history_entries;
        } else {
            $running_total_data += $history_entries;
        }
    }

    // Do we want to bin the results in a timeframe in case there are multi-page surveys, etc.
    // Should this be a config parameter?
    $binned_results = binResultsOnTS($running_total_data);

    $req_header_fields = array("ts data saved", REDCap::getRecordIdField(), "redcap_event_name");
    $all_unique_fields = array_unique($running_total_fields);

    $status = reformatDataToCSVAndDownload($binned_results, $req_header_fields, $all_unique_fields);

    return;

}


// Put together the form/event list
$forms = getFormEventList();

// Create a drop down for the records
$record_list = retrieveRecordList();
$records = generateHTMLRecordList($record_list);

function splitFormEvent($selected_form_event) {

    global $module;

    // The format is 'ef-' . event name . '-' . form name
    $pieces = explode("-", $selected_form_event);
    return array($pieces[1], $pieces[2]);

}

function reformatDataToCSVAndDownload($binned_results, $req_header_fields, $all_updated_field_names) {

    global $module;
    $status = true;
    $csv_format = array();

    // First add the header in the first row
    $header = array_merge($req_header_fields, $all_updated_field_names);
    $csv_format[] = array_values($header);

    // Extract timestamps and sort them so we go from older to newer
    $all_update_timestamps = array_keys($binned_results);
    sort($all_update_timestamps);

    // Next add each updated row.  We need to put the data in the same order as the headers
    // To make the file as close to uploadable as possible, we are putting data in the following order:
    // 1) timestamp of update, 2) record_id, 3) redcap_event_name, 4) updated data ....
    $last_save = array();
    foreach($all_update_timestamps as $timestamp) {
        foreach($binned_results[$timestamp] as $record_id => $record) {
            foreach($record as $event_name => $fields) {

                $timestamp_reformatted = reformat_timestamp($timestamp);
                $one_row = array();

                // If there was a previous save, start with that save and update the fields that have a save
                // so that we have running list of what the form looked like at the time of save.
                if (is_null($last_save[$record_id][$event_name])) {

                    // These are the 3 required header fields that each row is required to have
                    $one_row = array("ts" => $timestamp_reformatted, "rid" => $record_id, "eid" => $event_name);
                } else {
                    $one_row = $last_save[$record_id][$event_name];
                    $one_row["ts"] = $timestamp_reformatted;
                    $one_row["eid"] = $event_name;
                }

                // now loop over update fields
                foreach ($all_updated_field_names as $field) {
                    if (!is_null($fields[$field])) {
                        $one_row[$field] = $fields[$field];
                    } else if (is_null($one_row[$field])) {
                        $one_row[$field] = null;
                    }
                }

                // Save this row
                $csv_format[] = array_values($one_row);
                $last_save[$record_id][$event_name] = $one_row;
            }
        }
    }

    //$module->emDebug("This is csv format: " . json_encode($csv_format));
    $status = downloadCSVFile($csv_format);

    return $status;
}

function reformat_timestamp($timestamp) {

    global $module;
    $d = new DateTime($timestamp);
    return $d->format('Y-m-d H:i:s');

}

function downloadCSVFile($data)
{
    global $module;

    // Open the stream to the file
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=history_data.csv');

    $fp = fopen('php://output', 'w');
    if ($fp !== false) {

        // Write out each row of the csv
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
    }

    // Close the stream
    fclose($fp);
    ob_end_flush();

    return true;
}


function binResultsOnTS($updated_results) {

    $binned_results = $updated_results;
    return $binned_results;
}

function findHistoryData($pid, $selected_event, $selected_event_name, $selected_records,
                         $selected_field_names, $log_table_name) {

    global $module;

    $sql = "select pk, ts, data_values from " . $log_table_name .
        " where project_id = " . $pid .
        " and event_id = " . $selected_event .
        " and pk in (" . $selected_records . ")" .
        " and object_type = 'redcap_data' order by pk, event_id, ts";

    $merged_fields = array();
    $history_data = array();
    $q = db_query($sql);
    // Loop over each entry in the log table
    while ($history_results = db_fetch_assoc($q)) {

        // This entry may have many fields that were updated at the same time, so parse the entries into an array
        list($updated_data, $updated_field_names) = parseUpdatesIntoArray($history_results['data_values'], $selected_field_names);

        // If there are fields that were updated, save fields and values.  Our array looks like
        //  timestamp data saved, record_id, event name => array(updated field 1, updated field 2, etc.)
        if (!empty($updated_data)) {
            $history_data[$history_results['ts']][$history_results['pk']][$selected_event_name] = $updated_data;
        }

        // Keep track of all fields that were updated
        if (empty($merged_fields)) {
            $merged_fields = $updated_field_names;
        } else {
            $merged_fields = array_merge($merged_fields, $updated_field_names);
        }

    }

    // This is the list of all fields updated for this record on this form
    $all_updated_field_names = array_unique($merged_fields);

    return array($history_data, $all_updated_field_names);
}


function parseUpdatesIntoArray($db_updates, $field_names) {

    global $module;

    $all_updated_field_names = array();
    $save_fields = array();
    $fields_updated = explode(",\n", $db_updates);
    foreach($fields_updated as $value) {

        // field name in $new_value[0] and field value in $new_value[1]
        $new_value = explode(' = ', $value);

        // Depending on the type of field, reformat the data to an upload format
        // What do we do about file upload fields?
        if (($npos = strpos($new_value[0], '(')) === false) {
            $upload_field = $field_name = trim($new_value[0]);
            $value = trim($new_value[1]);
            if (strlen($value) > 2) {
                $upload_value = substr($value, 1, strlen($value) - 2);
            } else {
                $upload_value = null;
            }
        } else {

            // This section is for checkboxes because their name comes in as xxx(1) and should be put
            // into format xxx___1 and the value comes in as checked or unchecked and should be reformatted
            // to 0 and 1.
            $field_name = trim(substr($new_value[0],0, $npos));
            $npos_end = strpos($new_value[0], ')');
            $coded_value = substr($new_value[0], $npos+1, (strlen($new_value[0]) - $npos_end));
            $upload_field = $field_name . '___' . $coded_value;
            if (trim($new_value[1]) == 'checked') {
                $upload_value = 1;
            } else {
                $upload_value = 0;
            }
        }

        // If this field is on the form, save it as a possible value to re-save.
        if (in_array($field_name, $field_names)) {
            $save_fields[$upload_field] = $upload_value;
            $all_updated_field_names[] = $upload_field;
        }
    }

    return array($save_fields, $all_updated_field_names);

}

function findLogTable($proj_id) {

    // Query the database to see which log table our data resides
    $sql = 'select log_event_table from redcap_projects where project_id = ' . $proj_id;
    $q = db_query($sql);
    $log_table_name = db_fetch_row($q)[0];

    return $log_table_name;
}

function retrieveRecordList() {

    global $module, $Proj;

    $pk = REDCap::getRecordIdField();
    $first_event_id = $Proj->firstEventId;
    $json_records = REDCap::getData('json', null, $pk, $first_event_id);
    $record_array = json_decode($json_records, true);

    $records = array();
    foreach($record_array as $key => $record_name) {
        $records[] = $record_name['record_id'];
    }

    return $records;
}

function generateHTMLRecordList($records) {

    global $module;
    $html = '';

    foreach ($records as $record) {
        $html .= "<option value='$record'/>";

    }

    return $html;
}


function getFormEventList() {

    $forms =  \RecordDashboard::renderSelectedFormsEvents();
    return $forms;
}


require_once $module->getModulePath() . "pages/selector.php";
