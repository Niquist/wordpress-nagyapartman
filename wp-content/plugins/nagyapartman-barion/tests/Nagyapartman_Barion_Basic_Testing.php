<?php
/**
 * Class SampleTest
 *
 * @package Nagyapartman_Barion
 *
 * Contains 24 tests methods for basic functionality.
 */
require_once( 'Base-Nagyapartman_Barion_Basic.php' );

define( "TEST_DB_FILE", "test_db.txt" );

class Nagyapartman_Barion_Basic_Testing extends Nagyapartman_Barion_Basic {

    public function __construct() {
        parent::__construct(); /* One must call! */
        $this->resetInstance();
    }

    /* #1 */
    public function test__get_api_url() {
        $this->resetInstance();
        if ( WP_BARION_TESTING_ON_LOCALHOST ) {
            $this->assertSame( $this->instance->get_api_url(), LOCALHOST_FAKE_BARION_SERVER );
        }
        else {
            $this->assertSame( $this->instance->get_api_url(), BARION_SANDBOX_API_URL );
        }
    }

    /* #2 */
    public function test__hook_barion_pixel_basic_js() {
        $this->resetInstance();

        ob_start();
        $this->instance->hook_barion_pixel_basic_js();
        $echoed_output = ob_get_contents();
        ob_end_clean();
        $compare_str =
        '<script>
            // Create BP element on the window
            window["bp"] = window["bp"] || function () {
                (window["bp"].q = window["bp"].q || []).push(arguments);
            };
            window["bp"].l = 1 * new Date();

            // Insert a script tag on the top of the head to load bp.js
            scriptElement = document.createElement("script");
            firstScript = document.getElementsByTagName("script")[0];
            scriptElement.async = true;
            scriptElement.src = \'https://pixel.barion.com/bp.js\';
            firstScript.parentNode.insertBefore(scriptElement, firstScript);
            window[\'barion_pixel_id\'] = \'BPT-YP7eEebTye-83\';

            // Send init event
            bp(\'init\', \'addBarionPixelId\', window[\'barion_pixel_id\']);
        </script>

        <noscript>
            <img height="1" width="1" style="display:none" alt="Barion Pixel" src="https://pixel.barion.com/a.gif?ba_pixel_id=\'BPT-YP7eEebTye-83\'&ev=contentView&noscript=1">
        </noscript>
        ';
        $this->assertSame( $echoed_output, $compare_str );
    }

    /* #3 */
    public function test__save_payment_data_in_db() {
        $this->resetInstance();
        $this->wLog( __FUNCTION__ .' : start', TEST_DB_FILE );

        /* Test data */
        $test_output = array (
            'payment_request_id' => $this->instance->get_payment_request_id(),
            'userID' => 3,
            'paymentId' => '',
            'created' => '',
            'status' => '',
            'updated' => '',
            'callback_message' => '-'
        );
        $wpdb = $this->instance->get_wpdb();
        $query = 'SELECT payment_request_id,userID,paymentId,created,status,updated,callback_message FROM `'.$wpdb->prefix.$this->instance->get_tbl_name().'`';

        /*
         * Test with minimal data.
         */
        $ret_val = $this->instance->save_payment_data_in_db( 3 );
        $this->assertSame( 1, $ret_val );
        $ret_array = $wpdb->get_results( $query );
        $this->wLog( __FUNCTION__ .' : ret_array='.print_r($ret_array, true), TEST_DB_FILE );
        $ret_array = json_decode(json_encode($ret_array), true)[0];
        $test_output['created'] = $ret_array['created'];  // Small cheat to apply.
        $test_output['updated'] = $ret_array['created'];  // Small cheat to apply.
        $this->assertTrue( $this->arrays_are_similar( $test_output, $ret_array, __FUNCTION__ ) );

        /*
         * Test with input data.
         */
        $ret_val = $this->instance->save_payment_data_in_db( 5, 'paymentId_20201219', 'Status_OK', '2020-12-19 09:06:00', 'payment_req_id__2' );
        $this->assertSame( 1, $ret_val );
        $ret_array = $wpdb->get_results( $query );
        $ret_array = json_decode(json_encode($ret_array), true)[1];
        $test_output['userID'] = 5;
        $test_output['payment_request_id'] = 'payment_req_id__2';
        $test_output['paymentId'] = 'paymentId_20201219';
        $test_output['status'] = 'Status_OK';
        $test_output['created'] = '2020-12-19 09:06:00';
        $test_output['updated'] = '2020-12-19 09:06:00';
        $this->assertTrue( $this->arrays_are_similar( $test_output, $ret_array, __FUNCTION__ ) );
    }

    /* #4 */
    public function test__pack_json_data() {
        $this->resetInstance();
        $str1 = $this->instance->pack_json_data( ['key1' => 'value1', 'key2' => 'value2'] );
        $str2 = '{"key1":"value1","key2":"value2"}';
        $this->assertSame( $str1, $str2 );
    }

    /* #5 */
    /* For this test, one will use the 'ajax_req_test.php' resides in the localhost pathinfo
     * for a basic json answer.
     */
    public function test__start_barion_payment() {
        /*
         * Test data
         */
        $transactions = array(
            array(
                'POSTransactionId' => 'EXMPLSHOP-PM-001/TR001',
                'Payee' => 'hello@nagyapartmansiofok.hu',
                'Total' => 37,
                'Comment' => 'A brief description of the transaction',
                'Items' => array(
                    array(
                        'Name' => 'iPhone 7 smart case',
                        'Description' => 'Durable elegant phone case / matte black',
                        'Quantity' => 1,
                        'Unit' => 'piece',
                        'UnitPrice' => 25.2,
                        'ItemTotal' => 25.2,
                        'SKU' => 'EXMPLSHOP/SKU/PHC-01',
                    )
                )
            )
        );
        $billing_address = array(
            'Country' => 'HU',
            'Region' => 'BU',
            'City' => 'Budapest',
            'Zip' => 1173,
            'Street' => 97,
        );
        $data = array(
            'POSKey' => 'c9a000420f7e45b1bfaf9859b77757d2',
            'PaymentType' => 'Immediate',
            'PaymentWindow' => '0.00:30:00',
            'FundingSources' => array( 'All' ),
            'PaymentRequestId' => 'N.apartman_pf.paymnt._yMiRYbt3QweOSlBdZKETAjx5Iu8Nvkrmkr4MdJ3ocm9xWi6GYGL8UHb4AgV1CyaOnDvPqPDsh5acWh',
            'RedirectUrl' => 'http://localhost/wordpress/wordpress-nagyapartman/',
            'CallbackUrl' => 'http://localhost/wordpress/wordpress-nagyapartman/',
            'Locale' => 'hu-HU',
            'Currency' => 'HUF',
            'Transactions' => $transactions,
            'BillingAddress' => $billing_address,
            'ChallengePreference' => 20
        );

        /*
         * Test OK scenario
         */
        $this->resetInstance();
        $response = $this->instance->start_barion_payment( $data );
        $this->assertSame( $response['response']['code'], \Nagyapartman_Barion\Nagyapartman_Barion::HTML_OK );
        $this->assertSame( $response['response']['message'], 'OK' );

        if ( !WP_BARION_TESTING_ON_LOCALHOST ) {
            /*
             * Test with malfordmed/empty data
             */
            $this->resetInstance();
            $data['POSKey'] = 'poskey_that_is_not_existant';
            $response = $this->instance->start_barion_payment( $data );
            $this->assertSame( $response['response']['code'], \Nagyapartman_Barion\Nagyapartman_Barion::HTML_BAD_REQUEST );
            $this->assertSame( $response['response']['message'], 'Bad Request' );
            $data['POSKey'] = 'c9a000420f7e45b1bfaf9859b77757d2';  // Reset.

            /*
             * Test with malfordmed/empty data #2
             */
            $this->resetInstance();
            unset( $data['RedirectUrl'] );
            $response = $this->instance->start_barion_payment( $data );
            $this->assertSame( $response['response']['code'], \Nagyapartman_Barion\Nagyapartman_Barion::HTML_BAD_REQUEST );
            $this->assertSame( $response['response']['message'], 'Bad Request' );
            $data['RedirectUrl'] = 'http://localhost/wordpress/wordpress-nagyapartman/';  // Reset.
        }

        /*
         * Test with empty input.
         */
        $this->resetInstance();
        $data = array();
        $response = $this->instance->start_barion_payment( $data );
        $this->assertFalse( $response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::RESP__START_OF_PAYMENT_FAIL_MPTY_DAT );
    }

    /* #6 */
    /*
     * Basic structure for the test:
     * {
     *   "POSKey": "630ee026-3e19-469f-8325-afc9bd1ae6a6",
     *   "PaymentType": "Immediate",
     *   "PaymentRequestId": "EXMPLSHOP-PM-001",
     *   "FundingSources": ["All"],
     *   "Currency": "EUR",
     *   "Transactions": [
     *       {
     *           "POSTransactionId": "EXMPLSHOP-PM-001/TR001",
     *           "Payee": "webshop@example.com",
     *           "Total": 37.2,
     *           "Comment": "A brief description of the transaction",
     *           "Items": [
     *               {
     *                   "Name": "iPhone 7 smart case",
     *                   "Description": "Durable elegant phone case / matte black",
     *                   "Quantity": 1,
     *                   "Unit": "piece",
     *                   "UnitPrice": 25.2,
     *                   "ItemTotal": 25.2,
     *                   "SKU": "EXMPLSHOP/SKU/PHC-01"
     *               },
     *               {
     *                   "Name": "Fast delivery",
     *                   "Description": "Next day delivery",
     *                   "Quantity": 1,
     *                   "Unit": "piece",
     *                   "UnitPrice": 12,
     *                   "ItemTotal": 12,
     *                   "SKU": "EXMPLSHOP/SKU/PHC-01"
     *               }
     *           ]
     *       }
     *   ]
     * }
     *
     */
    public function test__collect_data_for_transaction() {
        $this->resetInstance();
        /*
         * Minimal datas to compare with the final return data of this method.
         */
        $item1 = array(
            'Name'=> 'iPhone 7 smart case',
            'Description'=> 'Durable elegant phone case / matte black',
            'Quantity'=> 1,
            'Unit'=> 'piece',
            'UnitPrice'=> 25.2,
            'ItemTotal'=> 25.2,
            'SKU'=> 'EXMPLSHOP/SKU/PHC-01'
        );
        $item2 = array(
            'Name'=> 'Fast delivery',
            'Description'=> 'Next day delivery',
            'Quantity'=> 1,
            'Unit'=> 'piece',
            'UnitPrice'=> 12,
            'ItemTotal'=> 12,
            'SKU'=> 'EXMPLSHOP/SKU/PHC-01'
        );
        $transactions = array(
            array(
                'POSTransactionId' => 'EXMPLSHOP-PM-001/TR001',
                'Payee'            => 'webshop@example.com',
                'Total'            => 37.2,
                'Comment'          => 'A brief description of the transaction',
                'Items'            => array(
                    $item1,
                    $item2
                )
            )
        );

        /*
         * ~Datas to compare ... END
         */
        $localities = array( 'Locale' => 'fr-FR', 'Currency' => 'EUR' );
        $purchase_info = array();      /* Optional */
        $urls = array();               /* Required but has default value. */
        $email_addresses = array();    /* Optional */
        $shipping_address = array();   /* Optional */
        $phone_numbers = array();      /* Optional */
        $payer_account_info = array(); /* Optional */

        $recurrence_value = Nagyapartman_Barion\Nagyapartman_Barion::ONE_CLICK_PAYMENT_C;  /* Optional */
        $order_number = '';                                                                /* Optional */
        $challenge_pref  = Nagyapartman_Barion\Nagyapartman_Barion::NO_CHALLENGE_NEEDED_C; /* Optional */
        $ch_name = '';                 /* Optional */

        $returnd_data = $this->instance->collect_data_for_transaction( $transactions,
                                                                       $localities,
                                                                       $purchase_info,
                                                                       $urls,
                                                                       $email_addresses,
                                                                       $shipping_address,
                                                                       $phone_numbers,
                                                                       $payer_account_info,
                                                                       $recurrence_value,
                                                                       $order_number,
                                                                       $challenge_pref,
                                                                       $ch_name );
        $assembled_data = array_merge(
            array(
                'POSKey'          => 'c9a000420f7e45b1bfaf9859b77757d2', /* niquist test POSkey */
                'PaymentType'     => 'Immediate',
                'PaymentWindow'   => '0.00:30:00',
                'PaymentRequestId'=> $this->instance->get_payment_request_id(),  /* small cheat */
                'FundingSources'  => array( 'All' ),
                'Transactions'    => $transactions,
                'RedirectUrl' => 'http://example.org',
                'CallbackUrl' => 'http://example.org/check_payment/',
                'ChallengePreference' => 20
            ),
            $localities
        );
        //$this->wLog( 'returned data:'.print_r( $returnd_data, true )."\n---------------\nassembled_data:".print_r( $assembled_data, true ) );
        $assembled_data['Transactions'][0]['POSTransactionId'] = $returnd_data['Transactions'][0]['POSTransactionId'];
        $this->assertTrue( $this->arrays_are_similar( $assembled_data, $returnd_data, __FUNCTION__ ) );
    }

    /* #7 */
    public function test__generate_recurrence_id() {
        $this->resetInstance();
        $prefix = 'N.apartman_pf.recurr._';
        for ( $i = 0; $i < 5; $i++ ) {
            $string = $this->instance->generate_recurrence_id();
            //echo "\n".$string;
            $this->assertSame( substr( $string, 0, strlen($prefix) ) === $prefix, true );
            $this->assertSame( strlen( $string ), Nagyapartman_Barion\Nagyapartman_Barion::MAX_LEN_OF_RECURRENCE_ID_C );
        }
    }

    /* #8 */
    public function test__generate_payment_id() {
        $this->resetInstance();
        for ( $i = 0; $i < 5; $i++ ) {
            $string = $this->instance->generate_payment_id();
            //echo "\n".$string;
            $this->assertSame( strlen( $string ), Nagyapartman_Barion\Nagyapartman_Barion::MAX_LEN_OF_PAYMENT_ID_C );
        }
    }

    /* #9 Private method call! */
    public function test__generate_id() {
        $this->resetInstance();

        $len = 30;
        $prefix = 'prefix_1';
        $string = $this->invokeMethod( $this->instance, 'generate_id', array( $prefix, $len ) );
        $this->assertSame( substr( $string, 0, strlen($prefix) ) === $prefix, true );

        $len = 10;
        $prefix = 'too_long_prefix_1';
        $string = $this->invokeMethod( $this->instance, 'generate_id', array( $prefix, $len ) );
        $this->assertEquals( $string, '' );
    }

    /* #10 */
    public function test__sanitize_payer_email() {
        $this->resetInstance();

        $too_long_string = 'lkjklajfalsjfésjfajfakjflkéajflkajkajflasjklasjalksjasjlasjléfjasléf'.
            'lajkfkljasfaléjalsjfalksjfljsjfasjldjaksjflajslfjlkasjflkjaslkjfaklsjdklajlkdjaflksjflajf'.
            'lkjasdflkjasflasjfjaskfjlsjfalsjflsajlksjakfjlskjfklsajklfjslkajfjlaskjdfalsjkdfjaslfl'.
            'lakjsdflkajsflkasjflkajalsdjfalskjfalksjflasjkfdkljsfjaioawuoajdlkajoifjsalk';
        $this->assertEquals( 'payer@pay.hu', $this->instance->sanitize_payer_email( ['Payer' => 'payer@pay.hu', 'Secondary' => 'admin@gmail.com'] ) );
        $this->assertEquals( 'admin@gmail.com', $this->instance->sanitize_payer_email( ['Payer' => 'payerpay.hu', 'Secondary' => 'admin@gmail.com'] ) );
        $this->assertEquals( 'admin@gmail.com', $this->instance->sanitize_payer_email( ['Payer' => 'payer@payhu', 'Secondary' => 'admin@gmail.com'] ) );
        $this->assertEquals( 'admin@gmail.com', $this->instance->sanitize_payer_email( ['Payer' => $too_long_string, 'Secondary' => 'admin@gmail.com'] ) );
        $this->assertEquals( '', $this->instance->sanitize_payer_email( ['Payer' => '', 'Secondary' => 'admin@gmailcom'] ) );
        $this->assertEquals( '', $this->instance->sanitize_payer_email( ['Payer' => '', 'Secondary' => 'admingmail.com'] ) );
        $this->assertEquals( '', $this->instance->sanitize_payer_email( ['Payer' => '', 'Secondary' => $too_long_string] ) );
        $this->assertEquals( '', $this->instance->sanitize_payer_email( ['Payer' => '', 'Secondary' => ''] ) );
        $this->assertEquals( '', $this->instance->sanitize_payer_email( [] ) );
    }

    /* #11 */
    public function test__sanitize_string() {
        $this->resetInstance();

        /*
         * Test with empty input.
         */
        $string = $this->instance->sanitize_string( 'Param #1', /* param_name hint */
                                                    '',         /* param */
                                                    'FAIL',     /* substitute_value */
                                                    10,         /* maxlen */
                                                    0 );        /* minlen = 0 */
        $this->assertSame( 'FAIL', $string );

        /*
         * Test with too low maxlen
         */
        $string = $this->instance->sanitize_string( 'Param #1', /* param_name hint */
                                                    'param_1',  /* param */
                                                    'FAIL',     /* substitute_value */
                                                    2,          /* maxlen */
                                                    0 );        /* minlen = 0 */
        $this->assertSame( 'FAIL', $string );

        /*
         * Test with too high minlen
         */
        $string = $this->instance->sanitize_string( 'Param #1', /* param_name hint */
                                                    'param_1',  /* param */
                                                    'FAIL',     /* substitute_value */
                                                    10,         /* maxlen */
                                                    8 );        /* minlen = 0 */
        $this->assertSame( 'FAIL', $string );

        /*
         * Test OK
         */
        $string = $this->instance->sanitize_string( 'Param #1', /* param_name hint */
                                                    'param_1',  /* param */
                                                    'FAIL',     /* substitute_value */
                                                    15,         /* maxlen */
                                                    0 );        /* minlen = 0 */
        $this->assertSame( 'param_1', $string );
    }

    /* #12 */
    public function test__extract_payment_transactions()
    {
        /*
         * Test data
         */
        $item1 = array(
            'Name' => 'iPhone 7 smart case',
            'Description' => 'Durable elegant phone case / matte black',
            'Quantity' => 1,
            'Unit' => 'piece',
            'UnitPrice' => 25.2,
            'ItemTotal' => 25.2,
            'SKU' => 'EXMPLSHOP/SKU/PHC-01',
        );
        $item2 = array(
            'Name' => 'Samsung Galaxy S6',
            'Description' => 'New SmartPhone',
            'Quantity' => 2,
            'Unit' => 'piece',
            'UnitPrice' => 35.2,
            'ItemTotal' => 55.2,
            'SKU' => 'EXMPLSHOP/SKU/PHC-04',
        );
        $transaction1 = array(
            'POSTransactionId' => 'EXMPLSHOP-PM-001/TR001',
            'Payee' => 'hello@nagyapartmansiofok.hu',
            'Total' => 37,
            'Comment' => 'A brief description of the transaction',
            'Items' => array(
                $item1,
                $item2
            )
        );
        $transaction2 = array(
            'POSTransactionId' => 'EXMPLSHOP-PM-001/TR003',
            'Payee' => 'hello@nagyapartmansiofok.hu',
            'Total' => 37,
            'Comment' => 'A brief description of the transaction',
            'Items' => array(
                $item1,
                $item2
            )
        );
        $transactions = array(
            $transaction1,
            $transaction2
        );

        /*
         * OK Scenario.
         */
        $this->resetInstance();
        $result = $this->instance->extract_payment_transactions( $transactions, \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_HUF_C );
        $transactions[0]['POSTransactionId'] = $result[0]['POSTransactionId'];  /* small cheat */
        $transactions[1]['POSTransactionId'] = $result[1]['POSTransactionId'];  /* small cheat */
        $this->assertTrue( $this->arrays_are_similar( $transactions, $result, __FUNCTION__ ) );

        /*
         * Empty input data.
         */
        $this->resetInstance();
        $result = $this->instance->extract_payment_transactions( [], \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_HUF_C );
        $this->assertTrue( $this->arrays_are_similar( [], $result, __FUNCTION__ ) );
        $this->assertTrue( $this->instance->is_any_error() );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::ERROR__FAILED_TO_PACK_TRANSACTIONS );
        //echo print_r( $this->instance->get_errors(), true );

        /*
         * Experimental from live prod.
         * Caused infinite loop.
         */
        $transaction3 = array(
            'Payee' => 'hello@nagyapartmansiofok.hu',
            'Total' => 37,
            'Comment' => 'A brief description of the transaction',
            'Items' => Array(
                'Unit' => 'Darab',
                'Quantity' => 1,
                'ItemTotal' => '',
                'UnitPrice' => '',
            )
        );
        $this->resetInstance();
        $result = $this->instance->extract_payment_transactions( $transaction3, \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_HUF_C );
    }

    /* #13 */
    public function test__get_total_regarding_currency() {
        $this->resetInstance();

        $curr = $this->instance->get_total_regarding_currency( '100.21', /* value */
                                                               'HUF'     /* currency */ );
        $this->assertSame( $curr, '100' );

        $curr = $this->instance->get_total_regarding_currency( '50.61', /* value */
                                                               'HUF'    /* currency */ );
        $this->assertSame( $curr, '51' );

        $curr = $this->instance->get_total_regarding_currency( '100.233', /* value */
                                                               'CZK'      /* currency */ );
        $this->assertSame( $curr, '100.23' );

        $curr = $this->instance->get_total_regarding_currency( '150.203', /* value */
                                                               'EUR'      /* currency */ );
        $this->assertSame( $curr, '150.2' );

        $curr = $this->instance->get_total_regarding_currency( '30.8734', /* value */
                                                               'USD'      /* currency */ );
        $this->assertSame( $curr, '30.87' );

        $curr = $this->instance->get_total_regarding_currency( '56.2', /* value */
                                                               'EUR'   /* currency */ );
        $this->assertSame( $curr, '56.2' );

        $curr = $this->instance->get_total_regarding_currency( '56', /* value */
                                                               'EUR' /* currency */ );
        $this->assertSame( $curr, '56' );
    }

    /* #14 */
    public function test__extract_payee_transactions() {
        /*
        $this->resetInstance();
        $this->instance->extract_payee_transactions( [] );
        $this->assertSame( false, true, 'NOT FINISHED'  );
        */
        $this->markTestSkipped('Not implemented in the code.');
    }

    /* #15 */
    public function test__extract_items() {
        $this->resetInstance();

        /*
         * Extract: success base case.
         */
        $item1 = array(
            'Name'=> 'iPhone 7 smart case',
            'Description'=> 'Durable elegant phone case / matte black',
            'Quantity'=> 1,
            'Unit'=> 'piece',
            'ImageUrl' => 'http://www.example.com/image1.jpg',
            'UnitPrice'=> 25.2,
            'ItemTotal'=> 25.2,
            'SKU'=> 'EXMPLSHOP/SKU/PHC-01'
        );

        $item2 = array(
            'Name'=> 'Samsung Galaxy 6S',
            'Description'=> 'Phone',
            'Quantity'=> 3,
            'Unit'=> 'piece',
            'UnitPrice'=> 125.2,
            'ItemTotal'=> 425.2,
            'SKU'=> 'SHOP/SKU/PHC-01'
        );

        $items = array();
        $items[] = $item1;
        $items[] = $item2;

        $extracted_items = $this->instance->extract_items( $items );
        $this->assertTrue( $this->arrays_are_similar( $items, $extracted_items, __FUNCTION__ ) );

        /*
         * Testing the missing elements: *ALL*.
         */
        $items = array( array(), array() );

        $extracted_items = $this->instance->extract_items( $items );
        $items[0] = array(
            'Name'=> 'Name_0',
            'Description'=> 'Description_0',
            'Quantity'=> 0,
            'Unit'=> 'Unit_0',
            'UnitPrice'=> 0,
            'ItemTotal'=> 0
        );
        $items[1] = array(
            'Name'=> 'Name_1',
            'Description'=> 'Description_1',
            'Quantity'=> 0,
            'Unit'=> 'Unit_1',
            'UnitPrice'=> 0,
            'ItemTotal'=> 0
        );
        $this->assertTrue( $this->arrays_are_similar( $items, $extracted_items, __FUNCTION__ ) );
    }

    /* #16 */
    public function test__extract_recurrence() {
        $this->resetInstance();

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::ONE_CLICK_PAYMENT_C;
        $value2 = $this->instance->extract_recurrence( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::MERCHANT_INITIATED_PAYMENT_C;
        $value2 = $this->instance->extract_recurrence( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::RECURRING_PAYMENT_C;
        $value2 = $this->instance->extract_recurrence( $value );
        $this->assertSame( $value, $value2 );

        $value = 35;
        $value2 = $this->instance->extract_recurrence( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::ONE_CLICK_PAYMENT_C, $value2 );

        $value = 546;
        $value2 = $this->instance->extract_recurrence( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::ONE_CLICK_PAYMENT_C, $value2 );

        $value = 6785;
        $value2 = $this->instance->extract_recurrence( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::ONE_CLICK_PAYMENT_C, $value2 );
    }

    /* #17 */
    public function test__extract_address() {
        $this->resetInstance();
        /* Both shipping-address and billing-address. */
        $addresses = array(
            'ShippingAddress' => array(
                'Country'  => 'HU',
                'City'     => 'Budapest',
                'Region'   => 'BU',
                'Zip'      => '1173',
                'Street'   => 'Kaszáló',
                'Street2'   => '97',
                'FullName' => 'Molnár Béla'
            ),
            'BillingAddress' => array(
                'Country'  => 'HU',
                'City'     => 'Budapest',
                'Region'   => 'BU',
                'Zip'      => '1173',
                'Street'   => 'Kaszáló',
                'Street2'   => '97'
            )
        );
        $sh_addr = $this->instance->extract_address( $addresses, 'ShippingAddress' );
        $this->assertTrue( $this->arrays_are_similar( $addresses['ShippingAddress'], $sh_addr, __FUNCTION__ ) );

        $bl_addr = $this->instance->extract_address( $addresses, 'BillingAddress' );
        $this->assertTrue( $this->arrays_are_similar( $addresses['BillingAddress'], $bl_addr, __FUNCTION__ ) );

        /* Test with partial data. (filter_array test) */
        $addresses['BillingAddress']['Country'] = '';
        $addresses['BillingAddress']['Zip'] = '';
        $addresses['BillingAddress']['Street'] = '';
        $bl_addr2 = $this->instance->extract_address( $addresses, 'BillingAddress' );
        $addresses['BillingAddress']['Country'] = 'ZZ';  // Default
        unset( $addresses['BillingAddress']['Zip'] );
        unset( $addresses['BillingAddress']['Street'] );
        $this->assertTrue( $this->arrays_are_similar( $addresses['BillingAddress'], $bl_addr2, __FUNCTION__ ) );

        $addresses['ShippingAddress']['Country'] = '';
        $addresses['ShippingAddress']['Zip'] = '';
        $addresses['ShippingAddress']['FullName'] = '';
        $sh_addr2 = $this->instance->extract_address( $addresses, 'ShippingAddress' );
        $addresses['ShippingAddress']['Country'] = 'ZZ';  // Default
        unset( $addresses['ShippingAddress']['Zip'] );
        unset( $addresses['ShippingAddress']['FullName'] );
        $this->assertTrue( $this->arrays_are_similar( $addresses['ShippingAddress'], $sh_addr2, __FUNCTION__ ) );
    }

    /* #18 */
    public function test__extract_locale() {
        $this->resetInstance();

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_CZ_C;
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_DE_C;
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_EN_C;
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_ES_C;
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_FR_C;
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_HU_C;
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_SK_C;
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_SI_C;
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( $value, $value2 );

        $value = 35;
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_HU_C, $value2 );

        $value = '';
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_HU_C, $value2 );

        $value = 'saÉLKE-lkék';
        $value2 = $this->instance->extract_locale( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::LOCALE_HU_C, $value2 );
    }

    /* #19 */
    public function test__extract_currency() {
        $this->resetInstance();

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_CZK_C;
        $value2 = $this->instance->extract_currency( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_EUR_C;
        $value2 = $this->instance->extract_currency( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_USD_C;
        $value2 = $this->instance->extract_currency( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_HUF_C;
        $value2 = $this->instance->extract_currency( $value );
        $this->assertSame( $value, $value2 );

        $value2 = $this->instance->extract_currency( 'GER' );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_HUF_C, $value2 );

        $value2 = $this->instance->extract_currency( 'ljafd' );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_HUF_C, $value2 );

        $value2 = $this->instance->extract_currency( 'swe5sg' );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_HUF_C, $value2 );

        $value2 = $this->instance->extract_currency( 'QWERTY' );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::CURRENCY_OF_HUF_C, $value2 );
    }

