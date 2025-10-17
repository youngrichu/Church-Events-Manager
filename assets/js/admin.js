jQuery(function($){
  // Toggle recurring options section
  $('input[name="is_recurring"]').on('change', function(){
    $('.recurring-options').toggle(this.checked);
  }).trigger('change');

  // Toggle visibility of specific "Ends" controls
  function updateEndControls(){
    var val = $('#recurrence_ends').val();
    $('.recurrence-end-date').toggle(val === 'on_date');
    $('.recurrence-end-count').toggle(val === 'after_count');
  }
  $('#recurrence_ends').on('change', updateEndControls);
  updateEndControls();

  // Cache date/time inputs and controls
  var $startDate = $('#event_date');
  var $startTime = $('#event_time');
  var $endDate = $('#event_end_date');
  var $endTime = $('#event_end_time');
  var $isAllDay = $('#is_all_day');
  var $hideEndTime = $('#hide_end_time');
  var $sameDay = $('#same_day_event');

  // Inline error message placed under the End Date field
  var $errorEl = $('#cem-end-error');
  if(!$errorEl.length){
    $errorEl = $('<div id="cem-end-error" class="cem-error-message" style="display:none;color:#d63638;margin-top:6px;"></div>')
      .text('End date/time must be after start date/time.');
    var $endDateField = $('#end_date_field');
    if($endDateField.length){
      $endDateField.append($errorEl);
    } else if($endDate.length){
      $endDate.after($errorEl);
    }
  }

  function toTimestamp(dateStr, timeStr){
    if(!dateStr) return NaN;
    var t = (timeStr && timeStr.length) ? timeStr : '00:00';
    return Date.parse(dateStr + 'T' + t);
  }

  function showError(show){
    if(show){
      $errorEl.show();
      $endDate.addClass('cem-input-error');
      if($endTime.length && $('#end_time_field').is(':visible')) $endTime.addClass('cem-input-error');
    } else {
      $errorEl.hide();
      $endDate.removeClass('cem-input-error');
      if($endTime.length) $endTime.removeClass('cem-input-error');
    }
  }

  // Date/time UI toggles for new grid layout
  function updateAllDayUI(){
    var isAllDay = $isAllDay.is(':checked');
    $('#start_time_field').toggle(!isAllDay);
    $('#end_time_field').toggle(!isAllDay && !$hideEndTime.is(':checked'));
    // When all-day, clear times for clarity
    if(isAllDay){
      $startTime.val('');
      $endTime.val('');
    }
  }
  $isAllDay.on('change', function(){
    updateAllDayUI();
    validateEndAfterStart();
  });

  // Hide end time toggle
  $hideEndTime.on('change', function(){
    $('#end_time_field').toggle(!$isAllDay.is(':checked') && !$hideEndTime.is(':checked'));
    validateEndAfterStart();
  });

  // Same day event toggle: keep end date in sync with start date
  function syncSameDay(){
    if($sameDay.is(':checked')){
      var sd = $startDate.val();
      if(sd) $endDate.val(sd);
      $endDate.attr('min', sd || '');
    }
  }
  $sameDay.on('change', function(){
    syncSameDay();
    validateEndAfterStart();
  });

  // Keep end date not earlier than start date (native date inputs)
  $startDate.on('change', function(){
    var startDate = $(this).val();
    if(startDate){ $endDate.attr('min', startDate); }
    syncSameDay();
    validateEndAfterStart();
  }).trigger('change');

  function validateEndAfterStart(){
    var sd = $startDate.val();
    var st = $isAllDay.is(':checked') ? '00:00' : $startTime.val();
    var ed = $endDate.val();
    // End time logic: if all-day, treat as 23:59, else use end time when visible; otherwise 00:00
    var endTimeVisible = $('#end_time_field').is(':visible');
    var et = $isAllDay.is(':checked') ? '23:59' : (endTimeVisible && $endTime.length ? $endTime.val() : '00:00');
    if(!ed){ showError(false); return true; }
    var sTs = toTimestamp(sd, st);
    var eTs = toTimestamp(ed, et);
    var valid = (isNaN(sTs) || isNaN(eTs)) ? true : (eTs >= sTs);
    showError(!valid);
    return valid;
  }

  if($startTime.length) $startTime.on('change', validateEndAfterStart);
  $endDate.on('change', validateEndAfterStart);
  if($endTime.length) $endTime.on('change', validateEndAfterStart);

  // Prevent submission in classic editor when invalid
  var $postForm = $('#post');
  if($postForm.length){
    $postForm.on('submit', function(e){
      if(!validateEndAfterStart()){
        e.preventDefault();
      }
    });
  }

  // Initial setup
  updateAllDayUI();
  validateEndAfterStart();
});