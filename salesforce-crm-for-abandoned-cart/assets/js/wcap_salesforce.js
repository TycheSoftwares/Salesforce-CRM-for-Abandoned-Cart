jQuery(function( $ ) {

	$ ( '.button-primary' ).on( 'click', function( e ) {
		if ( $(this).val() == 'Save Salesforce changes' ) {
			
			var wcap_sf_user_name = document.getElementById('wcap_salesforce_user_name').value;
			var wcap_sf_passwrod  = document.getElementById('wcap_salesforce_password').value;
			var wcap_sf_token     = document.getElementById('wcap_salesforce_security_token').value;

			if ('' == wcap_sf_token ){
				$( "#wcap_salesforce_security_token_label_error" ).fadeIn();
	            setTimeout( function(){
		            $( "#wcap_salesforce_security_token_label_error" ).fadeOut();
		        },4000);

		        e.preventDefault();
			}
			if ('' == wcap_sf_passwrod ){
				$( "#wcap_salesforce_password_label_error" ).fadeIn();
	            setTimeout( function(){
		            $( "#wcap_salesforce_password_label_error" ).fadeOut();
		        },4000);
			    e.preventDefault();
			}
			if ('' == wcap_sf_user_name ){
				$( "#wcap_salesforce_user_name_label_error" ).fadeIn();
	            setTimeout( function(){
		            $( "#wcap_salesforce_user_name_label_error" ).fadeOut();
		        },4000);
	            e.preventDefault();
			}
		}
	});

	var wcap_all = '';
	$ ( '.add_single_cart_salesforce' ).on( 'click', function( e ) {
		var wcap_selected_id = [];
		wcap_selected_id.push ( $( this ).attr( 'data-id' ) );

		console.log (wcap_selected_id);
		$( '#wcap_manual_email_data_loading' ).show();
		var data = {
			action                  : 'wcap_add_to_salesforce_crm',
			wcap_abandoned_cart_ids : wcap_selected_id,
			wcap_all                : wcap_all
		};

		$.post( wcap_salesforce_params.ajax_url, data, function( response ) {
			$( '#wcap_manual_email_data_loading' ).hide();
			
			var res = response.split(",");
			var abadoned_order_count = res[1];
			var order                = 'order';
			if ( abadoned_order_count > 1 ){
				order 				 = 'orders';
			}
			
			var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Salesforce CRM.'
			$( ".wcap_salesforce_message_p" ).html( display_message );
            $( "#wcap_salesforce_message" ).fadeIn();
            setTimeout( function(){
            	$( "#wcap_salesforce_message" ).fadeOut();
            },4000);
		});
	});

	$ ( '#add_all_carts_salesforce' ).on( 'click', function( e ) {
		
		wcap_all = 'yes';
		var wcap_selected_id = [];

		$( '#wcap_manual_email_data_loading' ).show();
		var data = {
			action                  : 'wcap_add_to_salesforce_crm',
			wcap_abandoned_cart_ids : wcap_selected_id,
			wcap_all                : wcap_all
		};

		$.post( wcap_salesforce_params.ajax_url, data, function( response ) {
			$( '#wcap_manual_email_data_loading' ).hide();

			var res = response.split(",");
			var abadoned_order_count = res[1];
			var order                = 'order';
			if ( abadoned_order_count > 1 ){
				order 				 = 'orders';
			}
			
			var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Agile CRM.'
			$( ".wcap_salesforce_message_p" ).html( display_message );
            $( "#wcap_salesforce_message" ).fadeIn();
            setTimeout( function(){
            	$( "#wcap_salesforce_message" ).fadeOut();
            },4000);
		});
	});

	$ ( '#doaction' ).on( 'click', function( e ) {
		if ( $( '#bulk-action-selector-top' ).val() == 'wcap_add_salesforce' ) {


			if ( $( '#bulk-action-selector-top' ).val() == 'wcap_add_salesforce' ) {

				var checkboxes = document.getElementsByName('abandoned_order_id[]');
				var wcap_selected_id = [];
				var wcap_parent      = [];
			  	for (var i = 0; i < checkboxes.length; i++) {
			     
			     	if ( checkboxes[i].checked ) {
			     		var email_check = $( checkboxes[i] ).parent().parent().find('.email').text();
			     		var wcap_salesforce = email_check.indexOf("Add to salesforce CRM");
			     		wcap_parent [ checkboxes[i].value ] =  wcap_salesforce ;
			        	wcap_selected_id.push( checkboxes[i].value );
			    	}
			  	}

			  	if ( wcap_selected_id.length == 0 ){

			  		var display_message       = 'Please select atleast 1 Abandoned order to Add to Salesforce CRM.';
					$( ".wcap_ac_bulk_message_p" ).html( display_message );
		            $( "#wcap_ac_bulk_message" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_ac_bulk_message" ).fadeOut();
		            },3000);
			  		e.preventDefault();	
			  		return;
			  	}

			  	var allow = 'no';
				if ( wcap_parent.length > 0 ){
					for ( var key in wcap_parent ) {
					  //console.log("key " + key + " has value " + wcap_parent[key]);
					  if ( wcap_parent[key] > 0 ){
					  	allow = 'yes';
					  }else{
					  	var visitor = document.querySelectorAll("input[ value = '"+ key +"']");
					  	visitor[0].checked = false;
					  }
					}
					
					if ( 'no' == allow ){
						var display_message       = 'Add to Salesforce CRM cannot be applied on Visitor carts.';
						$( ".wcap_ac_bulk_message_p" ).html( display_message );
			            $( "#wcap_ac_bulk_message" ).fadeIn();
			            setTimeout( function(){
			            	$( "#wcap_ac_bulk_message" ).fadeOut();
			            },3000);
						e.preventDefault();
						return;
					}
				}
			}
			var checkboxes = document.getElementsByName('abandoned_order_id[]');
			var wcap_selected_id = [];
		  	for (var i = 0; i < checkboxes.length; i++) {
		     
		     	if ( checkboxes[i].checked ) {
		        	wcap_selected_id.push( checkboxes[i].value );
		    	}
		  	}
		  	
		  	$( '#wcap_manual_email_data_loading' ).show();
			var data = {
				action                  : 'wcap_add_to_salesforce_crm',
				wcap_abandoned_cart_ids : wcap_selected_id,
				wcap_all                : wcap_all
			};
			
			$.post( wcap_salesforce_params.ajax_url, data, function( response ) {
				$( '#wcap_manual_email_data_loading' ).hide();
				var res = response.split(",");
				var abadoned_order_count = res[1];
				var order                = 'order';
				if ( abadoned_order_count > 1 ){
					order 				 = 'orders';
				}
				
				var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Salesforce CRM.'
				$( ".wcap_salesforce_message_p" ).html( display_message );
	            $( "#wcap_salesforce_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_salesforce_message" ).fadeOut();
	            },4000);
			});
			e.preventDefault();
		}
	});

	$ ( '#doaction2' ).on( 'click', function( e ) {
		if ( $( '#bulk-action-selector-bottom' ).val() == 'wcap_add_salesforce' ) {

			if ( $( '#bulk-action-selector-bottom' ).val() == 'wcap_add_salesforce' ) {

				var checkboxes = document.getElementsByName('abandoned_order_id[]');
				var wcap_selected_id = [];
				var wcap_parent      = [];
			  	for (var i = 0; i < checkboxes.length; i++) {
			     
			     	if ( checkboxes[i].checked ) {
			     		var email_check = $( checkboxes[i] ).parent().parent().find('.email').text();
			     		var wcap_salesforce = email_check.indexOf("Add to Salesforce CRM");
			     		wcap_parent [ checkboxes[i].value ] =  wcap_salesforce ;
			        	wcap_selected_id.push( checkboxes[i].value );
			    	}
			  	}

			  	if ( wcap_selected_id.length == 0 ){

			  		var display_message       = 'Please select atleast 1 Abandoned order to Add to Salesforce CRM.';
					$( ".wcap_ac_bulk_message_p" ).html( display_message );
		            $( "#wcap_ac_bulk_message" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_ac_bulk_message" ).fadeOut();
		            },3000);
			  		e.preventDefault();	
			  		return;
			  	}
			}

			var allow = 'no';
			if ( wcap_parent.length > 0 ){
				for ( var key in wcap_parent ) {
				  //console.log("key " + key + " has value " + wcap_parent[key]);
				  if ( wcap_parent[key] > 0 ){
				  	allow = 'yes';
				  }else{
				  	var visitor = document.querySelectorAll("input[ value = '"+ key +"']");
				  	visitor[0].checked = false;
				  }
				}
				
				if ( 'no' == allow ){
					var display_message       = 'Add to Salesforce CRM cannot be applied on Visitor carts.';
					$( ".wcap_ac_bulk_message_p" ).html( display_message );
		            $( "#wcap_ac_bulk_message" ).fadeIn();
		            setTimeout( function(){
		            	$( "#wcap_ac_bulk_message" ).fadeOut();
		            },3000);
					e.preventDefault();
					return;
				}
			}
			
			var checkboxes = document.getElementsByName('abandoned_order_id[]');
			var wcap_selected_id = [];
		  	for (var i = 0; i < checkboxes.length; i++) {
		     
		     	if ( checkboxes[i].checked ) {
		        	wcap_selected_id.push( checkboxes[i].value );
		    	}
		  	}
		  	
		  	$( '#wcap_manual_email_data_loading' ).show();
			var data = {
				action                  : 'wcap_add_to_salesforce_crm',
				wcap_abandoned_cart_ids : wcap_selected_id,
				wcap_all                : wcap_all
			};
			
			$.post( wcap_salesforce_params.ajax_url, data, function( response ) {
				$( '#wcap_manual_email_data_loading' ).hide();
				var res = response.split(",");
				var abadoned_order_count = res[1];
				var order                = 'order';
				if ( abadoned_order_count > 1 ){
					order 				 = 'orders';
				}
				
				var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Salesforce CRM.'
				$( ".wcap_salesforce_message_p" ).html( display_message );
	            $( "#wcap_agile_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_salesforce_message" ).fadeOut();
	            },4000);
			});
			e.preventDefault();
		}
	});

	var wcap_user_type_selected = $( 'input[type=radio][name=wcap_salesforce_user_type]:checked' ).val();
	if( wcap_user_type_selected == 'contact' ) {
		$(".wcap_salesforce_lead_company").hide();
            $(".wcap_salesforce_lead_company").closest("tr").hide();
	}

	$('input[type=radio][name=wcap_salesforce_user_type]').change(function() {
        if (this.value == 'lead') {
        	$(".wcap_salesforce_lead_company").closest("tr").fadeIn();
            $(".wcap_salesforce_lead_company").fadeIn();
        }
        else if (this.value == 'contact') {
        	$(".wcap_salesforce_lead_company").fadeOut();
            $(".wcap_salesforce_lead_company").closest("tr").fadeOut();
        }
    });
});