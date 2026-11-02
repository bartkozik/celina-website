<?php
/**
 * Plugin Name: CELINA TLS trust
 * Description: Excludes three compromised CA public keys from WordPress HTTP API trust.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

final class CELINA_TLS_Trust {
	// SPKI SHA-256, including cross-signed certificates with the same public key.
	private const BLOCKED = array(
		'8d417db2dd8bf5e3084d1e3f196d583849d81bdd4c00c70b9d39369e96b8c782',
		'bd153ed7b0434f6886b17bce8bbe84ed340c7132d702a8f4fa318f756ecbd6f3',
		'c619e4780ed8e0abeda24ad732567de4383a7ea941338f6d1a7d1b49ac792fe9',
	);
	private static $cache = array();
	private static $files = array();
	private static $selected = array();
	private static $capath;
	private static $diagnosing = false;
	private static $curl_bundles = array();

	private static function error() {
		return new WP_Error( 'celina_tls_trust_unavailable', 'CELINA TLS trust could not safely prepare the selected CA bundle.' );
	}

	/** Filter the current bundle; never freeze WordPress trust to a shipped snapshot. */
	public static function filter_bundle( $source ) {
		if ( ! function_exists( 'openssl_pkey_get_details' ) || ! is_string( $source ) || false !== strpos( $source, '://' ) || ! is_file( $source ) || ! is_readable( $source ) ) {
			return self::error();
		}
		$content = @file_get_contents( $source );
		if ( false === $content || '' === trim( $content ) ) {
			return self::error();
		}
		$key = hash( 'sha256', $content );
		if ( isset( self::$cache[ $key ] ) ) {
			$file = self::$cache[ $key ];
			clearstatcache( true, $file );
			if ( is_file( $file ) && 0600 === ( fileperms( $file ) & 0777 ) && self::$files[ $file ] === @hash_file( 'sha256', $file ) ) {
				return $file;
			}
		}
		preg_match_all( '/-----BEGIN CERTIFICATE-----\s*[A-Za-z0-9+\/=\r\n]+-----END CERTIFICATE-----/', $content, $matches );
		// Reject partial PEM blocks and other PEM types; ordinary CA-bundle comments are allowed.
		$remainder = str_replace( $matches[0], '', $content );
		if ( empty( $matches[0] ) || false !== strpos( $remainder, '-----BEGIN' ) || false !== strpos( $remainder, '-----END' ) ) {
			return self::error();
		}
		$allowed = array();
		foreach ( $matches[0] as $pem ) {
			$certificate = @openssl_x509_read( $pem );
			$public_key  = $certificate ? @openssl_pkey_get_public( $certificate ) : false;
			$details     = $public_key ? openssl_pkey_get_details( $public_key ) : false;
			if ( ! $details || ! isset( $details['key'] ) ) {
				return self::error();
			}
			$der = base64_decode( preg_replace( '/-----[^-]+-----|\s/', '', $details['key'] ), true );
			if ( false === $der || '' === $der ) {
				return self::error();
			}
			if ( ! in_array( hash( 'sha256', $der ), self::BLOCKED, true ) ) {
				$allowed[] = $pem;
			}
		}
		if ( empty( $allowed ) ) {
			return self::error();
		}
		$output = implode( "\n", $allowed ) . "\n";
		$dir    = realpath( get_temp_dir() );
		if ( false === $dir || ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return self::error();
		}
		$file = @tempnam( $dir, 'celina-ca-' );
		if ( false === $file ) {
			return self::error();
		}
		self::$files[ $file ] = hash( 'sha256', $output );
		if ( dirname( $file ) !== $dir || ! @chmod( $file, 0600 ) || @file_put_contents( $file, $output, LOCK_EX ) !== strlen( $output ) ) {
			@unlink( $file );
			return self::error();
		}
		clearstatcache( true, $file );
		if ( 0600 !== ( fileperms( $file ) & 0777 ) || self::$files[ $file ] !== @hash_file( 'sha256', $file ) ) {
			@unlink( $file );
			return self::error();
		}
		self::$cache[ $key ]                   = $file;
		self::$cache[ self::$files[ $file ] ] = $file;
		return $file;
	}

	public static function cleanup() {
		foreach ( self::$files as $file => $hash ) {
			@unlink( $file );
		}
		if ( self::$capath ) {
			@rmdir( self::$capath );
		}
	}

	public static function request_args( $args, $url ) {
		if ( self::$diagnosing ) {
			$args['headers'] = array();
			$args['cookies'] = array();
			$args['body'] = null;
			$args['method'] = 'GET';
			$args['user-agent'] = 'TLS-Trust-Diagnostic/1.0';
			$args['redirection'] = 0;
		}
		$bundle = self::filter_bundle( $args['sslcertificates'] ?? null );
		$args['sslverify'] = true; // An initial HTTP request may redirect to HTTPS.
		if ( ! is_wp_error( $bundle ) ) {
			$args['sslcertificates'] = $bundle;
		}
		return $args;
	}

	public static function pre_request( $pre, $args, $url ) {
		$bundle = self::filter_bundle( $args['sslcertificates'] ?? null );
		self::$selected[ $url ] = $bundle;
		return is_wp_error( $bundle ) ? $bundle : $pre;
	}

	public static function verify( $verify, $url ) {
		$bundle = is_string( $verify ) ? self::filter_bundle( $verify ) : ( self::$selected[ $url ] ?? self::error() );
		// This filter accepts bool|string, never WP_Error. The Requests guard below
		// converts the deliberately invalid path to a caught Requests exception.
		return is_wp_error( $bundle ) ? 'celina-tls-trust://unavailable' : $bundle;
	}

	public static function before_request( &$url, &$headers, &$data, &$type, &$options ) {
		if ( self::$diagnosing ) {
			$headers = array();
			$data = null;
			$type = 'GET';
			$options['useragent'] = 'TLS-Trust-Diagnostic/1.0';
			$options['follow_redirects'] = false;
		}
		$bundle = self::filter_bundle( $options['verify'] ?? null );
		if ( is_wp_error( $bundle ) ) {
			throw new WpOrg\Requests\Exception( $bundle->get_error_message(), 'celina.tls_trust' );
		}
		$options['verify']     = $bundle;
		$options['verifyname'] = true;
		// Capture the final Requests bundle for this handle before either native
		// WordPress cURL hook runs. A per-handle entry also handles nested requests.
		$options['hooks']->register( 'curl.before_send', static function ( $handle ) use ( $bundle ) {
			$id = is_object( $handle ) ? spl_object_id( $handle ) : (int) $handle;
			self::$curl_bundles[ $id ] = $bundle;
		} );
	}

	public static function curl( $handle ) {
		// Requests assigns CAINFO AFTER http_api_curl. The filtered verify option
		// supplies that path; this hook prevents a second trust store bypass.
		$version = curl_version();
		if ( ! preg_match( '/^(OpenSSL|LibreSSL|BoringSSL)\//', $version['ssl_version'] ) ) {
			throw new WpOrg\Requests\Exception( 'CELINA TLS trust requires a cURL OpenSSL-compatible backend.', 'celina.tls_backend' );
		}
		// An empty string is not portable (OpenSSL cURL returns error 77).
		// Use a private empty directory instead of the system hashed CA directory.
		if ( ! self::$capath ) {
			$directory = realpath( get_temp_dir() );
			$candidate = $directory . '/celina-ca-dir-' . bin2hex( random_bytes( 16 ) );
			if ( false === $directory || ! @mkdir( $candidate, 0700 ) ) {
				throw new WpOrg\Requests\Exception( 'CELINA TLS trust could not isolate cURL trust.', 'celina.tls_backend' );
			}
			self::$capath = $candidate;
		}
		clearstatcache( true, self::$capath );
		if ( ! is_dir( self::$capath ) || 0700 !== ( fileperms( self::$capath ) & 0777 ) || array( '.', '..' ) !== scandir( self::$capath ) ) {
			throw new WpOrg\Requests\Exception( 'CELINA TLS trust directory is not private and empty.', 'celina.tls_backend' );
		}
		foreach ( array( CURLOPT_CAPATH => self::$capath, CURLOPT_SSL_OPTIONS => 0, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2 ) as $option => $value ) {
			if ( ! curl_setopt( $handle, $option, $value ) ) {
				throw new WpOrg\Requests\Exception( 'CELINA TLS trust could not restrict cURL trust.', 'celina.tls_backend' );
			}
		}
		// CAINFO_BLOB overrides CAINFO. PHP converts null to an invalid empty blob,
		// so replace any injected blob with this handle's sanitized bundle instead.
		if ( defined( 'CURLOPT_CAINFO_BLOB' ) ) {
			$id = is_object( $handle ) ? spl_object_id( $handle ) : (int) $handle;
			$bundle = self::filter_bundle( self::$curl_bundles[ $id ] ?? null );
			$content = is_wp_error( $bundle ) ? false : @file_get_contents( $bundle );
			if ( false === $content || ! curl_setopt( $handle, CURLOPT_CAINFO_BLOB, $content ) ) {
				throw new WpOrg\Requests\Exception( 'CELINA TLS trust could not restrict cURL inline trust.', 'celina.tls_backend' );
			}
		}
	}

	public static function streams() {
		// Requests does not expose its SSL context. PHP's configured capath would
		// supplement cafile, so reject that unsupported setup rather than weaken trust.
		if ( '' !== trim( (string) ini_get( 'openssl.capath' ) ) ) {
			throw new WpOrg\Requests\Exception( 'CELINA TLS trust requires an empty openssl.capath for the streams transport.', 'celina.tls_backend' );
		}
	}

	private static function can_diagnose() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return is_admin() && $screen && 'site-health' === $screen->id && ! wp_doing_cron() && ( current_user_can( 'view_site_health_checks' ) || current_user_can( 'manage_options' ) );
	}

	public static function health_tests( $tests ) {
		if ( self::can_diagnose() ) {
			$tests['direct']['celina_tls_trust'] = array( 'label' => 'CELINA outgoing TLS trust', 'test' => array( __CLASS__, 'health_check' ), 'skip_cron' => true );
		}
		return $tests;
	}

	public static function health_check() {
		$result = array(
			'label' => 'CELINA outgoing TLS test is inconclusive',
			'status' => 'recommended',
			'badge' => array( 'label' => 'Security', 'color' => 'blue' ),
			'description' => '<p>The TLS diagnostic must be run by an administrator from Site Health.</p>',
			'actions' => '',
			'test' => 'celina_tls_trust',
		);
		if ( ! self::can_diagnose() ) {
			return $result;
		}
		$hosts = array( 'https://dv-g3b-emudhra.we-have-a-problem.com/', 'https://dv-g3b-aaa-emudhra.we-have-a-problem.com/', 'https://api.wordpress.org/' );
		$outcomes = array();
		foreach ( $hosts as $url ) {
			self::$diagnosing = true;
			try {
				$response = wp_remote_get( $url, array( 'timeout' => 5, 'redirection' => 0, 'sslverify' => true, 'user-agent' => 'TLS-Trust-Diagnostic/1.0', 'headers' => array(), 'cookies' => array(), 'limit_response_size' => 1 ) );
			} finally {
				self::$diagnosing = false;
			}
			if ( is_wp_error( $response ) ) {
				// Expiry, hostname errors and generic verification errors cannot prove CA distrust.
				$outcomes[] = preg_match( '/unable to get (?:local )?issuer certificate|unable to verify (?:the )?first certificate|self[- ]signed certificate|certificate signature failure/i', $response->get_error_message() ) ? 'certificate rejected' : 'inconclusive';
			} else {
				$outcomes[] = 200 === wp_remote_retrieve_response_code( $response ) ? 'accepted' : 'HTTP response received';
			}
		}
		if ( in_array( $outcomes[0], array( 'accepted', 'HTTP response received' ), true ) || in_array( $outcomes[1], array( 'accepted', 'HTTP response received' ), true ) ) {
			$result['label']  = 'An incident certificate was accepted';
			$result['status'] = 'critical';
		} elseif ( 'certificate rejected' === $outcomes[0] && 'certificate rejected' === $outcomes[1] && in_array( $outcomes[2], array( 'accepted', 'HTTP response received' ), true ) ) {
			$result['label']  = 'Incident certificates were rejected and the WordPress control succeeded';
			$result['status'] = 'good';
		}
		$result['description'] = '<p>Direct incident host: ' . esc_html( $outcomes[0] ) . '. AAA incident host: ' . esc_html( $outcomes[1] ) . '. WordPress control: ' . esc_html( $outcomes[2] ) . '.</p><p>Network errors and timeouts are inconclusive. This checks WordPress HTTP API requests only.</p>';
		return $result;
	}
}

register_shutdown_function( array( 'CELINA_TLS_Trust', 'cleanup' ) );
add_filter( 'http_request_args', array( 'CELINA_TLS_Trust', 'request_args' ), PHP_INT_MAX, 2 );
add_filter( 'pre_http_request', array( 'CELINA_TLS_Trust', 'pre_request' ), PHP_INT_MAX, 3 );
add_filter( 'https_ssl_verify', array( 'CELINA_TLS_Trust', 'verify' ), PHP_INT_MAX, 2 );
add_action( 'requests-requests.before_request', array( 'CELINA_TLS_Trust', 'before_request' ), PHP_INT_MAX, 5 );
add_action( 'http_api_curl', array( 'CELINA_TLS_Trust', 'curl' ), PHP_INT_MAX );
add_action( 'requests-curl.before_send', array( 'CELINA_TLS_Trust', 'curl' ), PHP_INT_MAX );
add_action( 'requests-fsockopen.before_request', array( 'CELINA_TLS_Trust', 'streams' ), PHP_INT_MAX );
add_filter( 'site_status_tests', array( 'CELINA_TLS_Trust', 'health_tests' ) );
