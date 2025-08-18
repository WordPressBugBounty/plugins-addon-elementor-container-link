<?php
/*
Plugin Name: Addon Elementor Container Link
Plugin URI: https://wordpress.org/plugins/addon-elementor-container-link/
Description: Enhance your Elementor Website Builder with addon elementor container plugin by adding custom URL or Link to container, section, inner sections or columns.
Version: 1.3
Author: Faiz R
Author URI: https://github.com/faizz-rasul
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: addon-elementor-container-link
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// load text domain
function fr_add_container_link_load_textdomain() {
    load_plugin_textdomain( 'addon-elementor-container-link', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'fr_add_container_link_load_textdomain' );

// Hook into Elementor's section & container structure settings
add_action( 'elementor/element/section/section_layout/after_section_end', 'fr_add_container_link_option' );
add_action( 'elementor/element/container/section_layout/after_section_end', 'fr_add_container_link_option' );

function fr_add_container_link_option( $element ) {
    $element->start_controls_section(
        'section_container_link',
        [
            'label' => __( 'Container Link', 'addon-elementor-container-link' ),
            'tab'   => \Elementor\Controls_Manager::TAB_LAYOUT,
        ]
    );

    $element->add_control(
        'container_link',
        [
            'label'         => __( 'Link', 'addon-elementor-container-link' ),
            'type'          => \Elementor\Controls_Manager::URL,
            'placeholder'   => __( 'https://your-link.com', 'addon-elementor-container-link' ),
            'dynamic'       => [ 'active' => true ],
            'show_external' => true,
            'default'       => [
                'url'         => '',
                'is_external' => false,
                'nofollow'    => false,
            ],
        ]
    );

    $element->end_controls_section();
}

// Save and retrieve the link value
add_action( 'elementor/frontend/section/before_render', 'fr_render_container_link' );
add_action( 'elementor/frontend/container/before_render', 'fr_render_container_link' );

function fr_render_container_link( $element ) {
    // Using get_settings_for_display() is safer as it processes dynamic tags.
    $settings = $element->get_settings_for_display();

    // Check if the link settings and the URL itself exist.
    if ( ! empty( $settings['container_link'] ) && ! empty( $settings['container_link']['url'] ) ) {

        $link_data = $settings['container_link'];
        $link_url    = $link_data['url'];
        $is_external = ! empty( $link_data['is_external'] ); // Check if 'Open in new window' is ticked.

        ?>
        <script>
        jQuery( document ).ready( function( $ ) {
            // Use .on('click', ...) as it is slightly more modern and flexible.
            $( '.elementor-element-<?php echo esc_attr( $element->get_id() ); ?>' ).css( 'cursor', 'pointer' ).on( 'click', function(e) {
                
                var url = '<?php echo esc_url( $link_url ); ?>';

                <?php if ( $is_external ) : ?>
                    // If 'is_external' is true, open the link in a new tab.
                    window.open( url, '_blank' );
                <?php else : ?>
                    // Otherwise, open the link in the same tab.
                    window.location.href = url;
                <?php endif; ?>
            });
        });
        </script>
        <?php
    }
}