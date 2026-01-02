<?php
/**
 * Development Mode Handler
 *
 * Provides mock endpoints for OpenAI and MailerLite APIs to avoid spending credits during development.
 *
 * @package MysticPalmReading
 * @since 1.3.8
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Dev_Mode
 *
 * Manages development mode settings and provides mock API responses.
 */
class SM_Dev_Mode {

	/**
	 * Singleton instance
	 *
	 * @var SM_Dev_Mode|null
	 */
	private static $instance = null;

	/**
	 * WordPress option key for DevMode mode.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'sm_dev_mode_mode';

	/**
	 * DevMode modes.
	 */
	private const MODE_PRODUCTION          = 'production';
	private const MODE_DEV_ALL             = 'dev_all';
	private const MODE_DEV_OPENAI_ONLY     = 'dev_openai_only';
	private const MODE_DEV_MAILERLITE_ONLY = 'dev_mailerlite_only';

	/**
	 * Initialize the handler (singleton pattern)
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
	}

	/**
	 * Get singleton instance
	 *
	 * @return SM_Dev_Mode
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::init();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		// Register REST API endpoints for mocks
		add_action( 'rest_api_init', array( $this, 'register_mock_endpoints' ) );

		// Filter the account service URL when DevMode is enabled
		add_filter( 'sm_account_service_url', array( $this, 'filter_account_service_url' ) );

		// Register WP-CLI commands if available
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'sm devmode', array( $this, 'cli_command' ) );
		}
	}

	/**
	 * Filter the Account Service URL to a local one in DevMode.
	 *
	 * @param string $url The original Account Service URL.
	 * @return string The modified URL.
	 */
	public function filter_account_service_url( $url ) {
		if ( self::is_enabled() && empty( $url ) ) {
			return 'http://account.sm-aura-reading.local';
		}
		return $url;
	}

	/**
	 * WP-CLI command handler for DevMode
	 *
	 * Usage:
	 *   wp sm devmode enable
	 *   wp sm devmode disable
	 *   wp sm devmode status
	 *   wp sm devmode set <production|dev_all|dev_openai_only|dev_mailerlite_only>
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function cli_command( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify a subcommand: enable, disable, or status' );
			return;
		}

		$subcommand = $args[0];

		switch ( $subcommand ) {
			case 'enable':
				$result = self::set_mode( self::MODE_DEV_ALL );
				if ( $result ) {
					WP_CLI::success( 'DevMode enabled (dev_all) - API calls will use mock endpoints' );
					WP_CLI::log( 'Mock OpenAI URL: ' . self::get_mock_openai_url() );
					WP_CLI::log( 'Mock MailerLite URL: ' . self::get_mock_mailerlite_url() );
				} else {
					WP_CLI::error( 'Failed to enable DevMode' );
				}
				break;

			case 'disable':
				$result = self::set_mode( self::MODE_PRODUCTION );
				if ( $result ) {
					WP_CLI::success( 'DevMode disabled - API calls will use real endpoints' );
				} else {
					WP_CLI::error( 'Failed to disable DevMode' );
				}
				break;

			case 'set':
				$mode = isset( $args[1] ) ? $args[1] : '';
				if ( ! self::is_valid_mode( $mode ) ) {
					WP_CLI::error( 'Invalid mode. Use production, dev_all, dev_openai_only, or dev_mailerlite_only.' );
				}
				$result = self::set_mode( $mode );
				if ( $result ) {
					WP_CLI::success( 'DevMode set to ' . $mode );
				} else {
					WP_CLI::error( 'Failed to set DevMode' );
				}
				break;

			case 'status':
				$mode = self::get_mode();
				if ( self::MODE_PRODUCTION !== $mode ) {
					WP_CLI::log( 'DevMode: ' . strtoupper( $mode ) );
					WP_CLI::log( 'Mock OpenAI URL: ' . self::get_mock_openai_url() );
					WP_CLI::log( 'Mock MailerLite URL: ' . self::get_mock_mailerlite_url() );
				} else {
					WP_CLI::log( 'DevMode: DISABLED (using real API endpoints)' );
				}
				break;

			default:
				WP_CLI::error( "Unknown subcommand: {$subcommand}. Use 'enable', 'disable', or 'status'" );
				break;
		}
	}

	/**
	 * Get DevMode mode.
	 *
	 * @return string
	 */
	public static function get_mode() {
		$mode = get_option( self::OPTION_KEY, self::MODE_PRODUCTION );
		return self::is_valid_mode( $mode ) ? $mode : self::MODE_PRODUCTION;
	}

