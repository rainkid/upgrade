<?php
!defined('P_W') && exit('Forbidden');
Class PW_Http{

	/**
	 *
	 * 适配适合的下载类
	 * @param array $args
	 */
	function getTransport($args = array()){
		static $transports;

		if ( !$transports ) {
			if ( true === PW_Http_Ext::test($args) ) $transports['ext'] = & new PW_Http_Ext();
			if ( true === PW_Http_Curl::test($args) ) $transports['curl'] = & new PW_Http_Curl();
			if ( true === PW_Http_Streams::test($args) ) $transports['streams'] = & new PW_Http_Streams();
			if ( true === PW_Http_Fopen::test($args) ) $transports['fopen'] = & new PW_Http_Fopen();
			if ( true === PW_Http_Fsockopen::test($args) ) $transports['fsockopen'] = & new PW_Http_Fsockopen();
		}
		return $transports;
	}

	/**
	 *
	 * 发送请求
	 * @param string $url
	 * @param array $args
	 */
	function request( $url, $args = array() ) {
		$arrUrl = parse_url($url);
		if ( empty( $url ) || empty( $arrUrl['scheme'] ) ) $this->msg('下载地址不正确');
		$check = @parse_url($url);
		if ( $check === false ) $this->msg('URL不正确');
		if ( $check['host'] == 'localhost') $this->msg('本地文件无需下载');
		$transports = $this->getTransport( $args );
		$response = array( 'headers' => array(), 'body' => '', 'response' => array('code' => false, 'message' => false), 'cookies' => array() );
		foreach ( (array) $transports as $transport ) {
			$response = $transport->request($url, $args);
			if ( $response['response']['code'] == '200' ) return $response;
		}
		return $response;
	}

	/**
	 * 
	 * 信息提示，文件扩展
	 * @param String $str 信息内容
	 */
	function msg($str){
		adminmsg($str);
	}
}

/**
 *
 * Http下载基类
 * @author hu.liaoh
 *
 */
Class PW_Http_Base{
	var $defaults = array(
			'method' => 'POST', 'timeout' => 300,
			'redirection' => 5, 'httpversion' => '1.0',
			'headers' => array(), 'body' => null, 'cookies' => array()
	);

	/**
	 *
	 * 合并参数
	 * @param array $args
	 * @param array $defaults
	 */
	function parseArgs($args, $defaults){
		$pwEncoding = new PW_Http_Encoding();
		if ( ! is_array($args['headers']) ) {
			$processedHeaders = $this->getHeaders($args['headers']);
			$args['headers'] = $processedHeaders['headers'];
		}
		if ( $pwEncoding->is_available() )	$args['headers']['Accept-Encoding'] = $pwEncoding->accept_encoding();
		if ( isset($args['headers']['User-Agent']) ) $defaults['user-agent'] = $args['headers']['User-Agent'];
		foreach ( $args as $key => $value ) {
			if (isset($defaults[$key])) $defaults[$key] = $value;
		}
		return $defaults;
	}

	/**
	 *
	 * 对输入内容进行Utf8转码
	 * @param String $body
	 */
	function chunkTransferDecode($body) {
		$body = str_replace(array("\r\n", "\r"), "\n", $body);
		if ( ! preg_match( '/^[0-9a-f]+(\s|\n)+/mi', trim($body) ) ) return $body;
		$parsedBody = '';
		$hasChunk = (bool) preg_match( '/^([0-9a-f]+)(\s|\n)+/mi', $body, $match );
		if ( $hasChunk ) {
			if ( empty( $match[1] ) ) return $body;
			$length = hexdec( $match[1] );
			$chunkLength = strlen( $match[0] );
			$strBody = substr($body, $chunkLength, $length);
			$parsedBody .= $strBody;
			$body = ltrim(str_replace(array($match[0], $strBody), '', $body), "\n");
			if ( "0" == trim($body) ) return $parsedBody;
		}
		return $body;
	}

	/**
	 *
	 * 解析头信息
	 * @param String $headers
	 */
	function getHeaders($headers) {
		if ( is_string($headers) ) {
			$headers = str_replace("\r\n", "\n", $headers);
			$headers = preg_replace('/\n[ \t]/', ' ', $headers);
			$headers = explode("\n", $headers);
		}

		$response = array('code' => 0, 'message' => '');
		for ( $i = count($headers)-1; $i >= 0; $i-- ) {
			if ( !empty($headers[$i]) && false === strpos($headers[$i], ':') ) {
				$headers = array_splice($headers, $i);
				break;
			}
		}

		$cookies = array();
		$newheaders = array();
		foreach ( $headers as $tempheader ) {
			if ( empty($tempheader) ) continue;
			if ( false === strpos($tempheader, ':') ) {
				list( , $response['code'], $response['message']) = explode(' ', $tempheader, 3);
				continue;
			}
			list($key, $value) = explode(':', $tempheader, 2);
			if ( !empty( $value ) ) {
				$key = strtolower( $key );
				if ( isset( $newheaders[$key] ) ) {
					if ( !is_array($newheaders[$key]) )	$newheaders[$key] = array($newheaders[$key]);
					$newheaders[$key][] = trim( $value );
				} else {
					$newheaders[$key] = trim( $value );
				}
				if ( 'set-cookie' == strtolower( $key ) ) $cookies[] = new PW_Http_Cookie( $value );
			}
		}
		return array('response' => $response, 'headers' => $newheaders, 'cookies' => $cookies);
	}

	/**
	 *
	 * 根据参数组装header头字符串
	 * @param array $r
	 */
	function buildCookieHeader( &$r ) {
		if ( ! empty($r['cookies']) ) {
			$cookies_header = '';
			foreach ( (array) $r['cookies'] as $cookie ) {
				$cookies_header .= $cookie->getHeaderValue() . '; ';
			}
			$cookies_header = substr( $cookies_header, 0, -2 );
			$r['headers']['cookie'] = $cookies_header;
		}
	}

	/**
	 *
	 * 根据状态码获得对应 的描述字符串
	 * @param Int $code
	 */
	function getHeaderDesc( $code ) {
		global $headerDesc;

		$code = abs( intval($code ));

		if ( !isset( $headerDesc ) ) {
			$headerDesc = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',

			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			226 => 'IM Used',

			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Reserved',
			307 => 'Temporary Redirect',

			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			426 => 'Upgrade Required',

			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			510 => 'Not Extended'
			);
		}
		return isset( $headerDesc[$code] ) ? $headerDesc[$code] : '';
	}

	/**
	 *
	 * 发送请求
	 * @param String $url
	 * @param Array $args
	 */
	function request($url, $args = array()){}
	/**
	 *
	 * 测试接口
	 * @param Array $args
	 */
	function test($args = array()){}

	function msg($str){
		adminmsg($str);
	}
}

