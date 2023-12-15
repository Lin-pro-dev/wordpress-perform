( function( document ) {
	document.addEventListener( 'DOMContentLoaded', function() {
		document.addEventListener( 'click', function(event) {
			if ( event.target.classList.contains( 'perflab-install-active-plugin' ) ) {
				var target = event.target;
				target.parentElement.querySelector( 'span' ).classList.remove( 'hidden' );

				var data = new FormData();
				data.append( 'action', 'perflab_install_activate_standalone_plugins' );
				data.append( 'nonce', perflab_module_migration_notice.nonce );

				fetch( perflab_module_migration_notice.ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				})
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( perflab_module_migration_notice.network_error );
					}
					return response.json();
				})
				.then( function( result ) {
					target.parentElement.querySelector( 'span' ).classList.add( 'hidden' );
					if ( ! result.success ) {
						alert( perflab_replace_html_entity( result.data.errorMessage ) );
					}
					window.location.reload();
				})
				.catch( function( error ) {
					alert( error.errorMessage );
					window.location.reload();
				});
			}
		});

		// Function to replace HTML entities with their corresponding characters.
		function perflab_replace_html_entity( str ) {
			return str.replace( /&#(\d+);/g, function( match, dec ) {
				return String.fromCharCode( dec );
			});
		}
	});
} )( document );
