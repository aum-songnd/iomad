/* global videojs, M */

define([], function () {

    /**
     * Detect Moodle App
     *
     * @returns {boolean}
     */
    function isMoodleApp() {
        return typeof window.isMoodleApp !== 'undefined' && window.isMoodleApp;
    }

    /**
     * Replace MP4 <video> with Video.js DRM player
     *
     * @param {HTMLElement} video The <video> element
     * @param {number} index Index of the video on page
     */
    function replaceVideo(video, index) {
        var drmSrc = video.getAttribute('data-drm-src');
        var drmType = video.getAttribute('data-drm-type') || 'application/dash+xml';
        if (!drmSrc) {
            return;
        }

        // Prevent double replace
        if (video.getAttribute('data-drm-replaced') === '1') {
            return;
        }
        video.setAttribute('data-drm-replaced', '1');
        var oldId = video.getAttribute('id');

        var width = video.getAttribute('width') || video.style.maxWidth || '100%';
        var id = oldId;

        var poster = video.getAttribute('poster') || '';
        var classes = video.getAttribute('class') || '';    

        var wrapper = document.createElement('div');
        wrapper.className = 'thvstep-drm-wrapper';

        wrapper.innerHTML =
            '<video-js ' +
                'id="' + id + '" ' +
                'class="video-js vjs-default-skin ' + classes + '" ' +
                'controls ' +
                'preload="auto" ' +
                (poster ? 'poster="' + poster + '" ' : '') +
                'style="width:' + width + ';">' +
            '</video-js>';

        video.parentNode.replaceChild(wrapper, video);

        var player = videojs(id, {
            playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2],
            fluid: true
        });

        if (player.eme) {
            player.eme();
        }

        player.src({
            src: drmSrc,
            type: drmType,
            keySystems: {
                'org.w3.clearkey':
                    M.cfg.wwwroot + '/question/type/thvstepcluster/dash_clear_key.php'
            }
        });
    }

    /**
     * Init entry point
     */
    function init() {
        // Moodle App → keep MP4
        if (isMoodleApp()) {
            return;
        }

        if (typeof videojs === 'undefined') {
            return;
        }

        var videos = document.querySelectorAll('video[data-drm-src]');
        if (!videos.length) {
            return;
        }

        videos.forEach(function (video, index) {
            replaceVideo(video, index);
        });
    }

    return {
        init: init
    };
});
