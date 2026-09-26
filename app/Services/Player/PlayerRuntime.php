<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Services\MediaService;
use CodeIgniter\Database\BaseConnection;

final class PlayerRuntime
{
    public readonly AdmissionService $admission;
    public readonly AssessmentService $assessment;
    public readonly PracticeService $practice;

    public function __construct(?BaseConnection $db = null, ?MediaService $media = null)
    {
        $store = new PlayerStore($db);
        $credentials = new Credentials();
        $definitions = new DefinitionService($store, $media ?? new MediaService($store->db));
        $this->practice = new PracticeService($store, $credentials, $definitions);
        $this->admission = new AdmissionService($store, $credentials, $definitions, $this->practice);
        $this->assessment = new AssessmentService($store, $definitions, new ScoringService());
    }
}
