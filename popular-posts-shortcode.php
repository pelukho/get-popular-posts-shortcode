<?php
/*
Plugin Name: Popular posts shortcode
Description: Вывод популярных постов по шорткоду
Version: 1.0
Author: Pelukho
*/

if( !defined( 'ABSPATH' )) exit;

add_action('admin_menu', 'pss_options');
add_action( 'admin_init', 'pss_option_settings' );

$pss_page = 'pss_parameters.php'; 
function pss_options() {
	global $pss_page;
	add_menu_page('Настройки плагина', 'Найстройки плагина', 'administrator', $pss_page, 'pss_option_page'); 
}		 

add_action('admin_head', 'pss_scripts', 50); 
function pss_scripts() {
	echo '
	<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet" />
	<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
	<style>
		.select2-container {
			min-width: 300px;
		}
	</style>
	<script>
		jQuery(document).ready(function() {
		    jQuery("#my_select").select2();
		    if(jQuery("input[type=\'radio\']").val() == "some"){		    		
	    		jQuery(".selected").text("Только: ");	    		
	    	} else {
	    		jQuery(".selected").text("Кроме: ");	    		
	    	}
		    jQuery("input[type=\'radio\']").change( function(){		    					
		    	if(jQuery(this).val() == "some"){		    		
		    		jQuery(".selected").text("Только: ");
		    		
		    	} else {
		    		jQuery(".selected").text("Кроме: ");
		    		
		    	}
		    });
		});
	</script>';

}


function pss_option_page(){
	global $pss_page;
	?><div class="wrap">
		<h2>Дополнительные параметры сайта</h2>
		<form method="post" enctype="multipart/form-data" action="options.php">
			<?php 
			settings_fields('pss_options'); 
			do_settings_sections($pss_page);
			?>
			<p class="submit">  
				<input type="submit" class="button-primary" value="Сохранить" />  
			</p>
		</form>
	</div><?php
}
 
function pss_option_settings() {
	$arr = [];
	$query = new WP_Query( array('post_type' => 'post'));
	if( $query->have_posts() ){
		while( $query->have_posts() ){
			$query->the_post();
			$arr[get_the_ID()] = get_the_title();
		}
	}
	global $pss_page;

	register_setting( 'pss_options', 'pss_options', 'pss_validate_settings' );  
	add_settings_section( 'pss_section_2', '', '', $pss_page );	
 
	$pss_field_params = array(
		'type'      => 'radio',
		'id'      => 'my_radio',
		'vals'		=> array( 'all' => 'Все', 'some' => 'Избранные')
	);
	add_settings_field( 'my_radio', 'Выберите какие посты могут быть популярные', 'pss_option_display_settings', $pss_page, 'pss_section_2', $pss_field_params );

	$options = get_option('pss_options');
		
		$pss_field_params = array(
			'type'      => 'select',
			'id'        => 'my_select',		
			'vals'		=> $arr
		);
		add_settings_field( 'my_select_field', '<span class="selected"></span>', 'pss_option_display_settings', $pss_page, 'pss_section_2', $pss_field_params ); 
}		
 
function pss_option_display_settings($args) {
	extract( $args );
 
	$option_name = 'pss_options';
 
	$o = get_option( $option_name );
 
	switch ( $type ) {  		
		case 'radio':
			echo "<fieldset>";
			foreach($vals as $v=>$l){
				$checked = ($o[$id] == $v) ? "checked='checked'" : '';  
				echo "<label><input type='radio' name='" . $option_name . "[$id]' value='$v' $checked />$l</label><br />";
			}
			echo "</fieldset>";  
		break; 
		case 'select':
			echo "<select multiple='multiple' id='$id' name='" . $option_name . "[$id][]'>";
			foreach($vals as $v=>$l){				
				if( isset( $o[$id] ) && !empty( $o[$id] )){
					if( in_array($v, $o[$id]) ){
						$selected = "selected='selected'";
					} else {
						$selected = '';		
					}
				}			
				echo "<option value='$v' $selected>$l</option>";				
			}			
			echo "</select>";  
		break;
	}
}

function post_count_views() {

	$meta_key       = 'count_views';  
	$who_count      = 1;            
	$exclude_bots   = 1;            

	global $user_ID, $post;
		if( is_singular() ) {

			$id = (int)$post->ID;		
			static $post_views = false;
			if( $post_views ) return true; 
			$post_views = (int)get_post_meta( $id,$meta_key, true );
			$should_count = false;
			switch( (int)$who_count ) {
				case 0: $should_count = true;
					break;
				case 1:
					if( (int)$user_ID == 0 )
						$should_count = true;
					break;
				case 2:
					if( (int)$user_ID > 0 )
						$should_count = true;
					break;
			}
			if( (int)$exclude_bots == 1 && $should_count ){
				$useragent = $_SERVER['HTTP_USER_AGENT'];
				$notbot = "Mozilla|Opera"; 
				$bot = "Bot/|robot|Slurp/|yahoo";
				if ( !preg_match("/$notbot/i", $useragent) || preg_match("!$bot!i", $useragent) )
					$should_count = false;
			}

			if( $should_count )
				if( !update_post_meta( $id, $meta_key, ( $post_views+1 ) ) ) add_post_meta( $id, $meta_key, 1, true );
		}
		return true;
}
add_action('wp_head', 'post_count_views');

function pss_shortcode_func(){

	$options = get_option('pss_options');
	
	if( $options['my_radio'] == 'all' ){
		$sc_query = new WP_Query( 
			array( 
				'post_type'=>'post', 
				'post__not_in' => $options['my_select'], 
				'orderby' => 'count_views', 
				'posts_per_page' => 3
			) 
		);
	} elseif ($options['my_radio'] == 'some') {
		$sc_query = new WP_Query( 
			array( 
				'post_type'=>'post', 
				'post__in' => $options['my_select'], 
				'orderby' => 'count_views',
				'posts_per_page' => 3
			)  
		);
	} else {
		$sc_query = new WP_Query( 
			array( 
				'post_type'=>'post', 
				'orderby' => 'count_views',
				'posts_per_page' => 3
			)  
		);
	}
	ob_start();
	if( $sc_query->have_posts() ){
		echo "<h2>Список популярных постов</h2>";
		echo "<ul>";
		while( $sc_query->have_posts() ){
			$sc_query->the_post();
			echo "<li><a href=". get_the_permalink() .">". 
			get_the_post_thumbnail( '', array( 50, 50) ) .
			get_the_title() .
			"</a><p>". get_the_excerpt() ."</p></li>";
		}
		echo "</ul>";
	}


	return ob_get_clean();
}
add_shortcode('pss_shortcode', 'pss_shortcode_func');