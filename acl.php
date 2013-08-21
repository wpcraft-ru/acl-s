<?php
/*
Plugin Name: ACL
Plugin URI: http://www.casepress.org/
Description: Access control list for WP
Version: 20130816
Author: CasePress Studio
Author URI: http://www.casepress.org
License: GPL
*/

/*
 * Механика для работы с метаданными ACL в постах
 */
$TheACL_posts = new acl_posts();
class acl_posts {
    
    function __construct() {
        add_action('post_submitbox_misc_actions', array($this, 'add_field_to_submitbox'));
        add_action( 'wp_ajax_save_acl_post', array($this, 'save_acl_post_callback') );
        add_action( 'wp_ajax_get_acl_users_for_post', array($this, 'get_acl_users_for_post_callback') );
        add_action( 'wp_ajax_get_acl_groups_for_post', array($this, 'get_acl_groups_for_post_callback') );
        
        
        
    }
    
    function auto_add_access(){
        //Если пользователю дан доступ полный, то автоматом дать доступ Чтение и Правка.
        //Если правка, то дать чтение.
        //На чтение должен быть доступ у всех
        //Это же правило относится к группам.
    }

    function auto_add_access_for_author(){
        
    }

    function get_acl_users_for_post_callback() {
        $ids = array();
        $elements = array();

        if (isset($_REQUEST['post_id']))
            $ids = get_post_meta( $_REQUEST['post_id'], 'acl_users_read');

        foreach ($ids as $user_id){
            $user = get_userdata($user_id);
            $elements[] = array(
                'id' => $user_id,
                'title' => $user->display_name
                );			
        }

        echo json_encode($elements);
        exit; 
    }

    function get_acl_groups_for_post_callback() {
        $ids = array();
        $elements = array();

        if (isset($_REQUEST['post_id']))
            $ids = get_post_meta( $_REQUEST['post_id'], 'acl_groups_read');

        foreach ($ids as $id){
            $group = get_post($id);
            $elements[] = array(
                'id' => $id,
                'title' => $group->post_title
                );			
        }

        echo json_encode($elements);
        exit; 
    }
    
    function save_acl_post_callback(){
        $post_id = $_REQUEST['post_id'];
        //save_acl_post
        $acl_users_read = explode( ',', trim($_REQUEST['acl_users_read']));
        $old_acl_users_read = get_post_meta($post_id, 'acl_users_read');
        
        //добавляем новые ИД если их нет в старом списке
        foreach ( $acl_users_read as $user_id ) {
            if (!(in_array($user_id, $old_acl_users_read)))
                add_post_meta($post_id, 'acl_users_read', $user_id);
        }
        
        //удаляем старые ИД если их нет в новом списке
        foreach ( $old_acl_users_read as $old_user_id ) {
            if (!(in_array($old_user_id, $acl_users_read)))
                delete_post_meta($post_id, 'acl_users_read', $old_user_id);
        }
        
        
        //$acl_users_edit = $_REQUEST['acl_users_edit'];
        //$acl_users_full = $_REQUEST['acl_users_full'];
        $acl_groups_read = explode( ',', trim($_REQUEST['acl_groups_read']));
        
        $old_acl_groups_read = get_post_meta($post_id, 'acl_groups_read');
        
        //добавляем новые ИД если их нет в старом списке
        foreach ( $acl_groups_read as $group_id ) {
            if (!(in_array($group_id, $old_acl_groups_read)))
                add_post_meta($post_id, 'acl_groups_read', $group_id);
        }
        
        //удаляем старые ИД если их нет в новом списке
        foreach ( $old_acl_groups_read as $old_group_id ) {
            if (!(in_array($old_group_id, $acl_groups_read)))
                delete_post_meta($post_id, 'acl_groups_read', $old_group_id);
        }       
        
        //$acl_groups_edit = $_REQUEST['acl_groups_edit'];
        //$acl_groups_full = $_REQUEST['acl_groups_full'];
    }
    
