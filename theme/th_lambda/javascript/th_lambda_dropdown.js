$(document).on('click', '.dropdown-toggle', function (e) {
    const $dropdown = $(this).closest('.dropdown');

    requestAnimationFrame(() => {
        if ($dropdown.hasClass('show') || $dropdown.find('.dropdown-menu').is(':visible')) {
            const event = $.Event('show.bs.dropdown', { relatedTarget: this });
            $dropdown.trigger(event);
        }
    });
});
