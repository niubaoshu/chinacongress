<?php
/**
 * cc-contact-link — 服务端动态渲染。
 *
 * @var array    $attributes 区块属性。
 * @var string   $content    区块默认内容。
 * @var WP_Block $block      区块实例。
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$preset_id = isset( $attributes['preset'] ) ? sanitize_key( (string) $attributes['preset'] ) : '';

if ( '' !== $preset_id ) {
	$presets = cc_contact_link_presets();
	if ( ! isset( $presets[ $preset_id ] ) ) {
		return '';
	}
	$item  = $presets[ $preset_id ];
	$type  = $item['type'];
	$value = $item['value'];
	$label = $item['label'];
} else {
	// 兼容未绑定预设的旧区块数据
	$type  = isset( $attributes['type'] ) && 'telegram' === $attributes['type'] ? 'telegram' : 'email';
	$value = isset( $attributes['value'] ) ? trim( (string) $attributes['value'] ) : '';
	$label = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
}

if ( '' === $value ) {
	return '';
}

$is_telegram = ( 'telegram' === $type );

if ( $is_telegram ) {
	$href         = esc_url_raw( cc_contact_link_normalize_telegram( $value ) );
	$display_text = $href;
	$copy_text    = $href;
	$icon         = '✈️';
	$fallback     = 'Telegram：';
} else {
	$email        = sanitize_email( $value );
	$email        = '' !== $email ? $email : $value;
	$href         = 'mailto:' . $email;
	$display_text = $email;
	$copy_text    = $email;
	$icon         = '📧';
	$fallback     = 'Email：';
}

$has_custom_label = '' !== trim( wp_strip_all_tags( $label ) );
$label_html       = $has_custom_label ? wp_kses_post( $label ) : esc_html( $fallback );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'cc-contact-link cc-contact-link--' . $type,
	)
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="cc-contact-link__card">
		<div class="cc-contact-link__header">
			<span class="cc-contact-link__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
			<span class="cc-contact-link__label"><?php echo $label_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<div class="cc-contact-link__row">
			<a
				class="cc-contact-link__anchor"
				href="<?php echo esc_url( $href ); ?>"
				<?php if ( $is_telegram ) : ?>
					target="_blank"
					rel="noopener noreferrer"
				<?php endif; ?>
			><?php echo esc_html( $display_text ); ?></a>
			<button
				type="button"
				class="cc-contact-link__copy"
				data-copy-text="<?php echo esc_attr( $copy_text ); ?>"
				aria-label="<?php echo esc_attr( sprintf( '复制 %s', $copy_text ) ); ?>"
			>复制</button>
		</div>
	</div>
</div>
