<?php
namespace Stanford\FormHistory;
/** @var \Stanford\FormHistory\FormHistory $module */


/**
 * The class will

 * Class CsvFiles
 * @package Stanford\FormHistory
 */

class CsvFiles {

    private $data, $binned_results, $req_header_fields, $all_updated_field_name;

    public function __construct($binned_results, $req_header_fields, $all_updated_field_names) {
        global $module;

        $this->binned_results              = $binned_results;
        $this->req_header_fields           = $req_header_fields;
        $this->all_updated_field_names     = $all_updated_field_names;
    }

    public function reformatToCsv() {

        global $module;
        $status = true;
        $this->data = array();

        // First add the header in the first row
        $header = array_merge($this->req_header_fields, $this->all_updated_field_names);
        $this->data[] = array_values($header);

        // Extract timestamps and sort them so we go from older to newer
        $all_update_timestamps = array_keys($this->binned_results);
        sort($all_update_timestamps);

        // Next add each updated row.  We need to put the data in the same order as the headers
        // To make the file as close to uploadable as possible, we are putting data in the following order:
        // 1) timestamp of update, 2) record_id, 3) redcap_event_name, 4) updated data ....
        $last_save = array();
        foreach($all_update_timestamps as $timestamp) {
            foreach($this->binned_results[$timestamp] as $record_id => $record) {
                foreach($record as $event_name => $fields) {

                    $timestamp_reformatted = reformat_timestamp($timestamp);
                    $one_row = array();

                    // If there was a previous save, start with that save and update the fields that have a save
                    // so that we have running list of what the form looked like at the time of save.
                    if (is_null($last_save[$record_id][$event_name])) {

                        // These are the 3 required header fields that each row is required to have
                        $one_row = array("user" => $fields['USER'], "ts" => $timestamp_reformatted, "rid" => $record_id, "eid" => $event_name);
                    } else {
                        $one_row = $last_save[$record_id][$event_name];
                        $one_row['user'] = $fields['USER'];
                        $one_row["ts"] = $timestamp_reformatted;
                        $one_row["eid"] = $event_name;
                    }

                    // now loop over update fields
                    foreach ($this->all_updated_field_names as $field) {
                        if (array_key_exists($field, $fields)) {
                            $one_row[$field] = $fields[$field];
                        } else if (!array_key_exists($field, $one_row)) {
                            $one_row[$field] = null;
                        }
                    }

                    // Save this row
                    $this->data[] = array_values($one_row);
                    $last_save[$record_id][$event_name] = $one_row;
                }
            }
        }
    }

    /**
     *
     * @return array|bool|null
     */
    public function downloadCsvFile() {

        global $module;

        // Open the stream to the file
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=history_data.csv');

        $fp = fopen('php://output', 'w');
        if ($fp !== false) {

            // Write out each row of the csv
            foreach ($this->data as $row) {
                fputcsv($fp, $row);
            }

            // Close the stream
            fclose($fp);
            ob_end_flush();

            return true;

        } else {
            return false;
        }
    }


}
