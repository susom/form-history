
<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <title>Form History Data</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous"/>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css"/>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.7.14/css/bootstrap-datetimepicker.min.css">
        <link rel="stylesheet" href="<?php echo $stylesheet; ?>">

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.15.1/moment.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.7.14/js/bootstrap-datetimepicker.min.js"></script>
    </head>
    <body>

        <div class="container">
            <div class="row pl-1">
                <h3>
                    Form History Setup Page
                </h3>
            </div>

            <form method="post" action="" id="data-request">

                <!-- Display list of forms in events -->
                <div class="row p-1">
                    <?php echo $forms; ?>
                </div>

                <!-- Select dropdown with search so user can select a record -->
                <div class="row newsection">
                    <div>Enter or select the record you want to pull data for:</div>
                </div>
                <select autocomplete id="record_list" name ="record">
                    <?php echo $records; ?>
                </select>

                <!-- Select csv or xml file -->
<!--
                <div class="row newsection">
                    <div>Select the file type you would like downloaded</div>
                </div>
                <div>
                    <input type="radio" name="file_type" value="csv" checked="checked">
                    <label>.csv file type</label>
                </div>
                <div>
                    <input type="radio" name="file_type" value="xml">
                    <label>.xml file type</label>
                </div>
-->
                <div class="row">
                    <table id="option-table">
                        <tr>
                            <th class="newsection">File type</th>
                            <th class="newsection">Reorder fields to form</th>
                            <th class="newsection">Fields to download</th>
                            <th class="newsection"></th>
                        </tr>
                        <tr>
                            <td>
                                <div>
                                    <input type="radio" name="file_type" value="csv" checked="checked">
                                    <label>.csv file type</label>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <input type="checkbox" name="order">
                                    <label>Reorder fields</label>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <input type="radio" name="include-fields" value="updated-only" checked>
                                    <label>Updated fields only</label>
                                </div>
                            </td>
                            <td>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div>
                                    <input type="radio" name="file_type" value="xml">
                                    <label>.xml file type</label>
                                </div>
                            </td>
                            <td>
                            </td>
                            <td>
                                <div>
                                    <input type="radio" name="include-fields" value="all-fields">
                                    <label>All fields on form</label>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <label>Comma seperated field list (field1, field2,...)</label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                            </td>
                            <td>
                            </td>
                            <td>
                                <div>
                                    <input type="radio" name="include-fields" value="filter-fields">
                                    <label>Specified fields only  --></label>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <input type="text" id="filter-fields" name="filter-fields">
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Form submit button -->
                <div class="row p-1 pb-5">
                    <input class="btn-primary mt-5" type="button" id='btnsubmit' onclick="submitForm()" value="Retrieve data and save to file">
                </div>

            </form>

        </div>  <!-- END CONTAINER -->

    </body>
</html>

<script>

    $( document ).ready(function() {

        // The checkboxes created from REDCap do not have a value set so no value comes through the form submit
        // create a value attribute to be the same as the id.
        var all_ckbx = $('#choose_select_forms_events_div_sub input[id^="ef-"]');
        $.each(all_ckbx, function (key, val) {
            var value = $(val).attr('id');
            $(val).attr("value", value);
            $(val).attr("name", "form_event[]")

        });

        $("#select_links_forms button").remove();
        $("#select_links_forms a").first().css("margin-left", "5px");
        var title = $("#choose_select_forms_events_div_sub div").first();
        title.css("font-size", "15px");
        title.html("Select instruments/events to retrieve history data for:");
    });

    function selectAllInEvent(event_name,ob) {
        $('#choose_select_forms_events_div_sub input[id^="ef-'+event_name+'-"]').prop('checked',$(ob).prop('checked'));
    }

    function selectAllFormsEvents(select_all) {
        $('#choose_select_forms_events_div_sub input[type="checkbox"]').prop('checked',select_all);
    }

    function submitForm() {
        var btn = $('#data-request');
        btn.submit();
        btn[0].reset();
    }

</script>
