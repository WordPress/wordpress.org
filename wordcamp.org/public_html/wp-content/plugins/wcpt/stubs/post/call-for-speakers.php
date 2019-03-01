<!-- wp:paragraph {"customBackgroundColor":"#eeeeee"} -->
<p style="background-color:#eeeeee" class="has-background"><?php _e( '<em>Organizers note:</em> Submissions to this form will automatically create draft posts for the Speaker and Session post types. Feel free to customize the form, but deleting or renaming the following fields will break the automation: Name, Email, WordPress.org Username, Your Bio, Session Title, Session Description.', 'wordcamporg' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php _e( "If you'd like to propose multiple topics, please submit the form multiple times, once for each topic. [Other speaker instructions/info goes here.]", 'wordcamporg' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:jetpack/contact-form {"subject":"<?php esc_attr_e( 'WordCamp Speaker Request', 'wordcamporg' ); ?>","hasFormSettingsSet":"yes"} -->
<!-- wp:jetpack/field-name {"label":"<?php esc_attr_e( 'Name', 'wordcamporg' ); ?>","required":true} /-->

<!-- wp:jetpack/field-email {"label":"<?php esc_attr_e( 'Email Address', 'wordcamporg' ); ?>","required":true} /-->

<!-- wp:jetpack/field-text {"label":"<?php esc_attr_e( 'WordPress.org Username', 'wordcamporg' ); ?>","required":true} /-->

<!-- wp:jetpack/field-textarea {"label":"<?php esc_attr_e( 'Your Bio', 'wordcamporg' ); ?>","required":true} /-->

<!-- wp:jetpack/field-text {"label":"<?php esc_attr_e( 'Topic Title', 'wordcamporg' ); ?>","required":true} /-->

<!-- wp:jetpack/field-textarea {"label":"<?php esc_attr_e( 'Topic Description', 'wordcamporg' ); ?>","required":true} /-->

<!-- wp:jetpack/field-text {"label":"<?php esc_attr_e( 'Intended Audience', 'wordcamporg' ); ?>","required":true} /-->

<!-- wp:jetpack/field-textarea {"label":"<?php esc_attr_e( 'Past Speaking Experience (not necessary to apply)', 'wordcamporg' ); ?>"} /-->
<!-- /wp:jetpack/contact-form -->
