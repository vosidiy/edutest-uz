<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Services\AttemptSnapshotService;
use App\Services\MediaService;
use CodeIgniter\Database\BaseConnection;

final class PlayerRuntime
{
    public readonly AdmissionService $admission;
    public readonly AssessmentService $assessment;

    public function __construct(?BaseConnection $db = null, ?MediaService $media = null, ?AttemptSnapshotService $snapshots = null)
    {
        $store = new PlayerStore($db);
        $media ??= new MediaService($store->db);
        $snapshots ??= new AttemptSnapshotService($store->db, $media);
        $credentials = new Credentials();
        $this->admission = new AdmissionService($store, $credentials, new DefinitionService(), $snapshots);
        $this->assessment = new AssessmentService($store, $snapshots, new ScoringService());
    }
}
