function cc_video() {
    function videoPlayItem(video) {
        if (video.parentElement && video.parentElement.classList.contains("cc_video_container")) {
            return;
        }
        const mainDiv = document.createElement("div");
        mainDiv.className = "cc_video_container";
        Object.assign(mainDiv.style, {
            margin: "20px auto 30px auto",
            maxWidth: "650px",
            width: "100%",
            textAlign: "center"
        });
        Object.assign(video.style, {
            margin: "0 auto",
            display: "block",
            maxWidth: "650px",
            width: "100%",
            height: "auto",
            borderRadius: "6px"
        });
        video.parentNode.insertBefore(mainDiv, video);
        mainDiv.appendChild(video);
        video.controls = true;
        video.playsInline = true;

        video.addEventListener("play", () => {
            const allVideos = document.getElementsByTagName("video");
            for (const e of allVideos) {
                if (e !== video && !e.paused) {
                    e.pause();
                }
            }
        });

        const caption = video.getAttribute("caption");
        if (caption) {
            const captionDiv = document.createElement("div");
            captionDiv.className = "cc_video_caption";
            Object.assign(captionDiv.style, {
                margin: "8px auto 25px auto",
                display: "block",
                maxWidth: "650px",
                width: "100%",
                textIndent: "2em",
                textAlign: "left",
                fontSize: "15px",
                lineHeight: "1.6",
                color: "rgba(0, 0, 40, 0.85)"
            });
            captionDiv.innerHTML = caption;
            mainDiv.appendChild(captionDiv);
        }
    }

    const videos = (typeof cc !== "undefined" && cc.player && cc.player.length > 0)
        ? cc.player
        : document.getElementsByTagName("video");

    for (const v of Array.from(videos)) {
        videoPlayItem(v);
    }
}
