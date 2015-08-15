<?php
class ACL_filter_Singleton{
private static $_instance = null;

private function __construct() {
  add_action('post_updated', array($this,'update_acl_s'));
  add_filter('update_acl_s',array($this,'add_post_author'));
}



function update_acl_s($post_id){
  if(empty($_REQUEST['acl_s_true'])){
    return;
  }
  $ids_from_meta=get_post_custom_values('acl_users_s',$post_id);
  $ids_from_meta=!empty($ids_from_meta)?$ids_from_meta:[];
  $ids_from_filter=apply_filters('update_acl_s',$ids_from_filter);
  $users_id=array_diff($ids_from_filter,$ids_from_meta);
  foreach ($users_id as $user_id){
    add_post_meta($post_id,'acl_users_s',$user_id);
  }
  }

function add_post_author(){
	global $post;
    return [$post->post_author];
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
}$ACL_filter = ACL_filter_Singleton::getInstance();