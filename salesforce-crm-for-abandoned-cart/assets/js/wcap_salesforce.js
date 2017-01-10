jQuery(function( $ ) {

	var wcap_agile_connection_established = '';

	$ ( '#wcap_agile_test' ).on( 'click', function( e ) {
		e.preventDefault();
		var wcap_agile_user_name = $("#wcap_agile_user_name").val();
		var wcap_agile_domain    = $("#wcap_agile_domain").val();

		var wcap_agile_token     = $("#wcap_agile_security_token").val();
        var data = {
            wcap_agile_user_name: wcap_agile_user_name,
            wcap_agile_domain: wcap_agile_domain,
            wcap_agile_token: wcap_agile_token,
            action: 'wcap_agile_check_connection'
        };
        $( '#wcap_agile_test_connection_ajax_loader' ).show();
        $.post( wcap_agile_params.ajax_url, data, function( response ) {
        	var wcap_check_string = response.indexOf("successfuly established");
        	if ( wcap_check_string !== -1 ){
        		wcap_agile_connection_established = 'yes';
        	}
    		$( '#wcap_agile_test_connection_message' ).html( response );
	        $( '#wcap_agile_test_connection_ajax_loader' ).hide();
        });
	});

	$ ( '.button-primary' ).on( 'click', function( e ) {
		if ( $(this).val() == 'Save Agile CRM Settings' ) {			
			
			var wcap_agile_user_name = $("#wcap_agile_user_name").val();
			var wcap_agile_domain    = $("#wcap_agile_domain").val();
			var wcap_agile_token     = $("#wcap_agile_security_token").val();
			
			if ( '' == wcap_agile_token ) {
				$( "#wcap_agile_security_token_label_error" ).fadeIn();
	            setTimeout( function(){
		            $( "#wcap_agile_security_token_label_error" ).fadeOut();
		        },4000);
		        e.preventDefault();
			}
			if ( '' == wcap_agile_domain ) {
				$( "#wcap_agile_domain_label_error" ).fadeIn();
	            setTimeout( function() {
		            $( "#wcap_agile_domain_label_error" ).fadeOut();
		        },4000);
			    e.preventDefault();
			}
			if ( '' == wcap_agile_user_name ) {
				$( "#wcap_agile_user_name_label_error" ).fadeIn();
	            setTimeout( function(){
		            $( "#wcap_agile_user_name_label_error" ).fadeOut();
		        },4000);
	            e.preventDefault();
			}

			if ( ( wcap_agile_params.wcap_agile_user_name   != wcap_agile_user_name
				|| wcap_agile_params.wcap_agile_domain_name != wcap_agile_domain 
				|| wcap_agile_params.wcap_agile_api_key     != wcap_agile_token ) &&
				 ( wcap_agile_params.wcap_agile_connection_established != 'yes' || wcap_agile_connection_established != 'yes') )  {
				e.preventDefault();
				var data = {
		            wcap_agile_user_name: wcap_agile_user_name,
		            wcap_agile_domain: wcap_agile_domain,
		            wcap_agile_token: wcap_agile_token,
		            action: 'wcap_agile_check_connection'
		        };
		        $( '#wcap_agile_test_connection_ajax_loader' ).show();
		        $.post( wcap_agile_params.ajax_url, data, function( response ) {
		    		var wcap_check_string = response.indexOf("successfuly established");
		    		$( '#wcap_agile_test_connection_ajax_loader' ).hide();
		    		
			    	if ( wcap_check_string !== -1 ){
			    		
			        	$('#wcap_agile_crm_form').submit();
			        }else{
			    		$( '#wcap_agile_test_connection_message' ).html( response );
					}
			    });
			}
		}
	});

	var wcap_all = '';
	$ ( '.add_single_cart' ).on( 'click', function( e ) {
		var wcap_selected_id = [];
		var wcap_all = '';
		wcap_selected_id.push ( $( this ).attr( 'data-id' ) );
		$( '#wcap_manual_email_data_loading' ).show();
		var data = {
			action                  : 'wcap_add_to_agile_crm',
			wcap_abandoned_cart_ids : wcap_selected_id,
			wcap_all                : wcap_all
		};

		$.post( wcap_agile_params.ajax_url, data, function( response ) {
			$( '#wcap_manual_email_data_loading' ).hide();
			var wcap_check_string = response.indexOf("duplicate_record");
			if ( wcap_check_string !== -1 ){

        		var display_message       = 'Abandoned cart has been already imported to Agile CRM.';
				$( ".wcap_agile_message_p_error" ).html( display_message );
	            $( "#wcap_agile_message_error" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_agile_message_error" ).fadeOut();
	            },4000);
        	}else{
				var abadoned_order_count = response;
				var order                = 'order';
				if ( abadoned_order_count > 1 ){
					order 				 = 'orders';
				}
				
				var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Agile CRM.'
				$( ".wcap_agile_message_p" ).html( display_message );
	            $( "#wcap_agile_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_agile_message" ).fadeOut();
	            },4000);
        	}
		});
	});

	$ ( '#add_all_carts' ).on( 'click', function( e ) {
		
		wcap_all = 'yes';
		var wcap_selected_id = [];
		$( '#wcap_manual_email_data_loading' ).show();
		var data = {
			action                  : 'wcap_add_to_agile_crm',
			wcap_abandoned_cart_ids : wcap_selected_id,
			wcap_all                : wcap_all
		};

		$.post( wcap_agile_params.ajax_url, data, function( response ) {
			$( '#wcap_manual_email_data_loading' ).hide();

			var wcap_check_string = response.indexOf("no_record");
			if ( wcap_check_string !== -1 ){
				var display_message       = 'All Abandoned cart has been already imported to Agile CRM.';
				$( ".wcap_agile_message_p_error" ).html( display_message );
	            $( "#wcap_agile_message_error" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_agile_message_error" ).fadeOut();
	            },4000);
			}else{
				var abadoned_order_count = response;
				var order                = 'order';
				if ( abadoned_order_count > 1 ){
					order 				 = 'orders';
				}
				
				var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Agile CRM.'
				$( ".wcap_agile_message_p" ).html( display_message );
	            $( "#wcap_agile_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_agile_message" ).fadeOut();
	            },4000);
        	}
		});
	});

	$ ( '#doaction' ).on( 'click', function( e ) {
		if ( $( '#bulk-action-selector-top' ).val() == 'wcap_add_salesforce' ) {
			
			var checkboxes = document.getElementsByName('abandoned_order_id[]');
			var wcap_selected_id = [];
			var wcap_selected_id = [];
			var wcap_parent      = [];
		  	for (var i = 0; i < checkboxes.length; i++) {
		     
		     	if ( checkboxes[i].checked ) {
		     		var email_check = $( checkboxes[i] ).parent().parent().find('.email').text();
		     		var wcap_agile = email_check.indexOf( "Add to Salesforce CRM" );
		     		wcap_parent [ checkboxes[i].value ] =  email_check ;
		        	wcap_selected_id.push( checkboxes[i].value );
		    	}
		  	}

		  	if ( wcap_selected_id.length == 0 ) {
		  		var display_message = 'Please select atleast 1 Abandoned order to Add to Salesforce CRM.';
				$( ".wcap_agile_message_p_error" ).html( display_message );
	            $( "#wcap_ac_bulk_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_ac_bulk_message" ).fadeOut();
	            },3000);
		  		e.preventDefault();	
		  		return;
		  	}
		  	var allow = 'no';
			if ( wcap_parent.length > 0 ) {
				for ( var key in wcap_parent ) {
				  if ( wcap_parent[key] > 0 ){
				  	allow = 'yes';
				  } else {
				  	var visitor = document.querySelectorAll( "input[ value = '"+ key +"']" );
				  	visitor[0].checked = false;
				  }
				}					
				if ( 'no' == allow ) {
					var display_message = 'Add to Salesforce CRM cannot be applied on Visitor carts.';
					$( ".wcap_ac_bulk_message_p" ).html( display_message );
		            $( "#wcap_agile_message_error" ).fadeIn();
		            setTimeout( function() {
		            	$( "#wcap_agile_message_error" ).fadeOut();
		            },3000);
					e.preventDefault();
					return;
				}
			}

		  	var wcap_all = '';
		  	$( '#wcap_manual_email_data_loading' ).show();
			var data = {
				action                  : 'wcap_add_to_agile_crm',
				wcap_abandoned_cart_ids : wcap_selected_id,
				wcap_all                : wcap_all
			};
			
			$.post( wcap_agile_params.ajax_url, data, function( response ) {
				$( '#wcap_manual_email_data_loading' ).hide();

				var wcap_check_string = response.indexOf("duplicate_record");
				if ( wcap_check_string !== -1 ){

	        		var display_message       = 'Abandoned cart has been already imported to Agile CRM.';
					$( ".wcap_agile_message_p_error" ).html( display_message );
		            $( "#wcap_agile_message_error" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_agile_message_error" ).fadeOut();
		            },4000);
	        	}else{
					var abadoned_order_count = response;
					var order                = 'order';
					if ( abadoned_order_count > 1 ){
						order 				 = 'orders';
					}
					
					var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Agile CRM.'
					$( ".wcap_agile_message_p" ).html( display_message );
		            $( "#wcap_agile_message" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_agile_message" ).fadeOut();
		            },4000);
	        	}
			});
			e.preventDefault();
		}
	});

	$ ( '#doaction2' ).on( 'click', function( e ) {
		if ( $( '#bulk-action-selector-bottom' ).val() == 'wcap_add_salesforce' ) {
			
			var checkboxes = document.getElementsByName('abandoned_order_id[]');
			var wcap_selected_id = [];
			var wcap_selected_id = [];
			var wcap_parent      = [];
		  	for (var i = 0; i < checkboxes.length; i++) {
		     
		     	if ( checkboxes[i].checked ) {
		     		var email_check = $( checkboxes[i] ).parent().parent().find('.email').text();
		     		var wcap_agile = email_check.indexOf( "Add to Salesforce CRM" );
		     		wcap_parent [ checkboxes[i].value ] =  email_check ;
		        	wcap_selected_id.push( checkboxes[i].value );
		    	}
		  	}

		  	if ( wcap_selected_id.length == 0 ) {
		  		var display_message = 'Please select atleast 1 Abandoned order to Add to Salesforce CRM.';
				$( ".wcap_agile_message_p_error" ).html( display_message );
	            $( "#wcap_ac_bulk_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_ac_bulk_message" ).fadeOut();
	            },3000);
		  		e.preventDefault();	
		  		return;
		  	}
		  	var allow = 'no';
			if ( wcap_parent.length > 0 ) {
				for ( var key in wcap_parent ) {
				  if ( wcap_parent[key] > 0 ){
				  	allow = 'yes';
				  } else {
				  	var visitor = document.querySelectorAll( "input[ value = '"+ key +"']" );
				  	visitor[0].checked = false;
				  }
				}					
				if ( 'no' == allow ) {
					var display_message = 'Add to Salesforce CRM cannot be applied on Visitor carts.';
					$( ".wcap_ac_bulk_message_p" ).html( display_message );
		            $( "#wcap_agile_message_error" ).fadeIn();
		            setTimeout( function() {
		            	$( "#wcap_agile_message_error" ).fadeOut();
		            },3000);
					e.preventDefault();
					return;
				}
			}
			
		  	var wcap_all = '';
		  	$( '#wcap_manual_email_data_loading' ).show();
			var data = {
				action                  : 'wcap_add_to_agile_crm',
				wcap_abandoned_cart_ids : wcap_selected_id,
				wcap_all                : wcap_all
			};
			
			$.post( wcap_agile_params.ajax_url, data, function( response ) {
				$( '#wcap_manual_email_data_loading' ).hide();
				var wcap_check_string = response.indexOf("duplicate_record");
				if ( wcap_check_string !== -1 ){

	        		var display_message       = 'Abandoned cart has been already imported to Agile CRM.';
					$( ".wcap_agile_message_p_error" ).html( display_message );
		            $( "#wcap_agile_message_error" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_agile_message_error" ).fadeOut();
		            },4000);
	        	}else{
					var abadoned_order_count = response;
					var order                = 'order';
					if ( abadoned_order_count > 1 ){
						order 				 = 'orders';
					}
					
					var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Agile CRM.'
					$( ".wcap_agile_message_p" ).html( display_message );
		            $( "#wcap_agile_message" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_agile_message" ).fadeOut();
		            },4000);
	        	}
			});
			e.preventDefault();
		}
	});
});