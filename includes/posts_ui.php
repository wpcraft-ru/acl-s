<?php


/*
 * Механика для работы с метаданными ACL в постах
 */


class acl_ui_posts {
    
    function __construct() {
        add_action( 'post_submitbox_misc_actions', array($this, 'add_field_to_submitbox'));
        //add_action( 'wp_ajax_save_acl_post', array($this, 'save_acl_post_callback') );
		add_action( 'save_post', array($this, 'save_acl_post_callback') );
        add_action( 'wp_ajax_get_acl_users_for_post', array($this, 'get_acl_users_for_post_callback') );
        add_action( 'wp_ajax_get_acl_groups_for_post', array($this, 'get_acl_groups_for_post_callback') );
        //add_action( 'add_meta_boxes', array($this, 'add_acl_meta_box'));        
    }

   
    
    
    
    function add_field_to_submitbox() {
        global $post;
		if($post->post_type == 'user_group') return;
        ?>
        <div class='misc-pub-section'>
            <span id="acl">Доступ: </span>
            <a href='#TB_inline?width=750&height=350&inlineId=acl_form' class="thickbox">Настроить</a>
        </div>
        <div id='acl_form' style='display:none;'>
            <p>Укажите пользователей и группы, в соответствии с требуемым уровнем доступа. После чего закройте окно и сохраните запись.</p>
            
            <div id='acl_users_wrapper'>
                <label for='acl_users'>Пользователи</label>
                <br />
                <input id='acl_users' name='acl_users' class='select2field' />
                <br />
                
                <script>
                    jQuery(document).ready(function($) {
                        //update_users_data();
                        $("#acl_users").select2({
                            placeholder: "Укажите пользователей",
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

                            formatResult: function(element){ return "<div>" + element.title + "</div>" }, // omitted for brevity, see the source of this page
                            formatSelection: function(element){  return element.title; }, // omitted for brevity, see the source of this page
                            dropdownCssClass: "bigdrop", // apply css that makes the dropdown taller
                            escapeMarkup: function (m) { return m; } // we do not want to escape markup since we are displaying html in results
                        });

                        //Если есть данные о значении, то делаем выбор
                        <?php 
                            $acl_users = get_post_meta($post->ID ,'acl_users');
                            $users_data_for_select2 = array();
                            foreach($acl_users as $acl_user) {
                                $user = get_user_by('id', $acl_user);
                                $users_data_for_select2[] = array('id' => $acl_user, 'title' => $user->display_name );
                            }
                             //$users_data_for_select2 = array_unique($users_data_for_select2);
                            if(!empty($users_data_for_select2)): ?>   
                            $("#acl_users").select2("data", <?php echo json_encode($users_data_for_select2); ?>); 
                        <?php endif; ?>
                    
                    });
                    

                    
                   
                </script>
            
            </div>
    
            
            
            <br />
            <div id='acl_groups_wrapper'>
                <label for='acl_groups'>Группы</label><br />
                <input id='acl_groups' name='acl_groups_read' class='select2field' /><br />
                <script>
                    jQuery(document).ready(function($) {
                        $("#acl_groups").select2({
                            placeholder: "Выберите группы",
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

                            formatResult: function(element){ return "<div>" + element.title + "</div>" },
                            formatSelection: function(element){  return element.title; }, // omitted for brevity, see the source of this page
                            escapeMarkup: function (m) { return m; }, // we do not want to escape markup since we are displaying html in results
                            dropdownCssClass: "bigdrop", // apply css that makes the dropdown taller
                        });
                    });

                </script>
            </div>

        </div>

    <?php
    }
    
    
    
    
    
    
    //Сохраняем список пользователей и групп в меты
    function save_acl_post_callback($post_id){
				
        // не происходит ли автосохранение? 
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return; 
        // не ревизию ли сохраняем? 
        if (wp_is_post_revision($post_id)) return; 
		
    
        // нужный ли тип поста?	
		//$screens = array( 'report', 'cases', 'post', 'document', 'forum' );
		//$screens = get_option( 'cp_acl_posts_types' );
		//if ( !in_array($_REQUEST['post_type'], $screens) )	return;

        $acl_users = explode(',', trim($_REQUEST['acl_users']));
        $acl_users[] = get_current_user_id();
        $acl_users = array_unique($acl_users);
        $old_acl_users = get_post_meta($post_id, 'acl_users');

        if($acl_users != $old_acl_users){
            delete_post_meta($post_id, 'acl_users');
        }

        foreach ( $acl_users as $user_id ) {
            if (!(in_array($user_id, $old_acl_users)) && !empty($user_id)){
                add_post_meta($post_id, 'acl_users', $user_id);
			}
        }
        
        $acl_groups_read = $_REQUEST['acl_groups_read'];
        $old_acl_groups_read = get_post_meta($post_id, 'acl_groups_read');

        if($acl_groups_read != $old_acl_groups_read){
            delete_post_meta($post_id, 'acl_groups_read');
        }

        foreach ( $acl_groups_read as $group_id ) {
			if (!(in_array($group_id, $old_acl_groups_read)) && !empty($group_id)){
				add_post_meta($post_id, 'acl_groups_read', $group_id);
			}
        }

        //$this->update_acl_cp($post_id);
    }
    
    
    
    
    // Получаем список пользователей через AJAX запрос
    function get_acl_users_for_post_callback() {
        $ids = array();
        $elements = array();

        if (isset($_REQUEST['post_id']))
		    //error_log('глянем что у нас в реквесте>>>>>'.$_REQUEST['post_id']);
            //$ids = get_post_meta( $_REQUEST['post_id'], 'acl_users');
			//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
			$ids_from_table = $this->get_acl_cp('user', 'post', $_REQUEST['post_id']);
			$ids_from_table_array=array();
			if ($ids_from_table){
			    foreach($ids_from_table as $id_from_table){
				    $id=$id_from_table->subject_id;
					$ids_from_table_array[]=$id;
					//error_log('user can read:'.$id);
				}
			}
            //>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
			//ВНИМАНИЕ!!! перезададим переменные с пользователями из таблицы для проверки
			$ids=$ids_from_table_array;
			//print_r($ids_from_table_array);
			
        foreach ($ids as $user_id){
			if (!empty($user_id)){
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

    
    
    
    // Получаем список групп через AJAX запрос
    function get_acl_groups_for_post_callback() {
        $ids = array();
        $elements = array();

        if (isset($_REQUEST['post_id']))
            //$ids = get_post_meta( $_REQUEST['post_id'], 'acl_groups_read');
			//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
			$ids_from_table = get_acl_cp('group', 'post', $_REQUEST['post_id']);
			$ids_from_table_array=array();
			if ($ids_from_table){
			    foreach($ids_from_table as $id_from_table){
				    $groupid=$id_from_table->subject_id;
					$ids_from_table_array[]=$groupid;
					//error_log('group can read:'.$groupid);
				}
			}
            //>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
			//ВНИМАНИЕ!!! перезададим переменные с пользователями из таблицы для проверки
			$ids=$ids_from_table_array;
			
        foreach ($ids as $id){
			if (!empty($id)){
				$group = get_post($id);
				$elements[] = array(
					'id' => $id,
					'title' => $group->post_title
					);
			}				
        }

        echo json_encode($elements);
        exit; 
    }
    
    
    
    // Все что касается метабокса - на удаление
    
	function add_acl_meta_box() {

		//$screens = array( 'report', 'cases', 'post', 'document', 'forum' );
		$screens = get_option( 'cp_acl_posts_types' );

		foreach ( $screens as $screen ) {

			add_meta_box(
				'acl_meta_box',
				'Доступ к записи',
				array($this, 'acl_meta_box_callback'),
				$screen, 
				'side',
				'core'
			);
		}
	
	}
	
	function acl_meta_box_callback(){ 
        global $post;
        ?>
		<div id='acl_users_wrapper'>
            <label for='acl_users'>Пользователи:</label>
			<br />
			<input id='acl_users' name='acl_users' class='select2field' />
			<br />
        </div>
		<div id='acl_groups_wrapper'>
            <label for='acl_groups_read'>Группы:</label>
			<br />
			<?php 
			$args = array(
                'fields' => 'ids',
                's' => $_GET['q'],
                'paged' => $_GET['page'],
                'posts_per_page' => $_GET['page_limit'],
                'post_type' => 'user_group',
				'order' => 'ASC',
				'orderby' => 'title'
            );

            $query = new WP_Query( $args );

		//	print_r($post->ID);
			//$acl_groups_read = get_post_meta($post->ID, 'acl_groups_read');
			// read groups from table
			$acl_groups_read_table = get_acl_cp('group', 'post', $post->ID);
			if ($acl_groups_read_table) {
			    $acl_group_read_table_array=array();
			    foreach($acl_groups_read_table as $acl_group_read_table) {
				    $groupid=$acl_group_read_table->subject_id;
					//error_log('группа которой доступна запись='.$post->ID.':'.$groupid);
					$acl_group_read_table_array[]=$groupid;
					
				}
			}
			//print_r($acl_groups_read);
			//print_r(acl_group_read_table_array);
			
			//$acl_users = get_post_meta($post->ID, 'acl_users');
			// read users from table
			$acl_users_table = get_acl_cp('user', 'post', $post->ID);
			if ($acl_users_table){
			    $acl_users_table_array=array();
			    foreach ($acl_users_table as $acl_user_read_table) {
				    $usrid=$acl_user_read_table->subject_id;
					//error_log('пользователь которому доступна запись='.$post->ID.':'.$usrid);
					$acl_users_table_array[]=$usrid;
					//error_log('пользователь которому доступна запись='.$post->ID.':'.$usrid);
				}
			}
			//error_log($acl_users_table);
			//print_r($acl_users);
			//print_r($acl_users_table_array);
		
		//ВНИМАНИЕ!!! перезададим переменные с пользователями из таблицы для проверки
		$acl_users=$acl_users_table_array;
		$acl_groups_read=$acl_group_read_table_array;
		
			
            foreach ($query->posts as $post_id){
                if(!empty($post_id)){
					echo "<div class='list_checkbox'><label for='acl_groups_".$post_id."'><input type='checkbox' ".(in_array($post_id, $acl_groups_read) ? "checked='checked'" : '')." name='acl_groups_read[]' id='acl_groups_".$post_id."' value='".$post_id."' />".get_the_title($post_id)."</label></div>";
				}
            }

			?>
			<br />
        </div>
		 <script>
                jQuery(document).ready(function($) {
                    update_users_data();
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
								console.log(acl);
                                jQuery('#acl_users').select2('data',  acl);
                            }
                        });
                }
            </script>
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
$TheACL_posts = new acl_ui_posts();
