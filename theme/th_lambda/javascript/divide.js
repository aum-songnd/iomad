document.addEventListener("DOMContentLoaded", function() {
    const widthControl = document.getElementById("th_mycustomwidth");
    if (widthControl) {
        document.querySelectorAll(".que.thvstepcluster").forEach(function(cluster) {
            const info = cluster.querySelector(".info");
            const description = cluster.querySelector(".que.description");

            if (info && description) {
                info.parentNode.insertBefore(description, info.nextSibling);
            }
        });
    }
});
