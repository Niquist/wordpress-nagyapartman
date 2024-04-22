<?php
/*
Plugin name: Nagyapartman Barion modul
Description: Barion payments for Nagyapartman.hu
Version: v 1.0.
Author: Nagy Gergő & Béla Molnár | niquist.dj@gmail.com, molnar.bela.fenix@gmail.com
Author URI: https://premiumlinkepites.hu/
Licence: Prémiumlinképítés.hu
*/
namespace Nagyapartman_Barion;

use function Nagyapartman_Logger\_log;
use function Nagyapartman_Logger\_error;
use function Nagyapartman_Logger\_vdump;    // For Barion-related response obj.

define( 'WP_BARION_SANDBOX',               true  );  // This should be false in production mode.
define( 'WP_BARION_TESTING_ON_LOCALHOST',  true  );  // This sets different db connection for localhost (required for testing).
define( 'WP_BARION_FAKE_LOCALHOST_SERVER', true  );  // [true]: Mocked localhost 'server' | [false]: barion test api server.
define( 'BARION_RESTRICTED_IPS_ONLY',      true  );  // [true]: Only whitelisted IP addr. can reach form page. | [false]: Open to public.

define( 'BARION_SANDBOX_API_URL',         'https://api.test.barion.com' );
define( 'BARION_LIVE_API_URL',            'https://api.barion.com' );
define( 'LOCALHOST_FAKE_BARION_SERVER',   'http://localhost/wordpress/wordpress-nagyapartman' );


/*
 * From: https://docs.barion.com/Responsive_web_payment
 * The payment process:
 * 1.) In all scenarios the payment starts with the preparation of the payment.
 *     This means that the webshop creates a JSON representation of the payment data.
 *     This data contains all the necessary information about the items to be purchased,
 *     the customer and the desired payment scenario. This JSON should be sent as the body
 *     of a POST request to the /v2/Payment/Start API endpoint. After the system recives the
 *     data it will be validated. If all the properties are correct a payment will be prepared
 *     in the database and a HTTP response is returned to the webshop.
 * 2.) The response should be processed by the webshop system and the Id of the payment (PaymentId)
 *     should be extracted from the response. This Id should be stored in the webshop database with
 *     the initial information about the payment. This is necessary because every following request is based on this Id.
 * 3.) The next step is to redirect the customer to the Barion website to a specific URL with the PaymentId as a query parameter.
 * 4.) After the customer paid the requested amount and the payment is completed a server to server request is
 *     initiated by Barion towards the webshop. This is called the callback mechanism.
 * 5.) At this point the customer is advised on the screen to return to the webshop to complete the process.
 *     If the customer clicks through the customer is redirected back to the webshop.
 * 6.) At any point of the process the webshop can request information about the
 *     payment through the v2/Payment/GetPaymentState API endpoint.
 */
class Nagyapartman_Barion
{
    /* Consts for the data to send to Barion server. */
        const MAX_LEN_OF_PAYMENT_ID_C           = 60; //100; /* ...will be enough. */

        const MAX_LEN_OF_POSTRANSACTION_ID_C    = 50;

        const MAX_LEN_OF_RECURRENCE_ID_C        = 100;

        const MAX_LEN_OF_SKU_C                  = 100;

        const MAX_LEN_OF_ORDER_NUMBER_C         = 100;

        const MAX_LEN_OF_EMAIL_PARAM_C          = 256;

        const MIN_LEN_OF_CARDHOLDER_NAME_HINT_C = 2;

        const MAX_LEN_OF_CARDHOLDER_NAME_HINT_C = 45;

        const MAX_LEN_OF_CALLBACK_URL_C         = 2000;

        const MAX_LEN_OF_ITEM_NAME_C            = 250;

        const MAX_LEN_OF_DESCRIPTION_C          = 500;

        const MAX_LEN_OF_UNIT_C                 = 50;

        const MAX_LEN_FOR_PHONE_NUMBERS_C       = 30;

    /* Locale consts */
        const LOCALE_CZ_C  = 'cs-CZ';

        const LOCALE_DE_C  = 'de-DE';

        const LOCALE_EN_C  = 'en-US';

        const LOCALE_ES_C  = 'es-ES';

        const LOCALE_FR_C  = 'fr-FR';

        const LOCALE_HU_C  = 'hu-HU';

        const LOCALE_SK_C  = 'sk-SK';

        const LOCALE_SI_C  = 'sl-SI';

    /* Currency consts */
        const CURRENCY_OF_CZK_C  = 'CZK';

        const CURRENCY_OF_EUR_C  = 'EUR';

        const CURRENCY_OF_HUF_C  = 'HUF';

        const CURRENCY_OF_USD_C  = 'USD';

    /* ChallengePreference consts */
        const NO_PREFERENCE_C       = 0;

        const CHALLENGE_REQUIRED_C  = 10;

        const NO_CHALLENGE_NEEDED_C = 20;

    /* RecurrenceType consts */
        const MERCHANT_INITIATED_PAYMENT_C  = 0;

        const ONE_CLICK_PAYMENT_C           = 10;

        const RECURRING_PAYMENT_C           = 20;

    /* API endpoints */
        const API_EP__PAYMENT_START      = '/v2/Payment/Start';  // Start a payment

        const API_FORM__PAYMENT_START    = 'start_barion_payment';

        // Query information about a given payment.
        // Only supports GET method.
        const API_EP__GET_PAYMENT_INFO   = '/v2/Payment/GetPaymentState';

    /* HTML sending methods. */
        const HTTP_METHOD_POST = 'POST';

        const HTTP_METHOD_GET  = 'GET';

    /* HTML Response codes. */
        const HTML_OK           = 200;

        const HTML_BAD_REQUEST  = 400;

    /*
     * HTML _GET array keys after barion payment
     * and main key for paying in the system.
     */
        const PAYMENT_ID_KEY  = 'paymentId';

    /*
     * User meta key for successful payment callback key.
     */
        const CALLBACK_MESSAGE_KEY = 'callbackKey';

    /*
     * Status indicators from '/v2/Payment/GetPaymentState'
     */
        const STATUS_PREP = 'Prepared';

        const STATUS_STARTED = 'Started';

        const STATUS_IPROGRESS = 'InProgress';

        const STATUS_WAITING = 'Waiting';

        const STATUS_RESERVED = 'Reserved';

        const STATUS_AUTH = 'Authorized';

        const STATUS_CANCELED = 'Canceled';

        const STATUS_SUCCESS = 'Succeeded';

        const STATUS_FAILED = 'Failed';

        const STATUS_PSUCC = 'PartiallySucceeded';

        const STATUS_EXP = 'Expired';

    /* Callback URL destination */
        const CALLBACK_ACTION_URL  = '/check_payment/';

    /* JSON Response/Error keys. */
        const RESP__NO_ERRORS                        = 'NoErrors';

        const RESP__NO_STATUS_CODE                   = 'NoHTMLStatusCodeInResponse';

        const RESP__NO_ERROR_CODE                    = 'NoErrorCodeInResponse';

        const RESP__HTML_NOT_OK                      = 'HTMLRespCodeNotOk';

        const RESP__NO_PAYMENT_ID                    = 'NoPaymentIdInResponse';

        const RESP__NO_GATEWAY_URL                   = 'NoGatewayUrlInResponse';

        const RESP__NO_TRANSACTIONS                  = 'NoTransactionsInResponse';

        const RESP__NO_STATUS                        = 'NoStatusInResponse';

        const RESP__PAYMENT_ID_MISMATCH              = 'PaymentIdNotMathingLastSavedValue';

        const RESP__PAYMENT_ID_RETRIEVAL_FAIL        = 'PaymentIdFailedToRetrieveFromRemote';

        const RESP__START_OF_PAYMENT_FAILED          = 'PaymentStartFailed';

        const RESP__START_OF_PAYMENT_FAIL_MPTY_DAT   = 'PaymentStartFailedBecauseEmptyData';

        const ERROR__FAILED_TO_PACK_JSON_DATA        = 'FailedToPackJsonData';

        const ERROR__FAILED_TO_PACK_TRANSACTIONS     = 'FailedToPackTransactions';

        const ERROR__MISSING_POSTTRANSID             = 'MissingPostTransId';

        const ERROR__MISSING_PAYEE                   = 'MissingPayee';

        const ERROR__MISSING_TOTAL                   = 'MissingTotal';

        const ERROR__MISSING_ITEMS                   = 'MissingItems';

        const ERROR__EMPTY_ITEMS                     = 'EmptyItems';

        const ERROR__EMPTY_TRANSACTIONS              = 'EmptyTransactions';

        const ERROR__MISSING_IT_NAME                 = 'MissingItemName';

        const ERROR__MISSING_IT_DESCR                = 'MissingItemDescription';

        const ERROR__MISSING_IT_QTY                  = 'MissingItemQuantity';

        const ERROR__MISSING_IT_UNIT                 = 'MissingItemUnit';

        const ERROR__MISSING_IT_UPRICE               = 'MissingItemUnitPrice';

        const ERROR__MISSING_IT_TOTAL                = 'MissingItemTotal';

        const FAILED_TO_RETRIEVE_PID_FROM_DB         = 'CannotRetrievePIDFromDb';

        const FAILED_TO_RETRIEVE_REMOTE_STATUS       = 'CannotRetrieveStatusData';

        const FAILED_TO_UPDATE_STATUS_TO_DB          = 'CannotUpdateStatusToDb';

        const FAILED_TO_INSERT_TO_DB                 = 'CannotInsertPaymentStartToDb';

        const FAILED_TO_SEND_200_OK                  = 'CannotSend200OK';

        const FAILED_CANNOT_PROCESS_RESPONSE         = 'CannotProcessResponseForStartPayment';

        const FAILED_CANNOT_START_PAYMENT            = 'CannotStartPayment';

    /* Output array keys */
        const OP_ERROR   = 'errors';

        const OP_MESSAGE = 'message';

    /*
     * Barion-related secure ips.
     * See: https://docs.barion.com/Security_Measures
     */
    private $barion_secure_ips = array('13.79.241.141', '40.69.88.149', '40.69.88.240', '52.164.220.205', '52.169.80.55');

    /*
     * The url of the barion server.
     */
    private $barion_api_url;

    /*
     * barion POS key.
     */
    private $barion_pos_key = 'c9a000420f7e45b1bfaf9859b77757d2';

    /*
     * barion public id.
     */
    private $barion_public_id = '0f8af7e1306b4595b9a9f9167ae9639e';

    /*
     * The barion pixel id.
     */
    private $barion_pixel_id = 'BPT-YP7eEebTye-83';

    /*
     * The wordpress database instance.
     */
    private $globalwpdb;

    /*
     * The table name for the barion transactions.
     */
    private $tbl_name = 'barion_transactions';

    /*
     * Input parameters to collect data from the user for the transactions.
     * This will be a huge associative array.
     * https://docs.barion.com/Payment-Start-v2
     */
    private $input_data;

    /*
     * Associative array, that holds
     * the data for the payment.
     * (2021.01.17)
     */
    private $form_data;

    /*
     * PaymentId
     * String
     * The transactions will be tracked by this id
     * also, this will be saved to the db.
     */
    private $payment_id;

    /*
     * PaymentRequestId
     * String
     * The shop-generated payment id.
     */
    private $payment_request_id;

    /*
     * GatewayUrl
     * String
     * The page to redirect the user to after
     * starting a payment transaction.
     */
    private $gateway_url;

    /*
     * Transactions
     * Array
     * The array of the payment transactions.
     */
    private $transactions;

    /*
     * Status
     * String
     * A status indicator of the payment.
     */
    private $payment_status;

    /*
     * Errors stored in an associative array.
     */
    private $output;

    /*
     * User id
     */
    private $user_id;

    public function __construct()
    {
        _log( __CLASS__.'.'.__FUNCTION__, 'created class instance' );
        $this->form_data = array();
        $this->user_id = get_current_user_id();
        $this->barion_api_url = $this->get_api_url();
        $this->payment_id = '';
        $this->gateway_url = '';
        $this->payment_status = '';
        $this->payment_request_id = '';
        $this->transactions = array();
        $this->output = array( self::OP_MESSAGE => '',
                               self::OP_ERROR   => array( self::RESP__NO_ERRORS ) );
        $this->choose_wpdb();
        add_action( 'wp_head', array( &$this, 'hook_barion_pixel_basic_js' ) );
        add_action( 'init', array(&$this, 'callback_action') );
        //add_action( 'init', array(&$this, 'display_message_to_user') );
        //add_action( 'wp_ajax_go_pay_with_barion', array( &$this, 'go_pay_with_barion') );   // Ajax trigger
        //add_action( 'wp_ajax_nopriv_go_pay_with_barion', array( &$this, 'go_pay_with_barion') );   // Ajax trigger
        //add_shortcode( 'message_to_user', array(&$this, 'display_message_to_user') );
    }

    /*
     * This is the actual logic for starting a payment towards the
     * Barion server.
     *
     * @param transactions - the tansactions associative array.
     * @param localities - contains the locale, and currency keys for data evaluation.
     * @param other_data - contains secondary datas for the transaction.
     * @return associative array - with the 'status', 'message', and (redirect) 'url' parameters.
     */
    public function do_start_payment( $transactions, $localities, $other_data )
    {
        _log( __FUNCTION__, 'started' );
        $data = $this->wr_collect_data_for_transaction( $transactions, $localities, $other_data );
        $result = array( 'status' => self::RESP__NO_ERRORS,
                         'url' => '',
                         'message' => '' );
        if ( !$this->is_any_error() )
        {
            $response = $this->start_barion_payment( $data );
            if ( is_array( $response ) )
            {
                if ( $this->process_response( $response['response'], $response['body'] ) )
                {
                    // Response processed, lets save the given new PaymentId and Status.
                    $rows = $this->save_payment_data_in_db( get_current_user_id(),
                                                            $this->get_payment_id(),
                                                            $this->get_payment_status() );
                    if ( $rows > 0 )
                    {  // Successful insert.
                        $result['url'] = $this->get_gateway_url();
                        $result['message'] = __('Fizetés indítása sikeres, átirányítás folyamatban, kérem várjon');
                    }
                    else
                    {  // Handle db insert error.
                        $result['message'] = __('Adatbázis művelet sikertelen!');
                        $result['status'] = self::FAILED_TO_INSERT_TO_DB;
                    }
                }
                else
                {  // Handle response processing related errors
                    $result['message'] = __('Fizetés indítására tett próbálkozás sikertelen!');
                    $result['status'] = self::FAILED_CANNOT_PROCESS_RESPONSE;
                }
            }
            else
            {  // Handle payment start error
                $result['message'] = __('Fizetés indítása sikertelen!');
                $result['status'] = self::RESP__START_OF_PAYMENT_FAILED;
            }
        }
        else
        {
            // Process data collecting errors.
            /* 'message' (self::OP_MESSAGE),
             * 'errors'  (self::OP_ERROR) */
            $errors = $this->get_errors();  // TODO with errors.
            _error( __FUNCTION__, 'Errors='.print_r($errors, true) );
            $result['message'] = __('Fizetés indítása hibába ütközött!');
            $result['status'] = self::FAILED_CANNOT_START_PAYMENT;
        }
        return $result;
    }

