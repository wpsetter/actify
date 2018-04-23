<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ACTIFY
 */
class ACTIFY {

	protected $component_uri;
	protected $component_dir;
	protected $plugin_version;

	/**
	 * ACTIFY constructor.
	 *
	 * @param $component_dir
	 * @param $component_uri
	 * @param $plugin_version
	 */
	public function __construct( $component_dir, $component_uri, $plugin_version ) {

		$this->component_dir  = $component_dir;
		$this->component_uri  = $component_uri;
		$this->plugin_version = $plugin_version;

		$this->register_post_types();

		add_action( 'add_meta_boxes_actify_highlights', array( $this, 'add_highlight_meta_box' ) );

		add_action( 'admin_menu', array( $this, 'add_options_page' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_scripts' ) );

		$this->add_ajax_callbacks();
		$this->add_nopriv_ajax_callbacks();

		add_filter( 'the_content', array( $this, 'wrap_content_in_component' ) );

	}

	/**
	 * Add nopriv ajax callbacks.
	 */
	public function add_nopriv_ajax_callbacks() {

		add_action( 'wp_ajax_nopriv_actify_report_mistake', array( $this, 'report_mistake' ) );
		add_action( 'wp_ajax_nopriv_actify_report_case', array( $this, 'report_case' ) );
		add_action( 'wp_ajax_nopriv_actify_save_highlight', array( $this, 'save_highlight' ) );
		add_action( 'wp_ajax_nopriv_actify_get_highlights', array( $this, 'get_highlights' ) );

	}

	/**
	 * Add ajax callbacks
	 */
	public function add_ajax_callbacks() {

		add_action( 'wp_ajax_actify_save_options', array( $this, 'save_options' ) );
		add_action( 'wp_ajax_actify_report_mistake', array( $this, 'report_mistake' ) );
		add_action( 'wp_ajax_actify_report_case', array( $this, 'report_case' ) );
		add_action( 'wp_ajax_actify_save_highlight', array( $this, 'save_highlight' ) );
		add_action( 'wp_ajax_actify_get_highlights', array( $this, 'get_highlights' ) );

	}

	/**
	 * Register post types.
	 */
	public function register_post_types() {

		$this->register_typo_post_type();
		$this->register_reports_post_type();
		$this->register_highlights_post_type();

	}

	/**
	 * Get highlights for frontend render.
	 */
	public function get_highlights() {

		if ( ! check_ajax_referer( 'actify-get-highlights-nonce', 'nonce' ) ) {
			wp_send_json_error( 'Invalid security token sent.' );
		};

		$sliced  = wp_array_slice_assoc( $_POST, array( 'post_id' ) );
		$post_id = (int) $sliced['post_id'];

		$highlights = $this->get_highlights_from_post( $post_id );

		wp_send_json_success( $highlights );

	}

	/**
	 * Get array of highlights by $post_id.
	 *
	 * @param $post_id
	 *
	 * @return array
	 */
	public function get_highlights_from_post( $post_id ) {

		$highlights = array();
		$args       = array(
			'posts_per_page' => - 1,
			'post_type'      => 'actify_highlights',
			'meta_key'       => 'highlight_from',
			'meta_query'     => array(
				array(
					'key'     => 'highlight_from',
					'value'   => $post_id,
					'compare' => '=',
				)
			)
		);
		$query      = new WP_Query( $args );

		if ( $query->have_posts() ) {

			foreach ( $query->posts as $post ) {
				$highlights[] = array( 'id' => $post->ID, 'highlight' => $post->post_content );
			}
			wp_reset_postdata();

		}

		return $highlights;

	}

	/**
	 * Ajax callback that save_highlight.
	 */
	public function save_highlight() {

		if ( ! check_ajax_referer( 'actify-highlight-nonce', 'nonce' ) ) {
			wp_send_json_error( __( 'Invalid security token sent.', 'actify' ) );
		};

		$request = wp_safe_remote_get( add_query_arg( array(
				'secret'   => $this->get_option_value( 'recaptcha_secret_key' ),
				'response' => $_POST['recaptcha']
			), 'https://www.google.com/recaptcha/api/siteverify' )
		);

		if ( is_wp_error( $request ) ) {
			wp_send_json_error( __( 'Recaptcha server request error.', 'actify' ) );
		}

		$body            = wp_remote_retrieve_body( $request );
		$captcha_success = json_decode( $body );

		if ( $captcha_success->success == false ) {
			wp_send_json_error( __( 'Invalid recaptcha token sent.', 'true' ) );
		}

		$sliced    = wp_array_slice_assoc( $_POST, array( 'highlight', 'postId' ) );
		$highlight = sanitize_textarea_field( $sliced['highlight'] );

		$highlights = $this->get_highlights_from_post( $sliced['postId'] );
		$messages   = $this->get_l18n_strings();

		foreach ( $highlights as $saved_highlight ) {
			$percentage = 0.0;
			similar_text( $highlight, $saved_highlight['highlight'], $percentage );
			if ( $percentage > 50.0 ) {

				$counts = get_post_meta( $saved_highlight['id'], 'highlight_counts', true );
				$counts ++;
				update_post_meta( $saved_highlight['id'], 'highlight_counts', $counts );

				wp_send_json_success( $messages['highlight']['complete'] );
			}
		}


		$inserted_id = wp_insert_post( array(
			'post_title'   => wp_trim_words( $highlight, 4 ),
			'post_content' => $highlight,
			'post_status'  => 'publish',
			'post_type'    => 'actify_highlights'
		) );

		update_post_meta( $inserted_id, 'highlight_from', $sliced['postId'] );
		update_post_meta( $inserted_id, 'highlight_counts', 1 );

		wp_send_json_success( $messages['highlight']['complete'] );

	}

	/**
	 * Ajax callback that save an reported mistake.
	 */
	public function report_mistake() {

		if ( ! check_ajax_referer( 'actify-mistake-nonce', 'nonce' ) ) {
			wp_send_json_error( __( 'Invalid security token sent.', 'actify' ) );
		};

		$request = wp_safe_remote_get( add_query_arg( array(
				'secret'   => $this->get_option_value( 'recaptcha_secret_key' ),
				'response' => $_POST['recaptcha']
			), 'https://www.google.com/recaptcha/api/siteverify' )
		);

		if ( is_wp_error( $request ) ) {
			wp_send_json_error( __( 'Recaptcha server request error.', 'actify' ) );
		}

		$body            = wp_remote_retrieve_body( $request );
		$captcha_success = json_decode( $body );

		if ( $captcha_success->success == false ) {
			wp_send_json_error( __( 'Invalid recaptcha token sent.', 'actify' ) );
		}

		$sliced  = wp_array_slice_assoc( $_POST, array( 'mistake', 'note' ) );
		$mistake = sanitize_text_field( $sliced['mistake'] );
		$note    = sanitize_textarea_field( $sliced['note'] );

		wp_insert_post( array(
			'post_title'   => $mistake,
			'post_content' => $note,
			'post_status'  => 'publish',
			'post_type'    => 'actify_typos'
		) );

		$messages = $this->get_l18n_strings();
		wp_send_json_success( $messages['typo']['complete'] );

	}

	/**
	 * Ajax callback that save the reported case.
	 */
	public function report_case() {

		if ( ! check_ajax_referer( 'actify-report-nonce', 'nonce' ) ) {
			wp_send_json_error( __( 'Invalid security token sent.', 'actify' ) );
		};

		$request = wp_safe_remote_get( add_query_arg( array(
				'secret'   => $this->get_option_value( 'recaptcha_secret_key' ),
				'response' => $_POST['recaptcha']
			), 'https://www.google.com/recaptcha/api/siteverify' )
		);

		if ( is_wp_error( $request ) ) {
			wp_send_json_error( __( 'Recaptcha server request error.', 'actify' ) );
		}

		$body            = wp_remote_retrieve_body( $request );
		$captcha_success = json_decode( $body );

		if ( $captcha_success->success == false ) {
			wp_send_json_error( __( 'Invalid recaptcha token sent.', 'actify' ) );
		}

		$sliced = wp_array_slice_assoc( $_POST, array( 'phone', 'desc' ) );
		$phone  = sanitize_text_field( $sliced['phone'] );
		$desc   = sanitize_textarea_field( $sliced['desc'] );

		wp_insert_post( array(
			'post_title'   => $phone,
			'post_content' => $desc,
			'post_status'  => 'publish',
			'post_type'    => 'actify_reports'
		) );

		$messages = $this->get_l18n_strings();
		wp_send_json_success( $messages['report']['complete'] );

	}

	/**
	 * the_content callback filter that wrap content in div that will trigger app from js.
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function wrap_content_in_component( $content ) {

		$post_types = $this->get_post_types();

		if (
			is_singular() &&
			in_array( get_post_type(), $post_types ) &&
			in_the_loop()
		) {
			return sprintf(
				'<div data-post-id="%2$d" class="actify-content-wrapper">%1$s</div>',
				$content,
				get_the_ID()
			);
		}

		return $content;

	}

	/**
	 * Get post types.
	 *
	 * @return array
	 */
	public function get_post_types() {

		$post_types = get_post_types( array(
			'public' => true,
		) );

		unset( $post_types['attachment'] );

		if ( wp_validate_boolean( get_option( 'show_tooltip_in_pages' ) ) === false ) {
			unset( $post_types['page'] );
		}

		if ( wp_validate_boolean( get_option( 'show_tooltip_in_posts' ) ) === false ) {
			unset( $post_types['post'] );
		}

		return $post_types;

	}

	/**
	 * Save options from settings page.
	 */
	function save_options() {

		$keys         = $this->get_options_keys();
		$trimmed_keys = array_keys( $keys );
		$sliced       = wp_array_slice_assoc( $_POST, $trimmed_keys );

		foreach ( $trimmed_keys as $key ) {
			update_option( $key, $sliced[ $key ] );
		}

		wp_send_json_success( array( 'message' => __( 'Options was saved', 'actify' ) ) );

	}

	/**
	 * Add settings page in menu.
	 */
	function add_options_page() {

		$options_page = add_options_page(
			__( 'Actify Settings', 'actify' ),
			__( 'Actify Settings', 'actify' ),
			'manage_options',
			'actify-settings-page',
			array( $this, 'render_options_page' )
		);

		add_action( 'load-' . $options_page, array( $this, 'load_settings_page_scripts' ) );

	}

	/**
	 * Load settings_page_scripts
	 */
	public function load_settings_page_scripts() {

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

	}

	/**
	 * Get default option values for settings page.
	 *
	 * @return array
	 */
	public function get_default_options_values() {

		return array(
			'facebook_app_id'            => '',
			'show_facebook_button'       => false,
			'recaptcha_site_key'         => '',
			'recaptcha_secret_key'       => '',
			'show_twitter_button'        => true,
			'show_highlight_button'      => true,
			'highlight_min_number'       => 5,
			'show_report_mistake_button' => true,
			'show_report_story_button'   => true,
			'show_tooltip_in_pages'      => false,
			'show_tooltip_in_posts'      => true
		);

	}

	/**
	 * Get options keys.
	 *
	 * @return array
	 */
	public function get_options_keys() {

		return array(
			'facebook_app_id'            => array( 'type' => 'text', 'visibility' => 'frontend' ),
			'show_facebook_button'       => array( 'type' => 'checkbox', 'visibility' => 'frontend' ),
			'recaptcha_site_key'         => array( 'type' => 'text', 'visibility' => 'frontend' ),
			'recaptcha_secret_key'       => array( 'type' => 'text', 'visibility' => 'backend' ),
			'show_twitter_button'        => array( 'type' => 'checkbox', 'visibility' => 'frontend' ),
			'show_highlight_button'      => array( 'type' => 'checkbox', 'visibility' => 'frontend' ),
			'highlight_min_number'       => array( 'type' => 'text', 'visibility' => 'frontend' ),
			'show_report_mistake_button' => array( 'type' => 'checkbox', 'visibility' => 'frontend' ),
			'show_report_story_button'   => array( 'type' => 'checkbox', 'visibility' => 'frontend' ),
			'show_tooltip_in_pages'      => array( 'type' => 'checkbox', 'visibility' => 'frontend' ),
			'show_tooltip_in_posts'      => array( 'type' => 'checkbox', 'visibility' => 'frontend' )
		);

	}

	/**
	 * Get options values from db.
	 *
	 * @param string $for
	 *
	 * @return array
	 */
	public function get_options_values( $for = 'backend' ) {

		$result   = array();
		$defaults = $this->get_default_options_values();

		foreach ( $this->get_options_keys() as $key => $data ) {

			//skip keys that is preserved to be in frontend
			if ( 'frontend' == $for && 'backend' === $data['visibility'] ) {
				continue;
			}

			$result[ $key ] = ( 'checkbox' === $data['type'] ) ?
				wp_validate_boolean( get_option( $key, $defaults[ $key ] ) ) :
				get_option( $key, $defaults[ $key ] );

		}

		return $result;

	}

	/**
	 * Get a single option value for specific key.
	 *
	 * @param $key
	 *
	 * @return bool
	 */
	public function get_option_value( $key ) {

		static $option_values = null;

		if ( null === $option_values ) {
			$option_values = $this->get_options_values();
		}

		return array_key_exists( $key, $option_values ) ? $option_values[ $key ] : false;

	}

	/**
	 * Render settings page.
	 */
	public function render_options_page() {
		?>
		<div class="gamify-page-wrapper" id="options-app">
			<div class="setting-item">
				<h2>{{l18n.header}}</h2>

			</div>
			<div class="setting-item">
				<label class="item-element">{{l18n.facebook_button_label}}</label>
				<div class="item-element">
					<input
						type="checkbox"
						v-model="options.show_facebook_button"/>
				</div>
				<div class="item-element">{{l18n.facebook_button_desc}}</div>
			</div>
			<div class="setting-item" v-if="options.show_facebook_button">
				<label class="item-element">{{l18n.facebook_app_id_label}}</label>
				<div class="item-element">
					<input
						type="text"
						v-model="options.facebook_app_id"/>
				</div>
				<div class="item-element"><span v-html="l18n.facebook_app_id_desc"></span></div>
			</div>
			<div class="setting-item">
				<label class="item-element">{{l18n.recaptcha_site_key_label}}</label>
				<div class="item-element">
					<input
						type="text"
						v-model="options.recaptcha_site_key"/>
				</div>
				<div class="item-element"><span v-html="l18n.recaptcha_site_key_desc"></span></div>
			</div>
			<div class="setting-item">
				<label class="item-element">{{l18n.recaptcha_secret_key_label}}</label>
				<div class="item-element">
					<input
						type="text"
						v-model="options.recaptcha_secret_key"/>
				</div>
				<div class="item-element"><span v-html="l18n.recaptcha_secret_key_desc"></span></div>
			</div>
			<div class="setting-item">
				<label class="item-element">{{l18n.twitter_button_label}}</label>
				<div class="item-element">
					<input
						type="checkbox"
						v-model="options.show_twitter_button"/>
				</div>
				<div class="item-element">{{l18n.twitter_button_desc}}</div>
			</div>
			<div class="setting-item">
				<label class="item-element">{{l18n.highlight_button_label}}</label>
				<div class="item-element">
					<input
						type="checkbox"
						v-model="options.show_highlight_button"/>
				</div>
				<div class="item-element">{{l18n.highlight_button_desc}}</div>
			</div>
			<div class="setting-item" v-if="options.show_highlight_button">
				<label class="item-element">{{l18n.highlight_min_number_label}}</label>
				<div class="item-element">
					<input
						type="text"
						v-model="options.highlight_min_number"/>
				</div>
				<div class="item-element">{{l18n.highlight_min_number_desc}}</div>
			</div>
			<div class="setting-item">
				<label class="item-element">{{l18n.typo_button_label}}</label>
				<div class="item-element">
					<input
						type="checkbox"
						v-model="options.show_report_mistake_button"/>
				</div>
				<div class="item-element">{{l18n.typo_button_desc}}</div>
			</div>
			<div class="setting-item">
				<label class="item-element">{{l18n.report_button_label}}</label>
				<div class="item-element">
					<input
						type="checkbox"
						v-model="options.show_report_story_button"/>
				</div>
				<div class="item-element">{{l18n.report_button_desc}}</div>
			</div>
			<div class="setting-item">
				<label class="item-element">{{l18n.show_tooltip_in_pages_button_label}}</label>
				<div class="item-element">
					<input
						type="checkbox"
						v-model="options.show_tooltip_in_pages"/>
				</div>
				<div class="item-element">{{l18n.show_tooltip_in_pages_button_desc}}</div>
			</div>
			<div class="setting-item">
				<label class="item-element">{{l18n.show_tooltip_in_posts_button_label}}</label>
				<div class="item-element">
					<input
						type="checkbox"
						v-model="options.show_tooltip_in_posts"/>
				</div>
				<div class="item-element">{{l18n.show_tooltip_in_posts_button_desc}}</div>
			</div>
			<div class="setting-item">
				<button
					@click="saveOptions"
					class="button button-primary button-large">
					{{l18n.save_settings_btn_label}}
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Append modal templates for highlight, report and typo modals.
	 */
	public function append_modal_template() { ?>
		<script type="x/template" id="modal-highlight">
			<transition name="modal" @after-leave="afterLeave">
				<div class="modal-mask" v-show="show">
					<slot name="modal-content">
						<div class="modal-container">
							<div v-show="!showMessage" class="modal-form highlight">
								<div class="modal-body">
									<blockquote>
										<h3>{{highlight.text}}</h3>
									</blockquote>
								</div>
								<div class="modal-footer text-right">
									<div class="recaptcha" ref="typoRecaptcha"></div>
									<div class="controls">
										<button class="button button-secondary" @click="closeModal()">
											{{ msg.close_modal }}
										</button>
										<button class="button button-primary" @click="savePost()">
											{{ msg.send_report }}
										</button>
									</div>
								</div>
							</div>
							<div v-show="showMessage" class="modal-message">
								<i class="actify-modal-icon" :class="addFontIcon()"></i>
								<div class="actify-text-message">{{ userText }}</div>
							</div>

						</div>
					</slot>
				</div>
			</transition>
		</script>
		<script type="x/template" id="modal-report">
			<transition name="modal" @after-leave="afterLeave">
				<div class="modal-mask" v-show="show">
					<slot name="modal-content">
						<div class="modal-container">
							<div v-show="!showMessage" class="modal-form">
								<div class="modal-header">
									<h3>{{msg.modal_header}}</h3>
								</div>
								<div class="modal-body">
									<label class="form-label">
										{{msg.modal_description}}
										<textarea v-model="desc" rows="8" class="form-control"></textarea>
									</label>
									<label class="form-label">
										{{msg.modal_phone}}
										<input v-model="phone" class="form-control">
									</label>
								</div>
								<div class="modal-footer text-right">
									<div class="recaptcha" ref="typoRecaptcha"></div>
									<div class="controls">
										<button class="button button-secondary" @click="closeModal()">
											{{ msg.close_modal }}
										</button>
										<button class="button button-primary" @click="savePost()">
											{{ msg.send_report }}
										</button>
									</div>
								</div>
							</div>
							<div v-show="showMessage" class="modal-message">
								<i class="actify-modal-icon" :class="addFontIcon()"></i>
								<div class="actify-text-message">{{ userText }}</div>
							</div>

						</div>
					</slot>
				</div>
			</transition>
		</script>
		<script type="x/template" id="modal-typo">
			<transition name="modal" @after-leave="afterLeave">
				<div class="modal-mask" v-show="show">
					<slot name="modal-content">
						<div class="modal-container">
							<div v-show="!showMessage" class="modal-form">
								<div class="modal-header">
									<h3>{{msg.modal_header}}</h3>
								</div>
								<div class="modal-body">
									<label class="form-label">
										{{msg.modal_mistake}}
										<input
											type="text"
											v-model="typoShared.text"
											class="form-control">
									</label>
									<label class="form-label">
										{{msg.modal_mistake_desc}}
										<textarea v-model="note" rows="5" class="form-control"></textarea>
									</label>
								</div>
								<div class="modal-footer">
									<div class="recaptcha" ref="typoRecaptcha"></div>
									<div class="controls">
										<button class="button button-secondary" @click="closeModal()">
											{{ msg.close_modal }}
										</button>
										<button class="button button-primary" @click="savePost()">
											{{ msg.send_mistake }}
										</button>
									</div>
								</div>
							</div>
							<div v-show="showMessage" class="modal-message">
								<i class="actify-modal-icon" :class="addFontIcon()"></i>
								<div class="actify-text-message">{{ userText }}</div>
							</div>
						</div>
					</slot>
				</div>
			</transition>
		</script>
		<?php
	}

	public function append_tooltip() {
		?>
		<div id="vue-app">
			<div v-if="options.show_report_story_button" class="megafon-wrapper">
				<i :title="msg.report.modal_header" class="fa fa-bullhorn" @click="openMegaModal"
				   aria-hidden="true"></i>
			</div>
			<modal-report
				:recaptcha-site-key="options.recaptcha_site_key"
				:msg-report="msg.report"
				:show="showMegaModal"
				@close="showMegaModal = false">
			</modal-report>
			<modal-highlight
				:recaptcha-site-key="options.recaptcha_site_key"
				:current-post-id="postId"
				:msg-highlight="msg.highlight"
				:highlight-shared="shared"
				:show="showHighlightModal"
				@close="showHighlightModal = false">
			</modal-highlight>
			<modal-typo
				:recaptcha-site-key="options.recaptcha_site_key"
				:msg-typo="msg.typo"
				:show="showTypoModal"
				:shared="shared"
				@close="showTypoModal = false">
			</modal-typo>

		</div>

		<div id="vue-tooltip" v-if="showTooltip" :style="tooltipStyle()">
			<i v-if="showFacebookButton" class="fa fa-facebook" :title="msg.facebook_title" @click="facebookShare"
			   aria-hidden="true"></i>
			<i v-if="options.show_twitter_button" class="fa fa-twitter" :title="msg.twitter_title" @click="twitterShare"
			   aria-hidden="true"></i>
			<i v-if="options.show_highlight_button" class="fa fa-paint-brush" :title="msg.highlight_title"
			   @click="highlight" aria-hidden="true"></i>
			<i v-if="options.show_report_mistake_button" class="fa fa-exclamation" :title="msg.report_mistake_title"
			   @click="openTypoModal()" aria-hidden="true"></i>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_admin_scripts() {

		wp_enqueue_style(
			'actify-admin-style',
			$this->get_css_uri( 'admin.style.css' ),
			array(),
			$this->get_plugin_version()
		);

		wp_enqueue_script(
			'actify-vue',
			$this->get_js_uri( 'vue.js' ),
			array(),
			$this->get_plugin_version(),
			true
		);

		wp_enqueue_script(
			'actify-admin',
			$this->get_js_uri( 'admin.js' ),
			array( 'wp-util' ),
			$this->get_plugin_version(),
			true
		);


		$l18n = $this->get_l18n_strings();
		wp_localize_script(
			'actify-admin',
			'admin_vue_options',
			array(
				'options'      => $this->get_options_values(),
				'ajaxCallback' => 'actify_save_options',
				'l18n'         => $l18n['settings_page']
			)
		);

	}

	/**
	 * Enqueue front scripts.
	 */
	public function enqueue_front_scripts() {

		$post_types = $this->get_post_types();

		if (
			is_singular() &&
			in_array( get_post_type(), $post_types )
		) {
			wp_enqueue_style(
				'actify-font-awesome',
				$this->get_css_uri( 'font-awesome.min.css' ),
				array(),
				$this->get_plugin_version()
			);

			wp_enqueue_style(
				'actify-style',
				$this->get_css_uri( 'style.css' ),
				array(),
				$this->get_plugin_version()
			);

			wp_enqueue_script(
				'actify-vue',
				$this->get_js_uri( 'vue.min.js' ),
				array(),
				$this->get_plugin_version(),
				true
			);

			wp_enqueue_script(
				'actify-mark',
				$this->get_js_uri( 'jquery.mark.min.js' ),
				array( 'jquery' ),
				$this->get_plugin_version(),
				true
			);

			wp_enqueue_script(
				'actify-recaptcha',
				'https://www.google.com/recaptcha/api.js?onload=vueRecaptchaInit&render=explicit',
				array(),
				$this->get_plugin_version(),
				true
			);

			wp_enqueue_script(
				'actify-vue-app',
				$this->get_js_uri( 'app.js' ),
				array( 'jquery', 'wp-util' ),
				$this->get_plugin_version(),
				true
			);

			$fb_js = "window.fbAsyncInit=function(){FB.init({appId:window.actify.options.facebook_app_id,autoLogAppEvents:!0,xfbml:!0,version:\"v2.11\"})},function(e,n,t){var o,i=e.getElementsByTagName(n)[0];e.getElementById(t)||((o=e.createElement(n)).id=t,o.src=\"https://connect.facebook.net/en_US/sdk.js\",i.parentNode.insertBefore(o,i))}(document,\"script\",\"facebook-jssdk\");";
			wp_add_inline_script( 'actify-vue-app', $fb_js );

			wp_localize_script( 'actify-vue-app',
				'actify',
				array(
					'mistake_nonce'        => wp_create_nonce( 'actify-mistake-nonce' ),
					'report_nonce'         => wp_create_nonce( 'actify-report-nonce' ),
					'highlight_nonce'      => wp_create_nonce( 'actify-highlight-nonce' ),
					'get_highlights_nonce' => wp_create_nonce( 'actify-get-highlights-nonce' ),
					'options'              => $this->get_options_values( 'frontend' ),
					'msg'                  => $this->get_l18n_strings()
				)
			);

			add_action( 'wp_footer', array( $this, 'append_tooltip' ) );
			add_action( 'wp_footer', array( $this, 'append_modal_template' ) );
		}

	}

	/**
	 * Get l18n strings.
	 *
	 * @return array
	 */
	public function get_l18n_strings() {

		return array(
			'settings_page' => array(
				'header'                             => __( 'Actify Settings', 'actify' ),
				'facebook_button_label'              => __( 'Show Facebook button', 'actify' ),
				'facebook_button_desc'               => __( 'Show Facebook button in the tooltip.', 'actify' ),
				'facebook_app_id_label'              => __( 'Facebook App Id', 'actify' ),
				'facebook_app_id_desc'               => sprintf(
					__(
						'A unique ID given to your app to use whenever, <a href="%s">read more</a>.',
						'actify'
					),
					'https://developers.facebook.com/docs/apps/register#app-settings'
				),
				'recaptcha_site_key_label'           => __( 'Recaptcha Site Key', 'actify' ),
				'recaptcha_site_key_desc'            => sprintf(
					wp_kses(
						__(
							'The site key is used to invoke reCAPTCHA service on your site or mobile application, <a href="%s">read more</a>.',
							'actify'
						),
						wp_kses_allowed_html( 'post' )
					),
					esc_url( 'https://developers.google.com/recaptcha/intro#overview' )
				),
				'recaptcha_secret_key_label'         => __( 'Recaptcha Secret Key', 'actify' ),
				'recaptcha_secret_key_desc'          =>
					sprintf(
						wp_kses(
							__(
								'The secret key authorizes communication between your application backend and the reCAPTCHA server to verify the user\'s response, <a href="%s">read more</a>.',
								'actify'
							),
							wp_kses_allowed_html( 'post' )
						),
						'https://developers.google.com/recaptcha/intro#overview'
					),
				'twitter_button_label'               => __( 'Show Twitter button', 'actify' ),
				'twitter_button_desc'                => __( 'Show Twitter button in the tooltip.', 'actify' ),
				'highlight_button_label'             => __( 'Show highlight button', 'actify' ),
				'highlight_button_desc'              => __( 'Show highlight button in the tooltip.', 'actify' ),
				'highlight_min_number_label'         => __( 'Minimum number of highlights', 'actify' ),
				'highlight_min_number_desc'          => __( 'Minimum number of highlights to be shown in the page/post.', 'actify' ),
				'typo_button_label'                  => __( 'Show report mistake button', 'actify' ),
				'typo_button_desc'                   => __( 'Show report mistake button in the tooltip.', 'actify' ),
				'report_button_label'                => __( 'Show report similar cases button', 'actify' ),
				'report_button_desc'                 => __( 'Show report similar cases button in the tooltip.', 'actify' ),
				'show_tooltip_in_pages_button_label' => __( 'Enable Actify functionality in pages.', 'actify' ),
				'show_tooltip_in_pages_button_desc'  => __( 'Show tooltip in single page template.', 'actify' ),
				'show_tooltip_in_posts_button_label' => __( 'Enable Actify functionality in posts.', 'actify' ),
				'show_tooltip_in_posts_button_desc'  => __( 'Show tooltip in single post template.', 'actify' ),
				'save_settings_btn_label'            => __( 'Save Settings', 'actify' ),
			),
			'highlight'     => array(
				'complete'          => __( 'Your highlight was sent.', 'actify' ),
				'invalid_nonce'     => __( 'Invalid Security token.', 'actify' ),
				'invalid_recaptcha' => __( 'Invalid recaptcha validation.', 'actify' ),
				'invalid_request'   => __( 'Invalid recaptcha request.', 'actify' ),
				'send_report'       => __( 'Send', 'actify' ),
				'close_modal'       => __( 'Close', 'actify' ),
				'modal_header'      => __( 'Highlight as favourite quote.', 'actify' ),
			),
			'tooltip'       => array(
				'facebook_title'       => __( 'Share on facebook', 'actify' ),
				'twitter_title'        => __( 'Share on Twitter', 'actify' ),
				'report_mistake_title' => __( 'Send a typo.', 'actify' ),
				'highlight_title'      => __( 'Highlight as favourite quote.', 'actify' ),
			),
			'typo'          => array(
				'complete'           => __( 'Your typo was sent.', 'actify' ),
				'invalid_nonce'      => __( 'Invalid Security token.', 'actify' ),
				'invalid_recaptcha'  => __( 'Invalid recaptcha validation.', 'actify' ),
				'invalid_request'    => __( 'Invalid recaptcha request.', 'actify' ),
				'send_mistake'       => __( 'Send Mistake', 'actify' ),
				'close_modal'        => __( 'Close', 'actify' ),
				'modal_header'       => __( 'Report a mistake', 'actify' ),
				'modal_mistake'      => __( 'Mistake', 'actify' ),
				'modal_mistake_desc' => __( 'Corrected mistake', 'actify' ),
			),
			'report'        => array(
				'complete'          => __( 'Your report was sent.', 'actify' ),
				'invalid_nonce'     => __( 'Invalid Security token.', 'actify' ),
				'invalid_recaptcha' => __( 'Invalid recaptcha validation.', 'actify' ),
				'invalid_request'   => __( 'Invalid recaptcha request.', 'actify' ),
				'send_report'       => __( 'Send', 'actify' ),
				'close_modal'       => __( 'Close', 'actify' ),
				'modal_header'      => __( 'Report similar cases', 'actify' ),
				'modal_phone'       => __( 'Phone number', 'actify' ),
				'modal_report_desc' => __( 'Describe your case.', 'actify' )
			)
		);

	}

	/**
	 * Get plugin version.
	 *
	 * @return mixed
	 */
	public function get_plugin_version() {

		return $this->plugin_version;

	}

	/**
	 * Get css uri.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function get_css_uri( $name = '' ) {

		return $this->get_assets_uri( 'css' ) . $name;

	}

	/**
	 * Get assets uri.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function get_assets_uri( $name = '' ) {

		return trailingslashit( trailingslashit( $this->component_uri ) . 'assets/' . $name );

	}

	/**
	 * Get js uri.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function get_js_uri( $name = '' ) {

		return $this->get_assets_uri( 'js' ) . $name;

	}

	/**
	 * Register typo post type.
	 */
	public function register_typo_post_type() {

		$args = array(
			'public' => true,
			'label'  => 'Mistakes'
		);

		register_post_type( 'actify_typos', $args );

	}

	/**
	 * Register reports post type.
	 */
	public function register_reports_post_type() {

		$args = array(
			'public' => true,
			'label'  => 'Reports'
		);

		register_post_type( 'actify_reports', $args );

	}

	/**
	 * Register highlights post type.
	 */
	public function register_highlights_post_type() {

		$args = array(
			'public' => true,
			'label'  => 'Highlights'
		);

		register_post_type( 'actify_highlights', $args );

	}

	/**
	 * Add highlight metabox.
	 */
	function add_highlight_meta_box() {

		add_meta_box(
			'highlight-details',
			__( 'Highlight Details', 'actify' ),
			array( $this, 'render_highlight_metabox' )
		);

	}

	/**
	 * Callback that render highlight metabox.
	 *
	 * @param $post
	 */
	function render_highlight_metabox( $post ) {

		$highlighted_from_postid = get_post_meta( $post->ID, 'highlight_from', true );
		$highlight_counts        = get_post_meta( $post->ID, 'highlight_counts', true );
		$edit_link               = sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $highlighted_from_postid ),
			get_the_title( $highlighted_from_postid )
		);
		$highlight_length        = strlen( $post->post_content );
		$highlight_words         = str_word_count( $post->post_content );

		?>
		<p><?php printf( __( 'Highlighted from : %s', 'actify' ), $edit_link ); ?></p>
		<p><?php printf( __( 'Highlighted length : %s', 'actify' ), $highlight_length ); ?></p>
		<p><?php printf( __( 'Highlighted words : %s', 'actify' ), $highlight_words ); ?></p>
		<p><?php printf( __( 'Count highlights : %s', 'actify' ), $highlight_counts ); ?></p>
		<?php
	}

}