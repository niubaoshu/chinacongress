<?php
/**
 * Template part for displaying page content in Child Theme.
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('post-items mb-6'); ?>>
	<figure class="post-image">
	   <a href="<?php echo esc_url(get_permalink()); ?>" class="post-hover">
			<?php if ( has_post_thumbnail() ) { the_post_thumbnail(); } ?>
		</a>
		<div class="post-meta imu">
			<span class="post-list">
			   <ul class="post-categories"><?php the_category(' '); ?></ul>
			</span>
		</div>
	</figure>
	<div class="post-content">
		<div class="post-meta up">
			<span class="posted-on">
			   <a href="<?php echo esc_url(get_month_link(get_post_time('Y'),get_post_time('m'))); ?>"><?php echo esc_html(get_the_date()); ?></a>
			</span>
		</div>
	   <?php     
			if ( is_single() || is_page() ) :
			
			the_title('<h5 class="post-title">', '</h5>' );
			
			the_content( __( 'Read More', 'avril-child' ) );

			// 社交分享摘要提取 (标题 + 140字以内导读，防止分享 URL 超长导致社交平台报错)
			$clean_title = html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' );
			$short_desc  = html_entity_decode( chinacongress_get_clean_excerpt( 140 ), ENT_QUOTES, 'UTF-8' );
			$share_text  = $clean_title . ( ! empty( $short_desc ) ? "\n\n" . $short_desc : '' );
			?>
			<!-- 统一文章底部社交分享组件 (包含 Telegram, X, Facebook, WhatsApp & 📄 复制文本) -->
			<div class="post-share-bar">
				<span class="share-label">分享本文：</span>
				<a href="https://t.me/share/url?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode($share_text); ?>" target="_blank" rel="noopener noreferrer" class="post-share-btn post-share-tg">✈️ Telegram</a>
				<a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode($share_text); ?>" target="_blank" rel="noopener noreferrer" class="post-share-btn post-share-x">𝕏 Twitter</a>
				<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>" target="_blank" rel="noopener noreferrer" class="post-share-btn post-share-fb">📘 Facebook</a>
				<a href="https://api.whatsapp.com/send?text=<?php echo urlencode($share_text . ' ' . get_permalink()); ?>" target="_blank" rel="noopener noreferrer" class="post-share-btn post-share-wa">🟢 WhatsApp</a>
				<a href="javascript:void(0);" onclick="chinacongressCopyArticleText();" class="post-share-btn post-share-cp">📄 复制文本</a>
			</div>

			<script>
			function chinacongressCopyArticleText() {
				const title = document.querySelector('.post-title')?.innerText?.trim() || document.title;
				const contentEl = document.querySelector('.post-content');
				let articleText = '';
				if (contentEl) {
					const clone = contentEl.cloneNode(true);
					clone.querySelectorAll('.post-share-bar, .post-title, .post-meta').forEach(el => el.remove());
					articleText = clone.innerText.trim();
				}
				const text = title + (articleText ? '\n\n' + articleText : '') + '\n\n文章链接：' + window.location.href;
				const showToast = msg => {
					const el = Object.assign(document.createElement('div'), { textContent: msg });
					Object.assign(el.style, { position: 'fixed', top: '30%', left: '50%', transform: 'translateX(-50%)', padding: '10px 22px', background: 'rgba(0,0,0,0.85)', color: '#fff', borderRadius: '6px', fontSize: '15px', zIndex: '99999', boxShadow: '0 4px 12px rgba(0,0,0,0.2)', transition: 'opacity 0.25s ease' });
					document.body.appendChild(el);
					setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 250); }, 2500);
				};
				if (navigator.clipboard?.writeText) {
					navigator.clipboard.writeText(text).then(() => showToast('文章全文与链接已成功复制到剪贴板！')).catch(() => showToast('复制失败，请手动选择复制。'));
				} else {
					const ta = Object.assign(document.createElement('textarea'), { value: text, style: 'position:fixed;opacity:0' });
					document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
					showToast('文章全文与链接已成功复制到剪贴板！');
				}
			}
			</script>
			<?php
			endif; 
		?> 
	</div>
</article>
