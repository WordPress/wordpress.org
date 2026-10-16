@php
	$review_id = $review['review_id'];
	$plugin_id = (int) $review_id['plugin_id'];
	$details   = array_filter(
		array(
			__( 'Type' )          => $review_id['type'],
			__( 'Reviews' )       => null === $review_id['reviews'] ? '' : (string) $review_id['reviews'],
			__( 'Latest review' ) => $review_id['reviewed'],
			__( 'Started' )       => $review_id['started'],
		),
		'strlen'
	);
@endphp
{{-- FreeScout's own sidebar block, like Previous Conversations. --}}
<div class="conv-sidebar-block wporg-review" data-flags="{{ implode( ' ', $review['flags'] ) }}" data-username="{{ $review_id['username'] }}" data-url="{{ route( 'wporgpluginreview.plugin', array( 'conversation_id' => $conversation_id ) ) }}" data-strings="{{ json_encode(
	array(
		'copied'          => __( 'Copied' ),
		'verified'        => __( 'DNS verified' ),
		'status'          => __( 'Status' ),
		'assigned'        => __( 'Assigned to' ),
		'current'         => __( 'Current' ),
		'submitter'       => __( 'Submitter' ),
		'download'        => __( 'Copy the download URL' ),
		'page'            => __( 'Open the plugin’s page' ),
		'scans'           => __( 'Open the plugin’s security scans' ),
		'slug'            => __( 'Slug' ),
		'copy_slug'       => __( 'Copy the slug' ),
		'email_match'     => __( 'At one of the plugin’s domains' ),
		'email_other'     => __( 'Not at the plugin’s domains' ),
		'edit'            => __( 'Edit the plugin on WordPress.org' ),
		'failed'          => __( 'Couldn’t load the plugin from WordPress.org.' ),
		'throttled'       => __( 'Couldn’t load the plugin from WordPress.org: too many requests. Reload the page in a minute.' ),
		'short_title'     => __( 'Shorten the tab’s title' ),
		'full_title'      => __( 'Show the tab’s full title' ),
		'mismatch'        => __( 'The Review ID in this reply is for :username, who isn’t the author of the plugin this conversation is about. Check that it’s the right review before you send it.' ),
		'acknowledge'     => __( 'It’s the right one' ),
		'names_followed'  => __( 'The plugin has the suggested name.' ),
		'names_ignored'   => __( 'The plugin still has its original name.' ),
		'names_to-review' => __( 'The plugin has another name: check it.' ),
		'owner_ignored'   => __( 'Neither the plugin’s domains nor the submitter’s email address show they own it.' ),
	)
) }}">
	<div class="panel-group accordion accordion-empty">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h4 class="panel-title">
					<a data-toggle="collapse" href=".collapse-wporg-review">{{ __( 'Plugin Review' ) }}
						<b class="caret"></b>
					</a>
				</h4>
			</div>
			<div class="collapse-wporg-review panel-collapse collapse in">
				<div class="panel-body">
					<div class="sidebar-block-header2"><strong>{{ __( 'Plugin Review' ) }}</strong> (<a data-toggle="collapse" href=".collapse-wporg-review">{{ __( 'close' ) }}</a>)</div>

					<dl class="wporg-review-details wporg-review-plugin">
						@if ( '' !== $review_id['slug'] )
							<dt>{{ __( 'Slug' ) }}</dt>
							<dd class="wporg-review-slug">
								@if ( $plugin_id )
									<a href="https://wordpress.org/plugins/wp-admin/post.php?post={{ $plugin_id }}&amp;action=edit" target="_blank" rel="noopener noreferrer" title="{{ __( 'Edit the plugin on WordPress.org' ) }}">{{ $review_id['slug'] }}</a>
								@else
									{{-- Linked once WordPress.org finds the plugin by its slug. --}}
									<span class="wporg-review-slug-name">{{ $review_id['slug'] }}</span>
								@endif
								<button type="button" class="wporg-review-copy" data-copy="{{ $review_id['slug'] }}" title="{{ __( 'Copy the slug' ) }}" aria-label="{{ __( 'Copy the slug' ) }}"><i class="glyphicon glyphicon-copy"></i></button>
							</dd>
						@endif
						@if ( '' !== $review_id['username'] )
							<dt>{{ __( 'User' ) }}</dt>
							<dd><a href="https://profiles.wordpress.org/{{ rawurlencode( $review_id['username'] ) }}/" target="_blank" rel="noopener noreferrer">{{ $review_id['username'] }}</a></dd>
						@endif
						@foreach ( $details as $label => $value )
							<dt>{{ $label }}</dt>
							<dd>{{ $value }}</dd>
						@endforeach
					</dl>

					@if ( $review['flags'] )
						<h5 class="wporg-review-heading">{{ __( 'Flags' ) }}</h5>
						<ul class="wporg-review-flags">
							@foreach ( $review['flags'] as $flag )
								<li class="wporg-review-flag" data-flag="{{ $flag }}">
									{{ $flag }}
									@if ( isset( $replies[ $flag ] ) )
										{{-- As an attribute, so it isn't part of the page until it's copied, as HTML and as text. --}}
										<button type="button" class="wporg-review-copy wporg-review-reply" data-reply="{{ $replies[ $flag ] }}" title="{{ __( 'Copy the reply for :flag', array( 'flag' => $flag ) ) }}" aria-label="{{ __( 'Copy the reply for :flag', array( 'flag' => $flag ) ) }}"><i class="glyphicon glyphicon-copy"></i></button>
									@endif
								</li>
							@endforeach
						</ul>
					@endif

					@if ( $review['names'] )
						@php
							$names = $review['names'];
						@endphp
						{{-- The script colors it once it knows the plugin's current name. --}}
						<div class="wporg-review-check wporg-review-names" data-original="{{ $names['original'] }}" data-suggested="{{ $names['suggested'] }}" data-suggested-slug="{{ $names['suggested_slug'] }}">
							<h5 class="wporg-review-heading">{{ __( 'Names' ) }}</h5>
							<dl class="wporg-review-details">
								<dt>{{ __( 'Original' ) }}</dt>
								<dd>{{ '' !== $names['original'] ? $names['original'] : '—' }}</dd>
								@if ( '' !== $names['suggested'] || '' !== $names['suggested_slug'] )
									<dt>{{ __( 'Suggested' ) }}</dt>
									<dd>
										{{ $names['suggested'] }}
										@if ( '' !== $names['suggested_slug'] )
											@if ( '' !== $names['suggested'] )
												<br>
											@endif
											<code>{{ $names['suggested_slug'] }}</code>
											<button type="button" class="wporg-review-copy" data-copy="{{ $names['suggested_slug'] }}" title="{{ __( 'Copy the slug' ) }}" aria-label="{{ __( 'Copy the slug' ) }}"><i class="glyphicon glyphicon-copy"></i></button>
										@endif
									</dd>
								@endif
							</dl>
						</div>
					@endif

					@if ( $review['owner'] )
						{{-- The script colors it once it knows whether the author proved they own the plugin. --}}
						<div class="wporg-review-check wporg-review-owner">
							<h5 class="wporg-review-heading">{{ __( 'Owner' ) }}</h5>
							<dl class="wporg-review-details">
								@foreach ( array( 'author' => __( 'Author host' ), 'plugin' => __( 'Plugin host' ) ) as $key => $label )
									@if ( '' !== $review['owner'][ $key . '_host' ] )
										<dt>{{ $label }}</dt>
										<dd data-host="{{ $key }}">{{ $review['owner'][ $key . '_host' ] }}</dd>
									@endif
								@endforeach
							</dl>
						</div>
					@endif

					@if ( $review['issues'] )
						<h5 class="wporg-review-heading">{{ __( 'Issues' ) }}</h5>
						<ul class="wporg-review-issues">
							@foreach ( $review['issues'] as $issue )
								<li>
									<button type="button" class="wporg-review-issue @if ( null !== $issue['priority'] ) is-priority-{{ $issue['priority'] }} @endif" data-thread-id="{{ $review['thread_id'] }}" data-title="{{ $issue['title'] }}" title="{{ $issue['title'] }}">{{ $issue['name'] }}</button>
								</li>
							@endforeach
						</ul>
					@endif

					{{-- The script shows it, labeled with what a click does, when it changes the tab's title. --}}
					<button type="button" class="wporg-review-title-toggle" hidden></button>
				</div>
			</div>
		</div>
	</div>
</div>