	/**
	 * Check if DevMode is enabled (any non-production mode).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return self::MODE_PRODUCTION !== self::get_mode();
	}

	/**
	 * Set DevMode mode.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function set_mode( $mode ) {
		$mode = sanitize_key( (string) $mode );
		if ( ! self::is_valid_mode( $mode ) ) {
			return false;
		}

		$result = update_option( self::OPTION_KEY, $mode );

		if ( $result ) {
			SM_Logger::log( 'info', 'DEV_MODE', 'DevMode mode updated', array( 'mode' => $mode ) );
		}

		return $result;
	}

	/**
	 * Check if OpenAI should be mocked.
	 *
	 * @return bool
	 */
	public static function should_mock_openai() {
		$mode = self::get_mode();
		return in_array( $mode, array( self::MODE_DEV_ALL, self::MODE_DEV_OPENAI_ONLY ), true );
	}

	/**
	 * Check if MailerLite should be mocked.
	 *
	 * @return bool
	 */
	public static function should_mock_mailerlite() {
		$mode = self::get_mode();
		return in_array( $mode, array( self::MODE_DEV_ALL, self::MODE_DEV_MAILERLITE_ONLY ), true );
	}

	/**
	 * Check if MailerLite should be skipped entirely.
	 *
	 * @return bool
	 */
	public static function should_skip_mailerlite() {
		return self::MODE_DEV_MAILERLITE_ONLY === self::get_mode();
	}

	/**
	 * Validate DevMode mode.
	 *
	 * @param string $mode Mode value.
	 * @return bool
	 */
	private static function is_valid_mode( $mode ) {
		return in_array(
			$mode,
			array( self::MODE_PRODUCTION, self::MODE_DEV_ALL, self::MODE_DEV_OPENAI_ONLY, self::MODE_DEV_MAILERLITE_ONLY ),
			true
		);
	}

	/**
	 * Register mock REST API endpoints
	 *
	 * @return void
	 */
	public function register_mock_endpoints() {
		// Mock OpenAI endpoint
		register_rest_route(
			'soulmirror-dev/v1',
			'/mock-openai',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mock_openai_response' ),
				'permission_callback' => '__return_true', // DevMode only, no auth required
			)
		);

