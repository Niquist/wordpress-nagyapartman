<?php
namespace Nagyapartman_Logger;


define( 'FENIX_WP_DEBUG', true ); // This should be turned off, to silence logs.
class FeniX_Logger {

    private static $date;

    /* Save the log output to a file, with timestamp of every 5 seconds.
     *
     * @param $stuff      - The string to output in the file.
     * @param $fileAppend - Whether to append or simply write to the file.
     * @param $log_type   - Is it a 'log' or a 'notice' type.
     */
    public static function _fecho( $stuff, $fileAppend = "a", $log_type = "log" ) {
        $path = ABSPATH . "wp-content/plugins/nagyapartman-barion/plugin-logs/";
        $path .= ( $log_type == "notice" ) ? "notice" : "log";
        $minutes_rounded_up = self::displayMinutes( date( 'i' ) ); /* only the minutes */
        $path .= date( '_Ymd-H' ).$minutes_rounded_up.".txt";
        $myfile = fopen( $path, $fileAppend ) or die( "Unable to open file!" );
        fwrite( $myfile, self::get_log_time().":".$stuff."\n" );
        fclose( $myfile );
    }

    /*
     * Helper method to dump the barion-communication responses.
     */
    public static function _fdump( $json_data, $response ) {
        $path = ABSPATH . "wp-content/plugins/nagyapartman-barion/plugin-logs/DUMP_";
        $minutes_rounded_up = self::displayMinutes( date( 'i' ) ); /* only the minutes */
        $path .= date( '_Ymd-H' ).$minutes_rounded_up.".txt";
        $myfile = fopen( $path, 'a' ) or die( "Unable to open file!" );
        fwrite( $myfile, self::get_log_time()."\nJSON_DATA:\n\n".print_r($json_data,true)."\n" );
        fwrite( $myfile, self::get_log_time()."\n\n:RESPONSE:\n\n".print_r($response,true)."\n" );
        fclose( $myfile );
    }

    /* Get the time in milliseconds. */
    private static function get_log_time( $short_format = false ) {
        $t = microtime( true );
        $micro = sprintf( "%06d",( $t - floor( $t ) ) * 1000000 );
        $d = new \DateTime( date( 'Y-m-d H:i:s.'.$micro, $t ) );
        if ( !$short_format ) {
            return $d->format( "Y-m-d H:i:s.u" );
        }
        else {
            return $d->format( "H:i:s.u" );
        }
        return $d->format( "Y-m-d H:i:s.u" );
    }

    private static function displayMinutes( $number, $timeDelay = 5 ) {
        $num = ceil( $number / $timeDelay ) * $timeDelay;
        if ( $num < 10 ) {
            return "0".$num;
        }
        else {
            return $num;
        }
    }
} /* ~FeniX_Logger */

function _log( $caller, $str )
{
    _l( '[ LOG   ]: '.$caller.'  :  '. $str );
}

function _error( $caller, $str )
{
    _l( '[ ERROR ]: '.$caller.'  :  '. $str );
}

function _l( $str )
{
    if ( FENIX_WP_DEBUG ) {
        FeniX_Logger::_fecho( $str );
    }
}

function _vdump( $json_data, $response )
{
    FeniX_Logger::_fdump( $json_data, $response );
}

//FeniX_Logger::_fecho('I\'m alive');

/* ~namespace Nagyapartman_Logger; */
