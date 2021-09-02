<?php
/**
 * Plugin Name: LearnDash + Googlebot
 * Description: Description
 * Plugin URI: http://#
 * Author: Author
 * Author URI: http://#
 * Version: 1.0.0
 * License: GPL2
 * Text Domain: learndash-google-bot
 * Domain Path: domain/path
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'LearnDash_Google_Bot' ) ) {

    /**
     * Main LearnDash_Google_Bot class
     *
     * @since       1.0.0
     */
    class LearnDash_Google_Bot {

        /**
         * @var         LearnDash_Google_Bot $instance The one true LearnDash_Google_Bot
         * @since       1.0.0
         */
        private static $instance;

        public static $session_id = '';


        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true LearnDash_Google_Bot
         */
        public static function instance() {

            self::$session_id = $_COOKIE['PHPSESSID'];

            if( !self::$instance ) {
                self::$instance = new LearnDash_Google_Bot();
                self::$instance->setup_constants();
                self::$instance->includes();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants() {
            // Plugin version
            define( 'LD_GOOGLE_BOT_VER', '1.0.0' );

            // Plugin path
            define( 'LD_GOOGLE_BOT_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'LD_GOOGLE_BOT_URL', plugin_dir_url( __FILE__ ) );
        }


        /**
         * Include necessary files
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function includes() {
        }


        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function hooks() {

            // 
            //add_action( 'init', array( $this, 'start_session' ), 1 );
            add_action( 'init', array( $this, 'google_visit_check' ), 5 );

            // enable course content access if google bot is crawling
            add_filter( 'sfwd_lms_has_access', array( $this, 'filter_ld_user_access' ), 99, 3 );
            add_filter( 'learndash_get_course_price', array( $this, 'filter_course_price' ), 99 );

            // user course progression hooks
            add_action( 'show_user_profile', array( $this, 'user_fields_callback' ) );
            add_action( 'edit_user_profile', array( $this, 'user_fields_callback' ) );
            add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
            add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
            add_filter( 'learndash_course_progression_enabled', array( $this, 'filter_course_progression' ), 99, 2 );

            add_action( 'wp_footer', array( $this, 'footer_hook_callback' ) );
        }

        public function google_visit_check() {

            delete_expired_transients();
            
            if( strstr( strtolower( $_SERVER['HTTP_REFERER'] ), "google" ) ) {

                $is_google_visit = get_transient( 'ld_is_google_visit_' . self::$session_id );

                if( !$is_google_visit ) {
                    set_transient( 'ld_is_google_visit_' . self::$session_id, 'yes', 1 );
                }
                
                // lets recall course access hooks
                add_filter( 'sfwd_lms_has_access', array( $this, 'filter_ld_user_access' ), 10, 3 );
                add_filter( 'learndash_get_course_price', array( $this, 'filter_course_price' ), 10 );

            }
        }

        /**
         * Filters whether a user has access to the course.
         *
         * @param boolean $has_access Whether the user has access to the course or not.
         * @param int     $post_id    Post ID.
         * @param int     $user_id    User ID.
         * 
         * @return boolearn $has_access Whether the user has access to the course or not.
         */
        public function filter_ld_user_access( $has_access, $post_id, $user_id ) {

            if( $this->is_google_bot() ) {
                $has_access = true;
            }

            
            $is_google_visit    = get_transient( 'ld_is_google_visit_' . self::$session_id );
            if( !empty( $is_google_visit ) ) {
                $has_access = true;
            }
            
            $user_id            = get_current_user_id();
            $course_progression = get_user_meta( $user_id, 'course_progression', true );

            if( $course_progression == 'yes' ) {
                $has_access     = false;
            } elseif( $course_progression == 'no' ) {
                $has_access     = true;
            }
            
            return $has_access;
        }


        /**
         * Filters whether a course has open price or not.
         *
         * @param      array  $course_price  The course price
         *
         * @return     array  $course_price  The course price
         */
        public function filter_course_price( $course_price ) {

            if( $this->is_google_bot() ) {
                $course_price['type'] = 'open';
            }

            $is_google_visit    = get_transient( 'ld_is_google_visit_' . self::$session_id );
            if( !empty( $is_google_visit ) ) {
                $course_price['type'] = 'open';
            }

            return $course_price;
        }


        /**
         * Checks if the request if from google search or not
         *
         * @return     bool  True if request from google , False otherwise.
         */
        public function is_google_bot() {

            if( strstr( strtolower( $_SERVER['HTTP_USER_AGENT'] ), "googlebot" ) ) {
                return true;
            }

            return false;
        }


        /**
         * Dispalyed user fields on profile editor
         *
         * @param      WP_User  $user   The user
         */
        public function user_fields_callback( WP_User $user ) {

			if( !current_user_can( 'manage_options' ) ) {
				return;
			}

			$user_courses = ld_course_list(array(
				'array'		=>	true,
				'user_id'	=>	$user->ID,
				'mycourses'	=> 	'enrolled'
			));
			
			if( empty($user_courses) ) {
				return;
			}

            $course_progression = get_user_meta( $user->ID, 'course_progression', true );
			
            ?>
            <h2><?php _e( 'Course Progression' ); ?></h2>
            <table class="form-table">
				<?php
				foreach ($user_courses as $key => $course) {

					$course_id 		= $course->ID;
					$progression 	= isset( $course_progression[$course_id] ) ? $course_progression[$course_id] : "";

					?>
						<tr>
							<th>
								<?php echo get_the_title($course);?>
							</th>
							<td>
								<label>
									<input type="radio" name="user_progression[<?php echo $course_id; ?>]" <?php checked( $progression, 'yes' ); ?> value="yes">
									<?php _e( 'Enable' ); ?>
								</label>
								&nbsp;
								<label>
									<input type="radio" name="user_progression[<?php echo $course_id; ?>]" <?php checked($progression, 'no' );?> value="no">
									<?php _e( 'Disable' ); ?>
								</label>
							</td>
						</tr>
					<?php
				}
				?>
            </table>
            <?php
        }


        /**
         * Saves user profile fields.
         *
         * @param      int  $user_id  The user ID
         */
        public function save_user_profile_fields( $user_id ) {

			if (!current_user_can('manage_options')) {
				return;
			}
			
            if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
                return;
            }
			
            update_user_meta( $user_id, 'course_progression', $_POST['user_progression'] );
        }



        /**
         * Enable or disable course progress based on user's profile value
         *
         * @param      bool     $progression  The course progression
         * @param      int      $course_id    The course identifier
         *
         * @return     bool     $progression  The course progression
         */
        public function filter_course_progression( $progression, $course_id ) {

            $user_id            		= get_current_user_id();
            $user_course_progression 	= get_user_meta( $user_id, 'course_progression', true );
			$course_progression 		= isset($user_course_progression[$course_id]) ? $user_course_progression[$course_id] : "";
			
            if( $course_progression == 'yes' ) {
                $progression    = true;
            } elseif( $course_progression == 'no' ) {
                $progression    = false;
            }
            
            return $progression;
        }



        /**
         * Enfore to delete expired transients at the end of the site
         */
        public function footer_hook_callback() {
            delete_expired_transients();
        }
    }
} // End if class_exists check

if (!function_exists("dd")) {
    function dd($data, $exit_data = true)
    {
        echo '<pre>' . print_r($data, true) . '</pre>';
        if ($exit_data == false) {
            echo '';
        } else {
            exit;
        }
    }
}

/**
 * The main function responsible for returning the one true LearnDash_Google_Bot
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \LearnDash_Google_Bot The one true LearnDash_Google_Bot
 *
 * @todo        Inclusion of the activation code below isn't mandatory, but
 *              can prevent any number of errors, including fatal errors, in
 *              situations where your extension is activated but EDD is not
 *              present.
 */
function LearnDash_Google_Bot_load() {
    return LearnDash_Google_Bot::instance();
}
add_action( 'plugins_loaded', 'LearnDash_Google_Bot_load' );