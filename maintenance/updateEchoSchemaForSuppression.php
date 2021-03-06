<?php
/**
 * Update event_page_id in echo_event based on event_page_title and
 * event_page_namespace
 *
 * @ingroup Maintenance
 */
require_once ( getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php' );

/**
 * Maintenance script that populates the event_page_id column of echo_event
 *
 * @ingroup Maintenance
 */
class UpdateEchoSchemaForSuppression extends LoggedUpdateMaintenance {

	/**
	 * @var string The table to update
	 */
	protected $table = 'echo_event';

	/**
	 * @var string The primary key column of the table to update
	 */
	protected $idField = 'event_id';

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 500 );
	}

	public function getUpdateKey() {
		return __CLASS__;
	}

	public function doDBUpdates() {
		global $wgEchoCluster;

		$reader = new BatchRowIterator( MWEchoDbFactory::getDB( DB_SLAVE ), $this->table, $this->idField, $this->mBatchSize );
		$reader->addConditions( array(
			"event_page_title IS NOT NULL",
			"event_page_id" => null,
		) );
		$reader->setFetchColumns( array( 'event_page_namespace', 'event_page_title', 'event_extra', 'event_type' ) );

		$updater = new BatchRowUpdate(
			$reader,
			new BatchRowWriter( MWEchoDbFactory::getDB( DB_MASTER ), $this->table, $wgEchoCluster ),
			new EchoSuppressionRowUpdateGenerator
		);
		$updater->setOutput( function ( $text ) {
			$this->output( $text );
		} );
		$updater->execute();
		return true;
	}
}

$maintClass = 'UpdateEchoSchemaForSuppression'; // Tells it to run the class
require_once ( RUN_MAINTENANCE_IF_MAIN );