    function add_field_to_submitbox() {
        global $post;
        ?>
        <div class='misc-pub-section'>
            <span id="acl">Доступ: </span>
            <a href='#TB_inline?width=600&height=550&inlineId=acl_form' class="thickbox">Настроить</a>
        </div>
        <div id='acl_form' style='display:none;'>
            <br />
            <p>Укажите пользователей и группы, в соответствии с требуемым уровнем доступа.</p>
            <p><a href='#ok' id='save_acl_post'>Сохранить</a></p>
            <script type="text/javascript">
                (function($) {

                    $("#save_acl_post").click(function(){
                        
                        $.ajax({
                            data: ({
                                acl_users_read: $("#acl_users_read").val(),
                                //acl_users_edit: $("#acl_users_edit").val(),
                                //acl_users_full: $("#acl_users_full").val(),
                                acl_groups_read: $("#acl_groups_read").val(),
                                //acl_groups_edit: $("#acl_groups_edit").val(),
                                //acl_groups_full: $("#acl_groups_full").val(),
                                post_id: <?php echo $post->ID ?>,
                                action: 'save_acl_post'
                            }),
                            url: "<?php echo admin_url('admin-ajax.php') ?>",
                            success: function(data) {
                                $("#save_acl_post").append("<br /><strong>Сохранено</strong>");
                            }                                
                        });
                    });

                })(jQuery);   
            </script>
            
            <fieldset id='users_access'>
                <legend><h1>Доступ пользователей</h1></legend>
                <br />
                <label for='acl_users_read'>На чтение</label><br /><input id='acl_users_read' class='select2field'></input><br />
            </fieldset>
            <script>
                jQuery(document).ready(function($) {
                    update_users_data();
                    $("#acl_users_read, #acl_users_edit, #acl_users_full").select2({
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
                });
                function update_users_data() {
                        jQuery.ajax({
                            data: ({
                                action: 'get_acl_users_for_post',
                                dataType: 'json',
                                post_id: <?php echo $post->ID ?>,
                            }),
                            url: "<?php echo admin_url('admin-ajax.php') ?>",
                            success: function(data) {
                                acl = jQuery.parseJSON(data);
                                jQuery('#acl_users_read').select2('data',  acl);
                            }
                        });
                }
            </script>
            
            
            <br />
            <fieldset id='groups_access'>
                <legend><h1>Доступ для групп</h1></legend>
                <br />
                <label for='acl_groups_read'>На чтение</label><br />
                <input id='acl_groups_read' class='select2field'></input><br />
            </fieldset>
            <script>
                jQuery(document).ready(function($) {
                    update_groups_data();
                    $("#acl_groups_read, #acl_groups_edit, #acl_groups_full").select2({
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
                                    action: 'query_groups',
                                    page_limit: 10, // page size
                                    page: page, // page number
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
                                action: 'get_acl_groups_for_post',
                                dataType: 'json',
                                post_id: <?php echo $post->ID ?>,
                            }),
                            url: "<?php echo admin_url('admin-ajax.php') ?>",
                            success: function(data) {
                                acl = $.parseJSON(data);
                                $('#acl_users').select2('data',  acl);
                            }
                        });
                    });
                    
                    function update_groups_data() {
                        jQuery.ajax({
                            data: ({
                                action: 'get_acl_groups_for_post',
                                dataType: 'json',
                                post_id: <?php echo $post->ID ?>,
                            }),
                            url: "<?php echo admin_url('admin-ajax.php') ?>",
                            success: function(data) {
                                acl = jQuery.parseJSON(data);
                                jQuery('#acl_groups_read').select2('data',  acl);
                            }
                        });
                }
            
            </script>
        </div>
        <script type="text/javascript">


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

}

/*
 * Замещения
 */
$TheACL_substitutes = new acl_substitutes();
class acl_substitutes {
    
