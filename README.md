# acl-s
ACL by Systemo for WordPress

# Function update ACL

- `update_acl_s($post_id)` - update access control list


# Filter

Example for post author
`
//Autoudate ACL for authot post
function add_post_author($ids, $post_id){
	$post = get_post($post_id);

  if(!in_array($post->post_author, $ids)) $ids[] = $post->post_author;

  return $ids;
} add_filter('update_acl_s', 'add_post_author', 10, 2);
`
