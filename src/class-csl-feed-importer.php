<?php
/**
 * CSL Feed Importer
 *
 * Imports the CSL RSS Feed as WordPress Posts.
 *
 * @package  CSL_Feed_Importer
 */

namespace Lift\Campus_Insiders\CSL_Feed_Importer;

/**
 * Class: CSL_Feed_Importer
 *
 * Class containing the methods to import an RSS feed from CSL as WordPress posts.
 *
 * @since  v0.1.0
 */
class CSL_Feed_Importer {

	/**
	 * The Url to the Feed
	 */
	const FEED = 'https://cstarleague.com/feed.rss';

	/**
	 * Raw Feed
	 *
	 * @var string The raw contents of the feed.
	 */
	protected $raw_feed;

	/**
	 * Parsed Feed
	 *
	 * @var \SimpleXMLElement A SimpleXMLElement representing the parsed raw feed
	 */
	protected $parsed_feed;

	/**
	 * Constructor
	 *
	 * Gets the feed contents and sets up member variables from the feed.
	 *
	 * @return  CSL_Feed_Importer Instance of self.
	 */
	public function __construct() {
		$this->raw_feed = $this->get_feed_contents();
		$this->parsed_feed = $this->parse_feed( $this->raw_feed );

		return $this;
	}

	/**
	 * Get Feed Contents
	 *
	 * @return string The raw contents of the feed.
	 */
	protected function get_feed_contents() {
		return wpcom_vip_file_get_contents( self::FEED );
	}

	/**
	 * Parse Feed
	 *
	 * @param  string $feed The raw contents of the feed.
	 * @return \SimpleXMLElement A SimpleXMLElement representing the parsed raw feed.
	 */
	protected function parse_feed( $feed ) {
		try {
			$parsed = new \SimpleXMLElement( $feed );
		} catch ( \Exception $e ) {
			$this->handle_failure();
			$parsed = null;
		}

		return $parsed;
	}

	/**
	 * Import
	 *
	 * Reads out the items in the feed channel, initializes a new instance of
	 * CSL_Feed_Item_Importer with the item, and imports it into WordPress.
	 *
	 * @return CSL_Feed_Importer Instance of self.
	 */
	public function import() {
		if ( ! is_null( $this->parsed_feed ) && ! empty( $this->parsed_feed->channel->item ) ) {
			foreach ( $this->parsed_feed->channel->item as $item ) {
				$item_importer = new CSL_Feed_Item_Importer( $item );
				$item_importer->import();
			}
		}

		return $this;
	}

	/**
	 * Handle Failure
	 *
	 * Handles a failure of the import caused by SimpleXML not being able to parse a
	 * raw feed, or if the feed was not fetched at all.  Resets the current cron
	 * schedule and resets to run again in 20 minutes.
	 *
	 * @return void
	 */
	public function handle_failure() {
		$scheduler = new CSL_Feed_Import_Scheduler;

		// Clear all runs.
		$scheduler->clear();

		// Schedule another run.
		$scheduler->schedule_next( time() + ( 20 * MINUTE_IN_SECONDS ) );
	}
}
