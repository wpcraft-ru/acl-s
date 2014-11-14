<?php
/*
Plugin Name: ACL
Plugin URI: http://www.casepress.org/
Description: Access control list for WP
Version: 20141114
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
include_once('includes/groups.php');
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
					'key' => 'acl_users',  
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
		
		$ids = implode(",", $my_ids);

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


	// функции для работы с таблицей ACL
	
function add_acl_cp ($subject_type, $object_type, $subject_id, $object_id) {
	    global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		// проверим есть ли такая запись если есть - обновим, если нет, то добавим
		$check_acl_table=check_acl_cp($subject_type, $object_type, $subject_id, $object_id);
		//error_log('$check_acl_table='.$check_acl_table);
		if (!$check_acl_table){
		    //error_log('нет такой записи, добавляем '.$subject_type.' '.$subject_id.' для :'.$object_id);
		    $data=array('subject_id'=>$subject_id, 'subject_type'=>$subject_type, 'object_type'=>'post', 'object_id'=>$object_id);
		    $format=array('%d','%s', '%s', '%d');
		    $result = $wpdb->insert($table_name, $data, $format);    
		}
		else {
		    //error_log('есть такая запись. обновляем:'.$object_id);
		    $data=array('subject_id'=>$subject_id, 'subject_type'=>$subject_type, 'object_type'=>'post');
		    $format=array('%d','%s', '%s');
			$where=array('object_id'=>$object_id);
			$where_format=array('%d');
		    $result = $wpdb->update($table_name, $data, $format, $where, $where_format);
		}

	    //$wpdb->show_errors();
		//$wpdb->print_error();
		return $result;
}

function get_acl_cp($subject_type, $object_type, $object_id) {
	    global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		$sql = $wpdb->prepare("SELECT subject_id FROM $table_name  WHERE object_type=%s AND subject_type=%s AND object_id=%d",$object_type, $subject_type, $object_id);
		$subjects_ids = $wpdb->get_results($sql);
		
		return $subjects_ids;
}
	
function check_acl_cp ($subject_type, $object_type, $subject_id, $object_id) {
	    // проверяем есть ли уже в таблице такая запись 
		global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		$subjects_ids = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name  WHERE object_type=%s AND subject_type=%s AND object_id=%d AND subject_id=%s",$object_type, $subject_type, $object_id, $subject_id));
		//$subjects_ids = $wpdb->get_results($sql);
		
		return $subjects_ids;
}
	
function update_acl_cp($post_id){

        $users_ids = apply_filters( 'acl_users_list', array(), $post_id );

        $users_ids = array_unique($users_ids);

        foreach ($users_ids as $user_id) {
            add_acl_cp ('user', 'post', $user_id, $post_id);
        }
}

//Удаляем записи из списка доступа, если удалили пост
function del_acl_cp($subject_type, $object_type, $subject_id, $object_id) {
	    global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		$result=0;
		// проверим, если такая запись есть то удалим
		$check_acl_table=check_acl_cp($subject_type, $object_type, $subject_id, $object_id);
		//error_log($check_acl_table);
		if ($check_acl_table) {
		    //error_log('есть такая запись, удаляем');
		    $sql = $wpdb->prepare("DELETE FROM $table_name WHERE object_id =%d AND subject_type=%s AND object_type=%s AND subject_id=%d", $object_id, $subject_type, $object_type, $subject_id);
	        $result = $wpdb->query($sql);
            //$wpdb->show_errors();
		    //$wpdb->print_error();
            
        }
		return $result;	
		
}
add_action( 'delete_post', 'del_acl_cps', 10, 1 );

	
    
function acl_users_list_save_post($users_ids, $post_id){
        $saved_users_ids = get_post_meta($post_id, 'acl_users');
        $post_users = array_merge($users_ids, $saved_users_ids); 
        return array_unique($post_users);
}
add_filter( 'acl_users_list', 'acl_users_list_save_post', 10, 2 );

//обновляем ACL если обновили список доступа в метах поста
function meta_change_acl_update($meta_id, $post_id, $meta_key){
		if(in_array($meta_key, array('acl_users'))){
			update_acl_cp($post_id);
		}
}
add_action( 'added_post_meta', 'meta_change_acl_update', 10, 3 );
add_action( 'updated_post_meta', 'meta_change_acl_update', 10, 3 );
add_action( 'deleted_post_meta', 'meta_change_acl_update', 10, 3 );

//удаляем мету группы у пользователя, если удалили группу
function del_acl_cps($acl_group_id){
		//ПРИ ОГРОМНОМ КОЛ-ВЕ ПОСТОВ МОЖЕТ ВЫЗВАТЬ ЗАМЕДЛЕНИЕ РАБОТЫ! РЕШЕНИЕ ПЕРЕДЕЛАТЬ ЧЕРЕЗ SQL
		if (get_post_type( $acl_group_id ) != 'user_group') return;
		$all_posts = get_posts("numberposts=-1&fields=ids&post_type=any");
		foreach($all_posts as $single_post){
			delete_post_meta($single_post, 'acl_groups_read', $acl_group_id);
			del_acl_cp('group', 'post', $acl_group_id, $single_post);
		}
}
add_action( 'delete_post','del_acl_cps', 10, 1 );
	
	
/*

функция для выборки постов из таблицы
 по ИД пользователя, либо по ИД группы
 возвращает массив ИД постов

*/
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
register_activation_hook(   __FILE__, 'ACL_Setup_on_activation' );
function ACL_Setup_on_activation(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "activate-plugin_{$plugin}" );
		acl_create_table();
		// Расcкомментируйте эту строку, чтобы увидеть функцию в действии
		// exit( var_dump( $_GET ) );
}

register_deactivation_hook( __FILE__, 'ACL_Setup_on_deactivation' );
function ACL_Setup_on_deactivation(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "deactivate-plugin_{$plugin}" );

		// Расcкомментируйте эту строку, чтобы увидеть функцию в действии
		//exit( var_dump( $_GET ) );
}

register_uninstall_hook(    __FILE__, 'ACL_Setup_on_uninstall' );
function ACL_Setup_on_uninstall(){
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

//TODO
function auto_add_access(){
    //Если пользователю дан доступ полный, то автоматом дать доступ Чтение и Правка.
    //Если правка, то дать чтение.
    //На чтение должен быть доступ у всех
    //Это же правило относится к группам.
}
