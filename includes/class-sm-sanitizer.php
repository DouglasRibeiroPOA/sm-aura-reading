<?php
/**
 * Centralized sanitization and validation helper.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides sanitization, validation, and output escaping helpers.
 */
class SM_Sanitizer {

	/**
	 * Allowed identity values for lead creation.
	 *
	 * @var array
	 */
	const ALLOWED_IDENTITIES = array( 'woman', 'man', 'prefer-not' );

	/**
	 * Maximum file size for image uploads (in bytes).
	 *
	 * @var int
	 */
	const MAX_IMAGE_SIZE = 5242880; // 5MB

	/**
	 * Allowed image MIME types.
	 *
	 * @var array
	 */
	const ALLOWED_IMAGE_TYPES = array( 'image/jpeg', 'image/png' );

	/**
	 * Maximum length for quiz free-text answers.
	 *
	 * @var int
	 */
	const MAX_QUIZ_TEXT_LENGTH = 500;

	/**
	 * Sanitize a string value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function sanitize_string( $value ) {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitize an email address.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function sanitize_email( $value ) {
		return sanitize_email( (string) $value );
	}

	/**
	 * Sanitize a boolean value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return bool
	 */
	public static function sanitize_boolean( $value ) {
		return (bool) rest_sanitize_boolean( $value );
	}

	/**
	 * Sanitize an integer value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return int
	 */
	public static function sanitize_integer( $value ) {
		return absint( $value );
	}

	/**
	 * Sanitize a UUID (alphanumeric and hyphens only).
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function sanitize_uuid( $value ) {
		$sanitized = self::sanitize_string( $value );
		return preg_replace( '/[^a-f0-9\-]/i', '', $sanitized );
	}

	/**
	 * Sanitize OTP code (numeric only, 6 digits).
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function sanitize_otp( $value ) {
		return preg_replace( '/[^0-9]/', '', (string) $value );
	}

	/**
	 * Sanitize a file name.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function sanitize_filename( $value ) {
		return sanitize_file_name( (string) $value );
	}

	/**
	 * Sanitize JSON data (decode, sanitize, re-encode).
	 *
	 * @param mixed $value JSON string or array.
	 * @return array|null
	 */
	public static function sanitize_json( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( null === $decoded ) {
				return null;
			}
			$value = $decoded;
		}

		if ( ! is_array( $value ) ) {
			return null;
		}

