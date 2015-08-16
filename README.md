# acl-s
ACL by Systemo for WordPress

# Function update ACL

- `update_acl_s($post_id)` - update access control list
- `get_post_types_for_acl_s()` - get post types for ACL (return array)

# Filter

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
