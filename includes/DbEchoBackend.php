<?php

/**
 * Database backend for echo notification
 */
class MWDbEchoBackend extends MWEchoBackend {

	/**
	 * Database object
	 */
	private $dbr;
	private $dbw;

	protected function __construct() {
		$this->dbr = MWEchoDbFactory::getDB( DB_SLAVE );
		$this->dbw = MWEchoDbFactory::getDB( DB_MASTER );
	}

	/**
	 * @param $row array
	 */
	public function createNotification( $row ) {
		$this->dbw->begin( __METHOD__ );
		// reset the base if this notification has a display hash
		if ( $row['notification_bundle_display_hash'] ) {
			$this->dbw->update(
				'echo_notification',
				array( 'notification_bundle_base' => 0 ),
				array(
					'notification_user' => $row['notification_user'],
					'notification_bundle_display_hash' => $row['notification_bundle_display_hash'],
					'notification_bundle_base' => 1
				),
				__METHOD__
			);
		}

		$row['notification_timestamp'] = $this->dbw->timestamp( $row['notification_timestamp'] );
		$this->dbw->insert( 'echo_notification', $row, __METHOD__ );
		$this->dbw->commit( __METHOD__ );
	}

	/**
	 * @param $user User the user to get notifications for
	 * @param $limit int The maximum number of notifications to return
	 * @param $timestamp int The timestamp to start from
	 * @param $offset int The notification event id to start from
	 * @param $outputFormat string The output format of the notifications (web,
	 *    email, etc.)
	 * @return array
	 */
	public function loadNotifications( $user, $limit, $timestamp, $offset, $outputFormat = 'web' ) {
		$eventTypesToLoad = $this->getUserEnabledEvents( $user, $outputFormat );
		if ( !$eventTypesToLoad ) {
			return array();
		}

		// Look for notifications with base = 1
		$conds = array(
			'notification_user' => $user->getID(),
			'event_type' => $eventTypesToLoad,
			'notification_bundle_base' => 1
		);

		// Start points are specified
		if ( $timestamp && $offset ) {
			$conds[] = 'notification_timestamp <= ' . $this->dbr->addQuotes( $this->dbr->timestamp( $timestamp ) );
			$conds[] = 'notification_event < ' . intval( $offset );
		}

		$res = $this->dbr->select(
			array( 'echo_notification', 'echo_event' ),
			'*',
			$conds,
			__METHOD__,
			array(
				'ORDER BY' => 'notification_timestamp DESC, notification_event DESC',
				'LIMIT' => $limit,
			),
			array(
				'echo_event' => array( 'LEFT JOIN', 'notification_event=event_id' ),
			)
		);

		return iterator_to_array( $res, false );
	}

	/**
	 * @param $user User
	 * @param $bundleHash string the bundle hash
	 * @param $type string
	 * @return ResultWrapper|bool
	 */
	public function getRawBundleData( $user, $bundleHash, $type = 'web' ) {
		// We only display 99+ if the number is over 100, we can do limit 250, this should be sufficient
		// to return 99 distinct group iterators, avoid select count( distinct ) for the folliwng:
		// 1. it will not scale for large volume data
		// 2. notification may have random grouping iterator
		// 2. agent may be anonymous, can't do distinct over two columens: event_agent_id and event_agent_ip
		if ( $type == 'web' ) {
			$res = $this->dbr->select(
				array( 'echo_notification', 'echo_event' ),
				array(
					'event_agent_id',
					'event_agent_ip',
					'event_extra',
					'event_page_namespace',
					'event_page_title'
				),
				array(
					'notification_event=event_id',
					'notification_user' => $user->getId(),
					'notification_bundle_base' => 0,
					'notification_bundle_display_hash' => $bundleHash
				),
				__METHOD__,
				array( 'ORDER BY' => 'notification_timestamp DESC', 'LIMIT' => 250 )
			);
		// this would be email for now
		} else {
			$res = $this->dbr->select(
				array( 'echo_email_batch', 'echo_event' ),
				array(
					'event_agent_id',
					'event_agent_ip',
					'event_extra',
					'event_page_namespace',
					'event_page_title'
				),
				array(
					'eeb_event_id=event_id',
					'eeb_user_id' => $user->getId(),
					'eeb_event_hash' => $bundleHash
				),
				__METHOD__,
				array( 'ORDER BY' => 'eeb_event_id DESC', 'LIMIT' => 250 )
			);
		}

		return $res;
	}

