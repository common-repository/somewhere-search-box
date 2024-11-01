<?php
/*
 Plugin Name: Somewhere search box
Plugin URI: https://elearn.jp/wpman/column/somewhere-search-box.html
Description: Search box widget add to the admin post editor.
Author: tmatsuur
Version: 1.4.0
Author URI: https://12net.jp/
*/

/*
 Copyright (C) 2012-2021 tmatsuur (Email: takenori dot matsuura at 12net dot jp)
This program is licensed under the GNU GPL Version 2.
*/

define( 'SOMEWHERE_SEARCH_BOX_DOMAIN', 'somewhere-search-box' );
define( 'SOMEWHERE_SEARCH_BOX_DB_VERSION_NAME', 'somewhere-search-box-db-version' );
define( 'SOMEWHERE_SEARCH_BOX_DB_VERSION', '1.4.0' );

class somewhere_search_box {
	private $post_type;
	/**
	 * Plugin initialize.
	 */
	public function __construct() {
		global $pagenow;
		register_activation_hook( __FILE__ , array( &$this , 'init' ) );
		if ( in_array( $pagenow, array( 'index.php', 'post.php', 'post-new.php' ) ) ) {
			add_action( 'admin_init', array( &$this, 'setup' ) );
			add_action( 'admin_footer', array( &$this, 'footer' ) );
			if ( in_array( $pagenow, array( 'post-new.php' ) ) ) {
				add_filter( 'default_content', array( &$this, 'default_content' ), 10, 2 );
			}
		} else if ( in_array( $pagenow, array( 'edit.php' ) ) ) {
			add_filter( 'post_row_actions', array( &$this, 'post_row_actions' ), 10, 2 );
			add_filter( 'page_row_actions', array( &$this, 'page_row_actions' ), 10, 2 );
		}
		if ( in_array( $pagenow, array( 'index.php', 'edit.php', 'post.php', 'post-new.php') ) ) {
			load_plugin_textdomain( SOMEWHERE_SEARCH_BOX_DOMAIN, false, plugin_basename( dirname( __FILE__ ) ).'/languages' );
		}
		add_filter( 'posts_where', array( &$this, 'allow_partial_match_title' ), 10, 2 );
	}

	/**
	 * Plugin activation.
	 */
	public function init() {
		if ( get_option( SOMEWHERE_SEARCH_BOX_DB_VERSION_NAME ) != SOMEWHERE_SEARCH_BOX_DB_VERSION ) {
			update_option( SOMEWHERE_SEARCH_BOX_DB_VERSION_NAME, SOMEWHERE_SEARCH_BOX_DB_VERSION );
		}
	}
	/**
	 * Post search box.
	 */
	public function setup() {
		global $pagenow;
		$_title = __( 'Search Posts' );
		$this->post_type = '';
		if ( in_array( $pagenow, array( 'index.php' ) ) ) {
			add_meta_box( 'meta_box_somewhere_search_box', $_title, array( &$this, 'meta_box' ),
				'dashboard', 'side', 'high' );
		} else {
			if ( isset( $_GET['post_type'] ) ) {
				$this->post_type = $_GET['post_type'];
			} elseif ( isset( $_GET['post'] ) ) {
				$_post = get_post( $_GET['post'] );
				if ( isset( $_post->post_type ) ) {
					$this->post_type = $_post->post_type;
				}
			}
			if ( $this->post_type != '' ) {
				$_title = get_post_type_object( $this->post_type )->labels->search_items;
			}
			add_meta_box( 'meta_box_somewhere_search_box', $_title, array( &$this, 'meta_box' ),
				$this->post_type != ''? $this->post_type: 'post', 'side', 'high' );
		}
	}
	/**
	 * Post search box fields.
	 */
	public function meta_box( $args ) {
?>
<style>
#meta_box_somewhere_search_box fieldset label {
	display: inline-block;
	margin: 0.15rem 0 0.3rem 0;
}
</style>
<fieldset>
<label>
<input type="text" id="somewhere-search-input" value="" size="24" style="width: 100%;" />
</label>
&nbsp;<label>
<select id="somewhere-search-post-type">
<?php foreach ( get_post_types( array( 'show_ui'=>true ), 'objects' ) as $post_type ) { ?>
<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $this->post_type == $post_type->name ); ?>><?php _e( $post_type->labels->name ); ?></option>
<?php } ?>
</select>
</label>
<?php if ( $this->_is_wp_version( '4.4', '>=' ) ) { ?>
&nbsp;<label for="somewhere-search-title-only" style="white-space: nowrap; line-height: 1.75rem;"><input type="checkbox" id="somewhere-search-title-only" value="1" /><?php _e( 'Search to post title', SOMEWHERE_SEARCH_BOX_DOMAIN ); ?></label>&nbsp;
<?php } ?>
<button class="button" id="somewhere-search-submit"><?php _e( 'Search' ); ?></button>
</fieldset>
<?php
	}
	/**
	 * Add admin footer scripts.
	 */
	public function footer() {
		global $post, $pagenow;
		$edit_post_link = '';
		$add_button_class = $this->_is_wp_version( '4.7', '>=' )? 'page-title-action': 'button';
		if ( isset( $post->post_status ) && $post->post_status != 'auto-draft' ) {
			$post_type_object = get_post_type_object( $post->post_type );
			if ( current_user_can( $post_type_object->cap->create_posts ) &&
				in_array( $post->post_status, array( 'publish', 'future', 'private' ) ) ) {
				$edit_post_link .= '&nbsp;'.$this->_get_replicate_action_link( $post, $add_button_class );
			}
			$prev_post = get_previous_post();
			if ( isset( $prev_post->ID ) ) {
				$title = trim( $prev_post->post_title ) != ''? $prev_post->post_title: 'ID:'.$prev_post->ID;
				$edit_post_link .= '&nbsp;<a href="?post='.intval( $prev_post->ID ).'&action=edit" title="'.esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ) .'" class="' . $add_button_class . '">'.__( '&laquo; Previous' ).'</a>';
			}
			$next_post = get_next_post();
			if ( isset( $next_post->ID ) ) {
				$title = trim( $next_post->post_title ) != ''? $next_post->post_title: 'ID:'.$next_post->ID;
				$edit_post_link .= '&nbsp;<a href="?post='.intval( $next_post->ID ).'&action=edit" title="'.esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ).'" class="' . $add_button_class . '">'.__( 'Next &raquo;' ).'</a>';
			}
		}
