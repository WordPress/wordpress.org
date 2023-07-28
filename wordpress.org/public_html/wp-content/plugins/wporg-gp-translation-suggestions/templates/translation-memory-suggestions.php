<?php
if ( empty( $suggestions ) ) {
	echo '<p class="no-suggestions">No suggestions.</p>';
} else {
	echo '<ul class="suggestions-list">';
	foreach ( $suggestions as $suggestion ) {
		$suggestion_type = ( 'translation' === strtolower( $type ) ) ? 'tm' : $type;

		echo '<li>';
		echo '<div class="translation-suggestion with-tooltip ' . esc_html( strtolower( $type ) ) . '" tabindex="0" data-suggestion-source="' . esc_html( strtolower( $suggestion_type ) ) . '" role="button" aria-pressed="false" aria-label="Copy translation">';
			echo '<span class="' . esc_html( strtolower( $type ) ) . '-suggestion__score">';
		if ( 'Translation' == $type ) {
			echo number_format( 100 * $suggestion['similarity_score'] ) . '%';
		} else {
			echo esc_html( $type );
		}
			echo '</span>';
			echo '<span class="translation-suggestion__translation">';
				echo esc_translation( $suggestion['translation'] );

		if ( $suggestion['diff'] ) {
			echo '<span class="translation-suggestion__original-diff">' . wp_kses_post( $suggestion['diff'] ) . '</span>';
		}
			echo '</span>';

			echo '<span aria-hidden="true" class="translation-suggestion__translation-raw">' . esc_translation( $suggestion['translation'] ) . '</span>';

			echo '<button type="button" class="button is-small copy-suggestion">Copy</button>';
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
}
