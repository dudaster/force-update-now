( function ( $ ) {
	'use strict';

	function post( action, $row ) {
		return $.post( FUN.ajaxUrl, {
			action: action,
			nonce: FUN.nonce,
			type: $row.data( 'type' ),
			file: $row.data( 'file' ),
			slug: $row.data( 'slug' ),
		} );
	}

	$( document ).on( 'click', '.fun-check', function () {
		var $btn = $( this );
		var $row = $btn.closest( 'tr' );

		$btn.prop( 'disabled', true ).text( 'Checking…' );

		post( 'fun_check', $row )
			.done( function ( res ) {
				if ( ! res.success ) {
					$row.find( '.fun-remote' ).text( 'Error: ' + res.data );
					return;
				}

				var diff = res.data;
				$row.find( '.fun-remote' ).text( diff.remote );

				if ( diff.has_update ) {
					$row.find( '.fun-force' ).prop( 'disabled', false );
					$row.addClass( 'fun-has-update' );
				} else {
					$row.find( '.fun-force' ).prop( 'disabled', true );
					$row.removeClass( 'fun-has-update' );
				}
			} )
			.fail( function () {
				$row.find( '.fun-remote' ).text( 'Request failed' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( 'Check now' );
			} );
	} );

	$( document ).on( 'click', '.fun-force', function () {
		var $btn = $( this );
		var $row = $btn.closest( 'tr' );

		if ( ! window.confirm( 'Force-install this update now, bypassing the WordPress.org review cooldown?' ) ) {
			return;
		}

		$btn.prop( 'disabled', true ).text( 'Installing…' );

		post( 'fun_force_update', $row )
			.done( function ( res ) {
				if ( ! res.success ) {
					window.alert( 'Failed: ' + res.data );
					$btn.prop( 'disabled', false ).text( 'Force update' );
					return;
				}

				$row.find( '.fun-installed' ).text( res.data.version );
				$row.find( '.fun-remote' ).text( res.data.version );
				$row.removeClass( 'fun-has-update' );
				$btn.text( 'Updated' );
			} )
			.fail( function () {
				window.alert( 'Request failed' );
				$btn.prop( 'disabled', false ).text( 'Force update' );
			} );
	} );
} )( jQuery );
