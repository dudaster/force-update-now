( function ( $ ) {
	'use strict';

	// This file loads inside the "View details" thickbox iframe
	// (plugin-install.php?tab=plugin-information&plugin=<slug>).
	var params = new URLSearchParams( window.location.search );
	var slug = params.get( 'plugin' );

	if ( ! slug || typeof FUN === 'undefined' ) {
		return;
	}

	$( function () {
		$.post( FUN.ajaxUrl, {
			action: 'fun_check',
			nonce: FUN.nonce,
			type: 'plugin',
			file: '',
			slug: slug,
		} ).done( function ( res ) {
			if ( ! res.success ) {
				return; // not installed here, or not on WordPress.org — nothing to add.
			}

			var diff = res.data;

			// Only worth showing while the update is still stuck in
			// WordPress.org's review cooldown. Once 24h have passed, the
			// normal update flow has (or will have) caught up on its own.
			if ( ! diff.has_update || diff.hours_since_release === null || diff.hours_since_release >= 24 ) {
				return;
			}

			var $footer = $( '#plugin-information-footer' );
			if ( ! $footer.length ) {
				return;
			}

			var $notice = $( '<div class="fun-cooldown-notice" style="margin: 0 0 10px; text-align: left;"></div>' )
				.append(
					$( '<p style="margin: 0 0 8px;"></p>' ).text(
						'A new version (' + diff.remote + ') is available, but it hasn’t cleared WordPress.org’s 24-hour review window yet.'
					)
				)
				.append(
					$( '<button type="button" class="button button-primary">Force update</button>' ).on( 'click', function () {
						var $btn = $( this );

						if ( ! window.confirm( 'Force-install ' + diff.remote + ' now, bypassing the WordPress.org review cooldown?' ) ) {
							return;
						}

						$btn.prop( 'disabled', true ).text( 'Installing…' );

						$.post( FUN.ajaxUrl, {
							action: 'fun_force_update',
							nonce: FUN.nonce,
							type: 'plugin',
							file: diff.file,
							slug: slug,
						} ).done( function ( updateRes ) {
							if ( ! updateRes.success ) {
								window.alert( 'Failed: ' + updateRes.data );
								$btn.prop( 'disabled', false ).text( 'Force update' );
								return;
							}

							$notice.find( 'p' ).first().text( 'Updated to ' + updateRes.data.version + '.' );
							$btn.remove();
						} ).fail( function () {
							window.alert( 'Request failed' );
							$btn.prop( 'disabled', false ).text( 'Force update' );
						} );
					} )
				);

			$footer.prepend( $notice );
		} );
	} );
} )( jQuery );
