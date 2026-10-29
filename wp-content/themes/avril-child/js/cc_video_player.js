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
			video.setAttribute('playsinline', '');
			video.setAttribute('webkit-playsinline', '');

			if (!video.getAttribute('preload') || video.getAttribute('preload') === 'none') {
				video.preload = 'metadata';
				video.setAttribute('preload', 'metadata');
			}

			// 兼容 iOS Safari / WebKit 手机端自动解码渲染无 poster 视频的第一帧画面
			if (!video.getAttribute('poster')) {
				var rawSrc = video.getAttribute('src');
				if (rawSrc && rawSrc.indexOf('#') === -1) {
					video.setAttribute('src', rawSrc + '#t=0.001');
				} else if (!rawSrc) {
					var sourceEl = video.querySelector('source[src]');
					if (sourceEl) {
						var sSrc = sourceEl.getAttribute('src');
						if (sSrc && sSrc.indexOf('#') === -1) {
							sourceEl.setAttribute('src', sSrc + '#t=0.001');
							video.load();
						}
					}
				}
			}

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

			// 自动包装与 caption 处理（仅对未包装且非自定义卡片容器的裸视频生效）
			var frame = video.closest('.video_frame');
			if (frame) {
				// 自动清除 WordPress wpautop 误插入的空段落标签，消除卡片内部微小缝隙
				frame.querySelectorAll('p').forEach(function(p) {
					if (!p.textContent.trim()) {
						p.remove();
					}
				});
			}

			var hasCustomWrapper = video.closest('.cc_video_container') || 
			                       video.closest('.wp-block-video') || 
			                       frame;
			if (!hasCustomWrapper) {
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
