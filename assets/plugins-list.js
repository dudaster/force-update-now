( function ( $ ) {
	'use strict';

	if ( typeof FUN === 'undefined' ) {
		return;
	}

	function buildNoticeRow( colspan, diff, slug, file ) {
		var $tr = $( '<tr class="plugin-update-tr active fun-cooldown-tr"></tr>' )
			.attr( 'data-slug', slug )
			.attr( 'data-plugin', file );
		var $td = $( '<td colspan="' + colspan + '" class="plugin-update colspanchange"></td>' );
		var $div = $( '<div class="update-message notice inline notice-warning notice-alt"></div>' );
		var $p = $( '<p></p>' ).text(
			'A new version (' + diff.remote + ') is available, but it hasn’t cleared WordPress.org’s 24-hour review window yet. '
		);

		var $btn = $( '<button type="button" class="button button-primary" style="margin-left: 6px; vertical-align: baseline;">Force update</button>' );
		$btn.on( 'click', function () {
			if ( ! window.confirm( 'Force-install ' + diff.remote + ' now, bypassing the WordPress.org review cooldown?' ) ) {
				return;
			}

			$btn.prop( 'disabled', true ).text( 'Installing…' );

			$.post( FUN.ajaxUrl, {
				action: 'fun_force_update',
				nonce: FUN.nonce,
				type: 'plugin',
				file: file,
				slug: slug,
			} )
				.done( function ( res ) {
					if ( ! res.success ) {
						window.alert( 'Failed: ' + res.data );
						$btn.prop( 'disabled', false ).text( 'Force update' );
						return;
					}
					window.location.reload();
				} )
				.fail( function () {
					window.alert( 'Request failed' );
					$btn.prop( 'disabled', false ).text( 'Force update' );
				} );
		} );

		$p.append( $btn );
		$div.append( $p );
		$td.append( $div );
		$tr.append( $td );

		return $tr;
	}

	$( function () {
		var $table = $( '#the-list' ).closest( 'table' );
		var colspan = $table.find( 'thead th, thead td' ).length || 5;

		$( '#the-list > tr[data-plugin]' ).each( function () {
			var $row = $( this );
			var file = $row.data( 'plugin' );
			var slug = $row.data( 'slug' );

			if ( ! file || ! slug ) {
				return;
			}

			// A native "There is a new version..." row already covers this
			// once WordPress.org's own transient catches up — don't pile a
			// second notice on top of it.
			if ( $row.next().hasClass( 'plugin-update-tr' ) ) {
				return;
			}

			$.post( FUN.ajaxUrl, {
				action: 'fun_check',
				nonce: FUN.nonce,
				type: 'plugin',
				file: file,
				slug: slug,
			} ).done( function ( res ) {
				if ( ! res.success ) {
					return;
				}

				var diff = res.data;
				if ( ! diff.has_update || diff.hours_since_release === null || diff.hours_since_release >= 24 ) {
					return;
				}

				if ( $row.next().hasClass( 'plugin-update-tr' ) ) {
					return; // a check for another row may have added one meanwhile
				}

				$row.after( buildNoticeRow( colspan, diff, slug, file ) );
			} );
		} );
	} );
} )( jQuery );