    /* #20 */
    public function test__extract_phone_number() {
        $this->resetInstance();
        $p_num1 = '36703453445';
        $phone_number_array = ['key1' => $p_num1 ];
        $phone_num = $this->instance->extract_phone_number( $phone_number_array, 'key1' );
        $this->assertSame( $phone_num, $p_num1 );

        $p_num1 = '3670dsfw234';
        $phone_number_array = ['key1' => $p_num1 ];
        $phone_num = $this->instance->extract_phone_number( $phone_number_array, 'key1' );
        $this->assertSame( $phone_num, '3670' );

        $p_num1 = 'no_phone_number';
        $phone_number_array = ['key1' => $p_num1 ];
        $phone_num = $this->instance->extract_phone_number( $phone_number_array, 'key1' );
        $this->assertFalse( $phone_num );
    }

    /* #21 */
    public function test__extract_payer_account_information() {
        /*
        $this->resetInstance();
        $this->instance->extract_payer_account_information( [] );
        $this->assertSame( false, true, 'NOT FINISHED'  );
        */
        $this->markTestSkipped('Not implemented in the code.');
    }

    /* #22 */
    public function test__extract_purchase_information() {
        /*
        $this->resetInstance();
        $this->instance->extract_purchase_information( [] );
        $this->assertSame( false, true, 'NOT FINISHED'  );
        */
        $this->markTestSkipped('Not implemented in the code.');
    }

