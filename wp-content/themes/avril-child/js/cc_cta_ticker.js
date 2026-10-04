/**
 * ChinaCongress 首页双卡片选民统计数字动画与滚动跑马灯
 */
(function() {
	function animate(el, end) {
		var start = 100, t0 = null, dur = 1000;
		function step(t) {
			if (!t0) t0 = t;
			var p = Math.min((t - t0) / dur, 1);
			el.innerText = Math.floor(start + (end - start) * p);
			if (p < 1) requestAnimationFrame(step);
		}
		requestAnimationFrame(step);
	}

	function observe(id, val) {
		var el = document.getElementById(id);
		if (!el || typeof val === "undefined") return;
		var io = new IntersectionObserver(function(entries, obs) {
			if (entries[0].isIntersecting) {
				obs.disconnect();
				animate(el, val);
			}
		}, { threshold: 0.5 });
		io.observe(el);
	}

	function initTicker(tickerId, listClass) {
		var ticker = document.getElementById(tickerId);
		if (!ticker) return;
		var list = ticker.querySelector('.' + listClass);
		if (!list || list.children.length <= 1) return;
		var idx = 0, hover = false;
		ticker.onmouseenter = function() { hover = true; };
		ticker.onmouseleave = function() { hover = false; };
		setInterval(function() {
			if (hover) return;
			list.style.opacity = '0';
			list.style.transform = 'translateY(-3px)';
			setTimeout(function() {
				idx = (idx + 1) % list.children.length;
				list.style.top = -(idx * (list.children[0].offsetHeight || 36)) + 'px';
				list.style.transform = 'translateY(3px)';
				setTimeout(function() {
					list.style.opacity = '1';
					list.style.transform = 'translateY(0)';
				}, 50);
			}, 250);
		}, 3500);
	}

	function init() {
		var data = window.ccCtaData || {};
		if (typeof data.overseas !== 'undefined') observe("number_overseas", data.overseas);
		if (typeof data.mainland !== 'undefined') observe("number_mainland", data.mainland);
		initTicker('mainland_members_ticker', 'mainland-members-list');
		initTicker('overseas_members_ticker', 'overseas-members-list');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
