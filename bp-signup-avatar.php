<?php

/**
 * Plugin Name: BuddyPress Signup Avatar
 * Plugin URI: http://buddydev.com/plugins/bp-signup-avatar/
 * Version: 1.0.2
 * Author: Brajesh Singh
 * Author URI: http://buddydev.com/
 * License: GPL
 * Last Updated: March 25, 2015
 * test with: BuddyPress 2.2.1
 * Description: Allow your users to upload avatars when they signup for your site.
 */

class BP_Signup_Avatar_Helper {
    /**
	 *
	 * @var BP_Signup_Avatar_Helper 
	 */
	private static $instance;
    
	private function __construct() {

		add_action( 'bp_after_registration_confirmed', array( $this, 'show_form' ) );
		// add_action( 'bp_complete_signup',              array( $this, 'init_avatar_admin_step' ) );       
		add_action( 'bp_core_screen_signup',	array( $this, 'handle_avatar_upload_crop' ), 9 );

        add_action( 'bp_core_activated_user',   array( $this, 'move_signup_avatar' ),10, 3 );
        
		//load text domain
		add_action ( 'bp_loaded', array( $this, 'load_textdomain' ), 2 );
    }
    
	/**
	 * 
	 * @return BP_Signup_Avatar_Helper
	 */
    public static function get_instance() {
    
        if( ! isset( self::$instance ) )
                self::$instance = new self();
        
        return self::$instance;
    }
    
    /**
     * Load plugin textdomain for translation
     */
    public function load_textdomain() {
		
        $locale = apply_filters( 'bp-signup-avatar_get_locale', get_locale() );
        
        // if load .mo file
        if ( !empty( $locale ) ) {
            $mofile_default = sprintf( '%slanguages/%s.mo', plugin_dir_path( __FILE__ ), $locale );
            $mofile = apply_filters( 'bp-signup-avatar_load_textdomain_mofile', $mofile_default );

            if ( is_readable( $mofile ) ) 
                  // make sure file exists, and load it
                load_textdomain( 'bp-signup-avatar', $mofile );
        }
       
    }
     
    public function show_form() {
        ?>
		<div id="signup-avatar-wrapper">
			<?php  if ( 'upload-image' == bp_get_avatar_admin_step() ) : ?>
				<h4><?php _e( 'Your Current Avatar', 'bp-signup-avatar' ) ?></h4>

				<p><?php _e( "We've fetched an avatar for your new account. If you'd like to change this, why not upload a new one?", 'bp-signup-avatar' ) ?></p>

				<div id="signup-avatar">
					<?php bp_signup_avatar() ?>
				</div>

				<p>  
					<input type='hidden' name='signup-avatar-actions' value='1' />
					<input type="file" name="file" id="file" />
					<input type="submit" name="upload" id="upload" value="<?php _e( 'Upload Image', 'bp-signup-avatar' ) ?>" />
					<input type="hidden" name="action" id="action" value="bp_avatar_upload" />
					<input type="hidden" name="signup_email" id="signup_email" value="<?php bp_signup_email_value() ?>" />
					<input type="hidden" name="signup_username" id="signup_username" value="<?php bp_signup_username_value() ?>" />
				</p>

				<?php wp_nonce_field( 'bp_avatar_upload' ) ?>

			<?php endif; ?>

			<?php if ( 'crop-image' == bp_get_avatar_admin_step() ) : ?>

				<h3><?php _e( 'Crop Your New Avatar', 'bp-signup-avatar' ) ?></h3>
				<img src="<?php bp_avatar_to_crop() ?>" id="avatar-to-crop" class="avatar" alt="<?php _e( 'Avatar to crop', 'bp-signup-avatar' ) ?>" />
				<div id="avatar-crop-pane">
					<img src="<?php bp_avatar_to_crop() ?>" id="avatar-crop-preview" class="avatar" alt="<?php _e( 'Avatar preview', 'bp-signup-avatar' ) ?>" />
				</div>
				<input type="submit" name="avatar-crop-submit" id="avatar-crop-submit" value="<?php _e( 'Crop Image', 'bp-signup-avatar' ) ?>" />
				<input type="hidden" name="signup_email" id="signup_email" value="<?php bp_signup_email_value() ?>" />
				<input type="hidden" name="signup_username" id="signup_username" value="<?php bp_signup_username_value() ?>" />
				<input type="hidden" name="signup_avatar_dir" id="signup_avatar_dir" value="<?php bp_signup_avatar_dir_value() ?>" />
				<input type='hidden' name='signup-avatar-actions' value='1' />
				<input type="hidden" name="image_src" id="image_src" value="<?php bp_avatar_to_crop_src() ?>" />
				<input type="hidden" id="x" name="x" />
				<input type="hidden" id="y" name="y" />
				<input type="hidden" id="w" name="w" />
				<input type="hidden" id="h" name="h" />

				<?php wp_nonce_field( 'bp_avatar_cropstore' ) ?>

			<?php endif; ?>
		</div>
		<?php
    }
    
    public function init_avatar_admin_step() {
        global $bp;
        //if(!isset($bp->)) 
    }
    