    function __construct() {
        add_action( 'show_user_profile', array($this, 'acl_user_profile_fields') );
        add_action( 'edit_user_profile', array($this, 'acl_user_profile_fields' ));

        add_action( 'personal_options_update', array($this, 'save_acl_user_profile_fields' ));
        add_action( 'edit_user_profile_update', array($this, 'save_acl_user_profile_fields' ));
    }



    
    function acl_user_profile_fields($user){
        if ( !current_user_can( 'edit_user', $user_id ) ) { return false; }
        $acl_substitutes = implode(",", get_user_meta($user->ID, 'acl_substitutes'));
        
        ?>
    <div>
        <h3>Заместители</h3>
        </p>Эти пользователи получают доступ к записям текующего пользователя в рамках механизма ACL. Перечисление ИД пользователей через запятую.</p>
        <input id="acl_substitutes" name ="acl_substitutes" value="<?php echo $acl_substitutes ?>" size="100%" />
    </div>
    <?php
    }
    
    function save_acl_user_profile_fields( $user_id ) {

        if ( !current_user_can( 'edit_user', $user_id ) ) { return false; }

        $acl_substitutes = explode( ',', trim($_REQUEST['acl_substitutes']));
        
        $meta_key = 'acl_substitutes';
        delete_user_meta($user_id, $meta_key);
        
        //добавляем новые ИД если их нет в старом списке
        foreach ( $acl_substitutes as $sub_id ) {
                add_user_meta($user_id, $meta_key, $sub_id);
        }
    }
    
}


$TheACL_groups = new acl_groups();
class acl_groups {
    
    function __construct() {
        add_action('admin_init', array($this, 'add_custom_box'));  
        add_action('save_post', array($this, 'save_postdata'));  
        add_action('init', array($this, 'create_model'));
        add_action('admin_menu', array($this, 'sort_pages_admin'));
        add_filter('user_search_columns', array($this, 'add_display_name_to_query_users'));

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
            $user = get_userdata($user_id);
            $elements[] = array(
                'id' => $user_id,
                'title' => $user->display_name
                );			
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
                
                $elements[] = array(
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    );
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
                $elements[] = array(
                    'id' => $user->ID,
                    'title' => $user->display_name,
                    'email' => $user->user_email,
                    );
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

      // проверяем разрешено ли пользователю указывать эти данные   
        if ( !current_user_can( 'manage_options' ) )  
          return $post_id;

      delete_post_meta($post_id, 'users');
    
      if(isset($_POST['users'])){
        foreach ( ( array ) explode( ',', trim( $_POST['users'] ) ) as $user_id ) {
            add_post_meta($post_id, 'users', $user_id, false);
        }
      }
       
    }  
    
    function sort_pages_admin() {
        add_users_page( 'Группы пользователей', 'Группы пользователей', 'manage_options', 'edit.php?post_type=user_group');
    }

}

class ACL {

    function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'load_ss_acl'));
        add_filter('posts_where', array($this, 'acl_filter_where'), 10, 1);
        
        
    }
    
    function acl_filter_where($where) {
        global $wpdb;
        
        
        if (current_user_can('full_access_to_posts')) return $where;
   
		$acl_users[] = get_current_user_id();
		$sub = get_user_meta(get_current_user_id(), 'acl_substitutes');
		$acl_users = array_merge( $sub, $acl_users);
		$acl_users = array_unique($acl_users);

		$acl_groups_id = get_posts("fields=ids&post_type=user_group&meta_key=users&meta_value=".get_current_user_id());

		$args = array(  
			'fields' => 'ids',
			'post_type' => 'any',
			'meta_query' => array(  
				'relation' => 'OR',  
				array(  
					'key' => 'acl_users_read',  
					'value' => $acl_users
				),
				array(  
					'key' => 'acl_groups_read',
					'value' => $acl_groups_id
				)
			)
		);

		$ids = get_posts($args);
		$ids = implode(",", $ids);


		$where .= " AND (if(".$wpdb->posts.".post_type in ('report'),if(".$wpdb->posts.".ID IN (" . $ids . "),1,0),1)=1)";

		return $where;
        

    }
    
    function load_ss_acl(){
        global $post;
        //select2
        //if (!($post->post_type == "user_group') ) return;
        
        $handle = 'select2';
        
        $src_css = plugin_dir_url(__FILE__).'select2/select2.css';
        wp_enqueue_style( $handle, $src_css );

        $src_js = plugin_dir_url(__FILE__).'select2/select2.min.js';
        wp_enqueue_script( $handle, $src_js );
    }


}

$theACL = new ACL();

?>