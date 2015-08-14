<?php

//Access control list
class ACL_Singleton {
private static $_instance = null;

private function __construct() {
  add_filter('posts_where', array($this, 'acl_filter_where'), 10, 1);
}

//Access control posts on the list
function acl_filter_where($where){
    global $wpdb;

    $current_user_id = get_current_user_id();

    //Берем из настроек нужные типы постов
    $post_types=explode(',', trim(get_option('acl_post_type_field')));
    foreach($post_types as &$post_type){
        $post_type="'".$post_type."'";
    }
    $string_for_query=implode(',',$post_types);

    //Если это администратор, редактор или кто то с правом доступа, то отменяем контроль
    if (user_can($current_user_id, 'full_access_to_posts') or user_can($current_user_id, 'editor') or user_can($current_user_id, 'administrator')) return $where;

    $where .= " AND
        if(" . $wpdb->posts . ".post_type IN (".$string_for_query."),
            if(" . $wpdb->posts . ".ID IN (
                    SELECT post_id
                    FROM " . $wpdb->postmeta ."
                    WHERE
                        " . $wpdb->postmeta .".meta_key = 'acl_s_true'
                        AND " . $wpdb->postmeta .".post_id = " . $wpdb->posts . ".ID
                ),
            if(" . $wpdb->posts . ".ID IN (
                    SELECT post_id
                    FROM " . $wpdb->postmeta ."
                    WHERE
                        " . $wpdb->postmeta .".meta_key = 'acl_users_s'
                        AND " . $wpdb->postmeta .".post_id = " . $wpdb->posts . ".ID
                        AND " . $wpdb->postmeta .".meta_value = " . $current_user_id ."
                )
            ,1,0),1),
        1)=1";

        return $where;
}

protected function __clone() {
	// ограничивает клонирование объекта
}
static public function getInstance() {
	if(is_null(self::$_instance))
	{
	self::$_instance = new self();
	}
	return self::$_instance;
}
} $TheACL_bs = ACL_Singleton::getInstance();
