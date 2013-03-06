<?php

/**
 * Base backend class for accessing and saving echo notification data,
 * this class should only provide all the necessary interfaces and
 * implementation should be provided in each child class
 */
abstract class MWEchoBackend {

	private static $cache = array();

	/**
	 * Factory to initialize a backend class
	 * @param $backend string
	 * @return MWEchoBackend
	 * @throws MWException
	 */
	public static function factory( $backend ) {
		$backend = strval( $backend );

		$className = 'MW' . $backend . 'EchoBackend';

		if ( !class_exists( $className ) ) {
			throw new MWException( "$backend backend is not supported" );
		}

		if ( !isset( self::$cache[$backend] ) ) {
			self::$cache[$backend] = new $className();
		}

		return self::$cache[$backend];
	}

	/**
	 * Get the enabled events for a user, which excludes user-dismissed events
	 * from the general enabled events
	 * @param $user User
	 * @param $outputFormat string
	 * @return array
	 */
	protected function getUserEnabledEvents( $user, $outputFormat ) {
		global $wgEchoNotifications;
		$eventTypesToLoad = $wgEchoNotifications;
		foreach ( $eventTypesToLoad as $eventType => $eventData ) {
			$category = EchoNotificationController::getNotificationCategory( $eventType );
			// Make sure the user is eligible to recieve this type of notification
			if ( !EchoNotificationController::getCategoryEligibility( $user, $category ) ) {
				unset( $eventTypesToLoad[$eventType] );
			}
			if ( !$user->getOption( 'echo-subscriptions-' . $outputFormat . '-' . $category ) ) {
				unset( $eventTypesToLoad[$eventType] );
			}
		}

		return array_keys( $eventTypesToLoad );
	}

	/**
	 * Create a new notification
	 * @param $row array
	 */
	abstract public function createNotification( $row );

	/**
	 * Load notifications based on the parameters
	 * @param $user User the user to get notifications for
	 * @param $limit int The maximum number of notifications to return
	 * @param $timestamp int The timestamp to start from
	 * @param $offset int The notification event id to start from
	 * @return array
	 */
	abstract public function loadNotifications( $user, $limit, $timestamp, $offset );

	/**
	 * Get the bundle data for user/hash
	 * @param $user User
	 * @param $bundleHash string The hash used to identify a set of bundle-able events
	 * @param $type string 'web'/'email'
	 * @return ResultWrapper|bool
	 */
	abstract public function getRawBundleData( $user, $bundleHash, $type = 'web' );

	/**
	 * Get the last bundle stat - read_timestamp & bundle_display_hash
	 * @param $user User
	 * @param $bundleHash string The hash used to identify a set of bundle-able events
	 * @return ResultWrapper|bool
	 */
	abstract public function getLastBundleStat( $user, $bundleHash );

	/**
	 * Create an Echo event
	 * @param $row array
	 * @return int
	 */
	abstract public function createEvent( $row );

	/**
	 * Load an Echo event
	 * @param $id int
	 * @param $fromMaster bool
	 */
	abstract public function loadEvent( $id, $fromMaster );

	/**
	 * Update the extra data for an Echo event
	 * @param $event EchoEvent
	 */
	abstract public function updateEventExtra( $event );

	/**
	 * Mark notifications as read for a user
	 * @param $user User
	 * @param $eventIDs array
	 */
	abstract public function markRead( $user, $eventIDs );

	/**
	 * Retrieves number of unread notifications that a user has.
	 * @param $user User object to check notifications for
	 * @param $dbSource string use master or slave storage to pull count
	 * @return ResultWrapper|bool
	 */
	abstract public function getNotificationCount( $user, $dbSource );

}
