/**
 * cc-contact-link — 古腾堡编辑器端（零构建原生 JS，基于后台预设名单）。
 */
( function ( wp, window ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var Notice = wp.components.Notice;
	var PanelBody = wp.components.PanelBody;
	var Placeholder = wp.components.Placeholder;
	var SelectControl = wp.components.SelectControl;

	var config = window.ccContactLinks || {};
	var presets = config.presets || {};
	var settingsUrl = config.settingsUrl || '';
	var presetIds = Object.keys( presets );

	function presetOptions() {
		var options = [ { label: '— 请选择联系预设 —', value: '' } ];
		presetIds.forEach( function ( id ) {
			var item = presets[ id ];
			var icon = item.type === 'telegram' ? '✈️ ' : '📧 ';
			options.push( {
				value: id,
				label: icon + item.label + '（' + item.value + '）'
			} );
		} );
		return options;
	}

	function settingsLink( text ) {
		return el(
			'a',
			{ href: settingsUrl, target: '_blank', rel: 'noopener noreferrer' },
			text || '前往「设定 → 联系链接预设」'
		);
	}

	registerBlockType( 'cc/contact-link', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var presetId = attributes.preset || '';
			var current = presetId && presets[ presetId ] ? presets[ presetId ] : null;

			var blockProps = useBlockProps( {
				className: 'cc-contact-link' + ( current ? ' cc-contact-link--' + current.type : '' )
			} );

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: '联系链接预设', initialOpen: true },
					presetIds.length > 0
						? el( SelectControl, {
								label: '选择预设项目',
								value: presetId,
								options: presetOptions(),
								help: '显示文案、图标、链接地址均随后台预设自动同步。',
								onChange: function ( nextId ) {
									setAttributes( { preset: nextId } );
								}
						  } )
						: el(
								'p',
								null,
								'后台尚未配置联系链接预设。',
								settingsLink( '立即去配置' )
						  ),
					settingsUrl &&
						el( 'p', { style: { marginTop: '8px' } }, settingsLink( '管理预设名单 ↗' ) )
				)
			);

			if ( presetIds.length === 0 ) {
				return el(
					Fragment,
					null,
					inspector,
					el(
						'div',
						blockProps,
						el(
							Notice,
							{ status: 'warning', isDismissible: false },
							'后台尚未添加任何联系链接预设，请先配置：',
							settingsLink()
						)
					)
				);
			}

			if ( ! current ) {
				return el(
					Fragment,
					null,
					inspector,
					el(
						'div',
						blockProps,
						el(
							Placeholder,
							{
								icon: 'email',
								label: '联系链接卡片',
								instructions: presetId
									? '原预设「' + presetId + '」已从后台移除，请重新选择：'
									: '请选择一个预设联系项目（图标、文案与链接均自动生成）：'
							},
							el( SelectControl, {
								value: presetId,
								options: presetOptions(),
								onChange: function ( nextId ) {
									setAttributes( { preset: nextId } );
								}
							} )
						)
					)
				);
			}

			var icon = current.type === 'telegram' ? '✈️' : '📧';

			return el(
				Fragment,
				null,
				inspector,
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'cc-contact-link__card' },
						el(
							'div',
							{ className: 'cc-contact-link__header' },
							el(
								'span',
								{ className: 'cc-contact-link__icon', 'aria-hidden': 'true' },
								icon
							),
							el(
								'span',
								{ className: 'cc-contact-link__label' },
								current.label
							)
						),
						el(
							'div',
							{ className: 'cc-contact-link__row' },
							el(
								'span',
								{ className: 'cc-contact-link__anchor' },
								current.value
							),
							el(
								'span',
								{ className: 'cc-contact-link__copy' },
								'复制'
							)
						)
					)
				)
			);
		},

		save: function () {
			return null;
		}
	} );
} )( window.wp, window );
