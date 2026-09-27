<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Services\MediaService;
use App\Services\QuizPaperService;
use CodeIgniter\Database\BaseConnection;

final class PlayerRuntime
{
    public readonly AdmissionService $admission;
    public readonly AssessmentService $assessment;
    public readonly PracticeService $practice;

    public function __construct(?BaseConnection $db = null, ?MediaService $media = null, ?QuizPaperService $papers = null)
    {
        $store = new PlayerStore($db);
        $media ??= new MediaService($store->db);
        $credentials = new Credentials();
        $definitions = new DefinitionService();
        $papers ??= new QuizPaperService($store->db, $media);
        $this->practice = new PracticeService($store, $credentials, $papers);
        $this->admission = new AdmissionService($store, $credentials, $definitions, $this->practice, $papers);
        $this->assessment = new AssessmentService($store, $papers, new ScoringService());
    }
}
