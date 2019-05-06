<?php

if ( empty( $suggestions ) ) {
	echo '<p class="no-suggestions">No suggestions.</p>';
} else {
	echo '<ul class="suggestions-list">';
	foreach ( $suggestions as $suggestion ) {
		echo '<li>';
		echo '<div class="translation-suggestion with-tooltip" tabindex="0" role="button" aria-pressed="false" aria-label="Copy translation">';
			echo '<span class="translation-suggestion__score">' . number_format( 100 * $suggestion['similarity_score'] ) . '%</span>';

			echo '<span class="translation-suggestion__translation">';
				echo esc_translation( $suggestion['translation'] );

				if ( $suggestion['diff'] ) {
					echo '<span class="translation-suggestion__original-diff">' . wp_kses_post( $suggestion['diff'] ) . '</span>';
				}
			echo '</span>';

			echo '<span aria-hidden="true" class="translation-suggestion__translation-raw">' . esc_translation( $suggestion['translation'] ) . '</span>';

			echo '<button type="button" class="copy-suggestion">Copy</button>';
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
}
