<?php
/**
 * Template part for displaying page content in Child Theme.
 */

if ( ! is_single() && ! is_page() ) {
    get_template_part( 'template-parts/content/content', 'search' );
    return;
}
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

			// 解码 WordPress 自动生成的 HTML 实体字符（如将 &#8211; 还原为 - 或 –，将 &#038; 还原为 &）
			$clean_title = html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' );

			// 1. 过滤 script 和 style 标签及其内部代码
			$raw_content = get_the_content();
			$no_scripts  = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $raw_content );

			// 2. 将段落块标签转换为统一换行
			$block_break = preg_replace( '/<\/(p|sentence|div|h[1-6]|li)>/i', "\n\n", $no_scripts );
			$br_break    = preg_replace( '/<br\s*\/?>/i', "\n", $block_break );

			// 3. 剥离所有 HTML 标签并解码 HTML 实体字符
			$clean_text  = html_entity_decode( wp_strip_all_tags( $br_break ), ENT_QUOTES, 'UTF-8' );

			// 4. 按行整理全文章节段落
			$lines       = array_filter( array_map( 'trim', explode( "\n", $clean_text ) ) );
			$full_paragraphs = implode( "\n\n", $lines );
			
			// 全文复制格式：【文章标题】 + 空一行 + 【各段落文本（段与段空一行）】 + 空一行 + 【文章链接】
			$full_copy_text  = $clean_title . "\n\n" . $full_paragraphs . "\n\n文章链接：" . get_permalink();

			// 社交分享 URL 安全提取前 3 个核心段落 (预防 Telegram / X 的 404 报错)
			$safe_lines       = array_slice( $lines, 0, 3 );
			$safe_paragraphs  = implode( "\n\n", $safe_lines );
			$share_text       = $clean_title . "\n\n" . $safe_paragraphs;
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
			function chinacongressCopyArticleText() {
				const fullText = <?php echo json_encode( $full_copy_text, JSON_UNESCAPED_UNICODE ); ?>;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(fullText).then(function() {
						alert('文章全文与格式化段落已成功复制到剪贴板！');
					}).catch(function(err) {
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
					alert('文章全文与格式化段落已成功复制到剪贴板！');
				} catch (err) {
					alert('复制失败，请手动选择复制。');
				}
				document.body.removeChild(ta);
			}
			</script>
			<?php
			endif; 
		?> 
	</div>
</article>
