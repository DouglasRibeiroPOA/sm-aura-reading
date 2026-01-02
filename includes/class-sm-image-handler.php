<?php
/**
 * Image handler for palm uploads.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages palm image validation, storage, and cleanup.
 */
class SM_Image_Handler {

	/**
	 * Maximum file size in bytes (5MB).
	 */
	const MAX_FILE_SIZE = 5242880;

	/**
	 * Allowed MIME types.
	 *
	 * @var array<string,string>
	 */
	private $allowed_mimes = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
	);

	/**
	 * Singleton instance.
	 *
	 * @var SM_Image_Handler|null
	 */
	private static $instance = null;

	/**
	 * Base storage directory (non-public).
	 *
	 * @var string
	 */
	private $storage_dir = '';

	/**
	 * Lead handler dependency.
	 *
	 * @var SM_Lead_Handler|null
	 */
	private $lead_handler = null;

	/**
	 * Initialize and return instance.
	 *
	 * @return SM_Image_Handler
	 */
	public static function init() {
		return self::get_instance();
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @return SM_Image_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->lead_handler = class_exists( 'SM_Lead_Handler' ) ? SM_Lead_Handler::init() : null;
		$this->prepare_storage_dir();
	}

	/**
	 * Handle a base64 image payload (camera capture).
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $base64  Base64 encoded image (may include data URI prefix).
	 * @return array|WP_Error {
	 *     image_url: string Reference-safe URL (not publicly accessible).
	 *     image_path: string Absolute path to stored file.
	 *     mime_type: string MIME type.
	 *     size: int File size in bytes.
	 * }
	 */
	public function upload_base64( $lead_id, $base64 ) {
		$lead_id = $this->sanitize_lead_id( $lead_id );

		$lead = $this->get_lead( $lead_id );
		if ( empty( $lead ) ) {
			return new WP_Error( 'invalid_lead', __( 'Invalid lead reference.', 'mystic-palm-reading' ) );
		}

		$rate_check = $this->check_rate_limit( $lead_id );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$decoded = $this->decode_base64( $base64 );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		if ( strlen( $decoded ) > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'file_too_large', __( 'Image file too large. Maximum size is 5MB.', 'mystic-palm-reading' ) );
		}

		$validated = $this->validate_image_bytes( $decoded );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$saved = $this->save_image( $lead_id, $validated['resource'], $validated['mime'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->log(
			'info',
			'IMAGE_UPLOADED',
			'Palm image uploaded (base64)',
			array(
				'lead_id'   => $lead_id,
				'mime_type' => $validated['mime'],
				'file'      => basename( $saved['path'] ),
				'size'      => $saved['size'],
				'width'     => isset( $validated['width'] ) ? (int) $validated['width'] : 0,
				'height'    => isset( $validated['height'] ) ? (int) $validated['height'] : 0,
			)
		);

		return array(
			'image_url'  => $this->generate_private_url( basename( $saved['path'] ) ),
			'image_path' => $saved['path'],
			'mime_type'  => $validated['mime'],
			'size'       => $saved['size'],
		);
	}

	/**
	 * Handle an uploaded file array (multipart/form-data).
	 *
	 * @param string $lead_id Lead UUID.
	 * @param array  $file    $_FILES style array.
	 * @return array|WP_Error
	 */
	public function upload_file( $lead_id, $file ) {
		$lead_id = $this->sanitize_lead_id( $lead_id );

		$lead = $this->get_lead( $lead_id );
		if ( empty( $lead ) ) {
			return new WP_Error( 'invalid_lead', __( 'Invalid lead reference.', 'mystic-palm-reading' ) );
		}

		$rate_check = $this->check_rate_limit( $lead_id );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$normalized = $this->normalize_file_array( $file );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		if ( (int) $normalized['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'file_too_large', __( 'Image file too large. Maximum size is 5MB.', 'mystic-palm-reading' ) );
		}

		$bytes = file_get_contents( $normalized['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $bytes ) {
			return new WP_Error( 'file_read_error', __( 'Could not read the uploaded file.', 'mystic-palm-reading' ) );
		}

		$validated = $this->validate_image_bytes( $bytes );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$saved = $this->save_image( $lead_id, $validated['resource'], $validated['mime'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->log(
			'info',
			'IMAGE_UPLOADED',
			'Palm image uploaded (file)',
			array(
				'lead_id'   => $lead_id,
				'mime_type' => $validated['mime'],
				'file'      => basename( $saved['path'] ),
				'size'      => $saved['size'],
				'width'     => isset( $validated['width'] ) ? (int) $validated['width'] : 0,
				'height'    => isset( $validated['height'] ) ? (int) $validated['height'] : 0,
			)
		);

		return array(
			'image_url'  => $this->generate_private_url( basename( $saved['path'] ) ),
			'image_path' => $saved['path'],
			'mime_type'  => $validated['mime'],
			'size'       => $saved['size'],
		);
	}

	/**
	 * Delete a stored image by filename for a lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $filename Stored filename.
	 * @return bool True on success, false otherwise.
	 */
	public function delete_image( $lead_id, $filename ) {
		$lead_id  = $this->sanitize_lead_id( $lead_id );
		$filename = sanitize_file_name( $filename );

		if ( '' === $filename ) {
			return false;
		}

		$path = $this->build_file_path( $lead_id, $filename );
		if ( ! $path || ! file_exists( $path ) ) {
			return false;
		}

		$deleted = unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		if ( $deleted ) {
			$this->log(
				'info',
				'IMAGE_DELETED',
				'Palm image deleted',
				array(
					'lead_id' => $lead_id,
					'file'    => $filename,
				)
			);
		}

		return $deleted;
	}

	/**
	 * Cleanup all stored images for a lead (used after reading generation).
	 *
	 * @param string $lead_id Lead UUID.
	 * @return void
	 */
	public function cleanup_lead_images( $lead_id ) {
		$lead_id = $this->sanitize_lead_id( $lead_id );
		$dir     = $this->get_lead_dir( $lead_id );

		if ( ! $dir || ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( trailingslashit( $dir ) . '*' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob

		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rmdir_rmdir

		$this->log(
			'info',
			'IMAGE_CLEANUP',
			'Lead image directory cleaned up',
			array(
				'lead_id' => $lead_id,
			)
		);
	}

	/**
	 * Generate a private reference URL (not publicly accessible).
	 *
	 * @param string $filename Stored filename.
	 * @return string
	 */
	private function generate_private_url( $filename ) {
		$filename = sanitize_file_name( $filename );
		return 'sm-private://' . $filename;
	}

	/**
	 * Decode a base64 string, stripping data URI prefixes.
	 *
	 * @param string $base64 Base64 string.
	 * @return string|WP_Error
	 */
	private function decode_base64( $base64 ) {
		$normalized = trim( (string) $base64 );

		if ( strpos( $normalized, ',' ) !== false ) {
			$parts      = explode( ',', $normalized, 2 );
			$normalized = end( $parts );
		}

		$decoded = base64_decode( $normalized, true );

		if ( false === $decoded ) {
			return new WP_Error( 'invalid_image', __( 'Invalid image data.', 'mystic-palm-reading' ) );
		}

		return $decoded;
	}

	/**
	 * Validate image bytes and return GD resource + MIME.
	 *
	 * @param string $bytes Raw bytes.
	 * @return array|WP_Error
	 */
	private function validate_image_bytes( $bytes ) {
		$mime = '';

		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = $finfo ? finfo_buffer( $finfo, $bytes ) : '';

			if ( $finfo ) {
				finfo_close( $finfo );
			}
		}

		if ( empty( $mime ) ) {
			$info = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $info && ! empty( $info['mime'] ) ) {
				$mime = $info['mime'];
			}
		}

		if ( empty( $mime ) || ! isset( $this->allowed_mimes[ $mime ] ) ) {
			return new WP_Error( 'invalid_mime', __( 'Invalid file type. Please upload a JPEG or PNG.', 'mystic-palm-reading' ) );
		}

		$image = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $image ) {
			return new WP_Error( 'corrupt_image', __( 'The image appears to be corrupted. Please try another.', 'mystic-palm-reading' ) );
		}

		$size = getimagesizefromstring( $bytes );
		if ( false === $size || empty( $size[0] ) || empty( $size[1] ) ) {
			imagedestroy( $image );
			return new WP_Error( 'invalid_image_dimensions', __( 'Invalid image dimensions.', 'mystic-palm-reading' ) );
		}

		return array(
			'resource' => $image,
			'mime'     => $mime,
			'width'    => (int) $size[0],
			'height'   => (int) $size[1],
		);
	}

	/**
	 * Save GD resource to storage after stripping EXIF.
	 *
	 * @param string   $lead_id Lead UUID.
	 * @param resource $resource GD image resource.
	 * @param string   $mime     MIME type.
	 * @return array|WP_Error
	 */
	private function save_image( $lead_id, $resource, $mime ) {
		if ( '' === $this->storage_dir ) {
			return new WP_Error( 'storage_unavailable', __( 'Storage is not available for images.', 'mystic-palm-reading' ) );
		}

		$dir = $this->get_lead_dir( $lead_id );

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'storage_unavailable', __( 'Unable to prepare storage for the image.', 'mystic-palm-reading' ) );
		}

		$this->write_protection_files( $dir );

		$filename = $this->generate_filename( $lead_id, $mime );
		$path     = trailingslashit( $dir ) . $filename;

		$written = $this->write_image_file( $path, $resource, $mime );
		imagedestroy( $resource );

		if ( is_wp_error( $written ) ) {
			return $written;
		}

		$this->strip_exif( $path );

		$file_size = (int) filesize( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize

		return array(
			'path' => $path,
			'size' => $file_size,
		);
	}

	/**
	 * Write an image file to disk based on MIME type.
	 *
	 * @param string   $path     Destination path.
	 * @param resource $resource GD image resource.
	 * @param string   $mime     MIME type.
	 * @return true|WP_Error
	 */
	private function write_image_file( $path, $resource, $mime ) {
		switch ( $mime ) {
			case 'image/jpeg':
				$saved = imagejpeg( $resource, $path, 90 );
				break;
			case 'image/png':
				$saved = imagepng( $resource, $path, 6 );
				break;
			default:
				return new WP_Error( 'invalid_mime', __( 'Unsupported image format.', 'mystic-palm-reading' ) );
		}

		if ( false === $saved ) {
			return new WP_Error( 'write_failed', __( 'Could not save the image.', 'mystic-palm-reading' ) );
		}

		return true;
	}

	/**
	 * Strip EXIF metadata by re-saving via WP Image Editor.
	 *
	 * @param string $path File path.
	 * @return void
	 */
	private function strip_exif( $path ) {
		$editor = wp_get_image_editor( $path );

		if ( is_wp_error( $editor ) ) {
			return;
		}

		$editor->save( $path );
	}

	/**
	 * Normalize and validate a file array.
	 *
	 * @param array $file File array.
	 * @return array|WP_Error
	 */
	private function normalize_file_array( $file ) {
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'invalid_file', __( 'No file provided.', 'mystic-palm-reading' ) );
		}

		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'upload_error', __( 'Upload failed. Please try again.', 'mystic-palm-reading' ) );
		}

		if ( ! file_exists( $file['tmp_name'] ) ) {
			return new WP_Error( 'upload_missing', __( 'Uploaded file is missing.', 'mystic-palm-reading' ) );
		}

		return array(
			'tmp_name' => $file['tmp_name'],
			'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
			'name'     => isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '',
		);
	}

	/**
	 * Prepare storage directory and protection files.
	 *
	 * @return void
	 */
	private function prepare_storage_dir() {
		$uploads   = wp_upload_dir();
		$base_dir  = ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
		$this->storage_dir = trailingslashit( $base_dir ) . 'sm-aura-private';

		if ( ! wp_mkdir_p( $this->storage_dir ) ) {
			return;
		}

		$this->write_protection_files( $this->storage_dir );
	}

	/**
	 * Create .htaccess and index.php to block direct access.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function write_protection_files( $dir ) {
		$index_path    = trailingslashit( $dir ) . 'index.php';
		$htaccess_path = trailingslashit( $dir ) . '.htaccess';

		if ( ! file_exists( $index_path ) ) {
			file_put_contents( $index_path, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		if ( ! file_exists( $htaccess_path ) ) {
			$rules = "Options -Indexes\n";
			$rules .= "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n";
			$rules .= "<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n";
			file_put_contents( $htaccess_path, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Build a file path for a lead-specific filename.
	 *
	 * @param string $lead_id  Lead UUID.
	 * @param string $filename File name.
	 * @return string
	 */
	private function build_file_path( $lead_id, $filename ) {
		$dir = $this->get_lead_dir( $lead_id );
		return trailingslashit( $dir ) . sanitize_file_name( $filename );
	}

	/**
	 * Get directory for a specific lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return string
	 */
	private function get_lead_dir( $lead_id ) {
		return trailingslashit( $this->storage_dir ) . sanitize_file_name( $lead_id );
	}

	/**
	 * Generate a unique filename for an upload.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $mime    MIME type.
	 * @return string
	 */
	private function generate_filename( $lead_id, $mime ) {
		$ext = $this->allowed_mimes[ $mime ];
		return sanitize_file_name( $lead_id . '-' . wp_generate_uuid4() . '.' . $ext );
	}

	/**
	 * Sanitize lead ID input.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return string
	 */
	private function sanitize_lead_id( $lead_id ) {
		return sanitize_text_field( (string) $lead_id );
	}

	/**
	 * Retrieve lead by ID if handler is available.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return array|null
	 */
	private function get_lead( $lead_id ) {
		if ( $this->lead_handler instanceof SM_Lead_Handler ) {
			return $this->lead_handler->get_lead_by_id( $lead_id );
		}

		return null;
	}

	/**
	 * Enforce upload rate limiting per lead (5 per hour).
	 *
	 * @param string $lead_id Lead UUID.
	 * @return true|WP_Error
	 */
	private function check_rate_limit( $lead_id ) {
		return SM_Rate_Limiter::check(
			SM_Rate_Limiter::build_key(
				'image_upload',
				array(
					$lead_id,
					$this->get_client_ip(),
				)
			),
			5,
			HOUR_IN_SECONDS,
			array(
				'lead_id' => $lead_id,
			)
		);
	}

	/**
	 * Proxy logging.
	 *
	 * @param string $level      Level.
	 * @param string $event_type Event type.
	 * @param string $message    Message.
	 * @param array  $context    Context.
	 * @return void
	 */
	private function log( $level, $event_type, $message, $context = array() ) {
		if ( class_exists( 'SM_Logger' ) ) {
			SM_Logger::log( $level, $event_type, $message, $context );
		}
	}

	/**
	 * Get client IP for logging and rate limits.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return 'unknown';
	}
}