    /* #23 */
    public function test__sanitize_challenge_preference() {
        $this->resetInstance();

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::NO_PREFERENCE_C;
        $value2 = $this->instance->sanitize_challenge_preference( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::CHALLENGE_REQUIRED_C;
        $value2 = $this->instance->sanitize_challenge_preference( $value );
        $this->assertSame( $value, $value2 );

        $value = \Nagyapartman_Barion\Nagyapartman_Barion::NO_CHALLENGE_NEEDED_C;
        $value2 = $this->instance->sanitize_challenge_preference( $value );
        $this->assertSame( $value, $value2 );

        $value = 11;
        $value2 = $this->instance->sanitize_challenge_preference( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::NO_CHALLENGE_NEEDED_C, $value2 );

        $value = 50;
        $value2 = $this->instance->sanitize_challenge_preference( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::NO_CHALLENGE_NEEDED_C, $value2 );

        $value = 140;
        $value2 = $this->instance->sanitize_challenge_preference( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::NO_CHALLENGE_NEEDED_C, $value2 );

        $value = -30;
        $value2 = $this->instance->sanitize_challenge_preference( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::NO_CHALLENGE_NEEDED_C, $value2 );

        $value = 12314;
        $value2 = $this->instance->sanitize_challenge_preference( $value );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::NO_CHALLENGE_NEEDED_C, $value2 );
    }