    /*
     * Start Payment to Barion API.
     *
     * @param transactions - the tansactions associative array.
     * @param localities - contains the locale, and currency keys for data evaluation.
     * @param other_data - contains secondary datas for the transaction.
     * NOTE! All the above data will be collected via the POST array.
     * NOTE! Currently, this function is NOT used, but here as a placeholder.
     *
     */
    public function go_pay_with_barion()
    {
        $result = null;
        $transactions = array();
        $items = array();
        $status = self::RESP__NO_ERRORS;
        _log( __FUNCTION__, 'post='.print_r($_POST, true));
        if( array_key_exists( self::API_FORM__PAYMENT_START, $_POST ) && array_key_exists( 'data', $_POST ) )
        {   // De-parse values.
            $data = $_POST['data'];
            if ( array_key_exists( 'items', $data ) )
            {
                /*
                 * Must contain:
                 * Name'=> 'iPhone 7 smart case',
                 * Description'=> 'Durable elegant phone case / matte black',
                 */
                $items = $data['items'];
                $items['Unit'] = 'Darab';
                $items['Quantity'] = 1;
                /*
                 * ItemTotal = Transactions[Total] -> later.
                 * UnitPrice = Transactions[Total] -> later.
                 */
                _log( __FUNCTION__, 'items parsed='.print_r($items, true));
            }
            else
            {
                _error( __FUNCTION__, 'No "items" to parse in ajax request.' );
                $status = self::ERROR__EMPTY_ITEMS;
            }

            if ( array_key_exists( 'transactions', $data ) )
            {
                /*
                 * Must contain:
                 * Total,
                 * Comment
                 */
                $transactions = $data['transactions'];
                $transactions['Payee'] = 'hello@nagyapartmansiofok.hu';
                $items['ItemTotal'] = $transactions['Total'];
                $items['UnitPrice'] = $transactions['Total'];
                $transactions['Items'] = array( $items );
                $transactions['Comment'] = 'Szállásfoglalás';
                _log( __FUNCTION__, 'transactions parsed='.print_r($transactions, true));
            }
            else
            {
                _error( __FUNCTION__, 'No "transactions" to parse in ajax request.' );
                $status = self::ERROR__EMPTY_TRANSACTIONS;
            }

            $localities = array( 'Locale' => 'hu-HU', 'Currency' => 'HUF' );  // TODO: make this selectable later.

            // GDLR link. (unfortunatelly, not in variable, as the order of the 2 plugins are the opposite)
            $other_data = array( 'RedirectUrl' => site_url()."/?Foglalás" );
            _log( __FUNCTION__, 'other data='.print_r($other_data, true));
            _log( __FUNCTION__, 'transactions='.print_r($transactions, true) );
            if ( $status === self::RESP__NO_ERRORS )
            {
                $result = $this->do_start_payment( array( $transactions ), $localities, $other_data );
            }
            else
            {
                $result = array( 'status' => $status,
                                 'url' => '',
                                 'message' => __('Hiányos adatok a Barion szerverrel történő kommunikációnál' ));
            }
        }
        /*
         * result, status, url
         * as parameters.
         */
        echo json_encode( $result );
        die();
    }

    /*
     * Helper method for Barion form.
     */
    public function set_form_data( $key, $subkey, $value )
    {
        $this->form_data[$key][$subkey] = $value;
        _log( __FUNCTION__, 'invoked with='.$key.'", "'.$subkey.'", "'.$value.' -> '.print_r( $this->form_data, true ) );
    }

    /*
     * This method integrates the payment
     * with GDLR plugin.
     * The displayed form will contain each and every data for
     * the Barion ajax call.
     *
     * Test data:
     *   $item1 = array(
     *      'Name'=> 'iPhone 7 smart case',
     *      'Description'=> 'Durable elegant phone case / matte black',
     *      'Quantity'=> 1,
     *      'Unit'=> 'piece',
     *      'UnitPrice'=> 25.2,
     *      'ItemTotal'=> 25.2,
     *  );
     *  $transactions = array(
     *      array(
     *          'POSTransactionId' => 'EXMPLSHOP-PM-001/TR001',
     *          'Payee'            => 'hello@nagyapartmansiofok.hu',
     *          'Total'            => 37.2,
     *          'Comment'          => 'A brief description of the transaction',
     *          'Items'            => array(
     *              $item1
     *          )
     *      )
     *  );
     *  $localities = array( 'Locale' => 'fr-FR', 'Currency' => 'EUR' );
     */
    public function echo_barion_payment_to_page()
    {
        _log( __FUNCTION__, print_r( $this->form_data, true ) );
        echo "<div id='barion-payment-form'>";
        foreach( $this->form_data as $k => $v )
        {
            //_log( __FUNCTION__, 'key='.$k.', value='.print_r($v, true));
            foreach( $v as $subkey => $subvalue )
            {
                //_log( __FUNCTION__, '<input type="hidden" id="data-'.$k.'-'.$subkey.'" name="data['.$k.']['.$subkey.']'.'" value="'.$subvalue.'">' );
                echo '<input type="hidden" id="data-'.$k.'-'.$subkey.'" name="data['.$k.']['.$subkey.']" value="'.$subvalue.'">';
            }
        }
        echo '</div>';
    }

    /*
     * Code snippet, that will be displayed in the starting page to
     * inform the user about a possible message, after a successful
     * Barion transaction.
     */
    public function display_message_to_user()
    {
        if ( array_key_exists( 'paymentId', $_GET ) && !empty( $_GET['paymentId'] ) )
        {
            $msg = $this->manage_user_callback_message( $_GET['paymentId'] );
            _log( __FUNCTION__, 'paymentId='.$_GET['paymentId'].', message='.$msg );
            if ( !empty( $msg ) )
            {
                $ret = "<div class='gdlr-error-message' style='display:block;font-size:0.9em;color:#ffffff;padding:3px;' id='gdlr-error-message'></div>";
                $ret .= '<script type="text/javascript">';
                $ret .=
                    'jQuery(document).ready(function(){'.
                         'function showFinalMessage( msg, is_error ) {'.
                            'var content_area = jQuery("#gdlr-error-message");'.
                            'var background_color = is_error ? "#d15e5e" : "#00994c";'.
                            'content_area.css( {"background-color": background_color} );'.
                            'content_area.html( msg ).slideDown();'.
                         '}'.
                         'showFinalMessage("'.$msg.'", false );'.
                    '});';
                $ret .= '</script>';
                return $ret;
            }
        }
        return '';
    }

    /*
     * Handler method after payments.
     * _POST[paymentId] will be given after any
     * successful Barion payment.
     *
     * Query the database, to check the last inserted paymentId for this userID
     * and match it with this received value.
     *
     * If it is correct, perform a status check on the payment, and print out
     * any information regarding it.
     * If not, this will display some error message.
     *
     * The callback request contains one parameter, the payment identifier.
     * This is sent in the 'paymentId' field of the POST request body.
     * The merchant's system must send an HTTP 200 OK response (with any content)
     * in order for the callback to be considered successful. The timeout period
     * for answering a callback request is 15 seconds.
     * If the Barion system does not get an HTTP 200 response, it retries sending
     * the callback for a maximum of 5 times, with exponential back-off timing delay between tries:
     * -  2 seconds
     * -  6 seconds
     * - 18 seconds
     * - 54 seconds
     * -  2 minutes 42 seconds
     * So the total time window allocated for successful callback is roughly 4 minutes.
     * If the Barion system fails to get an HTTP 200 response after that, the
     * callback is not sent and the merchant's system automatically gets
     * an e-mail notification about the error.
     * Every time, when your system receives a callback request from the Barion you
     * have to call the /v2/Payment/GetPaymentState API endpoint to find out the change.
     * First, your system should check the payment's status and after the list of the
     * payment's transactions. For example, if you requested a payment refund or when
     * the reserved payment's time limit has expired the list of payment’s transactions
     * will contain new e-money transactions. It's your system's responsibility to handle changes.
     *
     * This method will be called via the Barion Remote host POST request.
     */
    public function callback_action()
    {
        $first = (0 == strcasecmp( $_SERVER['REQUEST_METHOD'], 'POST' ));
        $second = (false !== strpos( $_SERVER['REQUEST_URI'] , 'check_payment'));
        $third = (array_key_exists( 'PaymentId', $_POST ));
        _log( __FUNCTION__, "Entry conditions:{ 'req_meth is post':(".$first."), 'req_uri contains <check_payment>':(".$second."), 'PaymentId in _POST':(".$third.") }" );
        if ( 0 == strcasecmp( $_SERVER['REQUEST_METHOD'], 'POST' ) &&
             false !== strpos( $_SERVER['REQUEST_URI'] , 'check_payment' ) && /* unique callback url find. */
             array_key_exists( 'PaymentId', $_POST ) )
        {
            /*
             * Barion recommendation to restrict ip-addresses that are
             * capable of invoking this function.
             */
            if ( !WP_BARION_TESTING_ON_LOCALHOST )
            {
                if ( BARION_RESTRICTED_IPS_ONLY &&
                     !in_array( $_SERVER['REMOTE_ADDR'], $this->barion_secure_ips ) )
                {
                    _log( __FUNCTION__, "forbidden IP addr.: '".$_SERVER['REMOTE_ADDR']."'" );
                    $this->echo_response_and_exit( 'Not allowed' );
                }
            }

            $received_payment_id = $_POST['PaymentId'];
            _log( __FUNCTION__, "received PaymentId='".$received_payment_id."'" );

            $payment_id_from_db = $this->get_payment_data_from_db_by_pid( $received_payment_id );
            if ( !$payment_id_from_db )
            {
                _error( __FUNCTION__, 'cannot retrieve valid payment id from db!' );
                if ( WP_BARION_TESTING_ON_LOCALHOST ) { return false; }  /* FOR TESTING. */
                $this->echo_response_and_exit( 'failure #1' );
            }
            // Received payment_id is valid from here.
            $user_id = $payment_id_from_db['userID'];

            // Make the status-query to Barion-Server
            $response = $this->get_payment_state_from_remote( $received_payment_id );
            if ( !$response )
            {
                _error( __CLASS__.'.'.__FUNCTION__, 'cannot retrieve payment state from remote!' );
                if ( WP_BARION_TESTING_ON_LOCALHOST ) { return false; }  /* FOR TESTING. */
                $this->echo_response_and_exit( 'failure #2' );
            }
            $response = json_decode( $response['body'], true );
            $status = $response['Status'];
            _log( __FUNCTION__, 'status is="'.$status.'"' );
            $ret_val = $this->update_payment_status_in_db( $user_id,
                                                           $received_payment_id,
                                                           $status );
            if ( !$ret_val )
            {
                _error( __CLASS__.'.'.__FUNCTION__, 'updating status to db '.
                        'for paymentId=('.$received_payment_id.') failed.' );
                if ( WP_BARION_TESTING_ON_LOCALHOST ) { return false; }  /* FOR TESTING. */
                $this->echo_response_and_exit( 'failure #3' );
            }

            $update = $this->manage_user_callback_message( $received_payment_id, __($this->translate_from_status( $status )) );
            _log( __FUNCTION__, 'update is='.print_r($update, true));
            if ( !$update ) { _log( __FUNCTION__, 'updating the user message failed' ); }
            _log( __FUNCTION__, 'payment callback is successfully updated and done, EXITING.' );
            if ( WP_BARION_TESTING_ON_LOCALHOST ) { return true; }  /* FOR TESTING. */
            $this->echo_response_and_exit( 'success, status is='.$status );
        }
        else
        {   // No 'PaymentId' post variable is set, silently continue.
            return false;
        }
    }

    /*
     * Helper method for POST request.
     * The msg variable is not required as a final result.
     * This message will only mean information to the Barion server.
     */
    public function echo_response_and_exit( $msg )
    {
        header('Content-Type: application/json');
        http_response_code( self::HTML_OK );
        _log( __FUNCTION__, "msg='".$msg."'" );
        echo json_encode( array('result' => $msg ));
        exit();
    }

    /*
     * This hook allows you to create a function that runs when
     * your plugin is activated. It takes the path to your main
     * plugin file as the first argument, and the function that
     * you want to run as the second argument. You can use this
     * to check the version of your plugin, do some upgrades between
     * versions, check for the correct PHP version and so on.
     *
     * NOTE: PHPUnit cleans the database every time the test run.
     * The secondary activate function ot this is to update
     * the 'active_plugins' list, and make this the first to load
     * plugin.
     */
    public function register_activation_hook() {
        return $this->create_table();
    }

    /*
     * The name says it all. This function works like its counterpart above,
     * but it runs whenever your plugin is deactivated.
     * I suggest using the next function when deleting data; use this
     * one just for general housekeeping.
     */
    public function register_deactivation_hook() {
        _log( __FUNCTION__, 'invoked' );
    }

