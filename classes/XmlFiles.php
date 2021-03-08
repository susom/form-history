<?php
namespace Stanford\FormHistory;
/** @var \Stanford\FormHistory\FormHistory $module */


/**
 *
 * Class XmlFiles
 * @package Stanford\FormHistory
 */

class XmlFiles {

    private $data, $binned_results, $req_header_fields, $all_updated_field_name;

    public function __construct($binned_results, $req_header_fields, $all_updated_field_names) {
        global $module;

        $this->binned_results              = $binned_results;
        $this->req_header_fields           = $req_header_fields;
        $this->all_updated_field_names     = $all_updated_field_names;
    }

    /**
     *
     * @return bool|mixed - true when data was successfully sent to CureSMA
     */
    public function reformatToXml() {

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
                        $no_formatting = array();

                        // These are the 4 required header fields that each row is required to have
                        $one_row = array("user" => "<td>".$fields['USER']."</td>",
                                        "ts" => "<td>".$timestamp_reformatted."</td>",
                                        "rid" => "<td>".$record_id."</td>",
                                        "eid" => "<td>".$event_name."</td>");
                        $no_formatting = $last_save[$record_id][$event_name];

                        // now loop over update fields and check the no_formatting array which only has the value without formatting
                        foreach ($this->all_updated_field_names as $field) {

                            if (array_key_exists($field, $fields)) {

                                // If the field is in the update, make the cell red
                                if ($no_formatting[$field] != $fields[$field]) {
                                    $one_row[$field] = '<td style="background-color:#ffcccb">' . $fields[$field] . '</td>';
                                } else {
                                    $one_row[$field] = '<td>' . $fields[$field] . '</td>';
                                }
                                $no_formatting[$field] = $fields[$field];

                            } else if (array_key_exists($field, $no_formatting)) {

                                // If this field was not updated but had a previous value, set the previous value
                                $one_row[$field] = '<td>' . $no_formatting[$field] . '</td>';

                            } else {

                                // If this field doesn't have any entries, set it to null
                                $one_row[$field] = '<td>' . null . '</td>';
                                $no_formatting[$field] = null;

                            }
                        }

                        // Save this row
                        $this->data[] = array_values($one_row);
                        $last_save[$record_id][$event_name] = $no_formatting;
                    }
                }
            }
    }

    /**
     *
     * @return array|bool|null
     */
    function downloadXMLFile()
    {
        global $module;

        // Write out each row of the csv
        $nrow = 0;
        $xml = '<table style="font-family:Ariel; font-size: 12px;">';
        foreach ($this->data as $row) {
            $nrow++;
            $xml .= '<tr>';

            foreach ($row as $column) {
                if ($nrow == 1) {
                    $xml .= '<th>' . $column . '</th>';
                } else {
                    $xml .= $column;
                }
            }
            $xml .= '</tr>';
        }
        $xml .= '</table>';

        header ('Content-Description: File Transfer');
        header ("Content-Type: application/vnd.ms-excel" );
        header ("Content-Disposition: attachment; filename=history_data.xls" );

        $fp = fopen('php://output', 'w');
        if ($fp !== false) {
            fwrite($fp, $xml);
        }

        // Close the stream
        fclose($fp);
        ob_end_flush();

        return true;
    }

}
