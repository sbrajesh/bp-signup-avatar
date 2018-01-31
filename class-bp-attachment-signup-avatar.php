<?php
defined( 'ABSPATH' ) || exit;

if( class_exists( '\BP_Attachment' ) ){
    /**
     * This is a copy of class BP_Attachment_Avatar
     * with all the non-necessary bits stripped.
     * All the changes are there in the function 'crop'.
     * Other functions are copied over as is, and are just to setup the class object properly.
     */
    class BP_Attachment_Signup_Avatar extends \BP_Attachment  {
        public function __construct() {
            // Allowed avatar types.
            $allowed_types = bp_core_get_allowed_avatar_types();

            parent::__construct( array(
                'action'                => 'bp_avatar_upload',
                'file_input'            => 'file',
                'original_max_filesize' => bp_core_avatar_original_max_filesize(),

                // Specific errors for avatars.
                'upload_error_strings'  => array(
                    9  => sprintf( __( 'That photo is too big. Please upload one smaller than %s', 'buddypress' ), size_format( bp_core_avatar_original_max_filesize() ) ),
                    10 => sprintf( _n( 'Please upload only this file type: %s.', 'Please upload only these file types: %s.', count( $allowed_types ), 'buddypress' ), self::get_avatar_types( $allowed_types ) ),
                ),
            ) );
        }
        
        public static function get_avatar_types( $allowed_types = array() ) {
            $types = array_map( 'strtoupper', $allowed_types );
            $comma = _x( ',', 'avatar types separator', 'buddypress' );
            return join( $comma . ' ', $types );
        }
        
        public function set_upload_dir() {
            if ( bp_core_avatar_upload_path() && bp_core_avatar_url() ) {
                $this->upload_path = bp_core_avatar_upload_path();
                $this->url         = bp_core_avatar_url();
                $this->upload_dir  = bp_upload_dir();
            } else {
                parent::set_upload_dir();
            }
        }
        
        public function crop( $args = array() ) {
            // Bail if the original file is missing.
            if ( empty( $args['original_file'] ) ) {
                return false;
            }
            
            if ( ! bp_attachments_current_user_can( 'edit_avatar', $args ) ) {
                return false;
            }

            $avatar_dir = 'avatars';

            /**
             * Original file is a relative path to the image
             * eg: /avatars/1/avatar.jpg
             */
            $relative_path = sprintf( '/%s/%s/%s', $avatar_dir, $args['item_id'], basename( $args['original_file'] ) );
            $absolute_path = $this->upload_path . $relative_path;
            
            // Bail if the avatar is not available.
            if ( ! file_exists( $absolute_path ) )  {
                return false;
            }

            if ( empty( $args['item_id'] ) ) {

                /** This filter is documented in bp-core/bp-core-avatars.php */
                $avatar_folder_dir = apply_filters( 'bp_core_avatar_folder_dir', dirname( $absolute_path ), $args['item_id'], $args['object'], $args['avatar_dir'] );
            } else {

                /** This filter is documented in bp-core/bp-core-avatars.php */
                $avatar_folder_dir = apply_filters( 'bp_core_avatar_folder_dir', $this->upload_path . '/' . $args['avatar_dir'] . '/' . $args['item_id'], $args['item_id'], $args['object'], $args['avatar_dir'] );
            }

            // Bail if the avatar folder is missing for this item_id.
            if ( ! file_exists( $avatar_folder_dir ) ) {
                return false;
            }
            
            // Make sure we at least have minimal data for cropping.
            if ( empty( $args['crop_w'] ) ) {
                $args['crop_w'] = bp_core_avatar_full_width();
            }

            if ( empty( $args['crop_h'] ) ) {
                $args['crop_h'] = bp_core_avatar_full_height();
            }

            // Get the file extension.
            $data = @getimagesize( $absolute_path );
            $ext  = $data['mime'] == 'image/png' ? 'png' : 'jpg';

            $args['original_file'] = $absolute_path;
            $args['src_abs']       = false;
            $avatar_types = array( 'full' => '', 'thumb' => '' );

            foreach ( $avatar_types as $key_type => $type ) {
                if ( 'thumb' === $key_type ) {
                    $args['dst_w'] = bp_core_avatar_thumb_width();
                    $args['dst_h'] = bp_core_avatar_thumb_height();
                } else {
                    $args['dst_w'] = bp_core_avatar_full_width();
                    $args['dst_h'] = bp_core_avatar_full_height();
                }

                $filename         = wp_unique_filename( $avatar_folder_dir, uniqid() . "-bp{$key_type}.{$ext}" );
                $args['dst_file'] = $avatar_folder_dir . '/' . $filename;

                $avatar_types[ $key_type ] = parent::crop( $args );
            }
            // Remove the original.
            @unlink( $absolute_path );

            return $avatar_types;
        }

    }
}