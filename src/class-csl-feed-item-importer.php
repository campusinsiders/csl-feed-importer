<?php
/**
 * CSL Single Item Importer
 *
 * Imports a single article from the CSL RSS Feed as a WordPress Post inside a provided taxonomy.
 *
 * @package  CSL_Feed_Importer
 */

namespace Lift\Campus_Insiders\CSL_Feed_Importer;

/**
 * Class: CSL_Feed_Item_Importer
 *
 * Class containing the methods to import an RSS channel item from CSL as a WordPress post.
 *
 * @since  v0.1.0
 */
class CSL_Feed_Item_Importer {

	/**
	 * Item to Insert
	 *
	 * @var \SimpleXMLElement  The item to be inserted
	 */
	protected $item;

	/**
	 * Array of Post Arguments
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_insert_post/
	 * @var  array An array of post parameters
	 */
	protected $post;

	/**
	 * Post ID of Inserted Item
	 *
	 * @var integer|\WP_Error The Post ID if successful, 0 or WP_Error on failure
	 */
	public $post_id = 0;

	/**
	 * Featured Image ID
	 *
	 * @var integer The featured image attachment ID, or 0 if not set.
	 */
	public $featured_image = 0;

	/**
	 * Inserted
	 *
	 * @var boolean  False until the item is successfully inserted as a post
	 */
	public $inserted = false;

	/**
	 * Constructor
	 *
	 * @param \SimpleXMLElement $item The item to be inserted.
	 * @return  CSL_Feed_Item_Importer Instance of self
	 */
	public function __construct( \SimpleXMLElement $item ) {
		$this->item = $item;
		$this->post = array();

		$this->ensure_core_dependencies();

		return $this;
	}

	/**
	 * Ensure Core Dependencies
	 *
	 * The function `post_exists` is not always available, so we need to make sure it's
	 * available by checking for its existence and loading the required file if it's
	 * missing.
	 *
	 * @return void
	 */
	public function ensure_core_dependencies() {
		if ( ! function_exists( 'posts_exists' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/post.php' );
		}

		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
		}
	}

	/**
	 * Import
	 *
	 * Calls the necessary handlers and sets up the $post property. Ensure the post should
	 * be inserted, then inserts it.
	 *
	 * @return  CSL_Feed_Item_Importer Instance of self
	 */
	public function import() {
		$this->handle_title()
			->handle_excerpt()
			->handle_body()
			->handle_author()
			->handle_date()
			->handle_guid()
			->handle_post_status();

		if ( $this->post_should_insert() ) {
			$this->insert_as_post();

			if ( $this->post_id ) {
				// Map the terms.
				$this->map_terms();

				// Map the featured image.
				$image_mapped = $this->map_featured_image();

				// Map default featured image, if mapping provided image failed.
				if ( false === $image_mapped || is_wp_error( $image_mapped ) ) {
					$this->map_default_featured_image();
				}
			}
		}

		return $this;
	}

	/**
	 * Handle Title
	 *
	 * Strips all tags and sets the provided title on the $post property.
	 *
	 * @return  CSL_Feed_Item_Importer Instance of self
	 */
	public function handle_title() {
		$this->post['post_title'] = wp_strip_all_tags( $this->item->title );

		return $this;
	}

	/**
	 * Handle Excerpt
	 *
	 * Strips all tags from the item description and sets the excerpt on the $post property.
	 *
	 * @return  CSL_Feed_Item_Importer Instance of self
	 */
	public function handle_excerpt() {
		$this->post['post_excerpt'] = wp_strip_all_tags( $this->item->description );

		return $this;
	}

	/**
	 * Handle Body
	 *
	 * Decodes the htmlspecialchars present on the item body and sets the post_content on the $post
	 * property.
	 *
	 * @return  CSL_Feed_Item_Importer Instance of self
	 */
	public function handle_body() {
		$content = htmlspecialchars_decode( $this->item->body, ENT_COMPAT | ENT_HTML5 );
		$content = wp_kses_post( $content );
		$this->post['post_content'] = $content;
		return $this;
	}

	/**
	 * Handle Author
	 *
	 * Reads the author assigned to publish the feed from the option table and sets the post_author
	 * on the $post property.
	 *
	 * @return  CSL_Feed_Item_Importer Instance of self
	 */
	public function handle_author() {
		$this->post['post_author'] = 1;
		$options = \get_option( 'csl_feed_import_options' );

		if ( false !== $options && isset( $options['author'] ) ) {
			$this->post['post_author'] = $options['author'];
		}

		return $this;
	}

	/**
	 * Handle Date
	 *
	 * Transforms the provided publish date into a DateTime string that accounts for the blog
	 * timezone.  Sets the post_date on the $post property.
	 *
	 * @return  CSL_Feed_Item_Importer Instance of self
	 */
	public function handle_date() {
		$date = new \DateTime;
		$date->setTimestamp( strtotime( $this->item->pubDate ) );
		$date->setTimezone( new \DateTimeZone( get_option( 'timezone_string' ) ) );
		$this->post['post_date'] = $date->format( 'Y-m-d H:i:s' );

		return $this;
	}

