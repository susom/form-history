<?php
namespace Stanford\FormHistory;
/** @var \Stanford\FormHistory\FormHistory $module */

require_once $module->getModulePath() . "classes/CsvFiles.php";
require_once $module->getModulePath() . "classes/XmlFiles.php";

use BenMorel\GsmCharsetConverter\Packer;
use \REDCap;
use \DateTime;

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$selected_form_event = isset($_POST['form_event']) && !empty($_POST['form_event']) ? $_POST['form_event'] : null;
$selected_record = isset($_POST['record']) && !empty($_POST['record']) ? $_POST['record'] : null;
$file_type = isset($_POST['file_type']) && !empty($_POST['file_type']) ? $_POST['file_type'] : null;
$order_fields = isset($_POST['order']) && !empty($_POST['order']) ? $_POST['order'] : null;
$fields_to_include = isset($_POST['include-fields']) && !empty($_POST['include-fields']) ? $_POST['include-fields'] : null;

global $Proj;
$stylesheet = $module->getUrl('pages/selector.css');

$module->emDebug("**** New request ****");
$module->emDebug("selected forms/events: " . json_encode($selected_form_event) . ", selected record: " . $selected_record);
$module->emDebug("File type: " . $file_type . ', order fields: ' . $order_fields . ', fields to include: ' . $fields_to_include);
$module->emDebug("Order fields: " . $order_fields);

$selected_form = '';
$selected_event_id = '';
$primary_key = REDCap::getRecordIdField();

if (!empty($selected_form_event) && !empty($selected_record)) {

    // Find the log table where the history data is stored
    $log_table_name = findLogTable($pid);

    // Loop over each form/event
    if ($selected_record === "--ALL--") {
        $selected_records = retrieveRecordList($primary_key);
    } else {
        $selected_records = [$selected_record];
    }

    $running_total_fields = array();
    $running_total_data = array();

    foreach ($selected_records as $selected_record) {
        foreach ($selected_form_event as $one_form_event) {

            // Split out the form and event
            list($selected_event_name, $selected_form) = splitFormEvent($one_form_event);

            // Find which fields are on this form. If the primary key is on this form, delete it because
            // we automatically add it.
            $fields_on_form = $Proj->forms[$selected_form]['fields'];
            $all_but_pk = array_diff(array_keys($fields_on_form), array($primary_key));
            $form_field_names = array_values($all_but_pk);

            // Find the event id from the event name
            if (!empty($selected_event_name)) {
                $event_names = REDCap::getEventNames(true, true);
                $selected_event_id = array_search($selected_event_name, $event_names);
            } else {
                $selected_event_id = $Proj->firstEventId;
            }

            // Retrieve data from the log table
            list($history_entries, $all_updated_field_names) =
                findHistoryData($pid, $selected_event_id, $selected_event_name, $selected_record,
                    $form_field_names, $log_table_name);

            // See what fields should be included in the download file
            if ($fields_to_include == 'updated-only') {

                // No need to do anything - this is the list of updated fields
                $all_fields = $all_updated_field_names;

            } else if ($fields_to_include == 'filter-fields') {

                // Filter the list to just the fields specified
                $fields_to_include = isset($_POST['filter-fields']) && !empty($_POST['filter-fields']) ? $_POST['filter-fields'] : null;
                $all_fields = filterFieldsToSpecifiedList($fields_to_include);

            } else if ($fields_to_include == 'all-fields') {

                // Include all the fields on the form even if they were never updated
                $all_fields = includeAllFieldsOnForm($all_updated_field_names, $form_field_names);

            }

            // If the user wants the fields to be rearranged to the order they occur in the form, rearrange them.
            if (!empty($order_fields)) {
                $all_fields = orderFieldsToForm($all_fields, $form_field_names);
            }

            // Merge the new data with the old
            if (empty($running_total_fields)) {
                $running_total_fields = $all_fields;
            } else {
                $running_total_fields = array_merge($running_total_fields, $all_fields);
            }
            if (empty($running_total_data)) {
                $running_total_data = $history_entries;
            } else {
                $running_total_data += $history_entries;
            }
        }
    }

    // This is the final list of fields that were updated.
    $all_fields = array_unique($running_total_fields);

    // Do we want to bin the results in a timeframe in case there are multi-page surveys, etc.
    // Should this be a config parameter?
    $binned_results = binResultsOnTS($running_total_data);

    $req_header_fields = array("updated by", "ts data saved", $primary_key, "redcap_event_name");
    if ($file_type == 'xml') {
        $xmlClass = new XmlFiles($binned_results, $req_header_fields, $all_fields);
        $xmlClass->reformatToXml();
        $status = $xmlClass->downloadXmlFile();
   } else {
        $csvClass = new CsvFiles($binned_results, $req_header_fields, $all_fields);
        $csvClass->reformatToCsv();
        $status = $csvClass->downloadCsvFile();
    }

    return;
}

// Put together the form/event list
$forms = getFormEventList();

// Create a drop down for the records
$record_list = retrieveRecordList($primary_key);
$records = generateHTMLRecordList($record_list);

