<?php


$TheACL_groups = new acl_groups();
class acl_groups {
    
    function __construct() {
        add_action('admin_init', array($this, 'add_custom_box'));  
        add_action('save_post', array($this, 'save_postdata'));  
        add_action('init', array($this, 'create_model'));
        add_action('admin_menu', array($this, 'sort_pages_admin'));
        add_filter('user_search_columns', array($this, 'add_display_name_to_query_users'));
		add_action('wpmu_delete_user', array($this,'delete_user_from_groups_and_posts'));
		add_action('delete_user', array($this,'delete_user_from_groups_and_posts'));

        /*
         * AJAX
         */
        add_action( 'wp_ajax_query_users', array($this, 'query_users_callback') );
        add_action( 'wp_ajax_get_users_for_group', array($this, 'get_users_for_group_callback') );
        add_action( 'wp_ajax_query_groups', array($this, 'query_groups_callback') );
        
    }

    function create_model() {
        $labels = array(
                'name'              => 'Группы пользователей',
                'singular_name'		=> 'Группа пользователей',
                'add_new' 			=> 'Добавить',
                'add_new_item' 		=> 'Добавить',
                'edit_item' 		=> 'Изменить',
                'new_item' 			=> 'Новая группа',
                'view_item' 		=> 'Просмотр',
                'search_items' 		=> 'Поиск',
                'not_found' 		=> 'Запись не найдена',
                'not_found_in_trash'=> 'Запись не найдена',
                'parent_item_colon' => ''
        );	

        
        $supports = array (
            'title',
            'editor'
        );
        if (get_option( 'enable_custom_fields_for_cases' )) $supports[]="custom-fields";

        $args = array(
                'labels' 			=> $labels,
                'singular_label' 	=> 'Группы пользователей',
                'show_ui'           => true, 
                'show_in_menu'      => false, 
                'capability_type' 	=> 'post',	
                'hierarchical' 		=> true,
                'public' => false,
                'has_archive' => false,
                'publicly_queryable' => false,
                'query_var' => false,
                'supports' 			=> $supports
         );
        register_post_type('user_group',$args);
    }
    
    function add_custom_box() {  
        add_meta_box( 'myplugin_sectionid', 'Пользователи',   
                    array($this, 'inner_custom_box'), 'user_group' );  
    }  

    function get_users_for_group_callback(){
        $ids = array();
        $elements = array();

        if (isset($_REQUEST['post_id']))
            $ids = get_post_meta( $_REQUEST['post_id'], 'users');

        foreach ($ids as $user_id){
			if(!empty($user_id)){
				$user = get_userdata($user_id);
				$elements[] = array(
					'id' => $user_id,
					'title' => $user->display_name
					);
			}
        }

        echo json_encode($elements);
        exit; 
    }
    
