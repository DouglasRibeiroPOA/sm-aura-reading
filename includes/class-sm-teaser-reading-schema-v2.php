<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class SM_Teaser_Reading_Schema_V2
 *
 * Defines the v2 JSON schema for teaser readings, optimized for token reduction.
 */
class SM_Teaser_Reading_Schema_V2 {

    /**
     * Teaser reading type identifier.
     */
    const READING_TYPE = 'aura_teaser';

    /**
     * Master list of selectable traits.
     */
    const TRAIT_MASTER_LIST = array(
        'Intuition',
        'Creativity',
        'Resilience',
        'Emotional Depth',
        'Independence',
        'Adaptability',
        'Empathy',
        'Leadership',
        'Analytical Thinking',
        'Passion',
        'Patience',
        'Courage',
        'Wisdom',
        'Authenticity',
        'Determination',
    );

    /**
     * Returns the V2 schema definition for teaser readings.
     *
     * @return array The JSON schema as an associative array.
     */
    public static function get_schema() {
        return array(
            'meta'                     => array(
                'required' => true,
                'fields'   => array(
                    'user_name'    => array(
                        'type'       => 'string',
                        'required'   => true,
                        'min_length' => 1,
                        'max_length' => 120,
                    ),
                    'generated_at' => array(
                        'type'     => 'string',
                        'required' => true,
                        'format'   => 'datetime',
                    ),
                    'reading_type' => array(
                        'type'     => 'string',
                        'required' => true,
                        'allowed'  => array( self::READING_TYPE ),
                    ),
                ),
            ),
            'opening'                  => array(
                'required' => true,
                'fields'   => array(
                    'reflection_p1' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                    'reflection_p2' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                ),
            ),
            'life_foundations'         => array(
                'required' => true,
                'fields'   => array(
                    'paragraph_1' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 60,
                        'max_words' => 75,
                    ),
                    'paragraph_2' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 60,
                        'max_words' => 75,
                    ),
                    'paragraph_3' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                    'core_theme'  => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 20,
                        'max_words' => 35,
                    ),
                ),
            ),
            'love_patterns'            => array(
                'required' => true,
                'fields'   => array(
                    'preview'       => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                    'locked_teaser' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 12,
                        'max_words' => 20,
                    ),
                ),
            ),
            'career_success'           => array(
                'required' => true,
                'fields'   => array(
                    'main_paragraph'         => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 80,
                        'max_words' => 120,
                    ),
                    'modal_love_patterns'    => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 35,
                        'max_words' => 55,
                    ),
                    'modal_career_direction' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 35,
                        'max_words' => 55,
                    ),
                    'modal_life_alignment'   => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 35,
                        'max_words' => 55,
                    ),
                ),
            ),
            'personality_traits'       => array(
                'required' => true,
                'fields'   => array(
                    'intro'         => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 70,
                        'max_words' => 100,
                    ),
                    'trait_1_name'  => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                    'trait_1_score' => array(
                        'type'      => 'int',
                        'required'  => true,
                        'min_value' => 0,
                        'max_value' => 100,
                    ),
                    'trait_2_name'  => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                    'trait_2_score' => array(
                        'type'      => 'int',
                        'required'  => true,
                        'min_value' => 0,
                        'max_value' => 100,
                    ),
                    'trait_3_name'  => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                    'trait_3_score' => array(
                        'type'      => 'int',
                        'required'  => true,
                        'min_value' => 0,
                        'max_value' => 100,
                    ),
                ),
            ),
            'challenges_opportunities' => array(
                'required' => true,
                'fields'   => array(
                    'preview'       => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                    'locked_teaser' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 12,
                        'max_words' => 20,
                    ),
                ),
            ),
            'life_phase'               => array(
                'required' => true,
                'fields'   => array(
                    'preview'       => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                    'locked_teaser' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 12,
                        'max_words' => 20,
                    ),
                ),
            ),
            'timeline_6_months'        => array(
                'required' => true,
                'fields'   => array(
                    'preview'       => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                    'locked_teaser' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 12,
                        'max_words' => 20,
                    ),
                ),
            ),
            'guidance'                 => array(
                'required' => true,
                'fields'   => array(
                    'preview'       => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                    'locked_teaser' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 12,
                        'max_words' => 20,
                    ),
                ),
            ),
            'deep_relationship_analysis' => array(
                'required' => false,
                'fields'   => array(
                    'placeholder_text' => array(
                        'type'     => 'string',
                        'required' => false,
                        'default'  => 'This section contains deep relationship insights available in the full reading.',
                    ),
                ),
            ),
            'extended_timeline_12_months' => array(
                'required' => false,
                'fields'   => array(
                    'placeholder_text' => array(
                        'type'     => 'string',
                        'required' => false,
                        'default'  => 'This section contains a 12-month timeline available in the full reading.',
                    ),
                ),
            ),
            'life_purpose_soul_mission' => array(
                'required' => false,
                'fields'   => array(
                    'placeholder_text' => array(
                        'type'     => 'string',
                        'required' => false,
                        'default'  => 'This section explores life purpose and soul mission in the full reading.',
                    ),
                ),
            ),
            'shadow_work_transformation' => array(
                'required' => false,
                'fields'   => array(
                    'placeholder_text' => array(
                        'type'     => 'string',
                        'required' => false,
                        'default'  => 'This section covers shadow work insights in the full reading.',
                    ),
                ),
            ),
            'practical_guidance_action_plan' => array(
                'required' => false,
                'fields'   => array(
                    'placeholder_text' => array(
                        'type'     => 'string',
                        'required' => false,
                        'default'  => 'This section provides a practical action plan in the full reading.',
                    ),
                ),
            ),
            'closing'                  => array(
                'required' => true,
                'fields'   => array(
                    'paragraph_1' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                    'paragraph_2' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'min_words' => 40,
                        'max_words' => 60,
                    ),
                ),
            ),
        );
    }
}
