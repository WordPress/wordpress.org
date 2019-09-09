<?php namespace DevHub;
/**
 * BuddyPress Developer Theme functions.
 *
 * @package bporg-developer
 * @since 1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the needed styles.
 *
 * @since 1.0.0
 */
function bporg_developer_enqueue_styles() {
    wp_enqueue_style( 'wporg-developer', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'bporg-developer-main',
        get_stylesheet_directory_uri() . '/css/style.css',
        array( 'wp-dev-sass-compiled', 'dashicons' ),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\bporg_developer_enqueue_styles', 20 );

/**
 * Add the BP REST API handbook post type.
 *
 * @since 1.0.0
 *
 * @param  array $post_types The list of Handbook post types.
 * @return array The list of Handbook post types.
 */
function bporg_developer_register_rest_api( $post_types = array() ) {
    $post_types[] = 'bp-rest-api';
    return $post_types;
}
add_filter( 'handbook_post_types', __NAMESPACE__ . '\\bporg_developer_register_rest_api', 100, 1 );

/**
 * Override the generated Handbook post type label.
 *
 * @since 1.0.0
 *
 * @param  string $label     The generated Handbook post type label.
 * @param  string $post_type The current Handbook post type.
 * @return string The BP REST API post type label.
 */
function bporg_developer_set_rest_api_label( $label, $post_type ) {
    if ( 'bp-rest-api-handbook' === $post_type ) {
        $label = __( 'BP REST API Handbook', 'bporg-developer' );
    }

    return $label;
}
add_filter( 'handbook_label', __NAMESPACE__ . '\\bporg_developer_set_rest_api_label', 100, 2 );

/**
 * Init some starter content for the documentation site.
 *
 * @since 1.0.0.
 */
function bporg_developer_starter_content() {
    register_nav_menu( 'header-nav-menu', 'Main nav bar' );

    add_theme_support( 'title-tag' );

    add_theme_support( 'starter-content', array(
        'widgets' => array(
            // Place the BP REST API Chapters accordeon.
            'bp-rest-api-handbook' => array(
                'bp-rest-api-chapters' => array(
                    'handbook_pages',
                    array(
                        'title'  => __( 'Chapters', 'bporg-developer' ),
                    ),
                ),
            ),

            // Place a link to the codex.
            'landing-footer-1' => array(
                'more-resources' => array(
                    'text',
                    array(
                        'title'  => __( 'More resources', 'bporg-developer' ),
                        'text'   => sprintf( '<a href="https://codex.buddypress.org/">%s</a>', __( 'BuddyPress Codex', 'bporg-developer' ) ),
                        'filter' => true,
                        'visual' => true,
                    ),
                ),
            ),

            // Place a link to the support forums.
            'landing-footer-2' => array(
                'more-resources' => array(
                    'text',
                    array(
                        'title'  => __( 'Need help?', 'bporg-developer' ),
                        'text'   => sprintf( '<a href="https://buddypress.org/support/">%s</a>', __( 'BuddyPress support forums', 'bporg-developer' ) ),
                        'filter' => true,
                        'visual' => true,
                    ),
                ),
            ),
        ),

        // Create initial pages.
        'posts' => array(
            'landing' => array(
                'post_title' => __( 'Home', 'bporg-developer' ),
                'post_type'  => 'page',
                'post_name'  => 'landing',
                'template'   => 'page-home-landing.php',
            ),
            'reference' => array(
                'post_title' => __( 'Reference', 'bporg-developer' ),
                'post_type'  => 'page',
                'post_name'  => 'reference',
                'template'   => 'page-under-construction.php',
            ),
            'bp-rest-api' => array(
                'post_title'   => __( 'BP REST API Handbook', 'bporg-developer' ),
                'post_type'    => 'bp-rest-api-handbook',
                'post_name'    => 'bp-rest-api',
                'menu_order'   => 0,
                'post_content' => __( 'Let’s start documenting the BP REST API!', 'bporg-developer' ),
            ),
        ),

        // Default to a static front page and assign the front and posts pages.
        'options' => array(
            'show_on_front'  => 'page',
            'page_on_front'  => '{{landing}}',
        ),

        // Set the site title and description.
        'theme_mods'  => array(
            'blogname'        => __( 'BuddyPress Developer Resources', 'bporg-developer' ),
            'blogdescription' => __( 'Your best buddies ever to help you code.', 'bporg-developer' ),
        ),

        // Set up nav menus
        'nav_menus'   => array(
            // Assign a menu to the "header-nav-menu" location.
            'header-nav-menu' => array(
                'name'  => __( 'BuddyPress.org nav', 'bporg-developer' ),
                'items' => array(
                    'about' => array(
                        'type'  => 'custom',
                        'title' => __( 'About', 'bporg-developer' ),
                        'url'   => 'https://buddypress.org/about/',
                    ),
                    'plugins' => array(
                        'type'  => 'custom',
                        'title' => __( 'Plugins', 'bporg-developer' ),
                        'url'   => 'https://buddypress.org/plugins/',
                    ),
                    'themes' => array(
                        'type'  => 'custom',
                        'title' => __( 'Themes', 'bporg-developer' ),
                        'url'   => 'https://buddypress.org/themes/',
                    ),
                    'documentation' => array(
                        'type'  => 'custom',
                        'title' => __( 'Documentation', 'bporg-developer' ),
                        'url'   => 'https://codex.buddypress.org/',
                    ),
                    'blog' => array(
                        'type'  => 'custom',
                        'title' => __( 'Blog', 'bporg-developer' ),
                        'url'   => 'https://buddypress.org/blog/',
                    ),
                    'download' => array(
                        'type'  => 'custom',
                        'title' => __( 'Download', 'bporg-developer' ),
                        'url'   => 'https://buddypress.org/download/',
                    ),
                ),
            ),

            // Assign a menu to the "devhub-menu" location.
            'devhub-menu' => array(
                'name'  => __( 'DevHub menu', 'bporg-developer' ),
                'items' => array(
                    'link_home' => array(
                        'title' => __( 'All Developer Resources', 'bporg-developer' ),
                    ),
                ),
            ),

            // Assign a menu to the "reference-home-api" location.
            'reference-home-api' => array(
                'name'  => __( 'BuddyPress API', 'bporg-developer' ),
                'items' => array(
                    'bp-attachment' => array(
                        'type'  => 'custom',
                        'title' => __( 'BP Attachment API', 'bporg-developer' ),
                        'url'   => 'https://codex.buddypress.org/plugindev/bp_attachment/',
                    ),
                    'bp-theme-compat' => array(
                        'type'  => 'custom',
                        'title' => __( 'BP Theme Compat API', 'bporg-developer' ),
                        'url'   => 'https://codex.buddypress.org/plugindev/how-to-enjoy-bp-theme-compat-in-plugins/',
                    ),
                    'bp-component' => array(
                        'type'  => 'custom',
                        'title' => __( 'BP Component API', 'bporg-developer' ),
                        'url'   => 'https://codex.buddypress.org/developer/bp_component/',
                    ),
                    'bp-user-query' => array(
                        'type'  => 'custom',
                        'title' => __( 'BP User Query API', 'bporg-developer' ),
                        'url'   => 'https://codex.buddypress.org/developer/bp_user_query/',
                    ),
                    'bp-member-types' => array(
                        'type'  => 'custom',
                        'title' => __( 'Member Types API', 'bporg-developer' ),
                        'url'   => 'https://codex.buddypress.org/developer/member-types/',
                    ),
                    'bp-group-types' => array(
                        'type'  => 'custom',
                        'title' => __( 'Group Types API', 'bporg-developer' ),
                        'url'   => 'https://codex.buddypress.org/developer/group-types/',
                    ),
                    'bp-nav' => array(
                        'type'  => 'custom',
                        'title' => __( 'BP Nav API', 'bporg-developer' ),
                        'url'   => 'https://codex.buddypress.org/developer/navigation-api/',
                    ),
                    'bp-group-extension' => array(
                        'type'  => 'custom',
                        'title' => __( 'Group Extension API', 'bporg-developer' ),
                        'url'   => 'https://codex.buddypress.org/developer/group-extension-api/',
                    ),
                ),
            ),
        ),
    ) );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\\bporg_developer_starter_content' );

/**
 * Callback function to output the BP stable version input.
 *
 * @since 1.0.0
 */
function bporg_developer_version_setting_callback() {
    printf(
        '<input type="text" id="_bp_get_stable_version" name="_bp_get_stable_version" value="%s" class="regular-text ltr" />',
        esc_attr( get_option( '_bp_get_stable_version', '' ) )
    );
}

/**
 * Add a setting to set the latest BuddyPress stable version.
 *
 * @since 1.0.0
 */
function bporg_developer_version_setting() {
    register_setting( 'general', '_bp_get_stable_version', 'esc_attr' );

    add_settings_field(
        '_bp_get_stable_version',
        sprintf( '<label for="_bp_get_stable_version">%s</label>', esc_html__( 'BuddyPress latest stable version', 'bporg-developer' ) ),
        __NAMESPACE__ . '\\bporg_developer_version_setting_callback',
        'general'
    );
}
add_action( 'admin_init', __NAMESPACE__ . '\\bporg_developer_version_setting' );

/**
 * Get site section root URL based on URL path.
 *
 * @since 1.0.0
 *
 * @return string The section root URL.
 */
function bporg_developer_get_site_section_url() {
    $parts = explode( '/', $GLOBALS['wp']->request );
    switch ( $parts[0] ) {
        case 'reference':
        case 'plugins':
        case 'themes':
        case 'bp-rest-api':
            return home_url( '/' . $parts[0] . '/' );
        default:
            return apply_filters( 'bporg_developer_get_site_section_url', home_url( '/' ), $parts[0] );
    }
}

/**
 * Get site section title based on URL path.
 *
 * @since 1.0.0
 *
 * @return string The section title.
 */
function bporg_developer_get_site_section_title() {
    $parts = explode( '/', $GLOBALS['wp']->request );

    switch ( $parts[0] ) {
        case 'resources':
        case 'resource':
            return sprintf( __( 'Developer Resources: %s', 'bporg-developer' ), get_the_title() );
        case 'reference':
            return  __( 'Code Reference', 'bporg-developer' );
        case 'plugins':
            return __( 'Plugin Handbook', 'bporg-developer' );
        case 'themes':
            return __( 'Theme Handbook', 'bporg-developer' );
        case 'bp-rest-api':
            return __( 'BP REST API Handbook', 'bporg-developer' );
        default:
            return apply_filters( 'bporg_developer_get_site_section_title', __( 'Developer Resources', 'bporg-developer' ) );
    }
}

/**
 * Gets the current BP version.
 *
 * NB: used to generate the link to the function/class on BP Trac.
 *
 * @since 1.0.0
 *
 * @param  string $prefix          The prefix to append to the BP current version.
 * @param  bool   $ignore_unstable Whether to ignore unstable versions (eg: alpha,
 *                                 beta, RC).
 * @return string The version to use to build the Trac link.
 */
function bporg_developer_get_current_version( $prefix = 'tags/', $ignore_unstable = true ) {
    $version    = 'trunk';
    $bp_version = '';

    // BuddyPress might be active.
    if ( function_exists( 'bp_get_version' ) ) {
        $bp_version = bp_get_version();
    }

    // Try to get the option value.
    $bp_version = get_option( '_bp_get_stable_version', $bp_version );

    if ( $bp_version ) {
        if ( false !== strpos( $bp_version, '-' ) ) {
            if ( ! $ignore_unstable ) {
                list( $bp_version, $dev_version ) = explode( '-', $bp_version, 2 );
            } else {
                $bp_version = '';
            }
        }

        if ( $bp_version ) {
            $version = $prefix . $bp_version;
        }
    }

    return $version;
}

/**
 * Output the the URL to the actual source file and line.
 *
 * @since 1.0.0
 *
 * @param int  $post_id     The ID of the Code Reference post type.
 * @param bool $line_number Whether to include the line number anchor.
 */
function bporg_developer_source_file_link( $post_id = null, $line_number = true ) {
    echo esc_url( bporg_developer_get_source_file_link( $post_id, $line_number ) );
}

/**
 * Retrieve the URL to the actual source file and line.
 *
 * @since 1.0.0
 *
 * @param null $post_id     Post ID.
 * @param bool $line_number Whether to append the line number to the URL.
 *                          Default true.
 * @return string Source file URL with or without line number.
 */
function bporg_developer_get_source_file_link( $post_id = null, $line_number = true ) {

    $post_id = empty( $post_id ) ? get_the_ID() : $post_id;
    $url     = '';

    // Source file.
    $source_file = get_source_file( $post_id );
    if ( ! empty( $source_file ) ) {
        $bp_version = bporg_developer_get_current_version( 'tags/' );
        $url        = 'https://buddypress.trac.wordpress.org/browser/' . $bp_version . '/src/' . $source_file;
        // Line number.
        if ( $line_number = get_post_meta( get_the_ID(), '_wp-parser_line_num', true ) ) {
            $url .= "#L{$line_number}";
        }
    }

    return $url;
}

/**
 * Get current (latest) version of the parsed WP code as a wp-parser-since
 * term object.
 *
 * By default returns the major version (X.Y.0) term object because minor
 * releases rarely add enough, if any, new things to feature.
 *
 * For development versions, the development suffix ("-beta1", "-RC1") gets removed.
 *
 * @since 1.0.0
 *
 * @param  boolean $ignore_minor Use the major release version X.Y.0 instead of the actual version X.Y.Z?
 * @return object|WP_Error
 */
function bporg_developer_get_current_version_term( $ignore_minor = true ) {
    $current_version = bporg_developer_get_current_version( '' );

    if ( $ignore_minor ) {
        $version_parts = explode( '.', $current_version, 3 );
        if ( count( $version_parts ) == 2 ) {
            $version_parts[] = '0';
        } else {
            $version_parts[2] = '0';
        }
        $current_version = implode( '-', $version_parts );
    }

    $version = get_terms( 'wp-parser-since', array(
        'number' => '1',
        'order'  => 'DESC',
        'slug'   => $current_version,
    ) );

    return is_wp_error( $version ) ? $version : reset( $version );
}

if ( ! function_exists( 'wp_body_open' ) ) :
	/**
	 * Fire the wp_body_open action.
	 *
	 * Added for backwards compatibility to support pre 5.2.0 WordPress versions.
	 */
	function wp_body_open() {
		/**
		 * Triggered after the opening <body> tag.
		 */
		do_action( 'wp_body_open' );
	}
endif;
