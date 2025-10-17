jQuery(document).ready(function($) {
    // Handle month navigation via AJAX
    $('.calendar-navigation button').on('click', function(e) {
        e.preventDefault();
        var month = $(this).data('month');
        loadCalendarMonth(month);
    });

    function loadCalendarMonth(month) {
        $.ajax({
            url: church_events_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_calendar_month',
                month: month,
                nonce: church_events_ajax.nonce
            },
            beforeSend: function() {
                $('.church-events-calendar').addClass('loading');
            },
            success: function(response) {
                if (response.success) {
                    $('.church-events-calendar').replaceWith(response.data.html);
                    history.pushState({}, '', updateUrlParameter(window.location.href, 'month', month));
                }
            },
            complete: function() {
                $('.church-events-calendar').removeClass('loading');
            }
        });
    }

    // Handle event click for popup
    $(document).on('click', '.calendar-event', function(e) {
        e.stopPropagation();
        var eventId = $(this).data('event-id');
        showEventPopup(eventId, e);
    });

    // Handle "more events" click for modal
    $(document).on('click', '.more-events', function(e) {
        e.stopPropagation();
        var date = $(this).closest('.calendar-day').data('date');
        showDayEventsModal(date);
    });

    // Close popup when clicking outside
    $(document).on('click', function() {
        $('#calendar-event-popup').hide();
    });

    // Close modal when clicking close button or outside
    $(document).on('click', '.close-modal, .modal-overlay', function() {
        $('.modal-overlay').remove();
    });

    function showEventPopup(eventId, event) {
        var popup = $('#calendar-event-popup');
        
        $.ajax({
            url: church_events_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_event_details',
                event_id: eventId,
                nonce: church_events_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    popup.html(response.data.html);
                    positionPopup(popup, event);
                    popup.show();
                }
            }
        });
    }

    function showDayEventsModal(date) {
        $.ajax({
            url: church_events_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_day_events',
                date: date,
                nonce: church_events_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('body').append('<div class="modal-overlay">' + response.data.html + '</div>');
                }
            }
        });
    }

    function positionPopup(popup, event) {
        var windowWidth = $(window).width();
        var windowHeight = $(window).height();
        var popupWidth = popup.outerWidth();
        var popupHeight = popup.outerHeight();
        var left = event.pageX;
        var top = event.pageY;

        if (left + popupWidth > windowWidth) {
            left = windowWidth - popupWidth - 20;
        }
        if (top + popupHeight > windowHeight) {
            top = windowHeight - popupHeight - 20;
        }

        popup.css({
            left: left + 'px',
            top: top + 'px'
        });
    }

    function updateUrlParameter(url, key, value) {
        var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
        var separator = url.indexOf('?') !== -1 ? "&" : "?";
        if (url.match(re)) {
            return url.replace(re, '$1' + key + "=" + value + '$2');
        }
        else {
            return url + separator + key + "=" + value;
        }
    }

    // Auto-open day modal and highlight selected day when in Day view
    if (typeof church_events_ajax !== 'undefined' && church_events_ajax.view === 'day' && church_events_ajax.day) {
        var selectedDay = church_events_ajax.day;
        var $cell = $('.calendar-day[data-date="' + selectedDay + '"]');
        if ($cell.length) {
            $cell.addClass('selected');
        }
        showDayEventsModal(selectedDay);
    }
});