    /*
     * This function runs when the website administrator deletes
     * your plugin in WordPress’ back end. This is a great way to
     * remove data that has been lying around, such as database tables,
     * settings and what not. A drawback to this method is that the
     * plugin needs to be able to run for it to work; so, if your plugin
     * cannot uninstall in this way, you can create an uninstall.php file.
     * Check out this function’s documentation for more information.
     */
    public function register_uninstall_hook() {
        _log( __FUNCTION__, 'invoked' );
    }

    /*
     * Helper method that is not used as for now. (2021.01.14)
     * The main purpose of to update the order of the plugins,
     * so that this plugin will load first.
     */
    public function order_plugin_first()
    {
        $wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR."/$2", __FILE__);
        _log( __FUNCTION__, 'wp_path_to_this_file='.$wp_path_to_this_file );
        $this_plugin = plugin_basename(trim($wp_path_to_this_file));
        _log( __FUNCTION__, 'this_plugin='.$this_plugin );
        $active_plugins = get_option('active_plugins');
        _log( __FUNCTION__, 'active_plugins before='.print_r( $active_plugins, true ) );
        $this_plugin_key = array_search($this_plugin, $active_plugins);
        _log( __FUNCTION__, 'this_plugin_key='.$this_plugin_key );
        if ( $this_plugin_key )
        {  // Remove plugin-element from array
            array_splice($active_plugins, $this_plugin_key, 1);
        }
        //array_push($active_plugins, $this_plugin);   // Adds to the very last element.
        array_unshift($active_plugins, $this_plugin);  // Adds plugin-element to the very first position.
        update_option('active_plugins', $active_plugins);
        _log( __FUNCTION__, 'active_plugins after='.print_r( $active_plugins, true ) );
    }

    /*
     * Helper method to decide, if there are any errors present.
     * Usually after packing the data to start payment.
     *
     * @return true|false
     */
    public function is_any_error()
    {
        return ( 0 != strcmp( self::RESP__NO_ERRORS, $this->output[self::OP_ERROR][0] ) );
    }

    /*
     * Helper method to set error.
     *
     * The error messages are gathered in an array one-by-one.
     * (
     *  [message] => |text|text2
     *  [errors] => Array
     *    (
     *       [0] => error_constant
     *       [1] => error_constant2
     *    )
     * )
     *
     * @param text_msg - the textual reprezentation of the error.
     * @param err_const - the constant of the error.
     */
    public function set_errors( $text_msg, $err_const )
    {
        $this->output[self::OP_MESSAGE] = $this->output[self::OP_MESSAGE].'|'.__($text_msg);

        /*
         * If currently there are no errors
         * put the first error to the 0th place.
         */
        if ( 0 == strcmp( self::RESP__NO_ERRORS, $this->output[self::OP_ERROR][0] ) )
        {
            $this->output[self::OP_ERROR][0] = $err_const;
        }
        else
        {   // Add more errors.
            $this->output[self::OP_ERROR][] = $err_const;
        }
    }

    /*
     * Retrieve errors in an associative array.
     * Keys will be:
     *    'message' (self::OP_MESSAGE)  {concat. str},
     *    'errors'  (self::OP_ERROR)  []
     */
    public function get_errors()
    {
        return $this->output;
    }

    /*
     * Basic getter for {payment_id}.
     */
    public function get_payment_id()
    {
        return $this->payment_id;
    }

    /*
     * Basic getter for {payment_request_id}.
     */
    public function get_payment_request_id()
    {
        return $this->payment_request_id;
    }

    /*
     * Basic getter for {transactions}.
     */
    public function get_transactions()
    {
        return $this->transactions;
    }

    /*
     * Basic getter for {gateway_url}.
     */
    public function get_gateway_url()
    {
        return $this->gateway_url;
    }

    /*
     * Basic getter for {payment_status}.
     */
    public function get_payment_status()
    {
        return $this->payment_status;
    }

    /*
     * Basic getter for {tbl_name}.
     */
    public function get_tbl_name()
    {
        return $this->tbl_name;
    }

    /*
     * Basic getter for {globalwpdb}.
     */
    public function get_wpdb()
    {
        return $this->globalwpdb;
    }

    /*
     * Collects and validates data (to later construct as a json data string).
     *
     * Note: 2020.12.28:
     *   This method does not return when there are empty/missing data in the
     *   inputs. Error log will be written to notify the caller, but in any
     *   case, its up to the caller, to provide good input data for the
     *   communication with Barion server.
     *
     * (REQ FOR 3DS means: Mostly optional)
     *
     *  -- Array key-parameters: [*(R) means required, (O) means optional.]
     *
     * @param transactions[]       - (REQ)  The payment transactions.
     *                                 keys: POSTransactionId     (R),
     *                                       Payee                (R),
     *                                       Total                (R),
     *                                       Comment              (O),
     *                                       PayeeTransactions[]  (O),
     *                                       Items[]              (R)
     * @param localities[]         - (REQ)  The Locale, and Currency params.
     *                                 keys:  Locale   (R)   ('HUF' by default),
     *                                        Currency (R)   ('hu-HU' by default)
     * @param purchase_info[]      - (REQ FOR 3SA) - The purchase information param.
     *                                 keys: DeliveryTimeframe          (O)
     *                                       DeliveryEmailAddress       (O)
     *                                       PreOrderDate               (O)
     *                                       AvailabilityIndicator      (O)
     *                                       ReOrderIndicator           (O)
     *                                       ShippingAddressIndicator   (O)
     *                                       RecurringExpiry            (O)
     *                                       RecurringFrequency         (O)
     *                                       PurchaseType               (O)
     *                                       GiftCardPurchase           (O)
     *                                       PurchaseDate               (O)
     * @param urls[]               - (REQ)  The redirect and the callback urls.
     *                                      If not provided, the system will use the redirect URL
     *                                      assigned to the shop that started the payment.
     *                                      Required by barion documentation, but override in this class
     *                                      as the extract methods can work with site_url() wp function.
     *                                 keys: RedirectUrl  (R),
     *                                       CallbackUrl  (R)
     * @param email_address[]      - (REQ FOR 3DS)  The email addr. of the actual payer,
     *                                 keys: Payer,
     *                                       Secondary
     *                                 key packed in final data:
     *                                       PayerHint (O)
     * @param sh_addresses[]       - (REQ FOR 3DS)  The shipping and the billing address.
     *                                 keys: ShippingAddress (O),
     *                                       BillingAddress  (R)
     * @param phone_numbers[]      - (REQ FOR 3DS)  Containing the following phonenumbers:
     *                                 keys: PayerPhoneNumber     (O),
     *                                       PayerWorkPhoneNumber (O),
     *                                       PayerHomeNumber      (O)
     * @param payer_account_info[] - (REQ FOR 3DS)  The PayerAccountInformation parameter.
     *                                 keys: AccountId                      (O),
     *                                       AccountCreated                 (O),
     *                                       AccountCreationIndicator       (O),
     *                                       AccountLastChanged             (O),
     *                                       AccountChangeIndicator         (O),
     *                                       PasswordLastChanged            (O),
     *                                       PasswordChangeIndicator        (O),
     *                                       PuschasesInTheLast6Months      (O),
     *                                       ShippingAddressAdded           (O),
     *                                       ShippingAddressUsageIndicator  (O),
     *                                       ProvisionAttempts              (O),
     *                                       TransactionalActivityPerDay    (O),
     *                                       TransactionalActivityPerYear   (O),
     *                                       PaymentMethodAdded             (O),
     *                                       SuspiciousActicityIndicator    (O)
     *
     *  -- Normal parameters:
     * @param recurrence_value     - (REQ FOR 3DS) [int]  Containing the value for recurrence.
     *                                 key: RecurrenceType
     * @param order_number         - (Optional) [string]  The order number generated by the webshop.
     *                                 key: OrderNumber
     * @param challeng_pref        - (REQ FOR 3DS) [int]  The challenge preference param.
     *                                 key: ChallengePreference
     * @param ch_name              - (REQ FOR 3DS) [string]  Card holder name hint.
     *                                 key: CardHolderNameHint
     *
     * @return associative array.
     *         IMPORTANT: The empty values will be removed from the associative array.
     *                    Error log will be written only to the required values.
     *                    All the others will be simple notice log.
     *
     * (2020.11.27)
     * Please see https://docs.barion.com/Payment-Start-v2 , for more datafield tags.
     * Use cases:
     *    /Payment-Start-v2
     *    The API to start payments is designed to be used in the following scenarios:
     *    - Responsive Web Payment
     *
     * Properties marked with 3DS must be provided to comply with 3D Secure authentication.
     * The more attributes you provide the more chance you have to avoid the challenge flow.
     */
    public function collect_data_for_transaction( array $transactions,        /* (req)         */
                                                  array $localities,          /* (req)         */
                                                  array $purchase_info,       /* (req for 3DS) */
                                                  array $urls,                /* (req but has def. val.) */
                                                  array $email_address,       /* (req for 3DS) */
                                                  array $sh_addresses,        /* (req for 3DS) */
                                                  array $phone_numbers,       /* (req for 3DS) */
                                                  array $payer_account_info,  /* (req for 3DS) */
                                                  $recurrence_value,          /* (req for 3DS) */
                                                  $order_number,              /* (optional)    */
                                                  $challenge_pref,            /* (req for 3DS) */
                                                  $ch_name                    /* (req for 3DS) */
                                                )
    {
        _log( __FUNCTION__, 'entering');

        $this->input_data = array();

        /*
         * POSKey
         * type:Guid
         * Required
         *
         * The secret API key of the shop, generated by Barion.
         * This lets the shop to authenticate through the Barion API,
         * but does not provide access to the account owning the shop itself.
         */
        $this->input_data['POSKey'] = $this->barion_pos_key;

        /*
         * PaymentType
         * type:string
         * Required
         * Values:
         *   'Immediate|Reservation|DelayedCapture' (fixed)
         *
         * The type of payment, which can be either immediate or a money reservation.
         * Reservation means that the shop has a time window to finish the payment
         * (even though the money transaction still takes place immediately).
         * Reservation amounts can be modified during this time window unless
         * the new amount is lower than the original.
         */
        $this->input_data['PaymentType'] = 'Immediate';  /* TODO: To be discussed.*/

        /*
         * ReservationPeriod
         * type:Time(d.hh:mm:ss)
         * Required only if 'PaymentType' is 'Reservation' !
         * Values:
         *   Min: 1 minute,
         *   Max: 1 year,
         *   Default: 30 minutes
         *
         * This is the time window that allows the shop to finish (finalize) the payment.
         * If this does not happen within the time window, the system refunds the payment amount to the payer.
         */
        if ( 0 == strcmp( $this->input_data['PaymentType'], 'Reservation' ) )
        {
            $this->input_data['ReseravationPeriod'] = '0.00:30:00';  /* Default */
        }

        /*
         * DelayedCapturePeriod
         * type:Time(d.hh:mm:ss)
         * Required only if 'PaymentType' is 'DelayedCapture' !
         * Values:
         *   Min: 1 minute
         *   Max: 7 days
         *   Default: 7 days
         *
         * This is the time window that allows the shop to complete (finalize) the payment.
         * If this does not happen within the time window, the system releases the payment amount.
         */
        if ( 0 == strcmp( $this->input_data['PaymentType'], 'DelayedCapture' ) )
        {
            $this->input_data['DelayedCapturePeriod'] = '7.00:00:00';  /* Default */
        }

        /*
         * PaymentWindow
         * type:Time(d.hh:mm:ss)
         * Optional
         * Values:
         *   Min: 1 minute
         *   Max: 1 week
         *   Default: 30 minutes
         *
         * Time window for the payment to be completed. The payer must execute the
         * payment before this elapses, or else the payment will expire and can no longer be completed.
         */
        $this->input_data['PaymentWindow'] = '0.00:30:00';  /* Default */ /* TODO */

        /*
         * GuestCheckOut
         * type:bool
         * Required
         * Values:
         *   true|false  (int evaluation is not supported)
         *
         * Flag indicating whether the payment can be completed without
         * a registered Barion wallet. Guest checkout can only be done with
         * bank cards, and the payer must supply a valid
         * e-mail address - this is necessary for fraud control.
         */
        $this->input_data['GuestCheckOut'] = false;  /* TODO - Discuss. */

        /*
         * InitiateRecurrence
         * type:bool
         * Optional
         * Values:
         *   true|false
         *
         * This flag indicates that the shop would like to initialize a token payment.
         * This means that the shop is authorized to charge the funding source of the
         * payer in the future without redirecting her/him to the Barion Smart Gateway.
         * It can be used for one-click and subscription payment scenarios.
         */
        $this->input_data['InitiateRecurrence'] = false;  /* TODO - Discuss, but most likely false. */

        /*
         * RecurrenceId
         * type:string
         * Required only if executing token payments  ( InitiateRecurrence == true )
         * Values:
         *   Min: 0 chars.
         *   Max: 100 chars.
         *   Must be unique per shop and per user.
         *   Generated by the shop
         *
         * A string used to identify a given token payment.
         * Its purpose is determined by the value of the InitiateRecurrence property.
         *    If InitiateRecurrence is true, this property must contain a new desired identifier
         *    for a new token payment. This should be generated and stored by the shop before calling the API.
         *    Also, the shop must ensure that this is unique per user in its own system.
         *
         *    If InitiateRecurrence is false, this property must contain an existing identifier for a token payment.
         *    This should be used to charge a payer's funding source (either bank card or Barion wallet)
         *    that was already used successfully for a payment in the shop.
         */
        if ( $this->input_data['InitiateRecurrence'] )
        {
            $this->input_data['RecurrenceId'] = $this->generate_recurrence_id();  /* TODO - Discuss. */
        }

        /*
         * FundingSources
         * type:string[]
         * Required
         * Values:
         *   "All"
         *   "Balance"
         *
         * An array of strings containing the allowed funding sources that can
         * be used to complete the payment.
         * "Balance" means that the payer can only use their Barion wallet balance, while
         * "All" means the payment can be completed with either a Barion wallet or a bank card.
         *
         * Note: There is no option to disallow payment by balance since that would
         * deny Barion Wallet users with a balance the ability to pay.
         * There is an option to exclude cards, but not balance.
         *
         * Note: this must be supplied as an array because more funding source types are planned in the future.
         */
        $this->input_data['FundingSources'] = ['All'];  /* TODO - Discuss. */

        /*
         * PaymentRequestId
         * type:string
         * Required
         * Values:
         *   Min: 0 chars.
         *   Max: 100 chars.
         *
         * The unique identifier for the payment generated by the shop.
         * This is so the shop can track its own payment identifiers.
         * It is also useful for bookkeeping purposes since this shows up in the
         * monthly account statement and the transaction history export,
         * making identification of payments easier for the shop.
         *
         * Note: 2020.12.30: This value should be saved to the db.
         */
        $this->payment_request_id = $this->generate_payment_id();
        $this->input_data['PaymentRequestId'] = $this->payment_request_id;

        /*
         * PayerHint
         * Required for 3DS (Optional but recommended)
         * type:string
         * Values:
         *   Max: 256 chars.
         *
         * The shop can supply an e-mail address as a hint on who should
         * complete the payment. This can be used if the shop is certain about
         * that the payer has an active Barion wallet or the shop would like to
         * help the guest payer with filling in the email field for her/him.
         * If provided, the Barion Smart Gateway automatically fills out the
         * e-mail address field in the Barion wallet login form and the guest
         * payer form, speeding up the payment process.
         */
        $this->input_data['PayerHint'] = $this->sanitize_payer_email( $email_address );

        /*
         * CardHolderNameHint
         * Required for 3DS (Optional but recommended)
         * type:string
         * Values:
         *   Min: 2 chars.
         *   Max: 45 chars.
         *
         * The shop can provide a hint for the customer's name on the card to
         * speed up the payment flow. If a value is provided, the cardholder name input
         * will be pre-filled with it and the customer can use the pre-filled value instead
         * of typing it out on its own, which speeds up the payment process.
         */
        $this->input_data['CardHolderNameHint'] = $this->sanitize_string( 'CardHolderNameHint',
                                                                          $ch_name,
                                                                          '' /* secondary value */,
                                                                          self::MAX_LEN_OF_CARDHOLDER_NAME_HINT_C );

        /*
         * RecurrenceType
         * type:RecurrenceType
         * Required for 3DS when executing token payment.  ( InitiateRecurrence == true )
         *
         * Describes the nature of the token payment.
         */
        if ( $this->input_data['InitiateRecurrence'] )
        {
            $this->input_data['RecurrenceType'] = $this->extract_recurrence( $recurrence_value );
        }

        /*
         * TraceId
         * type:string
         * Required for 3DS when executing token payment.  ( InitiateRecurrence == true )
         * Values:
         *   Max:100 chars.
         *
         * Identifies the nature of the token payment.
         */
        if ( $this->input_data['InitiateRecurrence'] )
        {
            $this->input_data['TraceId'] = $this->generate_id( 'traceId', self::MAX_LEN_OF_PAYMENT_ID_C );
        }

        /*
         * RedirectUrl
         * type:string
         * Required
         * Values:
         *   Max:2000 chars.
         *
         * The URL where the payer should be redirected after the payment is
         * completed or canceled. The payment identifier is added to the query
         * string part of this URL in the paymentId parameter.
         * If not provided, the system will use the redirect URL assigned to the shop that started the payment.
         */
        $this->input_data['RedirectUrl'] = $this->sanitize_string( 'RedirectUrl',
                                                                   ( array_key_exists( 'RedirectUrl', $urls ) ? $urls['RedirectUrl'] : '' ),
                                                                   site_url(),
                                                                   self::MAX_LEN_OF_CALLBACK_URL_C );

        /*
         * CallbackUrl
         * type:string
         * Required
         * Values:
         *   Max:2000 chars.
         *
         * The URL where the Barion system sends a request whenever there is a change
         * in the state of the payment. The payment identifier is added to the query
         * string part of this URL in the paymentId parameter.
         */
        $this->input_data['CallbackUrl'] = $this->sanitize_string( 'CallbackUrl',
                                                                   ( array_key_exists( 'CallbackUrl', $urls ) ? $urls['CallbackUrl'] : '' ),
                                                                   site_url().self::CALLBACK_ACTION_URL,
                                                                   self::MAX_LEN_OF_CALLBACK_URL_C );

        /*
         * Locale
         * type:string
         * Required
         * Values:
         *   Max:10 chars.
         *
         * This indicates in which language the Barion Smart Gateway
         * should display for the payer upon redirect.
         */
        $locale = array_key_exists( 'Locale', $localities ) ? $localities['Locale'] : '';
        $this->input_data['Locale'] = $this->extract_locale( $locale );

        /*
         * Currency
         * type:string
         * Required
         * Values:
         *   Max:3 chars.
         *
         * The currency of the payment. Must be supplied in ISO 4217 format.
         * This affects all transactions included in the payment;
         * it is not possible to define multiple transactions in different currencies.
         */
        $currency = array_key_exists( 'Currency', $localities ) ? $localities['Currency'] : '';
        $this->input_data['Currency'] = $this->extract_currency( $currency );

        /*
         * Transactions
         * type:PaymentTransaction[]
         * Required
         *
         * An array of payment transactions contained in the payment.
         * A payment must contain at least one such transaction.
         * See the PaymentTransaction page for the appropriate structure and syntax.
         * Defining multiple transactions allow the payment initiator to distribute
         * the payment amount between multiple shops.
         */
        $this->input_data['Transactions'] = $this->extract_payment_transactions( $transactions, $this->input_data['Currency'] );

        /*
         * OrderNumber
         * type:string
         * Optional
         * Values:
         *   Max:100 chars.
         *
         * The order number generated by the shop. This is to aid the shop in identifying
         * a given payment in its own system. This also shows up in generated monthly account
         * statements and transaction history exports, so it also helps with bookkeeping.
         */
        if ( !empty( $order_number ) )
        {
            $this->input_data['OrderNumber'] = $this->sanitize_string( 'OrderNumber',
                                                                       $order_number,
                                                                       'order',
                                                                       self::MAX_LEN_OF_ORDER_NUMBER_C );
        }

        /*
         * PayerPhoneNumber
         * type:string
         * Required for 3DS
         * Values:
         *   Max:30 chars.
         *
         * The mobile phone number of the payer. Must be set to enable payment buttons.
         * The number must be sent in the expected format, without + sign or leading zero(s).
         * Expected format: 36701231234 (where 36 is the country code)
         * Required for using payment buttons
         */
        $this->input_data['PayerPhoneNumber'] = $this->extract_phone_number( $phone_numbers, 'PayerPhoneNumber' );

        /*
         * PayerWorkPhoneNumber
         * type:string
         * Required for 3DS
         * Values:
         *   Max:30 chars.
         *
         * The home phone number of the payer. The number must be sent in the
         * expected format, without + sign or leading zero(s).
         */
        $this->input_data['PayerWorkPhoneNumber'] = $this->extract_phone_number( $phone_numbers, 'PayerWorkPhoneNumber' );

        /*
         * PayerHomeNumber
         * type:string
         * Required for 3DS
         * Values:
         *   Max:30 chars.
         *
         * The home phone number of the payer. The number must be sent in
         * the expected format, without + sign or leading zero(s).
         */
        $this->input_data['PayerHomeNumber'] = $this->extract_phone_number( $phone_numbers, 'PayerHomeNumber' );

        /*
         * BillingAddress
         * type:string
         * Required for 3DS  (Barion Test server required it)
         * Values:
         *   Max:30 chars.
         *
         * The billing address associated with the payment, if applicable.
         */
        $this->input_data['BillingAddress'] = $this->extract_address( $sh_addresses, 'BillingAddress' );

        /*
         * ShippingAddress
         * type:ShippingAddress
         * Required for 3DS   (Barion Test server required it)
         *
         * The shipping address associated with the payment, if applicable.
         * Providing this is recommended because it helps the automatic anti-fraud
         * analysis get more accurate results.
         */
        $this->input_data['ShippingAddress'] = $this->extract_address( $sh_addresses, 'ShippingAddress' );

        /*
         * PayerAccount
         * type:PayerAccountInformation
         * Required for 3DS
         *
         * Information about the account of the payer in the merchant's system.
         */
        $this->input_data['PayerAccount'] = $this->extract_payer_account_information( $payer_account_info );

        /*
         * PurchaseInformation
         * type:PurchaseInformation
         * Required for 3DS
         *
         * Information about current purchase.
         */
        $this->input_data['PurchaseInformation'] = $this->extract_purchase_information( $purchase_info );

        /*
         * ChallengePreference
         * type:ChallengePreference
         * Required for 3DS
         *
         * The merchant's preference of the 3DS challenge.
         * Here you can specify what 3DS authentication should be utilized.
         */
        $this->input_data['ChallengePreference'] = $this->sanitize_challenge_preference( $challenge_pref );

        $this->input_data = array_filter( $this->input_data ); // Remove empty elements. (2020.12.11)
        _log( __FUNCTION__, 'returning, packed input_data='.print_r( $this->input_data, true ) );
        return $this->input_data;
    }

    /*
     * Wrapper method for collect_data_for_transaction.
     * This method analizes and fills the optional data
     * for collect_data_for_transaction.
     *
     * @param transactions - The original transactions param for the wrapped method.
     * @param localities   - The original localities param for the wrapped method.
     * @param other_data   - Any data in an associative array, that might be worth to send as an input.
     */
    public function wr_collect_data_for_transaction( $transactions,
                                                     $localities,
                                                     $other_data = '' )
    {
        $purchase_info = array();      /* Optional */
        $urls = array();               /* Required but has default value. */
        $email_addresses = array();    /* Optional */
        $phone_numbers = array();      /* Optional */
        $payer_account_info = array(); /* Optional */
        $shipping_address = array( 'BillingAddress' => [], 'ShippingAddress' => [] );   /* Barion test server => required */

        $recurrence_value = self::ONE_CLICK_PAYMENT_C;  /* Optional */
        $order_number = '';                                                                /* Optional */
        $challenge_pref  = self::NO_CHALLENGE_NEEDED_C; /* Optional */
        $ch_name = '';                 /* Optional */

        if ( is_array( $other_data ) && !empty( $other_data ) )
        {
            if ( array_key_exists( 'PurchaseInformation', $other_data ) )
            {
                $purchase_info = $other_data['PurchaseInformation'];
            }

            if ( array_key_exists( 'RedirectUrl', $other_data ) )
            {
                $urls['RedirectUrl'] = $other_data['RedirectUrl'];
            }

            if ( array_key_exists( 'CallbackUrl', $other_data ) )
            {
                $urls['CallbackUrl'] = $other_data['CallbackUrl'];
            }

            if ( array_key_exists( 'PayerHint', $other_data ) )
            {
                $email_addresses = $other_data['PayerHint'];
            }

            if ( array_key_exists( 'ShippingAddress', $other_data ) )
            {
                $shipping_address['ShippingAddress'] = $other_data['ShippingAddress'];
            }

            if ( array_key_exists( 'BillingAddress', $other_data ) )
            {
                $shipping_address['BillingAddress'] = $other_data['BillingAddress'];
            }

            if ( array_key_exists( 'PayerPhoneNumber', $other_data ) )
            {
                $phone_numbers['PayerPhoneNumber'] = $other_data['PayerPhoneNumber'];
            }

            if ( array_key_exists( 'PayerWorkPhoneNumber', $other_data ) )
            {
                $phone_numbers['PayerWorkPhoneNumber'] = $other_data['PayerWorkPhoneNumber'];
            }

            if ( array_key_exists( 'PayerAccount', $other_data ) )
            {
                $payer_account_info = $other_data['PayerAccount'];
            }

            if ( array_key_exists( 'RecurrenceType', $other_data ) )
            {
                $recurrence_value = $other_data['RecurrenceType'];
            }

            if ( array_key_exists( 'OrderNumber', $other_data ) )
            {
                $order_number = $other_data['OrderNumber'];
            }

            if ( array_key_exists( 'ChallengePreference', $other_data ) )
            {
                $challenge_pref = $other_data['ChallengePreference'];
            }

            if ( array_key_exists( 'CardHolderNameHint', $other_data ) )
            {
                $ch_name = $other_data['CardHolderNameHint'];
            }
        } /* ~if !empty( other_data ) */

        return $this->collect_data_for_transaction( $transactions,
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
    }

    /*
     * Method to handle barion returned json-data that is initiated from:
     *  '/v2/Payment/Start'
     * If all data is correct, all the 3 variables will be set:
     * - PaymentId : To save the webshop-local database the paymentId as an anchor point.
     * - GatewayUrl : To redirect the user to Barion payment page.
     * - Transactions : Secondary data. (optional)
     *
     * Success-Example for answer from Barion server:
     *  [body] => {
     *   "PaymentId":"046564c49a3ceb118bc4001dd8b71cc4"
     *   "PaymentRequestId":"N.apartman_pf.paymnt._uRIijMwZAK2bCnOFvCzwETyngQfqt4PGaxsHceyXhzK6lDYoe0O5dp1v2mDBY43rJkBIqVJiMNWSPp"
     *   "Status":"Prepared"
     *   "QRUrl":"https://api.test.barion.com/qr/generate?paymentId=046564c4-9a3c-eb11-8bc4-001dd8b71cc4&size=Large"
     *   "Transactions":[
     *      { "POSTransactionId":"EXMPLSHOP-PM-001/TR001"
     *        "TransactionId":"056564c49a3ceb118bc4001dd8b71cc4"
     *        "Status":"Prepared"
     *        "Currency":"HUF"
     *        "TransactionTime":"2020-12-12T16:54:59.24"
     *        "RelatedId":null }
     *   ]
     *   "RecurrenceResult":"None"
     *   "ThreeDSAuthClientData":null
     *   "GatewayUrl":"https://secure.test.barion.com/Pay?Id=046564c49a3ceb118bc4001dd8b71cc4&lang=hu_HU"
     *   "RedirectUrl":"http://example.org/?paymentId=046564c49a3ceb118bc4001dd8b71cc4"
     *   "CallbackUrl":"http://example.org/?paymentId=046564c49a3ceb118bc4001dd8b71cc4"
     *   "TraceId":null
     *   "Errors":[]
     *  }
     *  [response] => Array
     *   (
     *       [code] => 200
     *       [message] => OK
     *   )
     *
     * Error-Example for answer from Barion server:
     *  [body] => {
     *      "Errors":[
     *          { "Title":"Invalid user",
     *            "Description":"Invalid user(customer@test.com)!",
     *            "ErrorCode":"InvalidUser",
     *            "HappenedAt":"2020-12-10T20:14:31.2353001Z",
     *            "AuthData":"hello@nagyapartmansiofok.hu","EndPoint":"https://api.test.barion.com/v2/Payment/Start"}
     *      ]
     *  }
     *  [response] => Array
     *   (
     *       [code] => 400
     *       [message] => Bad Request
     *   )
     *
     * From the Barion documentation:
     * The response should be processed by the webshop system and the Id of the payment (PaymentId)
     * should be extracted from the response. This Id should be stored in the webshop database
     * with the initial information about the payment.
     * This is necessary because every following request is based on this Id.
     *
     * @param response - array, respectively. (see descr.)
     * @param body - json_encoded string, respectively. (see descr. 'PaymentId' will contain the ID for the payment
     *               which should be saved in the database.)
     * @return bool
     *
     */
    public function process_response( array $response, $body )
    {
        _log( __FUNCTION__, 'entering' );
        $body = json_decode( $body, true );  /* json_encoded input. 2020.12.30 */

        $errors    = '';
        $resp_code = '';

        if ( !$this->extract_key_from_array( 'code',
                                             $response,
                                             $resp_code,  /* OUT */
                                            ) )
        {
            _error( __CLASS__.'.'.__FUNCTION__, 'RESPONSE DOES NOT CONTAIN HTML STATUS CODE='.print_r( $response, true ) );
            $this->set_errors( __('Response from Barion server does not contain a valid html response code'), self::RESP__NO_STATUS_CODE );
            return false;
        }

        if ( !$this->extract_key_from_array( 'Errors',
                                             $body,
                                             $errors,  /* OUT */
                                             ) )
        {
            _error( __CLASS__.'.'.__FUNCTION__, 'BODY DOES NOT CONTAIN ERROR CODE='.print_r( $body, true ) );
            $this->set_errors( __('Response from Barion server does not contain a valid Error value.'), self::RESP__NO_ERROR_CODE );
            return false;
        }

        if ( self::HTML_OK == $resp_code && empty( $errors ) )
        {
            /*
             * Extract PaymentId.
             * Proceed with saving to db and
             * redirecting to barion page.
             */
            if ( !$this->extract_key_from_array( 'PaymentId',  /* (2020.12.28) Note: with BIG capital. */
                                                 $body,
                                                 $this->payment_id,  /* OUT */
                                                ) )
            {
                _error( __CLASS__.'.'.__FUNCTION__, 'BODY DOES NOT CONTAIN PAYMENT ID='.print_r( $body, true ) );
                $this->set_errors( __('PaymentId is not present in response array'), self::RESP__NO_PAYMENT_ID );
                return false;
            }

            /*
             * Extract GatewayUrl.
             * Proceed with saving to db and
             * redirecting to barion page.
             */
            if ( !$this->extract_key_from_array( 'GatewayUrl',
                                                 $body,
                                                 $this->gateway_url,  /* OUT */
                                                ) )
            {
                _error( __CLASS__.'.'.__FUNCTION__, 'BODY DOES NOT CONTAIN GATEWAY URL='.print_r( $body, true ) );
                $this->set_errors( __('GatewayUrl is not present in response array'), self::RESP__NO_GATEWAY_URL );
                return false;
            }

            /*
             * Extract Transactions.
             */
            if ( !$this->extract_key_from_array( 'Transactions',
                                                 $body,
                                                 $this->transactions,  /* OUT */
                                                ) )
            {
                _error( __CLASS__.'.'.__FUNCTION__, 'BODY DOES NOT CONTAIN VALID TRANSACTIONS='.print_r( $body, true ) );
                $this->set_errors( __('No transactions are present in response array'), self::RESP__NO_TRANSACTIONS );
                return false;
            }

            /*
             * Extract Status.
             */
            if ( !$this->extract_key_from_array( 'Status',
                                                 $body,
                                                 $this->payment_status,  /* OUT */
                                                ) )
            {
                _error( __CLASS__.'.'.__FUNCTION__, 'BODY DOES NOT CONTAIN VALID STATUS='.print_r( $body, true ) );
                $this->set_errors( __('No status are present in response array'), self::RESP__NO_STATUS );
                return false;
            }

            _log( __FUNCTION__, 'returning true; status="'.$this->payment_status.'", errors='.print_r( $this->output, true ) );
            $this->output[self::OP_MESSAGE] = _('Payment is under way, redirecting...');
            return true;  // All is OK, returning.
        }
        else
        {
            if ( empty( $errors ) )
            {
                $this->output[self::OP_ERROR] = array( self::RESP__HTML_NOT_OK );
            }
            else
            {
                $this->output[self::OP_ERROR] = array( $this->extract_errors_from_response( $errors ) );
            }
            $this->output[self::OP_MESSAGE] = _('Error when communicating with Barion server, response code='.$resp_code);
        }
        return false;
    }

    /**
     * Post the data to barion API
     * hint: https://stackoverflow.com/questions/5647461/how-do-i-send-a-post-request-with-php
     * barion-hint: https://docs.barion.com/Calling_the_API
     *              https://docs.barion.com/Sample-oneclickpayment
     *              https://docs.barion.com/Callback_mechanism
     *
     * payment start API:        [POST]: '/v2/Payment/Start'
     *
     * @param json_data (str): A JSON-encoded string, containing the necessary data to send to barion API.
     * @returns array | false
     *
     *
     * Response->body will contain the json data, among other fields.:
     * [headers] => Requests_Utility_CaseInsensitiveDictionary Object
     * (
     *    [data:protected] => Array
     *    (
     *        [date] => Fri, 27 Nov 2020 08:47:06 GMT
     *        [server] => Apache/2.4.38 (Win64) OpenSSL/1.1.1a PHP/7.3.2
     *        [x-powered-by] => PHP/7.3.2
     *        [content-length] => 63
     *        [content-type] => application/json
     *    )
     *  )
     *  [body] => {"key_1":"value_1","key_2":"value_2","added_key":"added_value"}
     *  [response] => Array
     *  (
     *     [code] => 200
     *     [message] => OK
     *  )
     *  [cookies] => Array
     *  (
     *  )
     *  ...(more fields)
     */
    public function start_barion_payment( $json_data )
    {
        _log( __FUNCTION__, 'entering' ); /* .', json_data='.print_r( $json_data, true ) );*/

        if ( empty( $json_data ) )
        {
            _error( __CLASS__.'.'.__FUNCTION__, 'JSON_DATA IS EMPTY' );
            $this->set_errors( __('Starting the payment failed, because the json-data is empty.'), self::RESP__START_OF_PAYMENT_FAIL_MPTY_DAT );
            return false;
        }

        $response = $this->send_to_remote( self::HTTP_METHOD_POST,
                                           $json_data,
                                           $this->get_api_url().self::API_EP__PAYMENT_START );

        if ( is_wp_error( $response ) ) {
            _error( __CLASS__.'.'.__FUNCTION__, 'ERROR MESSAGE='.$response->get_error_message() );
            $this->set_errors( __('Starting the payment failed=').$response->get_error_message(), self::RESP__START_OF_PAYMENT_FAILED );
            return false;
        }
        return $response;
    }

    /*
     * Helper method to send not even 'post' but 'get'
     * requests as well.
     *
     * An example for GET request:
     * ...
     *  [body] => {
     *   "PaymentId":"046564c49a3ceb118bc4001dd8b71cc4"
     *   "POSId":"0f8af7e1306b4595b9a9f9167ae9639e"
     *   "POSName":"Nagy Apartman Siófok"
     *   "Status":"Expired"
     *   "Errors":[]
     *  }
     *
     * An example for POST request:
     * ...
     *  [body] => {
     *   "PaymentId":"046564c49a3ceb118bc4001dd8b71cc4"
     *   "PaymentRequestId":"N.apartman_pf.paymnt._uRIijMwZAK2bCnOFvCzwETyngQfqt4PGaxsHceyXhzK6lDYoe0O5dp1v2mDBY43rJkBIqVJiMNWSPp"
     *   "Status":"Prepared"
     *   "Errors":[]
     *  }
     *  [response] => Array
     *   (
     *       [code] => 200
     *       [message] => OK
     *   )
     *
     * @param method - the method of the sending. (POST/GET)
     * @param json_data - the data that needs that is already encoded. (TODO!)
     * @param api_endpoint - the api gateway.
     * @return WP_ERROR|response (any; the error handling will be up to the wrapper functions)
     *
     */
    public function send_to_remote( $method, array $json_data, $api_endpoint )
    {
        _log( __FUNCTION__, 'method='.$method.', api_endpoint='.$api_endpoint );
        $response = new \WP_Error();

        if ( empty( $json_data ) )
        {
            _error( __FUNCTION__, 'empty data to send!' );
            $response->add( 'data is empty', 'No data to send!' );
            return $response;
        }

        if ( empty( $api_endpoint ) )
        {
            _error( __FUNCTION__, 'empty endpoint to send to!' );
            $response->add( 'enpoint is empty', 'Empty url address to send data to!' );
            return $response;
        }

        switch ( $method )
        {
            case self::HTTP_METHOD_GET:
            {
                $response = wp_remote_get( $api_endpoint.'/?'.http_build_query( $json_data ) );
                break;
            }

            case self::HTTP_METHOD_POST:
            {
                $headers = array(
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                );
                $options = array(
                    'method'      => $method,
                    'timeout'     => 45,
                    'redirection' => 5,
                    'headers'     => $headers,
                    'body'        => $this->pack_json_data( $json_data ),
                    'cookies'     => array()
                );
                $response = wp_remote_post( $api_endpoint, $options );
                break;
            }

            default:
            {
                _error( __FUNCTION__, 'unknown method='.$method );
                $response->add( 'unknown method', 'Not a valid method='.$method );
                return $response;
            }
        }

        if ( !is_wp_error( $response ) )
        {
            _log( __FUNCTION__, 'json response.headers='.print_r( $response['response'], true) ); /* HTML response in an array */
            //_log( __FUNCTION__, 'json response.body='.$response['body'] );  /* JSON encoded string */
        }
        else
        {
            _log( __FUNCTION__, 'json response='.print_r( $response, true) );
            //_log( __FUNCTION__, 'returning WP_Error obj.' );
            // Returned object will be a WP_Error obj.
        }
        _vdump( $json_data, $response );
        return $response;
    }

    /*
     * Insert the barion pixel basic into the pages head section.
     */
    public function hook_barion_pixel_basic_js() {
        echo
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
            window[\'barion_pixel_id\'] = \''.$this->barion_pixel_id.'\';

            // Send init event
            bp(\'init\', \'addBarionPixelId\', window[\'barion_pixel_id\']);
        </script>

        <noscript>
            <img height="1" width="1" style="display:none" alt="Barion Pixel" src="https://pixel.barion.com/a.gif?ba_pixel_id=\''.$this->barion_pixel_id.'\'&ev=contentView&noscript=1">
        </noscript>
        ';
    }

    /*
     * Validator method for payer_email.
     * Allowed a max len of MAX_LEN_OF_EMAIL_PARAM_C.
     * If invalid, the admin_email is provided in stead of.
     */
    public function sanitize_payer_email( $email )
    {
        if ( !empty( $email ) )
        {
            if ( array_key_exists( 'Payer', $email ) && $this->check_email( $email['Payer'], self::MAX_LEN_OF_EMAIL_PARAM_C ) )
            {
                return $email['Payer'];
            }
            else
            {
                _error( __CLASS__.'.'.__FUNCTION__, "PAYER EMAIL INVALID!" );
                if ( array_key_exists( 'Secondary', $email ) && $this->check_email( $email['Secondary'], self::MAX_LEN_OF_EMAIL_PARAM_C ) )
                {
                    return $email['Secondary'];
                }
                else
                {
                    _log( __FUNCTION__, 'admin email is malformed.' );
                    return '';
                }
            }
        }
        else
        {
            _error( __FUNCTION__, 'PAYER EMAIL IS EMPTY.' );
            return '';
        }
    }

    /*
     * General function to check/sanitize string parameters.
     */
    public function sanitize_string( $param_name, $param, $substitute_value, $maxlen, $minlen = 0 )
    {
        if ( empty( $param ) || ( strlen( $param ) > $maxlen || strlen( $param ) < $minlen ) )
        {
            _log( __FUNCTION__, 'param name= "'.$param_name.'" malformed/empty. value='.$param.', maxlen='.$maxlen );
            return $substitute_value;
        }
        return $param;
    }

    /*
     * Helper method to validate the challenge preference param.
     *
     * Constant values:
     *   NoPreference  0  The merchant does not have any preference about how the 3DS authentication should be used for this payment.
     * In this case Barion decides about the usage of 3DS authentication
     *
     *   ChallengeRequired  10  The customer should be challenged during the payment process for additional security.
     * In this case 3DS authentication will be performed even if the transaction would be eligible for frictionless flow.
     *
     *   NoChallengeNeeded  20  The customer should not be challenged during the payment process. The merchant trusts the customer.
     * In this case 3DS authentication will be performed however Barion will try to achieve the frictionless flow.
     *
     *   Default:
     *   NO_CHALLENGE_NEEDED_C
     */
    public function sanitize_challenge_preference( $challenge_pref )
    {
        $challenge_pref = intval( $challenge_pref );
        if ( $challenge_pref == self::NO_PREFERENCE_C ||
             $challenge_pref == self::CHALLENGE_REQUIRED_C ||
             $challenge_pref == self::NO_CHALLENGE_NEEDED_C )
        {
            return $challenge_pref;
        }
        return self::NO_CHALLENGE_NEEDED_C;
    }

    /*
     * Helper method for converting transactions.
     *
     * This structure represents a payment transaction related to a payment.
     * One payment can contain multiple payment transactions.
     * Multi-dimensional array.
     *
     * https://docs.barion.com/PaymentTransaction
     * One payment transaction structure contains the following data:
     * POSTransactionID  (Required)
     * Payee             (Required)
     * Total             (Required)
     * Comment           (Optional)
     * PayeeTransactions (Optional)
     * Items             (Required)
     *
     * return array.
     */
    public function extract_payment_transactions( array $transactions, $currency )
    {
        /* A payment must contain at least one such transaction. */
        if ( empty( $transactions ) )
        {
            _error( __CLASS__.'.'.__FUNCTION__, 'TRANSACTIONS ARRAY IS EMPTY.' );
            $this->set_errors( __('Transactions array is empty'), self::ERROR__FAILED_TO_PACK_TRANSACTIONS );
            return [];  /* This will indicate failure. */
        }

        $return_data = [];
        if ( !$this->is_assoc($transactions) )
        {
            $cter = count($transactions);   // Fix for infinite loop.
            for ( $i = 0; $i < $cter; $i++ )
            {
                $tran_instance = [];
                _log( __FUNCTION__, 'transactions['.$i.']='.print_r( $transactions[$i], true ) );

                /*
                 * POSTransactionId
                 * type:string
                 * Required
                 *
                 * The unique identifier of the transaction at the shop that started the payment.
                 */
                $tran_instance['POSTransactionId'] = $this->generate_postransaction_id();  /* 2021.01.14 - should be unique! */

                /*
                 * Payee
                 * type:string
                 * Required
                 *
                 * Must be the e-mail address of a valid Barion wallet.
                 */
                if ( !array_key_exists( 'Payee', $transactions[$i] ) )
                {
                    _error( __CLASS__.'.'.__FUNCTION__, 'Payee KEY MISSING' );
                    $transactions[$i]['Payee'] = '';
                    $this->set_errors( __('Payee is missing (#'.$i.')'), self::ERROR__MISSING_PAYEE );
                }
                $tran_instance['Payee'] = $transactions[$i]['Payee'];

                /*
                 * Total
                 * type:decimal
                 * Required
                 *   Value:x<0
                 *
                 * The total amount of the transaction. This is the amount that is charged
                 * towards the payer when completing the payment. The final amount of the transactiom.
                 * This will overwrite the original amount.
                 * The allowed number of decimal digits depends on the currency of the payment containing this transaction:
                 *   CZK: two digits
                 *   EUR: two digits
                 *   HUF: zero digits
                 *   USD: two digits
                 */
                if ( !array_key_exists( 'Total', $transactions[$i] ) )
                {
                    _error( __CLASS__.'.'.__FUNCTION__, 'Total KEY MISSING' );
                    $transactions[$i]['Total'] = 0;
                    $this->set_errors( __('Total is missing (#'.$i.')'), self::ERROR__MISSING_TOTAL );
                }
                // TODO: Figure out what is currency.
                $tran_instance['Total'] = $this->get_total_regarding_currency( $transactions[$i]['Total'], $currency );

                /*
                 * Comment
                 * type:string
                 * Optional
                 *
                 * A comment associated with the transaction. This is NOT shown to the payer.
                 * Description of the transaction, this will overwrite the original description
                 */
                if ( array_key_exists( 'Comment', $transactions[$i] ) )
                {
                    $tran_instance['Comment'] = $transactions[$i]['Comment'];
                }

                /*
                 * PayeeTransactions[]
                 * type:PayeeTransactions
                 * Optional
                 *
                 * An array containing possible sub-transactions, which are executed after
                 * the payment was completed. These are e-money transactions that always take place in the Barion system.
                 */
                if ( array_key_exists( 'PayeeTransactions', $transactions[$i] ) )
                {
                    $tran_instance['PayeeTransactions'] = $this->extract_payee_transactions( $transactions[$i]['PayeeTransactions'] );
                }

                /*
                 * Items
                 * type:Items[]
                 * Required
                 *
                 * An array containing the items (products or services) included in
                 * the transaction. See the Item page for structure and syntax. Items of the transaction.
                 * These will overwrite the original items These are shown to the payer on the Barion Smart Gateway.
                 */
                if ( !array_key_exists( 'Items', $transactions[$i] ) )
                {
                    _error( __CLASS__.'.'.__FUNCTION__, 'Items KEY MISSING' );
                    $transactions[$i]['Items'] = [];
                    $this->set_errors( __('Missing Items (#'.$i.')'), self::ERROR__MISSING_ITEMS );
                }
                $tran_instance['Items'] = $this->extract_items( $transactions[$i]['Items'] );
                $tran_instance = array_filter( $tran_instance ); // Remove empty elements. (2020.12.11)

                $return_data[$i] = $tran_instance;  /* Add instance to array. */
            }
        }
        else
        {
            _log( __FUNCTION__, 'input array is not sequential, but assoc array' );
        }
        return $return_data;
    }

    /*
     * Helper function to extract items[] field.
     * https://docs.barion.com/Item
     * One Item element contains the following data fields:
     *   Name        (Required)
     *   Description (Required)
     *   ImageUrl    (Optional)
     *   Quantity    (Required)
     *   Unit        (Required)
     *   UnitPrice   (Required)
     *   ItemTotal   (Required)
     *   SKU         (Optional)
     */
    public function extract_items( array $items )
    {
        if ( empty( $items ) )
        {
            _error( __CLASS__.'.'.__FUNCTION__, 'ITEMS ARE EMPTY.' );
            $this->set_errors( __('Items are empty'), self::ERROR__EMPTY_ITEMS );
            return [];
        }

        $return_data = [];

        if ( !$this->is_assoc($items) )
        {
            $cter = count( $items );    // Fix for infinite loop
            for ( $i = 0; $i < $cter; $i++ )
            {
                $item_instance = array();
                _log( __FUNCTION__, 'items['.$i.']='.print_r( $items[$i], true ) );

                /*
                 * Name
                 * type:string
                 * Required
                 * Values:
                 *   Max:250 chars.
                 *
                 * The short name of the item. This is shown to the payer on the Barion Smart Gateway.
                 */
                if ( !array_key_exists( 'Name', $items[$i] ) )
                {
                    _error( __CLASS__.'.'.__FUNCTION__, 'Name KEY MISSING' );
                    $this->set_errors( __('Name missing (#'.$i.')'), self::ERROR__MISSING_IT_NAME );
                    $items[$i]['Name'] = 'Name_'.$i;
                }
                $item_instance['Name'] = $this->sanitize_string( 'Name', $items[$i]['Name'], '', self::MAX_LEN_OF_ITEM_NAME_C );

                /*
                 * Description
                 * type:string
                 * Required
                 * Values:
                 *   Max:500 chars.
                 *
                 * The detailed description of the item. This is NOT shown to the payer on the Barion Smart Gateway.
                 */
                if ( !array_key_exists( 'Description', $items[$i] ) )
                {
                    _error( __CLASS__.'.'.__FUNCTION__, 'Description KEY MISSING' );
                    $this->set_errors( __('Description missing (#'.$i.')'), self::ERROR__MISSING_IT_DESCR );
                    $items[$i]['Description'] = 'Description_'.$i;
                }
                $item_instance['Description'] = $this->sanitize_string( 'Description', $items[$i]['Description'], '', self::MAX_LEN_OF_DESCRIPTION_C );

                /*
                 * ImageUrl
                 * type:string
                 * Optional
                 *
                 * A URL pointing to an image that shows the item. This is optional and available for UX purposes only.
                 */
                if ( array_key_exists( 'ImageUrl', $items[$i] ) )
                {
                    $item_instance['ImageUrl'] = $items[$i]['ImageUrl'];
                }

                /*
                 * Quantity
                 * type:decimal
                 * Required
                 * The total quantity of the item.
                 */
                if ( !array_key_exists( 'Quantity', $items[$i] ) )
                {
                    _error( __CLASS__.'.'.__FUNCTION__, 'Quantity KEY MISSING' );
                    $this->set_errors( __('Quantity missing (#'.$i.')'), self::ERROR__MISSING_IT_QTY );
                    $items[$i]['Quantity'] = 0;  /* indicating error */
                }
                $item_instance['Quantity'] = intval( $items[$i]['Quantity'] );

                /*
                 * Unit
                 * type:string
                 * Required
                 * Values:
                 *   Max:50 chars.
                 *
                 * The measurement unit of the item.
                 */
                if ( !array_key_exists( 'Unit', $items[$i] ) )
                {
                    _error( __CLASS__.'.'.__FUNCTION__, 'Unit KEY MISSING' );
                    $this->set_errors( __('Unit missing (#'.$i.')'), self::ERROR__MISSING_IT_UNIT );
                    $items[$i]['Unit'] = 'Unit_'.$i;
                }
                $item_instance['Unit'] = $this->sanitize_string( 'Unit', $items[$i]['Unit'], '', self::MAX_LEN_OF_UNIT_C );

                /*
                 * UnitPrice
                 * type:decimal
                 * Required
                 *
                 * The price of one measurement unit of the item. It can be any
                 * value, even negative if it indicates e.g. discount.
                 */
                if ( !array_key_exists( 'UnitPrice', $items[$i] ) )
                {
                    _error( __CLASS__.'.'.__FUNCTION__, 'UnitPrice KEY MISSING' );
                    $this->set_errors( __('UnitPrice missing (#'.$i.')'), self::ERROR__MISSING_IT_UPRICE );
                    $items[$i]['UnitPrice'] = 0;  /* indicating error */
                }
                $item_instance['UnitPrice'] = floatval( $items[$i]['UnitPrice'] );

                /*
                 * ItemTotal
                 * type:decimal
                 * Required
                 *
                 * The total price of the item. This is not necessarily equals
                 * Quanitity × UnitPrice. Pricing can be determined freely by the shop.
                 */
                if ( !array_key_exists( 'ItemTotal', $items[$i] ) )
                {
                    _error( __CLASS__.'.'.__FUNCTION__, 'ItemTotal KEY MISSING' );
                    $this->set_errors( __('ItemTotal missing (#'.$i.')'), self::ERROR__MISSING_IT_TOTAL );
                    $items[$i]['ItemTotal'] = 0;  /* indicating error */
                }
                $item_instance['ItemTotal'] = floatval( $items[$i]['ItemTotal'] );

                /*
                 * SKU
                 * type:string
                 * Optional
                 * Values:
                 *   Max:100 chars.
                 *
                 * The SKU value of the item in the shop's inventory system.
                 */
                if ( array_key_exists( 'SKU', $items[$i] ) )
                {
                    $item_instance['SKU'] = $this->sanitize_string( 'SKU', $items[$i]['SKU'], 'SKU'.$i, self::MAX_LEN_OF_SKU_C );
                }

                $return_data[$i] = $item_instance;  /* Add instance to array, but remove empty elements. (2020.11.12) */
            }
        }
        else
        {
            _log( __FUNCTION__, 'input array is not sequential, but assoc array' );
        }
        return $return_data;
    }

    /*
     * Helper method for extracting payee transactions structure.
     * https://docs.barion.com/PaymentTransaction
     * Tag is optional. Not Yet implemented.
     */
    public function extract_payee_transactions( $payee_transactions )
    {
        // TODO : Implement!! (optional param)
        // array_filter( $return_elem );  // Remove empty elements.
        return '';
    }

    /*
     * Helper method for extracting shippingAddress structure.
     * https://docs.barion.com/ShippingAddress
     * https://docs.barion.com/BillingAddress
     * The two type structure is nearly identical.
     * This data structure contains the following elements:
     *   Country  (Required)
     *   City     (Optional)
     *   Region   (Optional)
     *   Zip      (Optional)
     *   Street   (Optional)
     *   Street2  (Optional)
     *   Street3  (Optional)
     *   FullName (Optional) - Shipping addr. only.
     */
    public function extract_address( $address, $key )
    {
        _log( __CLASS__.'.'.__FUNCTION__, 'start, key='.$key );
        if ( !array_key_exists( $key, $address ) )
        {
            _log( __CLASS__.'.'.__FUNCTION__, 'input is empty.' );
            return [];
        }

        $return_data = [];
        $address = $address[$key];

        /*
         * Country
         * type:string
         * Required
         * Values:
         *   Max:2 chars.
         *
         * The payer's country code in ISO-3166-1 format.
         * E.g. HU or DE. If you cannot provide the shipping address,
         * send ZZ as the country code.
         */
        if ( array_key_exists( 'Country', $address ) )
        {
            $return_data['Country'] = $this->sanitize_string( 'Country', $address['Country'], 'ZZ', 2 );
        }

        /*
         * Region
         * type:string
         * Optional
         * Values:
         *   Max:2 chars.
         *
         * The country subdivision code (state or county) of the recipient
         * address in ISO-3166-2 format.
         * Examples: https://en.wikipedia.org/wiki/ISO_3166-2:HU
         */
        if ( array_key_exists( 'Region', $address ) )
        {
            $return_data['Region'] = $this->sanitize_string( 'Region', $address['Region'], '', 2 );
        }

        /*
         * City
         * type:string
         * Optional
         * Values:
         *   Max:50 chars.
         *
         * The complete name of the city of the recipient address.
         */
        if ( 0 == strcmp( 'ShippingAddress', $key ) && isset( $return_data['Region'] ) )
        {   /* Required */
            $return_data['City'] = $this->sanitize_string( 'City', $address['City'], 'City', 50 );
        }
        elseif ( array_key_exists( 'City', $address ) )
        {
            $return_data['City'] = $this->sanitize_string( 'City', $address['City'], '', 50 );
        }

        /*
         * Zip
         * type:string
         * Optional
         * Values:
         *   Max:16 chars.
         *
         * The zip code of the recipient address.
         */
        if ( array_key_exists( 'Zip', $address ) )
        {
            $return_data['Zip'] = $this->sanitize_string( 'Zip', $address['Zip'], '', 16 );
        }

        /*
         * Street
         * type:string
         * Optional
         * Values:
         *   Max:50 chars.
         *
         * The shipping street address with house number and other details.
         */
        if ( array_key_exists( 'Street', $address ) )
        {
            $return_data['Street'] = $this->sanitize_string( 'Street', $address['Street'], '', 50 );
        }

        /*
         * Street2
         * type:string
         * Optional
         * Values:
         *   Max:50 chars.
         *
         * The address, continued.
         */
        if ( array_key_exists( 'Street2', $address ) )
        {
            $return_data['Street2'] = $this->sanitize_string( 'Street2', $address['Street2'], '', 50 );
        }

        /*
         * Street3
         * type:string
         * Optional
         * Values:
         *   Max:50 chars.
         *
         * The address, continued.
         */
        if ( array_key_exists( 'Street3', $address ) )
        {
            $return_data['Street3'] = $this->sanitize_string( 'Street3', $address['Street3'], '', 50 );
        }

        /*
         * FullName
         * type:string
         * Optional
         * Values:
         *   Max:45 chars.
         *
         * The name of the customer on the shipping address.
         * Only for 'ShippingAddress'.
         */
        if ( 0 == strcmp( 'ShippingAddress', $key ) && array_key_exists( 'FullName', $address ) )
        {
            $return_data['FullName'] = $this->sanitize_string( 'FullName', $address['FullName'], '', 45 );
        }

        _log( __CLASS__.'.'.__FUNCTION__, 'return data='.print_r( $return_data, true ) );
        return array_filter( $return_data );  // Remove empty elements  (2020.12.11)
    }

    /*
     * Helper method to extract payer-account-information param.
     * https://docs.barion.com/PayerAccountInformation
     * This data structure contains the following elements:
     *   AccountId                      (Optional)
     *   AccountCreated                 (Optional)
     *   AccountCreationIndicator       (Optional)
     *   AccountLastChanged             (Optional)
     *   AccountChangeIndicator         (Optional)
     *   PasswordLastChanged            (Optional)
     *   PasswordChangeIndicator        (Optional)
     *   PuschasesInTheLast6Months      (Optional)
     *   ShippingAddressAdded           (Optional)
     *   ShippingAddressUsageIndicator  (Optional)
     *   ProvisionAttempts              (Optional)
     *   TransactionalActivityPerDay    (Optional)
     *   TransactionalActivityPerYear   (Optional)
     *   PaymentMethodAdded             (Optional)
     *   SuspiciousActicityIndicator    (Optional)
     */
    public function extract_payer_account_information( $payer_account_info )
    {
        // TODO : Implement!! (optional param)
        // array_filter( $return_elem ); // Remove empty elements. (2020.12.11)
        return '';
    }

    /*
     * Helper method to extract purchase-information param.
     * https://docs.barion.com/PurchaseInformation
     * This data structure contains the following elements:
     *   DeliveryTimeframe          (Optional)
     *   DeliveryEmailAddress       (Optional)
     *   PreOrderDate               (Optional)
     *   AvailabilityIndicator      (Optional)
     *   ReOrderIndicator           (Optional)
     *   ShippingAddressIndicator   (Optional)
     *   RecurringExpiry            (Optional)
     *   RecurringFrequency         (Optional)
     *   PurchaseType               (Optional)
     *   GiftCardPurchase           (Optional)
     *   PurchaseDate               (Optional)
     */
    public function extract_purchase_information( $purchase_info )
    {
        // TODO : Implement!! (optional param)
        // array_filter( $return_elem ); // Remove empty elements. (2020.12.11)
        return '';
    }

    /*
     * Helper method for converting recurrence type tag.
     * https://docs.barion.com/RecurrenceType
     * The value can be one of the following int constants:
     *   OneClickPayment  - 10
     *   MerchantInitiatedPayment  - 0
     *   RecurringPayment  - 20
     *
     *   Default:
     *   ONE_CLICK_PAYMENT_C
     */
    public function extract_recurrence( $recurrence_value )
    {
        $recurrence_value = intval( $recurrence_value );
        if ( $recurrence_value == self::ONE_CLICK_PAYMENT_C ||
             $recurrence_value == self::MERCHANT_INITIATED_PAYMENT_C ||
             $recurrence_value == self::RECURRING_PAYMENT_C )
        {
            return $recurrence_value;
        }
        return self::ONE_CLICK_PAYMENT_C;
    }

    /*
     * Helper method to extract the 'locale' parameter.
     * Accepted values:
     *   'cs-CZ' (Czech)
     *   'de-DE' (German)
     *   'en-US' (English)
     *   'es-ES' (Spanish)
     *   'fr-FR' (French)
     *   'hu-HU' (Hungarian)
     *   'sk-SK' (Slovak)
     *   'sl-SI' (Slovenian)
     *
     *   Default:
     *   'hu-HU'
     */
    public function extract_locale( $locale )
    {
        if ( 0 == strcmp( $locale, self::LOCALE_CZ_C ) ||
             0 == strcmp( $locale, self::LOCALE_DE_C ) ||
             0 == strcmp( $locale, self::LOCALE_EN_C ) ||
             0 == strcmp( $locale, self::LOCALE_ES_C ) ||
             0 == strcmp( $locale, self::LOCALE_FR_C ) ||
             0 == strcmp( $locale, self::LOCALE_HU_C ) ||
             0 == strcmp( $locale, self::LOCALE_SK_C ) ||
             0 == strcmp( $locale, self::LOCALE_SI_C ) )
        {
            return $locale;
        }
        else
        {
            return self::LOCALE_HU_C;
        }
    }

    /*
     * Helper method to extract the 'currency' parameter.
     * Accepted values:
     *   'CZK' (Czech crown)
     *   'EUR' (Euro)
     *   'HUF' (Hungarian forint)
     *   'USD' (U.S. dollar)
     *
     *   Default:
     *   'HUF'
     */
    public function extract_currency( $currency )
    {
        if ( 0 == strcmp( $currency, self::CURRENCY_OF_CZK_C ) ||
             0 == strcmp( $currency, self::CURRENCY_OF_EUR_C ) ||
             0 == strcmp( $currency, self::CURRENCY_OF_HUF_C ) ||
             0 == strcmp( $currency, self::CURRENCY_OF_USD_C ) )
        {
            return $currency;
        }
        else
        {
            return self::CURRENCY_OF_HUF_C;
        }
    }

    /*
     * Helper method to extract phone-number param.
     * Expected format: 36701231234 (where 36 is the country code)
     *
     * @param array - the input array that contains the phone number.
     * @param key - the key that points to the phone number.
     * @return '' | phone_number.
     */
    public function extract_phone_number( $array, $key )
    {
        if ( !array_key_exists( $key, $array ) )
        {
            _log( __FUNCTION__, 'key {'.$key.'} not exists in input array' );
            return '';
        }
        $matches = array();
        $result = preg_match("/[0-9]{1,".self::MAX_LEN_FOR_PHONE_NUMBERS_C."}/", $array[$key], $matches);
        if ( $result )
        {
            _log( __FUNCTION__, 'matches='.print_r( $matches, true ) );
            return $matches[0];
        }
        else
        {
            _log( __FUNCTION__, 'no result in phone number input to interpret as valid phone number.' );
            return false;
        }
    }

    /*
     * Helper method to extract the
     * 'PaymentId',
     * and the 'GatewayUrl' params
     * from the json response.
     *
     * (Also used at extracting other params from the response.)
     *
     * @param key - the key to look for in the input array
     * @param array - the input array that probably hold the key-value.
     * @param output - the output to save the searched value in.
     * @return bool
     */
    public function extract_key_from_array( $key, array $array, &$output )
    {
        if ( array_key_exists( $key, $array ) )
        {
            $output = $array[$key];
            return true;
        }
        else
        {
            _log( __FUNCTION__, 'key='.$key.' in array does not exists.' );
            $output = '';
            return false;
        }
    }

    /*
     * Helper method to deal with the error logic.
     * A tipical error may contain the following pattern:
     * NOTE: error param can contain several errors!!
     *
     * 2020.12.15.: TODO!
     * An imaginary function would be to return some error msg,
     * and redirect the wp user to another default page.
     *
     * https://docs.barion.com/Responsive_web_payment#Possible_error_responses
     *
     * "Errors":[
     *     { "Title":"Invalid user",
     *       "Description":"Invalid user(customer@test.com)!",
     *       "ErrorCode":"InvalidUser",
     *       "HappenedAt":"2020-12-10T20:14:31.2353001Z",
     *       "AuthData":"hello@nagyapartmansiofok.hu","EndPoint":"https://api.test.barion.com/v2/Payment/Start" }
     *  ]
     */
    public function extract_errors_from_response( $error )
    {
        // Todo : NO functionality for now.. improvement opportunity.
        return is_array( $error ) ? $error : array( $error );
    }

    public function get_api_url()
    {
        if ( WP_BARION_SANDBOX )
        {
            if ( WP_BARION_FAKE_LOCALHOST_SERVER )
            {   // Localhost test
                return LOCALHOST_FAKE_BARION_SERVER;
            }
            else
            {
                return BARION_SANDBOX_API_URL;
            }
        }
        else
        {
            return BARION_LIVE_API_URL;
        }
    }

    /**
     * Constructs and encodes necessary data into a json formatted string.
     *
     * @param data (array): the data to convert into necessary format.
     * @return (str): encoded string.
     */
    public function pack_json_data( array $data )
    {
        return json_encode( $data );
    }

    /*
     * Helper function for formatting total, depending on currency.
     * Currency values:
     *   CZK: two digits
     *   EUR: two digits
     *   HUF: zero digits
     *   USD: two digits
     */
    public function get_total_regarding_currency( $value, $currency )
    {
        if ( 0 == strcmp( $currency, self::CURRENCY_OF_HUF_C ) )
        {
            return strval(round($value,0));
        }
        else
        {
            $val = explode( '.', $value );
            if ( 1 >= strlen( $val[count($val)-1] ) )
            {   // If from the 'xx.yy' part, the yy part is no longer than 1.
                return $value;
            }
            else
            {
                return strval(round($value,2));
            }
        }
        return 0;
    }

    /*
     * Generate recurrence id token.
     * Max len = 100.
     */
    public function generate_recurrence_id()
    {
        $length = self::MAX_LEN_OF_RECURRENCE_ID_C;
        $prefix = 'N.apartman_pf.recurr._';
        return $this->generate_id( $prefix, $length );
    }

    /*
     * Generate payment id.
     * Max len = 100.
     */
    public function generate_payment_id()
    {
        $length = self::MAX_LEN_OF_PAYMENT_ID_C;
        $prefix = 'N_apartm__';
        return $this->generate_id( $prefix, $length );
    }

    /*
     * Generate postransaction id.
     * Max len = 50 (Arbitrary).
     */
    public function generate_postransaction_id()
    {
        $length = self::MAX_LEN_OF_POSTRANSACTION_ID_C;
        $prefix = 'N_trans__';
        return $this->generate_id( $prefix, $length );
    }

    /*
     * Save PaymentId in the {tbl_name}, among with secondary datas.
     * Data:
     *   wp_user_id: to tie the payment to a specific user,
     *   paymentId: to identify and anchor the transaction,
     *   created: timestamp about the transaction,
     *   status: status of the current payment. (can be updated)
     *   updated: cannot modify from here, this will hold the same
     *            as the 'created' field. (update will modify it)
     *
     * @param wp_user_id - See above.
     * @param payment_id - (Optional) The payment id.
     * @param payment_status - (Optional) The payment status.
     * @param date - (Optional) The payment date.
     * @param payment_request_id - (Optional) The payment_request_id.
     * @return the affected rows by the sql query.
     *
     * 2021.01.01: Added 'updated' field.
     *
     */
    public function save_payment_data_in_db( $wp_user_id, $payment_id = '', $payment_status = '', $date = '', $payment_request_id = '' )
    {
        _log( __FUNCTION__, 'Entered, with input data: user_id="'.$wp_user_id.
                            '", payment_id="'.$payment_id.'", status="'.$payment_status.'", date="'.$date.'"' );
        $payment_id = empty( $payment_id ) ? $this->payment_id : $payment_id;
        $payment_status = empty( $payment_status ) ? $this->payment_status : $payment_status;
        $date = empty( $date ) ? date('Y-m-d H:i:s') : $date;
        $payment_request_id = empty( $payment_request_id ) ? $this->payment_request_id : $payment_request_id;

        $tbl_name = $this->globalwpdb->prefix.$this->tbl_name;
        $fields = array(
            'payment_request_id' => $payment_request_id,
            'userID'             => $wp_user_id,
            self::PAYMENT_ID_KEY => $payment_id,
            'created'            => $date,
            'status'             => $payment_status,
            'updated'            => $date,
        );
        $formats = array( '%s', /* payment_request_id  */
                          '%d', /* wp_user_id */
                          '%s', /* paymentId  */
                          '%s', /* created    */
                          '%s', /* status     */
                          '%s', /* updated    */
        );
        $return_value_of_insert = $this->globalwpdb->insert( $tbl_name,
                                                             $fields,
                                                             $formats );
        _log( __FUNCTION__, 'last query='.$this->globalwpdb->last_query.', affected rows='.$return_value_of_insert );
        return $return_value_of_insert;
    }

    /*
     * Select the last payment data from the database, for a given user.
     * The SQL query returns only the last record for the user.
     *
     * 2021.01.01: Added 'updated' field.
     *
     * @param user_id - The ID of the user (WP)
     * @return assoc_array|false - The payment ID alongside with the payment data.
     *
     * -=-=--=-=--=-=--=-=--=-=--=-=--=-=-
     * 2021.01.22.:
     * NOTE: This method is now DEPRECATED as of it is allowed to save records and
     * make payments with {user id} = 0
     * -=-=--=-=--=-=--=-=--=-=--=-=--=-=-
     */
    public function get_payment_data_from_db( $user_id )
    {
        $tbl_name = $this->globalwpdb->prefix.$this->tbl_name;
        $query = 'SELECT '.
                   'payment_request_id,'.
                   'userID,'.
                   'paymentId,'.
                   'created,'.
                   'status,'.
                   'updated,'.
                   'callback_message '.
                 'FROM `'.$tbl_name.'` '.
                    'WHERE userID='.(intval($user_id)).' '.
                    'ORDER BY created DESC LIMIT 1';
        $ret = $this->globalwpdb->get_results( $query );
        _log( __FUNCTION__, 'query is='.$query."\n result=".print_r( $ret, true ) );
        return empty( $ret ) ? false : json_decode( json_encode( $ret ), true )[0];
    }

    /*
     * Select the payment data from the database, by a payment id.
     *
     * @param payment_id - The ID of the payment
     * @return assoc_array|false - The payment ID alongside with the payment data.
     */
    public function get_payment_data_from_db_by_pid( $payment_id )
    {
        if ( empty( $payment_id ) )
        {
            _log( __FUNCTION__, 'empty payment id' );
            return false;
        }
        $tbl_name = $this->globalwpdb->prefix.$this->tbl_name;
        $query = 'SELECT '.
                   'payment_request_id,'.
                   'userID,'.
                   'paymentId,'.
                   'created,'.
                   'status,'.
                   'updated,'.
                   'callback_message '.
                 'FROM `'.$tbl_name.'` '.
                    'WHERE paymentId=\''.$payment_id .'\'';
        $ret = $this->globalwpdb->get_results( $query );
        _log( __FUNCTION__, 'query is='.$query."\n result=".print_r( $ret, true ) );
        return empty( $ret ) ? false : json_decode( json_encode( $ret ), true )[0];
    }

    /*
     * Method to update the status of a given payment id
     * after a successful Barion transaction.
     *
     * @param user_id - the userID field in the db.
     * @param payment_id - the paymentId field in the db.
     * @param status - the updated status field in the db.
     * @return false|affected rows (int)
     */
    public function update_payment_status_in_db( $user_id, $payment_id, $status, $updated = '' )
    {
        $tbl_name = $this->globalwpdb->prefix.$this->tbl_name;
        $where = array(
            self::PAYMENT_ID_KEY => $payment_id,
            'userID'             => $user_id
        );
        $updated = empty( $updated ) ? date('Y-m-d H:i:s') : $updated;
        $data = array(
            'status'   => $status,
            'updated'  => $updated
        );
        $return_value_of_update = $this->globalwpdb->update( $tbl_name,
                                                             $data,
                                                             $where );
        _log( __FUNCTION__, 'last query='.$this->globalwpdb->last_query.', affected rows='.$return_value_of_update );
        return ( $return_value_of_update ) ? $return_value_of_update : false;
    }

     /*
     * Method to query the status change in a transaction.
     * Data that is needed:
     * - POSkey
     * - paymentId from the latest transaction.
     *
     * Barion API endpoint to communicate with: https://docs.barion.com/Payment-GetPaymentState-v2
     * payment status check API: [GET]:  '/v2/Payment/GetPaymentState'
     *
     * Input parameters:
     *  POSKey [Guid]:
     *    (Required)
     *    The secret API key of the shop, generated by Barion.
     *    This lets the shop to authenticate through the Barion API,
     *    but does not provide access to the account owning the shop itself.
     *  PaymentId [Guid]:
     *    (Required)
     *    The identifier of the payment in the Barion system.
     *
     * Output parameters:  Please see: https://docs.barion.com/Payment-GetPaymentState-v2
     * The most important output parameter is the 'Status' param.
     *
     * PaymentStatuses can be one of the following:
     *   Prepared             10   The payment is prepared.
     *                             This means it can be completed unless the payment time window expires.
     *   Started              20   The payment process has been started.
     *                             This means the payer started the execution of the payment with a funding source.
     *   InProgress           21   The payment process is currently in progress.
     *                             This means that the communication between Barion and
     *                             the bank card processing system is currently taking place.
     *                             No alterations can be made to the payment in this status.
     *   Waiting              22   The payment was paid with bank transfer and the result of
     *                             the bank transfer is not known yet. Used in Payment_Buttons scenarios.
     *   Reserved             25   The payment was completed by the payer, but the amount is
     *                             still reserved. This means that the payment should be finished
     *                             (finalized) unless the reservation period expires.
     *   Authorized           26   The payment was completed by the payer, but the amount is
     *                             not charged yet on the bankcard. The payment must be finished
     *                             before the authorization period expires.
     *   Canceled             30   The payment has been explicitly cancelled (rejected) by the payer.
     *                             This is a final status, the payment can no longer be completed.
     *   Succeeded            40   The payment has been fully completed. This is a final status,
     *                             the payment can no longer be altered.
     *   Failed               50   The payment has failed because of unknown reasons.
     *                             Used in payment scenarios that were paid with bank transfer.
     *   PartiallySucceeded   60   This can occur if a complex reservation payment contains multiple
     *                             transactions, and only some of them are finished. If all transactions
     *                             are finished, the payment status will change to Succeeded.
     *   Expired              70   The payment was expired. This can occur due to numerous reasons:
     *      - The payment time window has passed and the payer did not complete the payment.
     *      - A reserved payment was not finished during the reservation period. In this case, the money is refunded to the payer.
     *      - This is a final status, the payment can no longer be completed.
     *
     * @param payment_id - if not given, the inner instance id will be used,
     * @return false|resource (json-encoded 'body' key in resource)
     */
    public function get_payment_state_from_remote( $payment_id )
    {
        // Send a GET request to the barion API with
        // the given paymentId to get the latest status of the payment.
        // This is triggered via the callback mechanism. (post request to this webshop website)
        _log( __FUNCTION__, 'payment_id given="'.$payment_id.'", own payment_id="'.$this->payment_id.'"' );

        /*
         * We have the paymentId and the POSkey, so lets send it to the remote Barion
         * API to retrieve the results.
         *
         * Note: If the status for this paymentId is "Success" or "Finished", there is
         * no need to re-inspect this query. ( 2020.12.29 )
         */
        $json_data = array(
            'POSkey'    => $this->barion_pos_key,
            'PaymentId' => $payment_id
        );
        $response = $this->send_to_remote( self::HTTP_METHOD_GET,
                                           $json_data,
                                           $this->get_api_url().self::API_EP__GET_PAYMENT_INFO );

        if ( is_wp_error( $response ) ) {
            _error( __CLASS__.'.'.__FUNCTION__, 'ERROR MESSAGE='.$response->get_error_message() );
            $this->set_errors( __('Retrieving PaymentId failed=').$response->get_error_message(), self::RESP__PAYMENT_ID_RETRIEVAL_FAIL );
           return false;
        }
        /*
         * Note: 'response[body.Status]' key will hold the data, needed.
         */
        return $response;
    }

    /*
     * Helper method to store and retrieve user message
     * when the barion API request an update for a specific payment.
     *
     * UPDATE: 2021.01.21:
     * This method changes due the fact that even not logged in users
     * can submit a payment. user_id -> payment_id.
     * and a new field in the barion_payment table.
     *
     * NOTE! At this point, this record SHOULD BE ALWAYS PRESENT with
     *       a given paymentId, so the update function is more than
     *       enough. No insert will be needed.
     *
     * @param user_id - The user id of the transaction
     * @param message - The message to store for revisiting the page.
     * @return false|string.
     */
    public function manage_user_callback_message( $payment_id, $message = '' )
    {
        _log( __FUNCTION__, 'enter, with payment_id="'.$payment_id.'", message="'.$message.'"' );
        $tbl_name = $this->globalwpdb->prefix.$this->tbl_name;
        if ( empty( $message ) )
        {   // Retrieve & delete
            $query = 'SELECT '.
                       'callback_message '.
                     'FROM `'.$tbl_name.'` '.
                        'WHERE paymentId=\''.($payment_id).'\' '.
                        'AND callback_message <> \'-\' '.
                        'ORDER BY created DESC LIMIT 1';
            $ret = $this->globalwpdb->get_results( $query );
            _log( __FUNCTION__, 'query is='.$query."\n result=".print_r( $ret, true ) );
            if ( empty( $ret ) )
            {
                return false;
            }
            else
            {
                $this->update_user_callback_text( $payment_id, '-' );   // Delete for next time.
                return json_decode( json_encode( $ret ), true )[0]['callback_message'];
            }
        }
        else
        {   // Store
            $ret = $this->update_user_callback_text( $payment_id, $message );
            return empty( $ret ) ? false : $ret;
        }
    }

    /*
     * Helper method to update value.
     */
    public function update_user_callback_text( $payment_id, $message )
    {
        _log( __FUNCTION__, 'entered with paymentId='.$payment_id );
        $tbl_name = $this->globalwpdb->prefix.$this->tbl_name;
        $where = array(
            self::PAYMENT_ID_KEY => $payment_id
        );
        $data = array(
            'callback_message'   => $message
        );
        $return_value_of_update = $this->globalwpdb->update( $tbl_name,
                                                             $data,
                                                             $where,
                                                             array( '%s' ) );
        _log( __FUNCTION__, 'last query='.$this->globalwpdb->last_query.', affected rows='.$return_value_of_update );
        return ( $return_value_of_update ) ? $return_value_of_update : false;
    }

    /*
     * Method that will spit out a message for the user from the
     * Barion API status codes, regarding the getPaymentStatus API call
     *
     * @param status - the status message from Barion API.
     * @return string - a user-friendly-consumable text.
     */
    public function translate_from_status( $status )
    {
        switch( $status )
        {
            case self::STATUS_EXP:
            {
                return 'Utolsó fizetés lejárt.';
                break;
            }
            case self:: STATUS_PREP:
            {
                return 'Barion fizetés előkészítve';
                break;
            }
            case self:: STATUS_STARTED:
            {
                return 'Barion fizetés indítva';
                break;
            }
            case self:: STATUS_IPROGRESS:
            {
                return 'Barion fizetés folyamatban';
                break;
            }
            case self:: STATUS_WAITING:
            {
                return 'Barion fizetés várakozik';
                break;
            }
            case self:: STATUS_RESERVED:
            {
                return 'Barion fizetés lefoglalva';
                break;
            }
            case self:: STATUS_AUTH:
            {
                return 'Barion fizetés hitelesítve';
                break;
            }
            case self:: STATUS_CANCELED:
            {
                return 'Barion fizetés megszakítva';
                break;
            }
            case self:: STATUS_SUCCESS:
            {
                return 'Barion fizetés sikeres';
                break;
            }
            case self:: STATUS_FAILED:
            {
                return 'Barion fizetés sikertelen';
                break;
            }
            case self:: STATUS_PSUCC:
            {
                return 'Barion fizetés részlegesen sikerült';
                break;
            }
            default:
            {
                return 'Nincs értesítő üzenet.(default)';
                break;
            }
        }
    }

    /*
     * Helper method to determine
     * if it is an associative array.
     */
    protected function is_assoc( array $arr ) {
        if (array() === $arr) return false;
        return array_keys($arr) !== range( 0, count($arr) - 1 );
    }

    /*
     * Construct a shuffled, unique id, that is prefixed a with given param.
     * @param prefix - the first part of the string.
     * @param length - the length of the constructed string.
     */
    private function generate_id( $prefix, $length )
    {
        if ( strlen( $prefix ) >= $length )
        {
            _error( __CLASS__.'.'.__FUNCTION__, 'PREFIX TOO LONG!  PREFIX='.$prefix.', LENGTH='.$length );
            return '';
        }
        $length = $length - strlen( $prefix );
        return $prefix . substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
    }

    /*
     * Dumb simple email check function.
     * @param email - The email to check.
     * @len - The available max length of the email addr.
     * @return true|false
     */
    private function check_email( $email, $len )
    {
        if ( strlen( $email ) > $len ||
             empty( $email )         ||
             !strstr( $email, '@' )  ||
             !strstr( $email, '.' ) )
        {
            _error( __CLASS__.'.'.__FUNCTION__, 'INVALID PARAMS.  LEN='.$len.', EMAIL='.$email );
            return false;
        }
        else
        {
            return true;
        }
    }

    /*
     * Choose the wpdb instance on test and on production mode.
     */
    private function choose_wpdb()
    {
        global $wpdb;
        if ( WP_BARION_TESTING_ON_LOCALHOST )
        {   // Test
            $this->globalwpdb = new \wpdb( 'wordpress_sandbox',            /* username */
                                           'wordpress_sandbox',            /* password */
                                           'wordpress_nagyapartman_test',  /* database */
                                           'localhost' );                  /* host     */
            $this->globalwpdb->prefix = 'wptests_';
            $this->globalwpdb->show_errors(true);
            $this->create_table();
        }
        else
        {
            $this->globalwpdb = $wpdb;
            $this->globalwpdb->show_errors(false);
            //$this->create_table();
        }
    }

    /*
     * Creates the barion payment tables.
     *
     * Although the paymentId could be a primary key,
     * the primary key on the table will be the payment_request_id for the start
     * of every payment.
     *
     * 2021.01.01: Added 'updated' field.
     */
    private function create_table()
    {
        $charset_collate = $this->globalwpdb->get_charset_collate();
        $query_str =
            "CREATE TABLE IF NOT EXISTS `".( $this->globalwpdb->prefix.$this->tbl_name )."` (".
              "`payment_request_id` varchar(100) NOT NULL PRIMARY KEY,".
              "`userID` int(6) NOT NULL DEFAULT -1,".
              "`".self::PAYMENT_ID_KEY."` varchar(50) NOT NULL DEFAULT '',".
              "`created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',".
              "`status` varchar(15) NOT NULL DEFAULT '',".
              "`updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',".
              "`callback_message` varchar(30) DEFAULT '-'".
            ") ".$charset_collate.";";
        $result = $this->globalwpdb->query( $query_str );
        _log( __FUNCTION__, 'last query='.$this->globalwpdb->last_query.', affected rows='.$result );
        return $result;
    }

} /* ~Nagyapartman_Barion */


require_once( ABSPATH . "wp-content/plugins/nagyapartman-barion/FeniX_Logger.class.php");
$Nagyapartman_Barion = new Nagyapartman_Barion();
register_activation_hook( __FILE__, array(&$Nagyapartman_Barion, 'register_activation_hook') );
register_deactivation_hook( __FILE__, array(&$Nagyapartman_Barion, 'register_deactivation_hook') );
register_uninstall_hook( __FILE__, array(&$Nagyapartman_Barion, 'register_uninstall_hook') );

/* ~namespace Nagyapartman_Barion */