?>
<script type="text/javascript">
( function ( $ ) {
	$(document).ready( function () {
<?php if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && $edit_post_link != '' ) { ?>
		$( '.add-new-h2' ).each( function () {
			$(this).removeClass( 'add-new-h2' ).addClass( 'button' ).parent().addClass( 'wp-core-ui' );
			$(this).after( '<?php echo $edit_post_link; ?>' );
		} );
		$( 'a.page-title-action' ).each( function () {
			$(this).after( '<?php echo $edit_post_link; ?>' );
		} );
<?php } ?>
		$( '#somewhere-search-input' ).on( 'keypress', function ( e ) {
			if ( e.which == 13 ) {
				$( '#somewhere-search-submit' ).trigger( 'click' );
				return false;
			}
		} );
		$( '#somewhere-search-submit' ).on( 'click', function () {
			let url = '<?php echo admin_url( 'edit.php' ); ?>';
<?php if ( $this->_is_wp_version( '5.7.0', '>=' ) ) { ?>
			let post_search_input = $( '#somewhere-search-input' ).val().trim();
<?php } else { ?>
			let post_search_input = $.trim( $( '#somewhere-search-input' ).val() );
<?php } ?>
			if ( post_search_input != '' ) {
				url += '?s=' + encodeURI( post_search_input );
				if ( $( '#somewhere-search-title-only' ).prop( 'checked' ) ) {
					url += '&sentence=title_only';
				}
<?php if ( $this->_is_wp_version( '5.7.0', '>=' ) ) { ?>
				let post_type_selected = $( '#somewhere-search-post-type' ).val().trim();
<?php } else { ?>
				let post_type_selected = $.trim( $( '#somewhere-search-post-type' ).val() );
<?php } ?>
				if ( post_type_selected != 'post' ) {
					url += '&post_type=' + encodeURI( post_type_selected );
				}
				location.href = url;
			}
			return false;
		} );
	} );
} )( jQuery );
</script>
<?php
	}
	/**
	 * Replicate the post content, terms and meta values.
	 *
	 * @since 1.2.0
	 *
	 * @param string  $post_content Default post content.
	 * @param WP_Post $post         Post object.
	 * @return string Replicate post content
	 */
	public function default_content( $post_content, $post ) {
		if ( !empty( $_GET[ 'replicate' ] ) ) {
			$replicate_post = get_post( absint( $_GET[ 'replicate' ] ) );
			if ( $replicate_post instanceof WP_Post ) {
				$post_content = $replicate_post->post_content;
				if ( $post instanceof WP_Post ) {
					// Copy taxonomies.
					$taxonomies = get_object_taxonomies( $replicate_post->post_type );
					if ( is_array( $taxonomies ) && count( $taxonomies ) > 0 ) {
						foreach ( $taxonomies as $taxonomy ) {
							if ( $taxonomy == 'post_format' ) {
								if ( current_theme_supports( 'post-formats' ) ) {
									set_post_format( $post, get_post_format( $replicate_post ) );
								}
							} else {
								// Copy terms.
								$terms = wp_get_object_terms( $replicate_post->ID, $taxonomy, array( 'fields'  => 'ids' ) );
								if ( is_array( $terms ) ) foreach ( $terms as $term ) {
									wp_set_object_terms( $post->ID, $terms, $taxonomy );
								}
							}
						}
					}
					// Copy meta values.
					$postmetas = get_post_meta( $replicate_post->ID );
					if ( is_array( $postmetas ) ) foreach ( $postmetas as $_key=>$_values ) {
						if ( !is_protected_meta( $_key ) ) {
							foreach ( $_values as $_value ) {
								add_post_meta( $post->ID, $_key, maybe_unserialize( $_value ) );
							}
						}
					}
				}
			}
		}
		return $post_content;
	}
	/**
	 * Insert 'Replicate' to the array of row action links on the Posts list table.
	 *
	 * @since 1.2.0
	 *
	 * @param array $actions An array of row action links.
	 * @param WP_Post $post The post object.
	 * @return array An array of row action links
	 */
	public function post_row_actions( $actions, $post ) {
		$can_edit = current_user_can( 'edit_post', $post->ID );
		if ( $can_edit && 'trash' != $post->post_status ) {
			$actions = $this->_insert_replicate_action( $actions, $this->_get_replicate_action_link( $post ) );
		}
		return $actions;
	}
	/**
	 * Insert 'Replicate' to the array of row action links on the Pages list table.
	 *
	 * @since 1.2.0
	 *
	 * @param array $actions An array of row action links.
	 * @param WP_Post $post The post object.
	 * @return array An array of row action links
	 */
	public function page_row_actions( $actions, $post ) {
		$can_edit = current_user_can( 'edit_pages', $post->ID );
		if ( $can_edit && 'trash' != $post->post_status ) {
			$actions = $this->_insert_replicate_action( $actions, $this->_get_replicate_action_link( $post ) );
		}
		return $actions;
	}

	/**
	 * Allow partial match search of post_title.
	 *
	 * @since 1.3.1
	 *
	 * @see 'posts_where' filter.
	 *
	 * @param string $where The WHERE clause of the query.
	 * @param WP_Query $current_wp_query The WP_Query instance (passed by reference).
	 * @return string The WHERE clause of the query.
	 */
	public function allow_partial_match_title( $where, $current_wp_query ) {
		if ( $current_wp_query instanceof WP_Query && ! $current_wp_query->is_preview &&
			!empty( $current_wp_query->get( 's' ) ) &&
			in_array( 'title_only', explode( ',', $current_wp_query->get( 'sentence' ) ) ) ) {
			global $wpdb;
			if ( $this->_is_wp_version( '4.7.0', '>=' ) ) {
				$excerpt = $wpdb->prepare( " OR ({$wpdb->posts}.post_excerpt LIKE %s)", '%'.$wpdb->esc_like( $current_wp_query->query_vars['s'] ).'%' );
				$where = str_replace( $excerpt, '', $where );
				$content = $wpdb->prepare( " OR ({$wpdb->posts}.post_content LIKE %s)", '%'.$wpdb->esc_like( $current_wp_query->query_vars['s'] ).'%' );
				$where = str_replace( $content, '', $where );
			} else {
				$search = $wpdb->prepare( " AND {$wpdb->posts}.post_title = %s", stripslashes( $current_wp_query->query_vars['s'] ) );
				$replace = $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", '%'.$wpdb->esc_like( $current_wp_query->query_vars['s'] ).'%');
				$where = str_replace( $search, $replace, $where );
			}
		}
		return $where;
	}

	/**
	 * Retrieve  'Replicate' action links.
	 *
	 * @since 1.2.0
	 *
	 * @access private.
	 *
	 * @param WP_Post $post The post object.
	 * @return string The link of replicate action.
	 */
	private function _get_replicate_action_link( $post, $class='' ) {
		$param_type = ( $post->post_type != 'post' )? 'post_type='.esc_attr( $post->post_type ).'&': '';
		$attr_class = ( $class == '' )? '': 'class="'.esc_attr( $class ).'"';
		$more_args = '';
		if ( apply_filters( 'replace_editor', false, $post ) === false &&
			! ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post ) ) ) {
			$more_args = '&classic-editor';
		}
		return  '<a href="post-new.php?' . $param_type . 'replicate=' . intval( $post->ID ) . $more_args . '" title="' . esc_attr__( 'Replicate this item', SOMEWHERE_SEARCH_BOX_DOMAIN ) . '" '. $attr_class .'>'.__( 'Replicate', SOMEWHERE_SEARCH_BOX_DOMAIN ).'</a>';
	}
	/**
	 * Insert 'Replicate' to the array of row action links.
	 *
	 * @since 1.2.0
	 *
	 * @access private.
	 *
	 * @param array $actions An array of row action links.
	 * @param string $action_replicate The link of replicate action.
	 * @return array An array of row action links
	 */
	private function _insert_replicate_action( $actions, $action_replicate ) {
		$_actions = array();
		foreach ( $actions as $name=>$link ) {
			$_actions[$name] = $link;
			if ( $name == 'edit' ) $_actions['replicate'] = $action_replicate;
		}
		if ( !isset( $_actions['replicate'] ) ) $_actions['replicate'] = $action_replicate;
		return $_actions;
	}

	/**
	 * Compares WordPress version number strings.
	 *
	 * @since 1.3.0
	 *
	 * @access private.
	 *
	 * @global string $wp_version
	 *
	 * @param string $version Version number.
	 * @param string $compare Operator. Default is '>='.
	 * @return bool.
	 */
	private function _is_wp_version( $version, $compare = '>=' ) {
		return version_compare( $GLOBALS['wp_version'], $version, $compare );
	}
}
$plugin_somewhere_search_box = new somewhere_search_box();