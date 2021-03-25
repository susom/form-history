# Form History EM
The Form History External Module will extract data from the REDCap DB log tables and download it
in a csv file to your local machine. The data is formatted in a way that it can be re-uploaded to
the REDCap project.

The data retrieved from the log files include all data that has been saved for fields on the form. If
there are checkboxes or radio buttons on the form where options were deleted, those options will
be included in the file.  If there were other field types on the form that were deleted, those fields
will not be retrieved since they are no longer associated with the form.

This module does not handle file upload fields or signature fields.

In addition to form data, the first column of the file holds the user who made the change.  The
second column holds the timestamp when the change was made.  These two columns need to be
deleted before re-uploading the file.


## Data Format Options
There are several options you can select when downloading data.  You can include only fields
that have had data saved in them.  This is the default and the order of the fields is based on
the order the data was saved.

There is an option to re-order fields to be in the order they appear on the form currently.
The data file can also include all fields on the current form regardless of whether or not the
field was saved with data.  This case is helpful when overwriting a populated form to ensure all
fields are cleared when they currently hold data.  When uploading the data, the option to
overwrite current data with blank values should be selected.

If only specific fields are desired, you can specify which fields should be included in the
download file.

## File Options
By default, a .csv file is created.  The default file name is history_data.csv but that can
be changed when saving the file locally.  Since it may be difficult to tell which fields have
changed from one row to the next, another option is available. Currently xml can be selected and
the file can be opened with Excel. This file highlights the cell red when a value has been
modified from the previous save.

The xml file can be saved in csv format and then can be used for upload.
