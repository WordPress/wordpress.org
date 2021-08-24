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
						'keywords' => 'menu',
					],
					'dashicons-menu-alt' => [
						'code'     => 'f228',
						'keywords' => 'menu (alt)',
					],
					'dashicons-menu-alt2' => [
						'code'     => 'f329',
						'keywords' => 'menu (alt2)',
					],
					'dashicons-menu-alt3' => [
						'code'     => 'f349',
						'keywords' => 'menu (alt3)',
					],
					'dashicons-admin-site' => [
						'code'     => 'f319',
						'keywords' => 'site',
					],
					'dashicons-admin-site-alt' => [
						'code'     => 'f11d',
						'keywords' => 'site (alt)',
					],
					'dashicons-admin-site-alt2' => [
						'code'     => 'f11e',
						'keywords' => 'site (alt2)',
					],
					'dashicons-admin-site-alt3' => [
						'code'     => 'f11f',
						'keywords' => 'site (alt3)',
					],
					'dashicons-dashboard' => [
						'code'     => 'f226',
						'keywords' => 'dashboard',
					],
					'dashicons-admin-post' => [
						'code'     => 'f109',
						'keywords' => 'post',
					],
					'dashicons-admin-media' => [
						'code'     => 'f104',
						'keywords' => 'media',
					],
					'dashicons-admin-links' => [
						'code'     => 'f103',
						'keywords' => 'links',
					],
					'dashicons-admin-page' => [
						'code'     => 'f105',
						'keywords' => 'page',
					],
					'dashicons-admin-comments' => [
						'code'     => 'f101',
						'keywords' => 'comments',
					],
					'dashicons-admin-appearance' => [
						'code'     => 'f100',
						'keywords' => 'appearance',
					],
					'dashicons-admin-plugins' => [
						'code'     => 'f106',
						'keywords' => 'plugins',
					],
					'dashicons-plugins-checked' => [
						'code'     => 'f485',
						'keywords' => 'plugins checked',
					],
					'dashicons-admin-users' => [
						'code'     => 'f110',
						'keywords' => 'users',
					],
					'dashicons-admin-tools' => [
						'code'     => 'f107',
						'keywords' => 'tools',
					],
					'dashicons-admin-settings' => [
						'code'     => 'f108',
						'keywords' => 'settings',
					],
					'dashicons-admin-network' => [
						'code'     => 'f112',
						'keywords' => 'network',
					],
					'dashicons-admin-home' => [
						'code'     => 'f102',
						'keywords' => 'home',
					],
					'dashicons-admin-generic' => [
						'code'     => 'f111',
						'keywords' => 'generic',
					],
					'dashicons-admin-collapse' => [
						'code'     => 'f148',
						'keywords' => 'collapse',
					],
					'dashicons-filter' => [
						'code'     => 'f536',
						'keywords' => 'filter',
					],
					'dashicons-admin-customizer' => [
						'code'     => 'f540',
						'keywords' => 'customizer',
					],
					'dashicons-admin-multisite' => [
						'code'     => 'f541',
						'keywords' => 'multisite',
					],
				],
			],
		
			'welcome screen' => [
				'label' => __( 'Welcome Screen', 'wporg' ),
				'icons' => [
					'dashicons-welcome-write-blog' => [
						'code'     => 'f119',
						'keywords' => 'write blog',
					],
					/* Duplicate
					'dashicons-welcome-edit-page' => [
						'code'     => 'f119',
						'keywords' => '',
					],
					*/
					'dashicons-welcome-add-page' => [
						'code'     => 'f133',
						'keywords' => 'add page',
					],
					'dashicons-welcome-view-site' => [
						'code'     => 'f115',
						'keywords' => 'view site',
					],
					'dashicons-welcome-widgets-menus' => [
						'code'     => 'f116',
						'keywords' => 'widgets and menus',
					],
					'dashicons-welcome-comments' => [
						'code'     => 'f117',
						'keywords' => 'comments',
					],
					'dashicons-welcome-learn-more' => [
						'code'     => 'f118',
						'keywords' => 'learn more',
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
						'keywords' => 'aside',
					],
					'dashicons-format-image' => [
						'code'     => 'f128',
						'keywords' => 'image',
					],
					'dashicons-format-gallery' => [
						'code'     => 'f161',
						'keywords' => 'gallery',
					],
					'dashicons-format-video' => [
						'code'     => 'f126',
						'keywords' => 'video',
					],
					'dashicons-format-status' => [
						'code'     => 'f130',
						'keywords' => 'status',
					],
					'dashicons-format-quote' => [
						'code'     => 'f122',
						'keywords' => 'quote',
					],
					/* Duplicate
					'dashicons-format-links' => [
						'code'     => 'f103',
						'keywords' => '',
					],
					*/
					'dashicons-format-chat' => [
						'code'     => 'f125',
						'keywords' => 'chat',
					],
					'dashicons-format-audio' => [
						'code'     => 'f127',
						'keywords' => 'audio',
					],
					'dashicons-camera' => [
						'code'     => 'f306',
						'keywords' => 'camera',
					],
					'dashicons-camera-alt' => [
						'code'     => 'f129',
						'keywords' => 'camera (alt)',
					],
					'dashicons-images-alt' => [
						'code'     => 'f232',
						'keywords' => 'images (alt)',
					],
					'dashicons-images-alt2' => [
						'code'     => 'f233',
						'keywords' => 'images (alt 2)',
					],
					'dashicons-video-alt' => [
						'code'     => 'f234',
						'keywords' => 'video (alt)',
					],
					'dashicons-video-alt2' => [
						'code'     => 'f235',
						'keywords' => 'video (alt 2)',
					],
					'dashicons-video-alt3' => [
						'code'     => 'f236',
						'keywords' => 'video (alt 3)',
					],
				],
			],
		
			'media' => [
				'label' => __( 'Media', 'wporg' ),
				'icons' => [
					'dashicons-media-archive' => [
						'code'     => 'f501',
						'keywords' => 'archive',
					],
					'dashicons-media-audio' => [
						'code'     => 'f500',
						'keywords' => 'audio',
					],
					'dashicons-media-code' => [
						'code'     => 'f499',
						'keywords' => 'code',
					],
					'dashicons-media-default' => [
						'code'     => 'f498',
						'keywords' => 'default',
					],
					'dashicons-media-document' => [
						'code'     => 'f497',
						'keywords' => 'document',
					],
					'dashicons-media-interactive' => [
						'code'     => 'f496',
						'keywords' => 'interactive',
					],
					'dashicons-media-spreadsheet' => [
						'code'     => 'f495',
						'keywords' => 'spreadsheet',
					],
					'dashicons-media-text' => [
						'code'     => 'f491',
						'keywords' => 'text',
					],
					'dashicons-media-video' => [
						'code'     => 'f490',
						'keywords' => 'video',
					],
					'dashicons-playlist-audio' => [
						'code'     => 'f492',
						'keywords' => 'audio playlist',
					],
					'dashicons-playlist-video' => [
						'code'     => 'f493',
						'keywords' => 'video playlist',
					],
					'dashicons-controls-play' => [
						'code'     => 'f522',
						'keywords' => 'play player',
					],
					'dashicons-controls-pause' => [
						'code'     => 'f523',
						'keywords' => 'player pause',
					],
					'dashicons-controls-forward' => [
						'code'     => 'f519',
						'keywords' => 'player forward',
					],
					'dashicons-controls-skipforward' => [
						'code'     => 'f517',
						'keywords' => 'player skip forward',
					],
					'dashicons-controls-back' => [
						'code'     => 'f518',
						'keywords' => 'player back',
					],
					'dashicons-controls-skipback' => [
						'code'     => 'f516',
						'keywords' => 'player skip back',
					],
					'dashicons-controls-repeat' => [
						'code'     => 'f515',
						'keywords' => 'player repeat',
					],
					'dashicons-controls-volumeon' => [
						'code'     => 'f521',
						'keywords' => 'player volume on',
					],
					'dashicons-controls-volumeoff' => [
						'code'     => 'f520',
						'keywords' => 'player volume off',
					],
				],
			],
		
			'image editing' => [
				'label' => __( 'Image Editing', 'wporg' ),
				'icons' => [
					'dashicons-image-crop' => [
						'code'     => 'f165',
						'keywords' => 'crop',
					],
					'dashicons-image-rotate' => [
						'code'     => 'f531',
						'keywords' => 'rotate',
					],
					'dashicons-image-rotate-left' => [
						'code'     => 'f166',
						'keywords' => 'rotate left',
					],
					'dashicons-image-rotate-right' => [
						'code'     => 'f167',
						'keywords' => 'rotate right',
					],
					'dashicons-image-flip-vertical' => [
						'code'     => 'f168',
						'keywords' => 'flip vertical',
					],
					'dashicons-image-flip-horizontal' => [
						'code'     => 'f169',
						'keywords' => 'flip horizontal',
					],
					'dashicons-image-filter' => [
						'code'     => 'f533',
						'keywords' => 'filter',
					],
					'dashicons-undo' => [
						'code'     => 'f171',
						'keywords' => 'undo',
					],
					'dashicons-redo' => [
						'code'     => 'f172',
						'keywords' => 'redo',
					],
				],
			],
		
			'databases' => [
				'label' => __( 'Databases', 'wporg' ),
				'icons' => [
					'dashicons-database-add' => [
						'code'     => 'f170',
						'keywords' => 'database add',
					],
					'dashicons-database' => [
						'code'     => 'f17e',
						'keywords' => 'database',
					],
					'dashicons-database-export' => [
						'code'     => 'f17a',
						'keywords' => 'database export',
					],
					'dashicons-database-import' => [
						'code'     => 'f17b',
						'keywords' => 'database import',
					],
					'dashicons-database-remove' => [
						'code'     => 'f17c',
						'keywords' => 'database remove',
					],
					'dashicons-database-view' => [
						'code'     => 'f17d',
						'keywords' => 'database view',
					],
				],
			],
		
			'block editor' => [
				'label' => __( 'Block Editor', 'wporg' ),
				'icons' => [
					'dashicons-align-full-width' => [
						'code'     => 'f134',
						'keywords' => 'align full width',
					],
					'dashicons-align-pull-left' => [
						'code'     => 'f10a',
						'keywords' => 'align pull left',
					],
					'dashicons-align-pull-right' => [
						'code'     => 'f10b',
						'keywords' => 'align pull right',
					],
					'dashicons-align-wide' => [
						'code'     => 'f11b',
						'keywords' => 'align wide',
					],
					'dashicons-block-default' => [
						'code'     => 'f12b',
						'keywords' => 'block default',
					],
					'dashicons-button' => [
						'code'     => 'f11a',
						'keywords' => 'button',
					],
					'dashicons-cloud-saved' => [
						'code'     => 'f137',
						'keywords' => 'cloud saved',
					],
					'dashicons-cloud-upload' => [
						'code'     => 'f13b',
						'keywords' => 'cloud upload',
					],
					'dashicons-columns' => [
						'code'     => 'f13c',
						'keywords' => 'columns',
					],
					'dashicons-cover-image' => [
						'code'     => 'f13d',
						'keywords' => 'cover image',
					],
					'dashicons-ellipsis' => [
						'code'     => 'f11c',
						'keywords' => 'ellipsis',
					],
					'dashicons-embed-audio' => [
						'code'     => 'f13e',
						'keywords' => 'embed audio',
					],
					'dashicons-embed-generic' => [
						'code'     => 'f13f',
						'keywords' => 'embed generic',
					],
					'dashicons-embed-photo' => [
						'code'     => 'f144',
						'keywords' => 'embed photo',
					],
					'dashicons-embed-post' => [
						'code'     => 'f146',
						'keywords' => 'embed post',
					],
					'dashicons-embed-video' => [
						'code'     => 'f149',
						'keywords' => 'embed video',
					],
					'dashicons-exit' => [
						'code'     => 'f14a',
						'keywords' => 'exit',
					],
					'dashicons-heading' => [
						'code'     => 'f10e',
						'keywords' => 'heading',
					],
					'dashicons-html' => [
						'code'     => 'f14b',
						'keywords' => 'html',
					],
					'dashicons-info-outline' => [
						'code'     => 'f14c',
						'keywords' => 'info outline',
					],
					'dashicons-insert' => [
						'code'     => 'f10f',
						'keywords' => 'insert',
					],
					'dashicons-insert-after' => [
						'code'     => 'f14d',
						'keywords' => 'insert after',
					],
					'dashicons-insert-before' => [
						'code'     => 'f14e',
						'keywords' => 'insert before',
					],
					'dashicons-remove' => [
						'code'     => 'f14f',
						'keywords' => 'remove',
					],
					'dashicons-saved' => [
						'code'     => 'f15e',
						'keywords' => 'saved',
					],
					'dashicons-shortcode' => [
						'code'     => 'f150',
						'keywords' => 'shortcode',
					],
					'dashicons-table-col-after' => [
						'code'     => 'f151',
						'keywords' => 'table col after',
					],
					'dashicons-table-col-before' => [
						'code'     => 'f152',
						'keywords' => 'table col before',
					],
					'dashicons-table-col-delete' => [
						'code'     => 'f15a',
						'keywords' => 'table col delete',
					],
					'dashicons-table-row-after' => [
						'code'     => 'f15b',
						'keywords' => 'table row after',
					],
					'dashicons-table-row-before' => [
						'code'     => 'f15c',
						'keywords' => 'table row before',
					],
					'dashicons-table-row-delete' => [
						'code'     => 'f15d',
						'keywords' => 'table row delete',
					],
				],
			],
		
			'tinymce' => [
				'label' => __( 'TinyMCE', 'wporg' ),
				'icons' => [
					'dashicons-editor-bold' => [
						'code'     => 'f200',
						'keywords' => 'bold',
					],
					'dashicons-editor-italic' => [
						'code'     => 'f201',
						'keywords' => 'italic',
					],
					'dashicons-editor-ul' => [
						'code'     => 'f203',
						'keywords' => 'ul',
					],
					'dashicons-editor-ol' => [
						'code'     => 'f204',
						'keywords' => 'ol',
					],
					'dashicons-editor-ol-rtl' => [
						'code'     => 'f12c',
						'keywords' => 'ol rtl',
					],
					'dashicons-editor-quote' => [
						'code'     => 'f205',
						'keywords' => 'quote',
					],
					'dashicons-editor-alignleft' => [
						'code'     => 'f206',
						'keywords' => 'alignleft',
					],
					'dashicons-editor-aligncenter' => [
						'code'     => 'f207',
						'keywords' => 'aligncenter',
					],
					'dashicons-editor-alignright' => [
						'code'     => 'f208',
						'keywords' => 'alignright',
					],
					'dashicons-editor-insertmore' => [
						'code'     => 'f209',
						'keywords' => 'insertmore',
					],
					'dashicons-editor-spellcheck' => [
						'code'     => 'f210',
						'keywords' => 'spellcheck',
					],
					/* Duplicate
					'dashicons-editor-distractionfree' => [
						'code'     => 'f211',
						'keywords' => '',
					],
					*/
					'dashicons-editor-expand' => [
						'code'     => 'f211',
						'keywords' => 'expand',
					],
					'dashicons-editor-contract' => [
						'code'     => 'f506',
						'keywords' => 'contract',
					],
					'dashicons-editor-kitchensink' => [
						'code'     => 'f212',
						'keywords' => 'kitchen sink',
					],
					'dashicons-editor-underline' => [
						'code'     => 'f213',
						'keywords' => 'underline',
					],
					'dashicons-editor-justify' => [
						'code'     => 'f214',
						'keywords' => 'justify',
					],
					'dashicons-editor-textcolor' => [
						'code'     => 'f215',
						'keywords' => 'textcolor',
					],
					'dashicons-editor-paste-word' => [
						'code'     => 'f216',
						'keywords' => 'paste',
					],
					'dashicons-editor-paste-text' => [
						'code'     => 'f217',
						'keywords' => 'paste',
					],
					'dashicons-editor-removeformatting' => [
						'code'     => 'f218',
						'keywords' => 'remove formatting',
					],
					'dashicons-editor-video' => [
						'code'     => 'f219',
						'keywords' => 'video',
					],
					'dashicons-editor-customchar' => [
						'code'     => 'f220',
						'keywords' => 'custom character',
					],
					'dashicons-editor-outdent' => [
						'code'     => 'f221',
						'keywords' => 'outdent',
					],
					'dashicons-editor-indent' => [
						'code'     => 'f222',
						'keywords' => 'indent',
					],
					'dashicons-editor-help' => [
						'code'     => 'f223',
						'keywords' => 'help',
					],
					'dashicons-editor-strikethrough' => [
						'code'     => 'f224',
						'keywords' => 'strikethrough',
					],
					'dashicons-editor-unlink' => [
						'code'     => 'f225',
						'keywords' => 'unlink',
					],
					'dashicons-editor-rtl' => [
						'code'     => 'f320',
						'keywords' => 'rtl',
					],
					'dashicons-editor-ltr' => [
						'code'     => 'f10c',
						'keywords' => 'ltr',
					],
					'dashicons-editor-break' => [
						'code'     => 'f474',
						'keywords' => 'break',
					],
					'dashicons-editor-code' => [
						'code'     => 'f475',
						'keywords' => 'code',
					],
					/* Duplicate
					'dashicons-editor-code-duplicate' => [
						'code'     => 'f494',
						'keywords' => 'code',
					],
					*/
					'dashicons-editor-paragraph' => [
						'code'     => 'f476',
						'keywords' => 'paragraph',
					],
					'dashicons-editor-table' => [
						'code'     => 'f535',
						'keywords' => 'table',
					],
				],
			],
		
			'posts screen' => [
				'label' => __( 'Posts Screen', 'wporg' ),
				'icons' => [
					'dashicons-align-left' => [
						'code'     => 'f135',
						'keywords' => 'align left',
					],
					'dashicons-align-right' => [
						'code'     => 'f136',
						'keywords' => 'align right',
					],
					'dashicons-align-center' => [
						'code'     => 'f134',
						'keywords' => 'align center',
					],
					'dashicons-align-none' => [
						'code'     => 'f138',
						'keywords' => 'align none',
					],
					'dashicons-lock' => [
						'code'     => 'f160',
						'keywords' => 'lock',
					],
					/* Duplicate
					'dashicons-lock-duplicate' => [
						'code'     => 'f315',
						'keywords' => 'lock',
					],
					*/
					'dashicons-unlock' => [
						'code'     => 'f528',
						'keywords' => 'unlock',
					],
					'dashicons-calendar' => [
						'code'     => 'f145',
						'keywords' => 'calendar',
					],
					'dashicons-calendar-alt' => [
						'code'     => 'f508',
						'keywords' => 'calendar',
					],
					'dashicons-visibility' => [
						'code'     => 'f177',
						'keywords' => 'visibility',
					],
					'dashicons-hidden' => [
						'code'     => 'f530',
						'keywords' => 'hidden',
					],
					'dashicons-post-status' => [
						'code'     => 'f173',
						'keywords' => 'post status',
					],
					'dashicons-edit' => [
						'code'     => 'f464',
						'keywords' => 'edit pencil',
					],
					'dashicons-trash' => [
						'code'     => 'f182',
						'keywords' => 'trash remove delete',
					],
					'dashicons-sticky' => [
						'code'     => 'f537',
						'keywords' => 'sticky',
					],
				],
			],
		
			'sorting' => [
				'label' => __( 'Sorting', 'wporg' ),
				'icons' => [
					'dashicons-external' => [
						'code'     => 'f504',
						'keywords' => 'external',
					],
					'dashicons-arrow-up' => [
						'code'     => 'f142',
						'keywords' => 'arrow up',
					],
					/* Duplicate
					'dashicons-arrow-up-duplicate' => [
						'code'     => 'f143',
						'keywords' => 'arrow up duplicate',
					],
					*/
					'dashicons-arrow-down' => [
						'code'     => 'f140',
						'keywords' => 'arrow down',
					],
					'dashicons-arrow-right' => [
						'code'     => 'f139',
						'keywords' => 'arrow right',
					],
					'dashicons-arrow-left' => [
						'code'     => 'f141',
						'keywords' => 'arrow left',
					],
					'dashicons-arrow-up-alt' => [
						'code'     => 'f342',
						'keywords' => 'arrow up',
					],
					'dashicons-arrow-down-alt' => [
						'code'     => 'f346',
						'keywords' => 'arrow down',
					],
					'dashicons-arrow-right-alt' => [
						'code'     => 'f344',
						'keywords' => 'arrow right',
					],
					'dashicons-arrow-left-alt' => [
						'code'     => 'f340',
						'keywords' => 'arrow left',
					],
					'dashicons-arrow-up-alt2' => [
						'code'     => 'f343',
						'keywords' => 'arrow up',
					],
					'dashicons-arrow-down-alt2' => [
						'code'     => 'f347',
						'keywords' => 'arrow down',
					],
					'dashicons-arrow-right-alt2' => [
						'code'     => 'f345',
						'keywords' => 'arrow right',
					],
					'dashicons-arrow-left-alt2' => [
						'code'     => 'f341',
						'keywords' => 'arrow left',
					],
					'dashicons-sort' => [
						'code'     => 'f156',
						'keywords' => 'sort',
					],
					'dashicons-leftright' => [
						'code'     => 'f229',
						'keywords' => 'left right',
					],
					'dashicons-randomize' => [
						'code'     => 'f503',
						'keywords' => 'randomize shuffle',
					],
					'dashicons-list-view' => [
						'code'     => 'f163',
						'keywords' => 'list view',
					],
					'dashicons-excerpt-view' => [
						'code'     => 'f164',
						'keywords' => 'excerpt view',
					],
					'dashicons-grid-view' => [
						'code'     => 'f509',
						'keywords' => 'grid view',
					],
					'dashicons-move' => [
						'code'     => 'f545',
						'keywords' => 'move',
					],
				],
			],
		
			'social' => [
				'label' => __( 'Social', 'wporg' ),
				'icons' => [
					'dashicons-share' => [
						'code'     => 'f237',
						'keywords' => 'share',
					],
					'dashicons-share-alt' => [
						'code'     => 'f240',
						'keywords' => 'share',
					],
					'dashicons-share-alt2' => [
						'code'     => 'f242',
						'keywords' => 'share',
					],
					'dashicons-rss' => [
						'code'     => 'f303',
						'keywords' => 'rss',
					],
					'dashicons-email' => [
						'code'     => 'f465',
						'keywords' => 'email',
					],
					'dashicons-email-alt' => [
						'code'     => 'f466',
						'keywords' => 'email (alt)',
					],
					'dashicons-email-alt2' => [
						'code'     => 'f467',
						'keywords' => 'email (alt2)',
					],
					'dashicons-networking' => [
						'code'     => 'f325',
						'keywords' => 'networking social',
					],
					'dashicons-amazon' => [
						'code'     => 'f162',
						'keywords' => 'amazon',
					],
					'dashicons-facebook' => [
						'code'     => 'f304',
						'keywords' => 'facebook social',
					],
					'dashicons-facebook-alt' => [
						'code'     => 'f305',
						'keywords' => 'facebook social',
					],
					'dashicons-google' => [
						'code'     => 'f18b',
						'keywords' => 'google social',
					],
					/* Defunct
					'dashicons-googleplus' => [
						'code'     => 'f462',
						'keywords' => 'googleplus social',
					],
					*/
					'dashicons-instagram' => [
						'code'     => 'f12d',
						'keywords' => 'instagram social',
					],
					'dashicons-linkedin' => [
						'code'     => 'f18d',
						'keywords' => 'linkedin social',
					],
					'dashicons-pinterest' => [
						'code'     => 'f192',
						'keywords' => 'pinterest social',
					],
					'dashicons-podio' => [
						'code'     => 'f19c',
						'keywords' => 'podio',
					],
					'dashicons-reddit' => [
						'code'     => 'f195',
						'keywords' => 'reddit social',
					],
					'dashicons-spotify' => [
						'code'     => 'f196',
						'keywords' => 'spotify social',
					],
					'dashicons-twitch' => [
						'code'     => 'f199',
						'keywords' => 'twitch social',
					],
					'dashicons-twitter' => [
						'code'     => 'f301',
						'keywords' => 'twitter social',
					],
					'dashicons-twitter-alt' => [
						'code'     => 'f302',
						'keywords' => 'twitter social',
					],
					'dashicons-whatsapp' => [
						'code'     => 'f19a',
						'keywords' => 'whatsapp social',
					],
					'dashicons-xing' => [
						'code'     => 'f19d',
						'keywords' => 'xing',
					],
					'dashicons-youtube' => [
						'code'     => 'f19b',
						'keywords' => 'youtube social',
					],
				],
			],
		
			'WordPress.org' => [
				'label' => __( 'WordPress.org Specific: Jobs, Profiles, WordCamps', 'wporg' ),
				'icons' => [
					'dashicons-hammer' => [
						'code'     => 'f308',
						'keywords' => 'hammer development',
					],
					'dashicons-art' => [
						'code'     => 'f309',
						'keywords' => 'art design',
					],
					'dashicons-migrate' => [
						'code'     => 'f310',
						'keywords' => 'migrate migration',
					],
					'dashicons-performance' => [
						'code'     => 'f311',
						'keywords' => 'performance',
					],
					'dashicons-universal-access' => [
						'code'     => 'f483',
						'keywords' => 'universal access accessibility',
					],
					'dashicons-universal-access-alt' => [
						'code'     => 'f507',
						'keywords' => 'universal access accessibility',
					],
					'dashicons-tickets' => [
						'code'     => 'f486',
						'keywords' => 'tickets',
					],
					'dashicons-nametag' => [
						'code'     => 'f484',
						'keywords' => 'nametag',
					],
					'dashicons-clipboard' => [
						'code'     => 'f481',
						'keywords' => 'clipboard',
					],
					'dashicons-heart' => [
						'code'     => 'f487',
						'keywords' => 'heart',
					],
					'dashicons-megaphone' => [
						'code'     => 'f488',
						'keywords' => 'megaphone',
					],
					'dashicons-schedule' => [
						'code'     => 'f489',
						'keywords' => 'schedule',
					],
					'dashicons-tide' => [
						'code'     => 'f10d',
						'keywords' => 'Tide',
					],
					'dashicons-rest-api' => [
						'code'     => 'f124',
						'keywords' => 'REST API',
					],
					'dashicons-code-standards' => [
						'code'     => 'f13a',
						'keywords' => 'code standards',
					],
				],
			],
		
			'buddicons' => [
				'label' => __( 'Buddicons', 'wporg' ),
				'icons' => [
					'dashicons-buddicons-activity' => [
						'code'     => 'f452',
						'keywords' => 'activity',
					],
					'dashicons-buddicons-bbpress-logo' => [
						'code'     => 'f477',
						'keywords' => 'bbPress logo',
					],
					'dashicons-buddicons-buddypress-logo' => [
						'code'     => 'f448',
						'keywords' => 'BuddyPress logo',
					],
					'dashicons-buddicons-community' => [
						'code'     => 'f453',
						'keywords' => 'community',
					],
					'dashicons-buddicons-forums' => [
						'code'     => 'f449',
						'keywords' => 'forums',
					],
					'dashicons-buddicons-friends' => [
						'code'     => 'f454',
						'keywords' => 'friends',
					],
					'dashicons-buddicons-groups' => [
						'code'     => 'f456',
						'keywords' => 'groups',
					],
					'dashicons-buddicons-pm' => [
						'code'     => 'f457',
						'keywords' => 'private message',
					],
					'dashicons-buddicons-replies' => [
						'code'     => 'f451',
						'keywords' => 'replies',
					],
					'dashicons-buddicons-topics' => [
						'code'     => 'f450',
						'keywords' => 'topics',
					],
					'dashicons-buddicons-tracking' => [
						'code'     => 'f455',
						'keywords' => 'tracking',
					],
				],
			],
		
			'products' => [
				'label' => __( 'Products', 'wporg' ),
				'icons' => [
					'dashicons-wordpress' => [
						'code'     => 'f120',
						'keywords' => 'WordPress',
					],
					'dashicons-wordpress-alt' => [
						'code'     => 'f324',
						'keywords' => 'WordPress',
					],
					'dashicons-pressthis' => [
						'code'     => 'f157',
						'keywords' => 'press this',
					],
					'dashicons-update' => [
						'code'     => 'f463',
						'keywords' => 'update',
					],
					'dashicons-update-alt' => [
						'code'     => 'f113',
						'keywords' => 'update (alt)',
					],
					'dashicons-screenoptions' => [
						'code'     => 'f180',
						'keywords' => 'screenoptions',
					],
					'dashicons-info' => [
						'code'     => 'f348',
						'keywords' => 'info',
					],
					'dashicons-cart' => [
						'code'     => 'f174',
						'keywords' => 'cart shopping',
					],
					'dashicons-feedback' => [
						'code'     => 'f175',
						'keywords' => 'feedback form',
					],
					'dashicons-cloud' => [
						'code'     => 'f176',
						'keywords' => 'cloud',
					],
					'dashicons-translation' => [
						'code'     => 'f326',
						'keywords' => 'translation language',
					],
				],
			],
		
			'taxonomies' => [
				'label' => __( 'Taxonomies', 'wporg' ),
				'icons' => [
					'dashicons-tag' => [
						'code'     => 'f323',
						'keywords' => 'tag',
					],
					'dashicons-category' => [
						'code'     => 'f318',
						'keywords' => 'category',
					],
				],
			],
		
			'widgets' => [
				'label' => __( 'Widgets', 'wporg' ),
				'icons' => [
					'dashicons-archive' => [
						'code'     => 'f480',
						'keywords' => 'archive',
					],
					'dashicons-tagcloud' => [
						'code'     => 'f479',
						'keywords' => 'tagcloud',
					],
					'dashicons-text' => [
						'code'     => 'f478',
						'keywords' => 'text',
					],
				],
			],
		
			'notifications' => [
				'label' => __( 'Notifications', 'wporg' ),
				'icons' => [
					'dashicons-bell' => [
						'code'     => 'f16d',
						'keywords' => 'bell',
					],
					'dashicons-yes' => [
						'code'     => 'f147',
						'keywords' => 'yes check checkmark',
					],
					'dashicons-yes-alt' => [
						'code'     => 'f12a',
						'keywords' => 'yes check checkmark (alt)',
					],
					'dashicons-no' => [
						'code'     => 'f158',
						'keywords' => 'no x',
					],
					'dashicons-no-alt' => [
						'code'     => 'f335',
						'keywords' => 'no x',
					],
					'dashicons-plus' => [
						'code'     => 'f132',
						'keywords' => 'plus add increase',
					],
					'dashicons-plus-alt' => [
						'code'     => 'f502',
						'keywords' => 'plus add increase',
					],
					'dashicons-plus-alt2' => [
						'code'     => 'f543',
						'keywords' => 'plus add increase',
					],
					'dashicons-minus' => [
						'code'     => 'f460',
						'keywords' => 'minus decrease',
					],
					'dashicons-dismiss' => [
						'code'     => 'f153',
						'keywords' => 'dismiss',
					],
					'dashicons-marker' => [
						'code'     => 'f159',
						'keywords' => 'marker',
					],
					'dashicons-star-filled' => [
						'code'     => 'f155',
						'keywords' => 'filled star',
					],
					'dashicons-star-half' => [
						'code'     => 'f459',
						'keywords' => 'half star',
					],
					'dashicons-star-empty' => [
						'code'     => 'f154',
						'keywords' => 'empty star',
					],
					'dashicons-flag' => [
						'code'     => 'f227',
						'keywords' => 'flag',
					],
					'dashicons-warning' => [
						'code'     => 'f534',
						'keywords' => 'warning',
					],
				],
			],
		
			'miscellaneous' => [
				'label' => __( 'Miscellaneous', 'wporg' ),
				'icons' => [
					'dashicons-location' => [
						'code'     => 'f230',
						'keywords' => 'location pin',
					],
					'dashicons-location-alt' => [
						'code'     => 'f231',
						'keywords' => 'location',
					],
					'dashicons-vault' => [
						'code'     => 'f178',
						'keywords' => 'vault safe',
					],
					'dashicons-shield' => [
						'code'     => 'f332',
						'keywords' => 'shield',
					],
					'dashicons-shield-alt' => [
						'code'     => 'f334',
						'keywords' => 'shield',
					],
					'dashicons-sos' => [
						'code'     => 'f468',
						'keywords' => 'sos help',
					],
					'dashicons-search' => [
						'code'     => 'f179',
						'keywords' => 'search',
					],
					'dashicons-slides' => [
						'code'     => 'f181',
						'keywords' => 'slides',
					],
					'dashicons-text-page' => [
						'code'     => 'f121',
						'keywords' => 'text page',
					],
					'dashicons-analytics' => [
						'code'     => 'f183',
						'keywords' => 'analytics',
					],
					'dashicons-chart-pie' => [
						'code'     => 'f184',
						'keywords' => 'pie chart',
					],
					'dashicons-chart-bar' => [
						'code'     => 'f185',
						'keywords' => 'bar chart',
					],
					'dashicons-chart-line' => [
						'code'     => 'f238',
						'keywords' => 'line chart',
					],
					'dashicons-chart-area' => [
						'code'     => 'f239',
						'keywords' => 'area chart',
					],
					'dashicons-groups' => [
						'code'     => 'f307',
						'keywords' => 'groups',
					],
					'dashicons-businessman' => [
						'code'     => 'f338',
						'keywords' => 'businessman',
					],
					'dashicons-businesswoman' => [
						'code'     => 'f12f',
						'keywords' => 'businesswoman',
					],
					'dashicons-businessperson' => [
						'code'     => 'f12e',
						'keywords' => 'businessperson',
					],
					'dashicons-id' => [
						'code'     => 'f336',
						'keywords' => 'id',
					],
					'dashicons-id-alt' => [
						'code'     => 'f337',
						'keywords' => 'id',
					],
					'dashicons-products' => [
						'code'     => 'f312',
						'keywords' => 'products',
					],
					'dashicons-awards' => [
						'code'     => 'f313',
						'keywords' => 'awards',
					],
					'dashicons-forms' => [
						'code'     => 'f314',
						'keywords' => 'forms',
					],
					'dashicons-testimonial' => [
						'code'     => 'f473',
						'keywords' => 'testimonial',
					],
					'dashicons-portfolio' => [
						'code'     => 'f322',
						'keywords' => 'portfolio',
					],
					'dashicons-book' => [
						'code'     => 'f330',
						'keywords' => 'book',
					],
					'dashicons-book-alt' => [
						'code'     => 'f331',
						'keywords' => 'book',
					],
					'dashicons-download' => [
						'code'     => 'f316',
						'keywords' => 'download',
					],
					'dashicons-upload' => [
						'code'     => 'f317',
						'keywords' => 'upload',
					],
					'dashicons-backup' => [
						'code'     => 'f321',
						'keywords' => 'backup',
					],
					'dashicons-clock' => [
						'code'     => 'f469',
						'keywords' => 'clock',
					],
					'dashicons-lightbulb' => [
						'code'     => 'f339',
						'keywords' => 'lightbulb',
					],
					'dashicons-microphone' => [
						'code'     => 'f482',
						'keywords' => 'microphone mic',
					],
					'dashicons-desktop' => [
						'code'     => 'f472',
						'keywords' => 'desktop monitor',
					],
					'dashicons-laptop' => [
						'code'     => 'f547',
						'keywords' => 'laptop',
					],
					'dashicons-tablet' => [
						'code'     => 'f471',
						'keywords' => 'tablet ipad',
					],
					'dashicons-smartphone' => [
						'code'     => 'f470',
						'keywords' => 'smartphone iphone',
					],
					'dashicons-phone' => [
						'code'     => 'f525',
						'keywords' => 'phone',
					],
					'dashicons-index-card' => [
						'code'     => 'f510',
						'keywords' => 'index card',
					],
					'dashicons-carrot' => [
						'code'     => 'f511',
						'keywords' => 'carrot food vendor',
					],
					'dashicons-building' => [
						'code'     => 'f512',
						'keywords' => 'building',
					],
					'dashicons-store' => [
						'code'     => 'f513',
						'keywords' => 'store',
					],
					'dashicons-album' => [
						'code'     => 'f514',
						'keywords' => 'album',
					],
					'dashicons-palmtree' => [
						'code'     => 'f527',
						'keywords' => 'palm tree',
					],
					'dashicons-tickets-alt' => [
						'code'     => 'f524',
						'keywords' => 'tickets (alt)',
					],
					'dashicons-money' => [
						'code'     => 'f526',
						'keywords' => 'money',
					],
					'dashicons-money-alt' => [
						'code'     => 'f18e',
						'keywords' => 'money alt',
					],
					'dashicons-smiley' => [
						'code'     => 'f328',
						'keywords' => 'smiley smile',
					],
					'dashicons-thumbs-up' => [
						'code'     => 'f529',
						'keywords' => 'thumbs up',
					],
					'dashicons-thumbs-down' => [
						'code'     => 'f542',
						'keywords' => 'thumbs down',
					],
					'dashicons-layout' => [
						'code'     => 'f538',
						'keywords' => 'layout',
					],
					'dashicons-paperclip' => [
						'code'     => 'f546',
						'keywords' => 'paperclip',
					],
					'dashicons-color-picker' => [
						'code'     => 'f131',
						'keywords' => 'color picker',
					],
					'dashicons-edit-large' => [
						'code'     => 'f327',
						'keywords' => 'edit large',
					],
					'dashicons-edit-page' => [
						'code'     => 'f186',
						'keywords' => 'edit page',
					],
					'dashicons-airplane' => [
						'code'     => 'f15f',
						'keywords' => 'airplane',
					],
					'dashicons-bank' => [
						'code'     => 'f16a',
						'keywords' => 'bank',
					],
					'dashicons-beer' => [
						'code'     => 'f16c',
						'keywords' => 'beer',
					],
					'dashicons-calculator' => [
						'code'     => 'f16e',
						'keywords' => 'calculator',
					],
					'dashicons-car' => [
						'code'     => 'f16b',
						'keywords' => 'car',
					],
					'dashicons-coffee' => [
						'code'     => 'f16f',
						'keywords' => 'coffee',
					],
					'dashicons-drumstick' => [
						'code'     => 'f17f',
						'keywords' => 'drumstick',
					],
					'dashicons-food' => [
						'code'     => 'f187',
						'keywords' => 'food',
					],
					'dashicons-fullscreen-alt' => [
						'code'     => 'f188',
						'keywords' => 'fullscreen alt',
					],
					'dashicons-fullscreen-exit-alt' => [
						'code'     => 'f189',
						'keywords' => 'fullscreen exit alt',
					],
					'dashicons-games' => [
						'code'     => 'f18a',
						'keywords' => 'games',
					],
					'dashicons-hourglass' => [
						'code'     => 'f18c',
						'keywords' => 'hourglass',
					],
					'dashicons-open-folder' => [
						'code'     => 'f18f',
						'keywords' => 'open folder',
					],
					'dashicons-pdf' => [
						'code'     => 'f190',
						'keywords' => 'pdf',
					],
					'dashicons-pets' => [
						'code'     => 'f191',
						'keywords' => 'pets',
					],
					'dashicons-printer' => [
						'code'     => 'f193',
						'keywords' => 'printer',
					],
					'dashicons-privacy' => [
						'code'     => 'f194',
						'keywords' => 'privacy',
					],
					'dashicons-superhero' => [
						'code'     => 'f198',
						'keywords' => 'superhero',
					],
					'dashicons-superhero-alt' => [
						'code'     => 'f197',
						'keywords' => 'superhero',
					],
				],
			],
		];
	}

} // Devhub_Dashicons
