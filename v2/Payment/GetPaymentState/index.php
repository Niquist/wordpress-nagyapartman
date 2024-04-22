<?php
/*
 * 2020.12.30:
 * This file is part of the Nagyapartman-Barion plugin
 * for localhost tests.
 *
 * This file will always return a mock-barion reply to the
 * v2/Payment/GetPaymentState request.
 */
$file = fopen("test_".date( 'Ymd_His' ).".txt","w");
$response = array();
if( 0 == strcasecmp($_SERVER['REQUEST_METHOD'], 'GET') )
{
    $response = array(
        'PaymentId' => '046564c49a3ceb118bc4001dd8b71cc4',
        'PaymentRequestId' => 'N.apartman_pf.paymnt._uRIijMwZAK2bCnOFvCzwETyngQfqt4PGaxsHceyXhzK6lDYoe0O5dp1v2mDBY43rJkBIqVJiMNWSPp',
        'OrderNumber' => '',
        'POSId' => '0f8af7e1306b4595b9a9f9167ae9639e',
        'POSName' => 'Nagy Apartman Siófok',
        'POSOwnerEmail' => 'hello@nagyapartmansiofok.hu',
        'Status' => 'Expired',
        'PaymentType' => 'Immediate',
        'FundingSource' => '',
        'RecurrenceType' => '',
        'TraceId' => '',
        'FundingInformation' => '',
        'AllowedFundingSources' => array (
            '0' => 'All'
        ),
        'GuestCheckout' => '',
        'CreatedAt' => '2020-12-12T16:54:59.24Z',
        'ValidUntil' => '2020-12-12T17:24:59.24Z',
        'CompletedAt' => '2020-12-12T17:25:40.968Z',
        'ReservedUntil' => '',
        'DelayedCaptureUntil' => '',
        'Transactions' => array(
            '0' => array(
                'TransactionId' => '056564c49a3ceb118bc4001dd8b71cc4',
                'POSTransactionId' => 'EXMPLSHOP-PM-001/TR001',
                'TransactionTime' => '2020-12-12T16:54:59.24Z',
                'Total' => 37,
                'Currency' => 'HUF',
                'Payer' => '',
                'Payee' => array(
                    'Name' => array(
                        'LoginName' => 'hello@nagyapartmansiofok.hu',
                        'FirstName' => 'Kinga',
                        'LastName' => 'Nagyné Alföldy',
                        'OrganizationName' => ''
                    ),
                    'Email' => 'hello@nagyapartmansiofok.hu',
                ),
                'Comment' => 'A brief description of the transaction',
                'Status' => 'Expired',
                'TransactionType' => 'Unspecified',
                'Items' => array(
                    '0' => array(
                        'Name' => 'iPhone 7 smart case',
                        'Description' => 'Durable elegant phone case / matte black',
                        'Quantity' => 1,
                        'Unit' => 'piece',
                        'UnitPrice' => '25',
                        'ItemTotal' => '25',
                        'SKU' => 'EXMPLSHOP/SKU/PHC-01',
                    )
                ),
                'RelatedId' => '',
                'POSId' => '0f8af7e1306b4595b9a9f9167ae9639e',
                'PaymentId' => '046564c49a3ceb118bc4001dd8b71cc4',
            )
        ),
        'Total' => 37,
        'SuggestedLocale' => 'hu-HU',
        'FraudRiskScore' => '',
        'RedirectUrl' => 'http://example.org/?paymentId=046564c49a3ceb118bc4001dd8b71cc4',
        'CallbackUrl' => 'http://example.org/?paymentId=046564c49a3ceb118bc4001dd8b71cc4',
        'Currency' => 'HUF',
        'Errors' => array()
    );
    if ( !array_key_exists( 'PaymentId', $_GET ) || (0 != strcmp( $_GET['PaymentId'], $response['PaymentId'] ))) {
        $response = array( 'Status' => 'PaymentIds DO NOT MATCH' );
    }
    else {
        fwrite($file,date('y-m-d')."\nget.paymentid=".$_GET['PaymentId'].', response.paymentid='.$response['PaymentId']."\n");
    }
}
fwrite($file,date('y-m-d')."\nresponse=".print_r($response,true));

fclose($file);

/* Echo back the json encoded reply */
header('Content-Type: application/json');
echo json_encode( $response );