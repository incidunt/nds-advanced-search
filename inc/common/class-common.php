<?php

namespace NDS_Advanced_Search\Inc\Common;
use NDS_Advanced_Search as NS;

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, hooks and
 * the public-facing stylesheet and JavaScript.
 *
 * @link       http://nuancedesignstudio.in
 * @since      1.0.0
 *
 * @author    Karan NA Gupta
 */
class Common {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The text domain of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_text_domain    The text domain of this plugin.
	 */
	private $plugin_text_domain;

	/**
	 * The object to hold details for the post transient.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Array    $post_search_transient    The transient object.
	 */
	private $transient_search_cpt;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 * @param string $plugin_text_domain The text domain of this plugin.
	 */
	public function __construct( $plugin_name, $version, $plugin_text_domain ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->plugin_text_domain = $plugin_text_domain;
		$this->transient_search_cpt = json_decode( NS\PLUGIN_TRANSIENT, true );

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( 'jquery-ui', plugin_dir_url( __FILE__ ) . 'css/jquery-ui.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'font-awesome.min', plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/font-awesome.min.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/nds-advanced-search-common.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		$params = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		);
		wp_enqueue_script( 'nds_advanced_search_autosuggest', plugin_dir_url( __FILE__ ) . 'js/nds-advanced-search-autosuggest.js', array( 'jquery', 'jquery-ui-autocomplete' ), $this->version, true );
		wp_localize_script( 'nds_advanced_search_autosuggest', 'params', $params );

	}

	/**
	 * Delete cached posts from transients when a
	 * registered cpt has been published or updated.
	 *
	 * @since 1.0.0
	 */
	public function delete_post_cache() {
		// TODO combine transient operations in a separate class.
		// delete the transitent.
		$transient_name = $this->transient_search_cpt['autosuggest_transient'];
		if ( get_transient( $transient_name ) ) {
			delete_transient( $transient_name );
		}
	}

	/**
	 * Cache WordPress posts from multiple post types
	 * where the meta key include_in_search is set to true.
	 *
	 * @since 1.0.0
	 */
	public function cache_posts_in_post_types() {
		$transient_name = $this->transient_search_cpt['autosuggest_transient'];
		$transient_expiration = $this->transient_search_cpt['expiration'];

		// retrieve the post types to search.
		$plugin_options = get_option( $this->plugin_name );
		$post_types = array_keys( $plugin_options, "1" );

		// check the transient for existing cached data.
		$cached_posts = get_transient( $transient_name );
		if ( false === $cached_posts ) {
			$args = array(
				'post_type' => $post_types,
				'post_status' => 'publish',
				'posts_per_page' => -1,
			);

			// offer suggestions only for posts under events and videos.
			$custom_post_query = new \WP_Query( $args );
			$posts_in_custom_post_type = $custom_post_query->get_posts();

			if ( $posts_in_custom_post_type ) {
				foreach ( $posts_in_custom_post_type as $key => $post ) {

					$cached_post = array(
						'id' => $post->ID,
						'title' => esc_html( $post->post_title ),
						'permalink' => get_permalink( $post->ID ),
					);
					$cached_posts[] = $cached_post;
				}

				/**
				 * Save the post data in a transient.
				 * For better performance cache only the post ids, titles, and permalink
				 * instead of the entire WP Query.
				 */
				set_transient( $transient_name, $cached_posts, $transient_expiration );
			}
			wp_reset_postdata();
		}
		return $cached_posts;
	}

	/**
	 * AJAX handler for the autosuggest.
	 *
	 * @since    1.0.0
	 */
	public function advanced_search_autosuggest_handler() {

		$transient_name = $this->transient_search_cpt['autosuggest_transient'];

		// check if cached posts are available.
		$cached_posts = get_transient( $transient_name );
		if ( false === $cached_posts ) {

			// retrieve posts by running a new loop and cache the posts the transients as well.
			$cached_posts = $this->cache_posts_in_post_types();
		}

		$suggestions = array();
		foreach ( $cached_posts as $index => $post ) {
			$suggestions[ $index ] = $post['title'];
		}

		// Echo the response to the AJAX request.
		wp_send_json( $suggestions );

		// wp_send_json will also die().
	}

	/**
	 * Override get_search_form HTML markup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $form Form HTML.
	 * @return string Modified form HTML.
	 */
	public function advanced_search_form_markup( $form ) {

		ob_start();
		include_once( 'views/html-nds-advanced-search-form.php' );
		$form = ob_get_contents();
		ob_end_clean();

		return $form;
	}

	/**
	 * Register shortcodes.
	 *
	 * @since    1.0.0
	 */
	public function register_shortcodes() {

		add_shortcode( 'nds-advanced-search', array( $this, 'shortcode_nds_advanced_search' ) );

	}

	/**
	 * Shortcode to add the advanced search form.
	 *
	 * Loads the custom search form added via the get_search_form filter hook.
	 *
	 * retrieves a custom search form from "advanced_search_form_markup" that overrides searchform.php.
	 *
	 * @since    1.0.0
	 */
	public function shortcode_nds_advanced_search() {
		/*
		 * Hook in a custom search form to override searchform.php in the theme.
		 *
		 * Note: I am adding and removing the "get_search_form" filter as I want my
		 * advanced form to load only when I invoke it using the custom plugin shortcode.
		 *
		 * This will ensure that any form defined in the theme's searchform.php is not
		 * overwritten.
		 *
		 * To completely override searchform.php detele the add_filter and remove_filter
		 * lines above and uncomment line 165 in the method "define_common_hooks" of
		 * core/class-init.php.
		 */

		add_filter( 'get_search_form', array( $this, 'advanced_search_form_markup' ) );
		get_search_form();
		remove_filter( 'get_search_form', array( $this, 'advanced_search_form_markup' ) );

		$search_term = isset( $_REQUEST['search_key'] ) ? $_REQUEST['search_key'] : false;
		if ( isset( $search_term ) && ! empty( $search_term ) ) {
			include_once( 'views/html-nds-advanced-search-results.php' );
		}

	}
}