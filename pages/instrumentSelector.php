<?php
namespace Stanford\RetrieveHistoryData;
/** @var \Stanford\RetrieveHistoryData\RetrieveHistoryData $module */

use \REDCap;

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$action = isset($_POST['action']) && !empty($_POST['action']) ? $_POST['action'] : null;
$selected_form = isset($_POST['form']) && !empty($_POST['form']) ? $_POST['form'] : null;
$selected_event = isset($_POST['event']) && !empty($_POST['event']) ? $_POST['event'] : null;
$selected_records = isset($_POST['record']) && !empty($_POST['records']) ? $_POST['records'] : null;

$module->emDebug("This is the post action: " . $action . " for project " . $pid . ", form: " . $selected_form);
if (empty($pid)) {
    return;
}
$user = USERID;
global $Proj;

$action = 'retrieve';
$selected_form = 'lab_result_automation';
$selected_event = 225;
$selected_records = "'20', '21'";

if (empty($selected_form)) {

    $forms = getFormList();

} else if ($action == 'events') {

    $events = getEventList($selected_form);

    print $events;
    return;

} else if ($action == 'records') {

    // Create checkboxes for each record so the user can decide which records to retrieve
    $records = retrieveRecordList($selected_event);
    $html = generateHTMLRecordList($records);

    print $html;
    return;

} else if ($action == 'retrieve') {

    // Find which fields are on this form
    $fields_on_form = $Proj->forms[$selected_form]['fields'];
    $selected_field_names = array_keys($fields_on_form);

    // If the selected event is empty, select the first event (which is probably the only event in the project).
    if (empty($selected_event)) {
        $selected_event = $Proj->firstEventId;
    }

    // Find the event name from the event number
    if (is_numeric($selected_event)) {
        $event_names = REDCap::getEventNames(true, true);
        $selected_event_name = $event_names[$selected_event];
    }

    // Find log table and retrieve data from the log table
    $log_table_name = findLogTable($pid);
    list($history_entries, $all_updated_field_names) =
        findHistoryData($pid, $selected_event, $selected_records,
                        $selected_field_names, $log_table_name);

    // Do we want to bin the results in a timeframe in case there are multi-page surveys, etc.
    // Should this be a config parameter?
    $binned_results = binResultsOnTS($history_entries);

    $status = downloadCSVToFile($binned_results, $all_updated_field_names, $selected_event_name);
}

function downloadCSVToFile($binned_results, $all_updated_field_names, $selected_event_name) {

    global $module;
    $status = true;

    $csv_format = array();

    // First add the header in the first row
    $csv_format[] = array_values($all_updated_field_names);

    // Next add each updated row.  We need to put the data in the same order as the headers
    // To make the file as close to uploadable as possible, we are putting data in the following order:
    // 1) timestamp of update, 2) record_id, 3) redcap_event_name, 4) rest of data ....
    foreach($binned_results as $record => $record_updates) {
        foreach($record_updates as $timestamp => $ts_updates) {
            $one_row = array($timestamp, $record, $selected_event_name);
            foreach ($all_updated_field_names as $field_name) {
                $one_row[] = $ts_updates[$field_name];
            }
            $csv_format[] = $one_row;
        }
    }

    // This should be the array to write to a file
    $module->emDebug("CSV File: " . json_encode($csv_format));

    return $status;
}

function binResultsOnTS($updated_results) {

    $binned_results = $updated_results;
    return $binned_results;
}

function findHistoryData($pid, $selected_event, $selected_records, $selected_field_names, $log_table_name) {

    $sql = "select pk, ts, data_values from " . $log_table_name .
                " where project_id = " . $pid .
                " and event_id = " . $selected_event .
                " and pk in (" . $selected_records . ")" .
                " and object_type = 'redcap_data' order by pk, ts";

    $merged_array = array();
    $history_data = array();
    $q = db_query($sql);
    while ($history_results = db_fetch_assoc($q)) {
        list($updated_data, $updated_field_names) = parseUpdatesIntoArray($history_results['data_values'], $selected_field_names);
        if (!empty($updated_data)) {
            $history_data[$history_results['pk']][$history_results['ts']] = $updated_data;
            if (empty($merged_array)) {
                $merged_array = $updated_field_names;
            } else {
                $merged_array = array_merge($merged_array, $updated_field_names);
            }
        }
    }

    $all_updated_field_names = array_unique($merged_array);

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
            $upload_value = trim($new_value[1]);
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

function retrieveRecordList($event) {

    $pk = REDCap::getRecordIdField();
    $json_records = REDCap::getData('json', null, $pk, $event);

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
        $html .= "<div class='col-md-3'>
                    <label for='$record'>
                        <input style='vertical-align:middle;' checked type='checkbox' id='$record' name='$record' unchecked>
                        <span style='word-break: break-all; vertical-align:middle'>$record</span>
                    </label>
                 </div>";

    }

    return $html;
}

function getFormList() {

    global $Proj;

    // Make a list of forms in this project
    $forms = "<option value='' disabled selected>Select a form</option>";
    foreach ($Proj->forms as $name => $formInfo) {
        $option = "[$name] " . $formInfo['menu'];
        $forms .= "<option value='" . $name . "'>" . $option . "</option>";
    }

    return $forms;
}

function getEventList($selected_form) {

    global $Proj;

    // Find the events that includes this instrument
    $ncount = 0;
    $event_names = REDCap::getEventNames(true);
    $events = "<option value='' disabled selected>Select an event</option>";

    foreach ($Proj->eventsForms as $event_id => $form_list) {

        if (in_array($selected_form, $form_list)) {
            $ncount++;
            $event_name = $event_names[$event_id];
            $option = "[$event_id] " . $event_name;
            $events .= "<option value='" . $event_id . "'>" . $option . "</option>";
        }
    }

    // If there is only  event that has this form, no need to make the user select the event
    if ($ncount > 1) {
        return null;
    } else {
        return $events;
    }

}

require_once $module->getModulePath() . "pages/selector.php";