/**
 *
 * Curl 扩展下载文件类
 * @author hu.liaoh
 *
 */
Class PW_Http_Curl extends PW_Http_Base{

	/**
	 * (non-PHPdoc)
	 * @see PW_Http_Base::request()
	 */
	function request($url, $args = array()){
		$r = $this->parseArgs($args, $this->defaults);

		$ssl_verify = isset($args['sslverify']) && $args['sslverify'];//是否开启证书验证
		$handle = curl_init();//启动会话
		$timeout = (int) ceil( $r['timeout'] );
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $timeout );//连接超时时间
		curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $handle, CURLOPT_URL, $url);
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );//以流的形式返回
		curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, $ssl_verify );//对认证证书来源的检查
		curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, $ssl_verify );//从证书中检查SSL加密算法是否存在
		curl_setopt( $handle, CURLOPT_USERAGENT, $r['user-agent'] );//模拟用户使用的浏览器
		curl_setopt( $handle, CURLOPT_MAXREDIRS, $r['redirection'] );//允许自动跳转的次数
		curl_setopt( $handle, CURLOPT_HEADER, true ); //是否显示返回的Header区域内容
		curl_setopt( $handle, CURLOPT_POST, true );//以POST方式发送
		curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );//发送的数据包
		if ( !ini_get('safe_mode') && !ini_get('open_basedir')) curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true );//是否作为http头的一部分返回放在Location:里面
		if ( !empty( $r['headers'] ) ) {
			$headers = array();
			foreach ( $r['headers'] as $name => $value ) {
				$headers[] = "{$name}: $value";
			}
			curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
		}
		$curlVersion = ($r['httpversion'] == '1.0') ? CURL_HTTP_VERSION_1_0 : CURL_HTTP_VERSION_1_1;
		curl_setopt( $handle, CURLOPT_HTTP_VERSION, $curlVersion );
		$theResponse = curl_exec( $handle );
		if ( !empty($theResponse) ) {
			$headerLength = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
			$theHeaders = trim( substr($theResponse, 0, $headerLength) );
			$theBody = ( strlen($theResponse) > $headerLength ) ? substr( $theResponse, $headerLength ) : '';
			if ( false !== strrpos($theHeaders, "\r\n\r\n") ) {
				$headerParts = explode("\r\n\r\n", $theHeaders);
				$theHeaders = $headerParts[ count($headerParts) -1 ];
			}
			$theHeaders = $this->getHeaders($theHeaders);
		} else {
			if ( $curl_error = curl_error($handle) ) $this->msg('CURL错误:'.$curl_error);
			if ( in_array( curl_getinfo( $handle, CURLINFO_HTTP_CODE ), array(301, 302) ) ) $this->msg('CURL错误:');
			$theHeaders = array( 'headers' => array(), 'cookies' => array() );
			$theBody = '';
		}
		$response = array();
		$response['code'] = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
		$response['message'] = $this->getHeaderDesc($response['code']);
		curl_close( $handle );
		return array('headers' => $theHeaders['headers'], 'body' => $theBody, 'response' => $response, 'cookies' => $theHeaders['cookies']);
	}

	/**
	 * (non-PHPdoc)
	 * @see PW_Http_Base::test()
	 */
	function test($args = array()){
		if ( function_exists('curl_init') && function_exists('curl_exec') ) return true;
		return false;
	}
}

