<?php

namespace MyCloud\Api\Core;

use MyCloud\Api\Exception\MCConnectionException;

/**
 * Class MyCloudModel
 *
 * Generic Model class that all Model classes extend.
 * Stores all member data in a Hashmap using the PHP magic setters/getters,
 * as this makes attribute assignment from the server simpler.
 *
 * @package MyCloud\Api\Core
 */
class MyCloudModel
{
	/**
	 * The array into which all of our magic properties are stored.
	 */
    private $_propMap = array();

    /**
     * Default Constructor
     *
     * You can pass data as a json representation or array object.
     *
     * @param null    $data
     */
    public function __construct( $data = null )
    {
        switch ( gettype($data) )
		{
            case "NULL":
                break;

            case "string":
                if ( $this->validate_json( $data ) ) {
					$this->fromJson($data);
				}
                break;

            case "array":
                $this->fromArray($data);
                break;

            default:
        }
    }

    /**
     * Magic Get Method
     *
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        if ( $this->__isset($key) ) {
			return $this->_propMap[$key];
        }
        return NULL;
    }

    /**
     * Magic Set Method
     *
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        if ( ! is_array($value) && $value === null ) {
            $this->__unset($key);
        } else {
            $this->_propMap[$key] = $value;
        }
    }

    /**
     * Magic isSet Method
     *
     * @param $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset( $this->_propMap[$key] );
    }

    /**
     * Magic Unset Method
     *
     * @param $key
     */
    public function __unset($key)
    {
        unset( $this->_propMap[$key] );
    }

    /**
     * Converts Params to Array
     *
     * @param $param
     * @return array
     */
    private function _convertToArray($param)
    {
        $result = array();
        foreach ( $param as $k => $v ) {
            if ( $v instanceof MyCloudModel ) {
                $result[$k] = $v->toArray();
            } elseif ( sizeof($v) <= 0 && is_array($v) ) {
                $result[$k] = array();
            } elseif ( is_array($v) ) {
                $result[$k] = $this->_convertToArray($v);
            } else {
                $result[$k] = $v;
            }
        }

		// TGE This case is not needed by our design
		//
        // If the array is empty, which means an empty object,
        // we need to convert that array to a StdClass object
		// in order to be able to properly construct a JSON String.
        // if ( sizeof($result) <= 0 ) {
        //    $result = new MyCloudModel();
        // }

        return $result;
    }

    private function assignValue( $key, $value )
    {
        $setter = 'set'. $this->convertToCamelCase( $key );

        // If we find the setter, use that, otherwise use magic method.
        if ( method_exists($this, $setter) ) {
            $this->$setter($value);
        } else {
            $this->__set($key, $value);
        }
    }

    /**
     * Fills object value from Json string
     *
     * @param $json
     * @return $this
     */
    public function fromJson( $json )
    {
        return $this->fromArray( json_decode($json, true) );
    }

    /**
     * Returns array representation of object
     *
     * @return array
     */
    public function toArray()
    {
        return $this->_convertToArray( $this->_propMap );
    }

    /**
     * Returns object JSON representation
     *
     * @param int $options http://php.net/manual/en/json.constants.php
     * @return string
     */
    public function toJSON( $options = 0 )
    {
		// Use JSON_UNESCAPED_SLASHES option (requires PHP >= 5.4.0)
		return json_encode( $this->toArray(), ($options | 64) );
    }

    /**
     * Magic Method for toString
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJSON( 128 );
    }

    /**
     * Helper method for validating if string provided is a valid json.
     *
     * @param string $string String representation of Json object
     * @param bool $silent Flag to not throw \InvalidArgumentException
     * @return bool
     */
    public function validate_json( $string )
    {
        @json_decode( $string );
        if ( json_last_error() != JSON_ERROR_NONE ) {
            if ( $string !== '' && $string !== null ) {
				return false;
			}
        }
        return true;
    }

