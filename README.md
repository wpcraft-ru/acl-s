# acl-s
ACL by Systemo for WordPress

# Установка

1. Ставим и активируем плагин
2. Указываем типы постов которым нужна опция доступа по списку (Параметры / Чтение или через хук chg_post_types_for_acl_s)
3. Далее у постов указанного типа появляется галочка "Доступ по списку", если ее нажать то можно указать список доступа

# Функции

- `update_acl_s($post_id)` - update access control list
- `get_post_types_for_acl_s()` - get post types for ACL (return array)

# Фильтры

## update_acl_s

apply_filters('update_acl_s',$ids_from_filter, $post_id);

Example for post author

//Autoudate ACL for authot post
function add_post_author($ids, $post_id){
	$post = get_post($post_id);

  if(!in_array($post->post_author, $ids)) $ids[] = $post->post_author;

  return $ids;
} add_filter('update_acl_s', 'add_post_author', 10, 2);

## chg_post_types_for_acl_s

Allow change post type for ACL

apply_filters( 'chg_post_types_for_acl_s', $post_types );
