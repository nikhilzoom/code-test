<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AuthService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to seed the users table from a JSON fixture file.
 *
 * Reads user records from `src/DataFixtures/users.json` and registers each
 * one via {@see AuthService::register()}, which hashes passwords with bcrypt.
 * Already-existing emails are skipped rather than treated as errors.
 *
 * Usage:
 *   php bin/console app:seed:users
 *   php bin/console app:seed:users --fixture=/path/to/custom.json
 *
 * @package App\Command
 */
#[AsCommand(
    name: 'app:seed:users',
    description: 'Seed the users table from src/DataFixtures/users.json',
)]
class SeedUsersCommand extends Command
{
    /**
     * The authentication service used to register users.
     *
     * @var AuthService
     */
    private AuthService $authService;

    /**
     * Construct a new SeedUsersCommand.
     *
     * @param AuthService $authService The service that handles user registration.
     */
    public function __construct(AuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    /**
     * Configure the command options and arguments.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addOption(
            'fixture',
            null,
            InputOption::VALUE_OPTIONAL,
            'Absolute path to a JSON fixture file (defaults to src/DataFixtures/users.json)',
        );
    }

    /**
     * Execute the seed command.
     *
     * Reads the JSON fixture file, iterates over each user record, and
     * registers them via AuthService. Skips duplicates gracefully.
     *
     * @param InputInterface  $input  The console input interface.
     * @param OutputInterface $output The console output interface.
     *
     * @return int Command::SUCCESS or Command::FAILURE
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fixturePath = $input->getOption('fixture')
            ?? __DIR__ . '/../DataFixtures/users.json';

        if (!file_exists($fixturePath)) {
            $io->error(sprintf('Fixture file not found: %s', $fixturePath));
            return Command::FAILURE;
        }

        /** @var array<int, array<string, string>>|null $users */
        $users = json_decode((string) file_get_contents($fixturePath), true);

        if (!is_array($users)) {
            $io->error('Fixture file is not valid JSON.');
            return Command::FAILURE;
        }

        $seeded  = 0;
        $skipped = 0;

        foreach ($users as $data) {
            if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
                $io->warning(sprintf('Skipping incomplete record: %s', json_encode($data)));
                $skipped++;
                continue;
            }

            try {
                $this->authService->register($data);
                $io->text(sprintf('  <info>✔</info> Seeded: %s <%s>', $data['name'], $data['email']));
                $seeded++;
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'already registered')) {
                    $io->text(sprintf('  <comment>–</comment> Skipped (exists): %s <%s>', $data['name'], $data['email']));
                    $skipped++;
                } else {
                    $io->error(sprintf('Failed to seed %s: %s', $data['email'], $e->getMessage()));
                    return Command::FAILURE;
                }
            }
        }

        $io->success(sprintf('Done. Seeded: %d, Skipped: %d.', $seeded, $skipped));

        return Command::SUCCESS;
    }
}
