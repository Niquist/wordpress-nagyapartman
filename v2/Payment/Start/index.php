<?php
/*
 * 2020.12.30:
 * This file is part of the Nagyapartman-Barion plugin
 * for localhost tests.
 *
 * This file will always return a mock-barion reply to the
 * v2/Payment/Start request.
 */
$file = fopen("test_".date( 'Ymd_His' ).".txt","w");
$response = array();
if( 0 == strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') )
{
    $response = array(
        'PaymentId' => '2337088ab74aeb118bc4001dd8b71cc4',
        'PaymentRequestId' => 'N.apartman_pf.paymnt._yMiRYbt3QweOSlBdZKETAjx5Iu8Nvkrmkr4MdJ3ocm9xWi6GYGL8UHb4AgV1CyaOnDvPqPDsh5acWh',
        'Status' => 'Prepared',
        'QRUrl' => 'https://api.test.barion.com/qr/generate?paymentId=2337088a-b74a-eb11-8bc4-001dd8b71cc4&size=Large',
        'Transactions' => array(
            '0' => array (
                    'POSTransactionId' => 'EXMPLSHOP-PM-001/TR001',
                    'TransactionId' => '2437088ab74aeb118bc4001dd8b71cc4',
                    'Status' => 'Prepared',
                    'Currency' => 'HUF',
                    'TransactionTime' => '2020-12-30T15:56:13.028',
                    'RelatedId' => ''
                )
        ),
        'RecurrenceResult' => 'None',
        'ThreeDSAuthClientData' => '',
        'GatewayUrl' => 'https://secure.test.barion.com/Pay?Id=2337088ab74aeb118bc4001dd8b71cc4&lang=hu_HU',
        'RedirectUrl' => 'http://localhost/wordpress/wordpress-nagyapartman?paymentId=2337088ab74aeb118bc4001dd8b71cc4',
        'CallbackUrl' => 'http://localhost/wordpress/wordpress-nagyapartman?paymentId=2337088ab74aeb118bc4001dd8b71cc4',
        'TraceId' => '',
        'Errors' => array()
    );
}
fwrite($file,date('y-m-d')."\nresponse=".print_r($response,true));
fclose($file);

/* Echo back the json encoded reply */
header('Content-Type: application/json');
echo json_encode($response);