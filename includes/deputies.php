<?php


/*
 * Замещения
 */
$TheACL_substitutes = new acl_substitutes();
class acl_substitutes {
    
    function __construct() {
        add_action( 'show_user_profile', array($this, 'acl_user_profile_fields') );
        add_action( 'edit_user_profile', array($this, 'acl_user_profile_fields' ));

        add_action( 'personal_options_update', array($this, 'save_acl_user_profile_fields' ));
        add_action( 'edit_user_profile_update', array($this, 'save_acl_user_profile_fields' ));
    }



    
    function acl_user_profile_fields($user){
        if ( !current_user_can( 'edit_user', $user_id ) ) { return false; }
        $acl_substitutes = implode(",", get_user_meta($user->ID, 'acl_substitutes')); ?>
		<div id="acl_substitutes">
			<h3>Заместители</h3>
			</p>Эти пользователи получают доступ к записям текующего пользователя в рамках механизма ACL. Перечисление ИД пользователей через запятую.</p>
			<input id="acl_substitutes" name ="acl_substitutes" value="<?php echo $acl_substitutes ?>" size="100%" />
		</div>
	<?php }
    
    function save_acl_user_profile_fields( $user_id ) {

        if ( !current_user_can( 'edit_user', $user_id ) ) { return false; }

        $acl_substitutes = explode( ',', trim($_REQUEST['acl_substitutes']));
        
        $meta_key = 'acl_substitutes';
        delete_user_meta($user_id, $meta_key);
        
        //добавляем новые ИД если их нет в старом списке
        foreach ( $acl_substitutes as $sub_id ) {
			if(!empty($sub_id)){
                add_user_meta($user_id, $meta_key, $sub_id);
			}
        }
    }
    
}
