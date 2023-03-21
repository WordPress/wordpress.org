<?php
if ( empty( $suggestions ) && empty( $openai_suggestions ) && empty( $deepl_suggestions ) ) {
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

			echo '<button type="button" class="button is-small copy-suggestion">Copy</button>';
		echo '</div>';
		echo '</li>';
	}
	foreach ( $openai_suggestions as $suggestion ) {
		echo '<li>';
		echo '<div class="translation-suggestion with-tooltip openai" tabindex="0" role="button" aria-pressed="false" aria-label="Copy translation">';
			echo '<span class="openai-suggestion__score">OpenAI</span>';

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
	foreach ( $deepl_suggestions as $suggestion ) {
		echo '<li>';
		echo '<div class="translation-suggestion with-tooltip deepl" tabindex="0" role="button" aria-pressed="false" aria-label="Copy translation">';
			echo '<span class="deepl-suggestion__score">Deepl</span>';

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
