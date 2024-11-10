jQuery(document).ready(function($) {
    // Handle hover state for mobile devices
    if (window.matchMedia('(max-width: 768px)').matches) {
        $('.event-item').on('click', function(e) {
            if (!$(e.target).closest('.event-detail-card').length && 
                !$(e.target).closest('.event-link').length) {
                e.preventDefault();
                $('.event-detail-card').not($(this).find('.event-detail-card')).slideUp();
                $(this).find('.event-detail-card').slideToggle();
            }
        });
    }

    // Adjust card position if it goes off screen
    function adjustCardPosition() {
        $('.event-detail-card').each(function() {
            var card = $(this);
            var cardRect = this.getBoundingClientRect();
            var windowHeight = window.innerHeight;

            if (cardRect.bottom > windowHeight) {
                var adjustment = cardRect.bottom - windowHeight + 20;
                card.css('margin-top', -adjustment + 'px');
            }
        });
    }

    // Adjust on hover
    $('.event-item').on('mouseenter', adjustCardPosition);

    // Adjust on window resize
    $(window).on('resize', adjustCardPosition);
}); 