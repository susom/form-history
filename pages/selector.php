
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <title>Retrieve History Data</title>
    <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">

            <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet" />
            <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
</head>
<body>

<h1>Retrieve History Data Setup</h1>
<div>
    <h4>This is the Setup Page to select which instrument that history data will be pulled from and put into a csv file.</h4>
</div>
<br>

    <div class="container">

        <div>
            <label><b>Select the form that you want to retrieve history data:</b></label>
        </div>
        <select name="forms" id="forms" onchange="getSelectedForm()">
            <?php echo $forms; ?>
        </select>

        <br><br>

        <div id="event_list_id" hidden>
            <div id="event_id">
                <label><b>There is more than one arm in this project, please select the arm you want to pull data from:</b></label>
            </div>
            <select name="events" id="events">
            </select>
        </div>

        <br><br>

        <div id="record_list_id" hidden>
            <div id="record_id">
                <label><b>Select the records in the project to retrieve history data:</b></label>
            </div>
            <div name="records" id="records">
            </div>
        </div>

        <br><br>

        <button type="submit" id="submit" hidden>Retrieve Data</button>

    </div>  <!-- END CONTAINER -->
</body>
</html>

<script>
    document.getElementById("forms").onchange = function() {getSelectedForm()};
    document.getElementById("submit").onclick = function() {retrieveData()};

    function getSelectedForm() {

    var selected_form = document.getElementById("forms").value;
    edt.getEventList(selected_form);
}

    function retrieveData() {

    var selected_form = document.getElementById("forms").value;
    var selected_event = document.getElementById("events").value;
    edt.retrieveData(selected_form, selected_event);
}


    var edt = edt || {};

    edt.getEventList = function (selected_form) {

    // Make the API call to see if there are multiple arms in this project
    $.ajax({
        type: "POST",
        datatype: "html",
        async: false,
        data: {
            "action"     : "events",
            "form"       : selected_form
        },
        success:function(html) {
        },
        error:function(jqXhr, textStatus, errorThrown) {
            console.log("Error in get_arms request: ", jqXHR, textStatus, errorThrown);
        }

    }).done(function (html) {
        alert("Return from find events: " + html);
        if (html === null) {
            document.getElementById("event_list_id").style.display = "inline";
            document.getElementById("events").innerHTML = html;
        } else {
            edt.getRecords(selected_form, null);
        }
    }).fail(function (jqXHR, textStatus, errorThrown) {
        console.log("Failed to retrieve list of arms in getArmsList");
    });

};

    edt.getRecords = function (selected_form, selected_event) {

    // Make the API call to see if there are multiple arms in this project
    $.ajax({
        type: "POST",
        datatype: "html",
        async: false,
        data: {
            "action"     : "records",
            "form"       : selected_form,
            "event"      : selected_event
        },
        success:function(html) {
        },
        error:function(jqXhr, textStatus, errorThrown) {
            console.log("Error in get_arms request: ", jqXHR, textStatus, errorThrown);
        }

    }).done(function (html) {
        if (html === null) {
            document.getElementById("record_list_id").style.display = "inline";
            document.getElementById("records").innerHTML = html;
        }

        document.getElementById("submit").style.display = "inline";
    }).fail(function (jqXHR, textStatus, errorThrown) {
        console.log("Failed to retrieve list of arms in getArmsList");
    });

};

    edt.retrieveData = function (selected_form, selected_event, records) {

    // Make the API call to retrieve the list of record_ids in this project
    $.ajax({
        type: "POST",
        datatype: "html",
        async: false,
        data: {
            "action"     : "records",
            "form"       : selected_form,
            "event"      : selected_event,
            "records"    : records
        },
        success:function(html) {
        },
        error:function(jqXhr, textStatus, errorThrown) {
            console.log("Error in get_arms request: ", jqXHR, textStatus, errorThrown);
        }

    }).done(function (html) {

    }).fail(function (jqXHR, textStatus, errorThrown) {
        console.log("Failed to retrieve list of arms in getArmsList");
    });

};


</script>
