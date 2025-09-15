<?php

global $codex_contributors;

if ( is_singular( 'page' ) ) : ?>

<div class="footer-meta-wrap">
    <div class="footer-meta">
        <div class="col-half">

            <h2 class="title">Page Contributors</h2>

            <?php if ( count( $codex_contributors ) ) : ?>

                <div class="contributors">

                    <?php $codex_contributors = array_slice( $codex_contributors, 0, 3, true );

                    foreach ( (array) $codex_contributors as $contributor_id => $count ) :

                        $userdata = get_userdata( $contributor_id );

                        if ( empty( $userdata ) ) :
                            continue;
                        endif; ?>

                        <div class="contributor">
                            <a href="#">
                                <div class="contributor-avatar float-left">
                                    <?php echo get_avatar( $contributor_id, 48 ); ?>
                                    <?php echo '<div class="revision-count">' . esc_html( $count ) . '</div>'; ?>
                                    <?php echo '<div class="contributor-name"><span>' . esc_html( $userdata->display_name ) . '</span></div>'; ?>
                                </div>
                            </a>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <p class="date">Updated <strong><?php echo human_time_diff( get_the_modified_time( 'U', get_queried_object_id() ) ); ?></strong> ago / Published <strong><?php echo human_time_diff( get_the_time( 'U', get_queried_object_id() ) ); ?></strong> ago</p>

        </div>

        <div class="col-half">

            <h2 class="title">Want to help?</h2>

            <p>These sites are volunteer-powered which means you can contribute too! If you're interested in updating existing pages or creating entirely new ones, please read our <a href="https://codex.bbpress.org/participate-and-contribute/codex-standards-guidelines/">Standards & Guidelines</a>.</p>

        </div>
    </div>
</div>

<?php endif; ?>