/**
 *
 * Fopen 方式下载文件类
 * @author hu.liaoh
 *
 */
Class PW_Http_Fopen extends PW_Http_Base{

	/**
	 * (non-PHPdoc)  发送请求
	 * @see PW_Http_Base::request()
	 */
	function request($url, $args = array()){
		$httpEncoding = new PW_Http_Encoding();
		$r = $this->parseArgs( $args, $this->defaults );
		$arrUrl = parse_url( $url );
		if ( false === $arrUrl ) $this->msg( '下载地址错误' );
		if ( 'http' != $arrUrl['scheme'] && 'https' != $arrUrl['scheme'] )	$url = str_replace( $arrUrl['scheme'], 'http', $url );
		if ( is_null( $r['headers'] ) ) $r['headers'] = array();
		if ( is_string($r['headers']) ) {
			$processedHeaders = $this->getHeaders($r['headers']);
			$r['headers'] = $processedHeaders['headers'];
		}
		$initial_user_agent = ini_get('user_agent');
		if ( !empty($r['headers']) && is_array($r['headers']) ) {
			$user_agent_extra_headers = '';
			foreach ( $r['headers'] as $header => $value ){
				$user_agent_extra_headers .= "\r\n$header: $value";
			}
			@ini_set('user_agent', $r['user-agent'] . $user_agent_extra_headers);
		} else {
			@ini_set('user_agent', $r['user-agent']);
		}
		$handle = fopen($url, 'r');
		if (! $handle) $this->msg( 'fopen：无法打开下载地址' );
		$timeout = (int) floor( $r['timeout'] );
		$utimeout = $timeout == $r['timeout'] ? 0 : 1000000 * $r['timeout'] % 1000000;
		stream_set_timeout( $handle, $timeout, $utimeout );

		$strResponse = '';
		while ( ! feof($handle) ){
			$strResponse .= fread( $handle, 4096 );
		}

		if ( function_exists('stream_get_meta_data') ) {
			$meta = stream_get_meta_data($handle);
			$theHeaders = $meta['wrapper_data'];
			if ( isset( $meta['wrapper_data']['headers'] ) ) $theHeaders = $meta['wrapper_data']['headers'];
		} else {
			//$http_response_header通过Http封装在当前作用域中的一个变量详细请参考 http://php.oregonstate.edu/manual/en/reserved.variables.httpresponseheader.php
			$theHeaders = $http_response_header;
		}
		fclose($handle);
		@ini_set('user_agent', $initial_user_agent); //还原user_agent设置
		$processedHeaders = $this->getHeaders($theHeaders);
		if ( ! empty( $strResponse ) && isset( $processedHeaders['headers']['transfer-encoding'] ) && 'chunked' == $processedHeaders['headers']['transfer-encoding'] )
		$strResponse = $this->chunkTransferDecode($strResponse);
		if ( true === $r['decompress'] && true === $httpEncoding->should_decode($processedHeaders['headers']) ) $strResponse = $httpEncoding->decompress( $strResponse );

		return array('headers' => $processedHeaders['headers'], 'body' => $strResponse, 'response' => $processedHeaders['response'], 'cookies' => $processedHeaders['cookies']);
	}

	function test($args = array()){
		if ( function_exists('fopen') || (function_exists('ini_get') && true == ini_get('allow_url_fopen')) ) return true;
		return false;
	}
}

