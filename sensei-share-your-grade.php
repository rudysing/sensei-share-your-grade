<?php
/**
 * Plugin Name: Sensei Share Your Grade
 * Plugin URI: http://woothemes.com/products/sensei-share-your-grade/
 * Description: Hi, I'm here to help you share your course results via Twitter, Facebook and more, once you've completed a course.
 * Author: WooThemes
 * Version: 1.0.0
 * Author URI: http://woothemes.com/
 *
 * Requires at least: 3.8.1
 * Tested up to: 3.8.1
 *
 * Text Domain: sensei-share-your-grade
 * Domain Path: /languages/
 *
 * @package Sensei_Share_Your_Grade
 * @category Extension
 * @author Matty Cohen
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

Sensei_Share_Your_Grade();

/**
 * Returns the main instance of Sensei_Share_Your_Grade to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Sensei_Share_Your_Grade
 */
function Sensei_Share_Your_Grade() {
	return Sensei_Share_Your_Grade::instance();
} // End Sensei_Share_Your_Grade()

/**
 * Main Sensei_Share_Your_Grade Class
 *
 * @class Sensei_Share_Your_Grade
 * @version	1.0.0
 * @since 1.0.0
 * @package	Sensei_Share_Your_Grade
 * @author Matty
 */
final class Sensei_Share_Your_Grade {
	/**
	 * Sensei_Share_Your_Grade The single instance of Sensei_Share_Your_Grade.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The token.
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $_token;

	/**
	 * The version number.
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $_version;

	/**
	 * A collection of the data we're working with for the current course results.
	 * @var     array
	 * @access  private
	 * @since   1.0.0
	 */
	private $_course_data;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct () {
		$this->_token = 'sensei-share-your-grade';
		$this->_version = '1.0.0';

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		// Set up the data we will need for our output.
		add_action( 'sensei_course_results_info', array( $this, 'setup_data_before_output' ), 20 );

		// Display a message when viewing course results.
		add_action( 'sensei_course_results_info', array( $this, 'output_sharing_message' ), 30 );

		// Display sharing buttons when viewing course results.
		add_action( 'sensei_course_results_info', array( $this, 'output_sharing_buttons' ), 40 );
	} // End __construct()

	/**
	 * Set up the necessary data, before we begin output.
	 * @access  public
	 * @since   1.0.0
	 * @return  string
	 */
	public function setup_data_before_output () {
		global $woothemes_sensei, $course, $current_user;

		if ( ! is_a( $course, 'WP_Post' ) || ! is_a( $current_user, 'WP_User' ) ) return;

		$course_id = intval( $course->ID );
		$user_id = intval( $current_user->ID );

		$started_course = sensei_has_user_started_course( $course_id, $user_id );

		$pass_mark = WooThemes_Sensei_Utils::sensei_course_pass_grade( $course_id );
		$user_grade = WooThemes_Sensei_Utils::sensei_course_user_grade( $course_id, $user_id );
		$has_passed = WooThemes_Sensei_Utils::sensei_user_passed_course( $course_id, $user_id );

		$args = array(
			'has_passed' => $has_passed,
			'pass_mark' => $pass_mark,
			'user_grade' => $user_grade,
			'course_id' => $course_id,
			'user_id' => $user_id
		);

		$this->set_current_course_data( $args );

		do_action( 'sensei_share_your_grade_setup_data_before_output' );
	} // End setup_data_before_output()

	/**
	 * Output some introductory text, as well as a message preview, for sharing.
	 * @access  public
	 * @since   1.0.0
	 * @return  string
	 */
	public function output_sharing_message () {
		$message = $this->get_message();
		if ( '' != $message ) {
			echo '<div class="sensei-share-your-grade message">' . "\n";
			echo apply_filters( 'sensei_share_your_grade_preview_heading', '<h2>' . __( 'Share your progress!', 'sensei-share-your-grade' ) . '</h2>' );
			echo sprintf( apply_filters( 'sensei_share_your_grade_preview_description', __( 'Go on, get social! Share your progress with your friends and family on social media. Here\'s a preview of the message they will see. %1$s', 'sensei-share-your-grade' ) ), '<div class="message-preview">' . "\n" . wpautop( make_clickable( $message ) ) . "\n" . '</div><!--/.message-preview-->' . "\n" );
			echo '</div><!--/.sensei-share-your-grade message-->' . "\n";
		}
		do_action( 'sensei_share_your_grade_output_sharing_message' );
	} // End output_sharing_message()

	/**
	 * Output the sharing buttons.
	 * @access  public
	 * @since   1.0.0
	 * @return  string
	 */
	public function output_sharing_buttons () {
		$message = $this->get_message();
		if ( '' != $message ) {
			echo '<div class="sensei-share-your-grade buttons">' . "\n";
			$networks = $this->_get_supported_networks();
			if ( 0 < count( $networks ) ) {
				foreach ( $networks as $k => $v ) {
					if ( '' != $v && 'method' != $v && function_exists( $v ) ) {
						$v();
					} else {
						if ( 'method' == $v && method_exists( $this, 'render_' . $k . '_button' ) ) {
							$this->{'render_' . $k . '_button'}( $message );
						}
					}
				}
			}
			echo '</div><!--/.sensei-share-your-grade buttons-->' . "\n";
		}
		do_action( 'sensei_share_your_grade_output_sharing_buttons' );
	} // End output_sharing_buttons()