	/**
	 * Get the last bundle stat - read_timestamp & bundle_display_hash
	 * @param $user User
	 * @param $bundleHash string The hash used to identify a set of bundle-able events
	 * @return ResultWrapper|bool
	 */
	public function getLastBundleStat( $user, $bundleHash ) {
		$res = $this->dbr->selectRow(
			array( 'echo_notification' ),
			array( 'notification_read_timestamp', 'notification_bundle_display_hash' ),
			array(
				'notification_user' => $user->getId(),
				'notification_bundle_hash' => $bundleHash
			),
			__METHOD__,
			array( 'ORDER BY' => 'notification_timestamp DESC', 'LIMIT' => 1 )
		);
		return $res;
	}

	/**
	 * @param $row array
	 * @return int
	 */
	public function createEvent( $row ) {
		$id = $this->dbw->nextSequenceValue( 'echo_event_id' );

		$this->dbw->insert( 'echo_event', $row, __METHOD__ );

		if ( !$id ) {
			$id = $this->dbw->insertId();
		}

		return $id;
	}

	/**
	 * @param $id int
	 * @param $fromMaster bool
	 * @return ResultWrapper
	 * @throws MWException
	 */
	public function loadEvent( $id, $fromMaster = false ) {
		$db = $fromMaster ? $this->dbw : $this->dbr;

		$row = $db->selectRow( 'echo_event', '*', array( 'event_id' => $id ), __METHOD__ );

		if ( !$row && !$fromMaster ) {
			return $this->loadEvent( $id, true );
		} elseif ( !$row ) {
			throw new MWException( "No EchoEvent found with ID: $id" );
		}

		return $row;
	}

	/**
	 * @param $event EchoEvent
	 */
	public function updateEventExtra( $event ) {
		$this->dbw->update(
			'echo_event',
			array( 'event_extra' => $event->serializeExtra() ),
			array( 'event_id' => $event->getId() ),
			__METHOD__
		);
	}

	/**
	 * @param $user User
	 * @param $eventIDs array
	 */
	public function markRead( $user, $eventIDs ) {
		if ( !$eventIDs ) {
			return;
		}
		$this->dbw->update(
			'echo_notification',
			array( 'notification_read_timestamp' => $this->dbw->timestamp( wfTimestampNow() ) ),
			array(
				'notification_user' => $user->getId(),
				'notification_event' => $eventIDs,
			),
			__METHOD__
		);
	}

	/**
	 * @param $user User object to check notifications for
	 * @param $dbSource string use master or slave storage to pull count
	 * @return int
	 */
	public function getNotificationCount( $user, $dbSource ) {
		// double check
		if ( !in_array( $dbSource, array( DB_SLAVE, DB_MASTER ) ) ) {
			$dbSource = DB_SLAVE;
		}

		$eventTypesToLoad = $this->getUserEnabledEvents( $user, 'web' );

		if ( !$eventTypesToLoad ) {
			return false;
		}

		global $wgEchoMaxNotificationCount;

		$db = MWEchoDbFactory::getDB( $dbSource );
		$res = $db->select(
			array( 'echo_notification', 'echo_event' ),
			array( 'notification_event' ),
			array(
				'notification_user' => $user->getId(),
				'notification_bundle_base' => 1,
				'notification_read_timestamp' => null,
				'event_type' => $eventTypesToLoad,
			),
			__METHOD__,
			array( 'LIMIT' => $wgEchoMaxNotificationCount + 1 ),
			array(
				'echo_event' => array( 'LEFT JOIN', 'notification_event=event_id' ),
			)
		);
		return $db->numRows( $res );
	}

}
