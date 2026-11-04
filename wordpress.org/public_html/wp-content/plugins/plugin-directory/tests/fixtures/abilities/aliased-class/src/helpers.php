<?php
namespace Aliased\Helpers;

function label_for( string $id ): string {
	return 'summarize' === $id ? __( 'Summarize', 'aliased' ) : $id;
}