	/**
	 * Return a formatted Twitter sharing button.
	 * @access  public
	 * @since   1.0.0
	 * @return  string
	 */
	public function render_twitter_button ( $message ) {
		// TODO
	} // End render_twitter_button()

	/**
	 * Return a formatted Facebook sharing button.
	 * @access  public
	 * @since   1.0.0
	 * @return  string
	 */
	public function render_facebook_button ( $message ) {
		// TODO
	} // End render_facebook_button()

	/**
	 * Return a formatted message to be shared.
	 * @access  public
	 * @since   1.0.0
	 * @return  string
	 */
	public function get_message () {
		$status = $this->_get_passed_or_failed();
		$message = $this->_format_message( $this->get_message_template( $status ) );
		return apply_filters( 'sensei_share_your_grade_message', $message );
	} // End get_message()

	/**
	 * Return the appropriate text message templated, based on "passed" status.
	 * @access  public
	 * @since   1.0.0
	 * @param 	string $status "passed" or "failed."
	 * @return  string
	 */
	public function get_message_template ( $status = 'failed' ) {
		if ( 'passed' == $status ) {
			$template = $this->get_message_template_passed();
		} else {
			$template = $this->get_message_template_failed();
		}
		return $template;
	} // End get_message_template()

	/**
	 * Return a text template for the message to be shared, if the student has passed.
	 * @access  public
	 * @since   1.0.0
	 * @return  string
	 */
	public function get_message_template_passed () {
		return apply_filters( 'sensei_share_your_grade_message_template_passed', __( 'I just %%STATUS%% %%COURSE_NAME%%, over at %%SITE_NAME%% with %%PERCENTAGE%%%! Take the course, today! %%COURSE_PERMALINK%%', 'sensei-share-your-grade' ) );
	} // End get_message_template_passed()

	/**
	 * Return a text template for the message to be shared, if the student has failed.
	 * @access  public
	 * @since   1.0.0
	 * @return  string
	 */
	public function get_message_template_failed () {
		return apply_filters( 'sensei_share_your_grade_message_template_failed', __( 'Cheer me on as I work to pass the %%COURSE_NAME%% course, over at %%SITE_NAME%%! Take the course with me, today! %%COURSE_PERMALINK%%', 'sensei-share-your-grade' ) );
	} // End get_message_template_failed()

	/**
	 * Return 'passed' or 'failed', depending on the user's status with the current course.
	 * @access  private
	 * @since   1.0.0
	 * @return  string
	 */
	private function _get_passed_or_failed () {
		$template = 'passed';
		if ( true !== $this->_course_data['has_passed'] ) {
			$template = 'failed';
		}
		return $template;
	} // End _get_passed_or_failed()

	/**
	 * Format the given message, replacing the various placeholders.
	 * @access  private
	 * @since   1.0.0
	 * @param 	string $unformatted_text The raw message template.
	 * @param 	object $course The course to format the message for.
	 * @param 	object $student The student object to format the message for.
	 * @return  string
	 */
	private function _format_message ( $unformatted_text ) {
		$message = $unformatted_text;
		$data = $this->_course_data;

		$message = str_replace( '%%SITE_NAME%%', get_bloginfo( 'name' ), $message );
		$message = str_replace( '%%COURSE_NAME%%', get_the_title( $data['course_id'] ), $message );
		$message = str_replace( '%%COURSE_PERMALINK%%', get_permalink( $data['course_id'] ), $message );
		$message = str_replace( '%%PERCENTAGE%%', intval( $data['user_grade'] ), $message );
		$message = str_replace( '%%STATUS%%', $data['status_text'], $message );

		return $message;
	} // End _format_message()

	/**
	 * Return a filtered array of supported networks. Users can specify a callback function for any custom sharing methods.
	 * @access  private
	 * @since   1.0.0
	 * @return  string
	 */
	private function _get_supported_networks () {
		return (array)apply_filters( 'sensei_share_your_grade_supported_networks', array( 'twitter' => 'method', 'facebook' => 'method' ) );
	} // End _get_supported_networks()

	/**
	 * Set the data we'll be using for the current course.
	 * @access  public
	 * @since   1.0.0
	 * @param 	array $args Arguments to store.
	 * @return  string
	 */
	public function set_current_course_data ( $args = array() ) {
		if ( 0 < count( $args ) ) {
			foreach ( $args as $k => $v ) {
				$this->_course_data[$k] = $v;
			}

			if ( isset( $this->_course_data['has_passed'] ) && true == $this->_course_data['has_passed'] ) {
				$this->_course_data['status_text'] = __( 'passed', 'sensei-share-your-grade' );
			} else {
				$this->_course_data['status_text'] = __( 'failed', 'sensei-share-your-grade' );
			}
		}
	} // End set_current_course_data()

	/**
	 * Main Sensei_Share_Your_Grade Instance
	 *
	 * Ensures only one instance of Sensei_Share_Your_Grade is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see Sensei_Share_Your_Grade()
	 * @return Main Sensei_Share_Your_Grade instance
	 */
	public static function instance () {
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), '1.0.0' );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), '1.0.0' );
	} // End __wakeup()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		// Log the version number.
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number()
} // End Class
?>