    /* #24 */
    public function test__check_email() {
        $this->resetInstance();
        $fname = 'check_email';

        $email = 'an_email_addr@gmail.com';
        $len = 200;
        $res = $this->invokeMethod( $this->instance, $fname, array( $email, $len ) );
        $this->assertSame( true, $res );

        $email = 'an_email_addr@gmail.com';
        $len = 5;
        $res = $this->invokeMethod( $this->instance, $fname, array( $email, $len ) );
        $this->assertSame( false, $res );

        $email = 'an_email_addrgmail.com';
        $len = 100;
        $res = $this->invokeMethod( $this->instance, $fname, array( $email, $len ) );
        $this->assertSame( false, $res );

        $email = 'an_email_addr@gmail_com';
        $len = 100;
        $res = $this->invokeMethod( $this->instance, $fname, array( $email, $len ) );
        $this->assertSame( false, $res );
    }

    /* #25 */
    public function test__wr_collect_data_for_transaction() {
        $this->resetInstance();

        /*
         * Test data assembled
         */
        $item1 = array(
            'Name'=> 'iPhone 7 smart case',
            'Description'=> 'Durable elegant phone case / matte black',
            'Quantity'=> 1,
            'Unit'=> 'piece',
            'UnitPrice'=> 25,
            'ItemTotal'=> 125,
            'SKU'=> 'EXMPLSHOP/SKU/PHC-01'
        );

        $item2 = array(
            'Name'=> 'iPhone 5',
            'Description'=> 'Elegant phone case',
            'Quantity'=> 3,
            'Unit'=> 'piece',
            'UnitPrice'=> 23,
            'ItemTotal'=> 35,
            'SKU'=> 'SHOP/SKU/PHC-01'
        );

        $transactions = array(
            array(
                'POSTransactionId' => 'EXMPLSHOP-PM-001/TR001',
                'Payee'            => 'webshop@example.com',
                'Total'            => 37,
                'Comment'          => 'A brief description of the transaction',
                'Items'            => array(
                    $item1,
                    $item2
                )
            )
        );
        $localities = array( 'Locale' => 'hu-HU', 'Currency' => 'HUF' );

        /* Check data for comparision. */
        $assembled_data = array_merge(
            array(
                'POSKey'          => 'c9a000420f7e45b1bfaf9859b77757d2', /* niquist test POSkey */
                'PaymentType'     => 'Immediate',
                'PaymentWindow'   => '0.00:30:00',
                'PaymentRequestId'=> '',
                'FundingSources'  => array( 'All' ),
                'Transactions'    => $transactions,
                'RedirectUrl' => 'http://example.org',      /* site_url() returned */
                'CallbackUrl' => 'http://example.org/check_payment/',
                'ChallengePreference' => 20
            ),
            $localities
        );

        /*
         * Test with minimal data.
         */
        $data = $this->instance->wr_collect_data_for_transaction( $transactions, $localities );
        $assembled_data['PaymentRequestId'] = $this->instance->get_payment_request_id();  /* small cheat */
        $assembled_data['Transactions'][0]['POSTransactionId'] = $data['Transactions'][0]['POSTransactionId'];  /* small cheat */
        $this->assertTrue( $this->arrays_are_similar( $assembled_data, $data, __FUNCTION__ ) );

        /* PurchaseInformation key in other data not implemented, skip. */
        /* PayerAccount not implemented, skip */
        /* RecurrenceType not implemented, because InitiateRecurrence
         * is false by default, and this depends on that. */

        /* RedirectUrl, CallbackUrl */
        $other_data = array(
            'CallbackUrl' => 'www.this.is.the.callback.url',
            'RedirectUrl' => 'www.this.is.the.redirect.url'
        );
        $data = $this->instance->wr_collect_data_for_transaction( $transactions, $localities, $other_data );
        $assembled_data['PaymentRequestId'] = $this->instance->get_payment_request_id();  /* small cheat */
        $assembled_data['Transactions'][0]['POSTransactionId'] = $data['Transactions'][0]['POSTransactionId'];  /* small cheat */
        $this->assertTrue( $this->arrays_are_similar( array_merge( $assembled_data, $other_data ), $data, __FUNCTION__ ) );

        /* PayerHint, ShippingAddress, BillingAddress */
        $other_data = array(
            'PayerHint' => array(
                'Payer' => 'the_payer@onlinepayer.com',
                'Secondary' => 'sec_payer@onlinepayer.com'
            ),
            'ShippingAddress' => array(
                'Country'  => 'HU',
                'City'     => 'Budapest',
                'Region'   => 'BU',
                'Zip'      => '1173',
                'Street'   => 'Pesti út',
                'Street2'   => '97',
                'FullName' => 'Molnár Béla Géza'
            ),
            'BillingAddress' => array(
                'Country'  => 'HU',
                'City'     => 'Budapest',
                'Region'   => 'BU',
                'Zip'      => '1171',
                'Street'   => 'Kaszáló',
                'Street2'   => '99'
            )
        );
        $data = $this->instance->wr_collect_data_for_transaction( $transactions, $localities, $other_data );
        $assembled_data['PaymentRequestId'] = $this->instance->get_payment_request_id();  /* small cheat */
        $assembled_data['Transactions'][0]['POSTransactionId'] = $data['Transactions'][0]['POSTransactionId'];  /* small cheat */
        $assembled_data2 = array_merge( $assembled_data, $other_data );
        $assembled_data2['PayerHint'] = $other_data['PayerHint']['Payer'];
        $this->assertTrue( $this->arrays_are_similar( $assembled_data2, $data, __FUNCTION__ ) );

        /* PayerPhoneNumber, PayerWorkPhoneNumber */
        $other_data = array(
            'PayerPhoneNumber' => '555123',
            'PayerWorkPhoneNumber' => '223442'
        );
        $data = $this->instance->wr_collect_data_for_transaction( $transactions, $localities, $other_data );
        $assembled_data['PaymentRequestId'] = $this->instance->get_payment_request_id();  /* small cheat */
        $assembled_data['Transactions'][0]['POSTransactionId'] = $data['Transactions'][0]['POSTransactionId'];  /* small cheat */
        $assembled_data3 = array_merge( $assembled_data, $other_data );
        $this->assertTrue( $this->arrays_are_similar( $assembled_data3, $data, __FUNCTION__ ) );

        /* OrderNumber, ChallengePreference, CardHolderNameHint */
        $other_data = array(
            'OrderNumber' => 'Order_1-2-3',
            'ChallengePreference' => \Nagyapartman_Barion\Nagyapartman_Barion::CHALLENGE_REQUIRED_C,
            'CardHolderNameHint' => 'CardHolderNameHintString'
        );
        $data = $this->instance->wr_collect_data_for_transaction( $transactions, $localities, $other_data );
        $assembled_data['PaymentRequestId'] = $this->instance->get_payment_request_id();  /* small cheat */
        $assembled_data['Transactions'][0]['POSTransactionId'] = $data['Transactions'][0]['POSTransactionId'];  /* small cheat */
        $assembled_data4 = array_merge( $assembled_data, $other_data );
        $this->assertTrue( $this->arrays_are_similar( $assembled_data4, $data, __FUNCTION__ ) );
    }

