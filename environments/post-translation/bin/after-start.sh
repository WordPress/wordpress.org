#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks, GlotPress, and sample content.
#

CONFIG="--config post-translation/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# Set up permalinks (required by GlotPress).
echo "Setting up permalinks..."
$WP wp rewrite structure '/%postname%/' --hard

# Create GlotPress database tables.
echo "Creating GlotPress tables..."
$WP wp eval "require_once ABSPATH . 'wp-admin/includes/upgrade.php'; require_once WP_PLUGIN_DIR . '/GlotPress/gp-includes/schema.php'; dbDelta( gp_schema_get() );"

# Grant the admin user GlotPress admin permissions.
echo "Setting up GlotPress admin..."
$WP wp eval "
if ( class_exists( 'GP' ) && class_exists( 'GP_Permission' ) ) {
    GP::\$permission->create( array(
        'user_id'     => 1,
        'action'      => 'admin',
        'object_type' => '',
        'object_id'   => '',
    ) );
}
"

# Create the parent 'Post Content' project with a Spanish translation set.
echo "Creating GlotPress projects..."
$WP wp eval "
if ( ! class_exists( 'GP' ) ) return;

\$parent = GP::\$project->by_path( 'post-content' );
if ( ! \$parent ) {
    \$parent = GP::\$project->create_and_select( array(
        'name'              => 'Post Content',
        'slug'              => 'post-content',
        'parent_project_id' => null,
        'description'       => 'Translations for WordPress post and page content.',
        'active'            => 1,
    ) );
    GP::\$translation_set->create( array(
        'project_id' => \$parent->id,
        'name'       => 'Spanish',
        'locale'     => 'es',
        'slug'       => 'default',
    ) );
    echo 'Created Post Content project with Spanish translation set.';
} else {
    echo 'Post Content project already exists.';
}
"

# Set site locale to Spanish so translations are applied on the frontend.
echo "Setting locale to Spanish..."
$WP wp language core install es_ES
$WP wp site switch-language es_ES

# Create sample content with translation enabled.
echo "Creating sample content..."
$WP wp eval "
\$existing = get_page_by_path( 'sample-translated-page' );
if ( \$existing ) {
    echo 'Sample content already exists.';
    return;
}

\$page_id = wp_insert_post( array(
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'About WordPress',
    'post_name'    => 'sample-translated-page',
    'post_content' => '<!-- wp:heading --><h2>Welcome</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>WordPress is open source software you can use to create a beautiful website, blog, or app.</p><!-- /wp:paragraph -->
<!-- wp:heading {\"level\":3} --><h3>Features</h3><!-- /wp:heading -->
<!-- wp:list --><ul><li>Easy to use</li><li>Flexible and extensible</li><li>Available in many languages</li></ul><!-- /wp:list -->
<!-- wp:quote --><blockquote class=\"wp-block-quote\"><p>Code is poetry.</p><cite>WordPress</cite></blockquote><!-- /wp:quote -->
<!-- wp:paragraph --><p>Get started today and join the millions who trust WordPress.</p><!-- /wp:paragraph -->',
) );

update_post_meta( \$page_id, '_post_translation_enabled', true );

echo 'Created sample page (ID: ' . \$page_id . ') with translation enabled.';
"

# Trigger the import cron to send strings to GlotPress.
echo "Importing strings to GlotPress..."
$WP wp cron event run --all > /dev/null 2>&1 || true

echo "Post Translation environment ready."
echo "  Sample page: http://localhost:8888/sample-translated-page/"
echo "  GlotPress:   http://localhost:8888/glotpress/"
