jQuery(document).ready(function($) {
    // Initialize datepickers
    $('.datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        firstDay: 1
    });

    // Toggle recurring options
    $('input[name="is_recurring"]').change(function() {
        $('.recurring-options').toggle(this.checked);
    });

    // Ensure end date is after start date
    $('#event_date').change(function() {
        var startDate = $(this).datepicker('getDate');
        $('#event_end_date').datepicker('option', 'minDate', startDate);
    });
}); 