/**
 *
 * PHP 扩展方式下载文件类
 * @author hu.liaoh
 *
 */
Class PW_Http_Ext  extends PW_Http_Base{

	function test($args = array()){
		return false;
	}
}

/**
 *
 * Fsockopen方式下载文件类
 * @author hu.liaoh
 *
 */
Class PW_Http_Fsockopen  extends PW_Http_Base{

	function test($args = array()){
		return false;
	}
}

/**
 *
 * Streams文件流形式下载文件类
 * @author hu.liaoh
 *
 */
Class PW_Http_Streams  extends PW_Http_Base{
	
	/**
	 * (non-PHPdoc) 发送请求
	 * @see PW_Http_Base::request()
	 */
	function request($url, $args){
		$httpEncoding = new PW_Http_Encoding();
		$r = $this->parseArgs($args, $this->defaults);

		$this->buildCookieHeader( $r );
		$arrUrl = parse_url($url);
		if ( false === $arrUrl ) $this->msg('URL不正确: '.  $url);

		if ( 'http' != $arrUrl['scheme'] && 'https' != $arrUrl['scheme'] )
		$url = preg_replace('|^' . preg_quote($arrUrl['scheme'], '|') . '|', 'http', $url);

		$strHeaders = '';
		if ( is_array( $r['headers'] ) ){
			foreach ( $r['headers'] as $name => $value ){
				$strHeaders .= "{$name}: $value\r\n";
			}
		}else if ( is_string( $r['headers'] ) ){
			$strHeaders = $r['headers'];
		}

		$ssl_verify = isset($args['sslverify']) && $args['sslverify'];

		$arrContext = array('http' =>
		array(
				'method' => strtoupper($r['method']),
				'user_agent' => $r['user-agent'],
				'max_redirects' => $r['redirection'] + 1,
				'protocol_version' => (float) $r['httpversion'],
				'header' => $strHeaders,
				'ignore_errors' => true, //返回状态为非200的错误
				'timeout' => $r['timeout'],
				'ssl' => array(
						'verify_peer' => $ssl_verify,
						'verify_host' => $ssl_verify
		)
		)
		);
		if ( ! empty($r['body'] ) )	$arrContext['http']['content'] = $r['body'];
		$context = stream_context_create($arrContext);
		$handle = fopen($url, 'r', false, $context);

		if ( ! $handle ) $this->msg('无法打开的URL:' . $url );

		$timeout = (int) floor( $r['timeout'] );
		$utimeout = $timeout == $r['timeout'] ? 0 : 1000000 * $r['timeout'] % 1000000;
		stream_set_timeout( $handle, $timeout, $utimeout );
		$strResponse = stream_get_contents($handle);
		$meta = stream_get_meta_data($handle);
		fclose($handle);
		$processedHeaders = array();
		$processedHeaders = ( isset( $meta['wrapper_data']['headers'] ) ) ? $this->getHeaders($meta['wrapper_data']['headers']) :  $this->getHeaders($meta['wrapper_data']);
		if ( ! empty( $strResponse ) && isset( $processedHeaders['headers']['transfer-encoding'] ) && 'chunked' == $processedHeaders['headers']['transfer-encoding'] ) $strResponse =$this->chunkTransferDecode($strResponse);
		if ( true === $r['decompress'] && true === $httpEncoding->should_decode($processedHeaders['headers']) ) $strResponse = $httpEncoding->decompress( $strResponse );
		return array('headers' => $processedHeaders['headers'], 'body' => $strResponse, 'response' => $processedHeaders['response'], 'cookies' => $processedHeaders['cookies']);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see PW_Http_Base::test()
	 */
	function test($args = array()){
		if ( function_exists('fopen') || (function_exists('ini_get') && true == ini_get('allow_url_fopen')) ) return true;
		if ( !version_compare(PHP_VERSION, '5.0', '<') ) return true;
		return false;
	}
}

/**
 *
 * 文件编码解码类
 * @author hu.liaoh
 *
 */
class PW_Http_Encoding {

	/**
	 *
	 * 压缩字符串
	 * @param string $raw
	 * @param int $level
	 */
	function compress( $raw, $level = 9) {
		return gzdeflate( $raw, $level );
	}

	/**
	 *
	 * 解压字符串
	 * @param string $compressed
	 */
	function decompress( $compressed) {
		if ( empty($compressed) ) return $compressed;
		if ( false !== ( $decompressed = @gzinflate( $compressed ) ) )	return $decompressed;
		if ( false !== ( $decompressed = $this->compatible_gzinflate( $compressed ) ) ) return $decompressed;
		if ( false !== ( $decompressed = @gzuncompress( $compressed ) ) )	return $decompressed;
		if ( function_exists('gzdecode') ) {
			$decompressed = @gzdecode( $compressed );
			if ( false !== $decompressed ) return $decompressed;
		}
		return $compressed;
	}

	/**
	 *
	 * Enter description here ...
	 * @param unknown_type $gzData
	 */
	function compatible_gzinflate( $gzData ) {
		if ( substr($gzData, 0, 3) == "\x1f\x8b\x08" ) {
			$i = 10;
			$flg = ord( substr($gzData, 3, 1) );
			if ( $flg > 0 ) {
				if ( $flg & 4 ) {
					list($xlen) = unpack('v', substr($gzData, $i, 2) );
					$i = $i + 2 + $xlen;
				}
				if ( $flg & 8 ) $i = strpos($gzData, "\0", $i) + 1;
				if ( $flg & 16 ) $i = strpos($gzData, "\0", $i) + 1;
				if ( $flg & 2 )	$i = $i + 2;
			}
			return gzinflate( substr($gzData, $i, -8) );
		}
		return false;
	}

	/**
	 *
	 * 选择编码类型并返回它的值
	 */
	function accept_encoding() {
		$type = array();
		if ( function_exists( 'gzinflate' ) ) $type[] = 'deflate;q=1.0';
		if ( function_exists( 'gzuncompress' ) ) $type[] = 'compress;q=0.5';
		if ( function_exists( 'gzdecode' ) ) $type[] = 'gzip;q=0.5';
		return implode(', ', $type);
	}

	/**
	 *
	 * 获取头信息中内容编码方式
	 */
	function content_encoding() {
		return 'deflate';
	}

	/**
	 *
	 * 检查header是否需要编码
	 * @param String|Array $headers
	 */
	function should_decode($headers) {
		if ( is_array( $headers ) && ( array_key_exists('content-encoding', $headers) && ! empty( $headers['content-encoding'] ) ) ) return true;
		if ( is_string( $headers ) ) return ( stripos($headers, 'content-encoding:') !== false );
		return false;
	}

	function is_available() {
		return ( function_exists('gzuncompress') || function_exists('gzdeflate') || function_exists('gzinflate') );
	}
}

/**
 *
 *
 * @author hu.liaoh
 *
 */
class PW_Http_Cookie {

	var $name; //Cookie name
	var $value; //Cookie value
	var $expires; //Cookie 过期时间
	var $path; //Cookie 地址
	var $domain; //Cookie 主机

	function PW_Http_Cookie( $data ) {
		$this->__construct( $data );
	}

	/**
	 * 设置Cookie.
	 * @param string|array.
	 */
	function __construct( $data ) {
		if ( is_string( $data ) ) {
			$pairs = explode( ';', $data );

			// Special handling for first pair; name=value. Also be careful of "=" in value
			$name  = trim( substr( $pairs[0], 0, strpos( $pairs[0], '=' ) ) );
			$value = substr( $pairs[0], strpos( $pairs[0], '=' ) + 1 );
			$this->name  = $name;
			$this->value = urldecode( $value );
			array_shift( $pairs ); //Removes name=value from items.

			foreach ( $pairs as $pair ) {
				$pair = rtrim($pair);
				if ( empty($pair) ) continue;
				list( $key, $val ) = strpos( $pair, '=' ) ? explode( '=', $pair ) : array( $pair, '' );
				$key = strtolower( trim( $key ) );
				if ( 'expires' == $key ) $val = strtotime( $val );
				$this->$key = $val;
			}
		} else {
			if ( !isset( $data['name'] ) ) return false;
			$this->name   = $data['name'];
			$this->value  = isset( $data['value'] ) ? $data['value'] : '';
			$this->path   = isset( $data['path'] ) ? $data['path'] : '';
			$this->domain = isset( $data['domain'] ) ? $data['domain'] : '';
			$this->expires = ( isset( $data['expires'] ) ) ? is_int( $data['expires'] ) ? $data['expires'] : strtotime( $data['expires'] ) : null;
		}
	}

	/**
	 * 根据Cookie Name 和 Cookie Value 返回Cookie Header
	 */
	function getHeaderValue() {
		if ( empty( $this->name ) || empty( $this->value ) ) return '';
		return $this->name . '=' . urlencode( $this->value );
	}

	/**
	 * 返回完整的Header
	 * @return string
	 */
	function getFullHeader() {
		return 'Cookie: ' . $this->getHeaderValue();
	}
}