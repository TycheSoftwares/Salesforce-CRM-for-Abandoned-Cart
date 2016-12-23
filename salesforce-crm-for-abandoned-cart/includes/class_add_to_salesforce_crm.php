<?php
/*
* This class help to add / get / delete the data to the Agile CRM
*/
class Wcap_Add_To_Salesforce_CRM
{
    public static function wcap_add_data_to_salesforce_crm ( $data, $wcap_sf_username, $wcap_sf_password, $wcap_sf_security_token, $wcap_sf_user_type, $wcap_product_details ) {

        //wp_mail ('kiribhavik4@gmail.com', 'add to crm', 'frm cron');
        $mySforceConnection = new SforcePartnerClient();
        $wcap_plguins_url   = plugins_url() . '/salesforce-crm-for-abandoned-cart';
      
        $mySoapClient       = $mySforceConnection->createConnection( $wcap_plguins_url .'/soapclient/partner.wsdl.xml');
        
        $wacp_pswd_token    = $wcap_sf_password . $wcap_sf_security_token;
        $mylogin            = $mySforceConnection->login( $wcap_sf_username, $wacp_pswd_token );

        $header             = new AssignmentRuleHeader('01Q300000005lDg', false);
      
        $mySforceConnection->setAssignmentRuleHeader($header);

        $type = '';
        if ( 'lead' == $wcap_sf_user_type ){
          $type = 'LEAD';
        }else if ( 'contact' == $wcap_sf_user_type ){
          $type = 'CONTACT';
        }
      
        $sObject1 = new SObject();
        $sObject1->fields = $data;
        $sObject1->type   = $type;
        $wcap_user_email = str_replace( "+", "\+", $data['email']);
        $search_result = '';
        $searchResult  = array();

        try {
          $old_data = ob_get_clean();
          $search = 'FIND {'.$wcap_user_email.'} IN EMAIL FIELDS '.
                    "RETURNING $type(ID, OWNERID) ";
          $searchResult = $mySforceConnection->search($search);
        } catch (Exception $e) {
          $old_data = ob_get_clean();
        }
            
        $wcap_all_data = $searchResult->searchRecords;

        if ( count( $wcap_all_data ) == 0 ){
          try {
            $old_data = ob_get_clean();
            $createResponse = $mySforceConnection->create( array ( $sObject1 ) );
            echo $type . '_created , ';

          } catch ( SoapFault $fault ) {
            $old_data = ob_get_clean();
            //echo ( $fault->faultstring );
            echo $type . '_created_error , ';
          }
        }

        if ( isset( $createResponse[0] ) && count( $createResponse[0] ) > 0 && 'contact' == $wcap_sf_user_type ){

          $all_data     = $createResponse[0];
          $wcap_user_id = '';
          foreach ( $all_data as $key => $value) {
              
              if ( $key == 'id'){
                $wcap_user_id = $value ;
              }
          }
          
          $createFields = array (
            'Title'     => 'Abandoned Cart Details',
            'body'      => $wcap_product_details,
            'ParentId'  => $wcap_user_id
          );
          $sObject1 = new SObject();
          $sObject1->fields = $createFields;
          $sObject1->type = 'NOTE';
          try {
            $createResponse = $mySforceConnection->create( array ( $sObject1 ) );
            $old_data = ob_get_clean();
            
            echo 'notes , ';
          } catch (SoapFault $fault) {
            $old_data = ob_get_clean();
            //echo ( $fault->faultstring);
            echo 'notes_error , ';

          }
        }

        if ( count( $wcap_all_data ) > 0 && 'contact' == $wcap_sf_user_type  ){

          $wcap_all_data          = $searchResult->searchRecords[0];
          $wcap_search_data_field = $wcap_all_data->fields;
          $wcap_sf_owner_id       = $wcap_search_data_field->OwnerId;
          $wcap_sf_parent_id      = $wcap_all_data->Id;
          
          $createFields = array (
            'Title'     => 'Abandoned Cart Details',
            'body'      => $wcap_product_details,
            'OwnerId'   => $wcap_sf_owner_id,
            'ParentId'  => $wcap_sf_parent_id
          );
          $sObject1 = new SObject();
          $sObject1->fields = $createFields;
          $sObject1->type = 'NOTE';
          try {
        
            $createResponse = $mySforceConnection->create( array ( $sObject1 ) );
            $old_data = ob_get_clean();
            echo 'notes , ';
        
          } catch (SoapFault $fault) {
            $old_data = ob_get_clean();
            echo 'notes_error , ';
          }
        }
  }
}