<?php

namespace App\Jobs;

use App\Jobs\BaseSyncJob;
use Exception;

/**
 * Class SyncSystempinUsers
 *
 * Synchronizes Systempin users into the local database.
 */
class SyncSystempinUsers extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   *
   * @var int
   */
  public int $priority = 1;

  /**
   * SyncSystempinUsers constructor.
   *
   * @return void
   */
  public function __construct()
  {
    // Initialize any required properties or dependencies
  }

  /**
   * Executes the synchronization process.
   *
   * @return void
   *
   * @throws Exception
   */
  protected function execute(): void
  {
    // Implement the synchronization logic here.
  }
}
