<?php
/*
Plugin Name: Addon Elementor Container Link
Plugin URI: https://wordpress.org/plugins/addon-elementor-container-link/
Description: Enhance your Elementor Website Builder with Addon Elementor Container Link by adding custom URL or link support to containers, sections, inner sections, and columns.
Version: 1.4
Author: Faiz R
Author URI: https://github.com/faizz-rasul
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: addon-elementor-container-link
Donate link: https://www.paypal.com/donate/?hosted_button_id=EV2YSF9KBVTBY
Requires PHP: 7.4
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Load plugin text domain for translations.
 */
function fr_add_container_link_load_textdomain() {
    load_plugin_textdomain( 'addon-elementor-container-link', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'fr_add_container_link_load_textdomain' );

/**
 * Display an admin notice if Elementor is not installed or active.
 */
function fr_elementor_required_admin_notice() {
    if ( is_admin() && current_user_can( 'activate_plugins' ) && ! did_action( 'elementor/loaded' ) ) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php echo esc_html__( 'Addon Elementor Container Link requires Elementor to be installed and active. Please install or activate Elementor to use this plugin.', 'addon-elementor-container-link' ); ?></p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'fr_elementor_required_admin_notice' );

/**
 * Initialize Elementor hooks once Elementor has loaded.
 */
function fr_init_container_link() {
    if ( ! did_action( 'elementor/loaded' ) ) {
        return;
    }

    // Register Container Link control on sections, containers, and columns.
    add_action( 'elementor/element/section/section_layout/after_section_end', 'fr_add_container_link_option' );
    add_action( 'elementor/element/container/section_layout_container/after_section_end', 'fr_add_container_link_option' );
    add_action( 'elementor/element/container/section_layout/after_section_end', 'fr_add_container_link_option' );
    add_action( 'elementor/element/column/layout/after_section_end', 'fr_add_container_link_option' );

    // Apply link render attributes before rendering elements.
    add_action( 'elementor/frontend/section/before_render', 'fr_render_container_link' );
    add_action( 'elementor/frontend/container/before_render', 'fr_render_container_link' );
    add_action( 'elementor/frontend/column/before_render', 'fr_render_container_link' );

    // Enqueue frontend scripts and styles cleanly via Elementor & WordPress APIs.
    add_action( 'elementor/frontend/after_enqueue_scripts', 'fr_enqueue_container_link_assets' );
    add_action( 'wp_enqueue_scripts', 'fr_enqueue_container_link_assets', 25 );
}
add_action( 'plugins_loaded', 'fr_init_container_link', 20 );

/**
 * "Container Link" control section to Elementor element.
 *
 * @param \Elementor\Element_Base $element
 */
function fr_add_container_link_option( $element ) {
    // Avoid registering duplicate control sections if multiple hooks run on the same element.
    if ( method_exists( $element, 'get_controls' ) && $element->get_controls( 'container_link' ) ) {
        return;
    }

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

/**
 * Wrapper attributes to make the element clickable on the frontend.
 *
 * @param \Elementor\Element_Base $element
 */
function fr_render_container_link( $element ) {
    $settings = $element->get_settings_for_display();

    if ( empty( $settings['container_link']['url'] ) ) {
        return;
    }

    $link_data = $settings['container_link'];
    $link_url  = esc_url( $link_data['url'] );
    $target    = ! empty( $link_data['is_external'] ) ? '_blank' : '_self';
    $nofollow  = ! empty( $link_data['nofollow'] ) ? 'nofollow' : '';

    $attributes = [
        'class'                         => 'fr-container-link',
        'data-fr-container-link-url'    => $link_url,
        'data-fr-container-link-target' => $target,
        'role'                          => 'link',
        'tabindex'                      => '0',
    ];

    if ( ! empty( $nofollow ) ) {
        $attributes['rel'] = $nofollow;
    }

    $element->add_render_attribute( '_wrapper', $attributes );
}

/**
 * Enqueue frontend CSS and JavaScript assets.
 */
function fr_enqueue_container_link_assets() {
    static $assets_enqueued = false;
    if ( $assets_enqueued ) {
        return;
    }

    $custom_css = '.fr-container-link { cursor: pointer; }';
    if ( wp_style_is( 'elementor-frontend', 'registered' ) || wp_style_is( 'elementor-frontend', 'enqueued' ) ) {
        wp_add_inline_style( 'elementor-frontend', $custom_css );
    }

    $custom_js = "
(function( $ ) {
    'use strict';

    function handleContainerClick( e, el ) {
        // Prevent navigation while editing inside Elementor Editor
        if ( ( typeof elementorFrontend !== 'undefined' && elementorFrontend.isEditMode && elementorFrontend.isEditMode() ) || $( 'body' ).hasClass( 'elementor-editor-active' ) ) {
            return;
        }

        // If the user was selecting/highlighting text, do not navigate
        if ( window.getSelection && window.getSelection().toString().length > 0 ) {
            return;
        }

        var linkUrl    = $( el ).data( 'fr-container-link-url' );
        var linkTarget = $( el ).data( 'fr-container-link-target' );

        if ( ! linkUrl ) {
            return;
        }

        // Ignore clicks on child links, buttons, form controls, and interactive widgets
        if ( $( e.target ).closest( 'a, button, input, textarea, select, label, .elementor-button, .elementor-icon, .elementor-widget-button' ).length ) {
            return;
        }

        e.stopPropagation();

        // Support opening in new tab via option, Ctrl/Cmd click, or middle mouse click
        if ( linkTarget === '_blank' || e.ctrlKey || e.metaKey || e.which === 2 ) {
            window.open( linkUrl, '_blank', 'noopener,noreferrer' );
        } else {
            window.location.href = linkUrl;
        }
    }

    $( document ).on( 'click auxclick', '.fr-container-link', function( e ) {
        if ( e.type === 'auxclick' && e.which !== 2 ) {
            return;
        }
        handleContainerClick( e, this );
    } );

    $( document ).on( 'keydown', '.fr-container-link', function( e ) {
        if ( e.key === 'Enter' ) {
            handleContainerClick( e, this );
        }
    } );
})( jQuery );
    ";

    if ( wp_script_is( 'elementor-frontend', 'registered' ) || wp_script_is( 'elementor-frontend', 'enqueued' ) ) {
        wp_add_inline_script( 'elementor-frontend', $custom_js );
    }

    $assets_enqueued = true;
}

/**
 * "Buy cup of coffee" donate link.
 *
 * @param array  $links An array of plugin row meta links.
 * @param string $file  The current plugin file.
 * @return array An array of plugin row meta links.
 */
function fr_add_support_link_to_plugin_row_meta( $links, $file ) {
    if ( $file !== plugin_basename( __FILE__ ) ) {
        return $links;
    }

    $support_link = '<a href="https://www.paypal.com/donate/?hosted_button_id=EV2YSF9KBVTBY" target="_blank" rel="noopener noreferrer">' . __( 'Buy cup of coffee', 'addon-elementor-container-link' ) . '</a>';

    $links[] = $support_link;

    return $links;
}

if ( is_admin() ) {
    add_filter( 'plugin_row_meta', 'fr_add_support_link_to_plugin_row_meta', 10, 2 );
}