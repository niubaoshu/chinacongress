/**
 * ChinaCongress 全站短视频自动包装与互斥播放脚本
 */
(function() {
	function initVideos() {
		var videos = document.querySelectorAll('video');
		if (!videos || videos.length === 0) return;

		videos.forEach(function(video) {
			video.controls = true;
			video.playsInline = true;

			// 互斥播放：当一个视频播放时，暂停其他正在播放的视频
			if (!video.dataset.ccMutualInit) {
				video.dataset.ccMutualInit = "1";
				video.addEventListener('play', function() {
					document.querySelectorAll('video').forEach(function(other) {
						if (other !== video && !other.paused) {
							other.pause();
						}
					});
				});
			}

			// 自动包装与 caption 处理（若未包装）
			if (!video.parentElement.classList.contains('cc_video_container') && !video.parentElement.classList.contains('wp-block-video')) {
				var container = document.createElement('div');
				container.className = 'cc_video_container';
				video.parentNode.insertBefore(container, video);
				container.appendChild(video);

				var caption = video.getAttribute('caption');
				if (caption && !container.querySelector('.cc_video_caption')) {
					var capDiv = document.createElement('div');
					capDiv.className = 'cc_video_caption';
					capDiv.textContent = caption;
					container.appendChild(capDiv);
				}
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initVideos);
	} else {
		initVideos();
	}
})();
