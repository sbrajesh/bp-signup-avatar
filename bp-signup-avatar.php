<?php

/**
 * Plugin Name: BuddyPress Signup Avatar
 * Plugin URI: http://buddydev.com/plugins/bp-signup-avatar/
 * Version: 1.0.1
 * Author: Brajesh Singh
 * Author URI: http://buddydev.com/
 * License: GPL
 * 
 * Description: Allow your users to upload avatars when they signup for your site.
 */

class BP_Signup_Avatar_Helper{
    
    
      private static $instance;
    
      private function __construct(){
        
        add_action( 'bp_after_registration_confirmed', array( $this, 'show_form' ) );
       // add_action( 'bp_complete_signup',              array( $this, 'init_avatar_admin_step' ) );       
        add_action( 'bp_core_screen_signup',                      array( $this, 'handle_avatar_upload_crop' ), 9 );
        
        //load text domain
        add_action ( 'bp_loaded', array( $this, 'load_textdomain' ), 2 );
    }
    
    public static function get_instance(){
    
        if( ! isset( self::$instance ) )
                self::$instance = new self();
        
        return self::$instance;
    }
    
    /**
     * Load plugin textdomain for translation
     */
    public function load_textdomain(){
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
     
    public function show_form(){
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
    
    public function init_avatar_admin_step(){
        global $bp;
        //if(!isset($bp->)) 
    }
    
    public function handle_avatar_upload_crop(){
        global $bp, $wpdb;
        
        if( !isset( $bp->signup->avatar_dir ) ){
            
            if( empty( $bp->signup ) )
                $bp->signup = new stdClass ();
        }
            
        if ( !bp_is_current_component( 'register' ) )
		return;

	// Not a directory
	bp_update_is_directory( false, 'register' );
        
        if( !isset( $_POST['signup-avatar-actions'] ) )
            return ;
        
       if( ! isset( $bp->signup->step ) )
                $bp->signup = new stdClass ();
       
        if( !isset( $bp->avatar_admin ) )
             $bp->avatar_admin = new stdClass ();
        
        	/* If user has uploaded a new avatar */
	if ( !empty( $_FILES ) ) {

		/* Check the nonce */
		check_admin_referer( 'bp_avatar_upload' );

		$bp->signup->step = 'completed-confirmation';

		if ( is_multisite() ) {
			/* Get the activation key */
			if ( !$bp->signup->key = $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s AND user_email = %s", $_POST[ 'signup_username' ], $_POST[ 'signup_email' ] ) ) ) {
				bp_core_add_message( __( 'There was a problem uploading your avatar, please try uploading it again', 'bp-signup-avatar' ) );
			} else {
				/* Hash the key to create the upload folder (added security so people don't sniff the activation key) */
				$bp->signup->avatar_dir = wp_hash( $bp->signup->key );
			}
		} else {
			$user_id = bp_core_get_userid( $_POST['signup_username'] );
			$bp->signup->avatar_dir = wp_hash( $user_id );
		}

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

            if ( !bp_core_avatar_handle_crop( array( 'original_file' => $_POST['image_src'], 'crop_x' => $_POST['x'], 'crop_y' => $_POST['y'], 'crop_w' => $_POST['w'], 'crop_h' => $_POST['h'] ) ) )
                    bp_core_add_message( __( 'There was a problem cropping your avatar, please try uploading it again', 'bp-signup-avatar' ), 'error' );
            else
                    bp_core_add_message( __( 'Your new avatar was uploaded successfully', 'bp-signup-avatar' ) );
	}
    
	bp_core_load_template( 'registration/register' );
    
    }
    public function add_jquery_cropper() {
        wp_enqueue_style( 'jcrop' );
        wp_enqueue_script( 'jcrop', array( 'jquery' ) );
        add_action( 'wp_head', 'bp_core_add_cropper_inline_js' );
        add_action( 'wp_head', 'bp_core_add_cropper_inline_css' );
    }
}
BP_Signup_Avatar_Helper::get_instance();