		return self::sanitize_array_recursive( $value );
	}

	/**
	 * Recursively sanitize an array.
	 *
	 * @param array $array Array to sanitize.
	 * @return array
	 */
	public static function sanitize_array_recursive( $array ) {
		$sanitized = array();

		foreach ( $array as $key => $value ) {
			$key = self::sanitize_string( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_array_recursive( $value );
			} else {
				$sanitized[ $key ] = self::sanitize_string( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Validate an email address.
	 *
	 * @param string $email Email to validate.
	 * @return true|WP_Error
	 */
	public static function validate_email( $email ) {
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'Please provide a valid email address.', 'mystic-palm-reading' )
			);
		}

		return true;
	}

	/**
	 * Validate a required string field.
	 *
	 * @param string $value     Value to validate.
	 * @param string $field_name Field name for error message.
	 * @param int    $min_length Minimum length (default: 1).
	 * @param int    $max_length Maximum length (default: 255).
	 * @return true|WP_Error
	 */
	public static function validate_string( $value, $field_name = 'field', $min_length = 1, $max_length = 255 ) {
		$length = strlen( $value );

		if ( $length < $min_length ) {
			return new WP_Error(
				'invalid_input',
				sprintf(
					/* translators: %s: field name */
					__( 'The %s is required.', 'mystic-palm-reading' ),
					$field_name
				)
			);
		}

		if ( $length > $max_length ) {
			return new WP_Error(
				'invalid_input',
				sprintf(
					/* translators: 1: field name, 2: maximum length */
					__( 'The %1$s must be less than %2$d characters.', 'mystic-palm-reading' ),
					$field_name,
					$max_length
				)
			);
		}

		return true;
	}

	/**
	 * Validate identity field.
	 *
	 * @param string $identity Identity value.
	 * @return true|WP_Error
	 */
	public static function validate_identity( $identity ) {
		if ( ! in_array( $identity, self::ALLOWED_IDENTITIES, true ) ) {
			return new WP_Error(
				'invalid_identity',
				__( 'Please select a valid identity option.', 'mystic-palm-reading' )
			);
		}

		return true;
	}

	/**
	 * Validate GDPR consent.
	 *
	 * @param bool $gdpr GDPR consent value.
	 * @return true|WP_Error
	 */
	public static function validate_gdpr( $gdpr ) {
		if ( true !== $gdpr ) {
			return new WP_Error(
				'gdpr_required',
				__( 'You must accept the privacy policy to continue.', 'mystic-palm-reading' )
			);
		}

		return true;
	}

	/**
	 * Validate OTP code format.
	 *
	 * @param string $otp OTP code.
	 * @return true|WP_Error
	 */
	public static function validate_otp( $otp ) {
		if ( strlen( $otp ) !== 6 || ! ctype_digit( $otp ) ) {
			return new WP_Error(
				'invalid_otp',
				__( 'Please enter a valid 6-digit verification code.', 'mystic-palm-reading' )
			);
		}

		return true;
	}

	/**
	 * Validate UUID format.
	 *
	 * @param string $uuid UUID to validate.
	 * @return true|WP_Error
	 */
	public static function validate_uuid( $uuid ) {
		$pattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';

		if ( ! preg_match( $pattern, $uuid ) ) {
			return new WP_Error(
				'invalid_uuid',
				__( 'Invalid identifier format.', 'mystic-palm-reading' )
			);
		}

		return true;
	}

	/**
	 * Validate image file size.
	 *
	 * @param int $size File size in bytes.
	 * @return true|WP_Error
	 */
	public static function validate_image_size( $size ) {
		if ( $size > self::MAX_IMAGE_SIZE ) {
			return new WP_Error(
				'image_too_large',
				sprintf(
					/* translators: %d: maximum file size in MB */
					__( 'Image file is too large. Maximum size is %d MB.', 'mystic-palm-reading' ),
					self::MAX_IMAGE_SIZE / 1024 / 1024
				)
			);
		}

		return true;
	}

	/**
	 * Validate image MIME type.
	 *
	 * @param string $mime_type MIME type.
	 * @return true|WP_Error
	 */
	public static function validate_image_type( $mime_type ) {
		if ( ! in_array( $mime_type, self::ALLOWED_IMAGE_TYPES, true ) ) {
			return new WP_Error(
				'invalid_image_type',
				__( 'Invalid file type. Please upload a JPEG or PNG image.', 'mystic-palm-reading' )
			);
		}

		return true;
	}

	/**
	 * Validate quiz answers structure.
	 *
	 * @param array $answers Quiz answers array.
	 * @return true|WP_Error
	 */
	public static function validate_quiz_answers( $answers ) {
		if ( ! is_array( $answers ) || empty( $answers ) ) {
			return new WP_Error(
				'invalid_quiz',
				__( 'Please answer all quiz questions.', 'mystic-palm-reading' )
			);
		}

		// Validate quiz structure (must have answers for required questions).
		$required_keys = array( 'energy', 'focus', 'element', 'intentions', 'goals' );
		foreach ( $required_keys as $key ) {
			if ( ! isset( $answers[ $key ] ) || '' === $answers[ $key ] ) {
				return new WP_Error(
					'incomplete_quiz',
					__( 'Please complete all quiz questions.', 'mystic-palm-reading' )
				);
			}
		}

		// Validate free text length.
		if ( isset( $answers['goals'] ) && is_string( $answers['goals'] ) ) {
			if ( strlen( $answers['goals'] ) > self::MAX_QUIZ_TEXT_LENGTH ) {
				return new WP_Error(
					'quiz_text_too_long',
					sprintf(
						/* translators: %d: maximum character length */
						__( 'Your answer is too long. Maximum %d characters allowed.', 'mystic-palm-reading' ),
						self::MAX_QUIZ_TEXT_LENGTH
					)
				);
			}
		}

		return true;
	}

	/**
	 * Mask an email for logging (preserve privacy).
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	public static function mask_email( $email ) {
		if ( empty( $email ) || false === strpos( $email, '@' ) ) {
			return $email;
		}

		list( $user, $domain ) = explode( '@', $email, 2 );
		$masked_user           = strlen( $user ) > 2 ? substr( $user, 0, 2 ) . '***' : substr( $user, 0, 1 ) . '***';

		return $masked_user . '@' . $domain;
	}

	/**
	 * Mask sensitive data for logging.
	 *
	 * @param string $data Sensitive data.
	 * @param int    $show_chars Number of characters to show at start/end.
	 * @return string
	 */
	public static function mask_sensitive( $data, $show_chars = 4 ) {
		if ( empty( $data ) || strlen( $data ) <= ( $show_chars * 2 ) ) {
			return '***';
		}

		return substr( $data, 0, $show_chars ) . '***' . substr( $data, -$show_chars );
	}

	/**
	 * Get allowed HTML tags for AI-generated content.
	 *
	 * @return array
	 */
	public static function get_allowed_html_tags() {
		return array(
			'h2'     => array(),
			'h3'     => array(),
			'p'      => array(),
			'strong' => array(),
			'em'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'br'     => array(),
		);
	}

	/**
	 * Sanitize HTML content using whitelist.
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	public static function sanitize_html( $html ) {
		return wp_kses( $html, self::get_allowed_html_tags() );
	}

	/**
	 * Escape HTML output.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	public static function escape_html( $text ) {
		return esc_html( $text );
	}

	/**
	 * Escape HTML attribute.
	 *
	 * @param string $attr Attribute value to escape.
	 * @return string
	 */
	public static function escape_attr( $attr ) {
		return esc_attr( $attr );
	}

	/**
	 * Escape URL.
	 *
	 * @param string $url URL to escape.
	 * @return string
	 */
	public static function escape_url( $url ) {
		return esc_url( $url );
	}

	/**
	 * Standardized error response helper.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message (optional, uses generic if empty).
	 * @return WP_Error
	 */
	public static function error( $code = 'invalid_input', $message = '' ) {
		if ( empty( $message ) ) {
			$message = self::get_generic_error_message();
		}

		return new WP_Error( $code, $message );
	}

	/**
	 * Get generic user-friendly error message.
	 *
	 * @return string
	 */
	public static function get_generic_error_message() {
		return __( 'Something went wrong. Please check your information and try again.', 'mystic-palm-reading' );
	}
}
