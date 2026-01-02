<?php
/**
 * Template Renderer Class
 *
 * Renders the teaser reading template by populating it with JSON data from the database.
 * Handles locked/unlocked sections, trait bars, modals, and proper escaping.
 *
 * @package MysticPalmReading
 * @since 1.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Template_Renderer
 *
 * Service for rendering teaser reading templates with dynamic content.
 */
class SM_Template_Renderer {

	/**
	 * Singleton instance
	 *
	 * @var SM_Template_Renderer|null
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
	 * @return SM_Template_Renderer
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
	 * @return SM_Template_Renderer
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
			? 'aura-reading-template-swipe-teaser.html'
			: 'aura-reading-template-teaser.html';
		$this->template_path = plugin_dir_path( dirname( __FILE__ ) ) . $template_file;
	}

	/**
	 * Render a teaser reading by reading_id
	 *
	 * @param string $reading_id Reading UUID.
	 * @return string|WP_Error Rendered HTML on success, WP_Error on failure.
	 */
	public function render_reading( $reading_id ) {
		return $this->render_reading_with_template( $reading_id, $this->template_path );
	}

	/**
	 * Render a reading with a custom template path.
	 *
	 * @param string $reading_id Reading UUID.
	 * @param string $template_path Template path.
	 * @return string|WP_Error Rendered HTML on success, WP_Error on failure.
	 */
	public function render_reading_with_template( $reading_id, $template_path ) {
		// Get reading service
		$reading_service = SM_Reading_Service::get_instance();

		// Load reading with unlock info
		$reading_info = $reading_service->get_reading_with_unlock_info( $reading_id );

		if ( is_wp_error( $reading_info ) ) {
			return $reading_info;
		}

		$reading = $reading_info['reading'];

		// Parse JSON content
		if ( empty( $reading->content_data_parsed ) ) {
			return new WP_Error(
				'missing_content',
				__( 'Reading content is empty or invalid.', 'mystic-palm-reading' )
			);
		}

		$data = $reading->content_data_parsed;
		$reading_type = ! empty( $reading->reading_type ) ? $reading->reading_type : 'aura_teaser';

		// Extract gender and numeric reading ID for quote selection
		$gender = isset( $data['meta']['gender'] ) ? $data['meta']['gender'] : 'female';
		$numeric_id = ! empty( $reading->id ) ? (int) $reading->id : crc32( $reading_id );

		SM_Logger::log(
			'info',
			'TEMPLATE_RENDERER',
			'Reading data summary',
			array(
				'reading_id'   => $reading_id,
				'reading_type' => $reading_type,
				'summary'      => $this->summarize_reading_data( $data ),
			)
		);

		// Load template
		$template = $this->load_template_from_path( $template_path );
		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$is_swipe_template = ( false !== strpos( $template, 'sm-swipe-template' ) );
		if ( $is_swipe_template ) {
			$template = $this->populate_swipe_template_content( $template, $data, $reading_info );
		}
		SM_Logger::log(
			'info',
			'TEMPLATE_RENDERER',
			'Template loaded for reading',
			array(
				'reading_id'   => $reading_id,
				'reading_type' => $reading_type,
				'template'     => basename( $template_path ),
				'is_swipe'     => $is_swipe_template ? 'yes' : 'no',
			)
		);

		$plugin_url = defined( 'SM_AURA_PLUGIN_URL' ) ? SM_AURA_PLUGIN_URL : plugin_dir_url( dirname( __FILE__ ) );
		$template   = str_replace( '{{PLUGIN_URL}}', esc_url( $plugin_url ), $template );

		// Inject reading_id, lead_id, and offerings_url into the template DOM
		$offerings_url = SM_Settings::init()->get_offerings_url();
		$lead_id       = isset( $reading->lead_id ) ? $reading->lead_id : '';
		$is_logged_in  = SM_Auth_Handler::get_instance()->is_user_logged_in();
		$has_full_access = ! empty( $reading_info['has_full_access'] );
		$unlock_count  = isset( $reading->unlock_count ) ? (int) $reading->unlock_count : 0;
		$max_unlocks   = class_exists( 'SM_Unlock_Handler' ) ? SM_Unlock_Handler::MAX_FREE_UNLOCKS : 2;
		$unlocked_sections = isset( $reading_info['unlocked_sections'] ) ? (array) $reading_info['unlocked_sections'] : array();
		$unlocked_attr = implode( ',', array_map( 'sanitize_key', $unlocked_sections ) );
		$template      = str_replace(
			'data-sm-id-placeholder',
			'data-reading-id="' . esc_attr( $reading_id ) . '"',
			$template
		);
		$template      = str_replace(
			'data-sm-lead-id-placeholder',
			'data-lead-id="' . esc_attr( $lead_id ) . '"',
			$template
		);
		$template      = str_replace(
			'data-sm-url-placeholder',
			'data-offerings-url="' . esc_attr( $offerings_url ) . '"',
			$template
		);
		$reading_type = ! empty( $reading->reading_type ) ? $reading->reading_type : 'aura_teaser';
		$template      = str_replace(
			'data-sm-status-placeholder',
			'data-is-logged-in="' . esc_attr( $is_logged_in ? '1' : '0' ) . '" data-has-full-access="' . esc_attr( $has_full_access ? '1' : '0' ) . '" data-reading-type="' . esc_attr( $reading_type ) . '"',
			$template
		);
		$template      = str_replace(
			'data-sm-home-url-placeholder',
			esc_url( home_url( '/' ) ),
			$template
		);
		$template      = str_replace(
			'data-sm-unlock-placeholder',
			'data-unlock-count="' . esc_attr( (string) $unlock_count ) . '" data-max-free-unlocks="' . esc_attr( (string) $max_unlocks ) . '" data-unlocked-sections="' . esc_attr( $unlocked_attr ) . '"',
			$template
		);
		$modal_love = '';
		$modal_career = '';
		$modal_alignment = '';
		if ( isset( $data['career_success'] ) && is_array( $data['career_success'] ) ) {
			$modal_love = isset( $data['career_success']['modal_love_patterns'] ) ? $data['career_success']['modal_love_patterns'] : '';
			$modal_career = isset( $data['career_success']['modal_career_direction'] ) ? $data['career_success']['modal_career_direction'] : '';
			$modal_alignment = isset( $data['career_success']['modal_life_alignment'] ) ? $data['career_success']['modal_life_alignment'] : '';
		}
		if ( '' === trim( (string) $modal_love ) ) {
			$modal_love = $this->build_modal_fallback(
				$this->get_section_text( $data, 'love_patterns', array( 'locked_full', 'preview', 'preview_p1', 'preview_p2' ) )
			);
		}
		if ( '' === trim( (string) $modal_career ) ) {
			$modal_career = $this->build_modal_fallback(
				$this->get_section_text( $data, 'career_success', array( 'main_paragraph' ) )
			);
		}
		if ( '' === trim( (string) $modal_alignment ) ) {
			$modal_alignment = $this->build_modal_fallback(
				$this->get_section_text( $data, 'guidance', array( 'locked_full', 'preview', 'preview_p1', 'preview_p2' ) )
			);
		}
		$template = str_replace(
			'data-modal-love-placeholder',
			'data-modal-love="' . esc_attr( $modal_love ) . '"',
			$template
		);
		$template = str_replace(
			'data-modal-career-placeholder',
			'data-modal-career="' . esc_attr( $modal_career ) . '"',
			$template
		);
		$template = str_replace(
			'data-modal-alignment-placeholder',
			'data-modal-alignment="' . esc_attr( $modal_alignment ) . '"',
			$template
		);

		// Inject login button (only if user not logged in and setting enabled)
		$template = $this->inject_login_button( $template );

		// Populate template with data
		$html = $this->populate_template( $template, $data, $reading_info, $gender, $numeric_id );

		return $html;
	}

	/**
	 * Summarize reading payload content lengths for logging.
	 *
	 * @param array $data Parsed reading data.
	 * @return array
	 */
	private function summarize_reading_data( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return array();
		}

