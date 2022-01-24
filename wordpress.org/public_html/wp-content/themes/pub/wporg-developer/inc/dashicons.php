<?php
/**
 * Class for Dashicons data and related functionality.
 *
 * @package wporg-developer
 */

/**
 * Class to handle Dashicons.
 */
class Devhub_Dashicons {

	/**
	 * Returns dashicons.
	 */
	public static function get_dashicons() {
		return [
			'admin menu' => [
				'label' => __( 'Admin Menu', 'wporg' ),
				'icons' => [
					'dashicons-menu' => [
						'code'     => 'f333',
						'label'    => 'menu',
						'keywords' => 'menu admin',
					],
					'dashicons-menu-alt' => [
						'code'     => 'f228',
						'label'    => 'menu (alt)',
						'keywords' => 'menu alt admin',
					],
					'dashicons-menu-alt2' => [
						'code'     => 'f329',
						'label'    => 'menu (alt2)',
						'keywords' => 'menu alt admin',
					],
					'dashicons-menu-alt3' => [
						'code'     => 'f349',
						'label'    => 'menu (alt3)',
						'keywords' => 'menu alt admin',
					],
					'dashicons-admin-site' => [
						'code'     => 'f319',
						'label'    => 'site',
						'keywords' => 'site admin',
					],
					'dashicons-admin-site-alt' => [
						'code'     => 'f11d',
						'label'    => 'site (alt)',
						'keywords' => 'site alt admin',
					],
					'dashicons-admin-site-alt2' => [
						'code'     => 'f11e',
						'label'    => 'site (alt2)',
						'keywords' => 'site alt admin',
					],
					'dashicons-admin-site-alt3' => [
						'code'     => 'f11f',
						'label'    => 'site (alt3)',
						'keywords' => 'site alt admin',
					],
					'dashicons-dashboard' => [
						'code'     => 'f226',
						'label'    => 'dashboard',
						'keywords' => 'dashboard admin',
					],
					'dashicons-admin-post' => [
						'code'     => 'f109',
						'label'    => 'post',
						'keywords' => 'post admin',
					],
					'dashicons-admin-media' => [
						'code'     => 'f104',
						'label'    => 'media',
						'keywords' => 'media admin',
					],
					'dashicons-admin-links' => [
						'code'     => 'f103',
						'label'    => 'links',
						'keywords' => 'links admin',
					],
					'dashicons-admin-page' => [
						'code'     => 'f105',
						'label'    => 'page',
						'keywords' => 'page admin',
					],
					'dashicons-admin-comments' => [
						'code'     => 'f101',
						'label'    => 'comments',
						'keywords' => 'comments admin',
					],
					'dashicons-admin-appearance' => [
						'code'     => 'f100',
						'label'    => 'appearance',
						'keywords' => 'appearance admin',
					],
					'dashicons-admin-plugins' => [
						'code'     => 'f106',
						'label'    => 'plugins',
						'keywords' => 'plugins admin',
					],
					'dashicons-plugins-checked' => [
						'code'     => 'f485',
						'label'    => 'plugins checked',
						'keywords' => 'plugins checked admin',
					],
					'dashicons-admin-users' => [
						'code'     => 'f110',
						'label'    => 'users',
						'keywords' => 'users admin',
					],
					'dashicons-admin-tools' => [
						'code'     => 'f107',
						'label'    => 'tools',
						'keywords' => 'tools admin',
					],
					'dashicons-admin-settings' => [
						'code'     => 'f108',
						'label'    => 'settings',
						'keywords' => 'settings admin',
					],
					'dashicons-admin-network' => [
						'code'     => 'f112',
						'label'    => 'network',
						'keywords' => 'network admin',
					],
					'dashicons-admin-home' => [
						'code'     => 'f102',
						'label'    => 'home',
						'keywords' => 'home admin',
					],
					'dashicons-admin-generic' => [
						'code'     => 'f111',
						'label'    => 'generic',
						'keywords' => 'generic admin',
					],
					'dashicons-admin-collapse' => [
						'code'     => 'f148',
						'label'    => 'collapse',
						'keywords' => 'collapse admin',
					],
					'dashicons-filter' => [
						'code'     => 'f536',
						'label'    => 'filter',
						'keywords' => 'filter admin',
					],
					'dashicons-admin-customizer' => [
						'code'     => 'f540',
						'label'    => 'customizer',
						'keywords' => 'customizer admin',
					],
					'dashicons-admin-multisite' => [
						'code'     => 'f541',
						'label'    => 'multisite',
						'keywords' => 'multisite admin',
					],
				],
			],
		
			'welcome screen' => [
				'label' => __( 'Welcome Screen', 'wporg' ),
				'icons' => [
					'dashicons-welcome-write-blog' => [
						'code'     => 'f119',
						'label'    => 'write blog',
						'keywords' => 'write blog welcome',
					],
					/* Duplicate
					'dashicons-welcome-edit-page' => [
						'code'     => 'f119',
						'lable'    => 'edit page',
						'keywords' => '',
					],
					*/
					'dashicons-welcome-add-page' => [
						'code'     => 'f133',
						'label'    => 'add page',
						'keywords' => 'add page welcome',
					],
					'dashicons-welcome-view-site' => [
						'code'     => 'f115',
						'label'    => 'view site',
						'keywords' => 'view site welcome',
					],
					'dashicons-welcome-widgets-menus' => [
						'code'     => 'f116',
						'label'    => 'widgets menus',
						'keywords' => 'widgets menus welcome',
					],
					'dashicons-welcome-comments' => [
						'code'     => 'f117',
						'label'    => 'comments',
						'keywords' => 'comments welcome',
					],
					'dashicons-welcome-learn-more' => [
						'code'     => 'f118',
						'label'    => 'learn more',
						'keywords' => 'learn more welcome',
					],
				],
			],
		
			'post formats' => [
				'label' => __( 'Post Formats', 'wporg' ),
				'icons'  => [
					/* Duplicate
					'dashicons-format-standard' => [
						'code'     => 'f109',
						'keywords' => '',
					],
					*/
					'dashicons-format-aside' => [
						'code'     => 'f123',
						'label'    => 'aside',
						'keywords' => 'aside format',
					],
					'dashicons-format-image' => [
						'code'     => 'f128',
						'label'    => 'image',
						'keywords' => 'image format',
					],
					'dashicons-format-gallery' => [
						'code'     => 'f161',
						'label'    => 'gallery',
						'keywords' => 'gallery format',
					],
					'dashicons-format-video' => [
						'code'     => 'f126',
						'label'    => 'video',
						'keywords' => 'video format',
					],
					'dashicons-format-status' => [
						'code'     => 'f130',
						'label'    => 'status',
						'keywords' => 'status format',
					],
					'dashicons-format-quote' => [
						'code'     => 'f122',
						'label'    => 'quote',
						'keywords' => 'quote format',
					],
					/* Duplicate
					'dashicons-format-links' => [
						'code'     => 'f103',
						'label'    => 'format links',
						'keywords' => '',
					],
					*/
					'dashicons-format-chat' => [
						'code'     => 'f125',
						'label'    => 'chat',
						'keywords' => 'chat format',
					],
					'dashicons-format-audio' => [
						'code'     => 'f127',
						'label'    => 'audio',
						'keywords' => 'audio format',
					],
					'dashicons-camera' => [
						'code'     => 'f306',
						'label'    => 'camera',
						'keywords' => 'camera format',
					],
					'dashicons-camera-alt' => [
						'code'     => 'f129',
						'label'    => 'camera (alt)',
						'keywords' => 'camera alt format',
					],
					'dashicons-images-alt' => [
						'code'     => 'f232',
						'label'    => 'images (alt)',
						'keywords' => 'images alt format',
					],
					'dashicons-images-alt2' => [
						'code'     => 'f233',
						'label'    => 'images (alt2)',
						'keywords' => 'images alt format',
					],
					'dashicons-video-alt' => [
						'code'     => 'f234',
						'label'    => 'video (alt)',
						'keywords' => 'video alt format',
					],
					'dashicons-video-alt2' => [
						'code'     => 'f235',
						'label'    => 'video (alt2)',
						'keywords' => 'video alt format',
					],
					'dashicons-video-alt3' => [
						'code'     => 'f236',
						'label'    => 'video (alt3)',
						'keywords' => 'video alt format',
					],
				],
			],
		
			'media' => [
				'label' => __( 'Media', 'wporg' ),
				'icons' => [
					'dashicons-media-archive' => [
						'code'     => 'f501',
						'label'    => 'archive',
						'keywords' => 'archive media',
					],
					'dashicons-media-audio' => [
						'code'     => 'f500',
						'label'    => 'audio',
						'keywords' => 'audio media',
					],
					'dashicons-media-code' => [
						'code'     => 'f499',
						'label'    => 'code',
						'keywords' => 'code media',
					],
					'dashicons-media-default' => [
						'code'     => 'f498',
						'label'    => 'default',
						'keywords' => 'default media',
					],
					'dashicons-media-document' => [
						'code'     => 'f497',
						'label'    => 'document',
						'keywords' => 'document media',
					],
					'dashicons-media-interactive' => [
						'code'     => 'f496',
						'label'    => 'interactive',
						'keywords' => 'interactive media',
					],
					'dashicons-media-spreadsheet' => [
						'code'     => 'f495',
						'label'    => 'spreadsheet',
						'keywords' => 'spreadsheet media',
					],
					'dashicons-media-text' => [
						'code'     => 'f491',
						'label'    => 'text',
						'keywords' => 'text media',
					],
					'dashicons-media-video' => [
						'code'     => 'f490',
						'label'    => 'video',
						'keywords' => 'video media',
					],
					'dashicons-playlist-audio' => [
						'code'     => 'f492',
						'label'    => 'playlist audio',
						'keywords' => 'audio playlist media',
					],
					'dashicons-playlist-video' => [
						'code'     => 'f493',
						'label'    => 'playlist video',
						'keywords' => 'video playlist media',
					],
					'dashicons-controls-play' => [
						'code'     => 'f522',
						'label'    => 'play',
						'keywords' => 'play player controls media',
					],
					'dashicons-controls-pause' => [
						'code'     => 'f523',
						'label'    => 'pause',
						'keywords' => 'player pause controls media',
					],
					'dashicons-controls-forward' => [
						'code'     => 'f519',
						'label'    => 'forward',
						'keywords' => 'player forward controls media',
					],
					'dashicons-controls-skipforward' => [
						'code'     => 'f517',
						'label'    => 'skip forward',
						'keywords' => 'player skip forward controls media',
					],
					'dashicons-controls-back' => [
						'code'     => 'f518',
						'label'    => 'back',
						'keywords' => 'player back controls media',
					],
					'dashicons-controls-skipback' => [
						'code'     => 'f516',
						'label'    => 'skip back',
						'keywords' => 'player skip back controls media',
					],
					'dashicons-controls-repeat' => [
						'code'     => 'f515',
						'label'    => 'repeat',
						'keywords' => 'player repeat controls media',
					],
					'dashicons-controls-volumeon' => [
						'code'     => 'f521',
						'label'    => 'volume on',
						'keywords' => 'player volume on controls media',
					],
					'dashicons-controls-volumeoff' => [
						'code'     => 'f520',
						'label'    => 'volume off',
						'keywords' => 'player volume off controls media',
					],
				],
			],
		
			'image editing' => [
				'label' => __( 'Image Editing', 'wporg' ),
				'icons' => [
					'dashicons-image-crop' => [
						'code'     => 'f165',
						'label'    => 'crop',
						'keywords' => 'crop image',
					],
					'dashicons-image-rotate' => [
						'code'     => 'f531',
						'label'    => 'rotate',
						'keywords' => 'rotate image',
					],
					'dashicons-image-rotate-left' => [
						'code'     => 'f166',
						'label'    => 'rotate left',
						'keywords' => 'rotate left image',
					],
					'dashicons-image-rotate-right' => [
						'code'     => 'f167',
						'label'    => 'rotate right',
						'keywords' => 'rotate right image',
					],
					'dashicons-image-flip-vertical' => [
						'code'     => 'f168',
						'label'    => 'flip vertical',
						'keywords' => 'flip vertical image',
					],
					'dashicons-image-flip-horizontal' => [
						'code'     => 'f169',
						'label'    => 'flip horizontal',
						'keywords' => 'flip horizontal image',
					],
					'dashicons-image-filter' => [
						'code'     => 'f533',
						'label'    => 'filter',
						'keywords' => 'filter image',
					],
					'dashicons-undo' => [
						'code'     => 'f171',
						'label'    => 'undo',
						'keywords' => 'undo image',
					],
					'dashicons-redo' => [
						'code'     => 'f172',
						'label'    => 'redo',
						'keywords' => 'redo image',
					],
				],
			],
		
			'databases' => [
				'label' => __( 'Databases', 'wporg' ),
				'icons' => [
					'dashicons-database-add' => [
						'code'     => 'f170',
						'label'    => 'database add',
						'keywords' => 'database add',
					],
					'dashicons-database' => [
						'code'     => 'f17e',
						'label'    => 'database',
						'keywords' => 'database',
					],
					'dashicons-database-export' => [
						'code'     => 'f17a',
						'label'    => 'database export',
						'keywords' => 'database export',
					],
					'dashicons-database-import' => [
						'code'     => 'f17b',
						'label'    => 'database import',
						'keywords' => 'database import',
					],
					'dashicons-database-remove' => [
						'code'     => 'f17c',
						'label'    => 'database remove',
						'keywords' => 'database remove',
					],
					'dashicons-database-view' => [
						'code'     => 'f17d',
						'label'    => 'database view',
						'keywords' => 'database view',
					],
				],
			],
		
			'block editor' => [
				'label' => __( 'Block Editor', 'wporg' ),
				'icons' => [
					'dashicons-align-full-width' => [
						'code'     => 'f114',
						'label'    => 'align full width',
						'keywords' => 'align full width block',
					],
					'dashicons-align-pull-left' => [
						'code'     => 'f10a',
						'label'    => 'align pull left',
						'keywords' => 'align pull left block',
					],
					'dashicons-align-pull-right' => [
						'code'     => 'f10b',
						'label'    => 'align pull right',
						'keywords' => 'align pull right block',
					],
					'dashicons-align-wide' => [
						'code'     => 'f11b',
						'label'    => 'align wide',
						'keywords' => 'align wide block',
					],
					'dashicons-block-default' => [
						'code'     => 'f12b',
						'label'    => 'block default',
						'keywords' => 'block default',
					],
					'dashicons-button' => [
						'code'     => 'f11a',
						'label'    => 'button',
						'keywords' => 'button block',
					],
					'dashicons-cloud-saved' => [
						'code'     => 'f137',
						'label'    => 'cloud saved',
						'keywords' => 'cloud saved block',
					],
					'dashicons-cloud-upload' => [
						'code'     => 'f13b',
						'label'    => 'cloud upload',
						'keywords' => 'cloud upload block',
					],
					'dashicons-columns' => [
						'code'     => 'f13c',
						'label'    => 'columns',
						'keywords' => 'columns block',
					],
					'dashicons-cover-image' => [
						'code'     => 'f13d',
						'label'    => 'cover image',
						'keywords' => 'cover image block',
					],
					'dashicons-ellipsis' => [
						'code'     => 'f11c',
						'label'    => 'ellipsis',
						'keywords' => 'ellipsis block',
					],
					'dashicons-embed-audio' => [
						'code'     => 'f13e',
						'label'    => 'embed audio',
						'keywords' => 'embed audio block',
					],
					'dashicons-embed-generic' => [
						'code'     => 'f13f',
						'label'    => 'embed generic',
						'keywords' => 'embed generic block',
					],
					'dashicons-embed-photo' => [
						'code'     => 'f144',
						'label'    => 'embed photo',
						'keywords' => 'embed photo block',
					],
					'dashicons-embed-post' => [
						'code'     => 'f146',
						'label'    => 'embed post',
						'keywords' => 'embed post block',
					],
					'dashicons-embed-video' => [
						'code'     => 'f149',
						'label'    => 'embed video',
						'keywords' => 'embed video block',
					],
					'dashicons-exit' => [
						'code'     => 'f14a',
						'label'    => 'exit',
						'keywords' => 'exit block',
					],
					'dashicons-heading' => [
						'code'     => 'f10e',
						'label'    => 'heading',
						'keywords' => 'heading block',
					],
					'dashicons-html' => [
						'code'     => 'f14b',
						'label'    => 'HTML',
						'keywords' => 'html block',
					],
					'dashicons-info-outline' => [
						'code'     => 'f14c',
						'label'    => 'info outline',
						'keywords' => 'info outline block',
					],
					'dashicons-insert' => [
						'code'     => 'f10f',
						'label'    => 'insert',
						'keywords' => 'insert block',
					],
					'dashicons-insert-after' => [
						'code'     => 'f14d',
						'label'    => 'insert after',
						'keywords' => 'insert after block',
					],
					'dashicons-insert-before' => [
						'code'     => 'f14e',
						'label'    => 'insert before',
						'keywords' => 'insert before block',
					],
					'dashicons-remove' => [
						'code'     => 'f14f',
						'label'    => 'remove',
						'keywords' => 'remove block',
					],
					'dashicons-saved' => [
						'code'     => 'f15e',
						'label'    => 'saved',
						'keywords' => 'saved block',
					],
					'dashicons-shortcode' => [
						'code'     => 'f150',
						'label'    => 'shortcode',
						'keywords' => 'shortcode block',
					],
					'dashicons-table-col-after' => [
						'code'     => 'f151',
						'label'    => 'table col after',
						'keywords' => 'table col after block',
					],
					'dashicons-table-col-before' => [
						'code'     => 'f152',
						'label'    => 'table col before',
						'keywords' => 'table col before block',
					],
					'dashicons-table-col-delete' => [
						'code'     => 'f15a',
						'label'    => 'table col delete',
						'keywords' => 'table col delete block',
					],
					'dashicons-table-row-after' => [
						'code'     => 'f15b',
						'label'    => 'table row after',
						'keywords' => 'table row after block',
					],
					'dashicons-table-row-before' => [
						'code'     => 'f15c',
						'label'    => 'table row before',
						'keywords' => 'table row before block',
					],
					'dashicons-table-row-delete' => [
						'code'     => 'f15d',
						'label'    => 'table row delete',
						'keywords' => 'table row delete block',
					],
				],
			],
		
			'tinymce' => [
				'label' => __( 'TinyMCE', 'wporg' ),
				'icons' => [
					'dashicons-editor-bold' => [
						'code'     => 'f200',
						'label'    => 'bold',
						'keywords' => 'bold editor tinymce',
					],
					'dashicons-editor-italic' => [
						'code'     => 'f201',
						'label'    => 'italic',
						'keywords' => 'italic editor tinymce',
					],
					'dashicons-editor-ul' => [
						'code'     => 'f203',
						'label'    => 'unordered list',
						'keywords' => 'ul unordered list editor tinymce',
					],
					'dashicons-editor-ol' => [
						'code'     => 'f204',
						'label'    => 'ordered list',
						'keywords' => 'ol ordered listeditor tinymce',
					],
					'dashicons-editor-ol-rtl' => [
						'code'     => 'f12c',
						'label'    => 'ordered list RTL',
						'keywords' => 'ol ordered list rtl right left editor tinymce',
					],
					'dashicons-editor-quote' => [
						'code'     => 'f205',
						'label'    => 'quote',
						'keywords' => 'quote editor tinymce',
					],
					'dashicons-editor-alignleft' => [
						'code'     => 'f206',
						'label'    => 'align left',
						'keywords' => 'align left editor tinymce',
					],
					'dashicons-editor-aligncenter' => [
						'code'     => 'f207',
						'label'    => 'align center',
						'keywords' => 'align center editor tinymce',
					],
					'dashicons-editor-alignright' => [
						'code'     => 'f208',
						'label'    => 'align right',
						'keywords' => 'align right editor tinymce',
					],
					'dashicons-editor-insertmore' => [
						'code'     => 'f209',
						'label'    => 'insert more',
						'keywords' => 'insert more editor tinymce',
					],
					'dashicons-editor-spellcheck' => [
						'code'     => 'f210',
						'label'    => 'spellcheck',
						'keywords' => 'spellcheck editor tinymce',
					],
					/* Duplicate
					'dashicons-editor-distractionfree' => [
						'code'     => 'f211',
						'label'    => 'distraction-free",
						'keywords' => '',
					],
					*/
					'dashicons-editor-expand' => [
						'code'     => 'f211',
						'label'    => 'expand',
						'keywords' => 'expand editor tinymce',
					],
					'dashicons-editor-contract' => [
						'code'     => 'f506',
						'label'    => 'contract',
						'keywords' => 'contract editor tinymce',
					],
					'dashicons-editor-kitchensink' => [
						'code'     => 'f212',
						'label'    => 'kitchen sink',
						'keywords' => 'kitchen sink editor tinymce',
					],
					'dashicons-editor-underline' => [
						'code'     => 'f213',
						'label'    => 'underline',
						'keywords' => 'underline editor tinymce',
					],
					'dashicons-editor-justify' => [
						'code'     => 'f214',
						'label'    => 'justify',
						'keywords' => 'justify editor tinymce',
					],
					'dashicons-editor-textcolor' => [
						'code'     => 'f215',
						'label'    => 'text color',
						'keywords' => 'textcolor editor text color tinymce',
					],
					'dashicons-editor-paste-word' => [
						'code'     => 'f216',
						'label'    => 'paste word',
						'keywords' => 'paste editor word tinymce',
					],
					'dashicons-editor-paste-text' => [
						'code'     => 'f217',
						'label'    => 'paste text',
						'keywords' => 'paste editor text tinymce',
					],
					'dashicons-editor-removeformatting' => [
						'code'     => 'f218',
						'label'    => 'remove formatting',
						'keywords' => 'remove formatting editor tinymce',
					],
					'dashicons-editor-video' => [
						'code'     => 'f219',
						'label'    => 'video',
						'keywords' => 'video editor tinymce',
					],
					'dashicons-editor-customchar' => [
						'code'     => 'f220',
						'label'    => 'custom character',
						'keywords' => 'custom character editor tinymce',
					],
					'dashicons-editor-outdent' => [
						'code'     => 'f221',
						'label'    => 'outdent',
						'keywords' => 'outdent editor tinymce',
					],
					'dashicons-editor-indent' => [
						'code'     => 'f222',
						'label'    => 'indent',
						'keywords' => 'indent editor tinymce',
					],
					'dashicons-editor-help' => [
						'code'     => 'f223',
						'label'    => 'help',
						'keywords' => 'help editor tinymce',
					],
					'dashicons-editor-strikethrough' => [
						'code'     => 'f224',
						'label'    => 'strikethrough',
						'keywords' => 'strikethrough editor tinymce',
					],
					'dashicons-editor-unlink' => [
						'code'     => 'f225',
						'label'    => 'unlink',
						'keywords' => 'unlink editor tinymce',
					],
					'dashicons-editor-rtl' => [
						'code'     => 'f320',
						'label'    => 'RTL',
						'keywords' => 'rtl right left editor tinymce',
					],
					'dashicons-editor-ltr' => [
						'code'     => 'f10c',
						'label'    => 'LTR',
						'keywords' => 'ltr left right editor tinymce',
					],
					'dashicons-editor-break' => [
						'code'     => 'f474',
						'label'    => 'break',
						'keywords' => 'break editor tinymce',
					],
					'dashicons-editor-code' => [
						'code'     => 'f475',
						'label'    => 'code',
						'keywords' => 'code editor tinymce',
					],
					/* Duplicate
					'dashicons-editor-code-duplicate' => [
						'code'     => 'f494',
						'label'    => 'code duplicate',
						'keywords' => '',
					],
					*/
					'dashicons-editor-paragraph' => [
						'code'     => 'f476',
						'label'    => 'paragraph',
						'keywords' => 'paragraph editor tinymce',
					],
					'dashicons-editor-table' => [
						'code'     => 'f535',
						'label'    => 'table',
						'keywords' => 'table editor tinymce',
					],
				],
			],
		
			'posts screen' => [
				'label' => __( 'Posts Screen', 'wporg' ),
				'icons' => [
					'dashicons-align-left' => [
						'code'     => 'f135',
						'label'    => 'align left',
						'keywords' => 'align left',
					],
					'dashicons-align-right' => [
						'code'     => 'f136',
						'label'    => 'align right',
						'keywords' => 'align right',
					],
					'dashicons-align-center' => [
						'code'     => 'f134',
						'label'    => 'align center',
						'keywords' => 'align center',
					],
					'dashicons-align-none' => [
						'code'     => 'f138',
						'label'    => 'align none',
						'keywords' => 'align none',
					],
					'dashicons-lock' => [
						'code'     => 'f160',
						'label'    => 'lock',
						'keywords' => 'lock',
					],
					/* Duplicate
					'dashicons-lock-duplicate' => [
						'code'     => 'f315',
						'label'    => 'lock duplicate',
						'keywords' => '',
					],
					*/
					'dashicons-unlock' => [
						'code'     => 'f528',
						'label'    => 'unlock',
						'keywords' => 'unlock',
					],
					'dashicons-calendar' => [
						'code'     => 'f145',
						'label'    => 'calendar',
						'keywords' => 'calendar',
					],
					'dashicons-calendar-alt' => [
						'code'     => 'f508',
						'label'    => 'calendar (alt)',
						'keywords' => 'calendar alt',
					],
					'dashicons-visibility' => [
						'code'     => 'f177',
						'label'    => 'visibility',
						'keywords' => 'visibility',
					],
					'dashicons-hidden' => [
						'code'     => 'f530',
						'label'    => 'hidden',
						'keywords' => 'hidden',
					],
					'dashicons-post-status' => [
						'code'     => 'f173',
						'label'    => 'post status',
						'keywords' => 'post status',
					],
					'dashicons-edit' => [
						'code'     => 'f464',
						'label'    => 'edit',
						'keywords' => 'edit pencil',
					],
					'dashicons-trash' => [
						'code'     => 'f182',
						'label'    => 'trash',
						'keywords' => 'trash remove delete',
					],
					'dashicons-sticky' => [
						'code'     => 'f537',
						'label'    => 'sticky',
						'keywords' => 'sticky',
					],
				],
			],
		
			'sorting' => [
				'label' => __( 'Sorting', 'wporg' ),
				'icons' => [
					'dashicons-external' => [
						'code'     => 'f504',
						'label'    => 'external',
						'keywords' => 'external',
					],
					'dashicons-arrow-up' => [
						'code'     => 'f142',
						'label'    => 'arrow up',
						'keywords' => 'arrow up',
					],
					/* Duplicate
					'dashicons-arrow-up-duplicate' => [
						'code'     => 'f143',
						'label'    => 'arrow up duplicate',
						'keywords' => '',
					],
					*/
					'dashicons-arrow-down' => [
						'code'     => 'f140',
						'label'    => 'arrow down',
						'keywords' => 'arrow down',
					],
					'dashicons-arrow-right' => [
						'code'     => 'f139',
						'label'    => 'arrow right',
						'keywords' => 'arrow right',
					],
					'dashicons-arrow-left' => [
						'code'     => 'f141',
						'label'    => 'arrow left',
						'keywords' => 'arrow left',
					],
					'dashicons-arrow-up-alt' => [
						'code'     => 'f342',
						'label'    => 'arrow up (alt)',
						'keywords' => 'arrow up alt',
					],
					'dashicons-arrow-down-alt' => [
						'code'     => 'f346',
						'label'    => 'arrow down (alt)',
						'keywords' => 'arrow down alt',
					],
					'dashicons-arrow-right-alt' => [
						'code'     => 'f344',
						'label'    => 'arrow right (alt)',
						'keywords' => 'arrow right alt',
					],
					'dashicons-arrow-left-alt' => [
						'code'     => 'f340',
						'label'    => 'arrow left (alt)',
						'keywords' => 'arrow left alt',
					],
					'dashicons-arrow-up-alt2' => [
						'code'     => 'f343',
						'label'    => 'arrow up (alt2)',
						'keywords' => 'arrow up alt',
					],
					'dashicons-arrow-down-alt2' => [
						'code'     => 'f347',
						'label'    => 'arrow down (alt2)',
						'keywords' => 'arrow down alt',
					],
					'dashicons-arrow-right-alt2' => [
						'code'     => 'f345',
						'label'    => 'arrow right (alt2)',
						'keywords' => 'arrow right alt',
					],
					'dashicons-arrow-left-alt2' => [
						'code'     => 'f341',
						'label'    => 'arrow left (alt2)',
						'keywords' => 'arrow left alt',
					],
					'dashicons-sort' => [
						'code'     => 'f156',
						'label'    => 'sort',
						'keywords' => 'sort',
					],
					'dashicons-leftright' => [
						'code'     => 'f229',
						'label'    => 'left right',
						'keywords' => 'left right',
					],
					'dashicons-randomize' => [
						'code'     => 'f503',
						'label'    => 'randomize',
						'keywords' => 'randomize shuffle',
					],
					'dashicons-list-view' => [
						'code'     => 'f163',
						'label'    => 'list view',
						'keywords' => 'list view',
					],
					'dashicons-excerpt-view' => [
						'code'     => 'f164',
						'label'    => 'excerpt view',
						'keywords' => 'excerpt view',
					],
					'dashicons-grid-view' => [
						'code'     => 'f509',
						'label'    => 'grid view',
						'keywords' => 'grid view',
					],
					'dashicons-move' => [
						'code'     => 'f545',
						'label'    => 'move',
						'keywords' => 'move',
					],
				],
			],
		
			'social' => [
				'label' => __( 'Social', 'wporg' ),
				'icons' => [
					'dashicons-share' => [
						'code'     => 'f237',
						'label'    => 'share',
						'keywords' => 'share social',
					],
					'dashicons-share-alt' => [
						'code'     => 'f240',
						'label'    => 'share (alt)',
						'keywords' => 'share alt social',
					],
					'dashicons-share-alt2' => [
						'code'     => 'f242',
						'label'    => 'share (alt2)',
						'keywords' => 'share alt social',
					],
					'dashicons-rss' => [
						'code'     => 'f303',
						'label'    => 'RSS',
						'keywords' => 'rss social',
					],
					'dashicons-email' => [
						'code'     => 'f465',
						'label'    => 'email',
						'keywords' => 'email social',
					],
					'dashicons-email-alt' => [
						'code'     => 'f466',
						'label'    => 'email (alt)',
						'keywords' => 'email alt social',
					],
					'dashicons-email-alt2' => [
						'code'     => 'f467',
						'label'    => 'email (alt2)',
						'keywords' => 'email alt social',
					],
					'dashicons-networking' => [
						'code'     => 'f325',
						'label'    => 'networking',
						'keywords' => 'networking social',
					],
					'dashicons-amazon' => [
						'code'     => 'f162',
						'label'    => 'Amazon',
						'keywords' => 'amazon social',
					],
					'dashicons-facebook' => [
						'code'     => 'f304',
						'label'    => 'Facebook',
						'keywords' => 'facebook social',
					],
					'dashicons-facebook-alt' => [
						'code'     => 'f305',
						'label'    => 'Facebook (alt)',
						'keywords' => 'facebook social alt',
					],
					'dashicons-google' => [
						'code'     => 'f18b',
						'label'    => 'Google',
						'keywords' => 'google social',
					],
					/* Defunct
					'dashicons-googleplus' => [
						'code'     => 'f462',
						'label'    => 'Google+',
						'keywords' => 'googleplus social',
					],
					*/
					'dashicons-instagram' => [
						'code'     => 'f12d',
						'label'    => 'Instagram',
						'keywords' => 'instagram social',
					],
					'dashicons-linkedin' => [
						'code'     => 'f18d',
						'label'    => 'LinkedIn',
						'keywords' => 'linkedin social',
					],
					'dashicons-pinterest' => [
						'code'     => 'f192',
						'label'    => 'Pinterest',
						'keywords' => 'pinterest social',
					],
					'dashicons-podio' => [
						'code'     => 'f19c',
						'label'    => 'Podio',
						'keywords' => 'podio social',
					],
					'dashicons-reddit' => [
						'code'     => 'f195',
						'label'    => 'Reddit',
						'keywords' => 'reddit social',
					],
					'dashicons-spotify' => [
						'code'     => 'f196',
						'label'    => 'Spotify',
						'keywords' => 'spotify social',
					],
					'dashicons-twitch' => [
						'code'     => 'f199',
						'label'    => 'Twitch',
						'keywords' => 'twitch social',
					],
					'dashicons-twitter' => [
						'code'     => 'f301',
						'label'    => 'Twitter',
						'keywords' => 'twitter social',
					],
					'dashicons-twitter-alt' => [
						'code'     => 'f302',
						'label'    => 'Twitter (alt)',
						'keywords' => 'twitter social alt',
					],
					'dashicons-whatsapp' => [
						'code'     => 'f19a',
						'label'    => 'WhatsApp',
						'keywords' => 'whatsapp social',
					],
					'dashicons-xing' => [
						'code'     => 'f19d',
						'label'    => 'Xing',
						'keywords' => 'xing social',
					],
					'dashicons-youtube' => [
						'code'     => 'f19b',
						'label'    => 'YouTube',
						'keywords' => 'youtube social',
					],
				],
			],
		
			'WordPress.org' => [
				'label' => __( 'WordPress.org', 'wporg' ),
				'icons' => [
					'dashicons-hammer' => [
						'code'     => 'f308',
						'label'    => 'hammer',
						'keywords' => 'hammer development',
					],
					'dashicons-art' => [
						'code'     => 'f309',
						'label'    => 'art',
						'keywords' => 'art design',
					],
					'dashicons-migrate' => [
						'code'     => 'f310',
						'label'    => 'migrate',
						'keywords' => 'migrate migration',
					],
					'dashicons-performance' => [
						'code'     => 'f311',
						'label'    => 'performance',
						'keywords' => 'performance',
					],
					'dashicons-universal-access' => [
						'code'     => 'f483',
						'label'    => 'universal access',
						'keywords' => 'universal access accessibility',
					],
					'dashicons-universal-access-alt' => [
						'code'     => 'f507',
						'label'    => 'universal access (alt)',
						'keywords' => 'universal access accessibility alt',
					],
					'dashicons-tickets' => [
						'code'     => 'f486',
						'label'    => 'tickets',
						'keywords' => 'tickets',
					],
					'dashicons-nametag' => [
						'code'     => 'f484',
						'label'    => 'nametag',
						'keywords' => 'nametag',
					],
					'dashicons-clipboard' => [
						'code'     => 'f481',
						'label'    => 'clipboard',
						'keywords' => 'clipboard',
					],
					'dashicons-heart' => [
						'code'     => 'f487',
						'label'    => 'heart',
						'keywords' => 'heart',
					],
					'dashicons-megaphone' => [
						'code'     => 'f488',
						'label'    => 'megaphone',
						'keywords' => 'megaphone',
					],
					'dashicons-schedule' => [
						'code'     => 'f489',
						'label'    => 'schedule',
						'keywords' => 'schedule',
					],
					'dashicons-tide' => [
						'code'     => 'f10d',
						'label'    => 'Tide',
						'keywords' => 'Tide',
					],
					'dashicons-rest-api' => [
						'code'     => 'f124',
						'label'    => 'REST API',
						'keywords' => 'REST API',
					],
					'dashicons-code-standards' => [
						'code'     => 'f13a',
						'label'    => 'code standards',
						'keywords' => 'code standards',
					],
				],
			],
		
			'buddicons' => [
				'label' => __( 'Buddicons', 'wporg' ),
				'icons' => [
					'dashicons-buddicons-activity' => [
						'code'     => 'f452',
						'label'    => 'activity',
						'keywords' => 'activity buddicons',
					],
					'dashicons-buddicons-bbpress-logo' => [
						'code'     => 'f477',
						'label'    => 'bbPress',
						'keywords' => 'bbPress buddicons',
					],
					'dashicons-buddicons-buddypress-logo' => [
						'code'     => 'f448',
						'label'    => 'BuddyPress',
						'keywords' => 'BuddyPress buddicons',
					],
					'dashicons-buddicons-community' => [
						'code'     => 'f453',
						'label'    => 'community',
						'keywords' => 'community buddicons',
					],
					'dashicons-buddicons-forums' => [
						'code'     => 'f449',
						'label'    => 'forums',
						'keywords' => 'forums buddicons',
					],
					'dashicons-buddicons-friends' => [
						'code'     => 'f454',
						'label'    => 'friends',
						'keywords' => 'friends buddicons',
					],
					'dashicons-buddicons-groups' => [
						'code'     => 'f456',
						'label'    => 'groups',
						'keywords' => 'groups buddicons',
					],
					'dashicons-buddicons-pm' => [
						'code'     => 'f457',
						'label'    => 'pm',
						'keywords' => 'private message buddicons pm',
					],
					'dashicons-buddicons-replies' => [
						'code'     => 'f451',
						'label'    => 'replies',
						'keywords' => 'replies buddicons',
					],
					'dashicons-buddicons-topics' => [
						'code'     => 'f450',
						'label'    => 'topics',
						'keywords' => 'topics buddicons',
					],
					'dashicons-buddicons-tracking' => [
						'code'     => 'f455',
						'label'    => 'tracking',
						'keywords' => 'tracking buddicons',
					],
				],
			],
		
			'products' => [
				'label' => __( 'Products', 'wporg' ),
				'icons' => [
					'dashicons-wordpress' => [
						'code'     => 'f120',
						'label'    => 'WordPress',
						'keywords' => 'WordPress',
					],
					'dashicons-wordpress-alt' => [
						'code'     => 'f324',
						'label'    => 'WordPress (alt)',
						'keywords' => 'WordPress alt',
					],
					'dashicons-pressthis' => [
						'code'     => 'f157',
						'label'    => 'Pressthis',
						'keywords' => 'Pressthis',
					],
					'dashicons-update' => [
						'code'     => 'f463',
						'label'    => 'update',
						'keywords' => 'update',
					],
					'dashicons-update-alt' => [
						'code'     => 'f113',
						'label'    => 'update (alt)',
						'keywords' => 'update alt',
					],
					'dashicons-screenoptions' => [
						'code'     => 'f180',
						'label'    => 'screen options',
						'keywords' => 'screenoptions',
					],
					'dashicons-info' => [
						'code'     => 'f348',
						'label'    => 'info',
						'keywords' => 'info',
					],
					'dashicons-cart' => [
						'code'     => 'f174',
						'label'    => 'cart',
						'keywords' => 'cart shopping',
					],
					'dashicons-feedback' => [
						'code'     => 'f175',
						'label'    => 'feedback',
						'keywords' => 'feedback form',
					],
					'dashicons-cloud' => [
						'code'     => 'f176',
						'label'    => 'cloud',
						'keywords' => 'cloud',
					],
					'dashicons-translation' => [
						'code'     => 'f326',
						'label'    => 'translation',
						'keywords' => 'translation language',
					],
				],
			],
		
			'taxonomies' => [
				'label' => __( 'Taxonomies', 'wporg' ),
				'icons' => [
					'dashicons-tag' => [
						'code'     => 'f323',
						'label'    => 'tag',
						'keywords' => 'tag taxonomy',
					],
					'dashicons-category' => [
						'code'     => 'f318',
						'label'    => 'category',
						'keywords' => 'category taxonomy',
					],
				],
			],
		
			'widgets' => [
				'label' => __( 'Widgets', 'wporg' ),
				'icons' => [
					'dashicons-archive' => [
						'code'     => 'f480',
						'label'    => 'archive',
						'keywords' => 'archive widget',
					],
					'dashicons-tagcloud' => [
						'code'     => 'f479',
						'label'    => 'tagcloud',
						'keywords' => 'tagcloud widget',
					],
					'dashicons-text' => [
						'code'     => 'f478',
						'label'    => 'text',
						'keywords' => 'text widget',
					],
				],
			],
		
			'notifications' => [
				'label' => __( 'Notifications', 'wporg' ),
				'icons' => [
					'dashicons-bell' => [
						'code'     => 'f16d',
						'label'    => 'bell',
						'keywords' => 'bell notifications',
					],
					'dashicons-yes' => [
						'code'     => 'f147',
						'label'    => 'yes',
						'keywords' => 'yes check checkmark notifications',
					],
					'dashicons-yes-alt' => [
						'code'     => 'f12a',
						'label'    => 'yes (alt)',
						'keywords' => 'yes check checkmark alt notifications',
					],
					'dashicons-no' => [
						'code'     => 'f158',
						'label'    => 'no',
						'keywords' => 'no x notifications',
					],
					'dashicons-no-alt' => [
						'code'     => 'f335',
						'label'    => 'no (alt)',
						'keywords' => 'no x alt notifications',
					],
					'dashicons-plus' => [
						'code'     => 'f132',
						'label'    => 'plus',
						'keywords' => 'plus add increase notifications',
					],
					'dashicons-plus-alt' => [
						'code'     => 'f502',
						'label'    => 'plus (alt)',
						'keywords' => 'plus add increase alt notifications',
					],
					'dashicons-plus-alt2' => [
						'code'     => 'f543',
						'label'    => 'plus (alt2)',
						'keywords' => 'plus add increase alt notifications',
					],
					'dashicons-minus' => [
						'code'     => 'f460',
						'label'    => 'minus',
						'keywords' => 'minus decrease notifications',
					],
					'dashicons-dismiss' => [
						'code'     => 'f153',
						'label'    => 'dismiss',
						'keywords' => 'dismiss notifications',
					],
					'dashicons-marker' => [
						'code'     => 'f159',
						'label'    => 'marker',
						'keywords' => 'marker notifications',
					],
					'dashicons-star-filled' => [
						'code'     => 'f155',
						'label'    => 'star filled',
						'keywords' => 'filled star notifications',
					],
					'dashicons-star-half' => [
						'code'     => 'f459',
						'label'    => 'star half',
						'keywords' => 'half star notifications',
					],
					'dashicons-star-empty' => [
						'code'     => 'f154',
						'label'    => 'star empty',
						'keywords' => 'empty star notifications',
					],
					'dashicons-flag' => [
						'code'     => 'f227',
						'label'    => 'flag',
						'keywords' => 'flag notifications',
					],
					'dashicons-warning' => [
						'code'     => 'f534',
						'label'    => 'warning',
						'keywords' => 'warning notifications',
					],
				],
			],
		
			'miscellaneous' => [
				'label' => __( 'Miscellaneous', 'wporg' ),
				'icons' => [
					'dashicons-location' => [
						'code'     => 'f230',
						'label'    => 'location',
						'keywords' => 'location pin',
					],
					'dashicons-location-alt' => [
						'code'     => 'f231',
						'label'    => 'location (alt)',
						'keywords' => 'location alt',
					],
					'dashicons-vault' => [
						'code'     => 'f178',
						'label'    => 'vault',
						'keywords' => 'vault safe',
					],
					'dashicons-shield' => [
						'code'     => 'f332',
						'label'    => 'shield',
						'keywords' => 'shield',
					],
					'dashicons-shield-alt' => [
						'code'     => 'f334',
						'label'    => 'shield (alt)',
						'keywords' => 'shield alt',
					],
					'dashicons-sos' => [
						'code'     => 'f468',
						'label'    => 'sos',
						'keywords' => 'sos help',
					],
					'dashicons-search' => [
						'code'     => 'f179',
						'label'    => 'search',
						'keywords' => 'search',
					],
					'dashicons-slides' => [
						'code'     => 'f181',
						'label'    => 'slides',
						'keywords' => 'slides',
					],
					'dashicons-text-page' => [
						'code'     => 'f121',
						'label'    => 'text page',
						'keywords' => 'text page',
					],
					'dashicons-analytics' => [
						'code'     => 'f183',
						'label'    => 'analytics',
						'keywords' => 'analytics',
					],
					'dashicons-chart-pie' => [
						'code'     => 'f184',
						'label'    => 'chart pie',
						'keywords' => 'pie chart',
					],
					'dashicons-chart-bar' => [
						'code'     => 'f185',
						'label'    => 'chart bar',
						'keywords' => 'bar chart',
					],
					'dashicons-chart-line' => [
						'code'     => 'f238',
						'label'    => 'chart line',
						'keywords' => 'line chart',
					],
					'dashicons-chart-area' => [
						'code'     => 'f239',
						'label'    => 'chart area',
						'keywords' => 'area chart',
					],
					'dashicons-groups' => [
						'code'     => 'f307',
						'label'    => 'groups',
						'keywords' => 'groups',
					],
					'dashicons-businessman' => [
						'code'     => 'f338',
						'label'    => 'businessman',
						'keywords' => 'businessman',
					],
					'dashicons-businesswoman' => [
						'code'     => 'f12f',
						'label'    => 'businesswoman',
						'keywords' => 'businesswoman',
					],
					'dashicons-businessperson' => [
						'code'     => 'f12e',
						'label'    => 'businessperson',
						'keywords' => 'businessperson',
					],
					'dashicons-id' => [
						'code'     => 'f336',
						'label'    => 'id',
						'keywords' => 'id',
					],
					'dashicons-id-alt' => [
						'code'     => 'f337',
						'label'    => 'id (alt)',
						'keywords' => 'id alt',
					],
					'dashicons-products' => [
						'code'     => 'f312',
						'label'    => 'products',
						'keywords' => 'products',
					],
					'dashicons-awards' => [
						'code'     => 'f313',
						'label'    => 'awards',
						'keywords' => 'awards',
					],
					'dashicons-forms' => [
						'code'     => 'f314',
						'label'    => 'forms',
						'keywords' => 'forms',
					],
					'dashicons-testimonial' => [
						'code'     => 'f473',
						'label'    => 'testimonial',
						'keywords' => 'testimonial',
					],
					'dashicons-portfolio' => [
						'code'     => 'f322',
						'label'    => 'portfolio',
						'keywords' => 'portfolio',
					],
					'dashicons-book' => [
						'code'     => 'f330',
						'label'    => 'book',
						'keywords' => 'book',
					],
					'dashicons-book-alt' => [
						'code'     => 'f331',
						'label'    => 'book (alt)',
						'keywords' => 'book alt',
					],
					'dashicons-download' => [
						'code'     => 'f316',
						'label'    => 'download',
						'keywords' => 'download',
					],
					'dashicons-upload' => [
						'code'     => 'f317',
						'label'    => 'upload',
						'keywords' => 'upload',
					],
					'dashicons-backup' => [
						'code'     => 'f321',
						'label'    => 'backup',
						'keywords' => 'backup',
					],
					'dashicons-clock' => [
						'code'     => 'f469',
						'label'    => 'clock',
						'keywords' => 'clock',
					],
					'dashicons-lightbulb' => [
						'code'     => 'f339',
						'label'    => 'lightbulb',
						'keywords' => 'lightbulb',
					],
					'dashicons-microphone' => [
						'code'     => 'f482',
						'label'    => 'microphone',
						'keywords' => 'microphone mic',
					],
					'dashicons-desktop' => [
						'code'     => 'f472',
						'label'    => 'desktop',
						'keywords' => 'desktop monitor',
					],
					'dashicons-laptop' => [
						'code'     => 'f547',
						'label'    => 'laptop',
						'keywords' => 'laptop',
					],
					'dashicons-tablet' => [
						'code'     => 'f471',
						'label'    => 'tablet',
						'keywords' => 'tablet ipad',
					],
					'dashicons-smartphone' => [
						'code'     => 'f470',
						'label'    => 'smartphone',
						'keywords' => 'smartphone iphone',
					],
					'dashicons-phone' => [
						'code'     => 'f525',
						'label'    => 'phone',
						'keywords' => 'phone',
					],
					'dashicons-index-card' => [
						'code'     => 'f510',
						'label'    => 'index card',
						'keywords' => 'index card',
					],
					'dashicons-carrot' => [
						'code'     => 'f511',
						'label'    => 'carrot',
						'keywords' => 'carrot food vendor',
					],
					'dashicons-building' => [
						'code'     => 'f512',
						'label'    => 'building',
						'keywords' => 'building',
					],
					'dashicons-store' => [
						'code'     => 'f513',
						'label'    => 'store',
						'keywords' => 'store',
					],
					'dashicons-album' => [
						'code'     => 'f514',
						'label'    => 'album',
						'keywords' => 'album',
					],
					'dashicons-palmtree' => [
						'code'     => 'f527',
						'label'    => 'palm tree',
						'keywords' => 'palm tree',
					],
					'dashicons-tickets-alt' => [
						'code'     => 'f524',
						'label'    => 'tickets (alt)',
						'keywords' => 'tickets alt',
					],
					'dashicons-money' => [
						'code'     => 'f526',
						'label'    => 'money',
						'keywords' => 'money',
					],
					'dashicons-money-alt' => [
						'code'     => 'f18e',
						'label'    => 'money (alt)',
						'keywords' => 'money alt',
					],
					'dashicons-smiley' => [
						'code'     => 'f328',
						'label'    => 'smiley',
						'keywords' => 'smiley smile',
					],
					'dashicons-thumbs-up' => [
						'code'     => 'f529',
						'label'    => 'thumbs up',
						'keywords' => 'thumbs up',
					],
					'dashicons-thumbs-down' => [
						'code'     => 'f542',
						'label'    => 'thumbs down',
						'keywords' => 'thumbs down',
					],
					'dashicons-layout' => [
						'code'     => 'f538',
						'label'    => 'layout',
						'keywords' => 'layout',
					],
					'dashicons-paperclip' => [
						'code'     => 'f546',
						'label'    => 'paperclip',
						'keywords' => 'paperclip',
					],
					'dashicons-color-picker' => [
						'code'     => 'f131',
						'label'    => 'color picker',
						'keywords' => 'color picker',
					],
					'dashicons-edit-large' => [
						'code'     => 'f327',
						'label'    => 'edit large',
						'keywords' => 'edit large',
					],
					'dashicons-edit-page' => [
						'code'     => 'f186',
						'label'    => 'edit page',
						'keywords' => 'edit page',
					],
					'dashicons-airplane' => [
						'code'     => 'f15f',
						'label'    => 'airplane',
						'keywords' => 'airplane',
					],
					'dashicons-bank' => [
						'code'     => 'f16a',
						'label'    => 'bank',
						'keywords' => 'bank',
					],
					'dashicons-beer' => [
						'code'     => 'f16c',
						'label'    => 'beer',
						'keywords' => 'beer',
					],
					'dashicons-calculator' => [
						'code'     => 'f16e',
						'label'    => 'calculator',
						'keywords' => 'calculator',
					],
					'dashicons-car' => [
						'code'     => 'f16b',
						'label'    => 'car',
						'keywords' => 'car',
					],
					'dashicons-coffee' => [
						'code'     => 'f16f',
						'label'    => 'coffee',
						'keywords' => 'coffee',
					],
					'dashicons-drumstick' => [
						'code'     => 'f17f',
						'label'    => 'drumstick',
						'keywords' => 'drumstick',
					],
					'dashicons-food' => [
						'code'     => 'f187',
						'label'    => 'food',
						'keywords' => 'food',
					],
					'dashicons-fullscreen-alt' => [
						'code'     => 'f188',
						'label'    => 'fullscreen (alt)',
						'keywords' => 'fullscreen alt',
					],
					'dashicons-fullscreen-exit-alt' => [
						'code'     => 'f189',
						'label'    => 'fullscreen exit (alt)',
						'keywords' => 'fullscreen exit alt',
					],
					'dashicons-games' => [
						'code'     => 'f18a',
						'label'    => 'games',
						'keywords' => 'games',
					],
					'dashicons-hourglass' => [
						'code'     => 'f18c',
						'label'    => 'hourglass',
						'keywords' => 'hourglass',
					],
					'dashicons-open-folder' => [
						'code'     => 'f18f',
						'label'    => 'open folder',
						'keywords' => 'open folder',
					],
					'dashicons-pdf' => [
						'code'     => 'f190',
						'label'    => 'PDF',
						'keywords' => 'pdf',
					],
					'dashicons-pets' => [
						'code'     => 'f191',
						'label'    => 'pets',
						'keywords' => 'pets',
					],
					'dashicons-printer' => [
						'code'     => 'f193',
						'label'    => 'printer',
						'keywords' => 'printer',
					],
					'dashicons-privacy' => [
						'code'     => 'f194',
						'label'    => 'privacy',
						'keywords' => 'privacy',
					],
					'dashicons-superhero' => [
						'code'     => 'f198',
						'label'    => 'superhero',
						'keywords' => 'superhero',
					],
					'dashicons-superhero-alt' => [
						'code'     => 'f197',
						'label'    => 'superhero (alt)',
						'keywords' => 'superhero alt',
					],
				],
			],
		];
	}

} // Devhub_Dashicons
