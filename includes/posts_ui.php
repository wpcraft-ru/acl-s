<?php


/*
 * Механика для работы с метаданными ACL в постах
 */
$TheACL_posts = new acl_ui_posts();
class acl_ui_posts {
    
    function __construct() {
        add_action( 'post_submitbox_misc_actions', array($this, 'add_field_to_submitbox'));
        add_action( 'wp_ajax_save_acl_post', array($this, 'save_acl_post_callback') );
		add_action( 'save_post', array($this, 'save_acl_post_callback') );
        add_action( 'wp_ajax_get_acl_users_for_post', array($this, 'get_acl_users_for_post_callback') );
        add_action( 'wp_ajax_get_acl_groups_for_post', array($this, 'get_acl_groups_for_post_callback') );
        add_action( 'delete_post', array($this, 'del_acl_cps'), 10, 1 );
        //add_action( 'add_meta_boxes', array($this, 'add_acl_meta_box'));

        add_filter( 'acl_users_list', array($this, 'acl_users_list_save_post'), 10, 2 );
        add_filter( 'acl_users_list', array($this, 'acl_users_list_members'), 10, 2 );
        
        add_action( 'added_post_meta', array($this, 'meta_change_acl_update'), 10, 3 );
        add_action( 'updated_post_meta', array($this, 'meta_change_acl_update'), 10, 3 );
        add_action( 'deleted_post_meta', array($this, 'meta_change_acl_update'), 10, 3 );
    }

    function acl_users_list_save_post($users_ids, $post_id){
        $saved_users_ids = get_post_meta($post_id, 'acl_users_read');
        $post_users = array_merge($users_ids, $saved_users_ids); 
        return array_unique($post_users);
    }

    function acl_users_list_members($users_ids, $post_id){
        $saved_users_ids = get_post_meta($post_id, 'members-cp-posts-sql');
        $post_users = array_merge($users_ids, $saved_users_ids);
        return array_unique($post_users);
    }

    function meta_change_acl_update($meta_id, $post_id, $meta_key){
		if(in_array($meta_key, array('acl_users_read', 'members-cp-posts-sql'))){
			$this->update_acl_cp($post_id);
		}
    }
	
	// функции для работы с таблицей acl
	
	function add_acl_cp ($subject_type, $object_type, $subject_id, $object_id) {
	    global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		// проверим есть ли такая запись если есть - обновим, если нет, то добавим
		$check_acl_table=$this->check_acl_cp($subject_type, $object_type, $subject_id, $object_id);
		//error_log('$check_acl_table='.$check_acl_table);
		if (!$check_acl_table){
		    //error_log('нет такой записи, добавляем '.$subject_type.' '.$subject_id.' для :'.$object_id);
		    $data=array('subject_id'=>$subject_id, 'subject_type'=>$subject_type, 'object_type'=>'post', 'object_id'=>$object_id);
		    $format=array('%d','%s', '%s', '%d');
		    $result = $wpdb->insert($table_name, $data, $format);    
		}
		else {
		    //error_log('есть такая запись. обновляем:'.$object_id);
		    $data=array('subject_id'=>$subject_id, 'subject_type'=>$subject_type, 'object_type'=>'post');
		    $format=array('%d','%s', '%s');
			$where=array('object_id'=>$object_id);
			$where_format=array('%d');
		    $result = $wpdb->update($table_name, $data, $format, $where, $where_format);
		}

	    //$wpdb->show_errors();
		//$wpdb->print_error();
		return $result;
	}

    function update_acl_cp($post_id){

        $users_ids = apply_filters( 'acl_users_list', array(), $post_id );

        $users_ids = array_unique($users_ids);

        foreach ($users_ids as $user_id) {
            $this->add_acl_cp ('user', 'post', $user_id, $post_id);
        }
    }
	
	function get_acl_cp($subject_type, $object_type, $object_id) {
	    global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		$sql = $wpdb->prepare("SELECT subject_id FROM $table_name  WHERE object_type=%s AND subject_type=%s AND object_id=%d",$object_type, $subject_type, $object_id);
		$subjects_ids = $wpdb->get_results($sql);
		
		return $subjects_ids;
	}
	
	function del_acl_cp($subject_type, $object_type, $subject_id, $object_id) {
	    global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		$result=0;
		// проверим, если такая запись есть то удалим
		$check_acl_table=$this->check_acl_cp($subject_type, $object_type, $subject_id, $object_id);
		//error_log($check_acl_table);
		if ($check_acl_table) {
		    //error_log('есть такая запись, удаляем');
		    $sql = $wpdb->prepare("DELETE FROM $table_name WHERE object_id =%d AND subject_type=%s AND object_type=%s AND subject_id=%d", $object_id, $subject_type, $object_type, $subject_id);
	        $result = $wpdb->query($sql);
            //$wpdb->show_errors();
		    //$wpdb->print_error();
            
        }
		return $result;	
		
	}
	
