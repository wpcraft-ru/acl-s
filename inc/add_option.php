<?php
add_action( 'admin_init', 'add_acl_post_type_option' );
function add_acl_post_type_option() {
	add_settings_section(
		'acl_post_type',
		'Типы постов для ACL',
		'',
		'reading'
	);
	add_settings_field(
		'acl_post_type_field',
		'Укажите типы постов через запятую',
		'acl_post_type_field_callback_function',
		'reading',
		'acl_post_type'
	);
	register_setting( 'reading', 'acl_post_type_field');
}

function acl_post_type_field_callback_function() {
	echo '<input name="acl_post_type_field" type="text" value="' . get_option( 'acl_post_type_field' ) . '">';
}