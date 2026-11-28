/*
 * Invoice settings page.
 *
 * The only thing this page does on load is make the settings panes equal
 * height, so the grid reads as rows rather than a ragged column. It lives here
 * rather than in an inline <script> in the view: the page is served under a
 * CSP that forbids inline script, and an inline block is stripped by it -
 * which is one reason the panes used to sit at uneven heights.
 */
$(function () {
    var $grid = $('.settings-inner-page');
    if ($grid.length && typeof $.fn.eqHeight === 'function') {
        $grid.eqHeight('.col-settings');
    }
});