	function check_acl_cp ($subject_type, $object_type, $subject_id, $object_id) {
	    // проверяем есть ли уже в таблице такая запись 
		global $wpdb;
		$table_name = $wpdb->prefix . "acl";
		$subjects_ids = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name  WHERE object_type=%s AND subject_type=%s AND object_id=%d AND subject_id=%s",$object_type, $subject_type, $object_id, $subject_id));
		//$subjects_ids = $wpdb->get_results($sql);
		
		return $subjects_ids;
	}
	
	
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
		<fieldset id='users_access'>
			<legend><b>Доступ для отдельных пользователей</b></legend>
            <label for='acl_users_read'>Пользователи:</label>
			<br />
			<input id='acl_users_read' name='acl_users_read' class='select2field' />
			<br />
        </fieldset>
		<br />
		<fieldset id='groups_access'>
            <legend><b>Доступ для групп</b></legend>
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
			$acl_groups_read_table = $this->get_acl_cp('group', 'post', $post->ID);
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
			
			//$acl_users_read = get_post_meta($post->ID, 'acl_users_read');
			// read users from table
			$acl_users_read_table = $this->get_acl_cp('user', 'post', $post->ID);
			if ($acl_users_read_table){
			    $acl_users_read_table_array=array();
			    foreach ($acl_users_read_table as $acl_user_read_table) {
				    $usrid=$acl_user_read_table->subject_id;
					//error_log('пользователь которому доступна запись='.$post->ID.':'.$usrid);
					$acl_users_read_table_array[]=$usrid;
					//error_log('пользователь которому доступна запись='.$post->ID.':'.$usrid);
				}
			}
			//error_log($acl_users_read_table);
			//print_r($acl_users_read);
			//print_r($acl_users_read_table_array);
		
		//ВНИМАНИЕ!!! перезададим переменные с пользователями из таблицы для проверки
		$acl_users_read=$acl_users_read_table_array;
		$acl_groups_read=$acl_group_read_table_array;
		
			
            foreach ($query->posts as $post_id){
                if(!empty($post_id)){
					echo "<div class='list_checkbox'><label for='acl_groups_".$post_id."'><input type='checkbox' ".(in_array($post_id, $acl_groups_read) ? "checked='checked'" : '')." name='acl_groups_read[]' id='acl_groups_".$post_id."' value='".$post_id."' />".get_the_title($post_id)."</label></div>";
				}
            }

			?>
			<br />
        </fieldset>
		 <script>
                jQuery(document).ready(function($) {
                    update_users_data();
                    $("#acl_users_read").select2({
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
                                jQuery('#acl_users_read').select2('data',  acl);
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
	
	function del_acl_cps($acl_group_id){
		//ПРИ ОГРОМНОМ КОЛ-ВЕ ПОСТОВ МОЖЕТ ВЫЗВАТЬ ЗАМЕДЛЕНИЕ РАБОТЫ! РЕШЕНИЕ ПЕРЕДЕЛАТЬ ЧЕРЕЗ SQL
		if (get_post_type( $acl_group_id ) != 'user_group') return;
		$all_posts = get_posts("numberposts=-1&fields=ids&post_type=any");
		foreach($all_posts as $single_post){
			delete_post_meta($single_post, 'acl_groups_read', $acl_group_id);
			$this->del_acl_cp('group', 'post', $acl_group_id, $single_post);
		}
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
		    //error_log('глянем что у нас в реквесте>>>>>'.$_REQUEST['post_id']);
            //$ids = get_post_meta( $_REQUEST['post_id'], 'acl_users_read');
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

    function get_acl_groups_for_post_callback() {
        $ids = array();
        $elements = array();

        if (isset($_REQUEST['post_id']))
            //$ids = get_post_meta( $_REQUEST['post_id'], 'acl_groups_read');
			//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
			$ids_from_table = $this->get_acl_cp('group', 'post', $_REQUEST['post_id']);
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
    
    function save_acl_post_callback($post_id){
				
        // не происходит ли автосохранение? 
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
            return; 
        // не ревизию ли сохраняем? 
        if (wp_is_post_revision($post_id)) 
            return; 
		// нужный ли тип поста?	
		//$screens = array( 'report', 'cases', 'post', 'document', 'forum' );
		$screens = get_option( 'cp_acl_posts_types' );
		if ( !in_array($_REQUEST['post_type'], $screens) )
			return;

        $acl_users_read = explode(',', trim($_REQUEST['acl_users_read']));
        $acl_users_read[] = get_current_user_id();
        $old_acl_users_read = get_post_meta($post_id, 'acl_users_read');

        if($acl_users_read != $old_acl_users_read){
            delete_post_meta($post_id, 'acl_users_read');
        }

        foreach ( $acl_users_read as $user_id ) {
            if (!(in_array($user_id, $old_acl_users_read)) && !empty($user_id)){
                add_post_meta($post_id, 'acl_users_read', $user_id);
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
    
    function add_field_to_submitbox() {
        global $post;
		if($post->post_type == 'user_group') return;
        ?>
        <div class='misc-pub-section'>
            <span id="acl">Доступ: </span>
            <a href='#TB_inline?width=600&height=550&inlineId=acl_form' class="thickbox">Настроить</a>
        </div>
        <div id='acl_form' style='display:none;'>
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
                                $("#save_acl_post").after("<br /><strong>Сохранено</strong>");
                                $("#TB_overlay").click();
                            }                                
                        });
                    });

                })(jQuery);   
            </script>
            
            <fieldset id='users_access'>
                <legend><h1>Доступ пользователей</h1></legend>
                <br />
                <label for='acl_users_read'>На чтение</label><br /><input id='acl_users_read' name='acl_users_read' class='select2field' /><br />
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
								console.log(acl);
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
                <input id='acl_groups_read' name='acl_groups_read' class='select2field' /><br />
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
								console.log(acl);
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