		$summary = array(
			'opening_words' => $this->count_words( isset( $data['opening']['reflection_p1'] ) ? $data['opening']['reflection_p1'] : '' )
				+ $this->count_words( isset( $data['opening']['reflection_p2'] ) ? $data['opening']['reflection_p2'] : '' ),
			'life_foundations_words' => $this->count_words( isset( $data['life_foundations']['paragraph_1'] ) ? $data['life_foundations']['paragraph_1'] : '' )
				+ $this->count_words( isset( $data['life_foundations']['paragraph_2'] ) ? $data['life_foundations']['paragraph_2'] : '' )
				+ $this->count_words( isset( $data['life_foundations']['paragraph_3'] ) ? $data['life_foundations']['paragraph_3'] : '' ),
			'personality_intro_words' => $this->count_words( isset( $data['personality_traits']['intro'] ) ? $data['personality_traits']['intro'] : '' ),
			'career_words' => $this->count_words( isset( $data['career_success']['main_paragraph'] ) ? $data['career_success']['main_paragraph'] : '' ),
			'love_preview_words' => $this->count_words( isset( $data['love_patterns']['preview'] ) ? $data['love_patterns']['preview'] : '' ),
			'challenges_preview_words' => $this->count_words( isset( $data['challenges_opportunities']['preview'] ) ? $data['challenges_opportunities']['preview'] : '' ),
			'deep_love_full_words' => $this->count_words( isset( $data['deep_relationship_analysis']['full_content'] ) ? $data['deep_relationship_analysis']['full_content'] : '' ),
			'extended_timeline_full_words' => $this->count_words( isset( $data['extended_timeline_12_months']['full_content'] ) ? $data['extended_timeline_12_months']['full_content'] : '' ),
			'life_purpose_full_words' => $this->count_words( isset( $data['life_purpose_soul_mission']['full_content'] ) ? $data['life_purpose_soul_mission']['full_content'] : '' ),
			'shadow_work_full_words' => $this->count_words( isset( $data['shadow_work_transformation']['full_content'] ) ? $data['shadow_work_transformation']['full_content'] : '' ),
			'practical_guidance_full_words' => $this->count_words( isset( $data['practical_guidance_action_plan']['full_content'] ) ? $data['practical_guidance_action_plan']['full_content'] : '' ),
		);

