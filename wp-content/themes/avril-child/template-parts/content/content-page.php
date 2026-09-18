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
			   <ul class="post-categories"><li><a href="<?php echo esc_url(get_permalink()); ?>"><?php the_category(' '); ?></a></li></ul>
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
			
			the_content( 
					sprintf( 
						__( 'Read More', 'avril' ), 
						'<span class="screen-reader-text">  '.esc_html(get_the_title()).'</span>' 
					) 
				);

			// 社交分享摘要提取 (标题 + 140字以内导读，防止分享 URL 超长导致社交平台报错)
			$clean_title = html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' );
			$raw_excerpt = has_excerpt() ? get_the_excerpt() : wp_strip_all_tags( get_the_content() );
			$clean_desc  = html_entity_decode( wp_strip_all_tags( $raw_excerpt ), ENT_QUOTES, 'UTF-8' );
			$short_desc  = mb_strimwidth( preg_replace( '/\s+/', ' ', $clean_desc ), 0, 140, '...' );
			$share_text  = $clean_title . ( ! empty( $short_desc ) ? "\n\n" . $short_desc : '' );
			?>
			<!-- 统一文章底部社交分享组件 (包含 Telegram, X, Facebook, WhatsApp & 📄 复制文本) -->
			<div class="post-share-bar" style="margin-top: 35px; padding-top: 20px; border-top: 1px solid #eee; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
				<span style="font-weight: bold; color: #444; font-size: 15px; margin-right: 5px;">分享本文：</span>
				<a href="https://t.me/share/url?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode($share_text); ?>" target="_blank" rel="noopener noreferrer" style="background: #0088cc; color: #fff; padding: 6px 14px; border-radius: 4px; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer;">✈️ Telegram</a>
				<a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode($share_text); ?>" target="_blank" rel="noopener noreferrer" style="background: #000000; color: #fff; padding: 6px 14px; border-radius: 4px; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer;">𝕏 Twitter</a>
				<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>" target="_blank" rel="noopener noreferrer" style="background: #1877f2; color: #fff; padding: 6px 14px; border-radius: 4px; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer;">📘 Facebook</a>
				<a href="https://api.whatsapp.com/send?text=<?php echo urlencode($share_text . ' ' . get_permalink()); ?>" target="_blank" rel="noopener noreferrer" style="background: #25d366; color: #fff; padding: 6px 14px; border-radius: 4px; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer;">🟢 WhatsApp</a>
				<a href="javascript:void(0);" onclick="chinacongressCopyArticleText();" style="background: #6c757d; color: #fff; padding: 6px 14px; border-radius: 4px; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer;">📄 复制文本</a>
			</div>

			<script>
			function chinacongressShowToast(msg) {
				if (typeof window.isCopy === 'function') {
					window.isCopy(msg);
					return;
				}
				const el = document.createElement('div');
				el.textContent = msg;
				Object.assign(el.style, {
					position: 'fixed',
					top: '30%',
					left: '50%',
					transform: 'translateX(-50%)',
					padding: '10px 22px',
					background: 'rgba(0,0,0,0.85)',
					color: '#fff',
					borderRadius: '6px',
					fontSize: '15px',
					zIndex: '99999',
					boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
					transition: 'opacity 0.25s ease'
				});
				document.body.appendChild(el);
				setTimeout(function() {
					el.style.opacity = '0';
					setTimeout(function() { el.remove(); }, 250);
				}, 2500);
			}

			function chinacongressCopyArticleText() {
				// 直接从浏览器 DOM 动态提取全文，零服务端二次重复输出，零网络传输冗余
				const title = document.querySelector('.post-title')?.innerText?.trim() || document.title;
				const contentEl = document.querySelector('.post-content');
				let articleText = '';
				if (contentEl) {
					const clone = contentEl.cloneNode(true);
					const shareBar = clone.querySelector('.post-share-bar');
					if (shareBar) shareBar.remove();
					const titleEl = clone.querySelector('.post-title');
					if (titleEl) titleEl.remove();
					const metaEl = clone.querySelector('.post-meta');
					if (metaEl) metaEl.remove();
					articleText = clone.innerText.trim();
				}
				const fullText = title + (articleText ? '\n\n' + articleText : '') + '\n\n文章链接：' + window.location.href;

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(fullText).then(function() {
						chinacongressShowToast('文章全文与链接已成功复制到剪贴板！');
					}).catch(function() {
						fallbackCopy(fullText);
					});
				} else {
					fallbackCopy(fullText);
				}
			}

			function fallbackCopy(text) {
				const ta = document.createElement('textarea');
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				try {
					document.execCommand('copy');
					chinacongressShowToast('文章全文与链接已成功复制到剪贴板！');
				} catch (err) {
					chinacongressShowToast('复制失败，请手动选择复制。');
				}
				document.body.removeChild(ta);
			}
			</script>
			<?php
			endif; 
		?> 
	</div>
</article>
