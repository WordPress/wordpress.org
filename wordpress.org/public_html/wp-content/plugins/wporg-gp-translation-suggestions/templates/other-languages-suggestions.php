<?php

if ( empty( $suggestions ) ) {
	echo '<p class="no-suggestions">No suggestions.</p>';
} else {
	echo '<ul class="suggestions-list">';
	foreach ( $suggestions as $suggestion ) {
		echo '<li>';
		echo '<div class="translation-suggestion with-tooltip" tabindex="0" role="button" data-suggestion-locale="' . esc_html( strtolower( $suggestion['locale'] ) ) . '" aria-pressed="false" aria-label="Copy translation">';

			echo '<span class="translation-suggestion__translation">';
				echo esc_translation( $suggestion['translation'] );

				echo '<span class="translation-suggestion__translation-meta">';
					$gp_locale_slug = $suggestion['locale'];
					if ( 'default' !== $suggestion['slug'] ) {
						$gp_locale_slug .= '/' . $suggestion['slug'];
					}
					$gp_locale = GP_Locales::by_slug( $gp_locale_slug );

					echo esc_html( $gp_locale ? $gp_locale->english_name : $gp_locale_slug );

					if ( $suggestion['user_id'] ) {
						$user = get_user_by( 'id', $suggestion['user_id'] );
						if ( $user ) {
							printf(
								' | By <a href="https://profiles.wordpress.org/%s">%s</a>',
 								$user->user_nicename,
								esc_html( $user->display_name )
							);
						}
					}

				echo '</span>';
			echo '</span>';

			echo '<span aria-hidden="true" class="translation-suggestion__translation-raw">' . esc_translation( $suggestion['translation'] ) . '</span>';

			echo '<button type="button" class="button is-small copy-suggestion">Copy</button>';
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
}