    /* #26 */
    public function test__process_response() {
        $this->resetInstance();

        /*
         * Test data
         * IMPORTANT: Keep {transactions} and {input} in sync!!
         */
        $payment_id = '6247e96f9b4aeb118bc4001dd8b71cc4';
        $transactions = array(
            'PaymentId' => $payment_id,
            'PaymentRequestId' => 'N.apartman_pf.paymnt._iUOlgn2AaNCc8dwDv43sh0NMeHyffoZktvpSJ0j95E66F9YXAw1gLbTsVzE1qjI8iG7uyVKIL3kRBz',
            'Status' => 'Prepared',
            'QRUrl' => 'https://api.test.barion.com/qr/generate?paymentId=6247e96f-9b4a-eb11-8bc4-001dd8b71cc4&size=Large',
            'Transactions' => Array(
                '0' => Array(
                        'POSTransactionId' => 'EXMPLSHOP-PM-001/TR001',
                        'TransactionId' => '6347e96f9b4aeb118bc4001dd8b71cc4',
                        'Status' => 'Prepared',
                        'Currency' => 'HUF',
                        'TransactionTime' => '2020-12-30T12:35:03.283',
                        'RelatedId' => '',
                    )
            ),
            'GatewayUrl' => 'https://secure.test.barion.com/Pay?Id='.$payment_id.'&lang=hu_HU',
            'RedirectUrl' => 'http://localhost/wordpress/wordpress-nagyapartman?paymentId='.$payment_id.'\'',
            'CallbackUrl' => 'http://localhost/wordpress/wordpress-nagyapartman?paymentId='.$payment_id.'\'',
            'Errors' => Array()
        );

        $input = array(
            /* Body is json-encoded! */
            'body' => '{"PaymentId":"'.$payment_id.'",
                       "PaymentRequestId":"N.apartman_pf.paymnt._iUOlgn2AaNCc8dwDv43sh0NMeHyffoZktvpSJ0j95E66F9YXAw1gLbTsVzE1qjI8iG7uyVKIL3kRBz",
                       "Status":"Prepared",
                       "QRUrl":"https://api.test.barion.com/qr/generate?paymentId=6247e96f-9b4a-eb11-8bc4-001dd8b71cc4&size=Large",
                       "Transactions":[{"POSTransactionId":"EXMPLSHOP-PM-001/TR001",
                       "TransactionId":"6347e96f9b4aeb118bc4001dd8b71cc4",
                       "Status":"Prepared",
                       "Currency":"HUF",
                       "TransactionTime":"2020-12-30T12:35:03.283",
                       "RelatedId":null}],
                       "GatewayUrl":"https://secure.test.barion.com/Pay?Id='.$payment_id.'&lang=hu_HU",
                       "RedirectUrl":"http://localhost/wordpress/wordpress-nagyapartman?paymentId='.$payment_id.'",
                       "CallbackUrl":"http://localhost/wordpress/wordpress-nagyapartman?paymentId='.$payment_id.'",
                       "Errors":[]}',
            'response' => array(
                'code' => 200,
                'message' => 'OK'
            )
        );

