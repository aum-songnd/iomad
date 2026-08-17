(function () {
    var body = document.body;
    var isQuizAttemptPage = location.pathname.indexOf('/mod/quiz/attempt.php') !== -1 ||
        (body && body.id === 'page-mod-quiz-attempt');

    if (!isQuizAttemptPage) {
        return;
    }

    var userAgent = navigator.userAgent;
    var isEdge = /\bEdg\//.test(userAgent);
    var isChrome = /\bChrome\//.test(userAgent) &&
        navigator.vendor === 'Google Inc.' &&
        !/\bOPR\//.test(userAgent) &&
        !isEdge;

    if (!isChrome && !isEdge) {
        return;
    }

    function isBackForwardNavigation(event) {
        var navigationEntries = [];

        if (performance.getEntriesByType) {
            navigationEntries = performance.getEntriesByType('navigation');
        }

        return event.persisted ||
            (navigationEntries.length && navigationEntries[0].type === 'back_forward') ||
            (performance.navigation && performance.navigation.type === 2);
    }

    window.addEventListener('pageshow', function (event) {
        if (isBackForwardNavigation(event)) {
            location.reload();
        }
    });
}());