function orderFieldsToForm($all_fields, $form_field_names) {

    global $Proj, $module;

    // Put the fields that we are going to save in the order that they are in the form
    // Not every field on the form are necessarily in the update array.
    $all_reordered_fields = array();
    foreach($form_field_names as $field_name) {

        // See what type of field this is. If it is checkbox field, handle it differently
        $field_type = $Proj->metadata[$field_name]['element_type'];
        if ($field_type == 'checkbox') {

            $field_cmp = $field_name . '___';
            $checkbox_options = array();
            foreach($all_fields as $save_field) {
                if (substr($save_field, 0, strlen($field_cmp)) == $field_cmp) {
                    $option_num = substr($save_field, strlen($field_cmp), strlen($save_field));
                    $checkbox_options[] = $save_field;
                }
            }

            sort($checkbox_options);
            $all_reordered_fields = array_merge($all_reordered_fields, $checkbox_options);

        } else {
            if (in_array($field_name, $all_fields)) {
                $all_reordered_fields[] = $field_name;
            }
        }

    }

    return $all_reordered_fields;

}

function includeAllFieldsOnForm($all_updated_field_names, $form_field_names) {

    global $Proj;

    // Find fields that are on the form but have not had data entered
    // This may not be exactly correct because the updated field array may have checkbox
    // fields in the form of field_name___1 where the form field array will be field_name.
    // At least this is the starting point
    $missing_fields = array_diff($form_field_names, $all_updated_field_names);

    $all_form_fields = $all_updated_field_names;
    foreach ($missing_fields as $field) {

        $field_type = $Proj->metadata[$field]['element_type'];
        if ($field_type == 'checkbox') {

            $checkbox_fields = checkboxOptions($field);
            foreach ($checkbox_fields as $checkbox_option) {
                if (!in_array($checkbox_option, $all_updated_field_names)) {
                    $all_form_fields[] = $checkbox_option;
                }
            }

        } else if ($field_type == 'file') {
            // These fields are upload fields or signature fields
            // We can't download the data in these fields so leave them out.

        } else {
            $all_form_fields[] = $field;
        }
    }

    return $all_form_fields;
}

function filterFieldsToSpecifiedList($selected_field_names) {

    global $module, $Proj;
    $filtered_fields = array();

    $module->emDebug("This are the selected field names: " . $selected_field_names);
    // We just need to make sure these are not checkbox fields.  If they are, then include
    // all the checkbox options
    $list = explode(',', $selected_field_names);
    foreach($list as $field_name) {

        $fname = trim($field_name);
        if ($Proj->metadata[trim($fname)]['element_type'] == 'checkbox') {
            $checkbox_fields = checkboxOptions($fname);
            $filtered_fields = array_merge($filtered_fields, $checkbox_fields);
        } else {
            $filtered_fields[] = $fname;
        }
    }

    $module->emDebug("All returned values: " . json_encode($filtered_fields));
    return $filtered_fields;
}

function checkboxOptions($field) {

    global $module, $Proj;

    // For checkbox fields, we need to reformat the field names to be field_name___0 for each option
    $field_options = array();
    $options = explode('\n', $Proj->metadata[$field]['element_enum']);
    foreach ($options as $option) {
        $key_value = explode(",", $option);
        $module->emDebug("Key " . trim($key_value[0]) . ', value ' . trim($key_value[1]));
        $field_options[] = $field . '___' . trim($key_value[0]);
    }

    return $field_options;
}

function splitFormEvent($selected_form_event) {

    global $module, $Proj;

    // The format is 'ef-' . event name . '-' . form name for longitudinal projects
    // The format is 'ef--' . form name for class projects
    $pieces = explode("-", $selected_form_event);

    return array($pieces[1], $pieces[2]);

}

function reformat_timestamp($timestamp) {

    global $module;
    $d = new DateTime($timestamp);
    return $d->format('Y-m-d H:i:s');

}


function binResultsOnTS($updated_results) {

    $binned_results = $updated_results;
    return $binned_results;
}

function findHistoryData($pid, $selected_event, $selected_event_name, $selected_records,
                         $selected_field_names, $log_table_name) {

    global $module;

    $sql = "select pk, ts, user, data_values from " . $log_table_name .
        " where project_id = " . $pid .
        " and event_id = " . $selected_event .
        " and pk in ('" . $selected_records . "')" .
        " and object_type = 'redcap_data' order by pk, event_id, ts";

    $merged_fields = array();
    $history_data = array();
    $q = db_query($sql);
    // Loop over each entry in the log table
    while ($history_results = db_fetch_assoc($q)) {

        // This entry may have many fields that were updated at the same time, so parse the entries into an array
        list($updated_data, $updated_field_names) = parseUpdatesIntoArray($history_results['data_values'], $selected_field_names);
        //$module->emDebug("Return values: " . json_encode($updated_data));


        // If there are fields that were updated, save fields and values.  Our array looks like
        //  timestamp data saved, record_id, event name => array(updated field 1, updated field 2, etc.)
        if (!empty($updated_data)) {
             $updated_data['USER'] = $history_results['user'];
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
            $npos_end = strpos($new_value[0], ')') - 1;
            $coded_value = substr($new_value[0], $npos+1, ($npos_end-$npos));
            $upload_field = $field_name . '___' . $coded_value;
            if (trim($new_value[1]) == 'checked') {
                $upload_value = 1;
            } elseif (trim($new_value[1]) == 'unchecked') {
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

function retrieveRecordList($primary_key) {

    global $module, $Proj;

    $first_event_id = $Proj->firstEventId;
    $json_records = REDCap::getData('json', null, $primary_key, $first_event_id);
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
        $html .= "<option value='$record'/>" . htmlentities($record) . "</option>";

    }
    $html .= "<option value='--ALL--'/>--ALL--</option>";

    $module->emDebug($records,$html);

    return $html;
}


function getFormEventList() {

    $forms =  \RecordDashboard::renderSelectedFormsEvents();
    return $forms;
}


require_once $module->getModulePath() . "pages/selector.php";

