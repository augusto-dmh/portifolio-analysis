<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSubmissionJob;
use App\Models\Submission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('portfolio:reprocess {submission : The submission UUID}')]
#[Description('Dispatch the portfolio processing pipeline for a submission')]
class PortfolioReprocessCommand extends Command
{
    public function handle(): int
    {
        $submission = Submission::query()->find($this->argument('submission'));

        if (! $submission instanceof Submission) {
            $this->error('Submission not found.');

            return self::FAILURE;
        }

        ProcessSubmissionJob::dispatch($submission->getKey());

        $this->info(sprintf(
            'Reprocessing dispatched for submission [%s].',
            $submission->getKey(),
        ));

        return self::SUCCESS;
    }
}
