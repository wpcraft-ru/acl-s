<?php

//Access control list


//Access control posts on the list
function acl_filter_where($where){
    global $wpdb;

    $current_user_id = get_current_user_id();

    //Берем из настроек нужные типы постов
    $post_types=get_post_types_for_acl_s();
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
add_filter('posts_where', 'acl_filter_where', 10, 1);




//update ACL
function update_acl_s($post_id){

  //Если доступ по списку отключили, то отмена исполнения
  $acl_s_true = get_post_meta($post_id, 'acl_s_true');
  if(empty($acl_s_true)) return;

  $ids_from_meta = get_post_custom_values('acl_users_s',$post_id);

  $ids_from_meta = empty($ids_from_meta)?array():$ids_from_meta;

  $ids_from_filter = apply_filters('update_acl_s', $ids_from_filter, $post_id);
  $ids_from_filter = array_unique($ids_from_filter);

  //Получаем id которых нет в мете, но есть в данных полученных через фильтр для добавления в список
  $ids_for_add =array_diff($ids_from_filter,$ids_from_meta);

  foreach ($ids_for_add  as $user_id){
    add_post_meta($post_id,'acl_users_s',$user_id);
  }

  //Получаем id которые есть в мете, но нет в данных полученных через фильтр для удаления из списка
  $ids_for_del = array_diff($ids_from_meta, $ids_from_filter);
  foreach ($ids_for_del  as $user_id){
    delete_post_meta( $post_id, 'acl_users_s', $user_id );
  }

} add_action('save_post', 'update_acl_s', 11);


//Autoudate ACL for authot post
function add_post_author($ids, $post_id){
	$post = get_post($post_id);

  if(!in_array($post->post_author, $ids)) $ids[] = $post->post_author;

  return $ids;
} add_filter('update_acl_s', 'add_post_author', 10, 2);