        /*
         * Basic OK scenario.
         */
        $input['body'] = json_decode( $input['body'], true );
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertTrue( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::RESP__NO_ERRORS );
        $this->assertSame( $this->instance->get_payment_id(), $payment_id );
        $this->assertSame( $this->instance->get_gateway_url(), 'https://secure.test.barion.com/Pay?Id='.$payment_id.'&lang=hu_HU' );
        $this->assertSame( $this->instance->get_payment_status(), 'Prepared' );
        $this->assertTrue( $this->arrays_are_similar( $transactions['Transactions'][0], $this->instance->get_transactions()[0], __FUNCTION__ ) );

        /*
         * Empty {response.code}
         */
        $this->resetInstance();
        unset( $input['response']['code'] );
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertFalse( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::RESP__NO_STATUS_CODE );
        $input['response']['code'] = '200';   // Reset

        /*
         * Empty {body.Errors}
         */
        $this->resetInstance();
        unset( $input['body']['Errors'] );
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertFalse( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::RESP__NO_ERROR_CODE );
        $input['body']['Errors'] = array();   // Reset

        /*
         * Errors present
         */
        $this->resetInstance();
        $errors1 = array(
            "Title" => "Invalid user",
            "Description" => "Invalid user(customer@test.com)!",
            "ErrorCode" => "InvalidUser",
            "HappenedAt" => "2020-12-10T20 => 14 => 31.2353001Z",
            "AuthData" => "hello@nagyapartmansiofok.hu",
            "EndPoint" => "https => //api.test.barion.com/v2/Payment/Start"
        );
        $input['body']['Errors'] = $errors1; // Add errors
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertFalse( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertTrue( $this->arrays_are_similar( $e['errors'][0], $errors1, __FUNCTION__ ) );
        $input['body']['Errors'] = array(); // Reset

        /*
         * Bad HTML response code (400)
         */
        $this->resetInstance();
        $input['response']['code'] = '400';  // Bad request
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertFalse( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::RESP__HTML_NOT_OK );
        $input['response']['code'] = '200'; // Reset

        /*
         * Bad HTML response code (400), and also errors present.
         */
        $this->resetInstance();
        $input['response']['code'] = '400';  // Bad request
        $input['body']['Errors'] = $errors1; // Add errors
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertFalse( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], $errors1 );
        $input['response']['code'] = '200'; // Reset
        $input['body']['Errors'] = array(); // Reset

        /*
         * Empty {body.GatewayUrl}
         */
        $this->resetInstance();
        unset( $input['body']['GatewayUrl'] );
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertFalse( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::RESP__NO_GATEWAY_URL );
        $input['body']['GatewayUrl'] = 'https://secure.test.barion.com/Pay?Id=§'.$payment_id.'&lang=hu_HU';  // Reset

        /*
         * Empty {body.PaymentId}
         */
        $this->resetInstance();
        unset( $input['body']['PaymentId'] );
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertFalse( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::RESP__NO_PAYMENT_ID );
        $input['body']['PaymentId'] = $payment_id;  // Reset

        /*
         * Empty {body.Transactions}
         */
        $this->resetInstance();
        unset( $input['body']['Transactions'] );
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertFalse( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::RESP__NO_TRANSACTIONS );
        $input['body']['Transactions'] = array(
            'POSTransactionId'=>'EXMPLSHOP-PM-001/TR001',
            'TransactionId'=>'056564c49a3ceb118bc4001dd8b71cc4',
            'Status'=>'Prepared',
            'Currency'=>'HUF',
            'TransactionTime'=>'2020-12-12T16=>54=>59.24',
            'RelatedId' => null
        );  // Reset

