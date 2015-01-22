<?php

namespace WidgetFavorites;

class Ajax_API {

	const AJAX_ACTION = 'widget_favorites_sync';

	/**
	 * @var Plugin
	 */
	public $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_sync' ) );
	}

	/**
	 * Callback for the WP Ajax action. Calls wp_send_json_success() and wp_send_json_error()
	 */
	public function ajax_sync() {
		try {
			if ( ! check_ajax_referer( 'widget_favorites', 'nonce', false ) ) {
				throw new Public_Exception( 'bad nonce', 403 );
			}
			if ( ! current_user_can( $this->plugin->config['capability'] ) ) {
				throw new Public_Exception( 'unauthorized', 403 );
			}
			if ( empty( $_REQUEST['method'] ) ) { // wpcs: input var okay
				throw new Public_Exception( 'bad method param', 400 );
			}
			$method = sanitize_key( $_REQUEST['method'] ); // wpcs: input var okay
			$params = wp_unslash( $_REQUEST ); // wpcs: input var okay

			$result = null;
			$params['check_capabilities'] = true;
			if ( 'read' === $method ) {
				$result = $this->read( $params );
			} else if ( 'create' === $method ) {
				$result = $this->create( $params );
			} else if ( 'update' === $method ) {
				$result = $this->update( $params );
			} else if ( 'delete' === $method ) {
				$result = $this->delete( $params );
			} else {
				throw new Public_Exception( 'method not supported: ' . $method, 403 );
			}

			wp_send_json_success( $result );
		} catch ( Exception $e ) {
			$status_code = 500;
			$message = 'Exception';
			if ( $e instanceof Public_Exception || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
				$message = $e->getMessage();
				if ( $e->getCode() >= 400 && $e->getCode() < 600 ) {
					$status_code = $e->getCode();
				}
			}
			status_header( $status_code );
			wp_send_json_error( $message );
		}
	}

	/**
	 * @param array $params
	 *
	 * @return array
	 * @throws Public_Exception
	 */
	public function validate_params( $params ) {
		$sanitized_params = array();
		if ( array_key_exists( 'post_id', $params ) ) {
			$params['post_id'] = intval( $params['post_id'] );
			if ( $params['post_id'] < 0 ) {
				throw new Public_Exception( 'post_id cannot be less than 0', 400 );
			} else if ( $params['post_id'] > 0 ) {
				$post = get_post( $params['post_id'] );
				if ( empty( $post ) ) {
					throw new Public_Exception( 'post does not exist', 404 );
				}
				if ( Plugin::POST_TYPE !== $post->post_type ) {
					throw new Public_Exception( 'bad post type for queried post', 400 );
				}
			}
			$sanitized_params['post_id'] = $params['post_id'];
		}

		if ( array_key_exists( 'id_base', $params ) ) {
			$sanitized_params['id_base'] = sanitize_text_field( $params['id_base'] );
		}

		if ( array_key_exists( 'name', $params ) ) {
			$sanitized_params['name'] = sanitize_text_field( $params['name'] );
		}

		if ( array_key_exists( 'src_widget_id', $params ) ) {
			$params['src_widget_id'] = sanitize_text_field( $params['src_widget_id'] );
			$parsed_widget_id = $this->plugin->get_customize_manager()->widgets->parse_widget_id( $params['src_widget_id'] );
			if ( empty( $parsed_widget_id ) ) {
				throw new Public_Exception( 'malformed src_widget_id', 400 );
			}
			$sanitized_params['src_widget_id'] = $params['src_widget_id'];
		}

		if ( array_key_exists( 'author_id', $params ) ) {
			$params['author_id'] = intval( $params['author_id'] );
			if ( $params['author_id'] < 0 ) {
				throw new Public_Exception( 'author cannot be less than 0', 400 );
			}
			$sanitized_params['author_id'] = $params['author_id'];
		}

		if ( array_key_exists( 'posts_per_page', $params ) ) {
			$params['posts_per_page'] = intval( $params['posts_per_page'] );
			if ( $params['posts_per_page'] < 0 ) {
				throw new Public_Exception( 'posts_per_page cannot be less than 0', 400 );
			}
			if ( $params['posts_per_page'] >= 100 ) {
				throw new Public_Exception( 'posts_per_page cannot be 100 or greater', 400 );
			}
			$sanitized_params['posts_per_page'] = $params['posts_per_page'];
		}

		if ( array_key_exists( 'paged', $params ) ) {
			$params['paged'] = intval( $params['paged'] );
			if ( $params['paged'] < 0 ) {
				throw new Public_Exception( 'paged cannot be less than 0', 400 );
			}
			$sanitized_params['paged'] = $params['paged'];
		}

		if ( array_key_exists( 'sanitized_widget_setting', $params ) ) {
			if ( empty( $params['sanitized_widget_setting'] ) ) {
				throw new Public_Exception( 'empty sanitized_widget_setting', 400 );
			}
			if ( is_string( $params['sanitized_widget_setting'] ) ) {
				$params['sanitized_widget_setting'] = json_decode( $params['sanitized_widget_setting'], true );
				if ( is_null( $params['sanitized_widget_setting'] ) ) {
					throw new Public_Exception( 'sanitized_widget_setting json error' );
				}
			}
			$params['sanitized_widget_setting'] = $this->plugin->get_customize_manager()->widgets->sanitize_widget_instance( $params['sanitized_widget_setting'] );
			if ( is_null( $params['sanitized_widget_setting'] ) ) {
				throw new Public_Exception( 'bad sanitized_widget_setting', 400 );
			}
			$sanitized_params['sanitized_widget_setting'] = $params['sanitized_widget_setting'];
		}

		if ( ! array_key_exists( 'check_capabilities', $params ) ) {
			$sanitized_params['check_capabilities'] = false;
		} else {
			$sanitized_params['check_capabilities'] = $params['check_capabilities'];
		}

		return $sanitized_params;
	}

	/**
	 * GET
	 *
	 * @param array $params
	 *
	 * @throws Public_Exception
	 * @return mixed
	 */
	public function read( $params ) {
		$params = $this->validate_params( $params );
		if ( empty( $params['post_id'] ) && empty( $params['id_base'] ) ) {
			throw new Public_Exception( 'Missing id_base and post_id param; either one must be supplied', 400 );
		}
		if ( $params['check_capabilities'] && ! current_user_can( $this->plugin->get_post_type_object()->cap->read_post ) ) {
			throw new Public_Exception( 'unauthorized', 403 );
		}

		$default_params = array(
			'post_id' => 0,
			'id_base' => '',
			'author_id' => 0,
			'posts_per_page' => 50,
			'paged' => 1,
		);
		$params = array_merge( $default_params, $params );
		$params = wp_array_slice_assoc( $params, array_keys( $default_params ) );

		if ( $params['id_base'] ) {
			$posts_where_filter = function ( $sql ) use ( $params ) {
				/**
				 * @var \WPDB $wpdb
				 */
				global $wpdb;
				return $sql . $wpdb->prepare( " AND $wpdb->posts.post_name LIKE %s", $wpdb->esc_like( $params['id_base'] ) . '-%' );
			};
		}

		$is_collection = empty( $params['post_id'] );
		if ( ! $is_collection && $params['paged'] > 1 ) {
			throw new Public_Exception( 'Cannot supply paged if not asking for collection.', 400 );
		}

		$query_vars = array(
			'post_type' => Plugin::POST_TYPE,
			'posts_per_page' => $params['posts_per_page'],
			'paged' => $params['paged'],
		);

		if ( isset( $posts_where_filter ) ) {
			add_filter( 'posts_where', $posts_where_filter );
		}
		$query = new \WP_Query( $query_vars );
		if ( isset( $posts_where_filter ) ) {
			remove_filter( 'posts_where', $posts_where_filter );
		}

		if ( $is_collection ) {
			$response = array();
			foreach ( $query->posts as $post ) {
				$response[] = $this->plugin->get_exported_data_from_post( $post );
			}
		} else {
			$response = null;
			if ( count( $query->posts ) ) {
				$response = $this->plugin->get_exported_data_from_post( $query->posts[0] );
			}
		}

		return $response;
	}

	/**
	 * PUT
	 *
	 * @param array $params
	 * @throws Exception
	 * @throws Public_Exception
	 * @returns array
	 */
	public function update( $params ) {
		$params = $this->validate_params( $params );
		if ( empty( $params['post_id'] ) ) {
			throw new Public_Exception( 'Must supply post_id for an update request.', 400 );
		}
		if ( $params['check_capabilities'] && ! current_user_can( $this->plugin->get_post_type_object()->cap->edit_post, $params['post_id'] ) ) {
			throw new Public_Exception( 'unauthorized', 403 );
		}
		return $this->save( $params );
	}

	/**
	 * POST
	 *
	 * @param array $params
	 * @throws Exception
	 * @throws Public_Exception
	 * @returns array
	 */
	public function create( $params ) {
		$params = $this->validate_params( $params );
		if ( ! empty( $params['post_id'] ) ) {
			throw new Public_Exception( 'Must not supply post_id for a create request.', 400 );
		}
		if ( $params['check_capabilities'] && ! current_user_can( $this->plugin->get_post_type_object()->cap->create_posts ) ) {
			throw new Public_Exception( 'unauthorized', 403 );
		}
		return $this->save( $params );
	}

	/**
	 * POST/PUT
	 *
	 * @param array $params
	 *
	 * @throws Exception
	 * @throws Public_Exception
	 * @returns array
	 */
	protected function save( $params ) {
		if ( $params['check_capabilities'] && ! current_user_can( $this->plugin->get_post_type_object()->cap->edit_posts ) ) {
			throw new Public_Exception( 'unauthorized', 403 );
		}

		if ( empty( $params['src_widget_id'] ) ) {
			throw new Public_Exception( 'missing param: src_widget_id', 400 );
		}
		if ( ! empty( $params['author_id'] ) ) {
			// @todo allow if editor?
			throw new Public_Exception( 'illegal param: cannot set the author', 403 );
		}
		if ( ! array_key_exists( 'sanitized_widget_setting', $params ) ) {
			throw new Public_Exception( 'must supply sanitized_widget_setting', 403 );
		}

		$postarr = array(
			'ID' => isset( $params['post_id'] ) ? $params['post_id'] : null,
			'post_name' => $params['src_widget_id'],
			'post_type' => Plugin::POST_TYPE,
			'post_status' => 'publish',
			'post_title' => isset( $params['name'] ) ? $params['name'] : null,
			'post_content' => base64_encode( serialize( $params['sanitized_widget_setting'] ) ), // using base64_encode to prevent de-serialization errors
		);

		// Prevent special characters from becoming HTML entities. The VIP Co-Schedule plugin removes this filter.
		$filter_suspension = new Filter_Suspension( array(
			array( 'title_save_pre', 'wp_filter_kses' ),
			array( 'content_save_pre', 'wp_filter_kses' ),
		) );

		$filter_suspension->start();
		$r = wp_insert_post( $postarr, true );
		$filter_suspension->stop();

		if ( is_wp_error( $r ) ) {
			throw new Exception( $r->get_error_message() );
		}

		$post_id = $r;
		update_post_meta( $post_id, 'src_widget_id', $params['src_widget_id'] ); // since WP will de-dup the $widget_id provided in the post_name above

		$response = $this->plugin->get_exported_data_from_post( $post_id );

		return $response;
	}

	/**
	 * DELETE
	 *
	 * @param $params
	 * @throws Public_Exception
	 * @return bool
	 */
	public function delete( $params ) {
		$params = $this->validate_params( $params );
		if ( empty( $params['post_id'] ) ) {
			throw new Public_Exception( 'missing param: post_id', 400 );
		}
		if ( ! empty( $params['check_capabilities'] ) && ! current_user_can( $this->plugin->get_post_type_object()->cap->delete_post, $params['post_id'] ) ) {
			throw new Public_Exception( 'unauthorized', 403 );
		}

		$r = wp_delete_post( $params['post_id'], true );
		if ( false === $r ) {
			throw new Public_Exception( 'failed to delete' );
		}

		return true;
	}
}