    /**
     * Determine if array is a (100%) associate array.
	 *
     * @param array $arr
     * @return true if $arr is an associative array
     */
    public function isAssocArray(array $arr)
    {
        foreach ( $arr as $k => $v ) {
            if ( is_int($k) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Converts the input key into a valid Setter Method Name
     *
     * @param $key
     * @return mixed
     */
    private function convertToCamelCase($key)
    {
        return str_replace(' ', '', ucwords(str_replace(array('_', '-'), ' ', $key)));
    }

	public function assignAttributes( $attrs )
	{
		if ( ! empty($attrs) && is_array($attrs) ) {
            foreach ( $attrs as $k => $v ) {
				$this->assignValue( $k, $v );
			}
		}
	}

	// WARNING!!!!
	// str_replace() is EVIL. It processes the search array
	// from 0 to count-1 NOT over the original string, but over
	// the "current" string. In other words, if ' ' is replaced
	// when '%20', BEFORE hitting '%' in the search array, then
	// the newly replaced '%20' will become '%2520'!!! Thus, we
	// MUST put '%' as the FIRST entry in the search array, as
	// it is the only value that is contained in the replace
	// array values being substituted into the new string.
	// This cost me an HOUR of confusion! Thanks PHP.
	//
	protected static function rfc3986Encode( $string ) {
		$search = array(
			'%', '!', '*', "'",
			"(", ")", ";", ":",
			"@", "&", "=", "+",
			"$", ",", "/", "?",
			" ", "#", "[", "]"
		);

		$replace = array(
			'%25', '%21', '%2A', '%27',
			'%28', '%29', '%3B', '%3A',
			'%40', '%26', '%3D', '%2B',
			'%24', '%2C', '%2F', '%3F',
			'%20', '%23', '%5B', '%5D'
		);

		return str_replace( $search, $replace, $string );
	}

    /**
     * Execute SDK Call to MyCloud API services
     *
     * @param string      $url
     * @param string      $method
     * @param string      $payLoad
     * @param array       $headers
     * @param ApiContext  $apiContext
	 *
     * @return string     json response of the object
     */
    protected static function executeCall( $url, $method, $payLoad, $headers = array(), $apiContext = null )
    {
        // Initialize the context and REST call object if not provided explicitly
        $apiContext = $apiContext ? $apiContext : new ApiContext( null );
        $config = $apiContext->getConfig();

		// NOTE
		// PHP has some serious issues with PUT and PATCH. Specifically, the
		// PHP developers have decided (rightly or wrongly) that they will not
		// process the input data for these requests. For this reason, Laravel
		// support these verbs using the "_method" mechanism, which always uses
		// the POST verb, and sets the "_method" parameter to indicate the actual
		// verb being requested. This is not what we want for a proper RESTful
		// API, which should be using proper verbs.
		//
		// SEE: https://laravel.io/forum/02-13-2014-i-can-not-get-inputs-from-a-putpatch-request
		// And the note that says:
		//    The problem looks like lies in Symfony it can't parse the data if it's multipart/form-data
		// This is a MAJOR problem with properly supporting the correct HTTP verbs!
		// Apparently this is _still_ (Nov 1, 2017) a problem.
		// Not sure if we can justify fixing this.
		//
		// Nov 5, 2017
		// Following up. I could not accept the "magic _method" fix, so I hunted down
		// the answer. It turns out that php://input has our payload (parameters and
		// files) data, it is just not processed. After some searching, I found code
		// that was close to what we needed and fixed it to support reading php://input
		// and building the parameters that we expected in $request.
		//
		// Here is the GIST for that code:
		//    https://gist.github.com/devmycloud/df28012101fbc55d8de1737762b70348
		//
		// So, we now can properly support all of the RESTful verbs, and the following
		// HACK (sorry, "magic") is no longer required.
		//
		// if ( $method == 'PUT' ) {
		// 	$method = 'POST';
		// 	$payLoad['_method'] = 'PUT';
		// } elseif ( $method == 'PATCH' ) {
		// 	$method = 'POST';
		// 	$payLoad['_method'] = 'PATCH';
		// }

		$token = $apiContext->getToken();

		if ( ! empty($token) ) {
			$httpConfig = new MCHttpConfig( $url, $method, $config );
			$http = new MCHttpConnection( $apiContext, $httpConfig, $token );
	        $json = $http->execute( $url, $method, $payLoad, $headers );
		} else {
            $ex = new MCConnectionException(
                ($method . " " . $url),
                "Missing authentication token. Double check your api keys in the configuration.",
                401
            );
            $ex->setData( "" );
            throw $ex;
		}

		return $json;
    }

}