    public function handle_avatar_upload_crop() {
       
		global $wpdb;
		$bp = buddypress();
        
        if( ! isset( $bp->signup->avatar_dir ) ) {
            
            if( empty( $bp->signup ) )
                $bp->signup = new stdClass ();
        }
            
        if ( ! bp_is_current_component( 'register' ) )
			return;

		// Not a directory
		bp_update_is_directory( false, 'register' );
        
        if( ! isset( $_POST['signup-avatar-actions'] ) )
            return ;
        
       if( ! isset( $bp->signup->step ) )
                $bp->signup = new stdClass ();
       
        if( ! isset( $bp->avatar_admin ) )
             $bp->avatar_admin = new stdClass ();
        
        	/* If user has uploaded a new avatar */
		if ( ! empty( $_FILES ) ) {

			/* Check the nonce */
			check_admin_referer( 'bp_avatar_upload' );

			$bp->signup->step = 'completed-confirmation';

			$user_login = $_POST['signup_username'];
			
			if( empty( $user_login ) )
				return ;
			
			$signups = BP_Signup::get( array('user_login'=> $user_login ) );
			
			$signups = $signups['signups'];
			
			if( empty( $signups ) )
				return ;
			
			$signup = array_pop( $signups );
			
			
			$key = $signup->activation_key;
			
			if( empty( $key ) )
				return ;
			
			$bp->signup->avatar_dir = wp_hash ( $key );
			
			/* Pass the file to the avatar upload handler */
			if ( bp_core_avatar_handle_upload( $_FILES, 'bp_core_signup_avatar_upload_dir' ) ) {
				$bp->avatar_admin->step = 'crop-image';

				/* Make sure we include the jQuery jCrop file for image cropping */
				self::add_jquery_cropper();
			}
		}

		/* If the image cropping is done, crop the image and save a full/thumb version */
		if ( isset( $_POST['avatar-crop-submit'] ) ) {
				if( !isset( $bp->signup->avatar_dir ) )
					$bp->signup->avatar_dir = $_POST['signup_avatar_dir'];

				/* Check the nonce */
				check_admin_referer( 'bp_avatar_cropstore' );

				/* Reset the avatar step so we can show the upload form again if needed */
				$bp->signup->step =        'completed-confirmation';
				$bp->avatar_admin->step =  'upload-image';
                
                $crop_args = array( 
                    'original_file' => $_POST['image_src'], 
                    'crop_x' => $_POST['x'], 
                    'crop_y' => $_POST['y'], 
                    'crop_w' => $_POST['w'], 
                    'crop_h' => $_POST['h'],
                    'object' => 'user',
                    'item_id' => 'signups/' . $_POST['signup_avatar_dir'], 
                    'avatar_dir' => 'avatars',
                );
                
                include_once __DIR__ . '/class-bp-attachment-signup-avatar.php';
                $obj = new \BP_Attachment_Signup_Avatar();
                
                $cropped_images = $obj->crop( $crop_args );

                if ( ! $cropped_images ){
                    bp_core_add_message( __( 'There was a problem cropping your avatar, please try uploading it again', 'bp-signup-avatar' ), 'error' );
                } else {
                    $this->update_signup_avatar_info();
                    bp_core_add_message( __( 'Your new avatar was uploaded successfully', 'bp-signup-avatar' ) );
                }
		}
    
		bp_core_load_template( 'registration/register' );
    
    }
    
    /**
     * Since, during signup, the avatar is not uploaded in avatar/user_id directory, 
     * save the uploaded avatar info in user meta, to be used later.
     * 
     * @global type \wpdb
     * @return type void
     */
    public function update_signup_avatar_info () {
        $user_login = $_POST[ 'signup_username' ];

        if ( empty( $user_login ) )
            return;

        $signups = \BP_Signup::get( array ( 'user_login' => $user_login ) );
        $signups = $signups[ 'signups' ];
        if ( empty( $signups ) )
            return;

        $signup = array_pop( $signups );
        $meta = $signup->meta;
        $meta[ 'avatar_folder' ] = $_POST[ 'signup_avatar_dir' ];

        global $wpdb;

        $wpdb->update(
            buddypress()->members->table_name_signups, array (
                'meta' => maybe_serialize( $meta ),
            ), 
            array (
                'signup_id' => $signup->signup_id,
            ), 
            array (
                '%s',
            ), 
            array (
                '%s',
            )
        );
    }

    /**
     * Move the uploaded avatar from signups/xxxxxxx to avatars/user_id folder.
     * 
     * @param int $user_id
     * @param string $key
     * @param array $user
     * @return void
     */
    public function move_signup_avatar ( $user_id, $key, $user ) {
        $signup_folder_name = isset( $user[ 'meta' ][ 'avatar_folder' ] ) ? $user[ 'meta' ][ 'avatar_folder' ] : '';
        if ( !$signup_folder_name ) {
            return false;
        }

        //move cropped images from ../uploads/avatars/signups/$signup_folder_name to ../uploads/avatars/$user_id 
        $upload_dir = bp_upload_dir();
        $path_to_avatars_folder = $upload_dir[ 'basedir' ] . '/avatars';

        $from = $path_to_avatars_folder . '/signups/' . $signup_folder_name;
        $to = $path_to_avatars_folder . '/' . $user_id;

        rename( $from, $to );
    }

    public function add_jquery_cropper() {
		
        wp_enqueue_style( 'jcrop' );
        wp_enqueue_script( 'jcrop', array( 'jquery' ) );
        add_action( 'wp_head', 'bp_core_add_cropper_inline_js' );
        add_action( 'wp_head', 'bp_core_add_cropper_inline_css' );
    }
}

BP_Signup_Avatar_Helper::get_instance();