    /* Пишем код блока */  
    function inner_custom_box() {  
        global $post;
      // Используем nonce для верификации  
      wp_nonce_field( plugin_basename(__FILE__), 'acl_noncename' );  

      ?>
	        <input type="text" id="acl_users" name="users" size="25" />
            <small>Выберите пользователей группы</small>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $("#acl_users").select2({
                        placeholder: "",
                        formatInputTooShort: function (input, min) { return "Пожалуйста, введите " + (min - input.length) + " или более символов"; },
                        minimumInputLength: 1,
                        formatSearching: function () { return "Поиск..."; },
                        formatNoMatches: function () { return "Ничего не найдено"; },
                        width: '100%',
                        multiple: true,
                        ajax: {
                            url: "<?php echo admin_url('admin-ajax.php') ?>",
                            dataType: 'json',
                            quietMillis: 100,
                            data: function (term, page) { // page is the one-based page number tracked by Select2
                                return {
                                    action: 'query_users',
                                    page_limit: 10, // page size
                                    page: page, // page number
                                    //params: {contentType: "application/json;charset=utf-8"},
                                    q: term //search term
                                };
                            },
                            results: function (data, page) {
                                //alert(data.total);
                                var more = (page * 10) < data.total; // whether or not there are more results available

                                // notice we return the value of more so Select2 knows if more results can be loaded
                                return {
                                    results: data.elements,
                                    more: more
                                    };
                            }
                        },

                        formatResult: elementFormatResult, // omitted for brevity, see the source of this page
                        formatSelection: elementFormatSelection, // omitted for brevity, see the source of this page
                        dropdownCssClass: "bigdrop", // apply css that makes the dropdown taller
                        escapeMarkup: function (m) { return m; } // we do not want to escape markup since we are displaying html in results
                        });
                        $.ajax({
                            data: ({
                                action: 'get_users_for_group',
                                dataType: 'json',
                                post_id: <?php echo $post->ID ?>,
                            }),
                            url: "<?php echo admin_url('admin-ajax.php') ?>",
                            success: function(data) {
							//	console.log(data);
                                members = $.parseJSON(data);
                                $('#acl_users').select2('data',  members);
                            }
                        });
                    });
                
                //format data from server and render list nodes for select2
                function elementFormatResult(element) {
                        //alert(element.title);
                        var markup = "<div id=\"select-list\">";
                        //if (movie.posters !== undefined && movie.posters.thumbnail !== undefined) {
                        //	markup += "<td class='movie-image'><img src='" + movie.posters.thumbnail + "'/></td>";
                        //}
                        markup += "<div class='node-title'>" + element.title + "</div>";
                        if (element.email !== undefined) {
                                markup += "<div class='node-email'>" + element.email + "</div>";
                        }
                        if (element.organization !== undefined) {
                                markup += "<div class='node-organization'>" + element.organization + "</div>";
                        }

                        markup += "</div>";
                        //alert(markup);
                        return markup;
                }

                //get field for put to input 
                function elementFormatSelection(element) {
                        return element.title;
                }
        </script>
      <?php
    }  

    function query_groups_callback(){
            $args = array(
                'fields' => 'ids',
                's' => $_GET['q'],
                'paged' => $_GET['page'],
                'posts_per_page' => $_GET['page_limit'],
                'post_type' => 'user_group'
                );

            $query = new WP_Query( $args );

            $elements = array();
            foreach ($query->posts as $post_id){
                if(!empty($post_id)){
					$elements[] = array(
						'id' => $post_id,
						'title' => get_the_title($post_id),
						);
				}
            }
			
            $data[] = array(
                "total" => (int)$query->found_posts, 
                'elements' => $elements);
            echo json_encode($data[0]);
            exit;
    }
    
    function query_users_callback(){
            $args = array(
                'offset' => $_GET['page']-1,
                'number' => $_GET['page_limit'],
                'search' => '*'.$_GET['q'].'*',
                'search_columns' => array( 'user_login', 'user_email', 'user_nicename' )
                );

            $query = new WP_User_Query( $args );
           

            $elements = array();
            foreach ($query->results as $user){
				if(!empty($user)){
					$elements[] = array(
						'id' => $user->ID,
						'title' => $user->display_name,
						'email' => $user->user_email,
						);
				}
            }
			
            $data[] = array(
                "total" => (int)$query->total_users, 
                'elements' => $elements);
            //$data[] = $query;
            echo json_encode($data[0]);
            exit;
    }
    
    function add_display_name_to_query_users($search_columns){
        /*Dysplay Name not added to WP User Query by default. And need use filter http://core.trac.wordpress.org/changeset/24056*/
        $search_columns[] = 'display_name';
        return $search_columns;
    }
  
    /* Сохраняем данные, когда пост сохраняется */  
    function save_postdata( $post_id ) {  

      // проверяем nonce из нашей страницы, потому что save_post может быть вызван с другого места.  

    if(isset($_POST['acl_noncename'])){
        if ( !wp_verify_nonce( $_POST['acl_noncename'], plugin_basename(__FILE__) )) {  
            return $post_id;
          }  
    }  
	
	$post_data = get_post($post_id, ARRAY_A);
	if($post_data['post_type'] != 'user_group') return;

      // проверяем разрешено ли пользователю указывать эти данные   
        if ( !current_user_can( 'manage_options' ) )  
          return $post_id;

      delete_post_meta($post_id, 'users');
    
      if(isset($_POST['users'])){
        foreach ( ( array ) explode( ',', trim( $_POST['users'] ) ) as $user_id ) {
			if (!empty($user_id)){
				add_post_meta($post_id, 'users', $user_id, false);
			}
        }
      }
       
    }  
    
    function sort_pages_admin() {
        add_users_page( 'Группы пользователей', 'Группы пользователей', 'manage_options', 'edit.php?post_type=user_group');
    }

	function delete_user_from_groups_and_posts($user_id){	
		//error_log('// acl - deleting users from group - '.$user_id);
		$posts = get_posts(array(
	        'numberposts'     => -1,
	        'offset'          => 0,
	        'category'        => '',
	        'orderby'         => 'post_date',
	        'order'           => 'DESC',
	        'include'         => '',
	        'exclude'         => '',
	        'meta_key'        => 'users',
	        'meta_value'      => $user_id,
	        'post_type'       => 'user_group',
	        'post_mime_type'  => '',
	        'post_parent'     => '',
	        'post_status'     => 'any'
        ));
        foreach($posts as $post){ 
		    setup_postdata($post);
			$post_id=$post->ID;
			delete_post_meta($post_id, 'users', $user_id);
        }
        wp_reset_postdata();
		
		
		//error_log('// acl - deleting users from posts - '.$user_id);
		$posts = get_posts(array(
	        'numberposts'     => -1,
	        'offset'          => 0,
	        'category'        => '',
	        'orderby'         => 'post_date',
	        'order'           => 'DESC',
	        'include'         => '',
	        'exclude'         => '',
	        'meta_key'        => 'acl_users_read',
	        'meta_value'      => $user_id,
	        'post_type'       => 'post',
	        'post_mime_type'  => '',
	        'post_parent'     => '',
	        'post_status'     => 'any'
        ));
		foreach($posts as $post){ 
		    setup_postdata($post);
			$post_id=$post->ID;
			delete_post_meta($post_id, 'acl_users_read', $user_id);
        }
        wp_reset_postdata();
		
		//error_log('// acl - deleting users from table - '.$user_id);
		global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		// проверим, если такая запись есть то удалим
		$sql = $wpdb->prepare("DELETE FROM $table_name WHERE subject_id=%d AND subject_type=%s", $user_id, 'user');
	    $wpdb->query($sql);
	}
}