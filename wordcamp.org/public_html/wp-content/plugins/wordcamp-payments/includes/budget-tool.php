<?php
class WordCamp_Budget_Tool {
    public static function load() {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 9 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
    }

    public static function admin_menu() {
		add_submenu_page( 'wordcamp-budget', __( 'WordCamp Budget', 'wordcamporg' ), __( 'Budget', 'wordcamporg' ), 'manage_options', 'wordcamp-budget' );
        add_action( 'wcb_render_budget_page', array( __CLASS__, 'render' ) );
    }

    public static function enqueue_scripts() {
        $screen = get_current_screen();
        if ( $screen->id == 'toplevel_page_wordcamp-budget' ) {
            wp_enqueue_script( 'backbone' );
        }
    }

    public static function render() {
        require( dirname( __DIR__ ) . '/views/budget-tool/main.php' );
    }
}

WordCamp_Budget_Tool::load();