	/**
	 * Handle GUID
	 *
	 * Reads the guid from the item and sets the guid on the $post property.
	 *
	 * @return  CSL_Feed_Item_Importer Instance of self
	 */
	public function handle_guid() {
		$this->post['guid'] = sanitize_text_field( $this->item->guid );

		return $this;
	}

	/**
	 * Handle Post Status
	 *
	 * Reads the User defined Post Status from the options page and sets this as the
	 * post_status.  If undefined in the options, defaults to publish.
	 *
	 * @return CSL_Feed_Item_Importer Instance of self
	 */
	public function handle_post_status() {
		$this->post['post_status'] = 'publish';
		$options = \get_option( 'csl_feed_import_options' );

		if ( false !== $options && isset( $options['post_status'] ) ) {
			if ( in_array( $options['post_status'], get_post_statuses(), true ) ) {
				$this->post['post_status'] = $options['post_status'];
			}
		}

		return $this;
	}

	/**
	 * Post Should Insert
	 *
	 * Decides whether the post should be inserted based on whether it already exists, has content,
	 * and has a title.
	 *
	 * @return boolean True if post doesn't exist and content and title are present.  False otherwise.
	 */
	protected function post_should_insert() {
		if ( ! isset( $this->post['post_title'] ) || empty( $this->post['post_title'] ) ) {
			return false;
		}

		if ( \post_exists( $this->post['post_title'], null, $this->post['post_date'] ) ) {
			return false;
		}

		if ( ! isset( $this->post['post_content'] ) || empty( $this->post['post_content'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Insert as Post
	 *
	 * Takes the $post array property and inserts it as a WordPress post.
	 *
	 * @uses  \wp_insert_post()
	 *
	 * @return  CSL_Feed_Item_Importer Instance of self
	 */
	protected function insert_as_post() {
		$this->post_id = wp_insert_post( $this->post );

		if ( $this->post_id && ! is_wp_error( $this->post_id ) ) {
			$this->inserted = true;
		}

		return $this;
	}

	/**
	 * Map Terms
	 *
	 * Maps the correct terms to the WP_Post object that was inserted.
	 *
	 * @return  mixed[] An array comprising of arrays of term taxonomy ids or WP_Errors
	 */
	protected function map_terms() {
		if ( ! $this->post_id ) {
			return [];
		}

		$mappings = array(
			wp_set_object_terms( $this->post_id, $this->get_tags(), 'post_tag', true ),
			wp_set_object_terms( $this->post_id, [ 'Collegiate Starleague' ], 'scci_conference', true ),
			wp_set_object_terms( $this->post_id, [ 'eSports' ], 'scci_school', true ),
		);

		return $mappings;
	}

	/**
	 * Get Tags
	 *
	 * @return array An array of post tags to map to the post
	 */
	protected function get_tags() {
		$default_tags = array(
			'eSports',
			);

		/**
		 * Filter: csl_feed_post_tags
		 *
		 * @param  array             $tags     An array of tag slugs to map to the post.
		 * @param  int               $post_id  The Post ID of the post we're adding tags to.
		 * @param  \SimpleXMLElement $item     The Feed Item that was imported.
		 */
		return apply_filters( 'csl_feed_post_tags', $default_tags, $this->post_id, $this->item );
	}

	/**
	 * Map Featured Media
	 *
	 * If the item provides a <featured_image> this method will download the image, and set it
	 * as the post thumbnail.
	 *
	 * @internal  Warning: Using `@` operator and `unlink()`.  As far as I know, we have to do
	 *            it this way. -CC
	 * @return bool|\WP_Error True on success, false or WP_Error if failure.
	 */
	protected function map_featured_image() {
		$image_url = $this->item->featured_image;

		// Bail if we don't have a post to attach to, or if the url provided isn't valid.
		if ( ! $this->post_id || ! filter_var( (string) $image_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Download the image.
		$tmp = download_url( $image_url, 30 );

		// Return error object if there was an error.
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Use the title as a description.
		$desc = \get_the_title( $this->post_id );
		$file_array = array();

		// Name the file.
		preg_match( '/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $image_url, $matches );
		$file_array['name'] = basename( $matches[0] );
		$file_array['tmp_name'] = $tmp;

		// Sideload the image.
		$thumb_id = media_handle_sideload( $file_array, $this->post_id, $desc );

		// If error storing permanently, delete the temp and return the error.
		if ( is_wp_error( $thumb_id ) ) {
			@unlink( $file_array['tmp_name'] );
			return $thumb_id;
		}

		// Store the feautured image ID and return the result of setting it on the post.
		$this->featured_image = absint( $thumb_id );
		return set_post_thumbnail( $this->post_id, $this->featured_image );
	}

	/**
	 * Map Default Featured Media
	 *
	 * Reads the default media from the options page and if defined there, sets it as the imported
	 * post thumbnail.  Should not run if featured image was set to provided image.
	 *
	 * @return CSL_Feed_Item_Importer Instance of self
	 */
	protected function map_default_featured_image() {
		// Bail early if the featured image is already set.
		if ( $this->featured_image ) {
			return $this;
		}

		$options = \get_option( 'csl_feed_import_options' );

		if ( false !== $options && isset( $options['default_media'] ) ) {
			$this->featured_image = absint( $options['default_media'] );
			set_post_thumbnail( absint( $this->post_id ), $this->featured_image );
		}

		return $this;
	}
}
