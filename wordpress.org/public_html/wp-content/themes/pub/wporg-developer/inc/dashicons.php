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
						'keywords' => 'menu admin',
					],
					'dashicons-menu-alt' => [
						'code'     => 'f228',
						'keywords' => 'menu alt admin',
					],
					'dashicons-menu-alt2' => [
						'code'     => 'f329',
						'keywords' => 'menu alt admin',
					],
					'dashicons-menu-alt3' => [
						'code'     => 'f349',
						'keywords' => 'menu alt admin',
					],
					'dashicons-admin-site' => [
						'code'     => 'f319',
						'keywords' => 'site admin',
					],
					'dashicons-admin-site-alt' => [
						'code'     => 'f11d',
						'keywords' => 'site alt admin',
					],
					'dashicons-admin-site-alt2' => [
						'code'     => 'f11e',
						'keywords' => 'site alt admin',
					],
					'dashicons-admin-site-alt3' => [
						'code'     => 'f11f',
						'keywords' => 'site alt admin',
					],
					'dashicons-dashboard' => [
						'code'     => 'f226',
						'keywords' => 'dashboard admin',
					],
					'dashicons-admin-post' => [
						'code'     => 'f109',
						'keywords' => 'post admin',
					],
					'dashicons-admin-media' => [
						'code'     => 'f104',
						'keywords' => 'media admin',
					],
					'dashicons-admin-links' => [
						'code'     => 'f103',
						'keywords' => 'links admin',
					],
					'dashicons-admin-page' => [
						'code'     => 'f105',
						'keywords' => 'page admin',
					],
					'dashicons-admin-comments' => [
						'code'     => 'f101',
						'keywords' => 'comments admin',
					],
					'dashicons-admin-appearance' => [
						'code'     => 'f100',
						'keywords' => 'appearance admin',
					],
					'dashicons-admin-plugins' => [
						'code'     => 'f106',
						'keywords' => 'plugins admin',
					],
					'dashicons-plugins-checked' => [
						'code'     => 'f485',
						'keywords' => 'plugins checked admin',
					],
					'dashicons-admin-users' => [
						'code'     => 'f110',
						'keywords' => 'users admin',
					],
					'dashicons-admin-tools' => [
						'code'     => 'f107',
						'keywords' => 'tools admin',
					],
					'dashicons-admin-settings' => [
						'code'     => 'f108',
						'keywords' => 'settings admin',
					],
					'dashicons-admin-network' => [
						'code'     => 'f112',
						'keywords' => 'network admin',
					],
					'dashicons-admin-home' => [
						'code'     => 'f102',
						'keywords' => 'home admin',
					],
					'dashicons-admin-generic' => [
						'code'     => 'f111',
						'keywords' => 'generic admin',
					],
					'dashicons-admin-collapse' => [
						'code'     => 'f148',
						'keywords' => 'collapse admin',
					],
					'dashicons-filter' => [
						'code'     => 'f536',
						'keywords' => 'filter admin',
					],
					'dashicons-admin-customizer' => [
						'code'     => 'f540',
						'keywords' => 'customizer admin',
					],
					'dashicons-admin-multisite' => [
						'code'     => 'f541',
						'keywords' => 'multisite admin',
					],
				],
			],
		
			'welcome screen' => [
				'label' => __( 'Welcome Screen', 'wporg' ),
				'icons' => [
					'dashicons-welcome-write-blog' => [
						'code'     => 'f119',
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
						'keywords' => 'add page welcome',
					],
					'dashicons-welcome-view-site' => [
						'code'     => 'f115',
						'keywords' => 'view site welcome',
					],
					'dashicons-welcome-widgets-menus' => [
						'code'     => 'f116',
						'keywords' => 'widgets menus welcome',
					],
					'dashicons-welcome-comments' => [
						'code'     => 'f117',
						'keywords' => 'comments welcome',
					],
					'dashicons-welcome-learn-more' => [
						'code'     => 'f118',
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
						'keywords' => 'aside format',
					],
					'dashicons-format-image' => [
						'code'     => 'f128',
						'keywords' => 'image format',
					],
					'dashicons-format-gallery' => [
						'code'     => 'f161',
						'keywords' => 'gallery format',
					],
					'dashicons-format-video' => [
						'code'     => 'f126',
						'keywords' => 'video format',
					],
					'dashicons-format-status' => [
						'code'     => 'f130',
						'keywords' => 'status format',
					],
					'dashicons-format-quote' => [
						'code'     => 'f122',
						'keywords' => 'quote format',
					],
					/* Duplicate
					'dashicons-format-links' => [
						'code'     => 'f103',
						'keywords' => '',
					],
					*/
					'dashicons-format-chat' => [
						'code'     => 'f125',
						'keywords' => 'chat format',
					],
					'dashicons-format-audio' => [
						'code'     => 'f127',
						'keywords' => 'audio format',
					],
					'dashicons-camera' => [
						'code'     => 'f306',
						'keywords' => 'camera format',
					],
					'dashicons-camera-alt' => [
						'code'     => 'f129',
						'keywords' => 'camera alt format',
					],
					'dashicons-images-alt' => [
						'code'     => 'f232',
						'keywords' => 'images alt format',
					],
					'dashicons-images-alt2' => [
						'code'     => 'f233',
						'keywords' => 'images alt format',
					],
					'dashicons-video-alt' => [
						'code'     => 'f234',
						'keywords' => 'video alt format',
					],
					'dashicons-video-alt2' => [
						'code'     => 'f235',
						'keywords' => 'video alt format',
					],
					'dashicons-video-alt3' => [
						'code'     => 'f236',
						'keywords' => 'video alt format',
					],
				],
			],
		
			'media' => [
				'label' => __( 'Media', 'wporg' ),
				'icons' => [
					'dashicons-media-archive' => [
						'code'     => 'f501',
						'keywords' => 'archive media',
					],
					'dashicons-media-audio' => [
						'code'     => 'f500',
						'keywords' => 'audio media',
					],
					'dashicons-media-code' => [
						'code'     => 'f499',
						'keywords' => 'code media',
					],
					'dashicons-media-default' => [
						'code'     => 'f498',
						'keywords' => 'default media',
					],
					'dashicons-media-document' => [
						'code'     => 'f497',
						'keywords' => 'document media',
					],
					'dashicons-media-interactive' => [
						'code'     => 'f496',
						'keywords' => 'interactive media',
					],
					'dashicons-media-spreadsheet' => [
						'code'     => 'f495',
						'keywords' => 'spreadsheet media',
					],
					'dashicons-media-text' => [
						'code'     => 'f491',
						'keywords' => 'text media',
					],
					'dashicons-media-video' => [
						'code'     => 'f490',
						'keywords' => 'video media',
					],
					'dashicons-playlist-audio' => [
						'code'     => 'f492',
						'keywords' => 'audio playlist media',
					],
					'dashicons-playlist-video' => [
						'code'     => 'f493',
						'keywords' => 'video playlist media',
					],
					'dashicons-controls-play' => [
						'code'     => 'f522',
						'keywords' => 'play player controls media',
					],
					'dashicons-controls-pause' => [
						'code'     => 'f523',
						'keywords' => 'player pause controls media',
					],
					'dashicons-controls-forward' => [
						'code'     => 'f519',
						'keywords' => 'player forward controls media',
					],
					'dashicons-controls-skipforward' => [
						'code'     => 'f517',
						'keywords' => 'player skip forward controls media',
					],
					'dashicons-controls-back' => [
						'code'     => 'f518',
						'keywords' => 'player back controls media',
					],
					'dashicons-controls-skipback' => [
						'code'     => 'f516',
						'keywords' => 'player skip back controls media',
					],
					'dashicons-controls-repeat' => [
						'code'     => 'f515',
						'keywords' => 'player repeat controls media',
					],
					'dashicons-controls-volumeon' => [
						'code'     => 'f521',
						'keywords' => 'player volume on controls media',
					],
					'dashicons-controls-volumeoff' => [
						'code'     => 'f520',
						'keywords' => 'player volume off controls media',
					],
				],
			],
		
			'image editing' => [
				'label' => __( 'Image Editing', 'wporg' ),
				'icons' => [
					'dashicons-image-crop' => [
						'code'     => 'f165',
						'keywords' => 'crop image',
					],
					'dashicons-image-rotate' => [
						'code'     => 'f531',
						'keywords' => 'rotate image',
					],
					'dashicons-image-rotate-left' => [
						'code'     => 'f166',
						'keywords' => 'rotate left image',
					],
					'dashicons-image-rotate-right' => [
						'code'     => 'f167',
						'keywords' => 'rotate right image',
					],
					'dashicons-image-flip-vertical' => [
						'code'     => 'f168',
						'keywords' => 'flip vertical image',
					],
					'dashicons-image-flip-horizontal' => [
						'code'     => 'f169',
						'keywords' => 'flip horizontal image',
					],
					'dashicons-image-filter' => [
						'code'     => 'f533',
						'keywords' => 'filter image',
					],
					'dashicons-undo' => [
						'code'     => 'f171',
						'keywords' => 'undo image',
					],
					'dashicons-redo' => [
						'code'     => 'f172',
						'keywords' => 'redo image',
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
						'keywords' => 'align full width block',
					],
					'dashicons-align-pull-left' => [
						'code'     => 'f10a',
						'keywords' => 'align pull left block',
					],
					'dashicons-align-pull-right' => [
						'code'     => 'f10b',
						'keywords' => 'align pull right block',
					],
					'dashicons-align-wide' => [
						'code'     => 'f11b',
						'keywords' => 'align wide block',
					],
					'dashicons-block-default' => [
						'code'     => 'f12b',
						'keywords' => 'block default',
					],
					'dashicons-button' => [
						'code'     => 'f11a',
						'keywords' => 'button block',
					],
					'dashicons-cloud-saved' => [
						'code'     => 'f137',
						'keywords' => 'cloud saved block',
					],
					'dashicons-cloud-upload' => [
						'code'     => 'f13b',
						'keywords' => 'cloud upload block',
					],
					'dashicons-columns' => [
						'code'     => 'f13c',
						'keywords' => 'columns block',
					],
					'dashicons-cover-image' => [
						'code'     => 'f13d',
						'keywords' => 'cover image block',
					],
					'dashicons-ellipsis' => [
						'code'     => 'f11c',
						'keywords' => 'ellipsis block',
					],
					'dashicons-embed-audio' => [
						'code'     => 'f13e',
						'keywords' => 'embed audio block',
					],
					'dashicons-embed-generic' => [
						'code'     => 'f13f',
						'keywords' => 'embed generic block',
					],
					'dashicons-embed-photo' => [
						'code'     => 'f144',
						'keywords' => 'embed photo block',
					],
					'dashicons-embed-post' => [
						'code'     => 'f146',
						'keywords' => 'embed post block',
					],
					'dashicons-embed-video' => [
						'code'     => 'f149',
						'keywords' => 'embed video block',
					],
					'dashicons-exit' => [
						'code'     => 'f14a',
						'keywords' => 'exit block',
					],
					'dashicons-heading' => [
						'code'     => 'f10e',
						'keywords' => 'heading block',
					],
					'dashicons-html' => [
						'code'     => 'f14b',
						'keywords' => 'html block',
					],
					'dashicons-info-outline' => [
						'code'     => 'f14c',
						'keywords' => 'info outline block',
					],
					'dashicons-insert' => [
						'code'     => 'f10f',
						'keywords' => 'insert block',
					],
					'dashicons-insert-after' => [
						'code'     => 'f14d',
						'keywords' => 'insert after block',
					],
					'dashicons-insert-before' => [
						'code'     => 'f14e',
						'keywords' => 'insert before block',
					],
					'dashicons-remove' => [
						'code'     => 'f14f',
						'keywords' => 'remove block',
					],
					'dashicons-saved' => [
						'code'     => 'f15e',
						'keywords' => 'saved block',
					],
					'dashicons-shortcode' => [
						'code'     => 'f150',
						'keywords' => 'shortcode block',
					],
					'dashicons-table-col-after' => [
						'code'     => 'f151',
						'keywords' => 'table col after block',
					],
					'dashicons-table-col-before' => [
						'code'     => 'f152',
						'keywords' => 'table col before block',
					],
					'dashicons-table-col-delete' => [
						'code'     => 'f15a',
						'keywords' => 'table col delete block',
					],
					'dashicons-table-row-after' => [
						'code'     => 'f15b',
						'keywords' => 'table row after block',
					],
					'dashicons-table-row-before' => [
						'code'     => 'f15c',
						'keywords' => 'table row before block',
					],
					'dashicons-table-row-delete' => [
						'code'     => 'f15d',
						'keywords' => 'table row delete block',
					],
				],
			],
		
			'tinymce' => [
				'label' => __( 'TinyMCE', 'wporg' ),
				'icons' => [
					'dashicons-editor-bold' => [
						'code'     => 'f200',
						'keywords' => 'bold editor tinymce',
					],
					'dashicons-editor-italic' => [
						'code'     => 'f201',
						'keywords' => 'italic editor tinymce',
					],
					'dashicons-editor-ul' => [
						'code'     => 'f203',
						'keywords' => 'ul editor tinymce',
					],
					'dashicons-editor-ol' => [
						'code'     => 'f204',
						'keywords' => 'ol editor tinymce',
					],
					'dashicons-editor-ol-rtl' => [
						'code'     => 'f12c',
						'keywords' => 'ol rtl editor tinymce',
					],
					'dashicons-editor-quote' => [
						'code'     => 'f205',
						'keywords' => 'quote editor tinymce',
					],
					'dashicons-editor-alignleft' => [
						'code'     => 'f206',
						'keywords' => 'align left editor tinymce',
					],
					'dashicons-editor-aligncenter' => [
						'code'     => 'f207',
						'keywords' => 'aligncenter editor tinymce',
					],
					'dashicons-editor-alignright' => [
						'code'     => 'f208',
						'keywords' => 'align right editor tinymce',
					],
					'dashicons-editor-insertmore' => [
						'code'     => 'f209',
						'keywords' => 'insert more editor tinymce',
					],
					'dashicons-editor-spellcheck' => [
						'code'     => 'f210',
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
						'keywords' => 'expand editor tinymce',
					],
					'dashicons-editor-contract' => [
						'code'     => 'f506',
						'keywords' => 'contract editor tinymce',
					],
					'dashicons-editor-kitchensink' => [
						'code'     => 'f212',
						'keywords' => 'kitchen sink editor tinymce',
					],
					'dashicons-editor-underline' => [
						'code'     => 'f213',
						'keywords' => 'underline editor tinymce',
					],
					'dashicons-editor-justify' => [
						'code'     => 'f214',
						'keywords' => 'justify editor tinymce',
					],
					'dashicons-editor-textcolor' => [
						'code'     => 'f215',
						'keywords' => 'textcolor editor text color tinymce',
					],
					'dashicons-editor-paste-word' => [
						'code'     => 'f216',
						'keywords' => 'paste editor word tinymce',
					],
					'dashicons-editor-paste-text' => [
						'code'     => 'f217',
						'keywords' => 'paste editor text tinymce',
					],
					'dashicons-editor-removeformatting' => [
						'code'     => 'f218',
						'keywords' => 'remove formatting editor tinymce',
					],
					'dashicons-editor-video' => [
						'code'     => 'f219',
						'keywords' => 'video editor tinymce',
					],
					'dashicons-editor-customchar' => [
						'code'     => 'f220',
						'keywords' => 'custom character editor tinymce',
					],
					'dashicons-editor-outdent' => [
						'code'     => 'f221',
						'keywords' => 'outdent editor tinymce',
					],
					'dashicons-editor-indent' => [
						'code'     => 'f222',
						'keywords' => 'indent editor tinymce',
					],
					'dashicons-editor-help' => [
						'code'     => 'f223',
						'keywords' => 'help editor tinymce',
					],
					'dashicons-editor-strikethrough' => [
						'code'     => 'f224',
						'keywords' => 'strikethrough editor tinymce',
					],
					'dashicons-editor-unlink' => [
						'code'     => 'f225',
						'keywords' => 'unlink editor tinymce',
					],
					'dashicons-editor-rtl' => [
						'code'     => 'f320',
						'keywords' => 'rtl editor tinymce',
					],
					'dashicons-editor-ltr' => [
						'code'     => 'f10c',
						'keywords' => 'ltr editor tinymce',
					],
					'dashicons-editor-break' => [
						'code'     => 'f474',
						'keywords' => 'break editor tinymce',
					],
					'dashicons-editor-code' => [
						'code'     => 'f475',
						'keywords' => 'code editor tinymce',
					],
					/* Duplicate
					'dashicons-editor-code-duplicate' => [
						'code'     => 'f494',
						'keywords' => '',
					],
					*/
					'dashicons-editor-paragraph' => [
						'code'     => 'f476',
						'keywords' => 'paragraph editor tinymce',
					],
					'dashicons-editor-table' => [
						'code'     => 'f535',
						'keywords' => 'table editor tinymce',
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
						'keywords' => '',
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
						'keywords' => 'calendar alt',
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
						'keywords' => '',
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
						'keywords' => 'arrow up alt',
					],
					'dashicons-arrow-down-alt' => [
						'code'     => 'f346',
						'keywords' => 'arrow down alt',
					],
					'dashicons-arrow-right-alt' => [
						'code'     => 'f344',
						'keywords' => 'arrow right alt',
					],
					'dashicons-arrow-left-alt' => [
						'code'     => 'f340',
						'keywords' => 'arrow left alt',
					],
					'dashicons-arrow-up-alt2' => [
						'code'     => 'f343',
						'keywords' => 'arrow up alt',
					],
					'dashicons-arrow-down-alt2' => [
						'code'     => 'f347',
						'keywords' => 'arrow down alt',
					],
					'dashicons-arrow-right-alt2' => [
						'code'     => 'f345',
						'keywords' => 'arrow right alt',
					],
					'dashicons-arrow-left-alt2' => [
						'code'     => 'f341',
						'keywords' => 'arrow left alt',
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
						'keywords' => 'share social',
					],
					'dashicons-share-alt' => [
						'code'     => 'f240',
						'keywords' => 'share alt social',
					],
					'dashicons-share-alt2' => [
						'code'     => 'f242',
						'keywords' => 'share alt social',
					],
					'dashicons-rss' => [
						'code'     => 'f303',
						'keywords' => 'rss social',
					],
					'dashicons-email' => [
						'code'     => 'f465',
						'keywords' => 'email social',
					],
					'dashicons-email-alt' => [
						'code'     => 'f466',
						'keywords' => 'email alt social',
					],
					'dashicons-email-alt2' => [
						'code'     => 'f467',
						'keywords' => 'email alt social',
					],
					'dashicons-networking' => [
						'code'     => 'f325',
						'keywords' => 'networking social',
					],
					'dashicons-amazon' => [
						'code'     => 'f162',
						'keywords' => 'amazon social',
					],
					'dashicons-facebook' => [
						'code'     => 'f304',
						'keywords' => 'facebook social',
					],
					'dashicons-facebook-alt' => [
						'code'     => 'f305',
						'keywords' => 'facebook social alt',
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
						'keywords' => 'podio social',
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
						'keywords' => 'twitter social alt',
					],
					'dashicons-whatsapp' => [
						'code'     => 'f19a',
						'keywords' => 'whatsapp social',
					],
					'dashicons-xing' => [
						'code'     => 'f19d',
						'keywords' => 'xing social',
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
						'keywords' => 'universal access accessibility alt',
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
						'keywords' => 'activity buddicons',
					],
					'dashicons-buddicons-bbpress-logo' => [
						'code'     => 'f477',
						'keywords' => 'bbPress logo buddicons',
					],
					'dashicons-buddicons-buddypress-logo' => [
						'code'     => 'f448',
						'keywords' => 'BuddyPress logo buddicons',
					],
					'dashicons-buddicons-community' => [
						'code'     => 'f453',
						'keywords' => 'community buddicons',
					],
					'dashicons-buddicons-forums' => [
						'code'     => 'f449',
						'keywords' => 'forums buddicons',
					],
					'dashicons-buddicons-friends' => [
						'code'     => 'f454',
						'keywords' => 'friends buddicons',
					],
					'dashicons-buddicons-groups' => [
						'code'     => 'f456',
						'keywords' => 'groups buddicons',
					],
					'dashicons-buddicons-pm' => [
						'code'     => 'f457',
						'keywords' => 'private message buddicons pm',
					],
					'dashicons-buddicons-replies' => [
						'code'     => 'f451',
						'keywords' => 'replies buddicons',
					],
					'dashicons-buddicons-topics' => [
						'code'     => 'f450',
						'keywords' => 'topics buddicons',
					],
					'dashicons-buddicons-tracking' => [
						'code'     => 'f455',
						'keywords' => 'tracking buddicons',
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
						'keywords' => 'WordPress alt',
					],
					'dashicons-pressthis' => [
						'code'     => 'f157',
						'keywords' => 'pressthis',
					],
					'dashicons-update' => [
						'code'     => 'f463',
						'keywords' => 'update',
					],
					'dashicons-update-alt' => [
						'code'     => 'f113',
						'keywords' => 'update alt',
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
						'keywords' => 'tag taxonomy',
					],
					'dashicons-category' => [
						'code'     => 'f318',
						'keywords' => 'category taxonomy',
					],
				],
			],
		
			'widgets' => [
				'label' => __( 'Widgets', 'wporg' ),
				'icons' => [
					'dashicons-archive' => [
						'code'     => 'f480',
						'keywords' => 'archive widget',
					],
					'dashicons-tagcloud' => [
						'code'     => 'f479',
						'keywords' => 'tagcloud widget',
					],
					'dashicons-text' => [
						'code'     => 'f478',
						'keywords' => 'text widget',
					],
				],
			],
		
			'notifications' => [
				'label' => __( 'Notifications', 'wporg' ),
				'icons' => [
					'dashicons-bell' => [
						'code'     => 'f16d',
						'keywords' => 'bell notifications',
					],
					'dashicons-yes' => [
						'code'     => 'f147',
						'keywords' => 'yes check checkmark notifications',
					],
					'dashicons-yes-alt' => [
						'code'     => 'f12a',
						'keywords' => 'yes check checkmark alt notifications',
					],
					'dashicons-no' => [
						'code'     => 'f158',
						'keywords' => 'no x notifications',
					],
					'dashicons-no-alt' => [
						'code'     => 'f335',
						'keywords' => 'no x alt notifications',
					],
					'dashicons-plus' => [
						'code'     => 'f132',
						'keywords' => 'plus add increase notifications',
					],
					'dashicons-plus-alt' => [
						'code'     => 'f502',
						'keywords' => 'plus add increase alt notifications',
					],
					'dashicons-plus-alt2' => [
						'code'     => 'f543',
						'keywords' => 'plus add increase alt notifications',
					],
					'dashicons-minus' => [
						'code'     => 'f460',
						'keywords' => 'minus decrease notifications',
					],
					'dashicons-dismiss' => [
						'code'     => 'f153',
						'keywords' => 'dismiss notifications',
					],
					'dashicons-marker' => [
						'code'     => 'f159',
						'keywords' => 'marker notifications',
					],
					'dashicons-star-filled' => [
						'code'     => 'f155',
						'keywords' => 'filled star notifications',
					],
					'dashicons-star-half' => [
						'code'     => 'f459',
						'keywords' => 'half star notifications',
					],
					'dashicons-star-empty' => [
						'code'     => 'f154',
						'keywords' => 'empty star notifications',
					],
					'dashicons-flag' => [
						'code'     => 'f227',
						'keywords' => 'flag notifications',
					],
					'dashicons-warning' => [
						'code'     => 'f534',
						'keywords' => 'warning notifications',
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
						'keywords' => 'location alt',
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
						'keywords' => 'shield alt',
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
						'keywords' => 'id alt',
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
						'keywords' => 'book alt',
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
						'keywords' => 'tickets alt',
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
						'keywords' => 'superhero alt',
					],
				],
			],
		];
	}

} // Devhub_Dashicons
