jQuery(function( $ ) {

	var wcap_sf_connection_established = '';

	$ ( '#wcap_salesforce_test' ).on( 'click', function( e ) {
		e.preventDefault();

		var wcap_sf_user_name = $("#wcap_salesforce_user_name").val();
		var wcap_sf_password  = $("#wcap_salesforce_password").val();
		var wcap_sf_token     = $("#wcap_salesforce_security_token").val();
        
        var data = {
            wcap_sf_user_name: wcap_sf_user_name,
            wcap_sf_password: wcap_sf_password,
            wcap_sf_token: wcap_sf_token,
            action: 'wcap_salesforce_check_connection'
        };
        
        $( '#wcap_salesforce_test_connection_ajax_loader' ).show();
        $.post( wcap_salesforce_params.ajax_url, data, function( response ) {
        	wcap_check_string = response.indexOf("successfuly established");
        	if ( wcap_check_string !== -1 ){
        		wcap_sf_connection_established = 'yes';
        	}
    		$( '#wcap_salesforce_test_connection_message' ).html( response );
	        $( '#wcap_salesforce_test_connection_ajax_loader' ).hide();
        });
	});

	$ ( '.button-primary' ).on( 'click', function( e ) {
		if ( $(this).val() == 'Save Salesforce Setting' ) {			
			var wcap_sf_user_name = $("#wcap_salesforce_user_name").val();
			var wcap_sf_password  = $("#wcap_salesforce_password").val();
			var wcap_sf_token     = $("#wcap_salesforce_security_token").val();

			if ( '' == wcap_sf_token ) {
				$( "#wcap_salesforce_security_token_label_error" ).fadeIn();
	            setTimeout( function(){
		            $( "#wcap_salesforce_security_token_label_error" ).fadeOut();
		        },4000);
		        e.preventDefault();
			}
			if ( '' == wcap_sf_password ) {
				$( "#wcap_salesforce_password_label_error" ).fadeIn();
	            setTimeout( function() {
		            $( "#wcap_salesforce_password_label_error" ).fadeOut();
		        },4000);
			    e.preventDefault();
			}
			if ( '' == wcap_sf_user_name ) {
				$( "#wcap_salesforce_user_name_label_error" ).fadeIn();
	            setTimeout( function(){
		            $( "#wcap_salesforce_user_name_label_error" ).fadeOut();
		        },4000);
	            e.preventDefault();
			}

			if ( ( wcap_salesforce_params.wcap_sf_user_name  != wcap_sf_user_name 
				|| wcap_salesforce_params.wcap_sf_token    != wcap_sf_token
				|| wcap_salesforce_params.wcap_sf_password != wcap_sf_password ) &&
				 ( wcap_salesforce_params.wcap_sf_connection_established != 'yes' || wcap_sf_connection_established != 'yes') ){
				e.preventDefault();
				var data = {
		            wcap_sf_user_name: wcap_sf_user_name,
		            wcap_sf_password: wcap_sf_password,
		            wcap_sf_token: wcap_sf_token,
		            action: 'wcap_salesforce_check_connection'
		        };
		        $( '#wcap_salesforce_test_connection_ajax_loader' ).show();
		        $.post( wcap_salesforce_params.ajax_url, data, function( response ) {

		    		wcap_check_string = response.indexOf("successfuly established");
		    		$( '#wcap_salesforce_test_connection_ajax_loader' ).hide();
		    		if ( wcap_check_string !== -1 ){
			    		$('#wcap_salesforce_crm_form').submit();
			        }else{
			    		$( '#wcap_salesforce_test_connection_message' ).html( response );
					}
			    });
			}
		}
	});
	var wcap_all = '';
	$ ( '.add_single_cart_salesforce' ).on( 'click', function( e ) {
		var wcap_selected_id = [];
		wcap_selected_id.push ( $( this ).attr( 'data-id' ) );
		var wcap_all = '';
		$( '#wcap_manual_email_data_loading' ).show();
		$( '#wcap_manual_email_data_loading_text' ).show();
		var data = {
			action                  : 'wcap_add_to_salesforce_crm',
			wcap_abandoned_cart_ids : wcap_selected_id,
			wcap_all                : wcap_all
		};

		$.post( wcap_salesforce_params.ajax_url, data, function( response ) {
			$( '#wcap_manual_email_data_loading' ).hide();
			$( '#wcap_manual_email_data_loading_text' ).hide();
			var wcap_check_string = response.indexOf("duplicate_record");
			if ( wcap_check_string !== -1 ){

        		var display_message       = 'The Abandoned cart is already exported to Salesforce CRM.';
				$( ".wcap_salesforce_message_p_error" ).html( display_message );
	            $( "#wcap_salesforce_message_error" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_salesforce_message_error" ).fadeOut();
	            },4000);
        	}else{
        		var res = response.split(",");
				var abadoned_order_count = res[0];

				var order                = 'order';
				if ( abadoned_order_count > 1 ){
					order 				 = 'orders';
				}
				
				var display_message       = abadoned_order_count  + ' created Abandoned ' +  order + ' has been successfully added to Salesforce CRM.';
				$( ".wcap_salesforce_message_p" ).html( display_message );
	            $( "#wcap_salesforce_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_salesforce_message" ).fadeOut();
	            },4000);
        	}
		});
	});

	$ ( '#add_all_carts_salesforce' ).on( 'click', function( e ) {		

		wcap_all = 'yes';
		var wcap_selected_id = [];
		$( '#wcap_manual_email_data_loading' ).show();
		$( '#wcap_manual_email_data_loading_text' ).show();

		var data = {
			action                  : 'wcap_add_to_salesforce_crm',
			wcap_abandoned_cart_ids : wcap_selected_id,
			wcap_all                : wcap_all
		};

		$.post( wcap_salesforce_params.ajax_url, data, function( response ) {
			$( '#wcap_manual_email_data_loading' ).hide();
			$( '#wcap_manual_email_data_loading_text' ).hide();

			var wcap_check_string = response.indexOf("no_record");
			if ( wcap_check_string !== -1 ){
				var display_message       = 'All the Abandoned carts are already exported to Salesforce CRM. No new carts found to export.';
				$( ".wcap_salesforce_message_p_error" ).html( display_message );
	            $( "#wcap_salesforce_message_error" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_salesforce_message_error" ).fadeOut();
	            },4000);
			}else{
				var res = response.split(",");
				var abadoned_order_count = res[0];
				
				var order                = 'order';
				if ( abadoned_order_count > 1 ){
					order 				 = 'orders';
				}
				
				var display_message       = abadoned_order_count  + ' created Abandoned ' +  order + ' has been successfully added to Salesforce CRM.';
				$( ".wcap_salesforce_message_p" ).html( display_message );
	            $( "#wcap_salesforce_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_salesforce_message" ).fadeOut();
	            },4000);
        	}
		});
	});

	$ ( '#doaction' ).on( 'click', function( e ) {
		if ( $( '#bulk-action-selector-top' ).val() == 'wcap_add_salesforce' ) {
			
			var checkboxes = document.getElementsByName('abandoned_order_id[]');
			var wcap_selected_id = [];
			var wcap_parent      = [];
		  	for (var i = 0; i < checkboxes.length; i++) {
		     
		     	if ( checkboxes[i].checked ) {
		     		var email_check = $( checkboxes[i] ).parent().parent().find('.email').text();
		     		var wcap_salesforce = email_check.indexOf( "Add to Salesforce CRM" );
		     		wcap_parent [ checkboxes[i].value ] =  wcap_salesforce ;
		        	wcap_selected_id.push( checkboxes[i].value );
		    	}
		  	}

		  	if ( wcap_selected_id.length == 0 ) {
		  		var display_message = 'Please select atleast 1 Abandoned order to Add to Salesforce CRM.';
				$( ".wcap_salesforce_message_p_error" ).html( display_message );
	            $( "#wcap_salesforce_message_error" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_salesforce_message_error" ).fadeOut();
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
					$( ".wcap_salesforce_message_p_error" ).html( display_message );
		            $( "#wcap_salesforce_message_error" ).fadeIn();
		            setTimeout( function() {
		            	$( "#wcap_salesforce_message_error" ).fadeOut();
		            },3000);
					e.preventDefault();
					return;
				}
			}

		  	var wcap_all = '';
		  	$( '#wcap_manual_email_data_loading' ).show();
		  	$( '#wcap_manual_email_data_loading_text' ).show();
			var data = {
				action                  : 'wcap_add_to_salesforce_crm',
				wcap_abandoned_cart_ids : wcap_selected_id,
				wcap_all                : wcap_all
			};
			
			$.post( wcap_salesforce_params.ajax_url, data, function( response ) {
				$( '#wcap_manual_email_data_loading' ).hide();
				$( '#wcap_manual_email_data_loading_text' ).hide();
				var wcap_check_string = response.indexOf("duplicate_record");
				if ( wcap_check_string !== -1 ){

	        		var display_message       = 'Selected Abandoned carts are already exported to Salesforce CRM. No new carts found to export.';
					$( ".wcap_salesforce_message_p_error" ).html( display_message );
		            $( "#wcap_salesforce_message_error" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_salesforce_message_error" ).fadeOut();
		            },4000);
	        	}else{
	        		var res = response.split(",");
					var abadoned_order_count = res[0];
					
					var order                = 'order';
					if ( abadoned_order_count > 1 ){
						order 				 = 'orders';
					}
					
					var display_message       = abadoned_order_count  + ' created Abandoned ' +  order + ' has been successfully added to Salesforce CRM.';
					$( ".wcap_salesforce_message_p" ).html( display_message );
		            $( "#wcap_salesforce_message" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_salesforce_message" ).fadeOut();
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
		     		var email_check 	= $( checkboxes[i] ).parent().parent().find('.email').text();
		     		var wcap_salesforce = email_check.indexOf( "Add to Salesforce CRM" );
		     		wcap_parent [ checkboxes[i].value ] =  wcap_salesforce ;
		        	wcap_selected_id.push( checkboxes[i].value );
		    	}
		  	}

		  	if ( wcap_selected_id.length == 0 ) {
		  		var display_message = 'Please select atleast 1 Abandoned order to Add to Salesforce CRM.';
				$( ".wcap_salesforce_message_p_error" ).html( display_message );
	            $( "#wcap_salesforce_message_error" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_salesforce_message_error" ).fadeOut();
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
					$( ".wcap_salesforce_message_p_error" ).html( display_message );
		            $( "#wcap_salesforce_message_error" ).fadeIn();
		            setTimeout( function() {
		            	$( "#wcap_salesforce_message_error" ).fadeOut();
		            },3000);
					e.preventDefault();
					return;
				}
			}
			
		  	var wcap_all = '';
		  	$( '#wcap_manual_email_data_loading' ).show();
		  	$( '#wcap_manual_email_data_loading_text' ).show();
			var data = {
				action                  : 'wcap_add_to_salesforce_crm',
				wcap_abandoned_cart_ids : wcap_selected_id,
				wcap_all                : wcap_all
			};
			
			$.post( wcap_salesforce_params.ajax_url, data, function( response ) {
				$( '#wcap_manual_email_data_loading' ).hide();
				$( '#wcap_manual_email_data_loading_text' ).hide();
				var wcap_check_string = response.indexOf("duplicate_record");
				if ( wcap_check_string !== -1 ){

	        		var display_message       = 'Selected Abandoned carts are already exported to Salesforce CRM. No new carts found to export.';
					$( ".wcap_salesforce_message_p_error" ).html( display_message );
		            $( "#wcap_salesforce_message_error" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_salesforce_message_error" ).fadeOut();
		            },4000);
	        	}else{
	        		var res = response.split(",");
					var abadoned_order_count = res[0];
					
					var order                = 'order';
					if ( abadoned_order_count > 1 ){
						order 				 = 'orders';
					}
					
					var display_message       = abadoned_order_count  + ' created Abandoned ' +  order + ' has been successfully added to Salesforce CRM.';
					$( ".wcap_salesforce_message_p" ).html( display_message );
		            $( "#wcap_salesforce_message" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_salesforce_message" ).fadeOut();
		            },4000);
	        	}
			});
			e.preventDefault();
		}
	});

	var wcap_user_type_selected = $( 'input[type=radio][name=wcap_salesforce_user_type]:checked' ).val();
	if( wcap_user_type_selected == 'contact' ) {
		$( ".wcap_salesforce_lead_company" ).hide();
            $( ".wcap_salesforce_lead_company" ).closest("tr").hide();
	}
	$( 'input[type=radio][name=wcap_salesforce_user_type]' ).change(function() {
        if ( this.value == 'lead' ) {
        	$( ".wcap_salesforce_lead_company" ).closest("tr").fadeIn();
            $( ".wcap_salesforce_lead_company" ).fadeIn();
        } else if ( this.value == 'contact' ) {
        	$( ".wcap_salesforce_lead_company" ).fadeOut();
            $( ".wcap_salesforce_lead_company" ).closest("tr").fadeOut();
        }
    });
});
