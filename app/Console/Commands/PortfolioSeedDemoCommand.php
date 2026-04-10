<?php

namespace App\Console\Commands;

use App\Actions\SeedDemoPortfolio;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('portfolio:seed-demo')]
#[Description('Seed deterministic demo data for local portfolio analysis review')]
class PortfolioSeedDemoCommand extends Command
{
    public function __construct(
        private readonly SeedDemoPortfolio $seedDemoPortfolio,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $seeded = $this->seedDemoPortfolio->handle();

        $this->info('Demo portfolio data seeded successfully.');
        $this->newLine();
        $this->line('Login credentials:');
        $this->line('  Admin: demo-admin@portfolio.test / password');
        $this->line('  Analyst: demo-analyst@portfolio.test / password');
        $this->line('  Viewer: demo-viewer@portfolio.test / password');
        $this->newLine();
        $this->line(sprintf('Classification rules: %d', $seeded['counts']['rules']));
        $this->line(sprintf('Demo submissions: %d', $seeded['counts']['submissions']));
        $this->line(sprintf('Demo documents: %d', $seeded['counts']['documents']));
        $this->line(sprintf('Demo extracted assets: %d', $seeded['counts']['assets']));
        $this->line(sprintf('Processing events: %d', $seeded['counts']['events']));
        $this->line(sprintf('Audit logs: %d', $seeded['counts']['audit_logs']));

        return self::SUCCESS;
    }
}
