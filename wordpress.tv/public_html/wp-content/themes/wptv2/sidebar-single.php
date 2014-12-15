<?php global $wptv, $originalcontent; ?>

<div class="secondary-content video-info">
	<h5>Published</h5>
			<p class="video-date"><?php echo get_the_date(); ?></p>

			<?php if ( get_the_excerpt() ) : ?>
				<div class="video-description"><?php the_excerpt(); ?></div>
			<?php
				endif;

				$wptv->the_terms( 'event', '<h5>Event</h5><p class="video-event">', '<br /> ', '</p>' );
				$wptv->the_terms( 'speakers', '<h5>Speakers</h5><p class="video-speakers">', '<br /> ', '</p>' );
				$wptv->the_terms( 'post_tag', '<h5>Tags</h5><p class="video-tags">', '<br /> ', '</p>' );
				$wptv->the_terms( 'language', '<h5>Language</h5><p class="video-lang">', '<br /> ', '</p>' );

				if ( function_exists( 'find_all_videopress_shortcodes' ) ) {
					$videos = array_keys( find_all_videopress_shortcodes( $originalcontent ) );
					if ( ! empty( $videos ) ) {
						$video = video_get_info_by_guid( $videos[0] );
						$formats = array( 'fmt_std' => 'Low', 'fmt_dvd' => 'Med', 'fmt_hd' => 'High', 'fmt1_ogg' => 'Low' );
						$mp4_links = array();
						$ogg_link = NULL;
						foreach ( $formats as $format => $name ) {
							if ( 'fmt1_ogg' == $format ) {
								$link = video_highest_resolution_ogg( $video );
							} else {
								$link = video_url_by_format( $video, $format );
							}

							if ( empty( $link ) ) {
								continue;
							}

							if ( 'fmt1_ogg' == $format ) {
								$ogg_link = "<a href='$link'>$name</a>";
							} else {
								$mp4_links[] = "<a href='$link'>$name</a>";
							}
						}

						if ( ! empty( $mp4_links ) || ! empty( $ogg_link ) ) {
?>
			<h5>Download</h5>
			<div class="video-downloads">
<?php
							if ( ! empty( $mp4_links ) ) {
								echo 'MP4: ' . join( ', ', $mp4_links ) . '<br/>';
							}
							if ( ! empty( $ogg_link ) ) {
								echo "OGG: $ogg_link";
							}
?>
			</div>
<?php
						}

						echo '<h5>Subtitles</h5>';
						$ttml_links = array();
						$languages = VideoPress_Subtitles::get_languages();
						$subtitles = (array) get_post_meta( $video->post_id, '_videopress_subtitles', true );

						foreach ( $subtitles as $track ) {
							if ( empty( $track['subtitles_post_id'] ) ) {
								continue;
							}

							$tracks[ $track['language'] ] = new VideoPress_Subtitles_Track( array(
								'guid'              => $video->guid,
								'language'          => $track['language'],
								'subtitles_post_id' => $track['subtitles_post_id'],
							) );

							$ttml_links[] = '<a href="'. $tracks[ $track['language'] ]->url() .'">'. $languages[ $track['language'] ]['localized_label'] .'</a>';
						}

						if ( ! empty( $ttml_links ) ) {
							echo 'TTML: ' . join( ', ', $ttml_links ) . '<br />';
						}

						printf( '<a href="%s">Subtitle this video &rarr;</a>', esc_url( add_query_arg( 'video', $video->post_id, home_url( 'subtitle/' ) ) ) );
					}
				}
			?>
</div><!-- .secondary-content -->
