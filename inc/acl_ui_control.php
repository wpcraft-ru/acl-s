<?php

/**
 * Обновляет данные таблицы доступа на основе ручного списка доступа
 */

class ACL_UI_Control {
  function __construct()
  {
    add_filter( 'update_acl_s', array($this,'update_acl_s_cb'), $priority = 10, $accepted_args = 2 );

    add_action( 'updated_post_meta', array($this, 'updated_postmeta_cb'), $priority = 10, $accepted_args = 4 );
    add_action( 'deleted_post_meta', array($this,'updated_postmeta_cb'), $priority = 10, $accepted_args = 4 );
    add_action( 'added_post_meta', array($this,'updated_postmeta_cb'), $priority = 10, $accepted_args = 4 );

  }

  //Получаем данные из ручного списка и подставляем в фильтр основного списка
  function update_acl_s_cb($ids, $post_id){
    
    $ids_from_manual_list = get_post_meta( $post_id, $key = 'list_users_for_acl_additional', $single = false );

    if(is_array($ids_from_manual_list)) $ids = array_merge($ids, $ids_from_manual_list);

    return $ids;
  }

  //Проверяем нужное ли метаполе обновилось и если да, то вызываем функцию обновления основного списка доступа
  function updated_postmeta_cb( $meta_id, $object_id, $meta_key, $meta_value){

    if($meta_key != 'list_users_for_acl_additional') return;

    update_acl_s($object_id);
  }


} $The_ACL_UI_Control = new ACL_UI_Control;
