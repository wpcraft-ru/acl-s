<?php
/*
Plugin Name: ACL
Plugin URI: http://www.casepress.org/
Description: Access control list for WP
Version: 20141111.2
Author: CasePress Studio
Author URI: http://casepress.org
GitHub Plugin URI: https://github.com/casepress-studio/acl-by-cps/
GitHub Branch: master
License: GPL
*/

/*
Подключаем компоненты
*/
//Группы
require_once('includes/groups.php');
//Замещения
require_once('includes/deputies.php');
//Пользовательский интерфейс для указания пользователей и групп у постов
require_once('includes/posts_ui.php');
//Страница настроек
require_once('includes/settings-api.php');



class ACL {

    function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'load_ss_acl'));
        add_filter('posts_where', array($this, 'acl_filter_where'), 10, 1);
        
        
    }

	/*
	Фильтр постов на основе наличия доступа	
	*/
    function acl_filter_where($where) {
		//if (current_user_can('full_access_to_posts') or current_user_can('editor') or current_user_can('administrator')) return $where;
		
		$current_usr_id = get_current_user_id();
		if (user_can($current_usr_id, 'full_access_to_posts') or user_can($current_usr_id, 'editor') or user_can($current_usr_id, 'administrator')) return $where;
		$acl_users[] = $current_usr_id;
		$sub = get_user_meta(get_current_user_id(), 'acl_substitutes');
		$acl_users = array_merge( $sub, $acl_users);
		$acl_users = array_unique($acl_users);

		// получим ид групп в которых есть пользователь
		$acl_groups_id = get_transient('acl_groups_id');
		if (false === $acl_groups_id) {
			$acl_groups_id = get_posts("numberposts=-1&fields=ids&post_type=user_group&meta_key=users&meta_value=".$current_usr_id);
			set_transient('acl_groups_id', $acl_groups_id, 5);
		}
		
		//$acl_groups_id = get_posts("fields=ids&post_type=user_group&meta_key=users&meta_value=".$current_usr_id);

		//Определяем типы постов для контроля доступа
		//$pt_array = array( 'report', 'cases', 'post', 'document', 'forum' );
        $pt_array = get_option( 'cp_acl_posts_types' );
		$pt = "'".implode("','", $pt_array)."'";
		//Определяем статусы постов для контроля доступа
		//добавил Резанов Е.В. 09.07.2014 
		$ps_array = array( 'publish', 'future', 'draft', 'pending', 'private' );
		$ps = "'".implode("','", $ps_array)."'";
		
		$args = array(  
			'fields' => 'ids',
			'post_type' => $pt_array,
			'post_status' =>$ps_array,
			'meta_query' => array(
				'relation' => 'OR',  
				array(  
					'key' => 'acl_users_read',  
					'value' => $acl_users
				)
			),
			'numberposts' => '-1'
		);
		
		//error_log("пользователь: ". $current_usr_id . ", группа: " . print_r($acl_groups_id, true));
		
		// на данном этапе имеем ИД пользователя и ИД групп куда он входит
		// получим ИД постов из таблицы ACL с доступом и переводим их в запятые.
		// код только не красивый
		$ids_usr = get_post_for_where_acl_cp($current_usr_id, 'user');
		$ids_group = array();
		if(!empty($acl_groups_id)) {
		    foreach ($acl_groups_id as $acl_group_id) {
		        $tmp=array();
			    $tmp=get_post_for_where_acl_cp($acl_group_id, 'group');
				$ids_group = $ids_group+$tmp;
			}
		}
		$my_ids=array_unique($ids_usr+$ids_group);
		
		
		/*if(!empty($acl_groups_id))
			$args['meta_query'][] = array(  
					'numberposts' => '-1',
					'key' => 'acl_groups_read',
					'value' => $acl_groups_id
				);

		$ids = get_transient('acl_ids');

		if (false === $ids) {
			$ids = get_posts($args);
			set_transient('acl_ids', $ids, 5);
		}*/

		//Получаем ИД постов с доступом и переводим их в запятые.
		//$ids = get_posts($args);
		//print_r('Meta:'.$ids);
		//print_r('Table:'.$my_ids);
		//$ids = implode(",", $ids);
		$ids = implode(",", $my_ids);
		//error_log($ids);
        global $wpdb;
		$where .= " AND (if(".$wpdb->posts.".post_type in (" . $pt . "),if(".$wpdb->posts.".ID IN (" . $ids . "),1,0),1)=1)";
        
		return $where;

        

    }
    
    function load_ss_acl(){
        global $post;
        //select2
        //if (!($post->post_type == "user_group') ) return;
        
        $handle = 'select2';
        
        $src_css = plugin_dir_url(__FILE__).'select2/select2.css';
        wp_enqueue_style( $handle, $src_css );

        $src_js = plugin_dir_url(__FILE__).'select2/select2.min.js';
        wp_enqueue_script( $handle, $src_js );
    }


}

$theACL = new ACL();

/* функция для выборки постов из таблицы
 по ИД пользователя, либо по ИД группы
 возвращает массив ИД постов*/
function get_post_for_where_acl_cp($subject_id, $subject_type){
        global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		$object_type='post';
		$sql = $wpdb->prepare("SELECT object_id FROM $table_name  WHERE object_type=%s AND subject_type=%s AND subject_id=%d",$object_type, $subject_type, $subject_id);
		$objects_ids = $wpdb->get_results($sql);
		$ids=array();
		if ($objects_ids){
			foreach ($objects_ids as $object_id) {
			    $id=$object_id->object_id;
				$ids[]=$id;
			}
		}
		
		return $ids;
}

// действия при активации\деактивации\удалении плагина
function ACL_Setup_on_activation()
{
	if ( ! current_user_can( 'activate_plugins' ) )
		return;
	$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
	check_admin_referer( "activate-plugin_{$plugin}" );
	acl_create_table();
	// Расcкомментируйте эту строку, чтобы увидеть функцию в действии
	// exit( var_dump( $_GET ) );
}

function ACL_Setup_on_deactivation()
{
	if ( ! current_user_can( 'activate_plugins' ) )
		return;
	$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
	check_admin_referer( "deactivate-plugin_{$plugin}" );

	// Расcкомментируйте эту строку, чтобы увидеть функцию в действии
	//exit( var_dump( $_GET ) );
}

function ACL_Setup_on_uninstall()
{
	if ( ! current_user_can( 'activate_plugins' ) )
		return;
	check_admin_referer( 'bulk-plugins' );

	// Важно: проверим тот ли это файл, который
	// был зарегистрирован в процессе хука удаления.
	if ( __FILE__ != WP_UNINSTALL_PLUGIN )
		return;
    //error_log('// Расcкомментируйте эту строку, чтобы увидеть функцию в действии');
	// Раскомментируйте эту строку, чтобы увидеть функцию в действии
	//exit( var_dump( $_GET ) );
}	

function acl_create_table () {
    global $wpdb;
    $table_name = $wpdb->prefix . "acl";
	error_log($table_name);
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE " . $table_name . " (
	        id mediumint(9) NOT NULL AUTO_INCREMENT,
			subject_type VARCHAR(55) NOT NULL,
			object_type VARCHAR(55) NOT NULL,
			subject_id mediumint(9) NOT NULL,
			object_id mediumint(9) NOT NULL,
	        UNIQUE KEY id (id)
	    );";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

register_activation_hook(   __FILE__, 'ACL_Setup_on_activation' );
register_deactivation_hook( __FILE__, 'ACL_Setup_on_deactivation' );
register_uninstall_hook(    __FILE__, 'ACL_Setup_on_uninstall' );


?>
