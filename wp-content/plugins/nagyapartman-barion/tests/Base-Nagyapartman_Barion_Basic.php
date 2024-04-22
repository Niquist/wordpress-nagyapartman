<?php
/**
 * Class SampleTest
 *
 * @package Nagyapartman_Barion
 */

/*
 * Basic testcase for all tests.
 */
class Nagyapartman_Barion_Basic extends WP_UnitTestCase {

    protected $instance;

    public function __construct() {
        parent::__construct(); /* One must call! */
    }

    /**
     * Call protected/private method of a class.
     * from:https://jtreminio.com/blog/unit-testing-tutorial-part-iii-testing-protected-private-methods-coverage-reports-and-crap/
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = array()) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    protected function resetInstance() {
        $this->instance = null;
        $this->instance = new Nagyapartman_Barion\Nagyapartman_Barion();
    }

    /**
     * From:
     https://www.codewars.com/kumite/57305deebd9f09a690000a35?sel=57305deebd9f09a690000a35
     * Determine if two arrays are similar
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering
     *
     * @param array $base_array - The array that is the base for comparison
     * @param array $compared_array - The compared array
     * @param string $test_name - The name for the log write
     * @return bool
     */
    protected function arrays_are_similar( $expected, $compared, $test_name, $key = '' /* Needed for recursion only! */ ) {
        if ( !is_array( $expected ) || !is_array( $compared ) ) {
            //if ( $compared === $expected ) { // type checking omitted.
            if ( $compared == $expected ) {
                return true;
            }
            else {
                $this->wLog( __FUNCTION__.' in test:['.$test_name.'], value for ["'.$key.'"] is not equal. expected: "'.
                            (is_array($expected) ? '[ARRAY()]' : $expected).'", compared: "'.
                            (is_array($compared) ? '[ARRAY()]' : $compared).'".' );
                /*
                $this->wLog( __FUNCTION__.' in test:['.$test_name.'], value for ["'.$key.'"] is not equal. '.
                            'expected('.gettype($expected).'): "'.$expected.'", compared('.gettype($compared).'): "'.$compared.'".' );
                            */
                return false;
            }
        }

        /* expected to compared */
        if ( $this->is_assoc( $expected ) ) {
            foreach ( $expected as $key => $value ) {
                if ( !array_key_exists( $key, $compared ) ) {
                    $this->wLog( __FUNCTION__.' in test:['.$test_name.'], key "'.$key.'" not exist in compared array.' );
                    return false;
                }

                if ( !$this->arrays_are_similar( $compared[$key], $expected[$key], $test_name, $key ) ) {
                    return false;
                }
            }
        }
        else {
            for ( $i = 0; $i < count( $expected ); $i++ ) {
                if ( !isset( $compared[$i] ) ) {
                    $this->wLog( __FUNCTION__.' in test:['.$test_name.'], offset "'.$i.'" not exist in compared array.' );
                    return false;
                }
                if ( !$this->arrays_are_similar( $compared[$i], $expected[$i], $test_name, $key ) ) {
                    return false;
                }
            }
        }

        /* compared to expected */
        if ( $this->is_assoc( $compared ) ) {
            foreach ( $compared as $key => $value ) {
                if ( !array_key_exists( $key, $expected ) ) {
                    $this->wLog( __FUNCTION__.' in test:['.$test_name.'], key "'.$key.'" not exist in expected array.' );
                    return false;
                }
                if ( !$this->arrays_are_similar( $compared[$key], $expected[$key], $test_name, $key ) ) {
                    return false;
                }
            }
        }
        else {
            for ( $i = 0; $i < count( $compared ); $i++ ) {
                if ( !isset( $expected[$i] ) ) {
                    $this->wLog( __FUNCTION__.' in test:['.$test_name.'], offset "'.$i.'" not exist in expected array.' );
                    return false;
                }
                if ( !$this->arrays_are_similar( $compared[$i], $expected[$i], $test_name, $key ) ) {
                    return false;
                }
            }
        }
        return true;
    }

    /*
     * Helper method to log the given arrays keys.
     */
    protected function get_arrays_keys_nicely( $base, $compared ) {
        natsort( $compared );
        natsort( $base );
        $str = __FUNCTION__." array sorted keys:\n";
        $max = max( count($base), count($compared) );
        for ( $i = 0; $i < $max; $i++ ) {
            $str .= isset( $base[$i] ) ? $base[$i] : '--';
            $str .= ' <-> ';
            $str .= isset( $compared[$i] ) ? $compared[$i] : '--';
            $str .=" \n";
        }
        return $str;
    }

    /*
     * From https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
     */
    protected function is_assoc( array $arr ) {
        if (array() === $arr) return false;
        return array_keys($arr) !== range( 0, count($arr) - 1 );
    }

    /*
     * Some simple logging mechanism.
     */
    public function wLog( $str, $f = 'test.txt' ) {
        file_put_contents( $f, "\n".date( 'md-h:i:s' ).'  : '.$str, FILE_APPEND );
    }
}
