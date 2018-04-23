<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Actify_Highlights_Widget.
 */
class Actify_Highlights_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {

		parent::__construct(
			'actify_highlights_widget', // Base ID
			esc_html__( 'Actify Highlights Widget', 'actify' ), // Name
			array( 'description' => esc_html__( 'A widget that show the most highlighted quotes from posts', 'actify' ), ) // Args
		);

	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {

		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Highlights', 'actify' );

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$number    = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 5;
		$min_count = ( ! empty( $instance['min_count'] ) ) ? absint( $instance['min_count'] ) : 5;
		if ( ! $number ) {
			$number = 5;
		}

		$show_number_of_counts = isset( $instance['show_number_of_counts'] ) ? $instance['show_number_of_counts'] : false;


		/**
		 * Filters the arguments for the Highlighted Posts widget.
		 *
		 * @since 3.4.0
		 *
		 * @see WP_Query::get_posts()
		 *
		 * @param array $args An array of arguments used to retrieve the highlight posts.
		 */
		$r = new WP_Query( apply_filters( 'widget_actify_highlights_args', array(
			'posts_per_page'      => $number,
			'no_found_rows'       => true,
			'post_status'         => 'publish',
			'post_type'           => 'actify_highlights',
			'ignore_sticky_posts' => true,
			'meta_key'            => 'highlight_from',
			'meta_query'          => array(
				array(
					'key'     => 'highlight_counts',
					'value'   => $min_count,
					'compare' => '>=',
				)
			)
		) ) );

		if ( $r->have_posts() ) :
			?>
			<?php echo $args['before_widget']; ?>
			<?php if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		} ?>
			<ul>
				<?php while ( $r->have_posts() ) : $r->the_post(); ?>
					<li>
						<a href="<?php the_permalink( get_post_meta( get_the_ID(), 'highlight_from', true ) ); ?>"><?php the_content() ?></a>
						<?php if ( $show_number_of_counts ) : ?>
							<span><?php printf( __( '%s people highlighted this passage.', 'actify' ), get_post_meta( get_the_ID(), 'highlight_counts', true ) ); ?></span>
						<?php endif; ?>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php echo $args['after_widget']; ?>
			<?php
			// Reset the global $the_post as this query will have stomped on it
			wp_reset_postdata();

		endif;
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {

		$title                 = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'New title', 'actify' );
		$number                = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		$min_count             = isset( $instance['min_count'] ) ? absint( $instance['min_count'] ) : 5;
		$show_number_of_counts = isset( $instance['show_number_of_counts'] ) ? (bool) $instance['show_number_of_counts'] : false;
		?>
		<p>
			<label
				for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'actify' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
			       name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
			       value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p><label
				for="<?php echo $this->get_field_id( 'min_count' ); ?>"><?php _e( 'Minimum counts to show highlight:', 'actify' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'min_count' ); ?>"
			       name="<?php echo $this->get_field_name( 'min_count' ); ?>" type="number" step="1" min="1"
			       value="<?php echo $min_count; ?>" size="3"/>
		</p>
		<p><label
				for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of highlights to show:', 'actify' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'number' ); ?>"
			       name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1"
			       value="<?php echo $number; ?>" size="3"/>
		</p>
		<p><input class="checkbox" type="checkbox"<?php checked( $show_number_of_counts ); ?>
		          id="<?php echo $this->get_field_id( 'show_number_of_counts' ); ?>"
		          name="<?php echo $this->get_field_name( 'show_number_of_counts' ); ?>"/>
			<label
				for="<?php echo $this->get_field_id( 'show_number_of_counts' ); ?>"><?php _e( 'Show number of counts ?', 'actify' ); ?></label>
		</p>

		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance                          = $old_instance;
		$instance['title']                 = sanitize_text_field( $new_instance['title'] );
		$instance['number']                = (int) $new_instance['number'];
		$instance['min_count']             = (int) $new_instance['min_count'];
		$instance['show_number_of_counts'] = isset( $new_instance['show_number_of_counts'] ) ? (bool) $new_instance['show_number_of_counts'] : false;

		return $instance;

	}

}