		return $summary;
	}

	/**
	 * Count words in text content.
	 *
	 * @param string $text Text to count.
	 * @return int
	 */
	private function count_words( $text ) {
		$text = is_string( $text ) ? $text : '';
		$text = wp_strip_all_tags( $text );
		$text = trim( $text );
		if ( '' === $text ) {
			return 0;
		}

		return str_word_count( $text );
	}

	/**
	 * Load the HTML template file
	 *
	 * @return string|WP_Error Template HTML on success, WP_Error on failure.
	 */
	private function load_template() {
		return $this->load_template_from_path( $this->template_path );
	}

	/**
	 * Load the HTML template file by path.
	 *
	 * @param string $template_path Template path.
	 * @return string|WP_Error Template HTML on success, WP_Error on failure.
	 */
	private function load_template_from_path( $template_path ) {
		if ( empty( $template_path ) || ! file_exists( $template_path ) ) {
			SM_Logger::log(
				'error',
				'TEMPLATE_RENDERER',
				'Template file not found',
				array( 'path' => $template_path )
			);
			return new WP_Error(
				'template_not_found',
				__( 'Could not load reading template.', 'mystic-palm-reading' )
			);
		}

		$template = file_get_contents( $template_path );

		if ( false === $template ) {
			SM_Logger::log(
				'error',
				'TEMPLATE_RENDERER',
				'Failed to read template file',
				array( 'path' => $template_path )
			);
			return new WP_Error(
				'template_read_error',
				__( 'Could not read reading template.', 'mystic-palm-reading' )
			);
		}

		return $template;
	}

	/**
	 * Populate template with JSON data
	 *
	 * @param string $template      Template HTML.
	 * @param array  $data          Parsed JSON data from reading.
	 * @param array  $reading_info  Reading info with unlock state.
	 * @param string $gender        Gender for quote selection.
	 * @param int    $numeric_id    Numeric reading ID for quote selection.
	 * @return string Populated HTML.
	 */
	private function populate_template( $template, $data, $reading_info, $gender = 'female', $numeric_id = 0 ) {
		// Extract meta data
		$user_name = isset( $data['meta']['user_name'] ) ? $data['meta']['user_name'] : 'You';
		$generated_at = isset( $data['meta']['generated_at'] ) ? $data['meta']['generated_at'] : current_time( 'mysql' );

		// Format date
		$date = date_i18n( 'F j, Y', strtotime( $generated_at ) );
		$date_time = date_i18n( 'F j, Y H:i', strtotime( $generated_at ) );

		// Replace meta fields
		$template = str_replace( 'Alexandra', esc_html( $user_name ), $template );
		$template = str_replace( 'December 17, 2024', esc_html( $date ), $template );
		$template = str_replace( '{{READING_TIMESTAMP}}', esc_html( $date_time ), $template );

		// Populate Opening Reflection
		$template = $this->replace_section( $template, 'opening', $data );

		// Populate Palm Summary Snapshot
		$template = $this->replace_palm_summary_section( $template, $data );

		// Populate Life Foundations (always visible)
		$template = $this->replace_section( $template, 'life_foundations', $data );

		// Populate Love Patterns (check if unlocked)
		$template = $this->replace_locked_section( $template, 'love', 'love_patterns', $data, $reading_info, $gender, $numeric_id );

		// Populate Career & Success + Modals
		$template = $this->replace_section( $template, 'career_success', $data );
		$template = $this->replace_modals( $template, $data );

		// Populate Personality & Intuition intro + Trait Bars
		$template = $this->replace_personality_intro( $template, $data );
		$template = $this->replace_traits( $template, $data );

		// Populate Challenges & Opportunities (locked)
		$template = $this->replace_locked_section( $template, 'challenges', 'challenges_opportunities', $data, $reading_info, $gender, $numeric_id );

		// Populate Current Life Phase (locked)
		$template = $this->replace_locked_section( $template, 'phase', 'life_phase', $data, $reading_info, $gender, $numeric_id );

		// Populate Timeline/Next 6 Months (locked)
		$template = $this->replace_locked_section( $template, 'timeline', 'timeline_6_months', $data, $reading_info, $gender, $numeric_id );

		// Populate Guidance (locked) - has special HTML structure
		$template = $this->replace_guidance_section( $template, $data, $reading_info, $gender, $numeric_id );

		// Populate full-report premium sections (paid flow) only when unlocked.
		$has_full_access = ! empty( $reading_info['has_full_access'] );
		if ( $has_full_access ) {
			$template = $this->replace_full_section( $template, 'section-deep-love', 'deep_relationship_analysis', $data );
			$template = $this->replace_full_section( $template, 'section-extended-timeline', 'extended_timeline_12_months', $data );
			$template = $this->replace_full_section( $template, 'section-life-purpose', 'life_purpose_soul_mission', $data );
			$template = $this->replace_full_section( $template, 'section-shadow-work', 'shadow_work_transformation', $data );
			$template = $this->replace_full_section( $template, 'section-action-plan', 'practical_guidance_action_plan', $data );
		}

		// Keep teaser closing copy static (avoid AI-generated closing in teaser template).

		return $template;
	}

	/**
	 * Populate swipe template markers with JSON data.
	 *
	 * @param string $template     Template HTML.
	 * @param array  $data         Parsed JSON data from reading.
	 * @param array  $reading_info Reading info with unlock state.
	 * @return string
	 */
	private function populate_swipe_template_content( $template, $data, $reading_info ) {
		$unlocked_sections = isset( $reading_info['unlocked_sections'] ) ? $reading_info['unlocked_sections'] : array();
		$has_full_access   = ! empty( $reading_info['has_full_access'] );

		$opening_parts = array();
		if ( isset( $data['opening'] ) ) {
			$opening = $data['opening'];
			foreach ( array( 'reflection_p1', 'reflection_p2' ) as $key ) {
				if ( ! empty( $opening[ $key ] ) ) {
					$opening_parts[] = $opening[ $key ];
				}
			}
		}

		$template = $this->replace_swipe_marker_block(
			$template,
			'OPENING_CONTENT_START',
			'OPENING_CONTENT_END',
			$this->build_swipe_paragraphs( $opening_parts ),
			'opening'
		);

		$palm_summary_html = $this->build_palm_summary_html(
			isset( $data['palm_summary'] ) ? $data['palm_summary'] : array()
		);
		if ( '' !== trim( $palm_summary_html ) ) {
			$template = $this->replace_swipe_marker_block(
				$template,
				'PALM_SUMMARY_CONTENT_START',
				'PALM_SUMMARY_CONTENT_END',
				$palm_summary_html,
				'palm_summary'
			);
		} else {
			$template = $this->remove_swipe_marker_block(
				$template,
				'PALM_SUMMARY_CARD_START',
				'PALM_SUMMARY_CARD_END',
				'card2'
			);
		}

		$template = $this->replace_swipe_marker_block(
			$template,
			'LIFE_FOUNDATIONS_CONTENT_START',
			'LIFE_FOUNDATIONS_CONTENT_END',
			$this->build_swipe_life_foundations( isset( $data['life_foundations'] ) ? $data['life_foundations'] : array() ),
			'life_foundations'
		);

		$personality_intro = isset( $data['personality_traits']['intro'] ) ? $data['personality_traits']['intro'] : '';
		$template = $this->replace_swipe_marker_block(
			$template,
			'PERSONALITY_CONTENT_START',
			'PERSONALITY_CONTENT_END',
			$this->build_swipe_paragraphs_from_text( $personality_intro ),
			'personality_traits'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'TRAITS_START',
			'TRAITS_END',
			$this->build_swipe_traits( isset( $data['personality_traits'] ) ? $data['personality_traits'] : array() ),
			'personality_traits'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'LOVE_CONTENT_START',
			'LOVE_CONTENT_END',
			$this->build_swipe_paragraphs_from_text(
				$this->get_swipe_section_text( $data, 'love_patterns', $has_full_access, $unlocked_sections, 'love' )
			),
			'love_patterns'
		);

		$career_text = isset( $data['career_success']['main_paragraph'] ) ? $data['career_success']['main_paragraph'] : '';
		$template = $this->replace_swipe_marker_block(
			$template,
			'CAREER_CONTENT_START',
			'CAREER_CONTENT_END',
			$this->build_swipe_paragraphs_from_text( $career_text ),
			'career_success'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'CHALLENGES_CONTENT_START',
			'CHALLENGES_CONTENT_END',
			$this->build_swipe_paragraphs_from_text(
				$this->get_swipe_section_text( $data, 'challenges_opportunities', $has_full_access, $unlocked_sections, 'challenges' )
			),
			'challenges_opportunities'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'LIFE_PHASE_CONTENT_START',
			'LIFE_PHASE_CONTENT_END',
			$this->build_swipe_paragraphs_from_text(
				$this->get_swipe_section_text( $data, 'life_phase', $has_full_access, $unlocked_sections, 'phase' )
			),
			'life_phase'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'DEEP_LOVE_CONTENT_START',
			'DEEP_LOVE_CONTENT_END',
			$this->build_swipe_paragraphs_from_text(
				$this->get_swipe_section_text( $data, 'deep_relationship_analysis', true, $unlocked_sections, '' )
			),
			'deep_relationship_analysis'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'TIMELINE_CONTENT_START',
			'TIMELINE_CONTENT_END',
			$this->build_swipe_paragraphs_from_text(
				$this->get_swipe_section_text( $data, 'timeline_6_months', $has_full_access, $unlocked_sections, 'timeline' )
			),
			'timeline_6_months'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'EXTENDED_TIMELINE_CONTENT_START',
			'EXTENDED_TIMELINE_CONTENT_END',
			$this->build_swipe_paragraphs_from_text(
				$this->get_swipe_section_text( $data, 'extended_timeline_12_months', $has_full_access, $unlocked_sections, 'extended_timeline_12_months' )
			),
			'extended_timeline_12_months'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'PURPOSE_CONTENT_START',
			'PURPOSE_CONTENT_END',
			$this->build_swipe_paragraphs_from_text(
				$this->get_swipe_section_text( $data, 'life_purpose_soul_mission', true, $unlocked_sections, '' )
			),
			'life_purpose_soul_mission'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'SHADOW_CONTENT_START',
			'SHADOW_CONTENT_END',
			$this->build_swipe_paragraphs_from_text(
				$this->get_swipe_section_text( $data, 'shadow_work_transformation', true, $unlocked_sections, '' )
			),
			'shadow_work_transformation'
		);

		$template = $this->replace_swipe_marker_block(
			$template,
			'GUIDANCE_CONTENT_START',
			'GUIDANCE_CONTENT_END',
			$this->build_swipe_paragraphs_from_text(
				$this->get_swipe_section_text( $data, 'practical_guidance_action_plan', true, $unlocked_sections, '' )
			),
			'practical_guidance_action_plan'
		);

		return $template;
	}

	private function remove_swipe_marker_block( $template, $start_marker, $end_marker, $card_id = '' ) {
		$pattern = sprintf(
			'/<!-- %s -->.*?<!-- %s -->/s',
			preg_quote( $start_marker, '/' ),
			preg_quote( $end_marker, '/' )
		);
		if ( preg_match( $pattern, $template ) ) {
			return preg_replace( $pattern, '', $template, 1 );
		}

		if ( '' === $card_id ) {
			return $template;
		}

		$pattern = '/<div class="reading-card[^"]*" id="' . preg_quote( $card_id, '/' ) . '">.*?<\/div>\s*<\/div>/s';
		return preg_replace( $pattern, '', $template, 1 );
	}

	private function replace_palm_summary_section( $template, $data ) {
		$palm_summary = isset( $data['palm_summary'] ) ? $data['palm_summary'] : array();
		$content_html = $this->build_palm_summary_html( $palm_summary );
		$section_pattern = '/<section class="reading-section section-palm-summary">.*?<\/section>/s';

		if ( '' === trim( $content_html ) ) {
			return preg_replace( $section_pattern, '', $template );
		}

		$pattern = '/(<section class="reading-section section-palm-summary">.*?<div class="section-content">)(.*?)(<\/div>\s*<\/section>)/s';
		$replacement = '$1' . "\n          " . $content_html . "\n        " . '$3';

		return preg_replace( $pattern, $replacement, $template, 1 );
	}

	private function build_palm_summary_html( $palm_summary ) {
		if ( empty( $palm_summary ) || ! is_array( $palm_summary ) ) {
			return '';
		}

		$items = array();

		if ( ! empty( $palm_summary['hand_type'] ) ) {
			$items[] = '<li><strong>Aura field:</strong> ' . esc_html( $palm_summary['hand_type'] ) . '</li>';
		}

		if ( ! empty( $palm_summary['line_observations'] ) && is_array( $palm_summary['line_observations'] ) ) {
			$line_parts = array();
			$labels = array(
				'life_line'  => 'Vital flow',
				'head_line'  => 'Mind flow',
				'heart_line' => 'Heart flow',
				'fate_line'  => 'Purpose flow',
			);
			foreach ( $labels as $key => $label ) {
				if ( ! empty( $palm_summary['line_observations'][ $key ] ) ) {
					$line_parts[] = '<li><span class="palm-summary-arrow">&gt;</span> <strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $palm_summary['line_observations'][ $key ] ) . '</li>';
				}
			}
			if ( ! empty( $line_parts ) ) {
				$items[] = '<li><strong>Energy flows:</strong><ul class="palm-summary-lines">' . implode( '', $line_parts ) . '</ul></li>';
			}
		}

		if ( ! empty( $palm_summary['mounts'] ) && is_array( $palm_summary['mounts'] ) ) {
			$items[] = '<li><strong>Centers:</strong> ' . esc_html( implode( ', ', array_map( 'sanitize_text_field', $palm_summary['mounts'] ) ) ) . '</li>';
		}

		if ( ! empty( $palm_summary['markings'] ) && is_array( $palm_summary['markings'] ) ) {
			$items[] = '<li><strong>Signals:</strong> ' . esc_html( implode( ', ', array_map( 'sanitize_text_field', $palm_summary['markings'] ) ) ) . '</li>';
		}

		if ( ! empty( $palm_summary['overall_energy'] ) ) {
			$items[] = '<li><strong>Overall energy:</strong> ' . esc_html( $palm_summary['overall_energy'] ) . '</li>';
		}

		if ( ! empty( $palm_summary['image_clarity_note'] ) ) {
			$items[] = '<li><strong>Image clarity:</strong> ' . esc_html( $palm_summary['image_clarity_note'] ) . '</li>';
		}

		if ( empty( $items ) ) {
			return '';
		}

		return '<ul class="palm-summary-list">' . "\n            " . implode( "\n            ", $items ) . "\n          </ul>";
	}

	private function replace_swipe_marker_block( $template, $start_marker, $end_marker, $content_html, $section_key ) {
		$pattern = sprintf(
			'/<!-- %s -->.*?<!-- %s -->/s',
			preg_quote( $start_marker, '/' ),
			preg_quote( $end_marker, '/' )
		);
		if ( ! preg_match( $pattern, $template ) ) {
			SM_Logger::log(
				'warning',
				'SWIPE_TEMPLATE',
				'Marker block not found in swipe template',
				array(
					'marker_start' => $start_marker,
					'marker_end'   => $end_marker,
					'section'      => $section_key,
				)
			);
			return $template;
		}

		if ( '' === trim( $content_html ) ) {
			SM_Logger::log(
				'warning',
				'SWIPE_TEMPLATE',
				'Empty content for swipe section',
				array( 'section' => $section_key )
			);
			return $template;
		}

		$replacement = sprintf(
			"<!-- %s -->\n%s\n            <!-- %s -->",
			$start_marker,
			$content_html,
			$end_marker
		);
		return preg_replace( $pattern, $replacement, $template );
	}

	private function build_swipe_paragraphs( $parts ) {
		if ( empty( $parts ) ) {
			return '';
		}

		$html = '';
		$index = 0;
		foreach ( $parts as $text ) {
			$text = trim( (string) $text );
			if ( '' === $text ) {
				continue;
			}
			$class = ( 0 === $index ) ? 'card-text' : 'extra-text';
			$html .= "<p class=\"" . esc_attr( $class ) . "\">" . esc_html( $text ) . "</p>\n";
			$index++;
		}

		return trim( $html );
	}

	private function build_swipe_life_foundations( $life ) {
		if ( empty( $life ) || ! is_array( $life ) ) {
			return '';
		}

		$parts = array();
		foreach ( array( 'paragraph_1', 'paragraph_2', 'paragraph_3' ) as $key ) {
			if ( ! empty( $life[ $key ] ) ) {
				$parts[] = $life[ $key ];
			}
		}

		$html = $this->build_swipe_paragraphs( $parts );

		if ( ! empty( $life['core_theme'] ) ) {
			$core = esc_html( 'Core Theme: ' . $life['core_theme'] );
			$html .= sprintf( '<p class="core-theme"><em>%s</em></p>', $core );
		}

		return trim( $html );
	}

	private function build_swipe_paragraphs_from_text( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}

		$sentences = preg_split( '/(?<=[.!?])\s+/', $text );
		$chunks = array();
		$current = '';
		$count = 0;

		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );
			if ( '' === $sentence ) {
				continue;
			}
			$current .= $sentence . ' ';
			$count++;
			if ( $count >= 3 ) {
				$chunks[] = trim( $current );
				$current = '';
				$count = 0;
			}
		}

		if ( '' !== trim( $current ) ) {
			$chunks[] = trim( $current );
		}

		return $this->build_swipe_paragraphs( $chunks );
	}

	private function build_swipe_traits( $traits ) {
		if ( empty( $traits ) ) {
			return '';
		}

		$html = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$name_key = "trait_{$i}_name";
			$score_key = "trait_{$i}_score";

			if ( empty( $traits[ $name_key ] ) || ! isset( $traits[ $score_key ] ) ) {
				continue;
			}

			$name = esc_html( $traits[ $name_key ] );
			$score = (int) $traits[ $score_key ];
			$html .= '<div class="trait-row">' . "\n";
			$html .= '  <div class="trait-name">' . $name . '</div>' . "\n";
			$html .= '  <div class="trait-bar">' . "\n";
			$html .= '    <div class="trait-fill" data-width="' . esc_attr( (string) $score ) . '" style="width: ' . esc_attr( (string) $score ) . '%"></div>' . "\n";
			$html .= '  </div>' . "\n";
			$html .= '  <div class="trait-score">' . esc_html( (string) $score ) . '</div>' . "\n";
			$html .= '</div>' . "\n";
		}

		return trim( $html );
	}

	private function get_swipe_section_text( $data, $section_key, $has_full_access, $unlocked_sections, $unlock_key ) {
		if ( empty( $data[ $section_key ] ) || ! is_array( $data[ $section_key ] ) ) {
			return '';
		}

		$section = $data[ $section_key ];
		$is_unlocked = $has_full_access;
		if ( '' !== $unlock_key ) {
			$is_unlocked = $is_unlocked || in_array( $unlock_key, $unlocked_sections, true );
		}

		if ( $is_unlocked ) {
			foreach ( array( 'full_content', 'locked_full' ) as $key ) {
				if ( ! empty( $section[ $key ] ) ) {
					return $section[ $key ];
				}
			}
		} else {
			foreach ( array( 'preview', 'preview_p1', 'preview_p2' ) as $key ) {
				if ( ! empty( $section[ $key ] ) ) {
					return $section[ $key ];
				}
			}

			if ( '' !== $unlock_key ) {
				return $this->get_locked_teaser_copy( $unlock_key );
			}

			$user_name = $this->get_user_name_from_data( $data );
			$premium_placeholder = $this->get_premium_teaser_placeholder( $section_key, $user_name );
			if ( '' !== $premium_placeholder ) {
				return $premium_placeholder;
			}

			if ( ! empty( $section['placeholder_text'] ) ) {
				return $section['placeholder_text'];
			}
		}

		return '';
	}

	/**
	 * Replace a visible section's content
	 *
	 * @param string $template Template HTML.
	 * @param string $section  Section key (e.g., 'opening', 'life_foundations').
	 * @param array  $data     JSON data.
	 * @return string Updated template.
	 */
	private function replace_section( $template, $section, $data ) {
		switch ( $section ) {
			case 'opening':
				return $this->replace_opening( $template, $data );
			case 'life_foundations':
				return $this->replace_life_foundations( $template, $data );
			case 'career_success':
				return $this->replace_career_success( $template, $data );
			case 'closing':
				return $this->replace_closing( $template, $data );
			default:
				return $template;
		}
	}

	/**
	 * Replace Opening Reflection content
	 *
	 * @param string $template Template HTML.
	 * @param array  $data     JSON data.
	 * @return string Updated template.
	 */
	private function replace_opening( $template, $data ) {
		if ( ! isset( $data['opening'] ) ) {
			return $template;
		}

		$opening = $data['opening'];

		// Find opening section paragraphs and replace
		// Pattern: <p> content inside .opening-text after <h2>Your Hand as a Mirror</h2>
		$pattern = '/(<div class="opening-text">.*?<h2>Your Hand as a Mirror<\/h2>\s*<p>)(.*?)(<\/p>\s*<p class="opening-soft">)(.*?)(<\/p>)/s';

		$p1 = isset( $opening['reflection_p1'] ) ? esc_html( $opening['reflection_p1'] ) : '';
		$p2 = isset( $opening['reflection_p2'] ) ? esc_html( $opening['reflection_p2'] ) : '';

		$replacement = '$1' . $p1 . '$3' . $p2 . '$5';
		$template = preg_replace( $pattern, $replacement, $template );

		return $template;
	}

	/**
	 * Replace Life Foundations content
	 *
	 * @param string $template Template HTML.
	 * @param array  $data     JSON data.
	 * @return string Updated template.
	 */
	private function replace_life_foundations( $template, $data ) {
		if ( ! isset( $data['life_foundations'] ) ) {
			return $template;
		}

		$life = $data['life_foundations'];

		// Build replacement content
		$paragraphs = array();
		if ( isset( $life['paragraph_1'] ) ) {
			$paragraphs[] = '<p>' . "\n            " . esc_html( $life['paragraph_1'] ) . "\n          </p>";
		}
		if ( isset( $life['paragraph_2'] ) ) {
			$paragraphs[] = '<p>' . "\n            " . esc_html( $life['paragraph_2'] ) . "\n          </p>";
		}
		if ( isset( $life['paragraph_3'] ) ) {
			$paragraphs[] = '<p>' . "\n            " . esc_html( $life['paragraph_3'] ) . "\n          </p>";
		}

		$core_theme = isset( $life['core_theme'] ) ? esc_html( $life['core_theme'] ) : '';

		// Pattern: Match all content between <div class="section-content"> and </div> in section-life
		$pattern = '/(<section class="reading-section section-life">.*?<div class="section-content">)(.*?)(<\/div>\s*<\/section>)/s';

		$replacement_content = "\n          " . implode( "\n          ", $paragraphs );

		if ( ! empty( $core_theme ) ) {
			$replacement_content .= "\n          " . '<div class="insight-point">' . "\n"
				. '            <i class="fas fa-star insight-icon" aria-hidden="true"></i>' . "\n"
				. '            <span class="insight-text">Core Theme: ' . $core_theme . '</span>' . "\n"
				. '          </div>' . "\n        ";
		}

		$replacement = '$1' . $replacement_content . '$3';
		$template = preg_replace( $pattern, $replacement, $template );

		return $template;
	}

	/**
	 * Replace Career & Success content
	 *
	 * @param string $template Template HTML.
	 * @param array  $data     JSON data.
	 * @return string Updated template.
	 */
	private function replace_career_success( $template, $data ) {
		if ( ! isset( $data['career_success'] ) ) {
			return $template;
		}

		$career = $data['career_success'];

		// Replace main paragraph(s) in section-destiny, preserving symbol grid if present
		if ( isset( $career['main_paragraph'] ) ) {
			$paragraphs = $this->build_paragraphs_from_text( esc_html( $career['main_paragraph'] ) );
			if ( empty( $paragraphs ) ) {
				return $template;
			}

			$replacement_content = "\n          " . implode( "\n          ", $paragraphs ) . "\n\n          ";

			$symbol_pattern = '/(<section class="reading-section section-destiny">.*?<div class="section-content">)(.*?)(<div class="symbol-grid">)/s';
			if ( preg_match( $symbol_pattern, $template ) ) {
				$template = preg_replace( $symbol_pattern, '$1' . $replacement_content . '$3', $template, 1 );
				return $template;
			}

			$insight_pattern = '/(<section class="reading-section section-destiny">.*?<div class="section-content">)(.*?)(<div class="insight-point">)/s';
			if ( preg_match( $insight_pattern, $template ) ) {
				$template = preg_replace( $insight_pattern, '$1' . $replacement_content . '$3', $template, 1 );
				return $template;
			}

			$pattern = '/(<section class="reading-section section-destiny">.*?<div class="section-content">)(.*?)(<\/div>\s*<\/section>)/s';
			$replacement = '$1' . rtrim( $replacement_content ) . "\n        " . '$3';
			$template = preg_replace( $pattern, $replacement, $template, 1 );
		}

		return $template;
	}

	/**
	 * Replace Closing Reflection content
	 *
	 * @param string $template Template HTML.
	 * @param array  $data     JSON data.
	 * @return string Updated template.
	 */
	private function replace_closing( $template, $data ) {
		if ( ! isset( $data['closing'] ) ) {
			return $template;
		}

		$closing = $data['closing'];

		// Pattern: Match both paragraphs in section-closing
		$pattern = '/(<section class="reading-section section-closing">.*?<div class="section-content">\s*<p>)(.*?)(<\/p>\s*<p class="closing-soft">)(.*?)(<\/p>)/s';

		$p1 = isset( $closing['paragraph_1'] ) ? esc_html( $closing['paragraph_1'] ) : '';
		$p2 = isset( $closing['paragraph_2'] ) ? esc_html( $closing['paragraph_2'] ) : '';

		$replacement = '$1' . $p1 . '$3' . $p2 . '$5';
		$template = preg_replace( $pattern, $replacement, $template );

		return $template;
	}

	/**
	 * Replace a locked section's content based on unlock state
	 *
	 * @param string $template      Template HTML.
	 * @param string $section_key   Section key in template (e.g., 'love', 'challenges').
	 * @param string $data_key      Data key in JSON (e.g., 'love_patterns', 'challenges_opportunities').
	 * @param array  $data          JSON data.
	 * @param array  $reading_info  Reading info with unlock state.
	 * @param string $gender        Gender for quote selection.
	 * @param int    $numeric_id    Numeric reading ID for quote selection.
	 * @return string Updated template.
	 */
	private function replace_locked_section( $template, $section_key, $data_key, $data, $reading_info, $gender = 'female', $numeric_id = 0 ) {
		if ( ! isset( $data[ $data_key ] ) ) {
			return $template;
		}

		$section_data = $data[ $data_key ];
		$is_unlocked = in_array( $section_key, $reading_info['unlocked_sections'], true ) || $reading_info['has_full_access'];

		// Build section class attribute to find the right section
		$section_class_map = array(
			'love'       => 'section-love',
			'challenges' => 'section-challenges',
			'phase'      => 'section-phase',
			'timeline'   => 'section-timeline',
			'guidance'   => 'section-guidance',
		);

		$section_class = isset( $section_class_map[ $section_key ] ) ? $section_class_map[ $section_key ] : '';
		if ( empty( $section_class ) ) {
			return $template;
		}

		if ( $is_unlocked ) {
			// Show full content, remove lock overlay
			$template = $this->replace_locked_section_unlocked( $template, $section_class, $section_data );
		} else {
			// Show preview content only
			$template = $this->replace_locked_section_preview( $template, $section_class, $section_data, $section_key, $gender, $numeric_id );
		}

		return $template;
	}

	/**
	 * Replace locked section with preview content
	 *
	 * @param string $template      Template HTML.
	 * @param string $section_class Section CSS class.
	 * @param array  $section_data  Section data.
	 * @param string $section_key   Section identifier for quote selection.
	 * @param string $gender        Gender for quote selection.
	 * @param int    $numeric_id    Numeric reading ID for quote selection.
	 * @return string Updated template.
	 */
	private function replace_locked_section_preview( $template, $section_class, $section_data, $section_key, $gender = 'female', $numeric_id = 0 ) {
		// Build preview paragraphs (support new single preview and legacy preview_p1/preview_p2)
		$paragraphs = array();
		$preview = isset( $section_data['preview'] ) ? trim( $section_data['preview'] ) : '';
		if ( '' !== $preview ) {
			$paragraphs[] = '<p>' . "\n            " . esc_html( $preview ) . "\n          </p>";
		} else {
			if ( isset( $section_data['preview_p1'] ) ) {
				$paragraphs[] = '<p>' . "\n            " . esc_html( $section_data['preview_p1'] ) . "\n          </p>";
			}
			if ( isset( $section_data['preview_p2'] ) ) {
				$paragraphs[] = '<p>' . "\n            " . esc_html( $section_data['preview_p2'] ) . "\n          </p>";
			}
		}

		if ( empty( $paragraphs ) ) {
			return $template;
		}

		// Map section keys to quote section names
		$section_to_quote_map = array(
			'love'       => 'love_patterns',
			'challenges' => 'challenges_opportunities',
			'phase'      => 'life_phase',
			'timeline'   => 'timeline_6_months',
			'guidance'   => 'guidance',
		);

		$quote_section = isset( $section_to_quote_map[ $section_key ] ) ? $section_to_quote_map[ $section_key ] : $section_key;
		$locked_teaser = SM_Quote_Handler::get_quote( $quote_section, $gender, $numeric_id );

		$replacement_paragraphs = "\n          " . implode( "\n          ", $paragraphs ) . "\n\n          ";
		$section_pattern        = '/<section class="reading-section ' . preg_quote( $section_class, '/' ) . '.*?<\/section>/s';
		if ( ! preg_match( $section_pattern, $template, $matches ) ) {
			return $template;
		}

		$section_html = $matches[0];
		$replace_count = 0;
		$section_html = preg_replace(
			'/(<div class="section-content">)(.*?)(<div class="insight-point">)/s',
			'$1' . $replacement_paragraphs . '$3',
			$section_html,
			1,
			$replace_count
		);

		if ( 0 === $replace_count ) {
			$section_html = preg_replace(
				'/(<div class="section-content">)(.*?)(<\/div>)(\s*(?:<div class="lock-overlay"|<\/section>))/s',
				'$1' . $replacement_paragraphs . '$3$4',
				$section_html,
				1
			);
		}

		$section_html = preg_replace(
			'/(<div class="insight-point">.*?<span class="insight-text">)(.*?)(<\/span>)/s',
			'$1' . 'Locked Insight: ' . $locked_teaser . '$3',
			$section_html,
			1
		);

		$template = str_replace( $matches[0], $section_html, $template );

		return $template;
	}

	/**
	 * Replace locked section with full unlocked content
	 *
	 * @param string $template      Template HTML.
	 * @param string $section_class Section CSS class.
	 * @param array  $section_data  Section data.
	 * @return string Updated template.
	 */
	private function replace_locked_section_unlocked( $template, $section_class, $section_data ) {
		$full_content = isset( $section_data['locked_full'] ) ? trim( $section_data['locked_full'] ) : '';
		if ( '' === $full_content ) {
			return $this->replace_locked_section_unlocked_with_preview( $template, $section_class, $section_data );
		}
		$full_content = esc_html( $full_content );
		$paragraphs   = $this->build_paragraphs_from_text( $full_content );

		$replacement_content = "\n          " . implode( "\n          ", $paragraphs ) . "\n        ";
		$section_pattern     = '/<section class="reading-section ' . preg_quote( $section_class, '/' ) . '.*?<\/section>/s';
		if ( ! preg_match( $section_pattern, $template, $matches ) ) {
			return $template;
		}

		$section_html = $matches[0];
		$section_html = preg_replace(
			'/(<div class="section-content">)(.*?)(<\/div>)(\s*(?:<div class="lock-overlay"|<\/section>))/s',
			'$1' . $replacement_content . '$3$4',
			$section_html,
			1
		);
		$section_html = preg_replace(
			'/\s*<div class="lock-overlay">\s*<div class="lock-card[^"]*">[\s\S]*?<\/div>\s*<\/div>\s*/s',
			'',
			$section_html,
			1
		);
		$section_html = preg_replace( '/(<section\s+class="[^"]*)\s+locked([^"]*")/', '$1$2', $section_html, 1 );

		$template = str_replace( $matches[0], $section_html, $template );

		return $template;
	}

	/**
	 * Replace a full section's content (paid report) using a text field.
	 *
	 * @param string $template      Template HTML.
	 * @param string $section_class Section CSS class.
	 * @param string $data_key      Data key in JSON (e.g., 'deep_relationship_analysis').
	 * @param array  $data          JSON data.
	 * @param string $content_key   Content key in the section data array.
	 * @return string Updated template.
	 */
	private function replace_full_section( $template, $section_class, $data_key, $data, $content_key = 'full_content', $fallback_content = '' ) {
		if ( ! isset( $data[ $data_key ] ) ) {
			return $template;
		}

		$section_data = $data[ $data_key ];
		$full_content = '';

		if ( is_array( $section_data ) ) {
			if ( isset( $section_data[ $content_key ] ) ) {
				$full_content = $section_data[ $content_key ];
			} elseif ( isset( $section_data['full_content'] ) ) {
				$full_content = $section_data['full_content'];
			}
		} elseif ( is_string( $section_data ) ) {
			$full_content = $section_data;
		}

		$full_content = trim( $full_content );
		if ( '' === $full_content && '' !== $fallback_content ) {
			$full_content = $fallback_content;
		}
		if ( '' === $full_content ) {
			return $template;
		}

		$paragraphs = $this->build_paragraphs_from_text( esc_html( $full_content ) );
		if ( empty( $paragraphs ) ) {
			return $template;
		}

		$replacement_content = "\n          " . implode( "\n          ", $paragraphs ) . "\n        ";
		$pattern = '/(<section class="reading-section ' . preg_quote( $section_class, '/' ) . '.*?<div class="section-content">)(.*?)(<\/div>\s*<\/section>)/s';
		$replacement = '$1' . $replacement_content . '$3';

		return preg_replace( $pattern, $replacement, $template, 1 );
	}

	/**
	 * Resolve a display name from reading data.
	 *
	 * @param array $data Reading data.
	 * @return string
	 */
	private function get_user_name_from_data( $data ) {
		if ( isset( $data['meta']['user_name'] ) ) {
			$name = sanitize_text_field( $data['meta']['user_name'] );
			if ( '' !== $name ) {
				return $name;
			}
		}

		return __( 'Seeker', 'mystic-palm-reading' );
	}

	/**
	 * Hardcoded teaser copy for locked insight rows.
	 *
	 * @param string $section_key Section identifier.
	 * @return string
	 */
	private function get_locked_teaser_copy( $section_key ) {
		$copy = array(
			'love'       => 'Unlock your love pattern and deeper relationship guidance.',
			'challenges' => 'Unlock the path and navigate obstacles with grace.',
			'phase'      => 'Unlock what is ending, what begins, and the shift ahead.',
			'timeline'   => 'Unlock your 6-month timing map and key windows.',
			'guidance'   => 'Unlock focused guidance and your next steps.',
		);

		if ( isset( $copy[ $section_key ] ) ) {
			return $copy[ $section_key ];
		}

		$class_map = array(
			'section-love'       => 'love',
			'section-challenges' => 'challenges',
			'section-phase'      => 'phase',
			'section-timeline'   => 'timeline',
			'section-guidance'   => 'guidance',
		);

		if ( isset( $class_map[ $section_key ] ) && isset( $copy[ $class_map[ $section_key ] ] ) ) {
			return $copy[ $class_map[ $section_key ] ];
		}

		return 'Unlock to reveal full insight.';
	}

	/**
	 * Hardcoded teaser placeholders for premium sections.
	 *
	 * @param string $section_key Section identifier.
	 * @param string $user_name   User display name.
	 * @return string
	 */
	private function get_premium_teaser_placeholder( $section_key, $user_name ) {
		$name = $user_name ? $user_name : __( 'Seeker', 'mystic-palm-reading' );
		$copy = array(
			'deep_relationship_analysis' => sprintf(
				'%s, your full reading reveals the deeper relationship patterns shaping your choices, plus how to recognize and strengthen what is truly aligned.',
				$name
			),
			'extended_timeline_12_months' => sprintf(
				'%s, unlock the 12-month timeline to see the key windows for change, growth, and the decisions that matter most.',
				$name
			),
			'life_purpose_soul_mission' => sprintf(
				'%s, your full reading maps your soul mission and the concrete steps to bring your most meaningful work into focus.',
				$name
			),
			'shadow_work_transformation' => sprintf(
				'%s, the full reading uncovers the hidden patterns you are ready to transform and the practical ways to turn them into strength.',
				$name
			),
			'practical_guidance_action_plan' => sprintf(
				'%s, unlock the action plan for clear next steps and grounded guidance tied to your palm insights.',
				$name
			),
		);

		return isset( $copy[ $section_key ] ) ? $copy[ $section_key ] : '';
	}

	/**
	 * Build paragraph HTML blocks from a text blob.
	 *
	 * @param string $text Content to split.
	 * @return array Paragraph HTML strings.
	 */
	private function build_paragraphs_from_text( $text ) {
		$text = trim( $text );
		if ( '' === $text ) {
			return array();
		}

		$sentences = preg_split( '/(?<=[.!?])\s+/', $text );
		$paragraphs = array();
		$current_paragraph = '';
		$sentence_count = 0;

		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );
			if ( '' === $sentence ) {
				continue;
			}
			$current_paragraph .= $sentence . ' ';
			$sentence_count++;

			if ( $sentence_count >= 3 ) {
				$paragraphs[] = '<p>' . "\n            " . trim( $current_paragraph ) . "\n          </p>";
				$current_paragraph = '';
				$sentence_count = 0;
			}
		}

		if ( '' !== trim( $current_paragraph ) ) {
			$paragraphs[] = '<p>' . "\n            " . trim( $current_paragraph ) . "\n          </p>";
		}

		return $paragraphs;
	}

	/**
	 * Replace locked section with preview content (fallback for missing locked_full).
	 *
	 * @param string $template      Template HTML.
	 * @param string $section_class Section CSS class.
	 * @param array  $section_data  Section data.
	 * @return string Updated template.
	 */
	private function replace_locked_section_unlocked_with_preview( $template, $section_class, $section_data ) {
		$paragraphs = array();
		$preview = isset( $section_data['preview'] ) ? trim( $section_data['preview'] ) : '';
		if ( '' !== $preview ) {
			$paragraphs[] = '<p>' . "\n            " . esc_html( $preview ) . "\n          </p>";
		} else {
			if ( isset( $section_data['preview_p1'] ) ) {
				$paragraphs[] = '<p>' . "\n            " . esc_html( $section_data['preview_p1'] ) . "\n          </p>";
			}
			if ( isset( $section_data['preview_p2'] ) ) {
				$paragraphs[] = '<p>' . "\n            " . esc_html( $section_data['preview_p2'] ) . "\n          </p>";
			}
		}

		if ( empty( $paragraphs ) ) {
			return $template;
		}

		$locked_teaser = $this->get_locked_teaser_copy( $section_class );
		$insight_html = '<div class="insight-point"><i class="fas fa-lock insight-icon" aria-hidden="true"></i><span class="insight-text">Locked Insight: ' . $locked_teaser . '</span></div>';

		$replacement_content = "\n          " . implode( "\n          ", $paragraphs ) . "\n\n          " . $insight_html . "\n        ";
		$section_pattern     = '/<section class="reading-section ' . preg_quote( $section_class, '/' ) . '.*?<\/section>/s';
		if ( ! preg_match( $section_pattern, $template, $matches ) ) {
			return $template;
		}

		$section_html = $matches[0];
		$section_html = preg_replace(
			'/(<div class="section-content">)(.*?)(<\/div>)(\s*(?:<div class="lock-overlay"|<\/section>))/s',
			'$1' . $replacement_content . '$3$4',
			$section_html,
			1
		);
		$section_html = preg_replace(
			'/\s*<div class="lock-overlay">\s*<div class="lock-card[^"]*">[\s\S]*?<\/div>\s*<\/div>\s*/s',
			'',
			$section_html,
			1
		);
		$section_html = preg_replace( '/(<section\s+class="[^"]*)\s+locked([^"]*")/', '$1$2', $section_html, 1 );

		$template = str_replace( $matches[0], $section_html, $template );

		return $template;
	}

	/**
	 * Placeholder text used when locked_full content is missing.
	 *
	 * @param string $section_key Section identifier or class.
	 * @return string
	 */
	private function get_locked_full_placeholder( $section_key ) {
		return 'Full insight is being prepared. Please check back soon.';
	}

	/**
	 * Pull the first available field from a section for modal fallback.
	 *
	 * @param array  $data Reading data.
	 * @param string $section_key Section name.
	 * @param array  $fields Ordered list of fields to check.
	 * @return string
	 */
	private function get_section_text( $data, $section_key, $fields ) {
		if ( ! isset( $data[ $section_key ] ) || ! is_array( $data[ $section_key ] ) ) {
			return '';
		}

		foreach ( $fields as $field ) {
			if ( ! empty( $data[ $section_key ][ $field ] ) ) {
				return (string) $data[ $section_key ][ $field ];
			}
		}

		return '';
	}

	/**
	 * Build a short modal fallback from longer section text.
	 *
	 * @param string $text Source text.
	 * @param int    $max_words Max words for the excerpt.
	 * @return string
	 */
	private function build_modal_fallback( $text, $max_words = 45 ) {
		$plain = trim( wp_strip_all_tags( (string) $text ) );
		if ( '' === $plain ) {
			return '';
		}

		$plain = preg_replace( '/\s+/', ' ', $plain );
		$words = preg_split( '/\s+/', $plain );
		if ( ! is_array( $words ) || count( $words ) <= $max_words ) {
			return $plain;
		}

		return implode( ' ', array_slice( $words, 0, $max_words ) ) . '...';
	}

	/**
	 * Replace modal content (for Career & Success section)
	 *
	 * @param string $template Template HTML.
	 * @param array  $data     JSON data.
	 * @return string Updated template.
	 */
	private function replace_modals( $template, $data ) {
		if ( ! isset( $data['career_success'] ) ) {
			return $template;
		}

		$career = $data['career_success'];

		// Replace modal copy in inline JavaScript
		$modal_data = array(
			'lovePatterns' => array(
				'title' => 'Love Patterns (Quick Reflection)',
				'body'  => isset( $career['modal_love_patterns'] ) ? $career['modal_love_patterns'] : '',
			),
			'careerDirection' => array(
				'title' => 'Career Direction (Quick Reflection)',
				'body'  => isset( $career['modal_career_direction'] ) ? $career['modal_career_direction'] : '',
			),
			'lifeAlignment' => array(
				'title' => 'Life Alignment (Quick Reflection)',
				'body'  => isset( $career['modal_life_alignment'] ) ? $career['modal_life_alignment'] : '',
			),
		);

		// Find and replace the modalCopy object in the script
		$pattern = '/(const modalCopy = \{)(.*?)(\};)/s';
		if ( preg_match( $pattern, $template, $matches ) ) {
			$new_modal_copy = $this->generate_modal_copy_js( $modal_data );
			$template = preg_replace( $pattern, '$1' . $new_modal_copy . '$3', $template );
		}

		return $template;
	}

	/**
	 * Generate JavaScript object for modal copy
	 *
	 * @param array $modal_data Modal data array.
	 * @return string JavaScript object string.
	 */
	private function generate_modal_copy_js( $modal_data ) {
		$js = "\n";
		foreach ( $modal_data as $key => $modal ) {
			$title = esc_js( $modal['title'] );
			$body = esc_js( $modal['body'] );
			$js .= "    {$key}: {\n";
			$js .= "      title: \"{$title}\",\n";
			$js .= "      body: \"{$body}\"\n";
			$js .= "    }";
			if ( $key !== array_key_last( $modal_data ) ) {
				$js .= ',';
			}
			$js .= "\n";
		}
		return $js;
	}

	/**
	 * Replace Guidance section (special HTML structure with guidance-card)
	 *
	 * @param string $template      Template HTML.
	 * @param array  $data          JSON data.
	 * @param array  $reading_info  Reading info with unlock state.
	 * @param string $gender        Gender for quote selection.
	 * @param int    $numeric_id    Numeric reading ID for quote selection.
	 * @return string Updated template.
	 */
	private function replace_guidance_section( $template, $data, $reading_info, $gender = 'female', $numeric_id = 0 ) {
		if ( ! isset( $data['guidance'] ) ) {
			return $template;
		}

		$guidance = $data['guidance'];
		$is_unlocked = in_array( 'guidance', $reading_info['unlocked_sections'], true ) || $reading_info['has_full_access'];

		if ( $is_unlocked ) {
			$full_content = isset( $guidance['locked_full'] ) ? trim( $guidance['locked_full'] ) : '';
			$has_full_content = '' !== $full_content;
			if ( $has_full_content ) {
				$body = esc_html( $full_content );
				$title = 'Your Focus Points';
			} else {
				$preview = isset( $guidance['preview'] ) ? trim( $guidance['preview'] ) : '';
				if ( '' === $preview && isset( $guidance['preview_p1'] ) ) {
					$preview = $guidance['preview_p1'];
				}
				$body = esc_html( $preview );
				$title = 'Notice where you\'re forcing certainty';
			}

			// Pattern: Match guidance-card h3 and p content
			$pattern = '/(<section class="reading-section section-guidance.*?<div class="guidance-text">\s*<h3>)(.*?)(<\/h3>\s*<p>)(.*?)(<\/p>)/s';

			$replacement = '$1' . esc_html( $title ) . '$3' . $body . '$5';
			$template = preg_replace( $pattern, $replacement, $template );

			// Remove "locked" class and lock overlay
			$template = str_replace( 'class="reading-section section-guidance locked"', 'class="reading-section section-guidance"', $template );
			$guidance_pattern = '/<section class="reading-section section-guidance.*?<\/section>/s';
			if ( preg_match( $guidance_pattern, $template, $matches ) ) {
				$guidance_html = preg_replace(
					'/\s*<div class="lock-overlay">\s*<div class="lock-card[^"]*">[\s\S]*?<\/div>\s*<\/div>\s*/s',
					'',
					$matches[0],
					1
				);
				$template = str_replace( $matches[0], $guidance_html, $template );
			}
		} else {
			// Show preview content
			$preview = isset( $guidance['preview'] ) ? esc_html( $guidance['preview'] ) : '';
			if ( '' === $preview && isset( $guidance['preview_p1'] ) ) {
				$preview = esc_html( $guidance['preview_p1'] );
			}

			// Pattern: Match guidance-card h3 and p content
			$pattern = '/(<section class="reading-section section-guidance.*?<div class="guidance-text">\s*<h3>)(.*?)(<\/h3>\s*<p>)(.*?)(<\/p>)/s';

			$title = 'Notice where you\'re forcing certainty';
			$replacement = '$1' . esc_html( $title ) . '$3' . $preview . '$5';
			$template = preg_replace( $pattern, $replacement, $template );
		}

		return $template;
	}

	/**
	 * Replace personality section intro paragraph
	 *
	 * @param string $template Template HTML.
	 * @param array  $data     JSON data.
	 * @return string Updated template.
	 */
	private function replace_personality_intro( $template, $data ) {
		if ( ! isset( $data['personality_traits'] ) || ! isset( $data['personality_traits']['intro'] ) ) {
			return $template;
		}

		$intro = esc_html( $data['personality_traits']['intro'] );

		// Pattern: Match the first <p> in section-personality
		$pattern = '/(<section class="reading-section section-personality">.*?<div class="section-content">\s*<p>)(.*?)(<\/p>)/s';
		$replacement = '$1' . $intro . '$3';
		$template = preg_replace( $pattern, $replacement, $template );

		return $template;
	}

	/**
	 * Replace trait bars with dynamic traits
	 *
	 * @param string $template Template HTML.
	 * @param array  $data     JSON data.
	 * @return string Updated template.
	 */
	private function replace_traits( $template, $data ) {
		if ( ! isset( $data['personality_traits'] ) ) {
			return $template;
		}

		$traits = $data['personality_traits'];

		// Build trait HTML
		$trait_html = '';
		for ( $i = 1; $i <= 3; $i++ ) {
			$name_key = "trait_{$i}_name";
			$score_key = "trait_{$i}_score";

			if ( isset( $traits[ $name_key ] ) && isset( $traits[ $score_key ] ) ) {
				$name = esc_html( $traits[ $name_key ] );
				$score = intval( $traits[ $score_key ] );

				$trait_html .= '<div class="trait-item">' . "\n";
				$trait_html .= '  <span class="trait-name">' . $name . '</span>' . "\n";
				$trait_html .= '  <div class="trait-bar"><div class="trait-fill" style="width: ' . $score . '%"></div></div>' . "\n";
				$trait_html .= '</div>' . "\n";
			}
		}

		// Replace between marker comments for reliable replacement
		$pattern = '/<!-- TRAITS_REPLACE_START -->.*?<!-- TRAITS_REPLACE_END -->/s';
		$replacement = '<!-- TRAITS_REPLACE_START -->' . "\n" . $trait_html . '            <!-- TRAITS_REPLACE_END -->';

		if ( preg_match( $pattern, $template ) ) {
			$template = preg_replace( $pattern, $replacement, $template );
		}

		return $template;
	}

	/**
	 * Escape text for safe HTML output
	 *
	 * @param mixed $value Value to escape.
	 * @return string Escaped HTML.
	 */
	private function esc( $value ) {
		if ( is_array( $value ) ) {
			return '';
		}
		return esc_html( (string) $value );
	}

	/**
	 * Get a nested array value safely
	 *
	 * @param array  $array   Array to search.
	 * @param string $path    Dot-notation path (e.g., 'opening.reflection_p1').
	 * @param mixed  $default Default value if not found.
	 * @return mixed Value or default.
	 */
	private function get_nested( $array, $path, $default = '' ) {
		$keys = explode( '.', $path );
		$value = $array;

		foreach ( $keys as $key ) {
			if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
				return $default;
			}
			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * Inject login button HTML into template if user not logged in.
	 *
	 * @param string $template Template HTML.
	 * @return string Modified template.
	 */
	private function inject_login_button( $template ) {
		// Check if Account Service integration is enabled
		$settings = SM_Settings::init()->get_settings();
		if ( empty( $settings['enable_account_integration'] ) ) {
			// Remove placeholder if integration disabled
			return str_replace( '<!-- SM_LOGIN_BUTTON_PLACEHOLDER -->', '', $template );
		}

		// Check if login button should be shown on teaser page
		$show_on_teaser = ! empty( $settings['show_login_button']['teaser'] );
		if ( ! $show_on_teaser ) {
			// Remove placeholder if not enabled for teaser page
			return str_replace( '<!-- SM_LOGIN_BUTTON_PLACEHOLDER -->', '', $template );
		}

		// Check if user is already logged in
		$auth_handler = SM_Auth_Handler::init();
		if ( $auth_handler->is_user_logged_in() ) {
			$user_data    = $auth_handler->get_current_user();
			$display_name = isset( $user_data['name'] ) ? $user_data['name'] : '';
			$email        = isset( $user_data['email'] ) ? $user_data['email'] : '';

			// Show logged-in user info instead of login button
			$logged_in_html = sprintf(
				'<div class="sm-user-info"><i class="fas fa-user-circle"></i> <span class="sm-user-name">%s</span></div>',
				esc_html( $display_name ? $display_name : $email )
			);

			return str_replace( '<!-- SM_LOGIN_BUTTON_PLACEHOLDER -->', $logged_in_html, $template );
		}

		// User not logged in - generate login button
		$login_url        = $auth_handler->get_login_url();
		$login_button_text = isset( $settings['login_button_text'] ) ? $settings['login_button_text'] : 'Login / Sign Up';

		if ( empty( $login_url ) ) {
			// Remove placeholder if login URL cannot be generated
			return str_replace( '<!-- SM_LOGIN_BUTTON_PLACEHOLDER -->', '', $template );
		}

		$login_button_html = sprintf(
			'<a href="%s" class="sm-login-button"><i class="fas fa-sign-in-alt"></i> %s</a>',
			esc_url( $login_url ),
			esc_html( $login_button_text )
		);

		return str_replace( '<!-- SM_LOGIN_BUTTON_PLACEHOLDER -->', $login_button_html, $template );
	}
}
