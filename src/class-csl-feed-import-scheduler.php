<?php
/**
 * CSL Feed Import Scheduler
 *
 * Manages the cron events used to schedule imports.
 *
 * @package  CSL_Feed_Importer
 */

namespace Lift\Campus_Insiders\CSL_Feed_Importer;

/**
 * Class: CSL_Feed_Import_Scheduler
 *
 * Class containing the methods to spawn, despawn, and reschedule feed imports.
 *
 * @since  v0.1.0
 */
class CSL_Feed_Import_Scheduler {

	/**
	 * Cron Hook
	 *
	 * @var string  The cron hook to attach the import event to.
	 */
	protected $hook = 'csl_feed_import';

	/**
	 * Recurrence
	 *
	 * @var string  Name to reference the interval.
	 */
	protected $recurrence = 'csl_feed_import_recurrence';

	/**
	 * Interval
	 *
	 * @var integer  Cron interval to run the importer.
	 */
	public $interval = 14400;

	/**
	 * Constructor
	 *
	 * Sets up the Scheduler with the options from the settings page
	 *
	 * @return  CSL_Feed_Import_Scheduler Instance of self.
	 */
	public function __construct() {
		// Extract interval from options.
		$opts = get_option( 'csl_feed_import_options' );

		if ( is_array( $opts ) && isset( $opts['interval'] ) ) {
			$this->interval = absint( $opts['interval'] ) * HOUR_IN_SECONDS;
		}

		return $this;
	}

	/**
	 * Setup
	 *
	 * Attaches the necessary options to run the cron events and includes our interval.
	 * in the list of recurrence schedules. On admin, checks to see if a cron task is scheduled,
	 * and if it doesn't find one, schedules it.
	 *
	 * @return CSL_Feed_Import_Scheduler Instance of self.
	 */
	public function setup() {
		add_action( $this->hook, array( $this, 'import' ) );
		add_filter( 'cron_schedules', array( $this, 'add_recurrence' ), 10, 1 );
		add_action( 'update_option', array( $this, 'reset_schedule' ), 10, 3 );
		add_action( 'admin_footer', array( $this, 'schedule_next' ), 10, 3 );
		return $this;
	}

	/**
	 * Schedule Next
	 *
	 * Searches the cron schedule for the next appearance of the import, and adds one
	 * if it doesn't find it.
	 *
	 * @param  int $time Timestamp of when the initial event should run.
	 * @return bool|void       False if nothing was added, void if an event was scheduled.
	 */
	public function schedule_next( $time = null ) {
		$scheduled = false;
		if ( ! wp_next_scheduled( $this->hook ) ) {
			if ( ! $time ) {
				$time = time();
			}
			$scheduled = wp_schedule_event( $time, $this->recurrence, $this->hook );
		}
		return $scheduled;
	}

	/**
	 * Add Recurrence Schedules
	 *
	 * Adds our recurrence schedule to WordPress' list.
	 *
	 * @param   array $schedules A list of recurrence schedules.
	 * @return  array            A filtered list of recurrence schedules.
	 */
	public function add_recurrence( array $schedules ) {
		$schedules[ $this->recurrence ] = array(
			'interval' => $this->interval,
			'display' => 'Every ' . strval( ($this->interval / HOUR_IN_SECONDS ) ) . ' hours.',
			);

		return $schedules;
	}

	/**
	 * Import
	 *
	 * Imports items from CSL RSS Feed
	 *
	 * @return CSL_Feed_Importer An instance of CSL_Feed_Importer.
	 */
	public function import() {
		$importer = new CSL_Feed_Importer;
		$importer->import();

		return $importer;
	}

	/**
	 * Reset Schedule
	 *
	 * Resets the sync schedule, usually attached to the `update_option` hook.
	 *
	 * @param  string $option Option name.
	 * @param  mixed  $old    Old value.
	 * @param  mixed  $new    New Value.
	 * @return void
	 */
	public function reset_schedule( $option, $old, $new ) {
		if ( 'csl_feed_import_options' !== $option ) {
			return;
		}

		if ( $old['interval'] !== $new['interval'] ) {
			$this->clear();
			$this->interval = absint( $new['interval'] ) * DAY_IN_SECONDS;
			$this->schedule_next();
		}
	}

	/**
	 * Clear
	 *
	 * Clears all the cron hooks
	 *
	 * @return void
	 */
	public function clear() {
		wp_clear_scheduled_hook( $this->hook );
	}
}
