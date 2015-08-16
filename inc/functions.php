<?php


function get_post_types_for_acl_s(){

  $post_types = explode(',', trim(get_option('acl_post_type_field')));

  $post_types = apply_filters( 'chg_post_types_for_acl_s', $post_types );

  return $post_types;
}