        /*
         * Empty {body.Status}
         */
        $this->resetInstance();
        unset( $input['body']['Status'] );
        $processed_response = $this->instance->process_response( $input['response'], json_encode($input['body']) );
        $this->assertFalse( $processed_response );
        $e = $this->instance->get_errors();
        $this->assertSame( $e['errors'][0], \Nagyapartman_Barion\Nagyapartman_Barion::RESP__NO_STATUS );
        $input['body']['Status'] = 'Prepared';  // Reset
    }

    /* #27 */
    public function test__extract_errors_from_response() {
        $this->resetInstance();
        $input = array(
            "Title" => "Invalid user",
            "Description" => "Invalid user(customer@test.com)!",
            "ErrorCode" => "InvalidUser",
            "HappenedAt" => "2020-12-10T20 => 14 => 31.2353001Z",
            "AuthData" => "hello@nagyapartmansiofok.hu",
            "EndPoint" => "https://api.test.barion.com/v2/Payment/Start"
        );
        $output = $this->instance->extract_errors_from_response( $input );
        $this->assertTrue( $this->arrays_are_similar( $input, $output, __FUNCTION__ ) );
    }

    /* #28 */
    public function test__register_activation_hook() {
        $this->resetInstance();
        $result = $this->instance->register_activation_hook();
        //$this->assertTrue( $result );
        $this->assertTrue( true );  // TODO
    }

    /* #29 */
    public function test__register_deactivation_hook() {
        $this->resetInstance();
        $this->instance->register_deactivation_hook();
        $this->assertTrue( true );  // TODO
    }

    /* #30 */
    public function test__register_uninstall_hook() {
        $this->resetInstance();
        $this->instance->register_uninstall_hook();
        $this->assertTrue( true );  // TODO
    }

    /* #31 */
    public function test__extract_key_from_array() {
        $this->resetInstance();

        $input = array(
            'code' => 400,
            'message' => 'Bad Request'
        );

        $output = '';
        $ret_val = $this->instance->extract_key_from_array( 'code', $input, $output );
        $this->assertSame( 400, $output );
        $this->assertTrue( $ret_val );

        $output = '';
        $ret_val = $this->instance->extract_key_from_array( 'not_exist', $input, $output );
        $this->assertSame( '', $output );
        $this->assertFalse( $ret_val );

        $output = '';
        $ret_val = $this->instance->extract_key_from_array( 0, $input, $output );
        $this->assertSame( '', $output );
        $this->assertFalse( $ret_val );
    }

    /* #32 */
    public function test__get_payment_id() {
        $this->resetInstance();
        $this->assertTrue( empty( $this->instance->get_payment_id() ) );
    }

    /* #33 */
    public function test__get_gateway_url() {
        $this->resetInstance();
        $this->assertTrue( empty( $this->instance->get_gateway_url() ) );
    }

    /* #34 */
    public function test__get_transactions() {
        $this->resetInstance();
        $this->assertTrue( empty( $this->instance->get_transactions() ) );
    }

    /* #35 */
    public function test__get_payment_status() {
        $this->resetInstance();
        $this->assertTrue( empty( $this->instance->get_payment_status() ) );
    }

    /* #36 */
    public function test__get_tbl_name() {
        $this->resetInstance();
        $this->assertSame( $this->instance->get_tbl_name(), 'barion_transactions' );
    }

    /* #37 */
    public function test__get_payment_data_from_db() {
        $this->resetInstance();

        /* input */
        $latest_inserted_id_for_user_1 = 'paymentId_2234201219';
        $date_for_user_3 = '2020-12-19 09:00:00';
        $status_for_user_2 = 'Status_Failed';

        /* Test inserts */
        $this->instance->save_payment_data_in_db( 1, 'paymentId_22342989889', 'Status_NOK', '2020-12-19 01:32:00', 'payment_request_id_1' );
        $this->instance->save_payment_data_in_db( 1, 'paymentId_22233334444', 'Status_NOK', '2020-12-19 03:55:00', 'payment_request_id_2' );
        $this->instance->save_payment_data_in_db( 1, $latest_inserted_id_for_user_1, 'Status_NOK', '2020-12-19 5:01:32', 'payment_request_id_3' );  // Latest
        $this->instance->save_payment_data_in_db( 2, 'paymentId_20wer1219', $status_for_user_2, '2020-12-19 07:00:00', 'payment_request_id_4' );
        $this->instance->save_payment_data_in_db( 3, 'paymentId_2020rztz9', 'Status_Prepared', $date_for_user_3, 'payment_request_id_5' );
        $this->instance->save_payment_data_in_db( 4, 'paymentId_202hdgh219', 'Status_OK', '2020-12-19 11:00:00', 'payment_request_id_6' );

        /* ID with specific date. */
        $val = $this->instance->get_payment_data_from_db( 3 );
        $this->assertSame( $val['created'], $date_for_user_3 );
        $this->assertSame( $val['updated'], $date_for_user_3 );  // Its the same as the 'created' field at this point.

        /* ID with status */
        $val = $this->instance->get_payment_data_from_db( 2 );
        $this->assertSame( $val['status'], $status_for_user_2 );

        /* ID with paymentID */
        $val = $this->instance->get_payment_data_from_db( 1 );
        $this->assertSame( $val['paymentId'], $latest_inserted_id_for_user_1 );

        /* ID that not exits */
        $this->assertFalse( $this->instance->get_payment_data_from_db( 7 ) );

        /* ID that not exits #2 */
        $this->assertFalse( $this->instance->get_payment_data_from_db( 'not_existant_id' ) );
    }

    /* #38 */
    public function test__callback_action() {
        $this->resetInstance();
        $payment_id_test_data = '046564c49a3ceb118bc4001dd8b71cc4';

        /*
         * Simulating an imaginary created paymentId
         * as contacting barion api and saving the paymentId from the response.
         */
        $this->instance->save_payment_data_in_db( get_current_user_id(),
                                                  $payment_id_test_data,
                                                  'Status_PREPARED',
                                                  '2020-12-19 01:32:00' );
        /*
         * Theoretically communicating with barion, paying
         * and receiving the callback-answer from the server.
         * Also updating the status.
         */
        $_POST['PaymentId'] = $payment_id_test_data;
        $_SERVER['REQUEST_URI'].= \Nagyapartman_Barion\Nagyapartman_Barion::CALLBACK_ACTION_URL;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertTrue( $this->instance->callback_action() );

        $val = $this->instance->get_payment_data_from_db_by_pid( $payment_id_test_data );
        $this->assertSame( $val['status'], 'Expired' );
        $this->assertSame( $val['callback_message'], 'Utolsó fizetés lejárt.' );

        unset( $_POST['PaymentId'] );
        $this->assertFalse( $this->instance->callback_action() );
    }

    /* #39 */
    public function test__get_errors() {
        $this->resetInstance();
        $errors = $this->instance->get_errors();
        $compare = array( \Nagyapartman_Barion\Nagyapartman_Barion::OP_MESSAGE => '',
                          \Nagyapartman_Barion\Nagyapartman_Barion::OP_ERROR   => array( \Nagyapartman_Barion\Nagyapartman_Barion::RESP__NO_ERRORS ) );
        $this->assertTrue( $this->arrays_are_similar( $errors, $compare, __FUNCTION__ ) );  /* fail */
    }

    /* #40 */
    public function test__update_payment_status_in_db() {
        $this->resetInstance();
        $this->wLog( __FUNCTION__ .' : start', TEST_DB_FILE );

        /*
         * Setup
         */
        $paymentId = 'test_payment_id_20201223_2133';
        $status_OK = 'status_OK';
        $user_id = 100;
        $user_id_NOK = 99;
        $updated = '2021-01-11 01:11:00';
        $this->instance->save_payment_data_in_db( $user_id,
                                                  $paymentId,
                                                  'Status_PREPARED',
                                                  '2020-12-19 01:32:00' );

        /*
         * Test OK
         */
        $ret_val = $this->instance->update_payment_status_in_db( $user_id,
                                                                 $paymentId,
                                                                 $status_OK,
                                                                 $updated );
        $val = $this->instance->get_payment_data_from_db( $user_id );
        $this->wLog( __FUNCTION__ .' : val='.print_r($val, true), TEST_DB_FILE );
        $this->assertSame( $val['status'], $status_OK );
        $this->assertSame( $val['updated'], $updated );

        /*
         * Test NOK
         */
        $ret_val = $this->instance->update_payment_status_in_db( $user_id_NOK,
                                                                 'paymentID_that_not_exists',
                                                                 $status_OK );
        $this->assertFalse( $ret_val );
        $this->assertFalse( $this->instance->get_payment_data_from_db( $user_id_NOK ) );
    }

    /* #41 */
    public function test__get_payment_state_from_remote() {
        $payment_id = '046564c49a3ceb118bc4001dd8b71cc4';

        /*
         * Basic scenario - OK
         */
        $this->resetInstance();
        $response = $this->instance->get_payment_state_from_remote( $payment_id );  // Status = 'Expired'
        $response = json_decode( $response['body'], true );
        $this->assertSame( $response['Status'], 'Expired' );
    }

    /* #42 */
    public function test__send_to_remote() {
        $this->resetInstance();

        /*
         * Send a simple 'GET' request
         */
        $json_data = array(
            'key_1' => 'value_1',
            'key_2' => 'value_2'
        );
        $response = $this->instance->send_to_remote( \Nagyapartman_Barion\Nagyapartman_Barion::HTTP_METHOD_GET,
                                                     $json_data,
                                                     'http://localhost/wordpress/wordpress-nagyapartman/ajax_req_test.php' );
        $json_data['added_key'] = 'get_test';
        $this->assertSame( $response['body'], json_encode( $json_data ) );

        /*
         * Send a simple 'POST' request
         */
        $this->resetInstance();
        $json_data = array(
            'key_1' => 'value_1',
            'key_2' => 'value_2'
        );
        $response = $this->instance->send_to_remote( \Nagyapartman_Barion\Nagyapartman_Barion::HTTP_METHOD_POST,
                                                     $json_data,
                                                     'http://localhost/wordpress/wordpress-nagyapartman/ajax_req_test.php' );
        $json_data['added_key'] = 'post_test';
        $this->assertSame( $response['body'], json_encode( $json_data ) );

        /*
         * Send a request with unknown method
         */
        $this->resetInstance();
        $json_data = array( 'key_1' => 'value_1' );
        $response = $this->instance->send_to_remote( 'HTTP_UNKNOWN_METHOD',
                                                     $json_data,
                                                     'http://localhost/wordpress/wordpress-nagyapartman/ajax_req_test.php' );
        $this->assertTrue( is_wp_error( $response ) );
        $this->assertSame( $response->get_error_message(), 'Not a valid method=HTTP_UNKNOWN_METHOD' );

        /*
         * Send a request with empty endpoint
         */
        $this->resetInstance();
        $json_data = array( 'key_1' => 'value_1' );
        $response = $this->instance->send_to_remote( 'HTTP_UNKNOWN_METHOD',
                                                     $json_data,
                                                     '' );
        $this->assertTrue( is_wp_error( $response ) );
        $this->assertSame( $response->get_error_message(), 'Empty url address to send data to!' );

        /*
         * Send a request with empty data
         */
        $this->resetInstance();
        $json_data = array( 'key_1' => 'value_1' );
        $response = $this->instance->send_to_remote( 'HTTP_UNKNOWN_METHOD',
                                                     array(),
                                                     'http://localhost/wordpress/wordpress-nagyapartman/ajax_req_test.php' );
        $this->assertTrue( is_wp_error( $response ) );
        $this->assertSame( $response->get_error_message(), 'No data to send!' );
    }

    /* #43 */
    public function test__set_errors() {
        $this->resetInstance();
        $this->instance->set_errors( 'text', 'error_constant' );
        $this->instance->set_errors( 'text2', 'error_constant2' );
        $output = $this->instance->get_errors();
        $compare = array(
            'message' => '|text|text2',
            'errors' => array(
                '0' => 'error_constant',
                '1' => 'error_constant2'
            )
        );
        $this->assertTrue( $this->arrays_are_similar( $output, $compare, __FUNCTION__ ) );
    }

    /* #44 */
    public function test__is_any_error() {
        /*
         * Test true.
         */
        $this->resetInstance();
        $this->instance->set_errors( 'text', 'error_constant' );
        $this->instance->set_errors( 'text2', 'error_constant2' );
        $this->assertTrue( $this->instance->is_any_error() );

        /*
         * Test false
         */
        $this->resetInstance();
        $this->assertFalse( $this->instance->is_any_error() );
    }

    /* #45 */
    public function test__get_payment_request_id() {
        $this->resetInstance();
        $item1 = array(
            'Name'=> 'iPhone 7 smart case',
            'Description'=> 'Durable elegant phone case / matte black',
            'Quantity'=> 1,
            'Unit'=> 'piece',
            'UnitPrice'=> 25.2,
            'ItemTotal'=> 25.2,
            'SKU'=> 'EXMPLSHOP/SKU/PHC-01'
        );
        $item2 = array(
            'Name'=> 'iPhone 5',
            'Description'=> 'Elegant phone case',
            'Quantity'=> 3,
            'Unit'=> 'piece',
            'UnitPrice'=> 23.2,
            'ItemTotal'=> 35.2,
            'SKU'=> 'SHOP/SKU/PHC-01'
        );
        $transactions = array(
            array(
                'POSTransactionId' => 'EXMPLSHOP-PM-001/TR001',
                'Payee'            => 'webshop@example.com',
                'Total'            => 37.2,
                'Comment'          => 'A brief description of the transaction',
                'Items'            => array(
                    $item1,
                    $item2
                )
            )
        );
        $localities = array( 'Currency' => 'fr-FR', 'Locale' => 'EUR' );
        $data = $this->instance->wr_collect_data_for_transaction( $transactions, $localities );
        $payment_request_id = $this->instance->get_payment_request_id();
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::MAX_LEN_OF_PAYMENT_ID_C, strlen( $payment_request_id ) );
    }

    /* #46 */
    public function test__get_payment_data_from_db_by_pid() {
        $this->resetInstance();

        /* input */
        $latest_inserted_id_for_user_1 = 'paymentId_2234201219';
        $date_for_user_3 = '2020-12-19 09:00:00';
        $status_for_user_2 = 'Status_Failed';

        /* Test inserts */
        $this->instance->save_payment_data_in_db( 1, 'paymentId_22342989889', 'Status_NOK', '2020-12-19 01:32:00', 'payment_request_id_1' );
        $this->instance->save_payment_data_in_db( 1, 'paymentId_22233334444', 'Status_NOK', '2020-12-19 03:55:00', 'payment_request_id_2' );
        $this->instance->save_payment_data_in_db( 1, $latest_inserted_id_for_user_1, 'Status_NOK', '2020-12-19 05:01:32', 'payment_request_id_3' );  // Latest
        $this->instance->save_payment_data_in_db( 2, 'paymentId_20wer1219', $status_for_user_2, '2020-12-19 07:00:00', 'payment_request_id_4' );
        $this->instance->save_payment_data_in_db( 3, 'paymentId_2020rztz9', 'Status_Prepared', $date_for_user_3, 'payment_request_id_5' );
        $this->instance->save_payment_data_in_db( 4, 'paymentId_202hdgh219', 'Status_OK', '2020-12-19 11:00:00', 'payment_request_id_6' );

        /* ID with specific date. */
        $val = $this->instance->get_payment_data_from_db_by_pid( $latest_inserted_id_for_user_1 );
        $this->assertSame( $val['created'], '2020-12-19 05:01:32' );
        $this->assertSame( $val['updated'], '2020-12-19 05:01:32' );

        /* ID with status */
        $val = $this->instance->get_payment_data_from_db_by_pid( 'non-existant payment id' );
        $this->assertFalse( $val );
    }

    /* #47 */
    public function test__manage_user_callback_message() {
        $this->resetInstance();
        $payment_id = 'paymentId_22342989889';
        $msg = 'message success';
        $this->instance->save_payment_data_in_db( 1, $payment_id, 'Status_NOK', '2020-12-19 01:32:00', 'payment_request_id_1' );

        /*
         * Add twice
         */
        $ret = $this->instance->manage_user_callback_message( $payment_id, 'message succ.' );
        $this->assertSame( $ret, 1 /* updated rows */ );
        $ret = $this->instance->manage_user_callback_message( $payment_id, $msg );
        $this->assertSame( $ret, 1 );

        /*
         * Retrieve twice (empty on 2nd)
         */
        $ret = $this->instance->manage_user_callback_message( $payment_id );
        $this->assertSame( $ret, $msg );
        $ret = $this->instance->manage_user_callback_message( $payment_id );
        $this->assertFalse( $ret );

        /*
         * Payment_id that not exist.
         */
        $payment_id = 0;
        $msg = 'message success';
        $ret = $this->instance->manage_user_callback_message( $payment_id, $msg );
        $this->assertSame( $ret, false );
    }

    /* #48 */
    public function test__translate_from_status() {
        $this->resetInstance();
        /*
        // All statuses:
        'Prepared';
        'Started';
        'InProgress';
        'Waiting';
        'Reserved';
        'Authorized';
        'Canceled';
        'Succeeded';
        'Failed';
        'PartiallySucceeded';
        'Expired';
        */

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_EXP );
        $this->assertSame( $status, 'Utolsó fizetés lejárt.' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_PREP );
        $this->assertSame( $status, 'Barion fizetés előkészítve' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_STARTED );
        $this->assertSame( $status, 'Barion fizetés indítva' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_IPROGRESS );
        $this->assertSame( $status, 'Barion fizetés folyamatban' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_WAITING );
        $this->assertSame( $status, 'Barion fizetés várakozik' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_RESERVED );
        $this->assertSame( $status, 'Barion fizetés lefoglalva' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_AUTH );
        $this->assertSame( $status, 'Barion fizetés hitelesítve' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_CANCELED );
        $this->assertSame( $status, 'Barion fizetés megszakítva' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_SUCCESS );
        $this->assertSame( $status, 'Barion fizetés sikeres' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_FAILED );
        $this->assertSame( $status, 'Barion fizetés sikertelen' );

        $status = $this->instance->translate_from_status( \Nagyapartman_Barion\Nagyapartman_Barion::STATUS_PSUCC );
        $this->assertSame( $status, 'Barion fizetés részlegesen sikerült' );

        $status = $this->instance->translate_from_status( 'Not existant status' );
        $this->assertSame( $status, 'Nincs értesítő üzenet.(default)' );
    }

    /* #49 */
    public function test__echo_response_and_exit() {
        $this->resetInstance();
        //$this->instance->echo_response_and_exit( 'Success' );
        $this->markTestSkipped('Cannot test this, because of "header" invocation.');
    }

    /* #50 */
    public function test__display_message_to_user() {
        $this->resetInstance();
        //$this->instance->display_message_to_user();
        $this->markTestSkipped('Cannot test this, because of "header" invocation.');
    }

    /* #51 */
    public function test__generate_postransaction_id() {
        $this->resetInstance();
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::MAX_LEN_OF_POSTRANSACTION_ID_C, strlen( $this->instance->generate_postransaction_id() ) );
    }

    /* #52 */
    public function test__do_start_payment() {
        /*
         * Test data for payment.
         */
        $item1 = array(
            'Name'=> 'iPhone 7 smart case',
            'Description'=> 'Durable elegant phone case / matte black',
            'Quantity'=> 1,
            'Unit'=> 'piece',
            'UnitPrice'=> 25.2,
            'ItemTotal'=> 25.2,
            'SKU'=> 'EXMPLSHOP/SKU/PHC-01'
        );
        $transactions = array(
            array(
                'Payee'            => 'hello@nagyapartmansiofok.hu',
                'Total'            => 37.2,
                'Comment'          => 'A brief description of the transaction',
                'Items'            => array(
                    $item1
                )
            )
        );
        $localities = array( 'Currency' => 'fr-FR', 'Locale' => 'EUR' );
        $other_data = array();

        /*
         * No errors
         */
        $this->resetInstance();
        $result = $this->instance->do_start_payment( $transactions, $localities, $other_data );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::RESP__NO_ERRORS, $result['status'] );

        /*
         * Error on starting payment.
         */
        $this->resetInstance();
        unset( $transactions[0]['Payee'] );
        $result = $this->instance->do_start_payment( $transactions, $localities, $other_data );
        $this->assertSame( \Nagyapartman_Barion\Nagyapartman_Barion::FAILED_CANNOT_START_PAYMENT, $result['status'] );
        $transactions[0]['Payee'] = 'hello@nagyapartmansiofok.hu';

        /*
         * Other errors cannot be testes with static data.
         */
    }

    /* #53 */
    public function test__update_user_callback_text() {
        $payment_id = 'abcdef1234';
        $msg = 'hello world';
        $this->resetInstance();
        $ret_val = $this->instance->save_payment_data_in_db( 0 /* userid */,
                                                             $payment_id,
                                                             'Status_OK',
                                                             '2020-12-19 09:06:00',
                                                             'payment_req_id__2' );
                                                             /* callback_message = '-' */
        $result = $this->instance->update_user_callback_text( $payment_id, $msg );
        echo print_r($result, true);
        //$this->assertSame( $ret, $msg );
        $ret = $this->instance->manage_user_callback_message( $payment_id );
        $this->assertSame( $ret, $msg );

        /*
         * Update non-existant paymentId
         */
        $result = $this->instance->update_user_callback_text( 'paymentId_non_existant', $msg );
        echo print_r($result, true);
        //$this->assertSame( $ret, $msg );
    }
}