		// Mock MailerLite endpoint
		register_rest_route(
			'soulmirror-dev/v1',
			'/mock-mailerlite',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mock_mailerlite_response' ),
				'permission_callback' => '__return_true', // DevMode only, no auth required
			)
		);
	}

	/**
	 * Mock OpenAI API response
	 *
	 * Returns a realistic OpenAI API response for teaser reading generation.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return array Mock OpenAI response.
	 */
	public function mock_openai_response( $request ) {
		SM_Logger::log( 'info', 'DEV_MODE', 'Mock OpenAI API called', array(
			'request_body_size' => strlen( $request->get_body() ),
		) );

		// Simulate API processing delay (1-2 seconds)
		sleep( rand( 1, 2 ) );

		// Get request parameters to extract user name if possible
		$params = $request->get_json_params();
		$user_name = 'Seeker'; // Default name

		// Try to extract name from messages
		if ( isset( $params['messages'] ) && is_array( $params['messages'] ) ) {
			foreach ( $params['messages'] as $message ) {
				if ( ! isset( $message['content'] ) ) {
					continue;
				}

				$parts = array();
				if ( is_array( $message['content'] ) ) {
					foreach ( $message['content'] as $part ) {
						if ( is_string( $part ) ) {
							$parts[] = $part;
							continue;
						}
						if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
							$parts[] = $part['text'];
							continue;
						}
						if ( is_array( $part ) && isset( $part['text']['value'] ) && is_string( $part['text']['value'] ) ) {
							$parts[] = $part['text']['value'];
						}
					}
				} elseif ( is_string( $message['content'] ) ) {
					$parts[] = $message['content'];
				}

				$content = implode( ' ', $parts );
				if ( '' === $content ) {
					continue;
				}

				if ( preg_match( '/Name:\s*([A-Za-z]+)/i', $content, $matches ) ) {
					$user_name = $matches[1];
					break;
				}
			}
		}

		// Detect paid completion prompts (Phase 2).
		$is_paid = false;
		if ( isset( $params['messages'] ) && is_array( $params['messages'] ) ) {
			foreach ( $params['messages'] as $message ) {
				if ( ! isset( $message['content'] ) ) {
					continue;
				}

				$parts = array();
				if ( is_array( $message['content'] ) ) {
					foreach ( $message['content'] as $part ) {
						if ( is_string( $part ) ) {
							$parts[] = $part;
							continue;
						}
						if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
							$parts[] = $part['text'];
							continue;
						}
						if ( is_array( $part ) && isset( $part['text']['value'] ) && is_string( $part['text']['value'] ) ) {
							$parts[] = $part['text']['value'];
						}
					}
				} elseif ( is_string( $message['content'] ) ) {
					$parts[] = $message['content'];
				}

				$content = implode( ' ', $parts );
				if ( '' === $content ) {
					continue;
				}

				if (
					false !== strpos( $content, '"reading_type": "aura_full"' ) ||
					false !== strpos( $content, 'aura_full' ) ||
					false !== strpos( $content, 'paid completion' ) ||
					false !== strpos( $content, 'Phase 2' ) ||
					false !== strpos( $content, 'full_content' )
				) {
					$is_paid = true;
					break;
				}
			}
		}

		// Mock reading JSON response
		$mock_reading = $is_paid
			? $this->generate_mock_paid_completion_reading( $user_name )
			: $this->generate_mock_teaser_reading( $user_name );

		return array(
			'id'      => 'chatcmpl-mock-' . wp_generate_uuid4(),
			'object'  => 'chat.completion',
			'created' => time(),
			'model'   => 'gpt-4o',
			'choices' => array(
				array(
					'index'   => 0,
					'message' => array(
						'role'    => 'assistant',
						'content' => wp_json_encode( $mock_reading ),
					),
					'finish_reason' => 'stop',
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 1500,
				'completion_tokens' => 1800,
				'total_tokens'      => 3300,
			),
		);
	}

	/**
	 * Generate mock teaser reading data
	 *
	 * @param string $name User's name.
	 * @return array Mock reading data structure.
	 */
	private function generate_mock_teaser_reading( $name = 'Seeker' ) {
		return array(
			'meta' => array(
				'user_name'    => $name,
				'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'reading_type' => 'aura_teaser',
			),
			'opening' => array(
				'reflection_p1' => "Your palm holds patterns shaped by experience, intention, and instinct. {$name}, the lines you see are not fixed—they shift as you do, responding to choice, resilience, and inner alignment. This reflection mirrors the way you have adapted, recovered, and kept moving forward.",
				'reflection_p2' => 'This teaser captures what rises first: your strongest themes, clearest signals, and the core questions your hand reflects back. The full reading goes deeper, revealing timelines, hidden strengths, and the specific guidance your palm offers for the path ahead, so you can move with confidence.',
			),
			'life_foundations' => array(
				'paragraph_1' => 'The primary lines in your palm suggest resilience shaped through experience rather than ease. Your life line shows depth and vitality, but also markers of times when you had to recalibrate, trust yourself differently, or build strength from what felt like loss. This suggests a steady inner engine that returns when you recommit to yourself.',
				'paragraph_2' => "Your emotional nature runs deep, {$name}, but you don't give access instantly. The heart line reveals discernment—you open slowly but completely. When you commit to a path or a person, it's intentional, and that commitment holds weight. You value consistency over intensity, and that keeps your heart steady.",
				'paragraph_3' => 'There\'s evidence here of a mind that works through pattern recognition and intuition rather than pure logic. You sense shifts before they become obvious, and this has likely saved you more than once. The more you trust that signal, the clearer your outcomes become.',
				'core_theme' => 'You evolve through lived experience, not shortcuts. What looks like detours have been building something essential.',
			),
			'love_patterns' => array(
				'preview' => 'Your heart line points to emotional depth paired with discernment. You are drawn to sincerity over excitement, and you can tell the difference early. This protects you but also slows down connections. There is a pattern here that explains why certain connections feel fated and others fade quickly.',
				'locked_teaser' => 'Your love pattern + what to stop repeating.',
			),
			'career_success' => array(
				'main_paragraph' => "Your palm suggests success grows best through alignment, not pressure. The head line shows strategic thinking and an ability to see around corners, but it also reveals tension when you're working in environments that don't value your instincts. {$name}, career satisfaction for you isn't about title or income alone—it's about having ownership over the work and seeing direct impact. You do best when your decisions shape the outcomes you are measured by. This is a steady-builder signature: fewer big leaps, more consistent gains that compound over time. When your work feels meaningful, your energy stays strong and your results follow.",
				'modal_love_patterns' => 'You open slowly but deeply. You read sincerity fast, and you commit only when actions match words. Calm, consistent love is your strongest signal.',
				'modal_career_direction' => 'You thrive with ownership and impact. Autonomy fuels your best work, while micromanagement drains you. Build where your choices change outcomes.',
				'modal_life_alignment' => 'Your balance comes from rhythm: deep work, deep rest, and clear boundaries. When you protect your pace, your energy stays steady.',
			),
			'personality_traits' => array(
				'intro' => 'Your hand shape and finger alignment suggest strong intuition paired with practical thinking. You are not purely abstract or purely grounded—you move between both, and this dual capacity is one of your greatest strengths. You notice undercurrents quickly, then ground them in action. This is why you often see what is coming before others do. Below are the three core traits most reflected in your palm and why they keep showing up in your choices.',
				'trait_1_name' => 'Intuition',
				'trait_1_score' => 88,
				'trait_2_name' => 'Resilience',
				'trait_2_score' => 92,
				'trait_3_name' => 'Independence',
				'trait_3_score' => 85,
			),
			'challenges_opportunities' => array(
				'preview' => 'Your palm reveals tensions between comfort and growth. You know what needs to shift, but part of you resists because the familiar feels safer, even when it no longer serves. The obstacles you face now are actually doorways that ask you to release an old pattern.',
				'locked_teaser' => 'What to release + where to double down.',
			),
			'life_phase' => array(
				'preview' => 'Your palm suggests a transition period where old structures are dissolving and new clarity is forming, but you are not fully in either space yet. This phase often feels quiet on the outside and loud on the inside as you recalibrate priorities.',
				'locked_teaser' => 'Your current life chapter + the next step.',
			),
			'timeline_6_months' => array(
				'preview' => 'The next six months hold a pattern of emergence. What has been internal will start becoming visible, with subtle shifts early on and a bigger opening in spring. The timeline suggests momentum building and a clear signal to act.',
				'locked_teaser' => 'Month-by-month guidance + key decision points.',
			),
			'guidance' => array(
				'preview' => "Notice where you're forcing certainty. Your hand suggests the next shift comes through patience, not pressure. Let things unfold without needing to control every variable. {$name}, trust that the right doors will open when you're truly ready. The deeper layer clarifies what deserves your devotion.",
				'locked_teaser' => 'Three focus points + what deserves your devotion.',
			),
			'deep_relationship_analysis' => array(
				'placeholder_text' => 'This section contains deep relationship insights available in the full reading.',
			),
			'extended_timeline_12_months' => array(
				'placeholder_text' => 'This section contains a 12-month timeline available in the full reading.',
			),
			'life_purpose_soul_mission' => array(
				'placeholder_text' => 'This section explores life purpose and soul mission in the full reading.',
			),
			'shadow_work_transformation' => array(
				'placeholder_text' => 'This section covers shadow work and transformation in the full reading.',
			),
			'practical_guidance_action_plan' => array(
				'placeholder_text' => 'This section provides a practical guidance plan in the full reading.',
			),
			'closing' => array(
				'paragraph_1' => 'This teaser captures what rises first: your strongest themes, clearest patterns, and the questions your palm reflects back. The full reading reveals deeper layers—timelines, hidden strengths, and the specific next steps your hand suggests.',
				'paragraph_2' => "If you're ready, {$name}, reveal the full meaning and see what your palm is truly pointing toward.",
			),
		);
	}

	/**
	 * Generate mock paid completion reading data (Phase 2).
	 *
	 * @param string $name User's name.
	 * @return array Mock paid completion structure.
	 */
	private function generate_mock_paid_completion_reading( $name = 'Seeker' ) {
		$base = "{$name}, this insight builds on your earlier reading with deeper clarity and practical direction.";

		return array(
			'meta' => array(
				'user_name'    => $name,
				'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'reading_type' => 'aura_full',
			),
			'love_patterns' => array(
				'locked_full' => $base . ' Your heart line suggests steady devotion with a cautious opening. You thrive when you feel chosen and seen, but you also need space to process before committing. The most aligned partner offers consistency without pressure and communicates through action as much as words. You are not drawn to drama, you are drawn to steadiness. When you honor your own pace, your relationships deepen quickly and feel sustainable. The key is to ask directly for what you need instead of waiting for it to be noticed.',
			),
			'challenges_opportunities' => array(
				'locked_full' => $base . ' Your challenge is over-commitment and the urge to prove yourself through constant effort. Your opportunity is discernment: choosing fewer paths so each one can fully bloom. The lines in your palm suggest you are most powerful when you say no early, then invest deeply. You are not meant to spread yourself thin; you are meant to focus and build. This shift turns your energy from scattered to magnetic and makes progress feel lighter.',
			),
			'life_phase' => array(
				'locked_full' => $base . ' You are entering a phase of consolidation and momentum. The patterns show a shift toward clarity after a period of uncertainty and a return to values that feel rooted. This is a building phase, not a rushing phase. When you slow down, your intuition becomes sharper and your next step feels obvious. The best results come from consistent routines and a clear yes to one direction.',
			),
			'timeline_6_months' => array(
				'locked_full' => $base . ' The next six months emphasize strategic decisions and boundary-setting. A window opens for a meaningful choice around your work or living situation, followed by a steady rise in confidence. Align your calendar to your highest priorities, and protect your mornings for focused creation. Momentum builds when you keep your energy clean. By late spring, a clear decision point arrives and creates a new rhythm for the rest of the year.',
			),
			'guidance' => array(
				'locked_full' => $base . ' Choose one focus to deepen, one habit to release, and one relationship to nourish. Create a small ritual that signals “I am available for my future,” even if it takes five minutes a day. Your palm shows that small, consistent actions will compound into big shifts for you. Keep your plan simple and sustainable, and your results will build faster than you expect.',
			),
			'deep_relationship_analysis' => array(
				'full_content' => $base . ' Your attachment pattern favors depth over volume. You do not connect with many people, but when you do, the bond is real. The most aligned partner will respect your pace, value long-term trust, and show consistency in small, everyday ways. You are most fulfilled when love feels calm and safe, not dramatic. The shift for you is to ask directly for what you need rather than hoping it will be noticed. When you do, your relationships deepen quickly and feel more secure.',
			),
			'extended_timeline_12_months' => array(
				'full_content' => $base . ' Over the next year, timing favors steady progress, with a meaningful turning point around the mid-year mark. The first quarter is about clearing space and finishing old obligations. The middle of the year carries a strong opening for a new commitment or role. The final quarter is about refinement, where you integrate what you learned and set a new rhythm. Expect the clearest momentum after you simplify your priorities.',
			),
			'life_purpose_soul_mission' => array(
				'full_content' => $base . ' Your purpose centers on creating stability for others while honoring your own creative truth. You are here to build something dependable, whether it is a space, a practice, or a community, while still protecting your independence. Your most fulfilling path blends service with autonomy and allows your intuition to guide the structure. When you create from that place, you feel aligned and energized. The more you trust your instinct, the more people naturally follow your lead.',
			),
			'shadow_work_transformation' => array(
				'full_content' => $base . ' The shadow theme is self-doubt disguised as caution. You sometimes delay action while searching for certainty, but the lines in your palm show that movement creates clarity for you. The antidote is action paired with compassionate self-checks: move, evaluate, adjust. This cycle turns fear into wisdom. The more you practice it, the more confident you become in your own timing.',
			),
			'practical_guidance_action_plan' => array(
				'full_content' => $base . ' Commit to three actions: protect your mornings for focused work, complete one meaningful project before starting another, and schedule consistent rest. Choose one person to share your goals with, and meet weekly for accountability. This simple structure aligns with your palm’s indication of steady, long-term success. Keep the plan small, repeatable, and honest, and your results will compound.',
			),
		);
	}

	/**
	 * Mock MailerLite API response
	 *
	 * Returns a realistic MailerLite API response for subscriber sync.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return array Mock MailerLite response.
	 */
	public function mock_mailerlite_response( $request ) {
		SM_Logger::log( 'info', 'DEV_MODE', 'Mock MailerLite API called', array(
			'request_body' => $request->get_json_params(),
		) );

		// Simulate API processing delay
		sleep( 1 );

		// Parse request to get email
		$params = $request->get_json_params();
		$email = isset( $params['email'] ) ? $params['email'] : 'subscriber@example.com';
		$name = isset( $params['fields']['name'] ) ? $params['fields']['name'] : 'Subscriber';

		return array(
			'data' => array(
				'id'         => 'mock-subscriber-' . md5( $email ),
				'email'      => $email,
				'status'     => 'active',
				'source'     => 'api',
				'sent'       => 0,
				'opens_count' => 0,
				'clicks_count' => 0,
				'open_rate'  => 0,
				'click_rate' => 0,
				'ip_address' => null,
				'subscribed_at' => gmdate( 'Y-m-d H:i:s' ),
				'unsubscribed_at' => null,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
				'fields' => array(
					'name' => $name,
					'company' => isset( $params['fields']['company'] ) ? $params['fields']['company'] : null,
				),
			),
		);
	}

	/**
	 * Get mock OpenAI endpoint URL
	 *
	 * @return string Mock endpoint URL.
	 */
	public static function get_mock_openai_url() {
		return rest_url( 'soulmirror-dev/v1/mock-openai' );
	}

	/**
	 * Get mock MailerLite endpoint URL
	 *
	 * @return string Mock endpoint URL.
	 */
	public static function get_mock_mailerlite_url() {
		return rest_url( 'soulmirror-dev/v1/mock-mailerlite' );
	}
}
