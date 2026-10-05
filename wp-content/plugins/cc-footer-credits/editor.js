/**
 * cc-footer-credits — 編輯器端。
 *
 * 刻意不用 JSX：沒有 npm、沒有 webpack、沒有建置步驟，改完直接 scp 上伺服器。
 * 名單庫由 PHP 用 wp_add_inline_script 放在 window.ccFooterCredits。
 */
( function ( wp, window ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var PanelBody = wp.components.PanelBody;
	var Placeholder = wp.components.Placeholder;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;

	var config = window.ccFooterCredits || {};
	var people = config.people || {};
	var roles = config.roles || {};
	var icons = config.icons || [];
	var settingsUrl = config.settingsUrl || '';

	var roleKeys = Object.keys( roles );
	var personIds = Object.keys( people );

	/** 下拉選項：只有名單庫的人，第一項是空值。沒有任何自由輸入欄位。 */
	function personOptions() {
		var options = [ { label: '— 請選擇 —', value: '' } ];

		personIds.forEach( function ( id ) {
			var person = people[ id ];
			options.push( {
				value: id,
				label: person.title ? person.name + '（' + person.title + '）' : person.name
			} );
		} );

		return options;
	}

	/** 值可能是單一 ID 字串，或遷移腳本寫入的 ID 陣列（多人）。空陣列視同無值。 */
	function hasValue( value ) {
		return Array.isArray( value ) ? value.length > 0 : !! value;
	}

	function hasAnyCredit( credits ) {
		return roleKeys.some( function ( key ) {
			return hasValue( credits[ key ] );
		} );
	}

	function withClass( blockProps, extra ) {
		var merged = {};
		Object.keys( blockProps ).forEach( function ( key ) {
			merged[ key ] = blockProps[ key ];
		} );
		merged.className = ( blockProps.className ? blockProps.className + ' ' : '' ) + extra;

		return merged;
	}

	function settingsLink( label ) {
		return el( 'a', { href: settingsUrl }, label || '設定 → 文章署名名單' );
	}

	function warning( blockProps, message ) {
		return el(
			'div',
			blockProps,
			el(
				Notice,
				{ status: 'warning', isDismissible: false },
				message + ' ',
				settingsLink( '打開設定頁' )
			)
		);
	}

	function roleSelects( credits, setCredit ) {
		return roleKeys.map( function ( key ) {
			var value = credits[ key ];
			var isMulti = Array.isArray( value ) && value.length > 0;

			// 多人值（來自遷移資料）：單選下拉不給它選中任何一項，並明說改動會改成單人，
			// 否則下拉空白＋預覽紅字會誘導編輯「重選一個」、把第二人靜默洗掉。
			return el( SelectControl, {
				key: key,
				label: roles[ key ] || key,
				value: isMulti ? '' : ( value || '' ),
				options: personOptions(),
				help: isMulti ? '此角色目前掛多人（遷移資料）；改動下拉會改成單人。' : undefined,
				onChange: function ( next ) {
					setCredit( key, next );
				},
				__nextHasNoMarginBottom: true
			} );
		} );
	}

	function iconsPreview() {
		if ( ! icons.length ) {
			return null;
		}

		return el(
			'div',
			{ className: 'cc-credits__icons' },
			icons.map( function ( icon, index ) {
				return el( 'span', {
					key: index,
					className: 'cc-credits__icon',
					style: { backgroundImage: 'url(' + icon.img + ')' },
					title: icon.tooltip || undefined
				} );
			} )
		);
	}

	/** 已選的人從名單庫消失時：刪除線＋紅字＋旁註，不靜默丟掉。 */
	function namePreview( personId ) {
		var person = people[ personId ];

		if ( ! person ) {
			return el(
				'span',
				{ className: 'cc-credits__name cc-credits__name--missing' },
				personId,
				el( 'span', { className: 'cc-credits__missing-note' }, '（名單庫已無此人）' )
			);
		}

		return el(
			'span',
			{ className: 'cc-credits__name', title: person.title || undefined },
			person.name
		);
	}

	/** 單一 ID → 一個名字；ID 陣列 → 以「、」串接（與前台 render.php 一致）。 */
	function namesPreviewFor( value ) {
		if ( ! Array.isArray( value ) ) {
			return namePreview( value );
		}

		var parts = [];
		value.forEach( function ( id, i ) {
			if ( i > 0 ) {
				parts.push( '、' );
			}
			parts.push( el( Fragment, { key: id + '-' + i }, namePreview( id ) ) );
		} );
		return parts;
	}

	function namesPreview( credits ) {
		var lines = roleKeys
			.filter( function ( key ) {
				return hasValue( credits[ key ] );
			} )
			.map( function ( key ) {
				return el(
					'div',
					{ key: key, className: 'cc-credits__line' },
					el( 'span', { className: 'cc-credits__label' }, roles[ key ] ),
					namesPreviewFor( credits[ key ] )
				);
			} );

		if ( ! lines.length ) {
			return null;
		}

		return el( 'div', { className: 'cc-credits__names' }, lines );
	}

	function Edit( props ) {
		var credits = props.attributes.credits || {};
		var showIcons = false !== props.attributes.showIcons;
		var blockProps = useBlockProps();
		// 剛插入的區塊先進選擇狀態 —— 不直接跳出成品。
		// 這個狀態存在屬性裡而非 useState：否則「只放圖標」的區塊每次重開文章
		// 都會退回選單畫面，前台卻正常出圖標，看起來像壞了。
		var isChoosing = true !== props.attributes.configured;

		function setCredit( roleKey, personId ) {
			var next = {};
			Object.keys( credits ).forEach( function ( key ) {
				next[ key ] = credits[ key ];
			} );

			if ( personId ) {
				next[ roleKey ] = personId;
			} else {
				delete next[ roleKey ];
			}

			props.setAttributes( { credits: next } );
		}

		function setShowIcons( value ) {
			props.setAttributes( { showIcons: value } );
		}

		if ( ! personIds.length ) {
			return warning( blockProps, '名單庫是空的，請先加人，否則這個區塊沒有東西可選。' );
		}

		if ( ! roleKeys.length ) {
			return warning( blockProps, '角色清單是空的，請先建角色（例 editor|编辑：）。' );
		}

		var inspector = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: '署名', initialOpen: true },
				roleSelects( credits, setCredit ),
				el(
					'p',
					{ className: 'cc-credits-editor__hint' },
					'名字只能從名單庫選。要增減人員：',
					settingsLink()
				)
			),
			el(
				PanelBody,
				{ title: '顯示', initialOpen: true },
				el( ToggleControl, {
					label: '同時顯示圖標',
					checked: showIcons,
					onChange: setShowIcons,
					__nextHasNoMarginBottom: true
				} )
			)
		);

		if ( isChoosing ) {
			// 產生條件：任一角色有值 或 圖標開著。
			var canGenerate = hasAnyCredit( credits ) || showIcons;

			return el(
				'div',
				withClass( blockProps, 'cc-credits-editor' ),
				inspector,
				el(
					Placeholder,
					{
						icon: 'groups',
						label: '文章署名（含圖標）',
						instructions: '選這篇文章的署名。每個角色都可以留空；只放圖標也可以。'
					},
					el(
						'div',
						{ className: 'cc-credits-editor__form' },
						roleSelects( credits, setCredit ),
						el( ToggleControl, {
							label: '同時顯示圖標',
							checked: showIcons,
							onChange: setShowIcons,
							__nextHasNoMarginBottom: true
						} ),
						el(
							Button,
							{
								variant: 'primary',
								disabled: ! canGenerate,
								onClick: function () {
									props.setAttributes( { configured: true } );
								}
							},
							'產生署名'
						),
						canGenerate
							? null
							: el(
								'p',
								{ className: 'cc-credits-editor__hint' },
								'至少選一個角色，或打開「同時顯示圖標」。'
							)
					)
				)
			);
		}

		return el(
			'div',
			withClass( blockProps, 'cc-credits' ),
			inspector,
			showIcons ? iconsPreview() : null,
			namesPreview( credits ),
			! showIcons && ! hasAnyCredit( credits )
				? el(
					'p',
					{ className: 'cc-credits-editor__empty' },
					'（目前不會輸出任何內容：沒有選人、圖標也關著。可在右側面板調整。）'
				)
				: null
		);
	}

	wp.blocks.registerBlockType( 'cc/footer-credits', {
		apiVersion: 3,
		title: '文章署名（含圖標）',
		description: '這篇文章的署名與社群圖標。人名從後台名單庫選，不接受臨時打字。',
		icon: 'groups',
		category: 'text',
		attributes: {
			credits: { type: 'object', default: {} },
			showIcons: { type: 'boolean', default: true },
			// 使用者按過「產生署名」沒有。只影響編輯器畫面，前台不看這個。
			configured: { type: 'boolean', default: false }
		},
		supports: {
			html: false,
			className: true
		},
		edit: Edit,
		// 動態區塊：前台由 PHP 產生，所以名單庫改了舊文章一起更新。
		save: function () {
			return null;
		}
	} );
} )( window.wp, window );
