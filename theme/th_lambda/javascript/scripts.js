(function($) {
    $(document).ready(function(){
        function getBrowserName() {
            var userAgent = navigator.userAgent;
        
            // Kiểm tra chuỗi user agent để xác định trình duyệt
            if (/edg/i.test(userAgent)) {
                return "Edge";
            } else if (/opr\//i.test(userAgent)) {
                return "Opera";
            } else if (/chrome|chromium|crios/i.test(userAgent)) {
                return "Chrome";
            } else if (/firefox|fxios/i.test(userAgent)) {
                return "Firefox";
            } else if (/safari/i.test(userAgent)) {
                return "Safari";
            } else {
                return "Unknown";
            }
        }

        function getOS() {
            var userAgent = navigator.userAgent;
            var platform = navigator.platform;
            var os = "Unknown";
        
            if (/Win/.test(platform)) {
                os = "Windows";
            } else if (/Mac/.test(platform)) {
                os = "macOS";
            } else if (/Linux/.test(platform)) {
                os = "Linux";
            } else if (/Android/.test(userAgent)) {
                os = "Android";
            } else if (/iOS|iPhone|iPad|iPod/.test(userAgent)) {
                os = "iOS";
            }
        
            return os;
        }

        function getCurrentTime() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
        
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }

        // Close the error form container
        $('#closeButton').click(function(event) {
            $('#errorFormContainer').css('display', 'none');
            // Xóa file đã chọn trong input file
            $('#uploadScreenshot').val('');
        });

        if (window.location.href.includes("/mod/scorm/player.php")) {
            const lastBreadcrumbLink = document.querySelector('.breadcrumb li:last-child a');
            if (lastBreadcrumbLink) {
                hrefValue = lastBreadcrumbLink.getAttribute('href');
            } else {
                hrefValue = window.location.href;
            }
        } else {
            hrefValue = window.location.href;
        }

        $('.error-button').click(async function(event) {
            if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
                try {
                    // const options = {audio: true, video: true};
                    // const displaySurface = 'monitor';
                    // options.video = {displaySurface};
                    // Capture the screen
                    const stream = await navigator.mediaDevices.getDisplayMedia({ preferCurrentTab: true });

                    const video = document.createElement('video');
                    video.srcObject = stream;
                    video.play();

                    // Chờ video bắt đầu phát
                    await new Promise((resolve) => {
                        video.onplaying = resolve;
                    });

                    if (typeof ImageCapture !== 'undefined') {

                        const track = stream.getVideoTracks()[0];
                        const imageCapture = new ImageCapture(track);
                        const bitmap = await imageCapture.grabFrame();
                        track.stop();
                
                        // Draw the captured frame onto a canvas
                        const canvas = document.createElement('canvas');
                        canvas.width = bitmap.width;
                        canvas.height = bitmap.height;
                        const context = canvas.getContext('2d');
                        context.drawImage(bitmap, 0, 0, bitmap.width, bitmap.height);
                        // Convert the canvas to a data URL
                        imgData = canvas.toDataURL('image/png');
                    } else {
                        // Sử dụng phương pháp canvas nếu ImageCapture không được hỗ trợ
                        const canvas = document.createElement('canvas');
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        const context = canvas.getContext('2d');
                        context.drawImage(video, 0, 0, canvas.width, canvas.height);
                        imgData = canvas.toDataURL('image/png');

                        // Dừng tất cả các track sau khi chụp màn hình xong
                        const tracks = stream.getTracks();
                        tracks.forEach(track => track.stop());
                    }
             
                    const currentUrl = hrefValue;
                    const osName = getOS();
                    const browserName = getBrowserName();
            
                    // Update the image element and other DOM elements
                    const imgElement = document.getElementById('screenshotImage');
                    imgElement.src = imgData;
                    imgElement.style.display = 'block';
            
                    document.getElementById('screenshotData').value = imgData;
                    document.getElementById('currentUrl').value = currentUrl;
                    document.getElementById('browserName').value = browserName;
                    document.getElementById('osName').value = osName;
                    document.getElementById('errorFormContainer').style.display = 'flex';
                } catch (error) {
                    console.error('Error capturing screen:', error);
                }
            } else {
                document.getElementById('uploadScreenshot').click();
            }
        });

        document.getElementById('uploadScreenshot').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgData = e.target.result;
                    const currentUrl = hrefValue;
                    const osName = getOS();
                    const browserName = getBrowserName();

                    // Update the image element and other DOM elements
                    const imgElement = document.getElementById('screenshotImage');
                    imgElement.src = imgData;
                    imgElement.style.display = 'block';

                    document.getElementById('screenshotData').value = imgData;
                    document.getElementById('currentUrl').value = currentUrl;
                    document.getElementById('browserName').value = browserName;
                    document.getElementById('osName').value = osName;
                    document.getElementById('errorFormContainer').style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        });
    });
})(jQuery);
