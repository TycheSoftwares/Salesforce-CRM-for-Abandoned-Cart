jQuery( function( $ ) {

    $( 'input#billing_company' ).on( 'change', function() {

        var data = {
            billing_company: $( '#billing_company' ).val(),
            action: 'wcap_salesforce_capture_company',
        };

        if ( localStorage.wcap_abandoned_id ) {
            data.wcap_abandoned_id = localStorage.wcap_abandoned_id;
        }

        if ( localStorage.wcap_hidden_email_id ) {
            data.billing_email = localStorage.wcap_hidden_email_id;
        }

        $.post( wcap_salesforce_guest_user_params.ajax_url, data, function( response ) {
            console.log( response );
        } );
        
    } );

});