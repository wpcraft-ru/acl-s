<?php


//update ACL
function update_acl_s($post_id){
  if(empty($_REQUEST['acl_s_true'])){
    return;
  }

  $ids_from_meta = get_post_custom_values('acl_users_s',$post_id);

  $ids_from_meta = empty($ids_from_meta)?array():$ids_from_meta;

  $ids_from_filter=apply_filters('update_acl_s',$ids_from_filter, $post_id);

  $users_id=array_diff($ids_from_filter,$ids_from_meta);

  foreach ($users_id as $user_id){
    add_post_meta($post_id,'acl_users_s',$user_id);
  }
} add_action('save_post', 'update_acl_s');


//Autoudate ACL for authot post
function add_post_author($ids, $post_id){
	$post = get_post($post_id);

  if(!in_array($post->post_author, $ids)) $ids[] = $post->post_author;

  return $ids;
} add_filter('update_acl_s', 'add_post_author', 10, 2);
