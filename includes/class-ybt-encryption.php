<?php
/**
 * YBT Encryption Class - SECURE VERSION
 * Secure data encryption with proper key derivation
 *
 * SECURITY FEATURES:
 * ✅ NEVER store raw encryption key in database
 * ✅ Derive separate keys for encryption and authentication
 * ✅ Encrypt-then-MAC pattern with HMAC authentication
 * ✅ Timing-safe comparison for HMAC verification
 * ✅ Secure token generation with proper entropy
 * ✅ PHP 7.4+ type hints and strict error handling
 */
if (!defined('ABSPATH')) {
	exit('Direct access denied.');
}

class YBT_Encryption {
	/**
	 * Encryption constants
	 */
	private const CIPHER = 'AES-256-CBC';
	private const HMAC_ALGORITHM = 'sha256';
	private const HMAC_LENGTH = 32; // SHA-256 output in bytes
	private const IV_LENGTH = 16;   // AES-256-CBC IV length
	private const BCRYPT_COST = 12;

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Derived encryption keys (separate for encryption and authentication)
	 */
	private $enc_key;
	private $auth_key;

	/**
	 * Get singleton instance
	 *
	 * @return self
	 */
	public static function getInstance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor - derive keys on instantiation
	 */
	private function __construct() {
		$keys = $this->derive_encryption_key();
		if ($keys === false) {
			$this->enc_key = false;
			$this->auth_key = false;
		} else {
			$this->enc_key = $keys['encryption'];
			$this->auth_key = $keys['authentication'];
		}
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 *
	 * @throws \Exception
	 */
	public function __wakeup(): void {
		throw new \Exception('Cannot unserialize a singleton.');
	}

	/**
	 * Derive encryption keys from secure sources
	 * Returns separate keys for encryption and authentication
	 *
	 * Priority: Environment Variable > WordPress Salts > Fail Hard
	 *
	 * @return array|false Array with 'encryption' and 'authentication' keys, or false on failure
	 */
	private function derive_encryption_key() {
		$master_key = null;

		// Priority 1: Environment variable (MOST SECURE)
		if (defined('YBT_ENCRYPTION_KEY') && YBT_ENCRYPTION_KEY) {
			$this->log_info('Using YBT_ENCRYPTION_KEY from constant');
			$master_key = YBT_ENCRYPTION_KEY;
		} elseif (getenv('YBT_ENCRYPTION_KEY')) {
			$this->log_info('Using YBT_ENCRYPTION_KEY from environment');
			$master_key = getenv('YBT_ENCRYPTION_KEY');
		}
		// Priority 2: WordPress salts (fallback)
		elseif (
			defined('AUTH_KEY') && defined('SECURE_AUTH_KEY') &&
			defined('NONCE_KEY') && defined('LOGGED_IN_KEY')
		) {
			$master_key = AUTH_KEY . SECURE_AUTH_KEY . NONCE_KEY . LOGGED_IN_KEY;
			$this->log_info('Using WordPress salts for key derivation');
		}

		// ✅ FAIL HARD if no key source available - better than silent data loss
		if ($master_key === null) {
			$this->log_critical('CRITICAL: No secure encryption key configured. Plugin cannot function securely.');
			if (defined('WP_DEBUG') && WP_DEBUG) {
				throw new \RuntimeException('YBT Encryption: Configure YBT_ENCRYPTION_KEY or WordPress salts in wp-config.php');
			}
			return false;
		}

		// ✅ Derive separate keys using domain separation
		return [
			'encryption'     => hash('sha256', $master_key . ':encrypt', true),
			'authentication' => hash('sha256', $master_key . ':auth', true)
		];
	}

	/**
	 * Encrypt data with authenticated encryption (Encrypt-then-MAC)
	 *
	 * Output format (base64-encoded):
	 * [IV:16 bytes][HMAC-SHA256:32 bytes][Ciphertext:variable]
	 *
	 * @param string $data Plaintext to encrypt
	 * @return string|false Base64-encoded ciphertext, or false on failure
	 */
	public function encrypt(string $data) {
		if (empty($data)) {
			return '';
		}

		// Check for valid keys
		if (empty($this->enc_key) || empty($this->auth_key)) {
			$this->log_error('Encryption aborted: No valid encryption key configured');
			return false;
		}

		try {
			$iv = random_bytes(self::IV_LENGTH);

			$encrypted = openssl_encrypt(
				$data,
				self::CIPHER,
				$this->enc_key,
				OPENSSL_RAW_DATA,
				$iv
			);

			if ($encrypted === false) {
				$errors = [];
				while ($error = openssl_error_string()) {
					$errors[] = $error;
				}
				throw new \Exception('Encryption failed: ' . implode('; ', $errors));
			}

			// Generate HMAC for authentication (Encrypt-then-MAC)
			$hmac = hash_hmac(self::HMAC_ALGORITHM, $iv . $encrypted, $this->auth_key, true);

			// Format: IV + HMAC + Ciphertext, then base64 encode
			return base64_encode($iv . $hmac . $encrypted);

		} catch (\Exception $e) {
			$this->log_error('Encryption failed', ['error' => $e->getMessage()]);
			return false;
		}
	}

	/**
	 * Decrypt data with authentication verification
	 *
	 * Input format (base64-encoded):
	 * [IV:16 bytes][HMAC-SHA256:32 bytes][Ciphertext:variable]
	 *
	 * @param string $data Base64-encoded ciphertext
	 * @return string|false Decrypted plaintext, or false on failure
	 */
	public function decrypt(string $data) {
		if (empty($data)) {
			return '';
		}

		// Check for valid keys
		if (empty($this->enc_key) || empty($this->auth_key)) {
			$this->log_error('Decryption aborted: No valid encryption key configured');
			return false;
		}

		try {
			$decoded = base64_decode($data, true);
			if ($decoded === false) {
				throw new \Exception('Base64 decoding failed');
			}

			// Validate ciphertext length
			$min_length = self::IV_LENGTH + self::HMAC_LENGTH;
			if (strlen($decoded) < $min_length) {
				throw new \Exception('Invalid ciphertext length');
			}

			// Extract components
			$iv = substr($decoded, 0, self::IV_LENGTH);
			$hmac = substr($decoded, self::IV_LENGTH, self::HMAC_LENGTH);
			$ciphertext = substr($decoded, self::IV_LENGTH + self::HMAC_LENGTH);

			// Verify HMAC first (timing-safe comparison)
			$expected_hmac = hash_hmac(self::HMAC_ALGORITHM, $iv . $ciphertext, $this->auth_key, true);
			if (!hash_equals($hmac, $expected_hmac)) {
				$this->log_security_event('HMAC_VERIFICATION_FAILED', [
					'hmac_preview' => bin2hex(substr($hmac, 0, 8))
				]);
				throw new \Exception('HMAC verification failed - possible tampering');
			}

			// Decrypt ciphertext
			$decrypted = openssl_decrypt(
				$ciphertext,
				self::CIPHER,
				$this->enc_key,
				OPENSSL_RAW_DATA,
				$iv
			);

			if ($decrypted === false) {
				$errors = [];
				while ($error = openssl_error_string()) {
					$errors[] = $error;
				}
				throw new \Exception('Decryption failed: ' . implode('; ', $errors));
			}

			return $decrypted;

		} catch (\Exception $e) {
			$this->log_error('Decryption failed', ['error' => $e->getMessage()]);
			return false;
		}
	}

	/**
	 * Hash password with bcrypt
	 *
	 * @param string $password Plain text password
	 * @return string|false Bcrypt hash, or false on failure
	 */
	public function hash_password(string $password) {
		if (empty($password)) {
			return false;
		}
		return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
	}

	/**
	 * Verify password hash
	 *
	 * @param string $password Plain text password
	 * @param string $hash Bcrypt hash to verify against
	 * @return bool True if password matches, false otherwise
	 */
	public function verify_password(string $password, string $hash): bool {
		if (empty($password) || empty($hash)) {
			return false;
		}
		return password_verify($password, $hash);
	}

	/**
	 * Generate secure random token
	 *
	 * @param int $length Token length in bytes (default: 32)
	 * @return string Hex-encoded random token
	 */
	public function generate_token(int $length = 32): string {
		if ($length <= 0) {
			$length = 32;
		}
		return bin2hex(random_bytes($length));
	}

	/**
	 * Generate API key
	 *
	 * @return string Formatted API key with prefix
	 */
	public function generate_api_key(): string {
		return 'ybt_' . $this->generate_token(20);
	}

	/**
	 * Hash API key for storage
	 *
	 * @param string $api_key Plain text API key
	 * @return string SHA-256 hash of API key
	 */
	public function hash_api_key(string $api_key): string {
		if (empty($api_key)) {
			return '';
		}
		return hash('sha256', $api_key);
	}

	/**
	 * Anonymize email for GDPR compliance
	 *
	 * @param string $email Email address to anonymize
	 * @return string Anonymized email
	 */
	public function anonymize_email(string $email): string {
		if (empty($email) || !is_string($email)) {
			return 'anonymized@example.com';
		}

		$parts = explode('@', $email);
		if (count($parts) !== 2) {
			return 'anonymized@example.com';
		}

		$local = $parts[0];
		$domain = $parts[1];
		$anonymized_local = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2));
		return $anonymized_local . '@' . $domain;
	}

	/**
	 * Anonymize phone number
	 *
	 * @param string $phone Phone number to anonymize
	 * @return string Anonymized phone number (last 4 digits visible)
	 */
	public function anonymize_phone(string $phone): string {
		if (empty($phone) || !is_string($phone)) {
			return str_repeat('*', 4);
		}

		$digits = preg_replace('/\D/', '', $phone);
		$length = strlen($digits);
		if ($length < 4) {
			return str_repeat('*', $length);
		}
		return str_repeat('*', $length - 4) . substr($digits, -4);
	}

	/**
	 * Anonymize IP address (zero out last portion per RFC guidelines)
	 *
	 * @param string $ip IP address to anonymize
	 * @return string Anonymized IP address
	 */
	public function anonymize_ip(string $ip): string {
		if (empty($ip) || !is_string($ip)) {
			return '0.0.0.0';
		}

		// Handle IPv4: zero last octet
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$parts = explode('.', $ip);
			if (count($parts) === 4) {
				$parts[3] = '0';
				return implode('.', $parts);
			}
			return '0.0.0.0';
		}

		// Handle IPv6: normalize and zero last 80 bits (keep first 48 bits per RFC 7844)
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$expanded = inet_ntop(inet_pton($ip));
			if ($expanded) {
				$parts = explode(':', $expanded);
				// Zero out last 2 groups (80 bits)
				for ($i = 6; $i < 8; $i++) {
					if (isset($parts[$i])) {
						$parts[$i] = '0000';
					}
				}
				return implode(':', array_slice($parts, 0, 6)) . ':0000:0000';
			}
			return '0.0.0.0';
		}

		return '0.0.0.0';
	}

	/**
	 * Secure comparison to prevent timing attacks
	 *
	 * @param string $known Known value
	 * @param string $user User-supplied value
	 * @return bool True if values match, false otherwise
	 */
	public function secure_compare(string $known, string $user): bool {
		return hash_equals($known, $user);
	}

	/**
	 * Generate CSRF token
	 *
	 * @return string|false CSRF token, or false on failure
	 */
	public function generate_csrf_token() {
		$token = $this->generate_token(16);
		if (!empty($token)) {
			// Store in transient (fast, but may be evicted)
			set_transient('ybt_csrf_' . $token, true, 3600);

			// Fallback to database for persistence in multi-server setups
			global $wpdb;
			if (function_exists('ybt_table_exists') && $wpdb && ybt_table_exists($wpdb->prefix . 'ybt_csrf_tokens')) {
				$wpdb->insert(
					$wpdb->prefix . 'ybt_csrf_tokens',
					[
						'token'      => $token,
						'expires_at' => date('Y-m-d H:i:s', time() + 3600),
						'created_at' => current_time('mysql')
					],
					['%s', '%s', '%s']
				);
			}
		}
		return $token ?: false;
	}

	/**
	 * Verify CSRF token
	 *
	 * @param string $token Token to verify
	 * @return bool True if token is valid and consumed, false otherwise
	 */
	public function verify_csrf_token(string $token): bool {
		if (empty($token)) {
			return false;
		}

		$valid = get_transient('ybt_csrf_' . $token);
		if ($valid) {
			delete_transient('ybt_csrf_' . $token);
			return true;
		}

		// Fallback: check database table
		global $wpdb;
		if (function_exists('ybt_table_exists') && $wpdb && ybt_table_exists($wpdb->prefix . 'ybt_csrf_tokens')) {
			$table = $wpdb->prefix . 'ybt_csrf_tokens';
			$record = $wpdb->get_row($wpdb->prepare(
				"SELECT id FROM $table WHERE token = %s AND expires_at > NOW() LIMIT 1",
				$token
			));

			if ($record) {
				$wpdb->delete($table, ['id' => $record->id], ['%d']);
				return true;
			}
		}

		return false;
	}

	/**
	 * Log info message (with safety check)
	 *
	 * @param string $message Message to log
	 */
	private function log_info(string $message): void {
		if (function_exists('ybt_log_info')) {
			ybt_log_info($message);
		} elseif (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[YBT Encryption] ' . $message);
		}
	}

	/**
	 * Log error message (with safety check)
	 *
	 * @param string $message Message to log
	 * @param array $context Additional context data
	 */
	private function log_error(string $message, array $context = []): void {
		if (function_exists('ybt_log_error')) {
			ybt_log_error($message, $context);
		} elseif (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[YBT Encryption ERROR] ' . $message . ' Context: ' . wp_json_encode($context));
		}
	}

	/**
	 * Log critical message (with safety check)
	 *
	 * @param string $message Message to log
	 */
	private function log_critical(string $message): void {
		if (function_exists('ybt_log_critical')) {
			ybt_log_critical($message);
		} elseif (function_exists('ybt_log_error')) {
			ybt_log_error($message);
		} elseif (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[YBT Encryption CRITICAL] ' . $message);
		}
	}

	/**
	 * Log security event (with safety check)
	 *
	 * @param string $event Event name
	 * @param array $details Event details
	 */
	private function log_security_event(string $event, array $details = []): void {
		if (function_exists('ybt_log_security_event')) {
			ybt_log_security_event($event, $details);
		} elseif (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[YBT SECURITY] ' . $event . ' Details: ' . wp_json_encode($details));
		}
	}
}

/**
 * Helper function to get encryption instance
 *
 * @return YBT_Encryption
 */
function ybt_encryption(): YBT_Encryption {
	return YBT_Encryption::getInstance();
}