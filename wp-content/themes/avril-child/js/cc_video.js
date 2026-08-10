function cc_video () {
    const media = [];
    const ratio = 1080 / 1934;
    if (window.innerWidth >= window.innerHeight) {
        media.width = window.innerWidth;
        media.height = window.innerHeight;
    } else {
        media.width = window.innerWidth;
        media.height = window.innerWidth * ratio;
    };
    function onePeopleOneVote(video) {
        Object.assign(video, { controls: true, autoplay: true, loop: false, muted: false, playsInline: true, width: 1934, height: 1080 });
        Object.assign(video.style, { position: "fixed", left: "0px", top: "0px", width: `${media.width}px`, height: `${media.height}px`, objectFit: "cover", zIndex: "999999" });
        document.body.appendChild(video);
        document.addEventListener("click", async () => {
            await video.requestFullscreen();
            video.muted = false;
            video.play();
        }, { once: true });
        video.addEventListener("ended", async () => {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            }
            video.remove();
        });
    }
    onePeopleOneVote(cc.player[0]);
}
