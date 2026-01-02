<?php
/**
 * Full Template Renderer Class
 *
 * Renders the paid (full) reading by reusing the teaser template output
 * and stripping lock UI while injecting premium section content.
 *
 * @package MysticPalmReading
 * @since 2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Full_Template_Renderer
 *
 * Service for rendering paid full readings.
 */
class SM_Full_Template_Renderer {

	/**
	 * Singleton instance
	 *
	 * @var SM_Full_Template_Renderer|null
	 */
	private static $instance = null;

	/**
	 * Template file path
	 *
	 * @var string
	 */
	private $template_path;

	/**
	 * Initialize the renderer (singleton pattern)
	 *
	 * @return SM_Full_Template_Renderer
	 */
	public static function init() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get singleton instance
	 *
	 * @return SM_Full_Template_Renderer
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::init();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$template_style = get_option( 'sm_report_template', 'traditional' );
		$template_file  = ( 'swipeable-cards' === $template_style )
			? 'aura-reading-template-swipe-full.html'
			: 'aura-reading-template-full.html';
		$this->template_path = plugin_dir_path( dirname( __FILE__ ) ) . $template_file;
	}

	/**
	 * Render a paid reading by reading_id.
	 *
	 * @param string $reading_id Reading UUID.
	 * @return string|WP_Error Rendered HTML on success, WP_Error on failure.
	 */
	public function render_reading( $reading_id ) {
		return $this->render_reading_with_template( $reading_id, $this->template_path );
	}

	/**
	 * Render a paid reading with a specific template path.
	 *
	 * @param string $reading_id Reading UUID.
	 * @param string $template_path Template path.
	 * @return string|WP_Error Rendered HTML on success, WP_Error on failure.
	 */
	public function render_reading_with_template( $reading_id, $template_path ) {
		$reading_service = SM_Reading_Service::get_instance();
		$reading_info    = $reading_service->get_reading_with_unlock_info( $reading_id );

		if ( is_wp_error( $reading_info ) ) {
			return $reading_info;
		}

		$reading = $reading_info['reading'];
		if ( empty( $reading->content_data_parsed ) ) {
			return new WP_Error(
				'missing_content',
				__( 'Reading content is empty or invalid.', 'mystic-palm-reading' )
			);
		}

		$data     = $reading->content_data_parsed;
		$renderer = SM_Template_Renderer::get_instance();
		$html     = $renderer->render_reading_with_template( $reading_id, $template_path );

		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$html = $this->apply_full_unlocks( $html, $data );
		$html = $this->replace_premium_sections( $html, $data );
		$html = $this->strip_lock_ui( $html );

		return $html;
	}

	/**
	 * Replace premium section placeholders with full content.
	 *
	 * @param string $html Rendered HTML.
	 * @param array  $data Reading data.
	 * @return string
	 */
	private function replace_premium_sections( $html, $data ) {
		$section_map = array(
			'section-deep-love'        => array( 'key' => 'deep_relationship_analysis', 'field' => 'full_content' ),
			'section-extended-timeline'=> array( 'key' => 'extended_timeline_12_months', 'field' => 'full_content' ),
			'section-life-purpose'     => array( 'key' => 'life_purpose_soul_mission', 'field' => 'full_content' ),
			'section-shadow-work'      => array( 'key' => 'shadow_work_transformation', 'field' => 'full_content' ),
			'section-action-plan'      => array( 'key' => 'practical_guidance_action_plan', 'field' => 'full_content' ),
		);

		foreach ( $section_map as $section_class => $config ) {
			$key   = $config['key'];
			$field = $config['field'];
			if ( empty( $data[ $key ][ $field ] ) ) {
				continue;
			}

			$html = $this->replace_section_content(
				$html,
				$section_class,
				$data[ $key ][ $field ]
			);
		}

		return $html;
	}

	/**
	 * Replace the section-content block for a given section class.
	 *
	 * @param string $html Rendered HTML.
	 * @param string $section_class Section class name.
	 * @param string $content Full content text.
	 * @return string
	 */
	private function replace_section_content( $html, $section_class, $content ) {
		$paragraphs = $this->split_into_paragraphs( $content );
		if ( empty( $paragraphs ) ) {
			return $html;
		}

		$replacement = "\n          " . implode( "\n          ", $paragraphs ) . "\n        ";
		$pattern     = '/(<section class="reading-section ' . preg_quote( $section_class, '/' ) . '.*?<div class="section-content">)(.*?)(<\/div>)/s';

		return preg_replace( $pattern, '$1' . $replacement . '$3', $html, 1 );
	}

	/**
	 * Force unlocked content for paid readings, even if unlock metadata is stale.
	 *
	 * @param string $html Rendered HTML.
	 * @param array  $data Reading data.
	 * @return string
	 */
	private function apply_full_unlocks( $html, $data ) {
		$locked_section_map = array(
			'section-love'       => 'love_patterns',
			'section-challenges' => 'challenges_opportunities',
			'section-phase'      => 'life_phase',
			'section-timeline'   => 'timeline_6_months',
		);

		foreach ( $locked_section_map as $section_class => $data_key ) {
			if ( empty( $data[ $data_key ] ) ) {
				continue;
			}
			$section_data = $data[ $data_key ];
			$full = '';
			if ( is_array( $section_data ) ) {
				if ( ! empty( $section_data['locked_full'] ) ) {
					$full = $section_data['locked_full'];
				} elseif ( ! empty( $section_data['preview'] ) ) {
					$full = $section_data['preview'];
				} elseif ( ! empty( $section_data['preview_p1'] ) ) {
					$full = $section_data['preview_p1'];
				}
			}
			if ( '' === trim( (string) $full ) ) {
				continue;
			}
			$html = $this->replace_section_content( $html, $section_class, $full );
		}

		// Guidance uses a special card layout.
		if ( ! empty( $data['guidance'] ) && is_array( $data['guidance'] ) ) {
			$guidance = $data['guidance'];
			$full = '';
			if ( ! empty( $guidance['locked_full'] ) ) {
				$full = $guidance['locked_full'];
			} elseif ( ! empty( $guidance['preview'] ) ) {
				$full = $guidance['preview'];
			} elseif ( ! empty( $guidance['preview_p1'] ) ) {
				$full = $guidance['preview_p1'];
			}
			if ( '' !== trim( (string) $full ) ) {
				$title = 'Your Focus Points';
				$pattern = '/(<section class="reading-section section-guidance.*?<div class="guidance-text">\\s*<h3>)(.*?)(<\\/h3>\\s*<p>)(.*?)(<\\/p>)/s';
				$replacement = '$1' . esc_html( $title ) . '$3' . esc_html( $full ) . '$5';
				$html = preg_replace( $pattern, $replacement, $html, 1 );
			}
		}

		// Remove lock classes and data-lock attributes that can keep blur styles.
		$html = preg_replace( '/\\s+locked(?=[\\s"])/', '', $html );
		$html = preg_replace( '/\\s+data-lock="[^"]*"/', '', $html );

		return $html;
	}

	/**
	 * Convert text into HTML paragraphs.
	 *
	 * @param string $content Full content.
	 * @return array
	 */
	private function split_into_paragraphs( $content ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return array();
		}

		$sentences = preg_split( '/(?<=[.!?])\s+/', $content );
		$paragraphs = array();
		$current = '';
		$count   = 0;

		foreach ( $sentences as $sentence ) {
			$current .= $sentence . ' ';
			$count++;

			if ( $count >= 3 ) {
				$paragraphs[] = '<p>' . "\n            " . esc_html( trim( $current ) ) . "\n          </p>";
				$current = '';
				$count   = 0;
			}
		}

		if ( '' !== trim( $current ) ) {
			$paragraphs[] = '<p>' . "\n            " . esc_html( trim( $current ) ) . "\n          </p>";
		}

		return $paragraphs;
	}

	/**
	 * Strip lock overlays, buttons, and lock metadata from HTML.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	private function strip_lock_ui( $html ) {
		$html = preg_replace( '/\s*<div class="lock-overlay[^"]*">[\s\S]*?<\/div>\s*/', '', $html );
		$html = preg_replace( '/<button[^>]*\bbtn-unlock\b[^>]*>[\s\S]*?<\/button>/', '', $html );
		$html = preg_replace( '/\s*<div class="lock-card[^"]*">[\s\S]*?<\/div>\s*/', '', $html );
		$html = preg_replace( '/\s*<div class="lock-counter"[^>]*>[\s\S]*?<\/div>\s*/', '', $html );
		$html = preg_replace( '/\s*<div class="sm-modal__counter"[^>]*>[\s\S]*?<\/div>\s*/', '', $html );
		$html = preg_replace( '/\sdata-lock="[^"]*"/', '', $html );
		$html = preg_replace( '/\sdata-unlock="[^"]*"/', '', $html );
		$html = preg_replace( '/\sdata-unlock-badge="[^"]*"/', '', $html );
		$html = preg_replace( '/\sdata-unlock-counter="[^"]*"/', '', $html );
		$html = preg_replace( '/\sdata-unlock-remaining="[^"]*"/', '', $html );
		$html = preg_replace_callback(
			'/class="([^"]*)"/',
			function ( $matches ) {
				$classes = preg_split( '/\s+/', $matches[1] );
				$classes = array_filter(
					$classes,
					function ( $class ) {
						return ! in_array( $class, array( 'locked', 'premium-locked' ), true );
					}
				);
				return 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
			},
			$html
		);

		return $html;
	}
}
