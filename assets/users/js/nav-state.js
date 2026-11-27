/*
 * Applies the saved navigation state before the body paints.
 *
 * Loaded in <head>. If this ran at the end of the document instead, a collapsed
 * menu would render open for a frame and then snap shut on every page load. The
 * class is put on <html> because <body> does not exist yet at this point.
 */
(function () {
    var root = document.documentElement;
    var narrow = window.matchMedia && window.matchMedia('(max-width: 991px)').matches;
    var collapsed = true;                       // narrow screens start closed
    if (!narrow) {
        try {
            collapsed = window.localStorage.getItem('av-nav-collapsed') === '1';
        } catch (e) {
            collapsed = false;                  // storage blocked: show the menu
        }
    }
    if (collapsed) {
        root.className += ' av-nav-collapsed';
    }
})();
